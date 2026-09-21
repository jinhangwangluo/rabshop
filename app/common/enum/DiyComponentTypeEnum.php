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

/**
 * DIY组件类型枚举
 */
class DiyComponentTypeEnum
{
    // ==================== 组件分类 ====================
    /**
     * 基础组件
     */
    public const CATEGORY_BASIC = 'basic';

    /**
     * 营销组件
     */
    public const CATEGORY_MARKETING = 'marketing';

    /**
     * 工具组件
     */
    public const CATEGORY_TOOL = 'tool';

    // ==================== 组件类型 ====================
    /**
     * 搜索框组件
     */
    public const SEARCH = 'search';

    /**
     * 轮播图组件
     */
    public const BANNER = 'banner';

    /**
     * 导航组件
     */
    public const NAVIGATION = 'navigation';

    /**
     * 公告组件
     */
    public const NOTICE = 'notice';

    /**
     * 图片魔方组件
     */
    public const IMAGE_CUBE = 'image_cube';

    /**
     * 直播组件
     */
    public const LIVE = 'live';

    /**
     * 内容组件
     */
    public const CONTENT = 'content';

    /**
     * 专题组件
     */
    public const SPECIAL = 'special';

    /**
     * 商品组件
     */
    public const PRODUCT = 'product';

    /**
     * 插件秒杀组件
     */
    public const SECKILL = 'seckill';

    /**
     * 优惠券组件
     */
    public const COUPON = 'coupon';

    /**
     * 热区组件
     */
    public const HOT_ZONE = 'hot_zone';

    /**
     * 标题组件
     */
    public const TITLE = 'title';

    /**
     * 富文本组件
     */
    public const RICH_TEXT = 'rich_text';

    /**
     * 空白组件
     */
    public const BLANK = 'blank';

    /**
     * 个人中心必备服务菜单
     */
    public const USER_SERVICE_MENU = 'user_service_menu';

    /**
     * 个人中心订单菜单
     */
    public const USER_ORDER_MENU = 'user_order_menu';

    /**
     * 个人中心广告
     */
    public const USER_BANNER = 'user_banner';

    // ==================== 分类映射 ====================
    /**
     * 类型名称映射
     *
     * @return array 装修组件类型与中文名称的映射。
     */
    private static function typeNames(): array
    {
        return [
            self::SEARCH     => '搜索框',
            self::BANNER     => '轮播图',
            self::NAVIGATION => '导航组',
            self::NOTICE     => '新闻公告',
            self::IMAGE_CUBE => '图片魔方',
            self::LIVE       => '直播',
            self::CONTENT    => '内容',
            self::SPECIAL    => '专题',
            self::PRODUCT    => '商品',
            self::COUPON     => '优惠券',
            self::SECKILL    => '秒杀',
            self::HOT_ZONE   => '热区',
            self::TITLE      => '标题',
            self::RICH_TEXT  => '富文本',
            self::BLANK      => '空白'
        ];
    }

    /**
     * 类型分类映射
     *
     * @return array 装修组件类型与组件分类的映射。
     */
    private static function typeCategories(): array
    {
        return [
            // 基础组件
            self::SEARCH,
            self::BANNER,
            self::NAVIGATION,
            self::NOTICE,
            self::IMAGE_CUBE,
            self::HOT_ZONE,
            self::TITLE,
            self::PRODUCT,
            // 营销组件
            self::LIVE,
            self::CONTENT,
            self::SPECIAL,
            self::COUPON,
            self::SECKILL,
            // 工具组件
            self::RICH_TEXT,
            self::BLANK,
            self::USER_SERVICE_MENU,
            self::USER_ORDER_MENU,
            self::USER_BANNER,
        ];
    }

    /**
     * 分类信息
     *
     * @return array 组件分类名称、图标和排序配置。
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_BASIC     => '基础组件',
            self::CATEGORY_MARKETING => '营销组件',
            self::CATEGORY_TOOL      => '工具组件',
        ];
    }

    // ==================== 核心方法 ====================

    /**
     * 获取类型列表（兼容旧版）
     *
     * @deprecated 请使用 getListWithCategory()
     *
     * @return array 兼容旧编辑器的组件类型列表。
     */
    public static function list(): array
    {
        return self::typeNames();
    }

    /**
     * 获取带分类的类型列表（新版）
     *
     * @return array 附带分类信息的组件类型列表。
     */
    public static function getListWithCategory(): array
    {
        $names = self::typeNames();
        $result = [];
        foreach ($names as $type => $name) {
            $category = self::getCategory($type);
            $result[] = [
                'type'      => $type,
                'name'      => $name,
                'category'  => $category,
                'label'     => $name . '组件',
            ];
        }
        return $result;
    }

    /**
     * 获取按分类分组的类型列表
     *
     * @return array 按组件分类组织的类型列表。
     */
    public static function getGroupedByCategory(): array
    {
        $grouped = [];
        foreach (self::categories() as $catKey => $catName) {
            $grouped[$catKey] = [
                'key'   => $catKey,
                'name'  => $catName,
                'types' => [],
            ];
        }

        foreach (self::getListWithCategory() as $item) {
            $cat = $item['category'];
            if (isset($grouped[$cat])) {
                $grouped[$cat]['types'][] = [
                    'value' => $item['type'],
                    'label' => $item['name'],
                ];
            }
        }

        return array_values($grouped);
    }

    /**
     * 获取某个类型的分类
     *
     * @param string $type 装修组件类型标识。
     * @return string 指定组件类型的分类标识。
     */
    public static function getCategory(string $type): string
    {
        $basicTypes = [
            self::SEARCH,
            self::BANNER,
            self::NAVIGATION,
            self::NOTICE,
            self::IMAGE_CUBE,
            self::HOT_ZONE,
            self::TITLE,
            self::PRODUCT,
        ];

        $marketingTypes = [
            self::LIVE,
            self::CONTENT,
            self::SPECIAL,
            self::COUPON,
            self::SECKILL,
        ];

        if (in_array($type, $basicTypes, true)) {
            return self::CATEGORY_BASIC;
        }

        if (in_array($type, $marketingTypes, true)) {
            return self::CATEGORY_MARKETING;
        }

        return self::CATEGORY_TOOL;
    }

    /**
     * 获取类型名称
     *
     * @param string $type 装修组件类型标识。
     * @return string 指定组件类型的中文名称。
     */
    public static function getName(string $type): string
    {
        return self::typeNames()[$type] ?? '未知组件';
    }

    /**
     * 获取下拉选项列表（兼容旧版）
     *
     * @deprecated 请使用 getGroupedByCategory()
     *
     * @return array 用于下拉选择的装修组件类型和中文名称。
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::typeNames() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label . '组件',
            ];
        }
        return $options;
    }

    /**
     * 验证类型是否有效
     *
     * @param string $type 装修组件类型标识。
     * @return bool 指定组件类型是否在声明范围内。
     */
    public static function isValid(string $type): bool
    {
        return isset(self::typeNames()[$type]);
    }

    /**
     * 获取需要数据聚合的组件类型
     *
     * @return array 需要加载实际业务数据的组件类型集合。
     */
    public static function needAggregateTypes(): array
    {
        return [
            self::LIVE,
            self::CONTENT,
            self::SPECIAL,
            self::COUPON,
            self::SECKILL,
        ];
    }

    /**
     * 判断是否需要数据聚合
     *
     * @param string $type 装修组件类型标识。
     * @return bool 指定组件类型是否需要聚合业务数据。
     */
    public static function needAggregate(string $type): bool
    {
        return in_array($type, self::needAggregateTypes(), true);
    }
}
