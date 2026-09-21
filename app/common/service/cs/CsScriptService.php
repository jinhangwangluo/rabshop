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

namespace app\common\service\cs;

use app\common\exception\AdminException;
use app\common\model\cs\CsScript;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 话术服务
 */
class CsScriptService extends BaseService
{
    /**
     * 设置模型
     * @return string
     */
    protected function setModel(): string
    {
        return CsScript::class;
    }

    /**
     * 创建话术
     * @param int $categoryId 分类ID
     * @param int $merchantId 商户ID
     * @param string $title 标题
     * @param string $content 内容
     * @param string $shortcut 快捷键
     * @return mixed
     */
    public function createScript(int $categoryId, int $merchantId, string $title, string $content, string $shortcut = '')
    {
        return $this->getModel()->create([
            'category_id' => $categoryId,
            'merchant_id' => $merchantId,
            'title' => $title,
            'content' => $content,
            'shortcut' => $shortcut,
            'status' => 1,
            'sort' => 0,
            'use_count' => 0,
        ]);
    }

    /**
     * 获取话术详情
     * @param int $id 话术ID
     * @param int $merchantId 商户ID
     * @return CsScript|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getScriptById(int $id, int $merchantId = -1): ?CsScript
    {
        $query = $this->getModel()->where('id', $id);
        if ($merchantId >= 0) {
            $query->where('merchant_id', $merchantId);
        }

        return $query->find();
    }

    /**
     * 删除话术
     * @param int $id 话术ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteScript(int $id): bool
    {
        $script = $this->getModel()->find($id);
        if (!$script) {
            throw new AdminException('话术不存在');
        }

        return (bool)$script->delete();
    }

    /**
     * 批量删除话术
     * @param array $ids 话术ID数组
     * @return bool
     */
    public function batchDelete(array $ids): bool
    {
        return $this->getModel()->whereIn('id', $ids)->delete() > 0;
    }

    /**
     * 获取话术列表
     * @param int $merchantId 商户ID
     * @param int $categoryId 分类ID
     * @param string $keyword 关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getScriptList(int $merchantId = 0, int $categoryId = 0, string $keyword = '', int $page = 1, int $limit = 20): array
    {
        $query = $this->getModel()->where('merchant_id', $merchantId);

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }

        $total = $query->count();
        $list = $query->order('sort desc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 使用话术
     * @param int $id 话术ID
     * @return CsScript|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function useScript(int $id): ?CsScript
    {
        $script = $this->getModel()->find($id);
        if (!$script) {
            return null;
        }

        $script->use_count = (int)$script->use_count + 1;
        $script->save();

        return $script;
    }

    /**
     * 搜索话术
     * @param int $merchantId 商户ID
     * @param string $keyword 关键词
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function searchScript(int $merchantId, string $keyword): array
    {
        return $this->getModel()
            ->where('merchant_id', $merchantId)
            ->where('status', 1)
            ->where('title', 'like', '%' . $keyword . '%')
            ->order('use_count desc, id desc')
            ->limit(20)
            ->select()
            ->toArray();
    }
}
