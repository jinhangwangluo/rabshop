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

namespace app\common\util;

/**
 * 配送门店判断工具类
 */
class DeliveryStoreJudger
{
    // 地理围栏判断工具类
    private GeoFenceUtil $geoFenceUtil;

    /**
     * @param GeoFenceUtil $geoFenceUtil
     */
    public function __construct(GeoFenceUtil $geoFenceUtil)
    {
        $this->geoFenceUtil = $geoFenceUtil;
    }

    /**
     * 主逻辑：判断实际配送门店
     * @param array $riderPath 骑手坐标列表（来自order/getDeliveryPath）
     * @param array $statusLogs 配送状态日志（来自推送API）
     * @param array $storeRanges 门店配送范围数据（来自shipping/fetch）
     * @return string|null        实际配送门店ID
     */
    public function judgeActualStore(array $riderPath, array $statusLogs, array $storeRanges): ?string
    {
        // 1. 获取关键时间节点
        $keyTimestamps = $this->getKeyTimestamps($statusLogs);

        // 2. 匹配最近坐标点
        $keyPoints = $this->matchCoordinates($riderPath, $keyTimestamps);

        // 3. 遍历判断配送范围
        foreach ($keyPoints as $point) {
            foreach ($storeRanges as $storeCode => $range) {
                if ($this->geoFenceUtil->isInRange($point, $range)) {
                    return $storeCode;
                }
            }
        }
        return null;
    }

    /**
     * 提取关键状态时间戳（取货、送达等）
     */
    private function getKeyTimestamps(array $statusLogs): array
    {
        $keys = [];
        foreach ($statusLogs as $log) {
            if (in_array($log['logistics_status'], [20, 40])) { // 20-取货 40-送达
                $keys[] = $log['operate_time'];
            }
        }
        return array_unique($keys);
    }

    /**
     * 匹配时间最近的坐标点
     */
    private function matchCoordinates(array $riderPath, array $timestamps): array
    {
        $result = [];
        foreach ($timestamps as $ts) {
            $nearest = null;
            $minDiff = PHP_INT_MAX;

            foreach ($riderPath as $point) {
                $diff = abs($point['timestamp'] - $ts);
                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $nearest = $point;
                }
            }

            if ($nearest && $minDiff <= 300) { // 允许5分钟误差
                $result[$ts] = [
                    'latitude' => $nearest['lat'],
                    'longitude' => $nearest['lng']
                ];
            }
        }
        return $result;
    }
}
