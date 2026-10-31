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
use app\common\service\BaseService;
use app\common\model\wechat\WechatReply;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 微信回复服务类
 * Class WechatReplyService
 * @package app\common\service\wechat
 */
class WechatReplyService extends BaseService
{
    /**
     * 缓存标签
     * @var string
     */
    protected string $cacheTag = 'wechat_reply';

    /**
     * 回复类型映射
     * @var array
     */
    protected static array $replyTypes = [
        'text'  => '文本消息',
        'image' => '图片消息',
        'news'  => '图文消息',
        'voice' => '语音消息',
        'video' => '视频消息',
    ];

    /**
     * @return string
     */
    protected function setModel(): string
    {
        return WechatReply::class;
    }

    /**
     * 获取关键词服务实例
     * @return WechatReplyKeyService
     */
    protected function getReplyKeyService(): WechatReplyKeyService
    {
        return app()->make(WechatReplyKeyService::class);
    }

    /**
     * 创建回复
     * @param array $data
     * @param array $keywords 关键字数组（已拆分好的单个关键字列表）
     * @return bool
     */
    public function createReply(array $data, array $keywords = []): bool
    {
        $replyData = [
            'key'    => $data['key'],
            'type'   => $data['type'],
            'data'   => json_encode($data['data'], JSON_UNESCAPED_UNICODE),
            'status' => $data['status'] ?? 1,
            'sort'   => $data['sort'] ?? 0,
        ];

        $reply = $this->create($replyData);

        // 将关键字数组写入 wechat_reply_key 表
        $resolvedKeywords = $keywords ?: (
        !empty($data['keywords']) ? $data['keywords'] : []
        );

        if (!empty($resolvedKeywords)) {
            // 如果传入的是关键字字符串数组，就直接批量写入
            $keywordsData = array_map(fn(string $kw) => ['keyword' => $kw, 'match_type' => 'equal', 'sort' => 0], $resolvedKeywords);
            $this->getReplyKeyService()->batchInsertKeywords((int)$reply->id, $keywordsData);
        }

        return true;
    }

    /**
     * 更新回复
     * @param int $id
     * @param array $data
     * @param array $keywords 关键字数组（已拆分好的单个关键字列表）
     * @return bool
     * @throws AdminResponseException
     */
    public function updateReply(int $id, array $data, array $keywords = []): bool
    {
        $reply = $this->get($id);
        if (!$reply) {
            throw new AdminResponseException('回复不存在');
        }

        $updateData = [
            'type'   => $data['type'],
            'data'   => json_encode($data['data'], JSON_UNESCAPED_UNICODE),
            'status' => $data['status'] ?? 1,
            'sort'   => $data['sort'] ?? 0,
        ];

        // 如果有传入 key，同步更新关键字字段
        if (!empty($data['key'])) {
            $updateData['key'] = $data['key'];
        }

        $this->update($id, $updateData);

        // 同步更新 wechat_reply_key 表
        $resolvedKeywords = $keywords ?: (
            isset($data['keywords']) ? $data['keywords'] : null
        );

        if ($resolvedKeywords !== null) {
            $replyKeyService = $this->getReplyKeyService();
            // 先删除原有关键词
            $replyKeyService->deleteByReplyId($id);

            // 添加新的关键词
            if (!empty($resolvedKeywords)) {
                $keywordsData = array_map(
                    fn(string $kw) => ['keyword' => $kw, 'match_type' => 'equal', 'sort' => 0],
                    $resolvedKeywords
                );
                $replyKeyService->batchInsertKeywords($id, $keywordsData);
            }
        }

        return true;
    }

