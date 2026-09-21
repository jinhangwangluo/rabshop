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

use app\common\exception\AdminResponseException;
use app\common\model\content\ArticleContent;
use app\common\service\BaseService;

/**
 * 文章内容服务
 * @package app\common\service\content
 */
class ArticleContentService extends BaseService
{
    /**
     * @return string
     */
    protected function setModel(): string
    {
        return ArticleContent::class;
    }

    /**
     * 根据内容 ID 获取内容记录
     */
    public function findByArticleId(int $articleId)
    {
        return $this->get(['article_id' => $articleId]);
    }

    /**
     * 获取内容记录列表
     */
    public function getListByArticleId(int $articleId, string $order = 'id asc'): array
    {
        $records = $this->getModel()
            ->where('article_id', $articleId)
            ->orderRaw($order)
            ->select()
            ->toArray();

        return array_map(fn (array $record): array => $this->sanitizeContentRecord($record), $records);
    }

    /**
     * 按内容 ID 保存或创建内容记录
     */
    public function saveOrCreateByArticleId(int $articleId, array $data): void
    {
        $data = $this->normalizeContentData($data);
        $content = $this->findByArticleId($articleId);
        if ($content) {
            $content->save($data);
            return;
        }

        $data['article_id'] = $articleId;
        $this->create($data);
    }

    /**
     * 删除内容记录
     */
    public function deleteByArticleId(int $articleId): void
    {
        $this->delete(['article_id' => $articleId]);
    }

    /**
     * 规范化文章扩展内容，确保所有写入路径都经过同一套 HTML 清洗规则。
     *
     * @param array $data 待保存的文章扩展字段。
     * @return array 规范化后的文章扩展字段。
     * @throws AdminResponseException 内容字段不是字符串时抛出。
     */
    private function normalizeContentData(array $data): array
    {
        foreach (['content', 'trial_content'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($data[$field] === null) {
                $data[$field] = '';
                continue;
            }
            if (!is_string($data[$field])) {
                throw new AdminResponseException('文章内容格式错误');
            }

            $data[$field] = app()->make(ArticleHtmlSanitizer::class)->clean($data[$field]);
        }

        return $data;
    }

    /**
     * 清洗文章扩展记录中的可渲染 HTML 字段。
     *
     * @param array $record 文章扩展记录。
     * @return array 清洗后的文章扩展记录。
     */
    private function sanitizeContentRecord(array $record): array
    {
        foreach (['content', 'trial_content'] as $field) {
            if (array_key_exists($field, $record)) {
                $record[$field] = is_string($record[$field])
                    ? app()->make(ArticleHtmlSanitizer::class)->clean($record[$field])
                    : '';
            }
        }

        return $record;
    }
}
