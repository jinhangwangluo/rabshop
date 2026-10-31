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

use app\common\model\wechat\WechatReplyKey;
use app\common\service\BaseService;

/**
 * 微信回复关键词服务类
 * Class WechatReplyKeyService
 * @package app\common\service\wechat
 */
class WechatReplyKeyService extends BaseService
{
    /**
     * 缓存标签
     * @var string
     */
    protected string $cacheTag = 'wechat_reply_key';

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return WechatReplyKey::class;
    }

    /**
     * 根据回复ID获取关键词列表
     * @param int $replyId
     * @return array
     */
    public function getKeywordsByReplyId(int $replyId): array
    {
        return $this->getModel()
            ->where('reply_id', $replyId)
            ->order('sort desc,id desc')
            ->select()
            ->toArray();
    }

    /**
     * 根据回复ID删除所有关键词
     * @param int $replyId
     * @return bool
     */
    public function deleteByReplyId(int $replyId): bool
    {
        $this->getModel()->where('reply_id', $replyId)->delete();
        return true;
    }

    /**
     * 批量插入关键词
     * @param int $replyId
     * @param array $keywords
     * @return bool
     */
    public function batchInsertKeywords(int $replyId, array $keywords): bool
    {
        $data = [];
        foreach ($keywords as $keyword) {
            $data[] = [
                'reply_id' => $replyId,
                'keyword' => $keyword['keyword'] ?? '',
                'match_type' => $keyword['match_type'] ?? 'equal',
                'sort' => $keyword['sort'] ?? 0,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
        }

        return (bool)$this->getModel()->insertAll($data);
    }

    /**
     * 检查关键词组合是否已存在
     * @param int $replyId
     * @param string $keyword
     * @param string $matchType
     * @param int|null $excludeId
     * @return bool
     */
    public function checkKeywordExists(int $replyId, string $keyword, string $matchType, ?int $excludeId = null): bool
    {
        $query = $this->getModel()
            ->where('reply_id', $replyId)
            ->where('keyword', $keyword)
            ->where('match_type', $matchType);

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->count() > 0;
    }

    /**
     * 获取匹配类型选项
     * @return array
     */
    public function getMatchTypes(): array
    {
        return [
            ['value' => 'equal', 'label' => '完全匹配'],
            ['value' => 'contain', 'label' => '包含匹配'],
            ['value' => 'regexp', 'label' => '正则匹配'],
        ];
    }

    /**
     * 根据完全匹配查找关键词对应的回复ID
     * @param string $keyword
     * @return int|null
     */
    public function findReplyIdByExactMatch(string $keyword): ?int
    {
        $record = $this->getModel()
            ->where('keyword', $keyword)
            ->where('match_type', 'equal')
            ->find();

        return $record ? (int)$record['reply_id'] : null;
    }

    /**
     * 获取包含匹配的所有关键词记录
     * @return array
     */
    public function getContainMatchKeywords(): array
    {
        return $this->getModel()
            ->where('match_type', 'contain')
            ->select()
            ->toArray();
    }

    /**
     * 获取正则匹配的所有关键词记录
     * @return array
     */
    public function getRegexpMatchKeywords(): array
    {
        return $this->getModel()
            ->where('match_type', 'regexp')
            ->select()
            ->toArray();
    }
}
