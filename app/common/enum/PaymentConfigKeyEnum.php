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
 * 支付配置 key 枚举。
 */
enum PaymentConfigKeyEnum
{
    /** @var string 支付方式展示配置 */
    const PAYMENT_DISPLAY_CONFIG = 'payment_display_config';

    /** @var array 微信支付配置字段 */
    const WECHAT_CONFIG_FIELDS = [
        ['key' => 'wechat_pay_version', 'label' => '微信支付版本', 'type' => 'select', 'placeholder' => '请选择微信支付版本', 'tip' => 'V2 通常用于 JSAPI/H5/Native 等老版商户接口；V3 用于新版 API、平台证书和商户私钥验签场景。', 'options' => [
            ['label' => '微信支付 V2', 'value' => 'v2'],
            ['label' => '微信支付 V3', 'value' => 'v3'],
        ]],
        ['key' => WechatConfigKeyEnum::APPID, 'label' => '公众号 AppID', 'type' => 'input', 'placeholder' => '请输入公众号 AppID', 'tip' => '复用「公众号配置」中的 AppID，支付发起使用的公众号 AppID 需与网页授权 openid 来源一致。'],
        ['key' => WechatConfigKeyEnum::SECRET, 'label' => '公众号 AppSecret', 'type' => 'password', 'placeholder' => '请输入公众号 AppSecret', 'tip' => '复用「公众号配置」中的 AppSecret，用于微信网页授权获取 openid。'],
        ['key' => 'wechat_mch_id', 'label' => '微信商户号', 'type' => 'input', 'placeholder' => '请输入微信商户号', 'tip' => '填写微信支付商户平台的商户号 mch_id，不是 AppID。'],
        ['key' => 'wechat_api_key', 'label' => '微信 V2 API Key', 'type' => 'password', 'placeholder' => '请输入微信支付 V2 API Key', 'tip' => '在微信商户平台「账户中心 - API安全 - API密钥」设置的 V2 API Key，用于 V2 接口签名。', 'visible_version' => 'v2'],
        ['key' => 'wechat_api_v3_key', 'label' => '微信 V3 API Key', 'type' => 'password', 'placeholder' => '请输入微信支付 V3 API Key', 'tip' => '在微信商户平台「账户中心 - API安全 - APIv3密钥」设置的 32 位密钥，用于 V3 回调解密和验签。', 'visible_version' => 'v3'],
        ['key' => 'wechat_mch_serial_no', 'label' => '微信商户证书序列号', 'type' => 'input', 'placeholder' => '请输入微信支付 V3 商户证书序列号', 'tip' => '填写 API 证书序列号，可在微信商户平台 API 证书页面查看，或通过证书文件查询 serial_no。', 'visible_version' => 'v3'],
        ['key' => 'wechat_cert_path', 'label' => '微信商户证书文件', 'type' => 'file', 'placeholder' => '请上传 apiclient_cert.pem', 'tip' => '退款请求使用的商户 API 证书文件，上传后会保存到非公开目录并返回项目相对路径。', 'visible_version' => 'v3', 'accept' => '.pem', 'max_size' => 2, 'storage_type' => 'payment_cert'],
        ['key' => 'wechat_key_path', 'label' => '微信商户私钥文件', 'type' => 'file', 'placeholder' => '请上传 apiclient_key.pem', 'tip' => '统一用于微信 V3 支付签名和退款 ssl_key，上传后会保存到非公开目录并返回项目相对路径。', 'visible_version' => 'v3', 'accept' => '.pem', 'max_size' => 2, 'storage_type' => 'payment_cert'],
        ['key' => 'wechat_public_key_id', 'label' => '微信支付公钥 ID', 'type' => 'input', 'placeholder' => '请输入 PUB_KEY_ID_ 开头的微信支付公钥 ID', 'tip' => '使用微信支付公钥模式时填写，必须与回调请求头 Wechatpay-Serial 完全一致。', 'visible_version' => 'v3'],
        ['key' => 'wechat_public_key', 'label' => '微信支付公钥/平台证书', 'type' => 'textarea', 'placeholder' => '请输入微信支付公钥或微信平台证书', 'tip' => '公钥模式填写 BEGIN/END PUBLIC KEY 完整内容；证书模式填写微信平台证书。不要填写 apiclient_cert.pem 商户 API 证书。', 'visible_version' => 'v3'],
    ];

    /** @var array 支付宝支付配置字段 */
    const ALIPAY_CONFIG_FIELDS = [
        ['key' => 'alipay_app_id', 'label' => '支付宝 AppID', 'type' => 'input', 'placeholder' => '请输入支付宝 AppID'],
        ['key' => 'alipay_private_key', 'label' => '支付宝应用私钥', 'type' => 'textarea', 'placeholder' => '请输入支付宝应用私钥'],
        ['key' => 'alipay_public_key', 'label' => '支付宝公钥', 'type' => 'textarea', 'placeholder' => '请输入支付宝公钥'],
        ['key' => 'alipay_order_notify_url', 'label' => '支付宝订单支付回调地址', 'type' => 'input', 'placeholder' => '请输入支付宝订单支付回调地址'],
        ['key' => 'alipay_recharge_notify_url', 'label' => '支付宝充值会员回调地址', 'type' => 'input', 'placeholder' => '请输入支付宝充值/会员支付回调地址'],
    ];

    /** @var array 余额支付配置字段 */
    const YUE_CONFIG_FIELDS = [
        ['key' => 'balance_pay_status', 'label' => '余额支付开关', 'type' => 'switch', 'placeholder' => ''],
    ];

    /** @var array 线下支付配置字段 */
    const OFFLINE_CONFIG_FIELDS = [
        ['key' => 'offline_pay_instructions', 'label' => '线下支付说明', 'type' => 'textarea', 'placeholder' => '请输入线下转账、到店付款等支付说明'],
    ];

    /** @var array 支付方式对应独立配置字段 */
    const PAY_TYPE_CONFIG_FIELDS = [
        PayTypeEnum::PAY_TYPE_WECHAT => self::WECHAT_CONFIG_FIELDS,
        PayTypeEnum::PAY_TYPE_ALIPAY => self::ALIPAY_CONFIG_FIELDS,
        PayTypeEnum::PAY_TYPE_YUE => self::YUE_CONFIG_FIELDS,
        PayTypeEnum::PAY_TYPE_OFFLINE => self::OFFLINE_CONFIG_FIELDS,
    ];

    /**
     * 获取指定支付方式配置字段。
     *
     * @param string $payType 支付类型。
     * @return array 配置字段列表。
     */
    public static function fieldsByPayType(string $payType): array
    {
        return self::PAY_TYPE_CONFIG_FIELDS[$payType] ?? [];
    }

    /**
     * 获取全部独立配置 key 与名称。
     *
     * @return array 配置 key 名称映射。
     */
    public static function settingKeys(): array
    {
        $keys = [];
        foreach (self::PAY_TYPE_CONFIG_FIELDS as $fields) {
            foreach ($fields as $field) {
                $keys[$field['key']] = $field['label'];
            }
        }
        return $keys;
    }
}
