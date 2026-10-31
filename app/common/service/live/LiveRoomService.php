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

namespace app\common\service\live;

use app\common\enum\LiveConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\live\LiveRoom;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\message\BackendMessageService;
use app\common\service\SensitiveFilterTrait;
use app\common\service\setting\AdminService;
use app\common\service\user\UserFavoriteService;
use app\common\service\user\UserService;
use Rabbit\service\live\LiveService;
use Rabbit\service\websocket\PushNotification;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;
use Throwable;

/**
 * 直播间服务
 */
class LiveRoomService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;
    use SensitiveFilterTrait;

    // 直播状态常量
    const STATUS_PENDING = 0; // 未开始
    const STATUS_LIVING = 1; // 直播中
    const STATUS_ENDED = 2; // 已结束

    // 审核状态常量
    const AUDIT_PENDING = 0; // 待审核
    const AUDIT_PASS = 1; // 审核通过
    const AUDIT_REJECT = 2; // 审核驳回

    // 直播类型常量
    const STREAM_TYPE_OBS = 'obs';
    const STREAM_TYPE_EXTERNAL = 'external';

    private const MOBILE_ROOM_DETAIL_CACHE_TTL = 10;
    private const MOBILE_ROOM_USERS_CACHE_TTL = 5;
    private const LIKE_RATE_LIMIT_SECONDS = 3;
    private const LIKE_RATE_LIMIT_COUNT = 50;

    protected function setModel(): string
    {
        return LiveRoom::class;
    }

    /**
     * 校验直播间商户归属。
     */
    public function checkMerchantOwner(int $merchantId, int $id, string $label = '直播间'): void
    {
        $room = $this->get($id);
        if (!$room) {
            throw new AdminResponseException($label . '不存在');
        }
        if ((int)($room['merchant_id'] ?? 0) !== $merchantId) {
            throw new AdminResponseException('无权操作该' . $label);
        }
    }

    /**
     * 获取指定商户名下的直播间 ID 列表。
     */
    public function getMerchantRoomIds(int $merchantId): array
    {
        if ($merchantId <= 0) {
            return [];
        }

        return $this->getModel()
            ->where('merchant_id', $merchantId)
            ->column('id') ?: [];
    }

    /**
     * 通过流名称获取直播间（供回调等场景使用）
     */
    public function getRoomByStreamName(string $streamName): ?LiveRoom
    {
        $streamName = trim($streamName);
        if ($streamName === '') {
            return null;
        }

        $room = $this->getModel()->where('stream_name', $streamName)->find();
        return $room ?: null;
    }

    /**
     * 获取直播间列表（携带主播和分类信息）
     * @param array $params
     * @param array|string $fields
     * @param array $with
     * @param string|array|null $sort
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getResourceList(array $params = [], $fields = [], array $with = [], $sort = null): mixed
    {
        $with = array_merge(['merchant', 'anchor', 'category'], $with);
        return $this->getDefaultList($params, $fields ?: ['*'], $with, $sort ?: ['sort' => 'desc', 'id' => 'desc']);
    }

    /**
     * 为移动端直播间列表追加当前用户的主播关注状态。
     *
     * @param array $data 直播间分页列表。
     * @param int $userId 当前用户ID，匿名为 0。
     * @return array
     */
    public function appendMobileAnchorFollowStatus(array $data, int $userId = 0): array
    {
        $list = is_array($data['list'] ?? null) ? $data['list'] : [];
        if ($list === []) {
            return $data;
        }

        $anchorIds = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $anchorId = (int)($item['live_anchor_id'] ?? $item['anchor_id'] ?? 0);
            if ($anchorId > 0) {
                $anchorIds[] = $anchorId;
            }
        }

        $statusMap = $userId > 0
            ? app()->make(UserFavoriteService::class)->getFavoriteStatusMap(
                $userId,
                UserFavoriteService::TARGET_TYPE_LIVE_ANCHOR,
                $anchorIds
            )
            : [];

        foreach ($list as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $anchorId = (int)($item['live_anchor_id'] ?? $item['anchor_id'] ?? 0);
            $isFollowed = $anchorId > 0 && (bool)($statusMap[$anchorId] ?? false);
            $item['is_followed'] = $isFollowed;
            $item['follow_status'] = $isFollowed ? 1 : 0;
            $list[$index] = $item;
        }

        $data['list'] = $list;
        return $data;
    }

    /**
     * 根据主播回填直播间归属商户。
     */
    protected function fillMerchantByAnchor(array $data): array
    {
        $anchorId = (int)($data['live_anchor_id'] ?? 0);
        if ($anchorId <= 0) {
            return $data;
        }

        /** @var LiveAnchorService $anchorService */
        $anchorService = app()->make(LiveAnchorService::class);
        $anchor = $anchorService->get($anchorId);
        if (!$anchor) {
            throw new AdminResponseException('关联主播不存在');
        }

        if (!$this->isExplicitPlatformRoom($data)) {
            $data['merchant_id'] = (int)($anchor['merchant_id'] ?? 0);
        }

        return $data;
    }

    /**
     * 判断是否已由调用方明确指定为平台直播间。
     */
    protected function isExplicitPlatformRoom(array $data): bool
    {
        return array_key_exists('merchant_id', $data) && (int)$data['merchant_id'] === 0;
    }

    /**
     * 标准化直播类型相关字段。
     */
    protected function normalizeStreamTypeData(array $data): array
    {
        $streamType = $this->normalizeStreamType((string)($data['stream_type'] ?? self::STREAM_TYPE_OBS));
        $externalPlayUrl = trim((string)($data['external_play_url'] ?? ''));

        if ($streamType === self::STREAM_TYPE_EXTERNAL) {
            $this->assertExternalPlayUrl($externalPlayUrl);
            $data['is_record'] = 0;
            $data['is_playback_auth'] = 0;
        } else {
            $externalPlayUrl = '';
        }

        $data['stream_type'] = $streamType;
        $data['external_play_url'] = $externalPlayUrl;

        return $data;
    }

    /**
     * 标准化直播类型。
     */
    protected function normalizeStreamType(string $streamType): string
    {
        $streamType = strtolower(trim($streamType));
        return in_array($streamType, [self::STREAM_TYPE_OBS, self::STREAM_TYPE_EXTERNAL], true)
            ? $streamType
            : self::STREAM_TYPE_OBS;
    }

    /**
     * 校验外链直播播放地址。
     */
    protected function assertExternalPlayUrl(string $url): void
    {
        $url = trim($url);
        if ($url === '') {
            throw new AdminResponseException('外链直播请填写直播链接');
        }

        if (!preg_match('/^(https?:\/\/|rtmp:\/\/)/i', $url)) {
            throw new AdminResponseException('直播链接仅支持 http、https 或 rtmp 地址');
        }
    }

    /**
     * 校验直播时间段。
     */
    protected function assertLiveTimeRange(array $data): void
    {
        $startTime = trim((string)($data['start_time'] ?? ''));
        $endTime = trim((string)($data['end_time'] ?? ''));
        if ($startTime === '' || $endTime === '') {
            return;
        }

        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);
        if ($startTimestamp !== false && $endTimestamp !== false && $startTimestamp >= $endTimestamp) {
            throw new AdminResponseException('结束时间必须晚于开始时间');
        }
    }

    /**
     * 新增直播间（覆盖 Trait 方法，加入敏感词过滤）
     * @param array $data
     * @return mixed
     */
    public function resourceSave(array $data): mixed
    {
        $this->assertLiveCategory($data);
        $data = $this->normalizeStreamTypeData($data);
        $this->assertLiveTimeRange($data);
        $data = $this->filterSensitiveFields($data, ['title', 'description', 'notice', 'welcome_msg', 'intro', 'keywords']);
        $data = $this->fillMerchantByAnchor($data);
        $data['audit_status'] = (int)($data['merchant_id'] ?? 0) > 0 ? self::AUDIT_PENDING : self::AUDIT_PASS;
        if (!array_key_exists('audit_remark', $data)) {
            $data['audit_remark'] = '';
        }
        $room = $this->create($data);
        $roomData = $room->toArray();
        if ((int)($roomData['merchant_id'] ?? 0) > 0) {
            app()->make(BackendMessageEventService::class)->auditSubmitted(
                BackendMessageService::SUB_TYPE_LIVE_ROOM_AUDIT,
                'live_room',
                (int)($roomData['id'] ?? 0),
                (int)$roomData['merchant_id'],
                (string)($roomData['title'] ?? '直播间')
            );
        }

        return $room;
    }

    /**
     * 更新直播间（覆盖 Trait 方法，加入敏感词过滤）
     * @param int|array $id
     * @param array $data
     * @return mixed
     */
    public function resourceUpdate($id, array $data): mixed
    {
        $this->assertLiveCategory($data);
        $data = $this->normalizeStreamTypeData($data);
        $this->assertLiveTimeRange($data);
        $data = $this->filterSensitiveFields($data, ['title', 'description', 'notice', 'welcome_msg', 'intro', 'keywords']);
        $current = $this->get((int)$id);

        if ($current && !array_key_exists('merchant_id', $data)) {
            $data['merchant_id'] = (int)($current['merchant_id'] ?? 0);
        }

        $data = $this->fillMerchantByAnchor($data);
        if ($this->isExplicitPlatformRoom($data)) {
            $data['audit_status'] = self::AUDIT_PASS;
            $data['audit_remark'] = '';
        } elseif ($current && (int)($current['merchant_id'] ?? 0) > 0) {
            $data['audit_status'] = self::AUDIT_PENDING;
            $data['audit_remark'] = '';
        }
        $this->update($id, $data);
        $fresh = $this->get((int)$id);
        if ($fresh && (int)($fresh['merchant_id'] ?? 0) > 0) {
            app()->make(BackendMessageEventService::class)->auditSubmitted(
                BackendMessageService::SUB_TYPE_LIVE_ROOM_AUDIT,
                'live_room',
                (int)$id,
                (int)$fresh['merchant_id'],
                (string)($fresh['title'] ?? '直播间')
            );
        }

        return true;
    }

    /** @param array $data 保存字段；未提交分类时保留原值。 @return void */
    protected function assertLiveCategory(array $data): void
    {
        if (array_key_exists('category_id', $data) && (int)$data['category_id'] > 0) {
            app()->make(LiveCategoryService::class, [], true)->resourceRead((int)$data['category_id']);
        }
    }

    /**
     * 审核直播间。
     */
    public function audit(int $id, int $auditStatus, string $remark = ''): bool
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        if ((int)$room->audit_status === self::AUDIT_PASS) {
            throw new AdminResponseException('该直播间已审核通过，无需重复审核');
        }
        if (!in_array($auditStatus, [self::AUDIT_PASS, self::AUDIT_REJECT], true)) {
            throw new AdminResponseException('审核状态参数错误');
        }
        if ($auditStatus === self::AUDIT_REJECT && $remark === '') {
            throw new AdminResponseException('驳回时必须填写原因');
        }

        $room->audit_status = $auditStatus;
        $room->audit_remark = $auditStatus === self::AUDIT_REJECT ? $remark : '';
        $room->save();
        app()->make(BackendMessageEventService::class)->auditResult(
            BackendMessageService::SUB_TYPE_LIVE_ROOM_AUDIT,
            'live_room',
            $id,
            (int)($room->merchant_id ?? 0),
            $auditStatus,
            $remark,
            (string)($room->title ?? '直播间')
        );

        return true;
    }

    /**
     * 管理端各 Tab 数量统计（与列表 Tab 筛选语义一致）。
     */
    public function getAdminTabStats(): array
    {
        $model = $this->getModel();

        return [
            'all' => $model->getDataCount(['tab' => 'all']),
            'platform' => $model->getDataCount(['tab' => 'platform']),
            'merchant' => $model->getDataCount(['tab' => 'merchant']),
            'pending' => $model->getDataCount(['tab' => 'pending']),
            'violation' => $model->getDataCount(['tab' => 'violation']),
        ];
    }

    /**
     * 切换直播状态（开播/暂停/结束）
     * 开播时若尚未生成推流地址，则自动生成
     *
     * @param int $id
     * @param int $status 0-未开始 1-直播中 2-已结束
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function switchStatus(int $id, int $status): array
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_LIVING, self::STATUS_ENDED], true)) {
            throw new AdminResponseException('直播状态参数错误');
        }
        if ($status === self::STATUS_LIVING && (int)($room->audit_status ?? self::AUDIT_PASS) !== self::AUDIT_PASS) {
            throw new AdminResponseException('直播间尚未审核通过，暂不可开播');
        }
        $streamType = $this->normalizeStreamType((string)($room->stream_type ?? self::STREAM_TYPE_OBS));
        $isExternalStream = $streamType === self::STREAM_TYPE_EXTERNAL;
        if ($status === self::STATUS_LIVING && $isExternalStream) {
            $this->assertExternalPlayUrl((string)($room->external_play_url ?? ''));
        }

        // 幂等保护：已经是目标状态，直接返回，不重复执行
        if ((int)$room->status === $status) {
            $statusText = [
                self::STATUS_PENDING => '未开始',
                self::STATUS_LIVING => '直播中',
                self::STATUS_ENDED => '已结束',
            ];
            $result = ['status' => $status, 'msg' => '当前已是' . ($statusText[$status] ?? '该状态') . '，无需重复操作'];

            // 开播状态下幂等返回时，也携带推流地址（避免多 tab 新连接拿不到）
            if ($status === self::STATUS_LIVING) {
                try {
                    $result['stream_info'] = $this->getStreamInfo($id);
                } catch (Throwable $e) {
                    // 推流地址不存在，忽略
                }
            }

            $this->clearMobileRoomCaches($id);
            return $result;
        }

        $prevStatus = (int)$room->status;
        $room->status = $status;

        // 每次进入「直播中」写入实际开播时间（创建页的计划时间仅作预告，不应挡住真实开播）
        if ($status === self::STATUS_LIVING && $prevStatus !== self::STATUS_LIVING) {
            $room->start_time = date('Y-m-d H:i:s');
        }
        // 每次进入「已结束」写入结束时间
        if ($status === self::STATUS_ENDED && $prevStatus !== self::STATUS_ENDED) {
            $room->end_time = date('Y-m-d H:i:s');
        }

        $room->save();

        $statusText = [
            self::STATUS_PENDING => '已重置为未开始',
            self::STATUS_LIVING => '已开播',
            self::STATUS_ENDED => '已结束',
        ];

        $result = ['status' => $status, 'msg' => $statusText[$status]];

        // 结束 OBS 直播：直接禁推并清空推/播流地址；外链直播不调用第三方直播服务
        if ($status === self::STATUS_ENDED && !$isExternalStream) {
            try {
                $closeResult = $this->closeStream($id);
                $result['stream_close'] = $closeResult['closed'] ?? false;
                $result['stream_close_warn'] = $closeResult['msg'] ?? '';
            } catch (Throwable $e) {
                // 禁推失败不阻断状态切换
                $result['stream_close_warn'] = '关播成功，但推流关闭失败：' . $e->getMessage();
            }

            // 录制回放归档（按直播间设置）
            if ((int)$room->is_record === 1) {
                try {
                    $archive = $this->archivePlaybackRecords($room);
                    $result['record_archive'] = $archive;
                } catch (Throwable $e) {
                    $result['record_archive_warn'] = '关播成功，但回放归档失败：' . $e->getMessage();
                }
            }
        }

        // 开播时恢复禁推并生成推流鉴权（若尚未生成/鉴权已失效）
        if ($status === self::STATUS_LIVING) {
            if ($isExternalStream) {
                $result['stream_info'] = $this->getStreamInfo($id);
                $result['msg'] = '已开播，外链直播地址已就绪';
            } else {
                try {
                    $streamInfo = $this->createStream($id, false);
                    $result['stream_info'] = $streamInfo;
                    $result['msg'] = '已开播，推流地址已就绪';
                } catch (Throwable $e) {
                    // 推流地址生成失败不阻断开播
                    $result['stream_warn'] = '开播成功，但推流地址生成失败：' . $e->getMessage();
                }
            }
        }

        $this->clearMobileRoomCaches($id);
        PushNotification::notifyConsoleAction($id, $status === self::STATUS_LIVING ? 'start_live' : 'end_live', $result);

        return $result;
    }

    /**
     * 切换热门状态
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function toggleHot(int $id): array
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        $room->is_hot = $room->is_hot ? 0 : 1;
        $room->save();

        return ['is_hot' => $room->is_hot];
    }

    /**
     * 获取直播间详情（含主播信息）
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomDetail(int $id): array
    {
        $room = $this->getModel()->with(['anchor', 'category'])->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }

        return array_merge($room->toArray(), $this->resolveLivePullMeta($room));
    }

    /**
     * 获取移动端直播间首屏详情快照。
     */
    public function getMobileRoomDetail(int $id, int $userId = 0): array
    {
        if ($id <= 0) {
            throw new AdminResponseException('直播间 ID 不能为空');
        }

        $data = $this->cacheTag()->remember(
            $this->getMobileRoomDetailCacheKey($id),
            function () use ($id) {
                $room = $this->getModel()->with(['anchor', 'category'])->find($id);
                if (!$room) {
                    throw new AdminResponseException('直播间不存在');
                }

                $roomData = array_merge($room->toArray(), $this->resolveLivePullMeta($room));
                $stats = $this->getMobileRoomStats($id);

                return [
                    'room' => $this->formatMobileRoom($roomData),
                    'stats' => $stats,
                ];
            },
            self::MOBILE_ROOM_DETAIL_CACHE_TTL
        );

        return $this->appendMobileRoomDetailFollowStatus($data, $userId);
    }

    /**
     * 为移动端直播间详情追加当前用户主播关注状态。
     *
     * 直播间详情主体使用公共缓存，关注状态按当前用户即时追加，避免把用户态写入公共缓存。
     */
    public function appendMobileRoomDetailFollowStatus(array $data, int $userId = 0): array
    {
        $room = is_array($data['room'] ?? null) ? $data['room'] : [];
        if ($room === []) {
            return $data;
        }

        $anchor = is_array($room['anchor'] ?? null) ? $room['anchor'] : [];
        $anchorId = (int)($anchor['id'] ?? $room['live_anchor_id'] ?? $room['anchor_id'] ?? 0);
        $isFollowed = false;
        if ($userId > 0 && $anchorId > 0) {
            $statusMap = app()->make(UserFavoriteService::class)->getFavoriteStatusMap(
                $userId,
                UserFavoriteService::TARGET_TYPE_LIVE_ANCHOR,
                [$anchorId]
            );
            $isFollowed = (bool)($statusMap[$anchorId] ?? false);
        }

        $followStatus = $isFollowed ? 1 : 0;
        $room['is_followed'] = $isFollowed;
        $room['follow_status'] = $followStatus;
        if ($anchorId > 0) {
            $anchor['is_followed'] = $isFollowed;
            $anchor['follow_status'] = $followStatus;
            $room['anchor'] = $anchor;
        }

        $data['room'] = $room;
        return $data;
    }

    /**
     * 清理移动端直播间相关缓存。
     */
    public function clearMobileRoomCaches(int $roomId): void
    {
        if ($roomId <= 0) {
            return;
        }

        $this->cacheTag()->clear();
        app()->make(LiveGoodsService::class)->clearMobileRoomGoodsCache($roomId);
    }

    /**
     * 为直播间生成/刷新推拉流地址
     * @param int $id 直播间ID
     * @param bool $force 是否强制重新生成（已有地址时）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function createStream(int $id, bool $force = false): array
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        if ($this->normalizeStreamType((string)($room->stream_type ?? self::STREAM_TYPE_OBS)) === self::STREAM_TYPE_EXTERNAL) {
            throw new AdminResponseException('外链直播无需生成第三方推流地址');
        }
        if ($room->status === self::STATUS_ENDED) {
            throw new AdminResponseException('直播已结束，无法创建推流');
        }

        // 幂等：当推/播流地址已清空（如关播时），允许在非 force 情况下重新生成
        $hasAliPush = !empty($room->ali_push_url) || !empty($room->ali_push_base) || !empty($room->ali_push_key);
        $hasQiniuPush = !empty($room->qiniu_push_url) || !empty($room->qiniu_push_base) || !empty($room->qiniu_push_key);
        $hasAliPull = !empty($room->ali_pull_url);
        $hasQiniuPull = !empty($room->qiniu_pull_url);
        $hasAnyStreamUrls = $hasAliPush || $hasQiniuPush || $hasAliPull || $hasQiniuPull;

        if (!$force && $room->stream_name && $hasAnyStreamUrls) {
            // 已有有效推/播流地址：非强制刷新幂等返回
            return $this->getStreamInfo($id);
        }

        $liveService = app()->make(LiveService::class);
        $streamName = $room->stream_name;
        if (!$streamName) {
            $streamName = $liveService->generateStreamName($room->id);
        }
        $streamData = $liveService->generateStreamUrls($streamName);

        // LiveService::generateStreamUrls 返回单云字段，根据 provider 写入对应的数据库字段
        $provider = $streamData['provider'] ?? '';
        $saveData = [
            'stream_name' => $streamData['stream_name'],
            'cloud_provider' => $provider,
            'stream_created_at' => $streamData['stream_created_at'],
        ];

        if ($provider === 'aliyun') {
            $saveData['ali_push_url'] = $streamData['push_url'] ?? '';
            $saveData['ali_pull_url'] = $streamData['pull_flv'] ?? '';
            $saveData['ali_push_base'] = $streamData['push_base'] ?? '';
            $saveData['ali_push_key'] = $streamData['push_key'] ?? '';
        } elseif ($provider === 'qiniu') {
            $saveData['qiniu_push_url'] = $streamData['push_url'] ?? '';
            $saveData['qiniu_pull_url'] = $streamData['pull_flv'] ?? '';
            $saveData['qiniu_push_base'] = $streamData['push_base'] ?? '';
            $saveData['qiniu_push_key'] = $streamData['push_key'] ?? '';
        }

        $room->save($saveData);

        $pullFlv = $streamData['pull_flv'] ?? '';

        // 返回给前端的统一结构（含分厂商字段，前端 streamInfo 直接使用）
        return [
            'stream_name' => $streamData['stream_name'],
            'cloud_provider' => $provider,
            'live_type' => $provider,
            'live_play_url' => $pullFlv,
            'ali_push_base' => $provider === 'aliyun' ? ($streamData['push_base'] ?? '') : ($room->ali_push_url ?? ''),
            'ali_push_key' => $provider === 'aliyun' ? ($streamData['push_key'] ?? '') : '',
            'ali_pull_url' => $provider === 'aliyun' ? $pullFlv : ($room->ali_pull_url ?? ''),
            'qiniu_push_base' => $provider === 'qiniu' ? ($streamData['push_base'] ?? '') : ($room->qiniu_push_url ?? ''),
            'qiniu_push_key' => $provider === 'qiniu' ? ($streamData['push_key'] ?? '') : '',
            'qiniu_pull_url' => $provider === 'qiniu' ? $pullFlv : ($room->qiniu_pull_url ?? ''),
            'push_url' => $streamData['push_url'] ?? '',
            'pull_flv' => $pullFlv,
            'pull_m3u8' => $streamData['pull_m3u8'] ?? '',
            'pull_rtmp' => $streamData['pull_rtmp'] ?? '',
            'stream_created_at' => $streamData['stream_created_at'],
            'has_stream' => true,
        ];
    }

    /**
     * 关闭直播流（结束直播时调用）
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function closeStream(int $id): array
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        if ($this->normalizeStreamType((string)($room->stream_type ?? self::STREAM_TYPE_OBS)) === self::STREAM_TYPE_EXTERNAL) {
            return [
                'stream_type' => self::STREAM_TYPE_EXTERNAL,
                'closed' => true,
                'msg' => '外链直播无需关闭第三方推流',
            ];
        }
        if (!$room->stream_name) {
            return ['msg' => '该直播间尚未创建推流'];
        }

        $result = app()->make(LiveService::class)->closeStream($room->stream_name);

        // 结束直播：禁推后清空推流/播流相关地址，便于下一次开播重新生成鉴权参数
        $clearData = [
            'ali_push_url' => '',
            'ali_pull_url' => '',
            'ali_push_base' => '',
            'ali_push_key' => '',
            'qiniu_push_url' => '',
            'qiniu_pull_url' => '',
            'qiniu_push_base' => '',
            'qiniu_push_key' => '',
            'stream_created_at' => null,
        ];
        $room->save($clearData);

        return [
            'stream_name' => $room->stream_name,
            'closed' => $result,
            'msg' => $result ? '流关闭指令已发送' : '流关闭失败，请检查云配置',
            'cleared' => true,
        ];
    }

    /**
     * 关播后：拉取云端 timeshift 片段并转存归档（生成可播放的回放文件）
     *
     * @param LiveRoom $room
     * @return array{saved_count:int, total:int, provider:string}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function archivePlaybackRecords(LiveRoom $room): array
    {
        $streamName = (string)($room->stream_name ?? '');
        if ($streamName === '') {
            return ['saved_count' => 0, 'total' => 0, 'provider' => (string)($room->cloud_provider ?? '')];
        }

        $provider = (string)($room->cloud_provider ?? '');
        if (!in_array($provider, ['aliyun', 'qiniu'], true)) {
            throw new AdminResponseException('当前直播间未配置有效云服务商，无法归档回放');
        }

        $bucket = '';
        $region = '';
        if ($provider === 'qiniu') {
            $bucket = (string)db_config('live_qiniu_record_bucket', '');
            $region = (string)db_config('live_qiniu_record_region', 'z0');
        } else {
            $bucket = (string)db_config('live_ali_record_bucket', '');
            $region = (string)db_config('live_ali_record_region', 'oss-cn-hangzhou.aliyuncs.com');
        }

        if ($bucket === '') {
            throw new AdminResponseException('回放归档桶未配置（请在直播配置中补齐 record_bucket 相关配置）');
        }

        $liveService = app()->make(LiveService::class);
        $recordList = $liveService->getRecordList(
            $streamName,
            (string)($room->start_time ?? ''),
            (string)($room->end_time ?? ''),
            $provider
        );

        $total = is_array($recordList) ? count($recordList) : 0;
        $savedCount = 0;
        $recordService = app()->make(LiveRoomRecordService::class);

        foreach ($recordList as $item) {
            $recordId = (string)($item['record_id'] ?? '');
            $startTime = (string)($item['start_time'] ?? '');
            $endTime = (string)($item['end_time'] ?? '');

            if ($recordId === '' || $startTime === '' || $endTime === '') {
                continue;
            }

            // 单片段幂等：已存在则跳过
            if ($recordService->hasCloudRecord((int)$room->id, $recordId)) {
                continue;
            }

            $fileKey = sprintf('live_room_record/room_%d/%s.flv', $room->id, $recordId);

            // 保存为 flv 归档文件
            $liveService->saveRecordToBucket(
                $streamName,
                $bucket,
                $fileKey,
                $startTime,
                $endTime,
                'flv',
                $provider
            );

            // 回放播放地址（qiniu 私有下载；aliyun 直接拼 URL）
            if ($provider === 'qiniu') {
                $playbackUrl = $liveService->getQiniuPrivateDownloadUrl($bucket, $region, $fileKey, 3600);
            } else {
                $ossDomain = $region ?: 'oss-cn-hangzhou.aliyuncs.com';
                $playbackUrl = sprintf('https://%s.%s/%s', $bucket, $ossDomain, ltrim($fileKey, '/'));
            }

            $recordService->createArchiveRecord([
                'room_id' => $room->id,
                'stream_name' => $streamName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'cloud_provider' => $provider,
                'record_id' => $recordId,
                'playback_url' => $playbackUrl,
                'record_bucket' => $bucket,
                'record_file_key' => $fileKey,
            ]);

            $savedCount++;
        }

        return ['saved_count' => $savedCount, 'total' => $total, 'provider' => $provider];
    }

    /**
     * 获取直播间推拉流信息
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getStreamInfo(int $id): array
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }

        [$aliPushBase, $aliPushKey] = $this->resolvePushBaseKey(
            (string)($room->ali_push_base ?? ''),
            (string)($room->ali_push_key ?? ''),
            (string)($room->ali_push_url ?? '')
        );
        [$aliPushBase, $aliPushKey] = $this->normalizeLegacyObsPushParts(
            $aliPushBase,
            $aliPushKey,
            (string)($room->stream_name ?? ''),
            'aliyun'
        );

        [$qiniuPushBase, $qiniuPushKey] = $this->resolvePushBaseKey(
            (string)($room->qiniu_push_base ?? ''),
            (string)($room->qiniu_push_key ?? ''),
            (string)($room->qiniu_push_url ?? '')
        );
        [$qiniuPushBase, $qiniuPushKey] = $this->normalizeLegacyObsPushParts(
            $qiniuPushBase,
            $qiniuPushKey,
            (string)($room->stream_name ?? ''),
            'qiniu'
        );

        return array_merge([
            'id' => $room->id,
            'title' => $room->title,
            'stream_type' => $this->normalizeStreamType((string)($room->stream_type ?? self::STREAM_TYPE_OBS)),
            'external_play_url' => (string)($room->external_play_url ?? ''),
            'stream_name' => $room->stream_name,
            'cloud_provider' => $room->cloud_provider,
            'ali_push_base' => $aliPushBase,
            'ali_push_key' => $aliPushKey,
            'ali_pull_url' => $room->ali_pull_url,
            'qiniu_push_base' => $qiniuPushBase,
            'qiniu_push_key' => $qiniuPushKey,
            'qiniu_pull_url' => $room->qiniu_pull_url,
            'stream_created_at' => $room->stream_created_at,
            'has_stream' => !empty($room->stream_name) || trim((string)($room->external_play_url ?? '')) !== '',
        ], $this->resolveLivePullMeta($room));
    }

    /**
     * 解析直播播放地址元信息
     *
     * @return array{
     *     live_play_url: string,
     *     live_type: string,
     *     pull_flv: string,
     *     pull_m3u8: string,
     *     pull_rtmp: string
     * }
     */
    private function resolveLivePullMeta(LiveRoom $room): array
    {
        $streamType = $this->normalizeStreamType((string)($room->stream_type ?? self::STREAM_TYPE_OBS));
        $externalPlayUrl = trim((string)($room->external_play_url ?? ''));
        if ($streamType === self::STREAM_TYPE_EXTERNAL) {
            return [
                'live_play_url' => $externalPlayUrl,
                'live_type' => self::STREAM_TYPE_EXTERNAL,
                'pull_flv' => $externalPlayUrl,
                'pull_m3u8' => $externalPlayUrl,
                'pull_rtmp' => '',
            ];
        }

        $aliPull = trim((string)($room->ali_pull_url ?? ''));
        $qiniuPull = trim((string)($room->qiniu_pull_url ?? ''));
        $provider = $this->inferLiveType((string)($room->cloud_provider ?? ''), $aliPull, $qiniuPull);
        $streamName = trim((string)($room->stream_name ?? ''));

        $pullFlv = $provider === 'aliyun' ? $aliPull : $qiniuPull;
        $pullM3u8 = '';
        $pullRtmp = '';

        if ($provider !== '' && $streamName !== '') {
            try {
                $driver = app()->make(LiveService::class)->driver($provider);
                $pullFlv = $pullFlv !== '' ? $pullFlv : $driver->buildPullUrl($streamName, 'flv');
                $pullM3u8 = $driver->buildPullUrl($streamName, 'm3u8');
                $pullRtmp = $driver->buildPullUrl($streamName, 'rtmp');
            } catch (Throwable $e) {
                // 保留库内已有拉流地址兜底
            }
        }

        if ($pullM3u8 === '' && $pullFlv !== '') {
            $pullM3u8 = $this->swapPullUrlExtension($pullFlv, 'm3u8');
        }

        if ((int)($room->is_playback_auth ?? 0) === 1 && $provider !== '') {
            $pullFlv = $this->appendPlaybackAuthUrl($pullFlv, $provider);
            $pullM3u8 = $this->appendPlaybackAuthUrl($pullM3u8, $provider);
        }

        return [
            'live_play_url' => $pullFlv,
            'live_type' => $provider,
            'pull_flv' => $pullFlv,
            'pull_m3u8' => $pullM3u8,
            'pull_rtmp' => $pullRtmp,
        ];
    }

    /**
     * 推断当前直播线路类型
     */
    private function inferLiveType(string $provider, string $aliPull, string $qiniuPull): string
    {
        if (in_array($provider, ['aliyun', 'qiniu'], true)) {
            return $provider;
        }

        if ($aliPull !== '') {
            return 'aliyun';
        }

        if ($qiniuPull !== '') {
            return 'qiniu';
        }

        return '';
    }

    /**
     * 替换直播播放地址扩展名
     */
    private function swapPullUrlExtension(string $url, string $extension): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['path'])) {
            return $url;
        }

        $path = (string)$parts['path'];
        $path = preg_replace('/\.(flv|m3u8|rtmp|mp4)$/i', '.' . ltrim($extension, '.'), $path) ?? $path;

        $scheme = (string)($parts['scheme'] ?? '');
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (string)$parts['port'] : '';
        $query = !empty($parts['query']) ? '?' . (string)$parts['query'] : '';

        return ($scheme !== '' ? $scheme . '://' : '') . $host . $port . $path . $query;
    }

    /**
     * 按线路类型补充播放鉴权参数
     */
    private function appendPlaybackAuthUrl(string $url, string $liveType): string
    {
        if ($url === '') {
            return '';
        }

        switch ($liveType) {
            case 'qiniu':
                return $this->appendQiniuPlaybackAuthUrl($url);
            case 'aliyun':
                return $this->appendAliyunPlaybackAuthUrl($url);
            default:
                return $url;
        }
    }

    /**
     * 七牛播放鉴权（时间戳防盗链）：追加 sign/t 参数
     */
    private function appendQiniuPlaybackAuthUrl(string $url): string
    {
        $key = (string)db_config(LiveConfigKeyEnum::QINIU_PLAY_AUTH_KEY, '');
        if ($key === '') {
            return $url;
        }

        $expireSeconds = (int)db_config(LiveConfigKeyEnum::QINIU_EXPIRE_SECONDS, 7200);
        $expireAt = time() + $expireSeconds;
        $tHex = strtolower(dechex($expireAt));

        $parts = parse_url($url);
        if (!$parts || empty($parts['path'])) {
            return $url;
        }

        $path = (string)$parts['path'];
        $sign = strtolower(md5($key . $path . $tHex));

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }

        // 私有下载 URL（带 token/e）已经具备访问控制能力，不再追加七牛热链鉴权参数 sign/t
        if (isset($query['token']) || (isset($query['sign']) && isset($query['t']))) {
            return $url;
        }
        $query['sign'] = $sign;
        $query['t'] = $tHex;

        $scheme = (string)($parts['scheme'] ?? '');
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (string)$parts['port'] : '';

        $rebuiltQuery = http_build_query($query);
        $rebuilt = ($scheme !== '' ? $scheme . '://' : '') . $host . $port . $path;
        if ($rebuiltQuery !== '') {
            $rebuilt .= '?' . $rebuiltQuery;
        }
        return $rebuilt;
    }

    /**
     * 阿里云播放鉴权（URL 鉴权）：追加 auth_key 参数
     *
     * 注意：此处将复用 `live_ali_access_secret` 作为 URL 鉴权的 PrivateKey（需与你们控制台域名配置一致）
     */
    private function appendAliyunPlaybackAuthUrl(string $url): string
    {
        $privateKey = (string)db_config(LiveConfigKeyEnum::ALI_ACCESS_SECRET, '');
        if ($privateKey === '') {
            return $url;
        }

        // 只对「直播拉流域名」追加 auth_key；如果是录制归档对象存储直连（OSS 域名），则跳过避免签名不兼容
        $aliPullDomain = (string)db_config(LiveConfigKeyEnum::ALI_PULL_DOMAIN, '');
        if ($aliPullDomain !== '') {
            $urlParts = parse_url($url);
            $urlHost = (string)($urlParts['host'] ?? '');
            $pullParts = parse_url($aliPullDomain);
            $pullHost = (string)($pullParts['host'] ?? $aliPullDomain);
            if ($urlHost !== '' && $pullHost !== '' && $urlHost !== $pullHost) {
                return $url;
            }
        }

        $expireSeconds = (int)db_config(LiveConfigKeyEnum::ALI_EXPIRE_SECONDS, 7200);
        $expireAt = time() + $expireSeconds;
        $rand = random_int(0, 999999);
        $uid = 0;

        $parts = parse_url($url);
        if (!$parts || empty($parts['path'])) {
            return $url;
        }

        $path = (string)$parts['path'];
        // URI 计算：去掉常见扩展名（.flv/.m3u8/.rtmp 等）
        $uri = preg_replace('/\.(flv|m3u8|rtmp|mp4)$/', '', $path) ?? $path;

        $md5Hash = md5($uri . '-' . $expireAt . '-' . $rand . '-' . $uid . '-' . $privateKey);
        $authKey = $expireAt . '-' . $rand . '-' . $uid . '-' . $md5Hash;

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        $query['auth_key'] = $authKey;

        $scheme = (string)($parts['scheme'] ?? '');
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (string)$parts['port'] : '';

        $rebuiltQuery = http_build_query($query);
        $rebuilt = ($scheme !== '' ? $scheme . '://' : '') . $host . $port . $path;
        if ($rebuiltQuery !== '') {
            $rebuilt .= '?' . $rebuiltQuery;
        }
        return $rebuilt;
    }

    /**
     * 从库内字段或完整推流 URL 解析出推流基础地址与鉴权串（与 Rabbit BaseDriver::splitPushUrl 一致）
     */
    private function resolvePushBaseKey(string $storedBase, string $storedKey, string $fullPushUrl): array
    {
        $base = $storedBase;
        $key = $storedKey;
        if (($base === '' || $key === '') && $fullPushUrl !== '' && str_contains($fullPushUrl, '?')) {
            [$parsedBase, $parsedKey] = explode('?', $fullPushUrl, 2);
            if ($base === '') {
                $base = $parsedBase;
            }
            if ($key === '') {
                $key = $parsedKey;
            }
        }
        if ($base === '' && $fullPushUrl !== '') {
            $base = $fullPushUrl;
        }

        return [$base, $key];
    }

    /**
     * 旧数据兼容：按首个 ? 拆分时 base 含「/流名」、key 仅为鉴权串；OBS 需 base=至 Hub/App、key=流名?鉴权
     *
     * @return array{0: string, 1: string}
     */
    private function normalizeLegacyObsPushParts(string $base, string $key, string $streamName, string $provider): array
    {
        if ($streamName === '' || $base === '' || $key === '') {
            return [$base, $key];
        }
        if (str_contains($key, '?')) {
            return [$base, $key];
        }
        $suffix = '/' . $streamName;
        if (!str_ends_with($base, $suffix)) {
            return [$base, $key];
        }
        if ($provider === 'qiniu' && str_starts_with($key, 'e=')) {
            return [substr($base, 0, -strlen($suffix)), $streamName . '?' . $key];
        }
        if ($provider === 'aliyun' && str_starts_with($key, 'auth_key=')) {
            return [substr($base, 0, -strlen($suffix)), $streamName . '?' . $key];
        }

        return [$base, $key];
    }

    /**
     * 获取直播间统计概览（观看总人数、在线人数、评论数、打赏次数）
     * @param int $id 直播间ID
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomStats(int $id): array
    {
        $room = $this->getModel()->find($id);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }

        $userService = app()->make(LiveRoomUserService::class);
        $chatService = app()->make(LiveChatRecordService::class);

        $onlineCount = $userService->getOnlineCount($id);
        $totalCount = $userService->countRoomUsers($id);
        $commentCount = $chatService->countRoomRecords($id);

        return [
            'room_id' => $id,
            'title' => $room->title,
            'status' => $room->status,
            'online_count' => $onlineCount,
            'total_count' => $totalCount,
            'comment_count' => $commentCount,
            'reward_count' => 0, // 打赏记录暂未实现独立表，预留字段
            'reward_amount' => '0.00',
        ];
    }

    /**
     * 获取移动端直播间统计字段。
     */
    public function getMobileRoomStats(int $id): array
    {
        $stats = $this->getRoomStats($id);
        $room = $this->get(['id' => $id], ['view_count', 'like_count']);
        $giftSummary = app()->make(LiveGiftRecordService::class)->getRoomGiftSummary($id);

        return [
            'view_count' => (int)($room['view_count'] ?? 0),
            'like_count' => (int)($room['like_count'] ?? 0),
            'online_count' => (int)($stats['online_count'] ?? 0),
            'total_user_count' => (int)($stats['total_count'] ?? 0),
            'comment_count' => (int)($stats['comment_count'] ?? 0),
            'reward_count' => (int)($giftSummary['reward_count'] ?? 0),
            'reward_amount' => (string)($giftSummary['reward_amount'] ?? '0.00'),
        ];
    }

    /**
     * 获取直播间用户列表（分页）
     * @param int $id 直播间ID
     * @param int $page
     * @param int $limit
     * @return array{list: array, total: int}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomUsers(int $id, int $page = 1, int $limit = 20): array
    {
        $userService = app()->make(LiveRoomUserService::class);
        return $userService->getRoomUserPage($id, $page, $limit);
    }

    /**
     * 获取移动端直播间观看用户列表。
     */
    public function getMobileRoomUsers(int $id, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));

        return $this->cacheTag()->remember(
            $this->getMobileRoomUsersCacheKey($id, $page, $limit),
            function () use ($id, $page, $limit) {
                $userService = app()->make(LiveRoomUserService::class);
                $levelService = app()->make(LiveRoomUserLevelService::class);
                $data = $userService->getRoomUserPage($id, $page, $limit, [
                    'user_id',
                    'nickname',
                    'avatar',
                    'is_online',
                    'last_enter_time',
                ]);
                $list = $data['list'] ?? [];

                $userIds = array_values(array_filter(array_map(fn($item) => (int)($item['user_id'] ?? 0), $list)));
                $levelMap = $levelService->getRoomUserLevelMap($id, $userIds);

                return [
                    'list' => array_map(fn(array $item): array => $this->formatMobileRoomUser($item, $levelMap), $list),
                    'total' => (int)($data['total'] ?? 0),
                    'page' => $page,
                    'limit' => $limit,
                ];
            },
            self::MOBILE_ROOM_USERS_CACHE_TTL
        );
    }

    /**
     * 获取直播间评论列表（分页）
     * @param int $id 直播间ID
     * @param int $page
     * @param int $limit
     * @return array{list: array, total: int}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getRoomComments(int $id, int $page = 1, int $limit = 20): array
    {
        $chatService = app()->make(LiveChatRecordService::class);
        return $chatService->getRoomRecordPage($id, $page, $limit);
    }

    /**
     * 获取移动端直播间历史评论列表。
     * @param int $id 直播间ID
     * @param int $page
     * @param int $limit
     * @return array{list: array, total: int}
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMobileRoomComments(int $id, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $data = $this->getRoomComments($id, $page, $limit);
        $list = array_reverse($data['list'] ?? []);

        return [
            'list' => array_map(fn(array $item): array => $this->formatMobileComment($item), $list),
            'total' => (int)($data['total'] ?? 0),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * 移动端批量上报直播间点赞。
     */
    public function submitMobileRoomLike(int $roomId, int $userId, int $count): array
    {
        if ($roomId <= 0) {
            throw new AdminResponseException('直播间 ID 不能为空');
        }
        if ($userId <= 0) {
            throw new AdminResponseException('请先登录后点赞');
        }

        $count = max(1, min(50, $count));
        $room = $this->get(['id' => $roomId], ['id', 'status']);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }
        if ((int)($room['status'] ?? 0) !== self::STATUS_LIVING) {
            throw new AdminResponseException('直播未开始或已结束，暂不可点赞');
        }
        $this->checkLikeRateLimit($roomId, $userId, $count);

        $this->getModel()->where('id', $roomId)->inc('like_count', $count)->update();
        $likeCount = (int)$this->getModel()->where('id', $roomId)->value('like_count');

        $data = [
            'room_id' => $roomId,
            'live_room_id' => $roomId,
            'user_id' => $userId,
            'count' => $count,
            'like_count' => $likeCount,
            'timestamp' => time(),
        ];

        PushNotification::broadcastRoomLike($roomId, $data);
        $this->clearMobileRoomCaches($roomId);

        return $data;
    }

    /**
     * 校验直播间点赞频率。
     */
    private function checkLikeRateLimit(int $roomId, int $userId, int $count): void
    {
        $cacheKey = sprintf('live_like_limit:%d:%d', $roomId, $userId);
        $current = (int)Cache::get($cacheKey, 0);
        if ($current + $count > self::LIKE_RATE_LIMIT_COUNT) {
            throw new AdminResponseException('点赞太快了，请稍后再试');
        }

        Cache::set($cacheKey, $current + $count, self::LIKE_RATE_LIMIT_SECONDS);
    }

    /**
     * 删除直播间评论
     * @param int $roomId 直播间ID
     * @param int $commentId 评论ID
     * @return void
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function deleteRoomComment(int $roomId, int $commentId): void
    {
        $chatService = app()->make(LiveChatRecordService::class);
        $chatService->deleteRoomRecord($roomId, $commentId);
    }

    /**
     * 后台管理员按绑定用户身份发送直播间评论。
     */
    public function sendAdminComment(int $roomId, int $adminId, string $content): array
    {
        if ($roomId <= 0) {
            throw new AdminResponseException('直播间 ID 不能为空');
        }
        if ($adminId <= 0) {
            throw new AdminResponseException('管理员身份无效');
        }
        $content = trim($content);
        if ($content === '') {
            throw new AdminResponseException('请输入回复内容');
        }

        $room = $this->get(['id' => $roomId], ['id', 'status']);
        if (!$room) {
            throw new AdminResponseException('直播间不存在');
        }

        $admin = app()->make(AdminService::class)->get($adminId, ['id', 'user_id']);
        $userId = (int)($admin['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new AdminResponseException('当前管理员未绑定用户，无法发送回复');
        }

        $user = app()->make(UserService::class)->get(['uid' => $userId], ['uid', 'nickname', 'avatar']);
        if (!$user) {
            throw new AdminResponseException('绑定用户不存在');
        }

        $content = $this->filterSensitiveText($content);
        if (trim($content, '*') === '' && strlen($content) > 0) {
            throw new AdminResponseException('消息包含敏感词，发送失败');
        }

        $msgId = 'chat_' . time() . '_' . $userId . '_' . mt_rand(1000, 9999);
        $sendTime = date('Y-m-d H:i:s');
        $record = app()->make(LiveChatRecordService::class)->createRecord([
            'msg_id' => $msgId,
            'room_id' => $roomId,
            'user_id' => $userId,
            'nickname' => (string)($user['nickname'] ?? ''),
            'avatar' => (string)($user['avatar'] ?? ''),
            'content' => $content,
            'msg_type' => 'text',
            'send_time' => $sendTime,
        ]);

        $chatData = [
            'id' => (int)($record['id'] ?? 0),
            'msg_id' => $msgId,
            'room_id' => $roomId,
            'user_id' => $userId,
            'nickname' => (string)($user['nickname'] ?? ''),
            'avatar' => (string)($user['avatar'] ?? ''),
            'content' => $content,
            'msg_type' => 'text',
            'send_time' => $sendTime,
            'create_time' => (string)($record['create_time'] ?? $sendTime),
            'timestamp' => time(),
        ];

        PushNotification::sendChatMessage($roomId, $chatData);

        return $chatData;
    }

    /**
     * 禁言直播间用户（HTTP 方式）
     * @param int $roomId 直播间ID
     * @param int $userId 用户ID
     * @param int $adminId 操作管理员ID
     * @param int $duration 禁言时长（秒），0=永久
     * @param string $reason 禁言原因
     * @return array
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function muteRoomUser(int $roomId, int $userId, int $adminId, int $duration = 3600, string $reason = ''): array
    {
        $userService = app()->make(LiveRoomUserService::class);
        return $userService->muteUser($roomId, $userId, $adminId, $duration, $reason);
    }

    /**
     * 踢出直播间用户（HTTP 方式，仅标记离线）
     * @param int $roomId
     * @param int $userId
     * @return void
     * @throws AdminResponseException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function kickRoomUser(int $roomId, int $userId): void
    {
        $userService = app()->make(LiveRoomUserService::class);
        if (!$userService->hasRoomUser($roomId, $userId)) {
            throw new AdminResponseException('该用户未进入过此直播间');
        }
        $userService->userLeave($roomId, $userId);
    }

    /**
     * 移动端直播间详情缓存 key。
     */
    private function getMobileRoomDetailCacheKey(int $roomId): string
    {
        return 'mobile_room_detail:' . $roomId;
    }

    /**
     * 移动端直播间用户缓存 key。
     */
    private function getMobileRoomUsersCacheKey(int $roomId, int $page, int $limit): string
    {
        return 'mobile_room_users:' . $roomId . ':' . $page . ':' . $limit;
    }

    /**
     * 格式化移动端直播间基础字段。
     */
    private function formatMobileRoom(array $room): array
    {
        $anchor = is_array($room['anchor'] ?? null) ? $room['anchor'] : [];
        $category = is_array($room['category'] ?? null) ? $room['category'] : [];

        return [
            'id' => (int)($room['id'] ?? 0),
            'title' => (string)($room['title'] ?? ''),
            'status' => (int)($room['status'] ?? 0),
            'like_count' => (int)($room['like_count'] ?? 0),
            'cover_url' => (string)($room['cover'] ?? ''),
            'banner_url' => (string)($room['banner'] ?? ''),
            'poster_url' => (string)($room['poster'] ?? ''),
            'description' => (string)($room['description'] ?? ''),
            'notice' => (string)($room['notice'] ?? ''),
            'welcome_msg' => (string)($room['welcome_msg'] ?? ''),
            'tags' => $this->formatMobileTags((string)($room['tags'] ?? '')),
            'start_time' => (string)($room['start_time'] ?? ''),
            'end_time' => (string)($room['end_time'] ?? ''),
            'stream_type' => $this->normalizeStreamType((string)($room['stream_type'] ?? self::STREAM_TYPE_OBS)),
            'external_play_url' => (string)($room['external_play_url'] ?? ''),
            'live_play_url' => (string)($room['live_play_url'] ?? ''),
            'pull_flv' => (string)($room['pull_flv'] ?? ''),
            'pull_m3u8' => (string)($room['pull_m3u8'] ?? ''),
            'live_type' => (string)($room['live_type'] ?? ''),
            'allow_landscape' => (int)($room['allow_landscape'] ?? 0),
            'anchor' => [
                'id' => (int)($anchor['id'] ?? $room['live_anchor_id'] ?? 0),
                'name' => (string)($anchor['name'] ?? ''),
                'avatar' => (string)($anchor['avatar'] ?? ''),
                'description' => (string)($anchor['description'] ?? ''),
            ],
            'category' => [
                'id' => (int)($category['id'] ?? $room['category_id'] ?? 0),
                'name' => (string)($category['name'] ?? ''),
            ],
        ];
    }

    /**
     * 格式化标签字符串。
     */
    private function formatMobileTags(string $tags): array
    {
        if ($tags === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags))));
    }

    /**
     * 格式化移动端直播间用户字段。
     */
    private function formatMobileRoomUser(array $item, array $levelMap): array
    {
        $userId = (int)($item['user_id'] ?? 0);
        $level = $levelMap[$userId] ?? [];

        return [
            'user_id' => $userId,
            'nickname' => (string)($item['nickname'] ?? ''),
            'avatar' => (string)($item['avatar'] ?? ''),
            'is_online' => (int)($item['is_online'] ?? 0),
            'last_enter_time' => (string)($item['last_enter_time'] ?? ''),
            'level_name' => $level ? 'Lv.' . (int)($level['level'] ?? 0) : '',
            'contribution' => (string)($level['contribution'] ?? '0.00'),
        ];
    }

    /**
     * 格式化移动端历史评论字段。
     */
    private function formatMobileComment(array $item): array
    {
        $sendTime = (string)($item['send_time'] ?? '');

        return [
            'id' => (int)($item['id'] ?? 0),
            'msg_id' => (string)($item['msg_id'] ?? ''),
            'user_id' => (int)($item['user_id'] ?? 0),
            'nickname' => (string)($item['nickname'] ?? ''),
            'avatar' => (string)($item['avatar'] ?? ''),
            'content' => (string)($item['content'] ?? ''),
            'msg_type' => (string)($item['msg_type'] ?? 'text'),
            'send_time' => $sendTime,
            'timestamp' => $sendTime !== '' ? strtotime($sendTime) : 0,
        ];
    }
}
