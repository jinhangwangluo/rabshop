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
 * 站点基础配置 db_config key 枚举
 * 用于 db_config() 调用时统一管理站点相关配置键名
 */
enum ConfigKeyEnum
{
    // ==================== 站点基础信息 ====================

    /** @var string 网站名称 */
    const SITE_NAME = 'site_name';

    /** @var string 网站根域名（含协议，如 https://example.com） */
    const WEB_HOST = 'web_host';

    /** @var string 登录页 Logo 图片路径 */
    const LOGIN_LOGO = 'login_logo';

    /** @var string 登录页左侧图片（平台端通用） */
    const LOGIN_IMAGE_GENERAL_PLATFORM = 'login_image_general_platform';

    /** @var string 平台自营店铺展示名称 */
    const PLATFORM_STORE_NAME = 'platform_store_name';

    /** @var string 平台自营店铺展示头像 */
    const PLATFORM_STORE_AVATAR = 'platform_store_avatar';

    /** @var string 平台自营店铺简短简介 */
    const PLATFORM_STORE_DESCRIPTION = 'platform_store_description';

    /** @var string 小程序登录后是否强制绑定手机号 */
    const MINIAPP_FORCE_BIND_PHONE = 'miniapp_force_bind_phone';

    /** @var string 移动端主题换色配置 */
    const SITE_THEME_CONFIG = 'site_theme_config';

    /** @var string 内容展示关闭开关（0=开启展示，1=关闭展示） */
    const CONTENT_DISPLAY_CLOSED = 'content_display_closed';

    /** @var string 专题展示关闭开关（0=开启展示，1=关闭展示） */
    const SPECIAL_DISPLAY_CLOSED = 'special_display_closed';

    // ==================== 版权 & 备案 ====================

    /** @var string 版权文本（如：Copyright © 2026 xxx） */
    const COPYRIGHT = 'copyright';

    /** @var string ICP 备案号（如：粤ICP备xxxxxx号） */
    const KEEP_ON_RECORD = 'keep_on_record';

    /** @var string 公安网安备案号 */
    const GOV_KEEP_ON_RECORD = 'gov_keep_on_record';

    // ==================== 第三方服务 ====================

    /** @var string 腾讯地图 Key */
    const TENCENT_MAP_KEY = 'tencent_map_key';

    /** @var string 阿里云市场物流查询 AppCode */
    const EXPRESS_QUERY_APPCODE = 'express_query_appcode';

    // ==================== 商品采集 ====================

    /** @var string 商品采集功能开关（0/1） */
    const PRODUCT_COLLECTOR_ENABLED = 'product_collector_enabled';

    /** @var string OneBound 商品采集调用 Key */
    const PRODUCT_COLLECTOR_KEY = 'product_collector_key';

    /** @var string OneBound 商品采集调用密钥 */
    const PRODUCT_COLLECTOR_SECRET = 'product_collector_secret';

    // ==================== 阿里云短信 ====================

    /** @var string 阿里云短信 AccessKey ID */
    const SMS_ACCESS_KEY = 'sms_access_key';

    /** @var string 阿里云短信 AccessKey Secret */
    const SMS_SECRET_KEY = 'sms_secret_key';

    /** @var string 阿里云短信签名 */
    const SMS_SIGN = 'sms_sign';

    /** @var string 阿里云短信验证码模板 Code */
    const SMS_CODE_TEMPLATE_ID = 'sms_code_templateid';

    // ==================== 消息推送邮箱 ====================

    /** @var string 邮箱推送接口地址 */
    const MESSAGE_PUSH_EMAIL_API_URL = 'message_push_email_api_url';

    /** @var string 邮箱推送 API Key */
    const MESSAGE_PUSH_EMAIL_API_KEY = 'message_push_email_api_key';

    /** @var string 邮箱推送标题模板 */
    const MESSAGE_PUSH_EMAIL_SUBJECT_TEMPLATE = 'message_push_email_subject_template';

    /** @var string 邮箱推送发件人名称 */
    const MESSAGE_PUSH_EMAIL_FROM_NAME = 'message_push_email_from_name';

    // ==================== 微信支付 ====================

    /** @var string 微信公众号 AppID */
    const WECHAT_APPID = 'wechat_appid';

    /** @var string 小程序 AppID */
    const WECHAT_MINI_APPID = 'program_appid';

    /** @var string 微信商户号 */
    const WECHAT_PAY_MCH_ID = 'wechat_mch_id';

    /** @var string 微信商户证书序列号 */
    const WECHAT_PAY_MCH_SERIAL_NO = 'wechat_mch_serial_no';

    /** @var string 微信 V3 API Key */
    const WECHAT_PAY_API_V3_KEY = 'wechat_api_v3_key';

    /** @var string 历史微信商户私钥配置；新配置统一使用 WECHAT_PAY_KEY_PATH */
    const WECHAT_PAY_PRIVATE_KEY = 'wechat_private_key';

    /** @var string 微信商户证书文件路径 */
    const WECHAT_PAY_CERT_PATH = 'wechat_cert_path';

    /** @var string 微信商户私钥文件路径 */
    const WECHAT_PAY_KEY_PATH = 'wechat_key_path';

    /** @var string 微信支付公钥 ID */
    const WECHAT_PAY_PUBLIC_KEY_ID = 'wechat_public_key_id';

    /** @var string 微信支付公钥或平台证书 */
    const WECHAT_PAY_PUBLIC_KEY = 'wechat_public_key';

    /** @var string 微信充值/会员支付回调地址 */
    const WECHAT_PAY_RECHARGE_NOTIFY_URL = 'wechat_recharge_notify_url';

    // ==================== 佣金提现 ====================

