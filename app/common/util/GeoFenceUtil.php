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
 * GeoFenceUtil
 * 配送范围判断工具
 */
class GeoFenceUtil
{
    /**
     * 判断坐标是否在配送范围内
     * @param array $point ['latitude'=>31.23, 'longitude'=>121.47]
     * @param array $range 配送范围数据结构
     */
    public function isInRange(array $point, array $range): bool
    {
        // 美团配送范围可能有多种形式，示例实现多边形判断
        foreach ($range['areas'] as $area) {
            if ($this->inPolygon($point, $area['coordinates'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 射线法判断点是否在多边形内
     */
    private function inPolygon(array $point, array $polygon): bool
    {
        $intersections = 0;
        $count = count($polygon);

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;

            $xi = $polygon[$i]['longitude'];
            $yi = $polygon[$i]['latitude'];
            $xj = $polygon[$j]['longitude'];
            $yj = $polygon[$j]['latitude'];

            if (($yi > $point['latitude']) !== ($yj > $point['latitude'])) {
                $slope = ($point['latitude'] - $yi) / ($yj - $yi);
                if ($xi + $slope * ($xj - $xi) >= $point['longitude']) {
                    $intersections++;
                }
            }
        }
        return ($intersections % 2) === 1;
    }
}
