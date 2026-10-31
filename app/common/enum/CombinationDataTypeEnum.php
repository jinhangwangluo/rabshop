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
 * 组合数据 data_type 枚举.
 *
 * ll_combination_data.data_type 来源于组合数据配置的 config_key。
 * 后续新增组合数据配置时，请同步在此处补充常量，便于 db_combination_data() 调用。
 */
class CombinationDataTypeEnum
{
    /** @var string 提现金额配置 */
    public const WITHDRAWAL_AMOUNT = 'withdrawal_amount';

    /** @var string 充值配置 */
    public const RECHARGE = 'recharge';

    /** @var string 直播金币充值快捷数量配置 */
    public const LIVE_COIN_RECHARGE = 'live_coin_recharge';

    /** @var string 会员套餐配置 */
    public const MEMBER_PACKAGE = 'member_package';

    /** @var string 会员权益配置 */
    public const MEMBER_BENEFIT = 'member_benefit';

    /** @var string 7天签到奖励配置 */
    public const ACTIVITY_SIGNIN_REWARD = 'activity_signin_reward';

    /** @var string 分销推广海报配置 */
    public const COMMISSION_PROMOTION_POSTER = 'commission_promotion_poster';

}
