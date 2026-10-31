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
use app\middleware\AuthAdminLoginMiddleware;
use app\middleware\AuthAdminLogMiddleware;
use app\middleware\AuthAdminRuleMiddleware;
use app\middleware\PluginEncryptedRequestMiddleware;
use app\middleware\LockRequestMiddleware;
use think\facade\Route;


/**
 * 后台管理路由
 */
Route::group('api/admin', function () {

    // 公开返回非敏感插件启用状态，供用户及角色初始化前判断插件是否可用。
    Route::get('plugins/runtime', 'common.PluginRuntime/read');

    // 登录
    Route::post('login', 'admin.Login/login');
    //验证码
    Route::get('captcha', 'admin.Login/ajcaptcha');
    //验证码验证
    Route::post('captcha/check', 'admin.Login/ajcheck');
    // 主动刷新 token（需携带有效 token，不走登录验证中间件）
    Route::post('auth/refresh_token', 'admin.Login/refreshToken');

    Route::group(function () {
        // 获取当前登录管理员信息
        Route::get('login/getInfo', 'admin.Login/getInfo');
        // 退出登录
        Route::get('login/logout', 'admin.Login/logout');
        //数据统计
        Route::get('statistics', 'admin.Index/statistics');
        //营业额
        Route::get('turnover', 'admin.Index/turnover');
        // 总平台首页统计概览
        Route::get('dashboard/overview', 'admin.Dashboard/overview');
        // 总平台首页实时预警摘要
        Route::get('dashboard/pending', 'admin.Dashboard/pending');

        Route::group('plugins', function () {
            Route::get('security/public-key', 'admin.plugin.Plugin/publicKey');
            // 开发源码走本地鉴权接口，不依赖市场和加密网关。
            Route::get('developer', 'admin.plugin.Plugin/developerHttpList');
            Route::post('developer/:code/install', 'admin.plugin.Plugin/developerHttpInstall')
                ->pattern(['code' => '[a-z][a-z0-9_]{1,63}']);
            Route::post('developer/:code/uninstall', 'admin.plugin.Plugin/developerHttpUninstall')
                ->pattern(['code' => '[a-z][a-z0-9_]{1,63}']);
            // 重新安装用当前源码替换已安装源码；下载导出整个插件源码包。
            Route::post('developer/:code/reinstall', 'admin.plugin.Plugin/developerHttpReinstall')
                ->pattern(['code' => '[a-z][a-z0-9_]{1,63}']);
            Route::get('developer/:code/download', 'admin.plugin.Plugin/developerHttpDownload')
                ->pattern(['code' => '[a-z][a-z0-9_]{1,63}']);
            Route::post('gateway', 'admin.plugin.Plugin/handle')
                ->middleware(PluginEncryptedRequestMiddleware::class);

            // 打包服务代理；静态路径必须写在 builds/:id 之前，任务号是 b+时间戳+随机串，不是纯数字。
            // 动态段只约束字符集，任务号的具体格式与长度由 Service 统一校验并返回 JSON 错误。
            Route::group('build-service', function () {
                Route::get('targets', 'admin.plugin.PluginBuild/targets');
                Route::post('uploads', 'admin.plugin.PluginBuild/upload');
                Route::post('mp-credentials', 'admin.plugin.PluginBuild/mpCredential');
                Route::get('builds', 'admin.plugin.PluginBuild/index');
                Route::post('builds', 'admin.plugin.PluginBuild/save');
                Route::get('builds/:id/log', 'admin.plugin.PluginBuild/log')->pattern(['id' => '[A-Za-z0-9]{1,64}']);
                Route::get('builds/:id/artifact', 'admin.plugin.PluginBuild/artifact')->pattern(['id' => '[A-Za-z0-9]{1,64}']);
                Route::post('builds/:id/cancel', 'admin.plugin.PluginBuild/cancel')->pattern(['id' => '[A-Za-z0-9]{1,64}']);
                Route::get('builds/:id', 'admin.plugin.PluginBuild/read')->pattern(['id' => '[A-Za-z0-9]{1,64}']);
            });
        });

        // 后台消息中心（静态路由优先）
        Route::group('backend_message', function () {
            Route::get('types', 'admin.BackendMessage/types');
            Route::get('list', 'admin.BackendMessage/list');
            Route::get('unread_count', 'admin.BackendMessage/unreadCount');
            Route::put('read', 'admin.BackendMessage/read');
            Route::put('read_all', 'admin.BackendMessage/readAll');
            Route::delete('clear', 'admin.BackendMessage/clear');
            Route::get('ws_token', 'admin.BackendMessage/wsToken');
        });

        Route::group('wechat', function () {
            // 关键字回复列表（分页）
            Route::get('wechat_reply/index', 'admin.wechat.Reply/index');
            // 按key获取回复详情（subscribe/default）
            Route::get('wechat_reply/keyword', 'admin.wechat.Reply/keyword');
            // 按ID获取回复详情
            Route::get('wechat_reply/info_keyword', 'admin.wechat.Reply/info_keyword');
            // 保存subscribe/default回复
            Route::post('wechat_reply/save', 'admin.wechat.Reply/save');
            // 保存关键字回复
            Route::post('wechat_reply/save_keyword', 'admin.wechat.Reply/saveKeyword');
            // 删除回复
            Route::get('wechat_reply/delete', 'admin.wechat.Reply/delete');
            // 上传图片
            Route::post('wechat_reply/upload_img', 'admin.wechat.Reply/upload_img');
            // 修改回复状态
            Route::put('wechat_reply/status/:id', 'admin.wechat.Reply/status');
            // 上传语音文件
            Route::post('wechat_reply/upload_file', 'admin.wechat.Reply/upload_file');
            // 获取回复类型选项
            Route::get('wechat_reply/get_reply_types', 'admin.wechat.Reply/get_reply_types');
            // 获取匹配类型选项
            Route::get('wechat_reply/get_match_types', 'admin.wechat.Reply/get_match_types');

            // 微信菜单管理路由
            Route::get('menus/index', 'admin.wechat.Menus/index');
            Route::post('menus/save', 'admin.wechat.Menus/save');
        });


        Route::group('user', function () {
        
            // ===== 用户资产管理路由 =====（静态前缀路由须在动态路由 :id 之前）
            // 通过用户 ID 获取资产
            Route::get('user_asset/by_user/:userId', 'admin.user.UserAsset/getByUser');
            // 变更用户资产
            Route::post('user_asset/change', 'admin.user.UserAsset/changeAsset');
            // 获取用户资产列表
            Route::get('user_asset', 'admin.user.UserAsset/index');
            // 获取用户资产详情
            Route::get('user_asset/:id', 'admin.user.UserAsset/read');
            // 删除用户资产
            Route::delete('user_asset/:id', 'admin.user.UserAsset/delete');
        
        
            // ===== 用户资产记录路由 =====（静态前缀路由须在动态路由 :id 之前）
            // 获取指定用户的资产记录
            Route::get('user_asset_log/user/:userId', 'admin.user.UserAssetLog/userLogs');
            // 获取指定用户的佣金记录
            Route::get('user_asset_log/commission/:userId', 'admin.user.UserAssetLog/commissionLogs');
            // 获取资产记录列表
            Route::get('user_asset_log', 'admin.user.UserAssetLog/index');
            // 获取资产记录详情
            Route::get('user_asset_log/:id', 'admin.user.UserAssetLog/read');
            // 删除资产记录
            Route::delete('user_asset_log/:id', 'admin.user.UserAssetLog/delete');


            // ===== 用户管理路由 =====（静态路由须在动态路由 :id 之前）
            // 获取用户列表
            Route::get('', 'admin.user.User/index');
            // 添加用户
            Route::post('', 'admin.user.User/save');
            // 调整用户余额
            Route::post('adjust_balance', 'admin.user.User/adjustBalance');
            // 调整用户积分
            Route::post('adjust_points', 'admin.user.User/adjustPoints');
            // 设置用户分销权限
            Route::post('set_distribution', 'admin.user.User/setDistribution');
            // 绑定上级分销人
            Route::post('bind_spread_user', 'admin.user.User/bindSpreadUser');
            // 设置用户禁评/允评
            Route::post('set_comment_ban', 'admin.user.User/setCommentBan');
            // 获取用户下级推广人列表
            Route::get(':userId/subordinates', 'admin.user.User/subordinates');
            // 更新用户基本信息（昵称、性别、生日等）
            Route::put(':id/basic', 'admin.user.User/updateBasic');
            // 修改用户手机号
            Route::put(':id/phone', 'admin.user.User/updatePhone');
            // 修改用户密码
            Route::put(':id/password', 'admin.user.User/updatePassword');
            // 启用/禁用用户
            Route::put(':id/status', 'admin.user.User/show');
            // 获取用户详情
            Route::get(':id', 'admin.user.User/read');
            // 修改用户
            Route::put(':id', 'admin.user.User/update');
            // 删除用户
            Route::delete(':id', 'admin.user.User/delete');
        });


        Route::group('distribution', function () {
            // 分销员管理（静态路由优先）
            Route::get('user/orders', 'admin.distribution.User/orders');
            Route::post('user/set_permission', 'admin.distribution.User/setPermission');
            Route::get('user', 'admin.distribution.User/index');
        });


        // 菜单管理路由
        Route::group('setting', function () {
            // 启用/禁用敏感词
            Route::get('sensitive_words/status/:id', 'admin.setting.SensitiveWords/show');
            // 批量删除敏感词
            Route::post('sensitive_words/batch_delete', 'admin.setting.SensitiveWords/batchDelete');
            // 批量添加敏感词
            Route::post('sensitive_words/batch_save', 'admin.setting.SensitiveWords/batchSave');
            // 导出敏感词
            Route::get('sensitive_words/export', 'admin.setting.SensitiveWords/export');
            // 导入敏感词
            Route::post('sensitive_words/import', 'admin.setting.SensitiveWords/import');
            // 检测文本敏感词
            Route::post('sensitive_words/check', 'admin.setting.SensitiveWords/check');
            // 敏感词
            Route::resource('sensitive_words', 'admin.setting.SensitiveWords')->except(['create', 'edit']);

            // 配置管理路由
            // 生成配置Token
            Route::get('config/generate_token', 'admin.setting.Config/generateToken');
            // 获取配置
            Route::get('config', 'admin.setting.Config/index');
            // 获取分类
            Route::get('config/cate', 'admin.setting.Config/cate');
            // 保存配置
            Route::post('config', 'admin.setting.Config/save');
            // 上传水印配置
            Route::get('uploader_watermark', 'admin.setting.UploaderWatermark/index');
            Route::post('uploader_watermark', 'admin.setting.UploaderWatermark/save');
            // 保存配置模板
            Route::post('config_template/save_template', 'admin.setting.ConfigTemplate/saveTemplate');
            // AI 流式生成配置模板设计草稿
            Route::post('config_template/ai_generate', 'admin.setting.ConfigTemplate/aiGenerate');
            // 获取状态开启的配置模板下拉选项
            Route::get('config_template/options', 'admin.setting.ConfigTemplate/options');
            // 配置模板
            Route::resource('config_template', 'admin.setting.ConfigTemplate')->except(['create', 'edit']);

            // 快递公司
            Route::get('express_company/options', 'admin.setting.ExpressCompany/options');
            Route::get('express_company/query_config', 'admin.setting.ExpressCompany/queryConfig');
            Route::post('express_company/query_config', 'admin.setting.ExpressCompany/saveQueryConfig');
            Route::get('express_company/:id/show', 'admin.setting.ExpressCompany/show')->pattern(['id' => '\d+']);
            Route::resource('express_company', 'admin.setting.ExpressCompany')->except(['create', 'edit']);

            // 协议管理
            Route::get('agreement/schema', 'admin.setting.Agreement/schema');
            Route::post('agreement/save_content', 'admin.setting.Agreement/saveContent');
            Route::resource('agreement', 'admin.setting.Agreement')->except(['create', 'edit']);

            // 关于我们
            Route::get('about/schema', 'admin.setting.About/schema');
            Route::post('about/save_content', 'admin.setting.About/saveContent');

            // 组合数据配置（静态路由须在动态资源路由之前）
            Route::get('combination_data_config/options', 'admin.combination.CombinationDataConfig/options');
            Route::get('combination_data_config/key/:configKey', 'admin.combination.CombinationDataConfig/readByKey')
                ->pattern(['configKey' => '[A-Za-z][A-Za-z0-9_]*']);
            Route::get('combination_data_config/:id/status/:status', 'admin.combination.CombinationDataConfig/show')->pattern(['id' => '\d+', 'status' => '\d+']);
            Route::get('combination_data/schema/:configId', 'admin.combination.CombinationData/schema')->pattern(['configId' => '\d+']);
            Route::resource('combination_data_config', 'admin.combination.CombinationDataConfig')->except(['create', 'edit']);
            Route::resource('combination_data', 'admin.combination.CombinationData')->except(['create', 'edit', 'show']);

            // 支付配置
            Route::get('payment_config/options', 'admin.setting.PaymentConfig/options');
            Route::get('payment_config', 'admin.setting.PaymentConfig/index');
            Route::post('payment_config', 'admin.setting.PaymentConfig/save');

            // 消息推送配置（静态路由须在动态参数路由之前）
            Route::get('message_push/events', 'admin.setting.MessagePush/events');
            Route::post('message_push/events', 'admin.setting.MessagePush/saveEvent');
            Route::get('message_push/list', 'admin.setting.MessagePush/index');
            Route::get('message_push/logs', 'admin.setting.MessagePush/logs');
            Route::post('message_push/save', 'admin.setting.MessagePush/save');
            Route::post('message_push/sync/:channel', 'admin.setting.MessagePush/syncChannel')
                ->pattern(['channel' => 'official_account|miniapp']);
            Route::put('message_push/channel/:id/status/:status', 'admin.setting.MessagePush/status')
                ->pattern(['id' => '\d+', 'status' => '[01]']);
            Route::post('message_push/channel/:id/test', 'admin.setting.MessagePush/test')->pattern(['id' => '\d+']);
            Route::get('message_push/detail/:eventKey', 'admin.setting.MessagePush/detail')
                ->pattern(['eventKey' => '[a-z0-9_]+']);

            // 获取路由地址
            Route::get('menu/get_rule', 'admin.setting.Menu/getRule');
            // 获取菜单详情
            Route::get('menu/:id', 'admin.setting.Menu/find');
            // 保存、修改菜单
            Route::post('menu/:id', 'admin.setting.Menu/save');
            // 修改菜单状态
            Route::put('menu/:id/status/:status', 'admin.setting.Menu/status');
            // 删除菜单
            Route::delete('menu/:id', 'admin.setting.Menu/delete');
            // 获取菜单列表
            Route::get('menu', 'admin.setting.Menu/index');

            // 角色管理路由
            // 获取角色列表
            Route::get('role', 'admin.setting.Role/index');
            // 获取角色详情
            Route::get('role/:id', 'admin.setting.Role/find');
            // 保存角色/修改角色
            Route::post('role/:id', 'admin.setting.Role/save');
            // 修改角色状态
            Route::put('role/:id/status/:status', 'admin.setting.Role/status');
            // 删除角色
            Route::delete('role/:id', 'admin.setting.Role/delete');

            // 管理员管理路由
            // 管理员绑定用户选择列表
            Route::get('admin/user_select', 'admin.setting.Admin/userSelect');
            // 获取管理员信息
            Route::get('admin/admin_info', 'admin.setting.Admin/adminInfo');
            // 修改管理员信息
            Route::put('admin/admin_info', 'admin.setting.Admin/putAdminInfo');
            // 获取管理员列表
            Route::get('admin', 'admin.setting.Admin/index');
            // 保存管理员
            Route::post('admin', 'admin.setting.Admin/save'); // 保存管理员
            // 修改管理员状态
            Route::put('admin/:id/status/:status', 'admin.setting.Admin/status');
            // 删除管理员
            Route::delete('admin/:id', 'admin.setting.Admin/delete');
            // 修改管理员
            Route::put('admin/:id', 'admin.setting.Admin/save');

            // 地区管理路由
            // 获取地区列表
            Route::get('city_area/list', 'admin.setting.CityArea/list');
            // 获取地区树
            Route::get('city_area/tree', 'admin.setting.CityArea/tree');
            // 获取下拉选项
            Route::get('city_area/options', 'admin.setting.CityArea/options');
            // 保存地区
            Route::post('city_area/', 'admin.setting.CityArea/save');
            // 修改地区
            Route::put('city_area/:id', 'admin.setting.CityArea/edit');
            // 修改地区状态
            Route::put('city_area/:id/status/:status', 'admin.setting.CityArea/status');
            // 删除地区
            Route::delete('city_area/:id', 'admin.setting.CityArea/delete');

            // 配置分类路由
            // 获取配置分类列表
            Route::get('config_category', 'admin.setting.ConfigCategory/index');
            // 创建配置分类
            Route::get('config_category/create', 'admin.setting.ConfigCategory/create');
            // 保存配置分类
            Route::post('config_category', 'admin.setting.ConfigCategory/save');
            // 获取配置分类详情
            Route::get('config_category/:id', 'admin.setting.ConfigCategory/read');
            // 修改配置分类
            Route::put('config_category/:id', 'admin.setting.ConfigCategory/update');
            // 删除配置分类
            Route::delete('config_category/:id', 'admin.setting.ConfigCategory/delete');
            // 修改配置分类状态
            Route::get('config_category/:id/show', 'admin.setting.ConfigCategory/show');

            // 组合数据分类路由
            Route::get('combination_data_category', 'admin.combination.CombinationDataCategory/index');
            Route::post('combination_data_category', 'admin.combination.CombinationDataCategory/save');
            Route::get('combination_data_category/:id', 'admin.combination.CombinationDataCategory/read')->pattern(['id' => '\d+']);
            Route::put('combination_data_category/:id', 'admin.combination.CombinationDataCategory/update')->pattern(['id' => '\d+']);
            Route::delete('combination_data_category/:id', 'admin.combination.CombinationDataCategory/delete')->pattern(['id' => '\d+']);
            Route::get('combination_data_category/:id/show', 'admin.combination.CombinationDataCategory/show')->pattern(['id' => '\d+']);

            // 文件分类路由
            // 移动文件
            Route::post('file/category/move', 'admin.setting.FileCategory/move');
            //保存
            Route::post('file/category/:id', 'admin.setting.FileCategory/save');
            // 获取文件分类列表
            Route::resource('file/category', 'admin.setting.FileCategory')->except(['edit','save']);
            // 获取文件列表
            Route::get('file/list', 'admin.setting.File/list');
            // 上传文件
            Route::post('file/upload', 'admin.setting.File/upload');
            // 获取上传配置（上传类型及云存储签名）
            Route::get('file/upload_config', 'admin.setting.File/getUploadConfig');
            // 本地分片上传（断点续传）
            Route::post('file/chunk_upload', 'admin.setting.File/chunkUpload');
            // 云存储直传后登记附件
            Route::post('file/record', 'admin.setting.File/record');
            // 文件库 AI 生图任务（静态路由需在 file/:id 前注册）
            Route::get('file/ai-generate/tasks', 'admin.setting.FileAiGenerate/list');
            Route::post('file/ai-generate/tasks', 'admin.setting.FileAiGenerate/save');
            Route::get('file/ai-generate/tasks/:id', 'admin.setting.FileAiGenerate/read')->pattern(['id' => '\d+']);
            Route::post('file/ai-generate/tasks/:id/cancel', 'admin.setting.FileAiGenerate/cancel')->pattern(['id' => '\d+']);
            Route::post('file/ai-generate/tasks/:id/retry', 'admin.setting.FileAiGenerate/retry')->pattern(['id' => '\d+']);
            // 删除文件
            Route::delete('file/delete', 'admin.setting.File/delete');
            // 修改文件名
            Route::put('file/:id', 'admin.setting.File/update')->pattern(['id' => '\d+']);

            // 日志管理路由
            // 获取日志列表
            Route::get('log', 'admin.setting.Log/index');
            // 获取管理员列表
            Route::get('log/admin', 'admin.setting.Log/adminList');
        });


        // 订单管理路由

        // 获取订单统计徽章数据
        Route::get('order/getBadge', 'admin.order.Order/getBadge');
        // 评价管理（静态路由必须在 order/:id 之前）
        Route::get('order/review', 'admin.order.OrderReview/index');
        Route::post('order/review/:id/reply', 'admin.order.OrderReview/reply');
        Route::delete('order/review/:id', 'admin.order.OrderReview/delete');
        // 退款记录列表（静态路由必须在 order/:id 之前）
        Route::get('order/refund', 'admin.order.OrderRefund/index');
        // 获取订单列表
        Route::get('order', 'admin.order.Order/index');
        // 查看订单物流
        Route::get('order/:id/express', 'admin.order.Order/express')->pattern(['id' => '\d+']);
        // 获取订单详情
        Route::get('order/:id', 'admin.order.Order/read');
        // 修改订单
        Route::put('order/:id', 'admin.order.Order/update');
        // 删除订单
        Route::delete('order/:id', 'admin.order.Order/delete');
        // 启用/禁用订单
        Route::get('order/:id/show', 'admin.order.Order/show');
        // 订单发货
        Route::post('order/:id/delivery', 'admin.order.Order/delivery')->pattern(['id' => '\d+']);
        // 发起退款
        Route::post('order/:id/apply_refund', 'admin.order.Order/applyRefund');
        // 退款审核通过
        Route::post('order/:id/approve_refund', 'admin.order.Order/approveRefund');
        Route::post('order/:id/receive_return', 'admin.order.Order/receiveReturn')->pattern(['id' => '\d+']);
        // 拒绝退款
        Route::post('order/:id/reject_refund', 'admin.order.Order/rejectRefund');
        // 更新订单备注
        Route::post('order/:id/remark', 'admin.order.Order/updateRemark');

        // 商户管理路由
        // 启用/禁用商户
        Route::get('merchant/:id/status/:status', 'admin.merchant.Merchant/show');
        // 审核商户
        Route::post('merchant/:id/audit', 'admin.merchant.Merchant/audit');
        // 修改营业状态
        Route::post('merchant/:id/business_status', 'admin.merchant.Merchant/businessStatus');
        // 设置推荐状态
        Route::post('merchant/:id/recommend', 'admin.merchant.Merchant/recommend');
        // 设置排序
        Route::post('merchant/:id/sort', 'admin.merchant.Merchant/sort');
        // 获取商户统计数据
        Route::get('merchant/:id/statistics', 'admin.merchant.Merchant/statistics');
        // 修改商户密码
        Route::post('merchant/:id/change_password', 'admin.merchant.Merchant/changePassword');
        // 一键登录商户后台
        Route::post('merchant/:id/login_as', 'admin.merchant.Merchant/loginAs');
        // 修改商户平台抽佣比例
        Route::put('merchant/:id/commission_rate', 'admin.merchant.Merchant/commissionRate')->pattern(['id' => '\d+']);
        // 获取商户列表
        Route::resource('merchant', 'admin.merchant.Merchant');

        // 商户菜单管理路由
        // 获取路由地址
        Route::get('merchant_menu/get_rule', 'admin.merchant.MerchantMenu/getRule');
        // 获取菜单树（用于权限分配）
        Route::get('merchant_menu/tree', 'admin.merchant.MerchantMenu/tree');
        // 修改菜单状态
        Route::put('merchant_menu/:id/status/:status', 'admin.merchant.MerchantMenu/status');
        // 修改菜单
        Route::put('merchant_menu/:id', 'admin.merchant.MerchantMenu/save');
        // 获取商户菜单
        Route::resource('merchant_menu', 'admin.merchant.MerchantMenu')->except(['create', 'edit', 'update']);

        // 商户申请管理路由
        Route::resource('merchant_apply', 'admin.merchant.MerchantApply');
        // 启用/禁用商户申请
        Route::get('merchant_apply/:id/status/:status', 'admin.merchant.MerchantApply/show');

        // 商户提现管理路由
        // 获取商户提现列表
        Route::get('merchant_withdraw', 'admin.merchant.MerchantWithdraw/index');
        // 添加商户提现
        Route::post('merchant_withdraw', 'admin.merchant.MerchantWithdraw/save');
        // 审核商户提现
        Route::put('merchant_withdraw/:id/audit', 'admin.merchant.MerchantWithdraw/handle')->pattern(['id' => '\d+']);
        // 获取商户提现详情
        Route::get('merchant_withdraw/:id', 'admin.merchant.MerchantWithdraw/read');
        // 修改商户提现
        Route::put('merchant_withdraw/:id', 'admin.merchant.MerchantWithdraw/update');
        // 删除商户提现
        Route::delete('merchant_withdraw/:id', 'admin.merchant.MerchantWithdraw/delete');
        // 启用/禁用商户提现
        Route::get('merchant_withdraw/:id/show', 'admin.merchant.MerchantWithdraw/show');


        // 财务记录管理路由

        // 用户提现申请
        Route::get('user_withdraw', 'admin.finance.UserWithdraw/index');
        Route::put('user_withdraw/:id/audit', 'admin.finance.UserWithdraw/audit')->pattern(['id' => '\d+']);

        // 获取用户充值订单详情
        Route::get('user_recharge_order/:id', 'admin.finance.UserRechargeOrder/read')->pattern(['id' => '\d+']);

        // 获取财务记录列表
        Route::get('finance_record', 'admin.finance.FinanceRecord/index');
        // 添加财务记录
        Route::post('finance_record', 'admin.finance.FinanceRecord/save');
        // 获取财务记录详情
        Route::get('finance_record/:id', 'admin.finance.FinanceRecord/read');
        // 修改财务记录
        Route::put('finance_record/:id', 'admin.finance.FinanceRecord/update');
        // 删除财务记录
        Route::delete('finance_record/:id', 'admin.finance.FinanceRecord/delete');
        // 启用/禁用财务记录
        Route::get('finance_record/:id/show', 'admin.finance.FinanceRecord/show');


        // 文章管理路由
        // 获取内容类型列表
        Route::get('article/content_types', 'admin.content.Article/contentTypes');
        // 搜索内容列表（专题选择内容时使用）
        Route::get('article/search_list', 'admin.content.Article/searchList');
        // URL采集内容（静态路由须在资源路由之前）
        Route::post('article/collect', 'admin.content.Article/collect');
        // AI润色写作（静态路由须在资源路由之前）
        Route::post('article/ai_generate', 'admin.content.Article/aiGenerate');
        // AI续写正文/试读（静态路由须在资源路由之前）
        Route::post('article/ai_continue', 'admin.content.Article/aiContinue');
        // 快捷编辑内容字段（静态路由须在资源路由之前）
        Route::put('article/:id/quick_update', 'admin.content.Article/quickUpdate')->pattern(['id' => '\d+']);
        // 获取内容列表
        Route::resource('article', 'admin.content.Article')->except(['create']);
        // 启用/禁用内容
        Route::get('article/status/:id', 'admin.content.Article/show');

        // 内容评论管理路由（静态路由须在动态参数路由之前）
        // 获取评论列表
        Route::get('content_comment', 'admin.content.ContentComment/index');
        // 删除评论
        Route::delete('content_comment/:id', 'admin.content.ContentComment/delete');

        // 标签分组管理路由（静态路由须在动态参数路由之前）
        Route::get('tag_category_tree', 'admin.content.TagCategory/tree');
        Route::post('tag_category/quick_add', 'admin.content.TagCategory/quickAdd');
        Route::get('tag_category', 'admin.content.TagCategory/index');
        Route::post('tag_category', 'admin.content.TagCategory/save');
        Route::get('tag_category/:id/show', 'admin.content.TagCategory/show')->pattern(['id' => '\\d+']);
        Route::get('tag_category/:id', 'admin.content.TagCategory/read')->pattern(['id' => '\\d+']);
        Route::put('tag_category/:id', 'admin.content.TagCategory/update')->pattern(['id' => '\\d+']);
        Route::delete('tag_category/:id', 'admin.content.TagCategory/delete')->pattern(['id' => '\\d+']);

        // 标签管理路由（静态路由须在动态参数路由之前）
        Route::get('tag/options', 'admin.content.Tag/options');
        Route::post('tag/quick_add', 'admin.content.Tag/quickAdd');
        Route::post('tag/ai_import', 'admin.content.Tag/aiImport');
        Route::get('tag/relation', 'admin.content.Tag/relation');
        Route::put('tag/relation', 'admin.content.Tag/saveRelation');
        Route::get('tag', 'admin.content.Tag/index');
        Route::post('tag', 'admin.content.Tag/save');
        Route::get('tag/:id/show', 'admin.content.Tag/show')->pattern(['id' => '\\d+']);
        Route::get('tag/:id', 'admin.content.Tag/read')->pattern(['id' => '\\d+']);
        Route::put('tag/:id', 'admin.content.Tag/update')->pattern(['id' => '\\d+']);
        Route::delete('tag/:id', 'admin.content.Tag/delete')->pattern(['id' => '\\d+']);

        // 文章分类管理路由
        // 获取文章分类列表
        Route::get('article_category', 'admin.content.ArticleCategory/index');
        // 添加文章分类
        Route::post('article_category', 'admin.content.ArticleCategory/save');
        // 获取文章分类详情
        Route::get('article_category/:id', 'admin.content.ArticleCategory/read');
        // 修改文章分类
        Route::put('article_category/:id', 'admin.content.ArticleCategory/update');
        // 删除文章分类
        Route::delete('article_category/:id', 'admin.content.ArticleCategory/delete');
        // 启用/禁用文章分类
        Route::get('article_category/:id/show', 'admin.content.ArticleCategory/show');
        // 获取分类二级树（级联选择器使用）
        Route::get('article_category_tree', 'admin.content.ArticleCategory/tree');
        // 快捷添加分类
        Route::post('article_category/quick_add', 'admin.content.ArticleCategory/quickAdd');

        // 专题管理路由
        // 搜索内容列表（专题添加内容选择器）
        Route::get('special/search_content', 'admin.content.Special/searchContent');
        // 专题审核（通过/驳回）
        Route::post('special/:id/audit', 'admin.content.Special/audit')->pattern(['id' => '\\d+']);
        // 获取专题列表
        Route::resource('special', 'admin.content.Special')->except(['create', 'edit']);
        // 启用/禁用专题
        Route::get('special/:id/show', 'admin.content.Special/show');
        // 获取专题内容列表（携带内容详情）
        Route::get('special/:id/content_list', 'admin.content.Special/contentList');

        // 专题分类管理路由
        Route::resource('special_category', 'admin.content.SpecialCategory');
        Route::get('special_category_tree', 'admin.content.SpecialCategory/tree');
        Route::post('special_category/quick_add', 'admin.content.SpecialCategory/quickAdd');


        // 内容审核模块路由
        Route::group('audit', function () {
            // 待审核数量统计
            Route::get('pending_count', 'admin.content.ContentAudit/pendingCount');
            // 图文内容审核（通过/驳回/下架）
            Route::post('article/:id', 'admin.content.ContentAudit/auditArticle');
            // 批量审核图文内容
            Route::post('article/batch', 'admin.content.ContentAudit/batchAuditArticle');
        });

        // 直播模块路由
        Route::group('live', function () {
            // 主播管理（需在 resource 之前注册，避免被 anchor/:id 解析）
            Route::get('anchor/tab_stats', 'admin.live.LiveAnchor/tabStats');
            Route::resource('anchor', 'admin.live.LiveAnchor')->except(['create']);
            Route::get('anchor/status/:id', 'admin.live.LiveAnchor/show');
            Route::post('anchor/:id/audit', 'admin.live.LiveAnchor/audit');
            Route::post('anchor/:id/toggle_ban', 'admin.live.LiveAnchor/toggleBan');
            Route::post('anchor/:id/add_violation', 'admin.live.LiveAnchor/addViolation');
            Route::get('anchor_options', 'admin.live.LiveAnchor/options');

            // 直播分类管理
            Route::get('category/options', 'admin.live.LiveCategory/options');
            Route::get('category/status/:id', 'admin.live.LiveCategory/show');
            Route::resource('category', 'admin.live.LiveCategory')->except(['create']);

            // 直播间管理（静态路由需在 resource 之前注册，避免被 room/:id 抢匹配）
            Route::get('room/tab_stats', 'admin.live.LiveRoom/tabStats');
            Route::post('room/:id/audit', 'admin.live.LiveRoom/audit');
            Route::get('room/status/:id', 'admin.live.LiveRoom/show');
            Route::post('room/:id/switch_status', 'admin.live.LiveRoom/switchStatus');
            Route::post('room/:id/toggle_hot', 'admin.live.LiveRoom/toggleHot');
            Route::get('room/:id/detail', 'admin.live.LiveRoom/detail');
            Route::get('room/:id/ws_token', 'admin.live.LiveRoom/wsToken');
            // 直播间统计概览
            Route::get('room/:id/stats', 'admin.live.LiveRoom/stats');
            // 直播间用户管理（HTTP 方式）
            Route::get('room/:id/users', 'admin.live.LiveRoom/users');
            Route::post('room/:roomId/user/:userId/mute', 'admin.live.LiveRoom/muteUser');
            Route::post('room/:roomId/user/:userId/kick', 'admin.live.LiveRoom/kickUser');
            // 直播间评论管理
            Route::get('room/:id/comments', 'admin.live.LiveRoom/comments');
            Route::post('room/:id/comment/send', 'admin.live.LiveRoom/sendComment');
            Route::delete('room/:roomId/comment/:commentId', 'admin.live.LiveRoom/deleteComment');
            // 直播间推拉流管理
            Route::post('room/:id/create_stream', 'admin.live.LiveStreamConfig/createStream');
            Route::post('room/:id/close_stream', 'admin.live.LiveStreamConfig/closeStream');
            Route::get('room/:id/stream_info', 'admin.live.LiveStreamConfig/streamInfo');

            // 直播回放归档管理
            Route::get('room/:roomId/records', 'admin.live.LiveRoomRecord/records');
            Route::delete('room/:roomId/record/:recordId', 'admin.live.LiveRoomRecord/deleteRecord');
            Route::post('room/:roomId/record/:recordId/playback', 'admin.live.LiveRoomRecord/setPlayback');
            Route::resource('room', 'admin.live.LiveRoom')->except(['create']);
            // 礼物管理
            Route::resource('gift', 'admin.live.LiveGift')->except(['create']);
            Route::get('gift/status/:id', 'admin.live.LiveGift/show');
            Route::get('gift/available', 'admin.live.LiveGift/available');

            // 直播间等级配置管理
            Route::get('level_config/options', 'admin.live.LiveRoomLevelConfig/options');
            Route::get('level_config/status/:id', 'admin.live.LiveRoomLevelConfig/show');
            Route::resource('level_config', 'admin.live.LiveRoomLevelConfig')->except(['create']);

            // 用户直播间等级管理
            Route::get('room/:id/rank', 'admin.live.LiveRoomUserLevel/getRoomRank');
            Route::get('room/:roomId/user_level/:userId', 'admin.live.LiveRoomUserLevel/getUserLevel');

            // 直播带货商品管理
            Route::get('goods/status/:id', 'admin.live.LiveGoods/show');
            Route::post('goods/explaining/:id', 'admin.live.LiveGoods/explaining');
            Route::post('goods/batch_sort', 'admin.live.LiveGoods/batchSort');
            Route::get('goods/special_options', 'admin.live.LiveGoods/specialOptions');
            Route::resource('goods', 'admin.live.LiveGoods');
            // 打赏记录
            Route::get('reward', 'admin.live.LiveReward/index');
        });

        // ===== 优惠券管理路由 =====
        Route::group('coupon', function () {
            // 优惠券管理（枚举/状态接口必须在 resource 之前）
            Route::get('scope_types', 'admin.store.StoreCoupon/getScopeTypes');
            Route::get('expire_types', 'admin.store.StoreCoupon/getExpireTypes');
            Route::get('distribute_types', 'admin.store.StoreCoupon/getDistributeTypes');
            Route::get('relation_types', 'admin.store.StoreCoupon/getRelationTypes');
            Route::get('signin_reward_options', 'admin.store.StoreCoupon/signinRewardOptions');
            Route::get('status/:id', 'admin.store.StoreCoupon/show');

            // 发放记录管理（子组必须在主 resource 之前，避免 read 路由误匹配）
            Route::group('issue', function () {
                Route::get('issue_types', 'admin.store.StoreCouponIssue/getIssueTypes');
                Route::get('status_list', 'admin.store.StoreCouponIssue/getStatusList');
                Route::get('statistics/:id', 'admin.store.StoreCouponIssue/statistics');
                Route::get('status/:id', 'admin.store.StoreCouponIssue/show');
                Route::resource('', 'admin.store.StoreCouponIssue')->only(['index', 'read']);
            });

            // 用户优惠券管理（子组必须在主 resource 之前，避免 read 路由误匹配）
            Route::group('user', function () {
                Route::get('status_list', 'admin.store.StoreCouponUser/getStatusList');
                Route::get('receive_types', 'admin.store.StoreCouponUser/getReceiveTypes');
                Route::get('status/:id', 'admin.store.StoreCouponUser/show');
                Route::resource('', 'admin.store.StoreCouponUser')->only(['index', 'read']);
            });

            Route::resource('', 'admin.store.StoreCoupon')->except(['create']);
        });

        // ===== 商品管理路由（商品库/店铺商品/规格/品牌/服务/分组/属性/运费模板）=====
        Route::group('product', function () {
            // 商品库管理（状态/选项接口必须在 resource 之前）
            Route::get('library/status/:id', 'admin.store.ProductLibrary/show')->pattern(['id' => '\\d+']);
            Route::post('library/share/:id', 'admin.store.ProductLibrary/share')->pattern(['id' => '\\d+']);
            Route::get('library/options', 'admin.store.ProductLibrary/options');
            Route::resource('library', 'admin.store.ProductLibrary')->except(['create']);

            // 店铺商品管理（状态/组合/选项/SKU 子资源接口必须在 resource 之前）
            Route::get('status/:id', 'admin.store.StoreProduct/show')->pattern(['id' => '\\d+']);
            Route::post('collect', 'admin.store.StoreProduct/collect');
            Route::post('assemble', 'admin.store.StoreProduct/assemble');
            Route::get('options', 'admin.store.StoreProduct/options');
            // 采集开关（上架页独立读取，不经通用配置接口，避免为此开放配置读取权限）
            Route::get('collector_config', 'admin.store.StoreProduct/collectorConfig');
            // 批量采集（静态组必须写在 :id 动态路由之前）
            Route::group('batch_collect', function () {
                Route::get('template', 'admin.store.ProductCollect/template');
                Route::post('import', 'admin.store.ProductCollect/import');
                Route::get('tasks', 'admin.store.ProductCollect/tasks');
                Route::post('publish', 'admin.store.ProductCollect/publish');
                Route::post('complete', 'admin.store.ProductCollect/complete');
                Route::post('retry_failed', 'admin.store.ProductCollect/retryFailed');
                Route::post('tasks/delete', 'admin.store.ProductCollect/deleteBatch');
                Route::post('tasks/:id/retry', 'admin.store.ProductCollect/retry')->pattern(['id' => '\\d+']);
                Route::delete('tasks/:id', 'admin.store.ProductCollect/delete')->pattern(['id' => '\\d+']);
            });
            Route::get(':id/sku_list', 'admin.store.StoreProduct/skuList')->pattern(['id' => '\\d+']);
            Route::post(':id/sku', 'admin.store.StoreProduct/saveSku')->pattern(['id' => '\\d+']);
            Route::post(':id/sku/delete/:skuId', 'admin.store.StoreProduct/deleteSku')->pattern(['id' => '\\d+', 'skuId' => '\\d+']);
            Route::post(':id/sku/status/:skuId', 'admin.store.StoreProduct/skuStatus')->pattern(['id' => '\\d+', 'skuId' => '\\d+']);
            Route::post(':id/sku/sort/:skuId', 'admin.store.StoreProduct/sortSku')->pattern(['id' => '\\d+', 'skuId' => '\\d+']);
            Route::post(':id/virtual_stats', 'admin.store.StoreProduct/virtualStats')->pattern(['id' => '\\d+']);
            Route::post(':id/audit', 'admin.store.StoreProduct/audit')->pattern(['id' => '\\d+']);
            Route::resource('', 'admin.store.StoreProduct')->except(['create'])->pattern(['id' => '\\d+']);

            // 商品分类（复用通用 category 表，type=10；状态/资源接口必须在静态路由之后，避免遮蔽）
            Route::get('category/tree', 'admin.store.ProductCategory/tree');
            Route::get('category/list', 'admin.store.ProductCategory/list');
            Route::get('category/status/:id', 'admin.store.ProductCategory/show')->pattern(['id' => '\\d+']);
            Route::resource('category', 'admin.store.ProductCategory')->except(['create']);

            // 品牌/规格/服务/分组/属性资源（选项接口必须在 resource 之前）
            Route::get('brand/options', 'admin.store.ProductBrand/options');
            Route::get('brand/status/:id', 'admin.store.ProductBrand/show')->pattern(['id' => '\\d+']);
            Route::resource('brand', 'admin.store.ProductBrand')->except(['create']);

            Route::get('spec/options', 'admin.store.ProductSpec/options');
            Route::get('spec/status/:id', 'admin.store.ProductSpec/show')->pattern(['id' => '\\d+']);
            Route::resource('spec', 'admin.store.ProductSpec')->except(['create']);

            Route::get('service/options', 'admin.store.ProductService/options');
            Route::get('service/status/:id', 'admin.store.ProductService/show')->pattern(['id' => '\\d+']);
            Route::resource('service', 'admin.store.ProductService')->except(['create']);

            Route::get('group/options', 'admin.store.ProductGroup/options');
            Route::get('group/status/:id', 'admin.store.ProductGroup/show')->pattern(['id' => '\\d+']);
            Route::resource('group', 'admin.store.ProductGroup')->except(['create']);

            Route::get('attribute/options', 'admin.store.ProductAttribute/options');
            Route::get('attribute/template/:categoryId', 'admin.store.ProductAttribute/templateByCategory')->pattern(['categoryId' => '\\d+']);
            Route::get('attribute/status/:id', 'admin.store.ProductAttribute/show')->pattern(['id' => '\\d+']);
            Route::resource('attribute', 'admin.store.ProductAttribute')->except(['create']);

            // 运费模板（选项/默认/发货时间/地区树/规则接口必须在 resource 之前）
            Route::get('freight/options', 'admin.store.FreightTemplate/options');
            Route::get('freight/status/:id', 'admin.store.FreightTemplate/show')->pattern(['id' => '\\d+']);
            Route::post('freight/default/:id', 'admin.store.FreightTemplate/setDefault')->pattern(['id' => '\\d+']);
            Route::get('freight/delivery_time_options', 'admin.store.FreightTemplate/deliveryTimeOptions');
            Route::get('freight/city_tree', 'admin.store.FreightTemplate/cityTree');
            Route::get('freight/rules/:id', 'admin.store.FreightTemplate/rules')->pattern(['id' => '\\d+']);
            Route::resource('freight', 'admin.store.FreightTemplate')->except(['create']);
        });

        // ===== DIY页面管理路由 =====
        Route::group('diy', function () {
            // 移动端主题换色配置
            Route::get('theme_config', 'admin.diy.ThemeConfig/index');
            Route::post('theme_config', 'admin.diy.ThemeConfig/save');

            // DIY分类页面配置路由（静态路由须在动态参数路由之前）
            Route::get('category_page/config', 'admin.diy.DiyCategoryPage/config');
            Route::put('category_page/config', 'admin.diy.DiyCategoryPage/save');

            // DIY分类管理
            Route::get('category/tree', 'admin.diy.DiyCategory/tree');
            Route::post('category/quick_add', 'admin.diy.DiyCategory/quickAdd');
            Route::resource('category', 'admin.diy.DiyCategory')->except(['create', 'edit']);

            // DIY页面链接管理
            Route::get('link/options', 'admin.diy.DiyPageLink/options');
            Route::get('link/status/:id', 'admin.diy.DiyPageLink/show');
            Route::resource('link', 'admin.diy.DiyPageLink')->except(['create', 'edit']);

            // DIY页面管理
            Route::get('page/set_default/:id', 'admin.diy.DiyPage/setDefault');
            Route::get('page/detail/:id', 'admin.diy.DiyPage/detail');
            Route::get('page/preview/:id', 'admin.diy.DiyPage/preview');
            Route::get('page/component_types', 'admin.diy.DiyPage/componentTypes');
            Route::get('page/user_center', 'admin.diy.DiyPage/userCenter');
            Route::post('page/user_center/save', 'admin.diy.DiyPage/saveUserCenter');
            Route::post('page/save_as_template/:id', 'admin.diy.DiyPage/saveAsTemplate');
            Route::resource('page', 'admin.diy.DiyPage')->except(['create', 'edit']);
            
            // DIY组件管理
            Route::get('component/types', 'admin.diy.DiyComponent/types');
            Route::get('component/list_by_page', 'admin.diy.DiyComponent/listByPage');
            Route::post('component/batch_sort', 'admin.diy.DiyComponent/batchSort');
            Route::post('component/copy', 'admin.diy.DiyComponent/copy');
            Route::post('component/save_all', 'admin.diy.DiyComponent/saveAll');
            Route::resource('component', 'admin.diy.DiyComponent')->except(['create', 'edit']);
        });

        // 客服系统路由
        Route::group('cs', function () {
            // 客服会话
            Route::get('ws_token', 'admin.cs.CsSession/wsToken');
            Route::get('session', 'admin.cs.CsSession/index');
            Route::get('session/read', 'admin.cs.CsSession/read');
            Route::post('session/assign', 'admin.cs.CsSession/accept');
            Route::post('session/transfer', 'admin.cs.CsSession/transfer');
            Route::post('session/close', 'admin.cs.CsSession/close');
            Route::get('session/messages', 'admin.cs.CsSession/messages');
            Route::get('session/stats', 'admin.cs.CsSession/stats');

            // 客服消息
            Route::post('message', 'admin.cs.CsMessage/create');
            Route::put('message/mark_read', 'admin.cs.CsMessage/markRead');
            Route::put('message/mark_session_read', 'admin.cs.CsMessage/markSessionRead');

            // 客服人员
            Route::get('staff', 'admin.cs.CsStaff/index');
            Route::post('staff', 'admin.cs.CsStaff/create');
            Route::put('staff/online_status', 'admin.cs.CsStaff/updateOnlineStatus');
            Route::get('staff/online', 'admin.cs.CsStaff/online');
            Route::put('staff/:id', 'admin.cs.CsStaff/update');
            Route::delete('staff/:id', 'admin.cs.CsStaff/delete');

            // 知识库（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge', 'admin.cs.CsKnowledge/index');
            Route::get('knowledge/search', 'admin.cs.CsKnowledge/search');
            Route::post('knowledge/batch_delete', 'admin.cs.CsKnowledge/batchDelete');
            Route::post('knowledge/vectorize', 'admin.cs.CsKnowledge/vectorize');
            Route::post('knowledge/batch_vectorize', 'admin.cs.CsKnowledge/batchVectorize');
            Route::post('knowledge/import_file', 'admin.cs.CsKnowledge/importFile');
            Route::post('knowledge/import_url', 'admin.cs.CsKnowledge/importUrl');
            Route::post('knowledge/parse_content', 'admin.cs.CsKnowledge/parseContent');
            Route::post('knowledge', 'admin.cs.CsKnowledge/create');
            Route::post('knowledge/:id/segments', 'admin.cs.CsKnowledge/updateSegments')->pattern(['id' => '\d+']);
            Route::put('knowledge/:id', 'admin.cs.CsKnowledge/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge/:id', 'admin.cs.CsKnowledge/delete')->pattern(['id' => '\d+']);

            // 知识库实体（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge_base', 'admin.cs.CsKnowledgeBase/index');
            Route::post('knowledge_base', 'admin.cs.CsKnowledgeBase/save');
            Route::get('knowledge_base/:id/show', 'admin.cs.CsKnowledgeBase/show')->pattern(['id' => '\d+']);
            Route::post('knowledge_base/:id/test_reply', 'admin.cs.CsKnowledgeBase/testReply')->pattern(['id' => '\d+']);
            Route::get('knowledge_base/:id', 'admin.cs.CsKnowledgeBase/read')->pattern(['id' => '\d+']);
            Route::put('knowledge_base/:id', 'admin.cs.CsKnowledgeBase/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge_base/:id', 'admin.cs.CsKnowledgeBase/delete')->pattern(['id' => '\d+']);

            // 知识库段落（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge_segment', 'admin.cs.CsKnowledgeSegment/index');
            Route::post('knowledge_segment/batch_save', 'admin.cs.CsKnowledgeSegment/batchSave');
            Route::post('knowledge_segment/batch_vectorize', 'admin.cs.CsKnowledgeSegment/batchVectorize');
            Route::post('knowledge_segment/:id/vectorize', 'admin.cs.CsKnowledgeSegment/vectorize')->pattern(['id' => '\d+']);
            Route::put('knowledge_segment/:id', 'admin.cs.CsKnowledgeSegment/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge_segment/:id', 'admin.cs.CsKnowledgeSegment/delete')->pattern(['id' => '\d+']);

            // 知识库问答（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge_qa', 'admin.cs.CsKnowledgeQa/index');
            Route::post('knowledge_qa/batch_save', 'admin.cs.CsKnowledgeQa/batchSave');
            Route::post('knowledge_qa/batch_vectorize', 'admin.cs.CsKnowledgeQa/batchVectorize');
            Route::post('knowledge_qa', 'admin.cs.CsKnowledgeQa/create');
            Route::post('knowledge_qa/:id/vectorize', 'admin.cs.CsKnowledgeQa/vectorize')->pattern(['id' => '\d+']);
            Route::put('knowledge_qa/:id', 'admin.cs.CsKnowledgeQa/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge_qa/:id', 'admin.cs.CsKnowledgeQa/delete')->pattern(['id' => '\d+']);

            // 话术
            Route::get('script', 'admin.cs.CsScript/index');
            Route::post('script', 'admin.cs.CsScript/create');
            Route::put('script/status', 'admin.cs.CsScript/updateStatus');
            Route::put('script/:id', 'admin.cs.CsScript/update');
            Route::delete('script/:id', 'admin.cs.CsScript/delete');
            Route::post('script/batch_delete', 'admin.cs.CsScript/batchDelete');
            Route::get('script/search', 'admin.cs.CsScript/search');

            // 话术分类管理路由（按文章分类风格）
            Route::get('script_category', 'admin.cs.CsScriptCategory/index');
            Route::post('script_category', 'admin.cs.CsScriptCategory/save');
            Route::get('script_category/:id', 'admin.cs.CsScriptCategory/read');
            Route::put('script_category/:id', 'admin.cs.CsScriptCategory/update');
            Route::delete('script_category/:id', 'admin.cs.CsScriptCategory/delete');
            Route::get('script_category/:id/show', 'admin.cs.CsScriptCategory/show');
            Route::get('script_category_tree', 'admin.cs.CsScriptCategory/tree');
            Route::post('script_category/quick_add', 'admin.cs.CsScriptCategory/quickAdd');

            // 知识库分类管理路由
            Route::get('knowledge_category', 'admin.cs.CsKnowledgeCategory/index');
            Route::post('knowledge_category', 'admin.cs.CsKnowledgeCategory/save');
            Route::get('knowledge_category/:id', 'admin.cs.CsKnowledgeCategory/read');
            Route::put('knowledge_category/:id', 'admin.cs.CsKnowledgeCategory/update');
            Route::delete('knowledge_category/:id', 'admin.cs.CsKnowledgeCategory/delete');
            Route::get('knowledge_category/:id/show', 'admin.cs.CsKnowledgeCategory/show');
            Route::get('knowledge_category_tree', 'admin.cs.CsKnowledgeCategory/tree');
            Route::post('knowledge_category/quick_add', 'admin.cs.CsKnowledgeCategory/quickAdd');

            // AI服务商配置（静态路径优先）
            Route::get('ai_provider', 'admin.cs.CsAiModelConfig/providerList');
            Route::post('ai_provider', 'admin.cs.CsAiModelConfig/providerCreate');
            Route::put('ai_provider/:id/status', 'admin.cs.CsAiModelConfig/providerStatus');
            Route::put('ai_provider/:id', 'admin.cs.CsAiModelConfig/providerUpdate');
            Route::delete('ai_provider/:id', 'admin.cs.CsAiModelConfig/providerDelete');

            // AI模型配置（静态路径优先）
            Route::get('ai_model/type_options', 'admin.cs.CsAiModelConfig/modelTypeOptions');
            Route::get('ai_model', 'admin.cs.CsAiModelConfig/modelList');
            Route::post('ai_model', 'admin.cs.CsAiModelConfig/modelCreate');
            Route::put('ai_model/:id/default', 'admin.cs.CsAiModelConfig/modelDefault');
            Route::put('ai_model/:id/status', 'admin.cs.CsAiModelConfig/modelStatus');
            Route::put('ai_model/:id', 'admin.cs.CsAiModelConfig/modelUpdate');
            Route::delete('ai_model/:id', 'admin.cs.CsAiModelConfig/modelDelete');

        });

    })->middleware(AuthAdminLoginMiddleware::class)
        ->middleware(LockRequestMiddleware::class)
        ->middleware(AuthAdminRuleMiddleware::class)
        ->middleware(AuthAdminLogMiddleware::class);


})->middleware(AllowOriginMiddleware::class);
