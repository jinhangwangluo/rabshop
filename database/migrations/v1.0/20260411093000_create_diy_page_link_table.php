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

use think\migration\Migrator;

/**
 * DIY页面链接表
 */
class CreateDiyPageLinkTable extends Migrator
{
    private const CATEGORY_TYPE = 2;

    public function change()
    {
        $table = $this->table('diy_page_link', [
            'comment' => 'DIY页面链接表',
            'collation' => 'utf8mb4_general_ci'
        ]);
        $table->addColumn('cate_id', 'integer', [
            'limit' => 10,
            'default' => 0,
            'comment' => '所属分类ID'
        ]);
        $table->addColumn('link_name', 'string', [
            'limit' => 100,
            'default' => '',
            'comment' => '链接名称'
        ]);
        $table->addColumn('link_url', 'string', [
            'limit' => 500,
            'default' => '',
            'comment' => '链接地址'
        ]);
        $table->addColumn('link_type', 'string', [
            'limit' => 20,
            'default' => 'page',
            'comment' => '链接类型：page-页面 url-外链 mini-小程序'
        ]);
        $table->addColumn('platform_type', 'string', [
            'limit' => 20,
            'default' => 'common',
            'comment' => '平台类型：common-通用 h5-H5 mp-小程序 pc-PC'
        ]);
        $table->addColumn('mini_appid', 'string', [
            'limit' => 100,
            'default' => '',
            'comment' => '小程序 AppID'
        ]);
        $table->addColumn('mini_path', 'string', [
            'limit' => 500,
            'default' => '',
            'comment' => '小程序页面路径'
        ]);
        $table->addColumn('icon', 'string', [
            'limit' => 255,
            'default' => '',
            'comment' => '图标'
        ]);
        $table->addColumn('param_desc', 'text', [
            'null' => true,
            'comment' => '参数说明'
        ]);
        $table->addColumn('example', 'string', [
            'limit' => 255,
            'default' => '',
            'comment' => '示例'
        ]);
        $table->addColumn('remark', 'text', [
            'null' => true,
            'comment' => '备注'
        ]);
        $table->addColumn('sort', 'integer', [
            'limit' => 10,
            'default' => 0,
            'comment' => '排序'
        ]);
        $table->addColumn('status', 'boolean', [
            'limit' => 1,
            'default' => 1,
            'comment' => '状态：0-禁用 1-启用'
        ]);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('cate_id');
        $table->addIndex('status');
        $table->addIndex('sort');
        $table->addIndex('platform_type');
        $table->addIndex('link_type');
        $table->create();

        $this->seedDefaultPageLinks();
    }

