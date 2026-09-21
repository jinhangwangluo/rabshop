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

namespace app\common\model\user;

use app\common\model\Base;

/**
 * 用户平台认证模型
 */
class UserPlatformAuth extends Base
{
    protected $name = 'user_platform_auth';

    protected $autoWriteTimestamp = true;
    protected $createTime = 'bind_time';
    protected $updateTime = 'update_time';

    protected $json = ['extra_data'];
    protected $jsonAssoc = true;

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
}
