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

use app\common\model\live\LiveRoomLevelConfig;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 直播间等级配置服务
 */
class LiveRoomLevelConfigService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return LiveRoomLevelConfig::class;
    }

    /**
     * 保存等级配置。
     */
    public function resourceSave(array $data)
    {
        return $this->create($this->normalizeLevelConfigData($data));
    }

    /**
     * 更新等级配置。
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $this->update($id, $this->normalizeLevelConfigData($data));
        return true;
    }

    /**
     * 获取等级配置列表，默认按等级值正序排列。
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        return $this
            ->getDefaultList($params, $fields, $with, $sort ?: ['level' => 'asc', 'id' => 'asc'])
            ->each(fn(array $item): array => $this->formatLevelConfig($item));
    }

    /**
     * 获取启用的等级选项列表（供前端下拉/展示使用）
     */
    public function getOptions(): array
    {
        $list = $this->getModel()
            ->where('status', 1)
            ->field(['id', 'level', 'name', 'icon', 'min_contribution', 'benefits'])
            ->order('min_contribution', 'asc')
            ->select()
            ->toArray();

        return array_map(fn(array $item): array => $this->formatLevelConfig($item), $list);
    }

    /**
     * 获取所有启用的等级配置（按最低贡献值升序），供升级计算使用
     */
    public function getEnabledLevels(): array
    {
        $list = $this->getModel()
            ->where('status', 1)
            ->order('min_contribution', 'asc')
            ->select()
            ->toArray();

        return array_map(fn(array $item): array => $this->formatLevelConfig($item), $list);
    }

    /**
     * 格式化等级配置，剔除已下线的权益字段。
     */
    public function formatLevelConfig(array $item): array
    {
        if (array_key_exists('benefits', $item)) {
            $item['benefits'] = $this->sanitizeBenefits($item['benefits']);
        }

        return $item;
    }

    /**
     * 仅保留当前仍支持的权益字段。
     */
    public function sanitizeBenefits(mixed $benefits): array
    {
        if (is_string($benefits)) {
            $decoded = json_decode($benefits, true);
            $benefits = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($benefits)) {
            $benefits = [];
        }

        $giftDiscount = (float)($benefits['gift_discount'] ?? 1.00);

        return [
            'gift_discount'  => max(0.01, min(1.00, $giftDiscount)),
            'upgrade_points' => max(0, (int)($benefits['upgrade_points'] ?? 0)),
        ];
    }

    /**
     * 规范化保存数据。
     */
    protected function normalizeLevelConfigData(array $data): array
    {
        if (array_key_exists('benefits', $data)) {
            $data['benefits'] = $this->sanitizeBenefits($data['benefits']);
        }

        return $data;
    }
}
