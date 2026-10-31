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
 * 直播配置 db_config key 枚举
 * 用于 db_config() 调用时统一管理直播相关配置键名（支持阿里云、七牛云）
 */
enum LiveConfigKeyEnum
{
    // ==================== 通用 ====================

    /** @var string 默认直播云服务商（aliyun / qiniu） */
    const DEFAULT_PROVIDER = 'live_default_provider';

    // ==================== 阿里云直播 ====================

    /** @var string 阿里云直播 AccessKey ID */
    const ALI_ACCESS_KEY = 'live_ali_access_key';

    /** @var string 阿里云直播 AccessKey Secret */
    const ALI_ACCESS_SECRET = 'live_ali_access_secret';

    /** @var string 阿里云直播应用名称（AppName） */
    const ALI_APP_NAME = 'live_ali_app_name';

    /** @var string 阿里云直播推流域名 */
    const ALI_PUSH_DOMAIN = 'live_ali_push_domain';

    /** @var string 阿里云直播拉流域名 */
    const ALI_PULL_DOMAIN = 'live_ali_pull_domain';

    /** @var string 阿里云直播鉴权有效秒数 */
    const ALI_EXPIRE_SECONDS = 'live_ali_expire_seconds';
    
    /** @var string 阿里云直播回调鉴权 Token（32 位，通过 GET 参数传输） */
    const ALI_CALLBACK_TOKEN = 'live_ali_callback_token';
    
    /** @var string 阿里云直播回调地址（只读展示，由系统自动拼接） */
    const ALI_CALLBACK_URL = 'ali_callback_url';

    // ==================== 七牛云直播 ====================

    /** @var string 七牛云直播 AccessKey */
    const QINIU_ACCESS_KEY = 'live_qiniu_access_key';

    /** @var string 七牛云直播 SecretKey */
    const QINIU_SECRET_KEY = 'live_qiniu_secret_key';

    /** @var string 七牛云直播 Hub 名称 */
    const QINIU_HUB = 'live_qiniu_hub';

    /** @var string 七牛云直播推流域名 */
    const QINIU_PUSH_DOMAIN = 'live_qiniu_push_domain';

    /** @var string 七牛云直播拉流域名 */
    const QINIU_PULL_DOMAIN = 'live_qiniu_pull_domain';

    /** @var string 七牛云直播鉴权有效秒数 */
    const QINIU_EXPIRE_SECONDS = 'live_qiniu_expire_seconds';

    /** @var string 七牛云直播播放鉴权（时间戳防盗链）Key */
    const QINIU_PLAY_AUTH_KEY = 'live_qiniu_play_auth_key';

    /** @var string 七牛云直播回调鉴权 Token（32 位，通过 GET 参数传输） */
    const QINIU_CALLBACK_TOKEN = 'live_qiniu_callback_token';

    /** @var string 七牛云直播回调地址（只读展示，由系统自动拼接） */
    const QINIU_CALLBACK_URL = 'qiniu_callback_url';
}
