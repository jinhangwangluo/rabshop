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
use app\middleware\AuthMerchantLoginMiddleware;
use app\middleware\AuthMerchantRuleMiddleware;
use think\facade\Route;

/**
 * 商户端路由
 */
Route::group('api/merchant', function () {

    Route::get('plugins/runtime', 'common.PluginRuntime/read');

    // 登录相关（不需要登录验证）
    Route::group(function () {
        // 商户登录
        Route::post('login', 'merchant.Auth/login');
        // 获取站点信息
        Route::get('site_info', 'merchant.Auth/index');
        // 获取登录验证码
        Route::get('captcha', 'merchant.Auth/ajcaptcha');
        // 验证验证码
        Route::post('captcha/check', 'merchant.Auth/ajcheck');
    });

    // 需要登录验证的路由
    Route::group(function () {

        // 商户首页
        Route::get('dashboard/home', 'merchant.Dashboard/home');

        // 认证相关（需要登录）
        Route::group('auth', function () {
            // 退出登录
            Route::post('logout', 'merchant.Auth/logout');
            // 获取当前商户信息
            Route::get('info', 'merchant.Auth/info');
        });

        // 后台消息中心（静态路由优先）
        Route::group('backend_message', function () {
            Route::get('types', 'merchant.BackendMessage/types');
            Route::get('list', 'merchant.BackendMessage/list');
            Route::get('unread_count', 'merchant.BackendMessage/unreadCount');
            Route::put('read', 'merchant.BackendMessage/read');
            Route::put('read_all', 'merchant.BackendMessage/readAll');
            Route::delete('clear', 'merchant.BackendMessage/clear');
            Route::get('ws_token', 'merchant.BackendMessage/wsToken');
        });

        // 店铺设置
        Route::group('store', function () {
            // 获取店铺信息
            Route::get('info', 'merchant.Store/info');
            // 更新店铺信息
            Route::put('info', 'merchant.Store/updateInfo');
            // 更新营业状态
            Route::put('business_status', 'merchant.Store/businessStatus');
            // 上传店铺头像
            Route::post('upload_avatar', 'merchant.Store/uploadAvatar');
        });

        // 数据统计
        Route::group('statistics', function () {
            // 获取店铺统计数据
            Route::get('overview', 'merchant.Statistics/overview');
            // 销售统计
            Route::get('sales', 'merchant.Statistics/sales');
            // 订单统计
            Route::get('orders', 'merchant.Statistics/orders');
            // 直播统计
            Route::get('live', 'merchant.Statistics/live');
            // 内容统计
            Route::get('content', 'merchant.Statistics/content');
        });

        // 内容管理
        Route::group('content', function () {
            // 内容分类（只读，分类由平台管理）
            Route::get('article_category/tree', 'merchant.content.ArticleCategory/tree');
            // 专题分类（只读，分类由平台管理）
            Route::get('special_category/tree', 'merchant.content.SpecialCategory/tree');

            // 标签分组管理
            Route::get('tag_category/tree', 'merchant.content.TagCategory/tree');
            Route::post('tag_category/quick_add', 'merchant.content.TagCategory/quickAdd');
            Route::get('tag_category', 'merchant.content.TagCategory/index');
            Route::post('tag_category', 'merchant.content.TagCategory/save');
            Route::get('tag_category/:id/show', 'merchant.content.TagCategory/show')->pattern(['id' => '\\d+']);
            Route::get('tag_category/:id', 'merchant.content.TagCategory/read')->pattern(['id' => '\\d+']);
            Route::put('tag_category/:id', 'merchant.content.TagCategory/update')->pattern(['id' => '\\d+']);
            Route::delete('tag_category/:id', 'merchant.content.TagCategory/delete')->pattern(['id' => '\\d+']);

            // 标签管理
            Route::get('tag/options', 'merchant.content.Tag/options');
            Route::post('tag/quick_add', 'merchant.content.Tag/quickAdd');
            Route::post('tag/ai_import', 'merchant.content.Tag/aiImport');
            Route::get('tag/relation', 'merchant.content.Tag/relation');
            Route::put('tag/relation', 'merchant.content.Tag/saveRelation');
            Route::get('tag', 'merchant.content.Tag/index');
            Route::post('tag', 'merchant.content.Tag/save');
            Route::get('tag/:id/show', 'merchant.content.Tag/show')->pattern(['id' => '\\d+']);
            Route::get('tag/:id', 'merchant.content.Tag/read')->pattern(['id' => '\\d+']);
            Route::put('tag/:id', 'merchant.content.Tag/update')->pattern(['id' => '\\d+']);
            Route::delete('tag/:id', 'merchant.content.Tag/delete')->pattern(['id' => '\\d+']);

            // 内容管理（支持图文/文字/小说/图片/音频/视频）
            Route::group('article', function () {
                // 内容类型列表
                Route::get('content_types', 'merchant.content.Article/contentTypes');
                // 搜索内容列表（专题内容选择用）
                Route::get('search', 'merchant.content.Article/searchList');
                // AI润色写作
                Route::post('ai_generate', 'merchant.content.Article/aiGenerate');
                // AI续写正文/试读
                Route::post('ai_continue', 'merchant.content.Article/aiContinue');
                Route::get('list', 'merchant.content.Article/index');
                Route::post('create', 'merchant.content.Article/save');
                Route::get('detail/:id', 'merchant.content.Article/detail')->pattern(['id' => '\d+']);
                Route::put('quick_update/:id', 'merchant.content.Article/quickUpdate')->pattern(['id' => '\d+']);
                Route::put('update/:id', 'merchant.content.Article/update')->pattern(['id' => '\d+']);
                Route::put('status/:id/:status', 'merchant.content.Article/status')->pattern(['id' => '\d+', 'status' => '[01]']);
                Route::delete('delete/:id', 'merchant.content.Article/delete')->pattern(['id' => '\d+']);
            });

            // 专题管理
            Route::group('special', function () {
                Route::get('list', 'merchant.content.Special/index');
                Route::post('ai_generate', 'merchant.content.Special/aiGenerate');
                Route::get('detail/:id', 'merchant.content.Special/detail');
                Route::post('create', 'merchant.content.Special/save');
                Route::put('update/:id', 'merchant.content.Special/update');
                Route::put('status/:id/:status', 'merchant.content.Special/status');
                Route::delete('delete/:id', 'merchant.content.Special/delete');
                // 专题内容列表
                Route::get('content_list/:id', 'merchant.content.Special/contentList');
                // 内容选择器
                Route::get('search_content', 'merchant.content.Special/searchContent');
            });
        });

        // 订单管理
        Route::group('order', function () {
            Route::get('list', 'merchant.order.Order/index');
            Route::get('refund_list', 'merchant.order.Order/refundList');
            Route::get('statistics', 'merchant.order.Order/statistics');
            Route::get('refund_statistics', 'merchant.order.Order/refundStatistics');
            Route::get('express_options', 'merchant.order.Order/expressOptions');
            Route::get('detail/:id', 'merchant.order.Order/detail')->pattern(['id' => '\\d+']);
            Route::get('record/:id', 'merchant.order.Order/record')->pattern(['id' => '\\d+']);
            Route::put('delivery/:id', 'merchant.order.Order/delivery')->pattern(['id' => '\\d+']);
            Route::post('refund_apply', 'merchant.order.Order/refundApply');
            Route::post('receive_return/:id', 'merchant.order.Order/receiveReturn')->pattern(['id' => '\d+']);
            Route::post('refund_approve/:id', 'merchant.order.Order/refundApprove')->pattern(['id' => '\\d+']);
            Route::post('refund_reject/:id', 'merchant.order.Order/refundReject')->pattern(['id' => '\\d+']);
        });

        // 优惠券管理
        Route::group('coupon', function () {
            // 枚举与状态接口必须在 resource 之前
            Route::get('scope_types', 'merchant.store.StoreCoupon/getScopeTypes');
            Route::get('expire_types', 'merchant.store.StoreCoupon/getExpireTypes');
            Route::get('distribute_types', 'merchant.store.StoreCoupon/getDistributeTypes');
            Route::get('relation_types', 'merchant.store.StoreCoupon/getRelationTypes');
            Route::get('status/:id', 'merchant.store.StoreCoupon/show')->pattern(['id' => '\\d+']);

            // 领取记录子组必须在主 resource 之前，避免 read 路由误匹配
            Route::group('user', function () {
                Route::get('status_list', 'merchant.store.StoreCouponUser/getStatusList');
                Route::get('receive_types', 'merchant.store.StoreCouponUser/getReceiveTypes');
                Route::resource('', 'merchant.store.StoreCouponUser')->only(['index', 'read']);
            });

            Route::resource('', 'merchant.store.StoreCoupon')->except(['create', 'edit']);
        });

        // 商品管理（商品库/店铺商品/规格/品牌/服务/分组/属性/运费模板）
        Route::group('product', function () {
            // 商品分类：平台分类只读用于 category_id，商户分类 CRUD 用于 category_ids。
            Route::get('category/platform_tree', 'merchant.store.ProductCategory/platformTree');
            Route::get('category/platform_list', 'merchant.store.ProductCategory/platformList');
            Route::get('category/tree', 'merchant.store.ProductCategory/tree');
            Route::get('category/list', 'merchant.store.ProductCategory/list');
            Route::get('category/status/:id', 'merchant.store.ProductCategory/show')->pattern(['id' => '\\d+']);
            Route::resource('category', 'merchant.store.ProductCategory')->except(['create', 'edit'])->pattern(['id' => '\\d+']);

            // 商品标签与商品标签分组（固定 tag_type=2）
            Route::get('tag_category/tree', 'merchant.store.ProductTagCategory/tree');
            Route::post('tag_category/quick_add', 'merchant.store.ProductTagCategory/quickAdd');
            Route::get('tag_category', 'merchant.store.ProductTagCategory/index');
            Route::post('tag_category', 'merchant.store.ProductTagCategory/save');
            Route::get('tag_category/:id/show', 'merchant.store.ProductTagCategory/show')->pattern(['id' => '\\d+']);
            Route::get('tag_category/:id', 'merchant.store.ProductTagCategory/read')->pattern(['id' => '\\d+']);
            Route::put('tag_category/:id', 'merchant.store.ProductTagCategory/update')->pattern(['id' => '\\d+']);
            Route::delete('tag_category/:id', 'merchant.store.ProductTagCategory/delete')->pattern(['id' => '\\d+']);

            Route::get('tag/options', 'merchant.store.ProductTag/options');
            Route::post('tag/quick_add', 'merchant.store.ProductTag/quickAdd');
            Route::post('tag/ai_import', 'merchant.store.ProductTag/aiImport');
            Route::get('tag/relation', 'merchant.store.ProductTag/relation');
            Route::put('tag/relation', 'merchant.store.ProductTag/saveRelation');
            Route::get('tag', 'merchant.store.ProductTag/index');
            Route::post('tag', 'merchant.store.ProductTag/save');
            Route::get('tag/:id/show', 'merchant.store.ProductTag/show')->pattern(['id' => '\\d+']);
            Route::get('tag/:id', 'merchant.store.ProductTag/read')->pattern(['id' => '\\d+']);
            Route::put('tag/:id', 'merchant.store.ProductTag/update')->pattern(['id' => '\\d+']);
            Route::delete('tag/:id', 'merchant.store.ProductTag/delete')->pattern(['id' => '\\d+']);

            // 商品库管理（状态/选项接口必须在 resource 之前）
            Route::get('library/status/:id', 'merchant.store.ProductLibrary/show')->pattern(['id' => '\\d+']);
            Route::post('library/share/:id', 'merchant.store.ProductLibrary/share')->pattern(['id' => '\\d+']);
            Route::get('library/options', 'merchant.store.ProductLibrary/options');
            Route::resource('library', 'merchant.store.ProductLibrary')->except(['create', 'edit']);

            // 品牌/规格/服务/分组/属性资源（选项接口必须在 resource 之前）
            Route::get('brand/options', 'merchant.store.ProductBrand/options');
            Route::get('brand/status/:id', 'merchant.store.ProductBrand/show')->pattern(['id' => '\\d+']);
            Route::resource('brand', 'merchant.store.ProductBrand')->except(['create', 'edit']);

            Route::get('spec/options', 'merchant.store.ProductSpec/options');
            Route::get('spec/status/:id', 'merchant.store.ProductSpec/show')->pattern(['id' => '\\d+']);
            Route::resource('spec', 'merchant.store.ProductSpec')->except(['create', 'edit']);

            Route::get('service/options', 'merchant.store.ProductService/options');
            Route::get('service/status/:id', 'merchant.store.ProductService/show')->pattern(['id' => '\\d+']);
            Route::resource('service', 'merchant.store.ProductService')->except(['create', 'edit']);

            Route::get('group/options', 'merchant.store.ProductGroup/options');
            Route::get('group/status/:id', 'merchant.store.ProductGroup/show')->pattern(['id' => '\\d+']);
            Route::resource('group', 'merchant.store.ProductGroup')->except(['create', 'edit']);

            Route::get('attribute/options', 'merchant.store.ProductAttribute/options');
            Route::get('attribute/template/:categoryId', 'merchant.store.ProductAttribute/templateByCategory')->pattern(['categoryId' => '\\d+']);
            Route::get('attribute/status/:id', 'merchant.store.ProductAttribute/show')->pattern(['id' => '\\d+']);
            Route::resource('attribute', 'merchant.store.ProductAttribute')->except(['create', 'edit']);

            // 运费模板（选项/默认/发货时间/地区树/规则接口必须在 resource 之前）
            Route::get('freight/options', 'merchant.store.FreightTemplate/options');
            Route::get('freight/status/:id', 'merchant.store.FreightTemplate/show')->pattern(['id' => '\\d+']);
            Route::post('freight/default/:id', 'merchant.store.FreightTemplate/setDefault')->pattern(['id' => '\\d+']);
            Route::get('freight/delivery_time_options', 'merchant.store.FreightTemplate/deliveryTimeOptions');
            Route::get('freight/city_tree', 'merchant.store.FreightTemplate/cityTree');
            Route::get('freight/rules/:id', 'merchant.store.FreightTemplate/rules')->pattern(['id' => '\\d+']);
            Route::resource('freight', 'merchant.store.FreightTemplate')->except(['create', 'edit']);

            // 店铺商品管理根资源放最后，避免动态资源路由遮蔽子模块静态路径。
            Route::get('status/:id', 'merchant.store.StoreProduct/show')->pattern(['id' => '\\d+']);
            Route::post('assemble', 'merchant.store.StoreProduct/assemble');
            Route::get('options', 'merchant.store.StoreProduct/options');
            Route::get(':id/sku_list', 'merchant.store.StoreProduct/skuList')->pattern(['id' => '\\d+']);
            Route::post(':id/sku', 'merchant.store.StoreProduct/saveSku')->pattern(['id' => '\\d+']);
            Route::post(':id/sku/delete/:skuId', 'merchant.store.StoreProduct/deleteSku')->pattern(['id' => '\\d+', 'skuId' => '\\d+']);
            Route::post(':id/sku/status/:skuId', 'merchant.store.StoreProduct/skuStatus')->pattern(['id' => '\\d+', 'skuId' => '\\d+']);
            Route::post(':id/sku/sort/:skuId', 'merchant.store.StoreProduct/sortSku')->pattern(['id' => '\\d+', 'skuId' => '\\d+']);
            Route::post(':id/virtual_stats', 'merchant.store.StoreProduct/virtualStats')->pattern(['id' => '\\d+']);
            Route::resource('', 'merchant.store.StoreProduct')->except(['create', 'edit'])->pattern(['id' => '\\d+']);
        });

        // 客服系统
        Route::group('cs', function () {
            // 会话管理
            Route::get('session', 'merchant.cs.CsSession/index');
            Route::get('session/read', 'merchant.cs.CsSession/read');
            Route::post('session/assign', 'merchant.cs.CsSession/accept');
            Route::post('session/transfer', 'merchant.cs.CsSession/transfer');
            Route::post('session/close', 'merchant.cs.CsSession/close');
            Route::get('session/messages', 'merchant.cs.CsSession/messages');
            Route::get('session/stats', 'merchant.cs.CsSession/stats');

            // 消息管理
            Route::post('message', 'merchant.cs.CsMessage/create');
            Route::put('message/mark_read', 'merchant.cs.CsMessage/markRead');
            Route::put('message/mark_session_read', 'merchant.cs.CsMessage/markSessionRead');

            // 客服人员管理
            Route::get('staff', 'merchant.cs.CsStaff/index');
            Route::post('staff', 'merchant.cs.CsStaff/create');
            Route::put('staff/online_status', 'merchant.cs.CsStaff/updateOnlineStatus');
            Route::get('staff/online', 'merchant.cs.CsStaff/online');
            Route::put('staff/:id', 'merchant.cs.CsStaff/update');
            Route::delete('staff/:id', 'merchant.cs.CsStaff/delete');

            // AI服务商配置（静态路径优先）
            Route::get('ai_provider', 'merchant.cs.CsAiModelConfig/providerList');
            Route::post('ai_provider', 'merchant.cs.CsAiModelConfig/providerCreate');
            Route::put('ai_provider/:id/status', 'merchant.cs.CsAiModelConfig/providerStatus')->pattern(['id' => '\d+']);
            Route::put('ai_provider/:id', 'merchant.cs.CsAiModelConfig/providerUpdate')->pattern(['id' => '\d+']);
            Route::delete('ai_provider/:id', 'merchant.cs.CsAiModelConfig/providerDelete')->pattern(['id' => '\d+']);

            // AI模型配置（静态路径优先）
            Route::get('ai_model/type_options', 'merchant.cs.CsAiModelConfig/modelTypeOptions');
            Route::get('ai_model', 'merchant.cs.CsAiModelConfig/modelList');
            Route::post('ai_model', 'merchant.cs.CsAiModelConfig/modelCreate');
            Route::put('ai_model/:id/default', 'merchant.cs.CsAiModelConfig/modelDefault')->pattern(['id' => '\d+']);
            Route::put('ai_model/:id/status', 'merchant.cs.CsAiModelConfig/modelStatus')->pattern(['id' => '\d+']);
            Route::put('ai_model/:id', 'merchant.cs.CsAiModelConfig/modelUpdate')->pattern(['id' => '\d+']);
            Route::delete('ai_model/:id', 'merchant.cs.CsAiModelConfig/modelDelete')->pattern(['id' => '\d+']);

            // 知识库实体管理（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge_base', 'merchant.cs.CsKnowledgeBase/index');
            Route::post('knowledge_base', 'merchant.cs.CsKnowledgeBase/save');
            Route::get('knowledge_base/:id/show', 'merchant.cs.CsKnowledgeBase/show')->pattern(['id' => '\d+']);
            Route::post('knowledge_base/:id/test_reply', 'merchant.cs.CsKnowledgeBase/testReply')->pattern(['id' => '\d+']);
            Route::get('knowledge_base/:id', 'merchant.cs.CsKnowledgeBase/read')->pattern(['id' => '\d+']);
            Route::put('knowledge_base/:id', 'merchant.cs.CsKnowledgeBase/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge_base/:id', 'merchant.cs.CsKnowledgeBase/delete')->pattern(['id' => '\d+']);

            // 知识库管理（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge', 'merchant.cs.CsKnowledge/index');
            Route::get('knowledge/search', 'merchant.cs.CsKnowledge/search');
            Route::post('knowledge/batch_delete', 'merchant.cs.CsKnowledge/batchDelete');
            Route::post('knowledge/vectorize', 'merchant.cs.CsKnowledge/vectorize');
            Route::post('knowledge/batch_vectorize', 'merchant.cs.CsKnowledge/batchVectorize');
            Route::post('knowledge/import_file', 'merchant.cs.CsKnowledge/importFile');
            Route::post('knowledge/import_url', 'merchant.cs.CsKnowledge/importUrl');
            Route::post('knowledge/parse_content', 'merchant.cs.CsKnowledge/parseContent');
            Route::post('knowledge', 'merchant.cs.CsKnowledge/create');
            Route::post('knowledge/:id/segments', 'merchant.cs.CsKnowledge/updateSegments')->pattern(['id' => '\d+']);
            Route::put('knowledge/:id', 'merchant.cs.CsKnowledge/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge/:id', 'merchant.cs.CsKnowledge/delete')->pattern(['id' => '\d+']);

            // 段落管理（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge_segment', 'merchant.cs.CsKnowledgeSegment/index');
            Route::post('knowledge_segment/batch_save', 'merchant.cs.CsKnowledgeSegment/batchSave');
            Route::post('knowledge_segment/batch_vectorize', 'merchant.cs.CsKnowledgeSegment/batchVectorize');
            Route::post('knowledge_segment/:id/vectorize', 'merchant.cs.CsKnowledgeSegment/vectorize')->pattern(['id' => '\d+']);
            Route::put('knowledge_segment/:id', 'merchant.cs.CsKnowledgeSegment/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge_segment/:id', 'merchant.cs.CsKnowledgeSegment/delete')->pattern(['id' => '\d+']);

            // QA管理（静态路径优先，动态 /:id 放最后）
            Route::get('knowledge_qa', 'merchant.cs.CsKnowledgeQa/index');
            Route::post('knowledge_qa/batch_save', 'merchant.cs.CsKnowledgeQa/batchSave');
            Route::post('knowledge_qa/batch_vectorize', 'merchant.cs.CsKnowledgeQa/batchVectorize');
            Route::post('knowledge_qa', 'merchant.cs.CsKnowledgeQa/create');
            Route::post('knowledge_qa/:id/vectorize', 'merchant.cs.CsKnowledgeQa/vectorize')->pattern(['id' => '\d+']);
            Route::put('knowledge_qa/:id', 'merchant.cs.CsKnowledgeQa/update')->pattern(['id' => '\d+']);
            Route::delete('knowledge_qa/:id', 'merchant.cs.CsKnowledgeQa/delete')->pattern(['id' => '\d+']);

            // 知识库分类管理路由（静态路由必须在 :id 动态路由之前）
            Route::get('knowledge_category', 'merchant.cs.CsKnowledgeCategory/index');
            Route::post('knowledge_category', 'merchant.cs.CsKnowledgeCategory/save');
            Route::get('knowledge_category_tree', 'merchant.cs.CsKnowledgeCategory/tree');
            Route::post('knowledge_category/quick_add', 'merchant.cs.CsKnowledgeCategory/quickAdd');
            Route::get('knowledge_category/:id', 'merchant.cs.CsKnowledgeCategory/read');
            Route::put('knowledge_category/:id', 'merchant.cs.CsKnowledgeCategory/update');
            Route::delete('knowledge_category/:id', 'merchant.cs.CsKnowledgeCategory/delete');
            Route::get('knowledge_category/:id/show', 'merchant.cs.CsKnowledgeCategory/show');

            // 话术管理
            Route::get('script', 'merchant.cs.CsScript/index');
            Route::put('script/:id', 'merchant.cs.CsScript/update');
            Route::put('script/status', 'merchant.cs.CsScript/updateStatus');
            Route::delete('script/:id', 'merchant.cs.CsScript/delete');
            Route::post('script/batch_delete', 'merchant.cs.CsScript/batchDelete');
            Route::post('script', 'merchant.cs.CsScript/create');
            Route::get('script/search', 'merchant.cs.CsScript/search');

            // 话术分类管理路由（按文章分类风格）
            Route::get('script_category', 'merchant.cs.CsScriptCategory/index');
            Route::post('script_category', 'merchant.cs.CsScriptCategory/save');
            Route::get('script_category/:id', 'merchant.cs.CsScriptCategory/read');
            Route::put('script_category/:id', 'merchant.cs.CsScriptCategory/update');
            Route::delete('script_category/:id', 'merchant.cs.CsScriptCategory/delete');
            Route::get('script_category/:id/show', 'merchant.cs.CsScriptCategory/show');
            Route::get('script_category_tree', 'merchant.cs.CsScriptCategory/tree');
            Route::post('script_category/quick_add', 'merchant.cs.CsScriptCategory/quickAdd');

        });

        // 财务管理
        Route::group('finance', function () {
            // 财务概览
            Route::get('overview', 'merchant.Finance/overview');
            // 流水账单
            Route::get('records', 'merchant.Finance/records');
            // 提现申请
            Route::post('withdraw', 'merchant.Finance/withdraw');
            // 提现记录
            Route::get('withdraw_records', 'merchant.Finance/withdrawRecords');
        });

        // 公共配置
        Route::get('common/config', 'merchant.Common/config');

        // 直播模块
        Route::group('live', function () {
            // 只读接口（直播分类/礼物/等级配置，由平台管理，商户只读）
            Route::get('category/options', 'merchant.live.LiveReadOnly/categoryOptions');
            Route::get('gift/available', 'merchant.live.LiveReadOnly/availableGifts');
            Route::get('level_config/options', 'merchant.live.LiveReadOnly/levelConfigOptions');

            // 主播管理（静态路由须在动态路由之前）
            Route::get('anchor_options', 'merchant.live.LiveAnchor/options');
            Route::get('anchor/:id/show', 'merchant.live.LiveAnchor/show');
            Route::post('anchor/:id/audit', 'merchant.live.LiveAnchor/audit');
            Route::post('anchor/:id/toggle_ban', 'merchant.live.LiveAnchor/toggleBan');
            Route::post('anchor/:id/add_violation', 'merchant.live.LiveAnchor/addViolation');
            Route::resource('anchor', 'merchant.live.LiveAnchor')->except(['create', 'edit']);

            // 直播商品管理
            Route::get('goods/status/:id', 'merchant.live.LiveGoods/show');
            Route::post('goods/batch_sort', 'merchant.live.LiveGoods/batchSort');
            Route::get('goods/special_options', 'merchant.live.LiveGoods/specialOptions');
            Route::resource('goods', 'merchant.live.LiveGoods')->except(['create', 'edit']);

            // 直播间管理（静态路由须在动态路由 room/:id 之前）
            Route::get('room/status/:id', 'merchant.live.LiveRoom/show');
            Route::post('room/:id/switch_status', 'merchant.live.LiveRoom/switchStatus');
            Route::post('room/:id/toggle_hot', 'merchant.live.LiveRoom/toggleHot');
            Route::get('room/:id/detail', 'merchant.live.LiveRoom/detail');
            Route::get('room/:id/ws_token', 'merchant.live.LiveRoom/wsToken');
            Route::get('room/:id/stats', 'merchant.live.LiveRoom/stats');
            Route::get('room/:id/users', 'merchant.live.LiveRoom/users');
            Route::get('room/:roomId/user_level/:userId', 'merchant.live.LiveRoomUserLevel/getUserLevel');
            Route::post('room/:roomId/user/:userId/mute', 'merchant.live.LiveRoom/muteUser');
            Route::post('room/:roomId/user/:userId/kick', 'merchant.live.LiveRoom/kickUser');
            Route::get('room/:id/comments', 'merchant.live.LiveRoom/comments');
            Route::delete('room/:roomId/comment/:commentId', 'merchant.live.LiveRoom/deleteComment');
            // 推拉流管理
            Route::post('room/:id/create_stream', 'merchant.live.LiveStreamConfig/createStream');
            Route::post('room/:id/close_stream', 'merchant.live.LiveStreamConfig/closeStream');
            Route::get('room/:id/stream_info', 'merchant.live.LiveStreamConfig/streamInfo');
            // 回放归档管理
            Route::get('room/:roomId/records', 'merchant.live.LiveRoomRecord/records');
            Route::delete('room/:roomId/record/:recordId', 'merchant.live.LiveRoomRecord/deleteRecord');
            Route::post('room/:roomId/record/:recordId/playback', 'merchant.live.LiveRoomRecord/setPlayback');
            // 贡献榜
            Route::get('room/:id/rank', 'merchant.live.LiveRoomRank/getRoomRank');
            Route::resource('room', 'merchant.live.LiveRoom')->except(['create', 'edit']);

            // 打赏统计
            Route::get('reward', 'merchant.live.LiveReward/index');
        });

        // 系统设置
        Route::group('setting', function () {
            // 菜单管理
            Route::group('menu', function () {
                Route::get('list', 'merchant.setting.Menu/index');
            });

            // 日志管理
            Route::group('log', function () {
                Route::get('list', 'merchant.setting.Log/index');
                Route::get('admin', 'merchant.setting.Log/adminList');
            });

            // 管理员管理
            Route::group('admin', function () {
                Route::get('list', 'merchant.setting.Admin/index');
                Route::get('create', 'merchant.setting.Admin/create');
                Route::get('detail/:id', 'merchant.setting.Admin/read');
                Route::post('save/:id', 'merchant.setting.Admin/save');
                Route::put('status/:id/:status', 'merchant.setting.Admin/status');
                Route::delete('delete/:id', 'merchant.setting.Admin/delete');
                Route::get('select', 'merchant.setting.Admin/select');
                // 当前管理员信息
                Route::get('info', 'merchant.setting.Admin/adminInfo');
                Route::put('info', 'merchant.setting.Admin/putAdminInfo');
            });

            // 角色管理
            Route::group('role', function () {
                Route::get('list', 'merchant.setting.Role/index');
                Route::get('create', 'merchant.setting.Role/create');
                Route::get('detail/:id', 'merchant.setting.Role/read');
                Route::post('save/:id', 'merchant.setting.Role/save');
                Route::put('status/:id/:status', 'merchant.setting.Role/status');
                Route::delete('delete/:id', 'merchant.setting.Role/delete');
                Route::get('select', 'merchant.setting.Role/select');
            });

            // 组合数据管理（静态路由须在动态资源路由之前）
            Route::get('combination_data_config/options', 'merchant.combination.CombinationDataConfig/options');
            Route::get('combination_data_config/:id/status/:status', 'merchant.combination.CombinationDataConfig/show')->pattern(['id' => '\d+', 'status' => '\d+']);
            Route::get('combination_data/schema/:configId', 'merchant.combination.CombinationData/schema')->pattern(['configId' => '\d+']);
            Route::resource('combination_data_config', 'merchant.combination.CombinationDataConfig')->except(['create', 'edit']);
            Route::resource('combination_data', 'merchant.combination.CombinationData')->except(['create', 'edit', 'show']);

            // 组合数据分类
            Route::get('combination_data_category', 'merchant.combination.CombinationDataCategory/index');
            Route::post('combination_data_category', 'merchant.combination.CombinationDataCategory/save');
            Route::get('combination_data_category/:id', 'merchant.combination.CombinationDataCategory/read')->pattern(['id' => '\d+']);
            Route::put('combination_data_category/:id', 'merchant.combination.CombinationDataCategory/update')->pattern(['id' => '\d+']);
            Route::delete('combination_data_category/:id', 'merchant.combination.CombinationDataCategory/delete')->pattern(['id' => '\d+']);
            Route::get('combination_data_category/:id/show', 'merchant.combination.CombinationDataCategory/show')->pattern(['id' => '\d+']);

            // 文件管理
            Route::group('file', function () {
                // 分类管理（具体路由必须在动态路由之前）
                Route::post('category/move', 'merchant.setting.FileCategory/move');
                Route::post('category/:id', 'merchant.setting.FileCategory/save');
                Route::resource('category', 'merchant.setting.FileCategory')->except(['save', 'read', 'update', 'edit', 'show']);
                // 文件管理
                Route::get('list', 'merchant.setting.File/list');
                Route::post('upload', 'merchant.setting.File/upload');
                Route::get('upload_config', 'merchant.setting.File/getUploadConfig');
                Route::post('chunk_upload', 'merchant.setting.File/chunkUpload');
                // 文件库 AI 生图任务（静态路由需在 file/:id 前注册）
                Route::get('ai-generate/tasks', 'merchant.setting.FileAiGenerate/list');
                Route::post('ai-generate/tasks', 'merchant.setting.FileAiGenerate/save');
                Route::get('ai-generate/tasks/:id', 'merchant.setting.FileAiGenerate/read')->pattern(['id' => '\d+']);
                Route::post('ai-generate/tasks/:id/cancel', 'merchant.setting.FileAiGenerate/cancel')->pattern(['id' => '\d+']);
                Route::post('ai-generate/tasks/:id/retry', 'merchant.setting.FileAiGenerate/retry')->pattern(['id' => '\d+']);
                Route::delete('delete', 'merchant.setting.File/delete');
                Route::put(':id', 'merchant.setting.File/update');
            });
        });

    })->middleware(AuthMerchantLoginMiddleware::class)
        ->middleware(AuthMerchantRuleMiddleware::class);

})->middleware(AllowOriginMiddleware::class);