    /**
     * 获取回复列表
     * @param array $where
     * @param array $field
     * @param string|null $sort
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getReplyList(array $where = [], array $field = ['*'], ?string $sort = null): array
    {
        $list = $this->getModel()->getDataList($where, $field, 0, 0, $sort ?: 'sort desc,id desc');

        $replyKeyService = $this->getReplyKeyService();
        // 为每个回复添加关键词信息
        foreach ($list as &$item) {
            $item['keywords'] = $replyKeyService->getKeywordsByReplyId((int)$item['id']);
        }

        return $list;
    }

    /**
     * 获取启用的回复列表
     * @param array $where
     * @param array $field
     * @param string|null $sort
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getEnableReplies(array $where = [], array $field = ['*'], ?string $sort = null): array
    {
        $where['status'] = 1;
        return $this->getReplyList($where, $field, $sort);
    }

    /**
     * 根据关键词获取回复内容
     * @param string $keyword
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getReplyContent(string $keyword): ?array
    {
        $reply = $this->getReplyByKey($keyword);
        if ($reply) {
            $reply['data'] = json_decode($reply['data'], true);
            return $reply;
        }
        return null;
    }

    /**
     * 获取关注回复内容
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getSubscribeReplyContent(): ?array
    {
        $reply = $this->getSubscribeReply();
        if ($reply) {
            $replyArr = is_array($reply) ? $reply : $reply->toArray();
            $replyArr['data'] = json_decode($replyArr['data'], true);
            return $replyArr;
        }
        return null;
    }

    /**
     * 获取默认回复内容
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getDefaultReplyContent(): ?array
    {
        $reply = $this->getModel()->where('key', 'default')->where('status', 1)->find();
        if ($reply) {
            $replyArr = $reply->toArray();
            $replyArr['data'] = json_decode($replyArr['data'], true);
            return $replyArr;
        }
        return null;
    }

    /**
     * 处理微信消息并返回回复
     * @param array $message
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function handleWechatMessage(array $message): ?array
    {
        $msgType = $message['MsgType'] ?? '';
        $content = $message['Content'] ?? '';

        switch ($msgType) {
            case 'text':
                return $this->getReplyContent($content);

            case 'event':
                $event = $message['Event'] ?? '';
                if ($event === 'subscribe') {
                    return $this->getSubscribeReplyContent();
                }
                break;

            default:
                return $this->getDefaultReplyContent();
        }

        return $this->getDefaultReplyContent();
    }

    /**
     * 格式化回复内容为微信格式
     * @param array $reply
     * @return array
     */
    public function formatReplyForWechat(array $reply): array
    {
        $type = $reply['type'];
        $data = $reply['data'];

        return match ($type) {
            'text' => [
                'MsgType' => 'text',
                'Content' => $data['content'] ?? '',
            ],
            'image' => [
                'MsgType' => 'image',
                'Image'   => [
                    'MediaId' => $data['media_id'] ?? '',
                ],
            ],
            'news' => [
                'MsgType'      => 'news',
                'ArticleCount' => count($data['articles'] ?? []),
                'Articles'     => array_map(fn(array $article) => [
                    'Title'       => $article['title'] ?? '',
                    'Description' => $article['description'] ?? '',
                    'PicUrl'      => $article['pic_url'] ?? '',
                    'Url'         => $article['url'] ?? '',
                ], $data['articles'] ?? []),
            ],
            'voice' => [
                'MsgType' => 'voice',
                'Voice'   => [
                    'MediaId' => $data['media_id'] ?? '',
                ],
            ],
            'video' => [
                'MsgType' => 'video',
                'Video'   => [
                    'MediaId'     => $data['media_id'] ?? '',
                    'Title'       => $data['title'] ?? '',
                    'Description' => $data['description'] ?? '',
                ],
            ],
            default => [
                'MsgType' => 'text',
                'Content' => '暂不支持的消息类型',
            ],
        };
    }

    /**
     * 获取关键字回复分页列表（排除subscribe/default）
     * @param array $where
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getKeywordReplyList(array $where = []): mixed
    {
        [$page, $limit] = $this->getPagesValue();

        $query = $this->getModel()
            ->where('key', 'not in', ['subscribe', 'default']);

        if (!empty($where['key'])) {
            $query->where('key', 'like', '%' . $where['key'] . '%');
        }

        if (!empty($where['type'])) {
            $query->where('type', $where['type']);
        }

        $count = (clone $query)->count();
        $list = $query->order('sort desc,id desc')
            ->when($page && $limit, function ($q) use ($page, $limit) {
                $q->page($page, $limit);
            })
            ->select()
            ->toArray();

        // 解码data字段并添加关键词信息
        $replyKeyService = $this->getReplyKeyService();
        foreach ($list as &$item) {
            $item['data'] = json_decode($item['data'], true);
            $item['keywords'] = $replyKeyService->getKeywordsByReplyId((int)$item['id']);
        }

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 按ID获取回复详情
     * @param int $id
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getReplyDetailById(int $id): ?array
    {
        $reply = $this->get($id);
        if ($reply) {
            $replyArr = $reply->toArray();
            $replyArr['data'] = json_decode($replyArr['data'], true);
            $replyArr['keywords'] = $this->getReplyKeyService()->getKeywordsByReplyId((int)$replyArr['id']);
            return $replyArr;
        }
        return null;
    }

    /**
     * 获取匹配类型选项
     * @return array
     */
    public function getMatchTypes(): array
    {
        return $this->getReplyKeyService()->getMatchTypes();
    }

