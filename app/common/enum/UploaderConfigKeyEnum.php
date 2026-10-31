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
 * 文件上传器配置 db_config key 枚举
 * 用于 db_config() 调用时统一管理各云存储上传相关配置键名
 */
enum UploaderConfigKeyEnum
{
    // ==================== 上传器类型 ====================

    /** @var string 当前启用的上传器类型（local / cos / oss / qiniu） */
    const UPLOADER_TYPE = 'uploader_type';

    // ==================== 腾讯云 COS ====================

    /** @var string COS Bucket 名称 */
    const COS_NAME = 'cos_name';

    /** @var string COS 所属地域（如 ap-guangzhou） */
    const COS_REGION = 'cos_region';

    /** @var string COS CDN 加速域名 */
    const COS_CDN = 'cos_cdn';

    /** @var string COS 访问 URL（文件访问根地址） */
    const COS_URL = 'cos_url';

    /** @var string COS AccessKey ID */
    const COS_ACCESS_KEY = 'cos_access_key';

    /** @var string COS AccessKey Secret */
    const COS_SECRET_KEY = 'cos_secret_key';

    /** @var string COS AppID */
    const COS_APPID = 'cos_appid';

    // ==================== 阿里云 OSS ====================

    /** @var string OSS Bucket 名称 */
    const OSS_NAME = 'oss_name';

    /** @var string OSS 所属地域（如 oss-cn-hangzhou） */
    const OSS_REGION = 'oss_region';

    /** @var string OSS 访问 URL（文件访问根地址） */
    const OSS_URL = 'oss_url';

    /** @var string OSS AccessKey ID */
    const OSS_ACCESS_KEY = 'oss_access_key';

    /** @var string OSS AccessKey Secret */
    const OSS_SECRET_KEY = 'oss_secret_key';

    // ==================== 七牛云 Kodo ====================

    /** @var string 七牛云 AccessKey */
    const QINIU_ACCESS_KEY = 'qiniu_access_key';

    /** @var string 七牛云 SecretKey */
    const QINIU_SECRET_KEY = 'qiniu_secret_key';

    /** @var string 七牛云 Bucket 名称 */
    const QINIU_NAME = 'qiniu_name';

    /** @var string 七牛云 Bucket 所属区域（如 z0/z1/z2/na0/as0） */
    const QINIU_REGION = 'qiniu_region';

    /** @var string 七牛云文件访问 URL */
    const QINIU_URL = 'qiniu_url';
}
