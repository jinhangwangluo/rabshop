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

namespace app\common\service\diy;

use app\common\exception\AdminResponseException;
use app\common\model\diy\DiyPageLink;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\setting\CategoryService;

/**
 * DIY页面链接服务
 */
class DiyPageLinkService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 链接类型
     */
    public const LINK_TYPES = [
        'page' => '页面',
        'url' => '外链',
        'mini' => '小程序',
    ];

    /**
     * 平台类型
     */
    public const PLATFORM_TYPES = [
        'common' => '通用',
        'h5' => 'H5',
        'mp' => '小程序',
        'pc' => 'PC',
    ];

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return DiyPageLink::class;
    }

    /**
     * 获取资源列表
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null): array
    {
        [$page, $limit] = $this->getPagesValue();

        $query = $this->buildListQuery($params)->with([
            'category' => function ($query) {
                $query->field('id,name');
            }
        ]);

        $count = (clone $query)->count();
        $list = $query->order('sort desc,id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['cate_name'] = $item['category']['name'] ?? '';
            unset($item['category']);
        }

        return compact('list', 'count');
    }

    /**
     * 读取资源详情
     */
    public function resourceRead(int|array $id): array
    {
        $model = $this->get($id, ['*'], ['category']);
        if (!$model) {
            throw new AdminResponseException('链接不存在');
        }

        $data = $model->toArray();
        $data['cate_name'] = $data['category']['name'] ?? '';
        unset($data['category']);

        return $data;
    }

    /**
     * 保存链接
     */
    public function resourceSave(array $data)
    {
        $data = $this->normalizeData($data);
        $this->validateData($data);
        return $this->create($data);
    }

    /**
     * 更新链接
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $id = (int)$id;
        $model = $this->get($id);
        if (!$model) {
            throw new AdminResponseException('链接不存在');
        }

        $data = $this->normalizeData($data);
        $this->validateData($data, $id);
        $this->update($id, $data);
        return true;
    }

    /**
     * 获取选链列表
     */
    public function getSelectorList(array $params = []): array
    {
        [$page, $limit] = $this->getPagesValue();
        $params['status'] = 1;

        $query = $this->buildListQuery($params);
        $count = (clone $query)->count();
        $list = $query->field([
            'id', 'cate_id', 'link_name', 'link_url', 'link_type', 'platform_type',
            'mini_appid', 'mini_path', 'icon', 'remark', 'param_desc', 'example', 'sort'
        ])
            ->order('sort desc,id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return compact('list', 'count');
    }

    /**
     * 构建列表查询
     */
    protected function buildListQuery(array $params)
    {
        $query = $this->getModel();

        if (($params['cate_id'] ?? '') !== '') {
            $query = $query->where('cate_id', (int)$params['cate_id']);
        }
        if (($params['link_name'] ?? '') !== '') {
            $query = $query->where('link_name', 'like', '%' . trim((string)$params['link_name']) . '%');
        }
        if (($params['keyword'] ?? '') !== '') {
            $keyword = trim((string)$params['keyword']);
            $query = $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereOr('link_name', 'like', '%' . $keyword . '%')
                    ->whereOr('link_url', 'like', '%' . $keyword . '%')
                    ->whereOr('remark', 'like', '%' . $keyword . '%');
            });
        }
        if (($params['link_type'] ?? '') !== '') {
            $query = $query->where('link_type', (string)$params['link_type']);
        }
        if (($params['platform_type'] ?? '') !== '') {
            $query = $query->where('platform_type', (string)$params['platform_type']);
        }
        if (($params['status'] ?? '') !== '') {
            $query = $query->where('status', (int)$params['status']);
        }

        return $query;
    }

    /**
     * 数据规范化
     */
    protected function normalizeData(array $data): array
    {
        $linkType = (string)($data['link_type'] ?? 'page');
        $miniAppid = trim((string)($data['mini_appid'] ?? ''));
        $miniPath = trim((string)($data['mini_path'] ?? ''));
        $linkUrl = trim((string)($data['link_url'] ?? ''));

        if ($linkType === 'mini') {
            $linkUrl = $miniPath;
        }

        if ($linkType !== 'mini') {
            $miniAppid = '';
            $miniPath = '';
        }

        return [
            'cate_id' => (int)($data['cate_id'] ?? 0),
            'link_name' => trim((string)($data['link_name'] ?? '')),
            'link_url' => $linkUrl,
            'link_type' => $linkType,
            'platform_type' => (string)($data['platform_type'] ?? 'common'),
            'mini_appid' => $miniAppid,
            'mini_path' => $miniPath,
            'icon' => trim((string)($data['icon'] ?? '')),
            'param_desc' => trim((string)($data['param_desc'] ?? '')),
            'example' => trim((string)($data['example'] ?? '')),
            'remark' => trim((string)($data['remark'] ?? '')),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
        ];
    }

    /**
     * 校验数据
     */
    protected function validateData(array $data, int $id = 0): void
    {
        if ($data['cate_id'] <= 0) {
            throw new AdminResponseException('请选择所属分类');
        }
        if ($data['link_name'] === '') {
            throw new AdminResponseException('链接名称不能为空');
        }
        if (!isset(self::LINK_TYPES[$data['link_type']])) {
            throw new AdminResponseException('链接类型不正确');
        }
        if ($data['link_type'] === 'mini') {
            if ($data['mini_appid'] === '') {
                throw new AdminResponseException('请输入小程序 AppID');
            }
            if ($data['mini_path'] === '') {
                throw new AdminResponseException('请输入小程序页面路径');
            }
        } elseif ($data['link_url'] === '') {
            throw new AdminResponseException('链接地址不能为空');
        }
        if (!isset(self::PLATFORM_TYPES[$data['platform_type']])) {
            throw new AdminResponseException('平台类型不正确');
        }

        $categoryExists = app()->make(DiyCategoryService::class)
            ->getModel()
            ->where('id', $data['cate_id'])
            ->where('type', CategoryService::DIY_TYPE)
            ->count();
        if (!$categoryExists) {
            throw new AdminResponseException('所属分类不存在');
        }

        $query = $this->getModel()
            ->where('cate_id', $data['cate_id'])
            ->where('link_name', $data['link_name']);
        if ($id > 0) {
            $query->where('id', '<>', $id);
        }
        if ($query->count()) {
            throw new AdminResponseException('同分类下链接名称已存在');
        }
    }
}
