<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

namespace app\common\service\wechat;

use app\common\exception\AdminResponseException;
use app\common\model\wechat\WechatMenus;
use app\common\service\BaseService;
use Rabbit\service\wechat\OfficialAccount;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 微信菜单服务类
 * Class WechatMenusService
 * @package app\common\service\wechat
 */
class WechatMenusService extends BaseService
{
    /**
     * 缓存标签
     * @var string
     */
    protected string $cacheTag = 'wechat_menus';

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return WechatMenus::class;
    }

    /**
     * 保存菜单（先清空旧数据，再批量写入新菜单树，并同步到微信）
     * @param array $button 前端传入的菜单按钮数组
     * @return bool
     * @throws AdminResponseException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function saveMenus(array $button): bool
    {
        if (count($button) > 3) {
            throw new AdminResponseException('一级菜单最多3个');
        }

        $this->transaction(function () use ($button) {
            // 清空旧菜单（软删除）
            $this->destroy(['status' => 1]);

            $this->batchInsert($button, 0, 0);
        });

        // 同步菜单到微信公众号
        $tree = $this->getMenuTree();
        app()->make(OfficialAccount::class)->createMenu($this->toWechatFormat($tree));

        return true;
    }

    /**
     * 递归批量插入菜单
     * @param array $items
     * @param int $pid
     * @param int $sort
     */
    protected function batchInsert(array $items, int $pid, int $sort): void
    {
        foreach ($items as $index => $item) {
            $record = [
                'pid'      => $pid,
                'name'     => $item['name'] ?? '',
                'type'     => $item['type'] ?? 'click',
                'key'      => $item['key'] ?? '',
                'url'      => $item['url'] ?? '',
                'appid'    => $item['appid'] ?? '',
                'pagepath' => $item['pagepath'] ?? '',
                'sort'     => $sort + $index,
                'status'   => 1,
            ];

            $model = $this->getModel()->create($record);

            // 处理子菜单
            $subButton = $item['sub_button'] ?? [];
            if (!empty($subButton)) {
                $this->batchInsert($subButton, (int)$model->id, 0);
            }
        }
    }

    /**
     * 获取前端可用的菜单树（sub_button 格式）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenusForFrontend(): array
    {
        $list = $this->getModel()->where('status', 1)->order('sort asc, id asc')->select()->toArray();
        return $this->buildFrontendTree($list);
    }

    /**
     * 构建前端使用的菜单树（使用 sub_button 键名）
     * @param array $list
     * @param int $pid
     * @return array
     */
    protected function buildFrontendTree(array $list, int $pid = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int)$item['pid'] === $pid) {
                $children = $this->buildFrontendTree($list, (int)$item['id']);
                $node = [
                    'id'       => $item['id'],
                    'name'     => $item['name'],
                    'type'     => $item['type'],
                    'key'      => $item['key'],
                    'url'      => $item['url'],
                    'appid'    => $item['appid'],
                    'pagepath' => $item['pagepath'],
                    'sort'     => $item['sort'],
                ];
                if (!empty($children)) {
                    $node['sub_button'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }

    /**
     * 获取启用的菜单列表
     * @param array $where
     * @param array $field
     * @param null $sort
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getEnableMenus(array $where = [], array $field = ['*'], $sort = null): array
    {
        $where['status'] = 1;
        return $this->getModel()->getDataList($where, $field, 0, 0, $sort ?: 'sort asc,id asc');
    }

    /**
     * 获取菜单树形结构
     * @param array $where
     * @param array $field
     * @param null $sort
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenuTree(array $where = [], array $field = ['*'], $sort = null): array
    {
        $list = $this->getEnableMenus($where, $field, $sort);
        return $this->buildTree($list);
    }

    /**
     * 构建树形结构
     * @param array $list
     * @param int $pid
     * @return array
     */
    protected function buildTree(array $list, int $pid = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int)$item['pid'] === $pid) {
                $children = $this->buildTree($list, (int)$item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * 将菜单树转换为微信接口所需格式
     * @param array $tree
     * @return array
     */
    protected function toWechatFormat(array $tree): array
    {
        $result = [];
        foreach ($tree as $node) {
            $item = ['name' => $node['name']];
            $children = $node['children'] ?? $node['sub_button'] ?? [];
            if (!empty($children)) {
                $item['sub_button'] = $this->toWechatFormat($children);
            } else {
                $item['type'] = $node['type'];
                switch ($node['type']) {
                    case 'click':
                        $item['key'] = $node['key'];
                        break;
                    case 'view':
                        $item['url'] = $node['url'];
                        break;
                    case 'miniprogram':
                        $item['appid'] = $node['appid'];
                        $item['pagepath'] = $node['pagepath'];
                        $item['url'] = $node['url'];
                        break;
                }
            }
            $result[] = $item;
        }
        return $result;
    }

    /**
     * 获取菜单类型选项
     * @return array
     */
    public function getMenuTypes(): array
    {
        return [
            ['value' => 'click', 'label' => '点击推事件'],
            ['value' => 'view', 'label' => '跳转URL'],
            ['value' => 'miniprogram', 'label' => '跳转小程序'],
        ];
    }
}
