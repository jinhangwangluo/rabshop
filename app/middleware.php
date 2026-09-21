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

// 全局中间件定义文件
return [
    // Session初始化
    \think\middleware\SessionInit::class,
    // 接口跨域中间件（内部已限制仅处理 api/* 请求）
    \app\middleware\AllowOriginMiddleware::class,
    // 系统安装状态检查中间件
    \app\middleware\CheckInstallMiddleware::class,
];