    /**
     * 获取数据库表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }

    /**
     * 插入 UniApp 默认页面链接。
     */
    private function seedDefaultPageLinks(): void
    {
        $categoryIds = $this->ensureDiyCategoryIds();
        $now = date('Y-m-d H:i:s');
        $rows = [];

        foreach ($this->getDefaultPageLinks() as $item) {
            $categoryName = $item['category'];
            if (empty($categoryIds[$categoryName])) {
                continue;
            }

            $rows[] = [
                'cate_id' => $categoryIds[$categoryName],
                'link_name' => $item['name'],
                'link_url' => $item['url'],
                'link_type' => 'page',
                'platform_type' => 'common',
                'icon' => '',
                'param_desc' => '页面基础路径，可按业务追加 query 参数。',
                'example' => $item['url'],
                'remark' => '来源：template/uniapp/pages.json',
                'sort' => $item['sort'],
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        if ($rows) {
            $this->table('diy_page_link')->insert($rows)->saveData();
        }
    }

    /**
     * 确保 DIY 链接分类存在，并返回分类 ID 映射。
     */
    private function ensureDiyCategoryIds(): array
    {
        $prefix = $this->getPrefix();
        $now = date('Y-m-d H:i:s');
        $ids = [];

        foreach ($this->getDiyLinkCategories() as $category) {
            $name = addslashes($category['name']);
            $row = $this->fetchRow(
                "SELECT `id` FROM `{$prefix}category` WHERE `type` = " . self::CATEGORY_TYPE
                . " AND `owner_id` = 0 AND `pid` = 0 AND `name` = '{$name}' LIMIT 1"
            );

            if (!$row) {
                $this->table('category')->insert([[
                    'pid' => 0,
                    'owner_id' => 0,
                    'name' => $category['name'],
                    'path' => '',
                    'image' => '',
                    'description' => $category['description'],
                    'sort' => $category['sort'],
                    'type' => self::CATEGORY_TYPE,
                    'status' => 1,
                    'other' => '{}',
                    'create_time' => $now,
                    'update_time' => $now,
                ]])->saveData();

                $row = $this->fetchRow(
                    "SELECT `id` FROM `{$prefix}category` WHERE `type` = " . self::CATEGORY_TYPE
                    . " AND `owner_id` = 0 AND `pid` = 0 AND `name` = '{$name}' LIMIT 1"
                );
            }

            $ids[$category['name']] = (int)($row['id'] ?? 0);
        }

        return $ids;
    }

    /**
     * DIY 链接分类。
     */
    private function getDiyLinkCategories(): array
    {
        return [
            ['name' => '基础页面', 'description' => 'UniApp 主包基础导航页面', 'sort' => 800],
            ['name' => 'DIY页面', 'description' => 'UniApp DIY 页面', 'sort' => 700],
            ['name' => '直播页面', 'description' => 'UniApp 直播相关页面', 'sort' => 600],
            ['name' => '用户中心', 'description' => 'UniApp 用户中心相关页面', 'sort' => 500],
            ['name' => '订单交易', 'description' => 'UniApp 订单与支付相关页面', 'sort' => 400],
            ['name' => '商户页面', 'description' => 'UniApp 商户相关页面', 'sort' => 300],
            ['name' => '营销活动', 'description' => 'UniApp 活动营销页面', 'sort' => 200],
            ['name' => '内容页面', 'description' => 'UniApp 内容与专题页面', 'sort' => 100],
        ];
    }

    /**
     * 从 template/uniapp/pages.json 整理出的默认页面链接。
     */
    private function getDefaultPageLinks(): array
    {
        return [
            ['category' => '基础页面', 'name' => '首页', 'url' => '/pages/index/index', 'sort' => 804],
            ['category' => '基础页面', 'name' => '分类', 'url' => '/pages/category/index', 'sort' => 803],
            ['category' => '基础页面', 'name' => '购物车', 'url' => '/pages/cart/index', 'sort' => 802],
            ['category' => '基础页面', 'name' => '我的', 'url' => '/pages/user/index', 'sort' => 801],
            ['category' => 'DIY页面', 'name' => 'DIY', 'url' => '/pages/diy/placeholder/index', 'sort' => 701],
            ['category' => '直播页面', 'name' => '直播列表', 'url' => '/pages/lives/live/index', 'sort' => 602],
            ['category' => '直播页面', 'name' => '直播间', 'url' => '/pages/lives/room/index', 'sort' => 601],
            ['category' => '用户中心', 'name' => '登录', 'url' => '/pages/users/login/index', 'sort' => 517],
            ['category' => '用户中心', 'name' => '我的足迹', 'url' => '/pages/users/history/index', 'sort' => 516],
            ['category' => '用户中心', 'name' => '我的收藏', 'url' => '/pages/users/collect/index', 'sort' => 515],
            ['category' => '用户中心', 'name' => '我的优惠券', 'url' => '/pages/users/usecoupon/index', 'sort' => 514],
            ['category' => '用户中心', 'name' => '积分资产', 'url' => '/pages/users/profile/index', 'sort' => 513],
            ['category' => '用户中心', 'name' => '在线客服', 'url' => '/pages/users/customer/index', 'sort' => 512],
            ['category' => '用户中心', 'name' => '个人设置', 'url' => '/pages/users/userinfo/index', 'sort' => 511],
            ['category' => '用户中心', 'name' => '修改手机号', 'url' => '/pages/users/phoneedit/index', 'sort' => 510],
            ['category' => '用户中心', 'name' => '收货地址', 'url' => '/pages/users/address/list/index', 'sort' => 509],
            ['category' => '用户中心', 'name' => '添加地址', 'url' => '/pages/users/address/add/index', 'sort' => 508],
            ['category' => '用户中心', 'name' => '添加银行卡', 'url' => '/pages/users/card/index', 'sort' => 507],
            ['category' => '用户中心', 'name' => '佣金管理', 'url' => '/pages/users/commission/index', 'sort' => 506],
            ['category' => '用户中心', 'name' => '佣金提现', 'url' => '/pages/users/withdraw/index', 'sort' => 505],
            ['category' => '用户中心', 'name' => '资产', 'url' => '/pages/users/balance/index', 'sort' => 504],
            ['category' => '用户中心', 'name' => '余额充值', 'url' => '/pages/users/recharge/index', 'sort' => 503],
            ['category' => '用户中心', 'name' => '会员中心', 'url' => '/pages/users/member/index', 'sort' => 502],
            ['category' => '用户中心', 'name' => '会员协议', 'url' => '/pages/users/member/agreement', 'sort' => 501],
            ['category' => '用户中心', 'name' => '关于我们', 'url' => '/pages/users/about/index', 'sort' => 500],
            ['category' => '订单交易', 'name' => '确认订单', 'url' => '/pages/orders/confirm/index', 'sort' => 406],
            ['category' => '订单交易', 'name' => '收银台', 'url' => '/pages/orders/pay/index', 'sort' => 405],
            ['category' => '订单交易', 'name' => '申请退款', 'url' => '/pages/orders/refund/index', 'sort' => 404],
            ['category' => '订单交易', 'name' => '订单评价', 'url' => '/pages/orders/review/index', 'sort' => 403],
            ['category' => '订单交易', 'name' => '我的订单', 'url' => '/pages/orders/order/list/index', 'sort' => 402],
            ['category' => '订单交易', 'name' => '订单详情', 'url' => '/pages/orders/order/detail/index', 'sort' => 401],
            ['category' => '商户页面', 'name' => '商户入驻', 'url' => '/pages/merchant/settled/index', 'sort' => 304],
            ['category' => '商户页面', 'name' => '商家中心', 'url' => '/pages/merchant/index/index', 'sort' => 303],
            ['category' => '商户页面', 'name' => '商家店铺', 'url' => '/pages/merchant/user/index', 'sort' => 302],
            ['category' => '商户页面', 'name' => '商户专题列表', 'url' => '/pages/merchant/special/index', 'sort' => 301],
            ['category' => '营销活动', 'name' => '领券中心', 'url' => '/pages/activity/coupon/index', 'sort' => 202],
            ['category' => '营销活动', 'name' => '每日签到', 'url' => '/pages/activity/signin/index', 'sort' => 201],
            ['category' => '内容页面', 'name' => '搜索', 'url' => '/pages/contents/search/index', 'sort' => 108],
            ['category' => '内容页面', 'name' => '专题列表', 'url' => '/pages/contents/special/index', 'sort' => 107],
            ['category' => '内容页面', 'name' => '专题详情', 'url' => '/pages/contents/special/detail/index', 'sort' => 106],
            ['category' => '内容页面', 'name' => '内容详情', 'url' => '/pages/contents/content/detail/index', 'sort' => 105],
            ['category' => '内容页面', 'name' => '内容列表', 'url' => '/pages/contents/content/list/index', 'sort' => 104],
            ['category' => '内容页面', 'name' => '视频详情', 'url' => '/pages/contents/content/vid/index', 'sort' => 103],
            ['category' => '内容页面', 'name' => '音频详情', 'url' => '/pages/contents/content/aud/index', 'sort' => 102],
            ['category' => '内容页面', 'name' => '小说详情', 'url' => '/pages/contents/content/novel/index', 'sort' => 101],
        ];
    }
}
