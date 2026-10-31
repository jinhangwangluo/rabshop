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

// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用地址
    'app_host'         => env('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 自动多应用模式
    'auto_multi_app'   => false,
    // 默认应用
    'default_app'      => '',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',
    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => ['common'],
    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',
    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => false,
    // appkey
    'key'              => env('app_key', ''),
    'app_key'          => env('app_key', ''),
    'cipher'           => env('app_cipher', 'AES-256-CBC'),
    //静态文件版本
    'static_version'   => 'v1.0.0',
    //token过期时间（秒），与JWT过期时间保持一致：10天
    'token_timeout'    =>   864000,
    //移动端token过期时间（秒），默认15天
    'mobile_token_timeout' => 1296000,
    //移动端旧token刷新宽限期（秒），用于并发请求平滑过渡
    'mobile_token_grace_timeout' => 120,
    //管理端前端路由前缀
    'admin_ui_prefix'     => 'admin',
    // 商户端前端路由前缀
    'merchant_ui_prefix'  => env('app.merchant_ui_prefix', 'merchant'),
    // 商户端前端完整地址，未配置时兼容当前域名下的 /merchant 路径
    'merchant_ui_url'     => env('app.merchant_ui_url', ''),
];
