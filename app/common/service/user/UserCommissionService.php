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

declare(strict_types=1);

namespace app\common\service\user;

use app\common\enum\CombinationDataTypeEnum;
use app\common\enum\ConfigKeyEnum;
use app\common\exception\ApiValidateException;
use app\common\model\order\Order;
use app\common\model\user\UserAsset;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\order\OrderService;
use app\common\service\setting\FileService;
use app\common\util\FileTypeService;
use Rabbit\service\uploader\UploaderService;
use Rabbit\service\wechat\MiniProgram;

/**
 * 移动端用户佣金服务。
 */
class UserCommissionService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const POSTER_ROUTE = '/pages/users/distribute/index';
    private const HOME_PAGE_PATH = 'pages/index/index';
    private const LOG_LIMIT_MAX = 50;

    /**
     * 设置模型。
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserAsset::class;
    }

    /**
     * 获取佣金中心首页数据。
     *
     * @param int $userId 用户 ID。
     * @return array
     */
    public function getMobileIndex(int $userId): array
    {
        $asset = app()->make(UserAssetService::class)->getInfo($userId);
        $releasedTotal = $this->getReleasedCommissionTotal($userId);
        $pendingTotal = $this->getFrozenCommissionTotal($userId);
        $adImage = $this->extractImageUrl(db_config(ConfigKeyEnum::COMMISSION_PROMOTION_AD_IMAGE, ''));
        $distributionState = app()->make(UserDistributionService::class)->getEffectivePermission($userId);

        return [
            'summary' => [
                'withdrawable' => round((float) ($asset->commission ?? 0), 2),
                'total' => round($releasedTotal + $pendingTotal, 2),
                'pending' => round($pendingTotal, 2),
            ],
            'config' => [
                'freeze_days' => max(0, (int) db_config(ConfigKeyEnum::COMMISSION_FREEZE_DAYS, 0)),
                'distribution_enabled' => (bool)$distributionState['distribution_enabled'],
                'distribution_level' => (int)$distributionState['distribution_level'],
                'distribution_mode' => (string)$distributionState['distribution_mode'],
                'distribution_first_rate' => (float)$distributionState['distribution_first_rate'],
                'distribution_second_rate' => (float)$distributionState['distribution_second_rate'],
                'distribution_permission' => (int)$distributionState['distribution_permission'],
                'effective_distribution_enabled' => (bool)$distributionState['effective_distribution_enabled'],
            ],
            'advertisement' => [
                'image' => $adImage,
                'route' => self::POSTER_ROUTE,
            ],
        ];
    }

    /**
     * 获取佣金中心独立统计数据。
     *
     * @param int $userId 用户 ID。
     * @return array
     */
    public function getMobileStatistics(int $userId): array
    {
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        return [
            'direct_count' => $this->getDirectUserCount($userId),
            'order_count' => $this->getCommissionOrderCount($userId),
            'month_estimate' => round(
                $this->getReleasedCommissionTotal($userId, $monthStart, $monthEnd)
                + $this->getFrozenCommissionTotal($userId, $monthStart, $monthEnd),
                2
            ),
        ];
    }

    /**
     * 获取佣金收益明细。
     *
     * @param int $userId 用户 ID。
     * @param int $page 页码。
     * @param int $limit 每页数量。
     * @param string $startDate 开始日期，格式 YYYY-MM-DD。
     * @param string $endDate 结束日期，格式 YYYY-MM-DD。
     * @return array
     */
    public function getMobileLogs(
        int $userId,
        int $page = 1,
        int $limit = 10,
        string $startDate = '',
        string $endDate = ''
    ): array
    {
        $page = max(1, $page);
        $limit = max(1, min(self::LOG_LIMIT_MAX, $limit));
        $offset = ($page - 1) * $limit;
        $fetchLimit = $offset + $limit;
        [$startTime, $endTime] = $this->normalizeDateRange($startDate, $endDate);

        $releasedTotal = $this->getReleasedLogCount($userId, $startTime, $endTime);
        $frozenTotal = $this->getFrozenCommissionEntryCount($userId, $startTime, $endTime);
        $records = array_merge(
            $this->getReleasedLogItems($userId, $fetchLimit, $startTime, $endTime),
            $this->getFrozenCommissionItems($userId, $fetchLimit, $startTime, $endTime)
        );

        usort($records, static function (array $left, array $right): int {
            return strtotime((string) ($right['sort_time'] ?? '')) <=> strtotime((string) ($left['sort_time'] ?? ''));
        });

        $list = array_slice($records, $offset, $limit);
        $list = array_map(static function (array $item): array {
            unset($item['sort_time']);
            return $item;
        }, $list);

        return [
            'list' => $list,
            'total' => $releasedTotal + $frozenTotal,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 获取移动端分销推广海报数据。
     *
     * @param int $userId 用户 ID。
     * @param string $platform 终端平台。
     * @return array 推广海报、链接和二维码数据。
     */
    public function getMobilePromotionPoster(int $userId, string $platform = ''): array
    {
        if ($userId <= 0) {
            throw new ApiValidateException('请先登录后再生成推广海报');
        }

        $distributionState = app()->make(UserDistributionService::class)->getEffectivePermission($userId);
        if (!(bool)$distributionState['distribution_enabled']) {
            throw new ApiValidateException('分销功能已关闭');
        }
        if (!(bool)$distributionState['effective_distribution_enabled']) {
            throw new ApiValidateException('当前账号暂无分销权限');
        }

        $platform = $this->normalizePlatform($platform);
        $promotionFactor = (string)$userId;
        $promotionUrl = $this->buildPromotionUrl($promotionFactor);
        $targetPath = '/' . self::HOME_PAGE_PATH . '?spread_uid=' . rawurlencode($promotionFactor);
        $targetUrl = $platform === 'miniapp' ? $targetPath : $promotionUrl;
        $recordService = app()->make(UserPromotionQrcodeRecordService::class);
        $qrcodeImage = '';
        $mode = $platform === 'miniapp' ? 'backend' : 'frontend';

        $record = $recordService->findValid($userId, $platform, $promotionFactor, $targetUrl);
        if ($platform === 'miniapp') {
            $qrcodeImage = trim((string)($record['qrcode_url'] ?? ''));
            if ($qrcodeImage === '') {
                $qrcodeImage = $this->generateMiniappQrcodeImage($userId, $promotionFactor);
                $record = $recordService->record($userId, $platform, $promotionFactor, $targetUrl, $qrcodeImage, [
                    'page' => self::HOME_PAGE_PATH,
                    'scene' => 'spread_uid=' . $promotionFactor,
                ]);
            }
        } else {
            $record = $recordService->record($userId, $platform, $promotionFactor, $targetUrl, '', [
                'mode' => 'frontend',
            ]);
        }

        return [
            'poster' => (array)db_config(ConfigKeyEnum::COMMISSION_PROMOTION_POSTER),
            'promotion' => [
                'factor' => $promotionFactor,
                'param_name' => 'spread_uid',
                'path' => $targetPath,
                'url' => $promotionUrl,
            ],
            'qrcode' => [
                'record_id' => (int)($record['id'] ?? 0),
                'platform' => $platform,
                'mode' => $mode,
                'url' => $targetUrl,
                'image' => $qrcodeImage !== '' ? get_image_url($qrcodeImage) : '',
            ],
        ];
    }

    /**
     * 获取已发放佣金总额。
     */
    protected function getReleasedCommissionTotal(int $userId, string $startTime = '', string $endTime = ''): float
    {
        $query = app()->make(UserAssetLogService::class)->getModel()
            ->where('user_id', $userId)
            ->where('type', 'commission')
            ->where('change_type', 'in');

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('create_time', $startTime, $endTime);
        }

        return (float) $query->sum('amount');
    }

    /**
     * 获取冻结中佣金总额。
     */
    protected function getFrozenCommissionTotal(int $userId, string $startTime = '', string $endTime = ''): float
    {
        $firstQuery = $this->getFrozenFirstCommissionQuery($userId);
        $secondQuery = $this->getFrozenSecondCommissionQuery($userId);

        if ($startTime !== '' && $endTime !== '') {
            $this->applyFrozenTimeRange($firstQuery, $startTime, $endTime);
            $this->applyFrozenTimeRange($secondQuery, $startTime, $endTime, 'o.');
        }

        return (float) $firstQuery->sum('first_commission_amount')
            + (float) $secondQuery->sum('o.second_commission_amount');
    }

    /**
     * 获取直推人数。
     */
    protected function getDirectUserCount(int $userId): int
    {
        return (int) app()->make(UserService::class)->getModel()
            ->where('spread_uid', $userId)
            ->count();
    }

    /**
     * 从组合数据上传字段中提取图片地址。
     *
     * @param mixed $value 原始上传字段。
     * @return string 图片绝对地址。
     */
    protected function extractImageUrl(mixed $value): string
    {
        if (is_string($value)) {
            $url = trim($value);
            return $url !== '' ? get_image_url($url) : '';
        }

        if (!is_array($value)) {
            return '';
        }

        if (isset($value['url']) || isset($value['path']) || isset($value['filePath'])) {
            $url = trim((string)($value['url'] ?? $value['path'] ?? $value['filePath'] ?? ''));
            return $url !== '' ? get_image_url($url) : '';
        }

        $first = $value[0] ?? null;
        if (is_array($first)) {
            $url = trim((string)($first['url'] ?? $first['path'] ?? $first['filePath'] ?? ''));
            return $url !== '' ? get_image_url($url) : '';
        }

        return '';
    }

    /**
     * 规范化终端平台。
     *
     * @param string $platform 请求终端。
     * @return string
     */
    protected function normalizePlatform(string $platform): string
    {
        return match (strtolower(trim($platform))) {
            'miniapp', 'mini_program', 'mp_weixin', 'wechat_mp' => 'miniapp',
            'official_account', 'wechat_h5', 'wechat-h5', 'wechat_oa' => 'official_account',
            default => 'h5',
        };
    }

    /**
     * 生成 H5/公众号推广链接。
     *
     * @param string $promotionFactor 推广因子。
     * @return string 完整推广链接。
     */
    protected function buildPromotionUrl(string $promotionFactor): string
    {
        $webHost = trim((string)db_config(ConfigKeyEnum::WEB_HOST, ''));
        if ($webHost === '') {
            $webHost = $this->getRequestHost();
        }

        $webHost = rtrim($webHost, '/');
        $query = 'spread_uid=' . rawurlencode($promotionFactor);

        return $webHost . '/' . self::HOME_PAGE_PATH . '?' . $query;
    }

    /**
     * 从当前请求兜底推导站点域名。
     *
     * @return string
     */
    protected function getRequestHost(): string
    {
        $request = request();
        $scheme = $request->scheme();
        $host = $request->host();

        return $host ? $scheme . '://' . $host : '';
    }

    /**
     * 生成并上传小程序推广二维码。
     *
     * @param int $userId 用户 ID。
     * @param string $promotionFactor 推广因子。
     * @return string 二维码图片地址。
     */
    protected function generateMiniappQrcodeImage(int $userId, string $promotionFactor): string
    {
        $binary = app()->make(MiniProgram::class)->getUnlimitedQrcode(
            'spread_uid=' . $promotionFactor,
            self::HOME_PAGE_PATH
        );

        $filename = sprintf('promotion-qrcode-%d-%s.png', $userId, date('YmdHis'));
        $directory = 'file/mobile/promotion_qrcode/' . $userId . '/' . date('Y');
        $uploaded = app('uploader')->storage()->to($directory)->stream($binary, $filename);
        if ($uploaded === false || !is_array($uploaded)) {
            throw new ApiValidateException('推广二维码保存失败');
        }

        $path = trim((string)($uploaded['filePath'] ?? ''));
        if ($path === '') {
            throw new ApiValidateException('推广二维码保存失败');
        }

        app()->make(FileService::class)->create([
            'path' => $path,
            'name' => (string)($uploaded['fileName'] ?? $filename),
            'real_name' => (string)($uploaded['realName'] ?? $filename),
            'cate_id' => 0,
            'update_type' => $this->resolveUploaderUpdateType((string)($uploaded['updateType'] ?? '')),
            'file_type' => FileTypeService::TYPE_IMAGE,
        ]);

        return $path;
    }

    /**
     * 转换上传类型为 file.update_type。
     *
     * @param string $type 上传驱动类型。
     * @return int
     */
    protected function resolveUploaderUpdateType(string $type): int
    {
        return match ($type) {
            UploaderService::QINIU_UPLOADER => 2,
            UploaderService::COS_UPLOADER => 3,
            UploaderService::OSS_UPLOADER => 4,
            default => 1,
        };
    }

    /**
     * 获取产生佣金的订单数。
     */
    protected function getCommissionOrderCount(int $userId): int
    {
        $firstOrderIds = $this->getPaidCommissionOrderBaseQuery()
            ->where('referrer_id', $userId)
            ->where('first_commission_amount', '>', 0)
            ->column('id');

        $secondOrderIds = app()->make(OrderService::class)->getModel()
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED])
            ->where('refund_status', 0)
            ->where('second_referrer_id', $userId)
            ->where('second_commission_amount', '>', 0)
            ->column('id');

        return count(array_unique(array_map('intval', array_merge($firstOrderIds, $secondOrderIds))));
    }

    /**
     * 获取已发放佣金记录数量。
     */
    protected function getReleasedLogCount(int $userId, string $startTime = '', string $endTime = ''): int
    {
        $query = app()->make(UserAssetLogService::class)->getModel()
            ->where('user_id', $userId)
            ->where('type', 'commission')
            ->where('change_type', 'in');

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('create_time', $startTime, $endTime);
        }

        return (int) $query->count();
    }

    /**
     * 获取冻结中佣金记录数量。
     */
    protected function getFrozenCommissionEntryCount(int $userId, string $startTime = '', string $endTime = ''): int
    {
        $firstQuery = $this->getFrozenFirstCommissionQuery($userId);
        $secondQuery = $this->getFrozenSecondCommissionQuery($userId);

        if ($startTime !== '' && $endTime !== '') {
            $this->applyFrozenTimeRange($firstQuery, $startTime, $endTime);
            $this->applyFrozenTimeRange($secondQuery, $startTime, $endTime, 'o.');
        }

        return (int) $firstQuery->count() + (int) $secondQuery->count();
    }

    /**
     * 获取已发放佣金记录。
     */
    protected function getReleasedLogItems(
        int $userId,
        int $limit,
        string $startTime = '',
        string $endTime = ''
    ): array
    {
        $query = app()->make(UserAssetLogService::class)->getModel()
            ->where('user_id', $userId)
            ->where('type', 'commission')
            ->where('change_type', 'in');

        if ($startTime !== '' && $endTime !== '') {
            $query->whereBetweenTime('create_time', $startTime, $endTime);
        }

        $rows = $query
            ->order('id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        return array_map(fn(array $row): array => $this->formatReleasedLogItem($row), $rows);
    }

    /**
     * 获取冻结中佣金记录。
     */
    protected function getFrozenCommissionItems(
        int $userId,
        int $limit,
        string $startTime = '',
        string $endTime = ''
    ): array
    {
        $firstQuery = $this->getFrozenFirstCommissionQuery($userId);
        $secondQuery = $this->getFrozenSecondCommissionQuery($userId);

        if ($startTime !== '' && $endTime !== '') {
            $this->applyFrozenTimeRange($firstQuery, $startTime, $endTime);
            $this->applyFrozenTimeRange($secondQuery, $startTime, $endTime, 'o.');
        }

        $firstRows = $firstQuery
            ->field('id, order_sn, user_id, first_commission_amount AS amount, receive_time, pay_time, update_time')
            ->order('id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $secondRows = $secondQuery
            ->field('o.id, o.order_sn, o.user_id, o.second_commission_amount AS amount, o.receive_time, o.pay_time, o.update_time')
            ->order('o.id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $firstItems = array_map(fn(array $row): array => $this->formatFrozenLogItem($row, '一级'), $firstRows);
        $secondItems = array_map(fn(array $row): array => $this->formatFrozenLogItem($row, '二级'), $secondRows);

        return array_merge($firstItems, $secondItems);
    }

    /**
     * 冻结中一级佣金查询。
     */
    protected function getFrozenFirstCommissionQuery(int $userId)
    {
        return $this->getCommissionOrderBaseQuery()
            ->where('referrer_id', $userId)
            ->where('first_commission_amount', '>', 0);
    }

    /**
     * 冻结中二级佣金查询。
     */
    protected function getFrozenSecondCommissionQuery(int $userId)
    {
        return app()->make(OrderService::class)->getModel()
            ->alias('o')
            ->where('o.commission_status', Order::COMMISSION_STATUS_FROZEN)
            ->whereIn('o.status', [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED])
            ->where('o.refund_status', 0)
            ->where('o.second_referrer_id', $userId)
            ->where('o.second_commission_amount', '>', 0);
    }

    /**
     * 佣金订单基础查询。
     */
    protected function getCommissionOrderBaseQuery()
    {
        return app()->make(OrderService::class)->getModel()
            ->where('commission_status', Order::COMMISSION_STATUS_FROZEN)
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED])
            ->where('refund_status', 0);
    }

    /**
     * 已支付佣金订单基础查询。
     */
    protected function getPaidCommissionOrderBaseQuery()
    {
        return app()->make(OrderService::class)->getModel()
            ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_WAIT_REVIEW, Order::STATUS_COMPLETED])
            ->where('refund_status', 0);
    }

    /**
     * 按佣金起算时间筛选冻结中订单。
     */
    protected function applyFrozenTimeRange($query, string $startTime, string $endTime, string $prefix = ''): void
    {
        $query->where(function ($subQuery) use ($startTime, $endTime, $prefix) {
            $subQuery->where(function ($goodsQuery) use ($startTime, $endTime, $prefix) {
                $goodsQuery->where($prefix . 'order_type', Order::ORDER_TYPE_GOODS)
                    ->whereBetweenTime($prefix . 'receive_time', $startTime, $endTime);
            })->whereOr(function ($virtualQuery) use ($startTime, $endTime, $prefix) {
                $virtualQuery->whereIn($prefix . 'order_type', [Order::ORDER_TYPE_CONTENT, Order::ORDER_TYPE_SPECIAL])
                    ->whereBetweenTime($prefix . 'pay_time', $startTime, $endTime);
            });
        });
    }

    /**
     * 规范化日期范围为完整时间区间。
     *
     * @return array{0: string, 1: string}
     */
    protected function normalizeDateRange(string $startDate, string $endDate): array
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);

        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            return ['', ''];
        }

        if (strtotime($startDate) > strtotime($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    }

    /**
     * 判断日期是否为 YYYY-MM-DD 格式。
     */
    protected function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    /**
     * 格式化已发放佣金记录。
     */
    protected function formatReleasedLogItem(array $row): array
    {
        $amount = (float) ($row['amount'] ?? 0);
        $orderNo = (string) ($row['order_no'] ?? '');
        $remark = (string) ($row['remark'] ?? '订单返佣');
        $createTime = (string) ($row['create_time'] ?? '');

        return [
            'id' => 'released-' . (int) ($row['id'] ?? 0),
            'title' => $remark,
            'tag' => '已结算',
            'tag_theme' => 'settled',
            'desc' => $orderNo !== '' ? '订单号: ' . $orderNo : (string) ($row['source_desc'] ?? ''),
            'amount' => '+ ¥' . $this->formatAmount($amount),
            'amount_type' => 'positive',
            'time' => $createTime,
            'icon' => 'person-filled',
            'icon_color' => '#666666',
            'dimmed' => false,
            'sort_time' => $createTime,
        ];
    }

    /**
     * 格式化冻结中佣金记录。
     */
    protected function formatFrozenLogItem(array $row, string $level): array
    {
        $baseTime = (string) (($row['receive_time'] ?? '') ?: (($row['pay_time'] ?? '') ?: ($row['update_time'] ?? '')));

        return [
            'id' => 'frozen-' . $level . '-' . (int) ($row['id'] ?? 0),
            'title' => '订单返佣（' . $level . '）',
            'tag' => '待入账',
            'tag_theme' => 'pending',
            'desc' => '订单号: ' . (string) ($row['order_sn'] ?? '') . ' · 冻结中',
            'amount' => '+ ¥' . $this->formatAmount((float) ($row['amount'] ?? 0)),
            'amount_type' => 'pending',
            'time' => $baseTime,
            'icon' => 'shop-filled',
            'icon_color' => '#666666',
            'dimmed' => false,
            'sort_time' => $baseTime,
        ];
    }

    /**
     * 金额格式化。
     */
    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
