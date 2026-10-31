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
use app\middleware\AuthMobileLoginMiddleware;
use app\middleware\OptionalAuthMobileMiddleware;
use think\facade\Route;

/**
 * 手机端接口
 */
Route::group('api/mobile', function () {

    Route::get('plugins/runtime', 'common.PluginRuntime/read');

    // 不需要登录验证的路由
    Route::group(function () {
        // 微信公众号 OAuth
        Route::get('wechat/oauth/redirect', 'mobile.wechat.OAuth/redirect');
        Route::get('wechat/oauth/callback', 'mobile.wechat.OAuth/callback');
        Route::get('wechat/jsSdkConfig', 'mobile.wechat.OAuth/jsSdkConfig');

        // 微信服务
        Route::any('wechat', 'mobile.wechat.Wechat/serve');

        // DIY 页面
        Route::get('diy/home', 'mobile.Diy/home')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('diy/page', 'mobile.Diy/page')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('diy/component/data', 'mobile.Diy/componentData')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('diy/user_center', 'mobile.Diy/userCenter')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('diy/category_page/config', 'mobile.Diy/categoryPageConfig')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('diy/preview', 'mobile.Diy/preview')->middleware(OptionalAuthMobileMiddleware::class);

        // 移动端主题配置
        Route::get('theme/config', 'mobile.Theme/config');

        // 商家公开页面
        Route::get('merchant/home/section', 'mobile.Merchant/homeSection')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('merchant/home', 'mobile.Merchant/home')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('merchant/category/list', 'mobile.Merchant/categoryList')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('merchant/category', 'mobile.Merchant/category')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('merchant/detail', 'mobile.Merchant/detail');

        // 商品公开查询
        Route::get('product/list', 'mobile.store.Product/list')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/detail', 'mobile.store.Product/detail')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/sku', 'mobile.store.Product/sku')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/coupons', 'mobile.store.Product/coupons')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/reviews', 'mobile.store.Product/reviews')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/favorite/status', 'mobile.store.Product/favoriteStatus')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/brand/options', 'mobile.store.Product/brandOptions')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/attribute/options', 'mobile.store.Product/attributeOptions')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('product/attribute/template/:categoryId', 'mobile.store.Product/attributeTemplate')->middleware(OptionalAuthMobileMiddleware::class)->pattern(['categoryId' => '\d+']);
        Route::get('product/categories', 'mobile.store.Product/categories');

        // 专题列表与分类
        Route::get('special/list', 'mobile.content.Special/index')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('special/category/tree', 'mobile.content.SpecialCategory/tree');
        // 内容列表与分类
        Route::get('content/list', 'mobile.content.Article/index')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('content/category/tree', 'mobile.content.ArticleCategory/tree');

        // 直播间接口
        Route::get('live/category/options', 'mobile.live.LiveRoom/categoryOptions');
        Route::get('live/gift/list', 'mobile.live.LiveRoom/gifts');
        Route::get('live/coin/recharge/options', 'mobile.live.LiveRoom/coinRechargeOptions');
        Route::get('live/room/list', 'mobile.live.LiveRoom/index')->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('live/room/:id/detail', 'mobile.live.LiveRoom/detail')->pattern(['id' => '\d+'])->middleware(OptionalAuthMobileMiddleware::class);
        Route::get('live/room/:id/users', 'mobile.live.LiveRoom/users')->pattern(['id' => '\d+']);
        Route::get('live/room/:id/goods', 'mobile.live.LiveRoom/goods')->pattern(['id' => '\d+']);
        Route::get('live/room/:id/comments', 'mobile.live.LiveRoom/comments')->pattern(['id' => '\d+']);

        // 移动端登录与短信验证
        Route::post('auth/smsCaptchaScene', 'mobile.Auth/smsCaptchaScene');
        Route::post('auth/smsCaptchaVerifySend', 'mobile.Auth/smsCaptchaVerifySend');
        Route::post('auth/phoneLogin', 'mobile.Auth/phoneLogin');
        Route::post('auth/login', 'mobile.Auth/accountLogin');
        Route::post('auth/accountLogin', 'mobile.Auth/accountLogin');
        Route::post('auth/resetPassword', 'mobile.Auth/resetPassword');
        Route::post('auth/refreshToken', 'mobile.Auth/refreshToken');

        // 用户收货地址基础数据
        Route::get('address/city_area', 'mobile.CityArea/bubble');

        // 微信小程序登录
        Route::post('miniapp/login', 'mobile.miniapp.Login/login');

        // 支付回调
        Route::post('order/wechatNotify', 'mobile.payment.Notify/orderWechat');
        Route::post('order/alipayNotify', 'mobile.payment.Notify/orderAlipay');
        Route::post('order/wechatRefundNotify', 'mobile.payment.Notify/orderWechatRefund');
        Route::post('user/recharge/wechatNotify', 'mobile.payment.Notify/rechargeWechat');
        Route::post('user/recharge/alipayNotify', 'mobile.payment.Notify/rechargeAlipay');

        // 内容评论
        Route::get('comment/list', 'mobile.comment.Comment/list');
        // 协议详情（公开）
        Route::get('agreement/detail', 'mobile.Agreement/detail');

        // 关于我们详情（公开）
        Route::get('about/detail', 'mobile.About/detail');

    });

    // 可选登录验证的路由
    Route::group(function () {
        // 统一搜索
        Route::get('search/meta', 'mobile.Search/meta');
        Route::get('search/result', 'mobile.Search/result');
        Route::post('search/record', 'mobile.Search/record');
        Route::post('search/history/clear', 'mobile.Search/clearHistory');

        // 专题推荐、收藏状态、预览与详情
        Route::get('special/recommend', 'mobile.content.Special/recommend');
        Route::get('special/favorite/status', 'mobile.content.Special/favoriteStatus');
        Route::post('special/preview/record', 'mobile.content.Special/recordPreview');
        Route::get('special/audio/list', 'mobile.content.Special/audioList');
        Route::get('special/products', 'mobile.content.Special/products');
        Route::get('special/detail', 'mobile.content.Special/detail');
        // 内容详情与收藏状态
        Route::get('content/favorite/status', 'mobile.content.Article/favoriteStatus');
        Route::get('content/products', 'mobile.content.Article/products');
        Route::get('content/detail', 'mobile.content.Article/detail');
    })->middleware(OptionalAuthMobileMiddleware::class);

    // 需要登录验证的路由
    Route::group(function () {
        // 专题收藏
        Route::post('special/favorite/toggle', 'mobile.content.Special/toggleFavorite');

        // 内容收藏
        Route::post('content/favorite/toggle', 'mobile.content.Article/toggleFavorite');

        // 店铺关注
        Route::get('merchant/follow/status', 'mobile.Merchant/followStatus');
        Route::post('merchant/follow/toggle', 'mobile.Merchant/toggleFollow');

        // 每日签到
        Route::get('activity/signin/index', 'mobile.activity.Signin/index');
        Route::post('activity/signin/sign', 'mobile.activity.Signin/sign');
        Route::post('activity/signin/task/claim', 'mobile.activity.Signin/claimTask');

        // 直播间接口
        Route::post('live/anchor/follow/toggle', 'mobile.live.LiveAnchor/toggleFollow');
        Route::post('live/room/like', 'mobile.live.LiveRoom/like');
        Route::get('live/room/:id/ws_token', 'mobile.live.LiveRoom/wsToken')->pattern(['id' => '\d+']);
        Route::post('live/room/:id/like', 'mobile.live.LiveRoom/likeRoom')->pattern(['id' => '\d+']);
        Route::post('live/room/:id/goods/buy_now', 'mobile.live.LiveRoom/buyNowGoods')->pattern(['id' => '\d+']);
        Route::post('live/room/:id/gift/send', 'mobile.live.LiveRoom/sendGift')->pattern(['id' => '\d+']);

        // 支付配置
        Route::get('payment/methods', 'mobile.payment.Payment/methods');

        // 移动端认证
        Route::get('auth/me', 'mobile.Auth/me');
        Route::get('user/info', 'mobile.Auth/me');
        Route::post('user/update', 'mobile.Auth/update');
        Route::post('auth/bindPhone', 'mobile.Auth/bindPhone');
        Route::get('user/bank-card', 'mobile.Auth/bankCard');
        Route::post('user/bank-card', 'mobile.Auth/saveBankCard');
        Route::post('auth/logout', 'mobile.Auth/logout');

        // 用户收藏
        Route::get('user/favorite/list', 'mobile.user.UserFavorite/index');
        Route::post('user/favorite/cancel', 'mobile.user.UserFavorite/cancel');

        // 用户关注
        Route::get('user/follow/list', 'mobile.user.UserFollow/index');
        Route::post('user/follow/cancel', 'mobile.user.UserFollow/cancel');

        // 用户足迹
        Route::get('user/browse/list', 'mobile.user.UserBrowseRecord/index');
        Route::post('user/browse/delete', 'mobile.user.UserBrowseRecord/delete');
        Route::post('user/browse/record', 'mobile.user.UserBrowseRecord/record');

        // 用户收货地址
        Route::get('user/address/list', 'mobile.user.UserAddress/index');
        Route::get('user/address/detail', 'mobile.user.UserAddress/detail');
        Route::post('user/address/create', 'mobile.user.UserAddress/create');
        Route::post('user/address/update', 'mobile.user.UserAddress/update');
        Route::post('user/address/delete', 'mobile.user.UserAddress/delete');

        // 微信小程序手机号
        Route::post('miniapp/phone', 'mobile.miniapp.Login/getPhoneNumber');
        Route::post('miniapp/bindPhone', 'mobile.miniapp.Login/bindPhone');
        Route::get('miniapp/subscribe/messages', 'mobile.miniapp.Subscribe/messages');

        // 商品（前台列表/详情/规格/分类）
        Route::get('product/list', 'mobile.store.Product/list');
        Route::get('product/detail', 'mobile.store.Product/detail');
        Route::get('product/sku', 'mobile.store.Product/sku');
        Route::get('product/coupons', 'mobile.store.Product/coupons');
        Route::get('product/reviews', 'mobile.store.Product/reviews');
        Route::get('product/favorite/status', 'mobile.store.Product/favoriteStatus');
        Route::post('product/favorite/toggle', 'mobile.store.Product/toggleFavorite');
        Route::get('product/categories', 'mobile.store.Product/categories');

        // 购物车
        Route::get('cart/list', 'mobile.order.Cart/index');
        Route::post('cart/add', 'mobile.order.Cart/add');
        Route::post('cart/buyNow', 'mobile.order.Cart/buyNow');
        Route::post('cart/changeNum', 'mobile.order.Cart/changeNum');
        Route::post('cart/changeSku', 'mobile.order.Cart/changeSku');
        Route::post('cart/delete', 'mobile.order.Cart/delete');
        Route::post('cart/settlement', 'mobile.order.Cart/settlement');

        // 优惠券
        Route::get('coupon/user_list', 'mobile.store.StoreCoupon/userList');
        Route::get('coupon/list', 'mobile.store.StoreCoupon/index');
        Route::post('coupon/receive', 'mobile.store.StoreCoupon/receive');

        // 订单确认与支付
        Route::get('order/list', 'mobile.order.Order/index');
        Route::get('order/detail', 'mobile.order.Order/detail');
        Route::get('order/express', 'mobile.order.Order/express');
        Route::get('order/payResult', 'mobile.order.Order/payResult');
        Route::get('order/badge', 'mobile.order.Order/badge');
        Route::get('order/paymentMethods', 'mobile.order.Order/paymentMethods');
        Route::get('order/review/page', 'mobile.order.Order/reviewPage');
        Route::get('order/review', 'mobile.order.Order/getReview');
        Route::get('order/refund/list', 'mobile.order.OrderRefund/index');
        Route::get('order/refund/detail', 'mobile.order.OrderRefund/detail');
        Route::post('order/confirm', 'mobile.order.Order/confirm');
        Route::post('order/create', 'mobile.order.Order/create');
        Route::post('order/cancel', 'mobile.order.Order/cancel');
        Route::post('order/pay', 'mobile.order.Order/pay');
        Route::post('order/repay', 'mobile.order.Order/repay');
        Route::post('order/refund', 'mobile.order.OrderRefund/apply');
        Route::post('order/refund/cancel', 'mobile.order.OrderRefund/cancel');
        Route::post('order/receipt', 'mobile.order.Order/receipt');
        Route::post('order/delete', 'mobile.order.Order/delete');
        Route::post('order/review', 'mobile.order.Order/review');

        // 文件上传
        Route::post('file/upload', 'mobile.File/upload');

        // 内容评论
        Route::post('comment/create', 'mobile.comment.Comment/create');
        Route::post('comment/reply', 'mobile.comment.Comment/reply');
        Route::post('comment/delete', 'mobile.comment.Comment/delete');

        // 内容点赞
        Route::post('content/like/toggle', 'mobile.content.Article/toggleLike');

        // 客服系统（用户端）
        Route::group('cs', function () {
            Route::post('session', 'mobile.cs.CsSession/create');
            Route::get('session/status', 'mobile.cs.CsSession/read');
            Route::get('ws_token', 'mobile.cs.CsSession/wsToken');

            Route::post('message', 'mobile.cs.CsMessage/create');
            Route::get('message/history', 'mobile.cs.CsMessage/history');
            Route::put('message/mark_session_read', 'mobile.cs.CsMessage/markSessionRead');
        });

        // 用户资产
        Route::get('user/asset/statistics', 'mobile.user.UserAsset/statistics');
        Route::get('user/asset/logs', 'mobile.user.UserAsset/logs');
        Route::get('user/commission/index', 'mobile.user.UserCommission/index');
        Route::get('user/commission/statistics', 'mobile.user.UserCommission/statistics');
        Route::get('user/commission/logs', 'mobile.user.UserCommission/logs');
        Route::get('user/commission/promotion_poster', 'mobile.user.UserCommission/promotionPoster');
        Route::get('user/recharge/options', 'mobile.user.UserRecharge/options');
        Route::post('user/recharge/create', 'mobile.user.UserRecharge/create');
        Route::get('user/recharge/detail', 'mobile.user.UserRecharge/detail');

        // 用户提现
        Route::get('user/withdraw/index', 'mobile.user.UserWithdraw/index');
        Route::post('user/withdraw/submit', 'mobile.user.UserWithdraw/submit');

        // 用户会员
        Route::get('user/member/info', 'mobile.user.UserMember/info');
        Route::get('user/member/packages', 'mobile.user.UserMember/packages');
        Route::post('user/member/create', 'mobile.user.UserMember/create');
    })->middleware(AuthMobileLoginMiddleware::class);
})->middleware(AllowOriginMiddleware::class);
