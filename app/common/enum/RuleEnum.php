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
    //管理员退出登录路由地址
    const ADMIN_LOGOUT_URL = 'admin/setting/admin/logout';
    //管理员信息路由地址
    const ADMIN_INFO_URL = 'admin/setting/admin/info';
    //配置路由地址
    const CONFIG_SAVE_URL = 'admin/setting/config';
    //配置前端路由前缀
    const CONFIG_VIEW_URL_PREFIX = '/setting/config/form';
}
