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

namespace app\common\service\content;

use app\common\model\content\SpecialContent;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 专题内容服务层
 * @package app\common\service\content
 */
class SpecialContentService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 专题内容服务层
     * @return string
     */
    protected function setModel(): string
    {
        return SpecialContent::class;
    }

    /**
     * 获取专题内容列表（携带内容详情）
     * @param int $specialId
     * @return array
     */
    public function getContentListWithDetail(int $specialId): array
    {
        $list = $this->getModel()
            ->where('special_id', $specialId)
            ->order('sort', 'asc')
            ->with(['article'])
            ->select();

        return $list ? $list->toArray() : [];
    }

    /**
     * 获取专题下内容的简洁列表（仅内容基本信息）
     * @param int $specialId
     * @return array
     */
    public function getContentIdList(int $specialId): array
    {
        return $this->getModel()
            ->where('special_id', $specialId)
            ->order('sort', 'asc')
            ->column('article_id, type, sort', 'id');
    }

    /**
     * 删除专题下的所有内容
     */
    public function deleteBySpecialId(int $specialId): void
    {
        $this->delete(['special_id' => $specialId]);
    }

    /**
     * 删除某内容关联的专题记录
     */
    public function deleteByArticleId(int $articleId): void
    {
        $this->delete(['article_id' => $articleId]);
    }

    /**
     * 替换专题内容关联
     */
    public function replaceBySpecialId(int $specialId, array $contents): void
    {
        $this->deleteBySpecialId($specialId);
        if ($contents === []) {
            return;
        }

        $insertData = [];
        foreach ($contents as $index => $content) {
            $insertData[] = [
                'special_id' => $specialId,
                'article_id' => (int) ($content['article_id'] ?? 0),
                'type' => (int) ($content['type'] ?? 1),
                'sort' => (int) ($content['sort'] ?? $index),
            ];
        }

        $this->getModel()->insertAll($insertData);
    }

    /**
     * 按专题 ID 获取关联内容 ID 列表
     */
    public function getArticleIdsBySpecialIds(array $specialIds, array $articleIds = []): array
    {
        $specialIds = array_values(array_unique(array_filter(array_map('intval', $specialIds))));
        if ($specialIds === []) {
            return [];
        }

        $query = $this->getModel()->whereIn('special_id', $specialIds);
        $articleIds = array_values(array_unique(array_filter(array_map('intval', $articleIds))));
        if ($articleIds !== []) {
            $query->whereIn('article_id', $articleIds);
        }

        $result = $query->column('article_id');

        return array_values(array_unique(array_filter(array_map('intval', $result))));
    }
}
