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

use app\middleware\AllowOriginMiddleware;
use think\facade\Route;

Route::any('api/test', 'test/index')->middleware(AllowOriginMiddleware::class);
Route::any('api/rule', 'test/rule')->middleware(AllowOriginMiddleware::class);

// 系统安装向导（独立页面与公开接口，必须在前端兜底路由之前）
Route::get('install', 'Install/page');
Route::group('api/install', function () {
    Route::get('status', 'Install/status');
    Route::get('check', 'Install/check');
    Route::post('test', 'Install/test');
    Route::post('run', 'Install/run');
    Route::get('progress', 'Install/progress');
})->middleware(AllowOriginMiddleware::class);

// 部署拉取钩子（独立密码鉴权，不走后台鉴权中间件）
Route::post('api/deploy/git_pull', 'deploy/gitPull')->middleware(AllowOriginMiddleware::class);

// 直播回调（云服务商 Webhook，不走后台鉴权中间件）
Route::group('api/live/callback', function () {
    Route::post('qiniu', 'live.LiveCallback/qiniu');
    Route::post('aliyun', 'live.LiveCallback/aliyun');
})->middleware(AllowOriginMiddleware::class);


Route::group('admin', function () {
    Route::miss(function () {
        return view(public_path() . 'admin/index.html');
    });
});

Route::group('merchant', function () {
    Route::miss(function () {
        return view(public_path() . 'merchant/index.html');
    });
});

Route::group('pages', function () {
    Route::miss(function () {
        return view(public_path() . 'index.html');
    });
});

// 全站兜底须在所有分组匹配失败后执行，不能截获 RouteLoaded 阶段加载的插件 API。
Route::miss(function () {
    return view(public_path() . 'index.html');
});
