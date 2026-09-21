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

use app\common\exception\AdminResponseException;
use app\common\model\live\LiveGift;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 直播礼物服务
 */
class LiveGiftService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const MOBILE_GIFT_LIST_CACHE_KEY = 'mobile_gift_list';
    private const MOBILE_GIFT_LIST_CACHE_TTL = 60;

    protected function setModel(): string
    {
        return LiveGift::class;
    }

    /**
     * 保存礼物配置。
     */
    public function resourceSave(array $data)
    {
        $gift = $this->create($this->normalizeGiftData($data));
        $this->clearMobileGiftCache();
        return $gift;
    }

    /**
     * 更新礼物配置。
     */
    public function resourceUpdate(int|array $id, array $data)
    {
        $this->update($id, $this->normalizeGiftData($data));
        $this->clearMobileGiftCache();
        return true;
    }

    /**
     * 更新礼物状态。
     */
    public function resourceShowUpdate(int|array $id, array $data)
    {
        $this->update($id, $data);
        $this->clearMobileGiftCache();
        return true;
    }

    /**
     * 获取礼物列表，默认按排序值和 ID 倒序排列。
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], string|array|null $sort = null)
    {
        return $this->getDefaultList($params, $fields, $with, $sort ?: ['sort' => 'desc', 'id' => 'desc']);
    }

    /**
     * 获取可用礼物列表（前端使用）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAvailableGifts(): array
    {
        return $this->getModel()
            ->where('status', 1)
            ->order('sort desc, id asc')
            ->field(['id', 'name', 'icon', 'effect_url', 'price', 'exchange_amount', 'score'])
            ->select()
            ->toArray();
    }

    /**
     * 获取移动端可用礼物列表（带短缓存）。
     */
    public function getMobileAvailableGifts(): array
    {
        return $this->cacheTag()->remember(
            self::MOBILE_GIFT_LIST_CACHE_KEY,
            fn() => array_map(
                fn(array $item): array => $this->formatMobileGiftItem($item),
                $this->getAvailableGifts()
            ),
            self::MOBILE_GIFT_LIST_CACHE_TTL
        );
    }

    /**
     * 清理移动端礼物缓存。
     */
    public function clearMobileGiftCache(): void
    {
        $this->cacheTag()->delete(self::MOBILE_GIFT_LIST_CACHE_KEY);
    }

    /**
     * 格式化移动端礼物字段。
     */
    private function formatMobileGiftItem(array $item): array
    {
        return [
            'id' => (int)($item['id'] ?? 0),
            'name' => (string)($item['name'] ?? ''),
            'icon' => (string)($item['icon'] ?? ''),
            'effect_url' => (string)($item['effect_url'] ?? ''),
            'price' => (string)($item['price'] ?? '0.00'),
            'exchange_amount' => (string)($item['exchange_amount'] ?? '0.00'),
            'score' => (int)($item['score'] ?? 0),
        ];
    }

    /**
     * 规范化礼物配置数据。
     */
    protected function normalizeGiftData(array $data): array
    {
        if (array_key_exists('price', $data)) {
            $data['price'] = max(0, (int)$data['price']);
        }

        if (array_key_exists('exchange_amount', $data)) {
            $data['exchange_amount'] = number_format(max(0, (float)$data['exchange_amount']), 2, '.', '');
        }

        if (array_key_exists('effect_url', $data)) {
            $effectUrl = trim((string)$data['effect_url']);
            $effectPath = (string)(parse_url($effectUrl, PHP_URL_PATH) ?: $effectUrl);
            if ($effectUrl !== '' && !preg_match('/\.webm$/i', $effectPath)) {
                throw new AdminResponseException('礼物特效仅支持 WebM 视频');
            }
            $data['effect_url'] = $effectUrl;
        }

        return $data;
    }
}
