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

namespace app\common\enum;

/**
 * 订单配置 db_config key 枚举。
 */
enum OrderConfigKeyEnum
{
    /** @var string 未支付订单自动取消分钟数 */
    const UNPAID_CANCEL_MINUTES = 'order_unpaid_cancel_minutes';

    /** @var string 未支付订单自动取消扫描间隔秒数 */
    const UNPAID_CANCEL_SCAN_SECONDS = 'order_unpaid_cancel_scan_seconds';

    /** @var string 未支付订单自动取消每轮处理数量 */
    const UNPAID_CANCEL_BATCH_LIMIT = 'order_unpaid_cancel_batch_limit';

    /** @var string 内容/专题订单赠送积分冻结天数 */
    const GIFT_POINTS_FREEZE_DAYS = 'order_gift_points_freeze_days';
}