    /** @var string 佣金提现开关（0/1） */
    const COMMISSION_WITHDRAW_ENABLED = 'commission_withdraw_enabled';

    /** @var string 佣金提现服务费率（小数，如 0.001 表示 0.1%） */
    const COMMISSION_WITHDRAW_FEE_RATE = 'commission_withdraw_fee_rate';

    /** @var string 佣金提现最低手续费（元） */
    const COMMISSION_WITHDRAW_MIN_FEE = 'commission_withdraw_min_fee';

    /** @var string 佣金提现单笔最低金额（元） */
    const COMMISSION_WITHDRAW_MIN_AMOUNT = 'commission_withdraw_min_amount';

    /** @var string 佣金提现单笔最高金额（元） */
    const COMMISSION_WITHDRAW_MAX_AMOUNT = 'commission_withdraw_max_amount';

    /** @var string 佣金提现预计到账时间文案 */
    const COMMISSION_WITHDRAW_ARRIVAL_TIME = 'commission_withdraw_arrival_time';

    /** @var string 佣金提现须知（换行分隔多条） */
    const COMMISSION_WITHDRAW_NOTES = 'commission_withdraw_notes';

    /** @var string 佣金冻结天数 */
    const COMMISSION_FREEZE_DAYS = 'commission_freeze_days';

    /** @var string 商户资金冻结天数 */
    const MERCHANT_FUND_FREEZE_DAYS = 'merchant_fund_freeze_days';

    /** @var string 推广广告图 */
    const COMMISSION_PROMOTION_AD_IMAGE = 'commission_promotion_ad_image';

    /** @var string 分销推广海报配置 */
    const COMMISSION_PROMOTION_POSTER = 'commission_promotion_poster';

    // ==================== 分销配置 ====================

    /** @var string 分销总开关（0/1） */
    const DISTRIBUTION_ENABLED = 'distribution_enabled';

    /** @var string 分销等级：1=一级，2=二级 */
    const DISTRIBUTION_LEVEL = 'distribution_level';

    /** @var string 分销模式：all=人人分销，specified=指定分销 */
    const DISTRIBUTION_MODE = 'distribution_mode';

    /** @var string 一级分销佣金比例，百分比口径 */
    const DISTRIBUTION_FIRST_RATE = 'distribution_first_rate';

    /** @var string 二级分销佣金比例，百分比口径 */
    const DISTRIBUTION_SECOND_RATE = 'distribution_second_rate';

    // ==================== 订单积分抵扣 ====================

    /** @var string 积分抵扣比例：每 1 元需消耗的积分数 */
    const ORDER_POINTS_RATIO = 'order_points_ratio';

    /** @var string 单笔订单最多可抵扣积分数，0 表示不限制 */
    const ORDER_POINTS_DEDUCT_LIMIT = 'order_points_deduct_limit';

    // ==================== 直播金币 ====================

    /** @var string 直播金币兑换比例：1 元可兑换金币数 */
    const LIVE_COIN_EXCHANGE_RATE = 'live_coin_exchange_rate';

    // ==================== 客服 ====================

    /** @var string AI 客服知识库未命中时的默认回复 */
    const CS_AI_FALLBACK_REPLY = 'cs_ai_fallback_reply';

    // ==================== 会员 ====================

    /** @var string 会员功能总开关（0/1） */
    const MEMBER_ENABLED = 'member_enabled';

    /** @var string 会员积分倍数，默认 2 */
    const MEMBER_POINTS_MULTIPLIER = 'member_points_multiplier';

    /** @var string 会员折扣率，默认 1.00，例如 0.80 为 8 折 */
    const MEMBER_DISCOUNT_RATE = 'member_discount_rate';

    /** @var string 会员积分广告开关（0/1） */
    const MEMBER_POINTS_BANNER_ENABLED = 'member_points_banner_enabled';

    /** @var string 会员积分广告标题 */
    const MEMBER_POINTS_BANNER_TITLE = 'member_points_banner_title';

    /** @var string 会员积分广告简介 */
    const MEMBER_POINTS_BANNER_DESC = 'member_points_banner_desc';

    /** @var string 会员积分广告图片 */
    const MEMBER_POINTS_BANNER_IMAGE = 'member_points_banner_image';

    //积分

    /** @var string 积分功能总开关（0/1） */
    const POINTS_ENABLED = 'points_switch';
    /** @var string 积分签到功能开关（0/1） */
    const POINTS_ACTIVITY_SIGNIN_ENABLED = 'activity_signin_switch';

    // ==================== 每日签到 ====================

    /** @var string 关注店铺任务开关（0/1） */
    const ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_ENABLED = 'activity_signin_task_follow_merchant_enabled';

    /** @var string 关注店铺任务积分 */
    const ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_POINTS = 'activity_signin_task_follow_merchant_points';

    /** @var string 下单商品任务开关（0/1） */
    const ACTIVITY_SIGNIN_TASK_ORDER_GOODS_ENABLED = 'activity_signin_task_order_goods_enabled';

    /** @var string 下单商品任务积分 */
    const ACTIVITY_SIGNIN_TASK_ORDER_GOODS_POINTS = 'activity_signin_task_order_goods_points';

    /** @var string 加入购物车任务开关（0/1） */
    const ACTIVITY_SIGNIN_TASK_ADD_CART_ENABLED = 'activity_signin_task_add_cart_enabled';

    /** @var string 加入购物车任务积分 */
    const ACTIVITY_SIGNIN_TASK_ADD_CART_POINTS = 'activity_signin_task_add_cart_points';

    /** @var string 浏览专题任务开关（0/1） */
    const ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_ENABLED = 'activity_signin_task_browse_special_enabled';

    /** @var string 浏览专题任务积分 */
    const ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_POINTS = 'activity_signin_task_browse_special_points';
}
