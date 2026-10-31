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
 * 微信公众号配置 db_config key 枚举
 * 用于 db_config() 调用时统一管理微信相关配置键名
 */
enum WechatConfigKeyEnum
{
    /** @var string 微信公众号 AppID */
    const APPID = 'wechat_appid';

    /** @var string 微信公众号 AppSecret */
    const SECRET = 'wechat_secret';

    /** @var string 微信服务器验证 Token */
    const TOKEN = 'wechat_token';

    /** @var string 微信消息加解密 AES Key */
    const AES_KEY = 'wechat_aes_key';

    /** @var string 小程序 AppID */
    const MINI_APPID = 'program_appid';

    /** @var string 小程序 AppSecret */
    const MINI_SECRET = 'program_app_secret';

    /** @var string 小程序 Token */
    const MINI_TOKEN = 'program_app_token';

    /** @var string 小程序 AES Key */
    const MINI_AES_KEY = 'program_app_aes_key';
}
