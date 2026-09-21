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

namespace app\common\service\user;

use app\common\enum\AuditStatusEnum;
use app\common\enum\ContentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\user\UserBrowseRecord;
use app\common\service\BaseService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialService;

/**
 * 用户浏览记录服务
 */
class UserBrowseRecordService extends BaseService
{
    public const TARGET_TYPE_SPECIAL = 'special';
    public const TARGET_TYPE_CONTENT = 'content';
    public const TARGET_TYPE_PRODUCT = 'product';

    /**
     * 对应模型
     */
    protected function setModel(): string
    {
        return UserBrowseRecord::class;
    }

    /**
     * 记录浏览行为。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 浏览对象类型。
     * @param int $targetId 浏览对象ID。
     * @param int $merchantId 商户ID。
     * @return array
     */
    public function recordBrowse(int $userId, string $targetType, int $targetId, int $merchantId = 0): array
    {
        if ($userId <= 0 || !$this->isValidTarget($targetType, $targetId)) {
            return [
                'recorded' => false,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'browse_count' => 0,
            ];
        }

        $record = $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->find();

        $now = date('Y-m-d H:i:s');
        $browseCount = 1;

        if ($record) {
            $browseCount = max(0, (int)($record['browse_count'] ?? 0)) + 1;
            $record->save([
                'merchant_id' => $merchantId,
                'browse_count' => $browseCount,
                'last_browse_time' => $now,
                'update_time' => $now,
            ]);
        } else {
            $this->create([
                'user_id' => $userId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'merchant_id' => $merchantId,
                'browse_count' => 1,
                'last_browse_time' => $now,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        return [
            'recorded' => true,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'browse_count' => $browseCount,
        ];
    }

    /**
     * 获取用户指定类型的浏览记录。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 浏览对象类型。
     * @param int $limit 限制条数。
     * @return array
     */
    public function getUserBrowseRecords(int $userId, string $targetType, int $limit = 20): array
    {
        if ($userId <= 0 || trim($targetType) === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));

        return $this->getModel()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->order('last_browse_time', 'desc')
            ->limit($limit)
            ->field(['target_id', 'browse_count', 'last_browse_time'])
            ->select()
            ->toArray();
    }

    /**
     * 获取移动端用户足迹列表。
     *
     * @param int $userId 用户ID。
     * @param int $page 页码。
     * @param int $limit 每页条数。
     * @return array
     * @throws AdminResponseException
     */
    public function getMobileHistoryList(int $userId, int $page = 1, int $limit = 10): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $page = max(1, $page);
        $limit = max(1, min(50, $limit));

        $recordList = $this->getModel()
            ->where('user_id', $userId)
            ->order('last_browse_time', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();
        $formattedList = $this->formatMobileHistoryList($recordList);
        $total = count($formattedList);
        $offset = ($page - 1) * $limit;

        return [
            'list' => array_slice($formattedList, $offset, $limit),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * 删除移动端用户选中的足迹记录。
     *
     * @param int $userId 用户ID。
     * @param array $ids 浏览记录 ID 集合。
     * @return array
     * @throws AdminResponseException
     */
    public function deleteMobileRecords(int $userId, array $ids): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw new AdminResponseException('请选择要删除的足迹');
        }

        $deleted = (int)$this->getModel()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->delete();

        return [
            'deleted' => $deleted,
            'ids' => $ids,
        ];
    }

    /**
     * 记录移动端用户浏览行为。
     *
     * @param int $userId 用户ID。
     * @param string $targetType 浏览对象类型。
     * @param int $targetId 浏览对象 ID。
     * @return array
     * @throws AdminResponseException
     */
    public function recordMobileBrowse(int $userId, string $targetType, int $targetId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $targetType = $this->normalizeMobileTargetType($targetType);
        if ($targetId <= 0) {
            throw new AdminResponseException('浏览对象ID不能为空');
        }

        $merchantId = $this->resolveTargetMerchantId($targetType, $targetId);

        return $this->recordBrowse($userId, $targetType, $targetId, $merchantId);
    }

    /**
     * 格式化移动端足迹列表。
     */
    protected function formatMobileHistoryList(array $recordList): array
    {
        if ($recordList === []) {
            return [];
        }

        $targetIdsByType = $this->collectTargetIdsByType($recordList);
        $contentMap = app()->make(ArticleService::class)->getMapByIds($targetIdsByType[self::TARGET_TYPE_CONTENT] ?? []);
        $specialMap = app()->make(SpecialService::class)->getMapByIds($targetIdsByType[self::TARGET_TYPE_SPECIAL] ?? []);

        $result = [];
        foreach ($recordList as $record) {
            $targetType = (string)($record['target_type'] ?? '');
            $targetId = (int)($record['target_id'] ?? 0);
            $target = match ($targetType) {
                self::TARGET_TYPE_CONTENT => $contentMap[$targetId] ?? [],
                self::TARGET_TYPE_SPECIAL => $specialMap[$targetId] ?? [],
                default => [],
            };

            if (!is_array($target) || $target === []) {
                continue;
            }

            $item = match ($targetType) {
                self::TARGET_TYPE_CONTENT => $this->formatMobileContentHistory($record, $target),
                self::TARGET_TYPE_SPECIAL => $this->formatMobileSpecialHistory($record, $target),
                self::TARGET_TYPE_PRODUCT => $this->formatMobileProductHistory($record, $target),
                default => [],
            };

            if ($item !== []) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * 收集各类型目标 ID。
     */
    protected function collectTargetIdsByType(array $recordList): array
    {
        $result = [
            self::TARGET_TYPE_CONTENT => [],
            self::TARGET_TYPE_SPECIAL => [],
            self::TARGET_TYPE_PRODUCT => [],
        ];

        foreach ($recordList as $record) {
            $targetType = (string)($record['target_type'] ?? '');
            $targetId = (int)($record['target_id'] ?? 0);
            if ($targetId > 0 && isset($result[$targetType])) {
                $result[$targetType][] = $targetId;
            }
        }

        foreach ($result as $type => $ids) {
            $result[$type] = array_values(array_unique($ids));
        }

        return $result;
    }

    /**
     * 格式化内容足迹。
     */
    protected function formatMobileContentHistory(array $record, array $content): array
    {
        if ((int)($content['status'] ?? 0) !== 1 || (int)($content['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            return [];
        }

        $targetId = (int)($content['id'] ?? 0);
        $price = (float)($content['price'] ?? 0);

        return $this->buildMobileHistoryItem($record, [
            'target_type' => self::TARGET_TYPE_CONTENT,
            'target_id' => $targetId,
            'title' => (string)($content['title'] ?? ''),
            'desc' => (string)($content['description'] ?? ''),
            'image' => (string)($content['cover'] ?? ''),
            'highlight' => $this->formatPriceText($price),
            'badge' => ContentTypeEnum::getName((int)($content['content_type'] ?? 0)),
            'detail_url' => '/pages/contents/content/detail/index?id=' . $targetId,
        ]);
    }

    /**
     * 格式化专题足迹。
     */
    protected function formatMobileSpecialHistory(array $record, array $special): array
    {
        if ((int)($special['status'] ?? 0) !== 1 || (int)($special['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            return [];
        }

        $targetId = (int)($special['id'] ?? 0);
        $price = (float)($special['price'] ?? 0);

        return $this->buildMobileHistoryItem($record, [
            'target_type' => self::TARGET_TYPE_SPECIAL,
            'target_id' => $targetId,
            'title' => (string)($special['title'] ?? ''),
            'desc' => (string)($special['description'] ?? ''),
            'image' => (string)($special['cover'] ?? ''),
            'highlight' => $this->formatPriceText($price),
            'badge' => '专题',
            'detail_url' => '/pages/contents/special/detail/index?id=' . $targetId,
        ]);
    }

    /**
     * 格式化商品足迹。
     */
    protected function formatMobileProductHistory(array $record, array $product): array
    {
        if ((int)($product['status'] ?? 0) !== 1) {
            return [];
        }

        $targetId = (int)($product['id'] ?? 0);
        $price = (float)($product['price'] ?? 0);

        return $this->buildMobileHistoryItem($record, [
            'target_type' => self::TARGET_TYPE_PRODUCT,
            'target_id' => $targetId,
            'title' => (string)($product['name'] ?? ''),
            'desc' => (string)($product['description'] ?? ''),
            'image' => (string)($product['cover'] ?? ''),
            'highlight' => $this->formatPriceText($price),
            'badge' => '商品',
            'detail_url' => '',
        ]);
    }

    /**
     * 构建移动端足迹统一字段。
     */
    protected function buildMobileHistoryItem(array $record, array $target): array
    {
        $lastBrowseTime = (string)($record['last_browse_time'] ?? '');
        $group = $this->resolveBrowseGroup($lastBrowseTime);

        return [
            'id' => (int)($record['id'] ?? 0),
            'target_type' => (string)($target['target_type'] ?? ''),
            'target_id' => (int)($target['target_id'] ?? 0),
            'title' => (string)($target['title'] ?? ''),
            'desc' => (string)($target['desc'] ?? ''),
            'image' => (string)($target['image'] ?? ''),
            'highlight' => (string)($target['highlight'] ?? ''),
            'badge' => (string)($target['badge'] ?? ''),
            'detail_url' => (string)($target['detail_url'] ?? ''),
            'browse_count' => (int)($record['browse_count'] ?? 0),
            'last_browse_time' => $lastBrowseTime,
            'group_key' => $group['key'],
            'group_label' => $group['label'],
        ];
    }

    /**
     * 获取价格展示文案。
     */
    protected function formatPriceText(float $price): string
    {
        if ($price <= 0) {
            return '免费';
        }

        return '¥' . rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    /**
     * 获取浏览记录日期分组。
     */
    protected function resolveBrowseGroup(string $lastBrowseTime): array
    {
        if ($lastBrowseTime === '') {
            return ['key' => 'earlier', 'label' => '更早'];
        }

        $timestamp = strtotime($lastBrowseTime);
        if (!$timestamp) {
            return ['key' => 'earlier', 'label' => '更早'];
        }

        $date = date('Y-m-d', $timestamp);
        if ($date === date('Y-m-d')) {
            return ['key' => 'today', 'label' => '今天'];
        }

        if ($date === date('Y-m-d', strtotime('-1 day'))) {
            return ['key' => 'yesterday', 'label' => '昨天'];
        }

        return ['key' => 'earlier', 'label' => '更早'];
    }

    /**
     * 归一化移动端浏览对象类型。
     *
     * @throws AdminResponseException
     */
    protected function normalizeMobileTargetType(string $targetType): string
    {
        $targetType = trim($targetType);
        if ($targetType === 'article') {
            $targetType = self::TARGET_TYPE_CONTENT;
        }
        if ($targetType === 'topic') {
            $targetType = self::TARGET_TYPE_SPECIAL;
        }

        if (!in_array($targetType, [
            self::TARGET_TYPE_CONTENT,
            self::TARGET_TYPE_SPECIAL,
            self::TARGET_TYPE_PRODUCT,
        ], true)) {
            throw new AdminResponseException('浏览对象类型不支持');
        }

        return $targetType;
    }

    /**
     * 解析目标商户 ID。
     */
    protected function resolveTargetMerchantId(string $targetType, int $targetId): int
    {
        if ($targetType === self::TARGET_TYPE_CONTENT) {
            $target = app()->make(ArticleService::class)->getMapByIds([$targetId])[$targetId] ?? [];
            return (int)($target['mer_id'] ?? 0);
        }

        if ($targetType === self::TARGET_TYPE_SPECIAL) {
            $target = app()->make(SpecialService::class)->getMapByIds([$targetId])[$targetId] ?? [];
            return (int)($target['merchant_id'] ?? 0);
        }

        return 0;
    }

    /**
     * 判断浏览对象参数是否有效。
     *
     * @param string $targetType 浏览对象类型。
     * @param int $targetId 浏览对象ID。
     * @return bool
     */
    protected function isValidTarget(string $targetType, int $targetId): bool
    {
        return trim($targetType) !== '' && $targetId > 0;
    }
}
