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

enum RuleEnum
{
    //管理员退出登录路由地址（api/admin/ 前缀剥离后的规则值，与 route/admin.php 保持同步）
    const ADMIN_LOGOUT_URL = 'login/logout';
    //管理员信息路由地址
    const ADMIN_INFO_URL = 'login/getInfo';
    //配置路由地址
    const CONFIG_SAVE_URL = 'setting/config';
    //配置前端路由前缀
    const CONFIG_VIEW_URL_PREFIX = '/setting/config/form';
}