    /**
     * 获取回复类型选项
     * @return array
     */
    public function getReplyTypes(): array
    {
        return array_map(
            fn(string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys(self::$replyTypes),
            array_values(self::$replyTypes)
        );
    }

    /**
     * 获取回复类型映射
     * @return array
     */
    public function getReplyTypeMap(): array
    {
        return self::$replyTypes;
    }

    /**
     * 检查回复类型是否有效
     * @param string $type
     * @return bool
     */
    public function isValidReplyType(string $type): bool
    {
        return array_key_exists($type, self::$replyTypes);
    }

    /**
     * 删除回复
     * @param int|string|array|float $id
     * @return bool
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteReply(int|string|array|float $id): bool
    {
        $reply = $this->get($id);
        if (!$reply) {
            throw new AdminResponseException('数据不存在!');
        }

        // 同时删除关联的关键词
        $this->getReplyKeyService()->deleteByReplyId((int)$id);

        return (bool)$this->delete($id);
    }

    /**
     * 获取单个回复详情
     * @param string $key
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getReplyDetail(string $key): ?array
    {
        $reply = $this->getModel()->where('key', $key)->find();
        if ($reply) {
            $replyArr = $reply->toArray();
            $replyArr['data'] = json_decode($replyArr['data'], true);
            $replyArr['keywords'] = $this->getReplyKeyService()->getKeywordsByReplyId((int)$replyArr['id']);
            return $replyArr;
        }
        return null;
    }

    /**
     * 编辑回复内容
     * @param array $data
     * @param string $key
     * @param string $type
     * @param int $status
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function redact(array $data, string $key, string $type, int $status = 1): bool
    {
        $reply = $this->getModel()->where('key', $key)->find();
        if ($reply) {
            $reply->type = $type;
            $reply->data = json_encode($data, JSON_UNESCAPED_UNICODE);
            $reply->status = $status;
            return (bool)$reply->save();
        } else {
            $this->create([
                'key'    => $key,
                'type'   => $type,
                'data'   => json_encode($data, JSON_UNESCAPED_UNICODE),
                'status' => $status,
                'sort'   => 0,
            ]);
            return true;
        }
    }

    /**
     * 根据关键词获取回复
     * @param string $keyword
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getReplyByKey(string $keyword): ?array
    {
        $replyKeyService = $this->getReplyKeyService();

        // 先查找完全匹配
        $replyId = $replyKeyService->findReplyIdByExactMatch($keyword);
        if ($replyId) {
            $reply = $this->get(['id' => $replyId, 'status' => 1]);
            if ($reply) {
                return $reply->toArray();
            }
        }

        // 查找包含匹配
        $containKeywords = $replyKeyService->getContainMatchKeywords();
        foreach ($containKeywords as $keyRecord) {
            if (str_contains($keyword, $keyRecord['keyword'])) {
                $reply = $this->get(['id' => $keyRecord['reply_id'], 'status' => 1]);
                if ($reply) {
                    return $reply->toArray();
                }
            }
        }

        // 查找正则匹配
        $regexpKeywords = $replyKeyService->getRegexpMatchKeywords();
        foreach ($regexpKeywords as $keyRecord) {
            if (preg_match('/' . $keyRecord['keyword'] . '/', $keyword)) {
                $reply = $this->get(['id' => $keyRecord['reply_id'], 'status' => 1]);
                if ($reply) {
                    return $reply->toArray();
                }
            }
        }

        // 返回默认回复
        $defaultReply = $this->getModel()->where('key', 'default')->where('status', 1)->find();
        return $defaultReply ? $defaultReply->toArray() : null;
    }

    /**
     * 获取关注回复
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getSubscribeReply(): mixed
    {
        return $this->getModel()->where('key', 'subscribe')->where('status', 1)->find();
    }

    /**
     * 清除缓存
     * @return bool
     */
    public function clearCache(): bool
    {
        return $this->cacheTag()->flush();
    }

    /**
     * 添加关键词
     * @param int $replyId
     * @param string $keyword
     * @param string $matchType
     * @param int $sort
     * @return bool
     * @throws AdminResponseException
     */
    public function addKeyword(int $replyId, string $keyword, string $matchType = 'equal', int $sort = 0): bool
    {
        $replyKeyService = $this->getReplyKeyService();

        if ($replyKeyService->checkKeywordExists($replyId, $keyword, $matchType)) {
            throw new AdminResponseException('该关键词组合已存在!');
        }

        $replyKeyService->create([
            'reply_id'   => $replyId,
            'keyword'    => $keyword,
            'match_type' => $matchType,
            'sort'       => $sort,
        ]);

        return true;
    }

    /**
     * 批量添加关键词
     * @param int $replyId
     * @param array $keywords
     * @return bool
     */
    public function batchAddKeywords(int $replyId, array $keywords): bool
    {
        return $this->getReplyKeyService()->batchInsertKeywords($replyId, $keywords);
    }

    /**
     * 删除关键词
     * @param int $id
     * @return bool
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteKeyword(int $id): bool
    {
        $replyKeyService = $this->getReplyKeyService();
        $keyword = $replyKeyService->get($id);
        if (!$keyword) {
            throw new AdminResponseException('数据不存在!');
        }

        return (bool)$replyKeyService->delete($id);
    }

    /**
     * 更新关键词
     * @param int $id
     * @param array $data
     * @return bool
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateKeyword(int $id, array $data): bool
    {
        $replyKeyService = $this->getReplyKeyService();
        $keyword = $replyKeyService->get($id);
        if (!$keyword) {
            throw new AdminResponseException('数据不存在!');
        }

        // 检查更新后的关键词是否重复
        if (isset($data['keyword']) || isset($data['match_type'])) {
            $checkKeyword = $data['keyword'] ?? $keyword['keyword'];
            $checkMatchType = $data['match_type'] ?? $keyword['match_type'];

            if ($replyKeyService->checkKeywordExists(
                (int)$keyword['reply_id'],
                $checkKeyword,
                $checkMatchType,
                $id
            )) {
                throw new AdminResponseException('该关键词组合已存在!');
            }
        }

        return (bool)$replyKeyService->update($id, $data);
    }
}
