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

namespace app\common\service\diy;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\DiyComponentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\diy\DiyPage;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ContentDisplayConfigService;
use app\common\service\user\UserMemberService;

/**
 * DIY页面服务
 */
class DiyPageService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 个人中心页面类型
     */
    public const PAGE_TYPE_USER_CENTER = 'user_center';

    /**
     * 订单入口默认图片目录
     */
    private const USER_CENTER_ORDER_IMAGE_BASE = '/admin/static/img/diy/user-center/';

    /**
     * @var array<int, string> 需要移动端按需加载的组件类型
     */
    private const LAZY_COMPONENT_TYPES = [
        DiyComponentTypeEnum::CONTENT,
        DiyComponentTypeEnum::SPECIAL,
        DiyComponentTypeEnum::LIVE,
        DiyComponentTypeEnum::COUPON,
        DiyComponentTypeEnum::PRODUCT,
        DiyComponentTypeEnum::SECKILL,
    ];

    /**
     * 设置模型
     *
     * @return string 当前服务对应的模型类名。
     */
    protected function setModel(): string
    {
        return DiyPage::class;
    }

    /**
     * 获取默认个人中心配置。
     *
     * @return array 个人中心服务菜单、订单菜单和广告默认配置。
     */
    protected function getDefaultUserCenterConfig(): array
    {
        return [
            'service_menu' => [
                'title' => '必备服务',
                'columns' => 4,
                'items' => [
                    ['name' => '我的收藏', 'image' => '', 'link_url' => '/pages/users/collect/index'],
                    ['name' => '收货地址', 'image' => '', 'link_url' => '/pages/users/address/list/index'],
                    ['name' => '在线客服', 'image' => '', 'link_url' => '/pages/users/customer/index'],
                    ['name' => '我的钱包', 'image' => '', 'link_url' => '/pages/users/balance/index'],
                    ['name' => '领券中心', 'image' => '', 'link_url' => '/pages/users/usecoupon/index'],
                    ['name' => '关于我们', 'image' => '', 'link_url' => '/pages/users/about/index'],
                ],
            ],
            'order_menu' => [
                'title' => '我的订单',
                'items' => [
                    [
                        'key' => 'pending-payment',
                        'name' => '待付款',
                        'icon' => '',
                        'image' => '',
                    ],
                    [
                        'key' => 'pending-shipment',
                        'name' => '待发货',
                        'icon' => '',
                        'image' => '',
                    ],
                    [
                        'key' => 'pending-receipt',
                        'name' => '待收货',
                        'icon' => '',
                        'image' => '',
                    ],
                    [
                        'key' => 'pending-review',
                        'name' => '待评价',
                        'icon' => '',
                        'image' => '',
                    ],
                    [
                        'key' => 'after-sale',
                        'name' => '售后',
                        'icon' => '',
                        'image' => '',
                    ],
                ],
            ],
            'banner' => [
                'image' => '',
                'title' => '',
                'desc' => '',
                'link_url' => '',
            ],
        ];
    }

    /**
     * 获取或创建默认个人中心装修页。
     *
     * @return array 已存在或新创建的默认个人中心装修页。
     */
    public function getOrCreateUserCenterPage(): array
    {
        $page = $this->getModel()
            ->where('page_type', self::PAGE_TYPE_USER_CENTER)
            ->where('is_default', 1)
            ->with(['components'])
            ->find();

        if (!$page) {
            $pageId = $this->createPage([
                'page_name' => '个人中心默认装修',
                'page_type' => self::PAGE_TYPE_USER_CENTER,
                'page_title' => '个人中心',
                'global_config' => [],
                'is_default' => 1,
                'status' => 1,
            ]);
            $defaultConfig = $this->getDefaultUserCenterConfig();
            app()->make(DiyComponentService::class)->saveAllComponents($pageId, [
                [
                    'component_type' => DiyComponentTypeEnum::USER_SERVICE_MENU,
                    'component_name' => '必备服务',
                    'sort' => 0,
                    'config' => $defaultConfig['service_menu'],
                    'status' => 1,
                ],
                [
                    'component_type' => DiyComponentTypeEnum::USER_ORDER_MENU,
                    'component_name' => '我的订单',
                    'sort' => 1,
                    'config' => $defaultConfig['order_menu'],
                    'status' => 1,
                ],
                [
                    'component_type' => DiyComponentTypeEnum::USER_BANNER,
                    'component_name' => '个人中心广告',
                    'sort' => 2,
                    'config' => $defaultConfig['banner'],
                    'status' => 1,
                ],
            ]);

            $page = $this->get($pageId, ['*'], ['components']);
        }

        $data = $page->toArray();
        $data['components'] = $data['components'] ?? [];

        return $data;
    }

    /**
     * 获取管理端个人中心装修详情。
     *
     * @return array 供后台编辑的个人中心装修页和组件详情。
     */
    public function getUserCenterDecorateDetail(): array
    {
        $page = $this->getOrCreateUserCenterPage();
        $config = $this->extractUserCenterConfig($page['components'] ?? []);

        return [
            'page' => [
                'id' => (int)($page['id'] ?? 0),
                'page_name' => (string)($page['page_name'] ?? ''),
                'page_title' => (string)($page['page_title'] ?? ''),
                'page_type' => (string)($page['page_type'] ?? self::PAGE_TYPE_USER_CENTER),
                'status' => (int)($page['status'] ?? 1),
                'global_config' => is_array($page['global_config'] ?? null) ? $page['global_config'] : [],
            ],
            'service_menu' => $config['service_menu'],
            'order_menu' => $config['order_menu'],
            'banner' => $config['banner'],
        ];
    }

    /**
     * 保存个人中心装修配置。
     *
     * @param array $data 装修页面资料及组件配置。
     * @return array 保存后的个人中心装修页详情。
     */
    public function saveUserCenterDecorate(array $data): array
    {
        $page = $this->getOrCreateUserCenterPage();
        $pageId = (int)$page['id'];
        $serviceMenu = $this->normalizeUserServiceMenuConfig($data['service_menu'] ?? []);
        $orderMenu = $this->normalizeUserOrderMenuConfig($data['order_menu'] ?? []);
        $banner = $this->normalizeUserBannerConfig($data['banner'] ?? []);

        $this->transaction(function () use ($pageId, $data, $serviceMenu, $orderMenu, $banner) {
            $this->update($pageId, [
                'page_name' => (string)($data['page_name'] ?? '个人中心默认装修'),
                'page_title' => (string)($data['page_title'] ?? '个人中心'),
                'status' => (int)($data['status'] ?? 1),
                'global_config' => is_array($data['global_config'] ?? null) ? $data['global_config'] : [],
            ]);

            app()->make(DiyComponentService::class)->saveAllComponents($pageId, [
                [
                    'component_type' => DiyComponentTypeEnum::USER_SERVICE_MENU,
                    'component_name' => '必备服务',
                    'sort' => 0,
                    'config' => $serviceMenu,
                    'status' => 1,
                ],
                [
                    'component_type' => DiyComponentTypeEnum::USER_ORDER_MENU,
                    'component_name' => '我的订单',
                    'sort' => 1,
                    'config' => $orderMenu,
                    'status' => 1,
                ],
                [
                    'component_type' => DiyComponentTypeEnum::USER_BANNER,
                    'component_name' => '个人中心广告',
                    'sort' => 2,
                    'config' => $banner,
                    'status' => 1,
                ],
            ]);

            return true;
        });

        return $this->getUserCenterDecorateDetail();
    }

    /**
     * 获取移动端个人中心装修配置。
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 个人中心菜单、广告配置和功能状态，供客户端按开关决定展示。
     */
    public function getMobileUserCenterConfig(int $userId = 0): array
    {
        $pointsSwitches = $this->getMobilePointsSwitches();
        $memberState = app()->make(UserMemberService::class)->getMemberRuntimeState($userId);
        $memberEnabled = (bool)($memberState['member_enabled'] ?? false);
        $memberVisible = (bool)($memberState['member_visible'] ?? false);
        $page = $this->getModel()
            ->where('page_type', self::PAGE_TYPE_USER_CENTER)
            ->where('is_default', 1)
            ->where('status', 1)
            ->with(['components' => function ($query) {
                $query->where('status', 1)->order('sort', 'asc');
            }])
            ->find();

        if (!$page) {
            $defaultConfig = $this->getDefaultUserCenterConfig();
            return [
                'page' => null,
                'service_menus' => $this->formatMobileUserServiceMenus($defaultConfig['service_menu']),
                'service_menus_title' => $defaultConfig['service_menu']['title'] ?? '我的服务',
                'order_menus' => $this->formatMobileUserOrderMenus($defaultConfig['order_menu']),
                'order_menu_title' => $defaultConfig['order_menu']['title'] ?? '我的订单',
                'banner' => $this->formatMobileUserBanner($defaultConfig['banner']),
                'points_switch' => $pointsSwitches['points_switch'],
                'activity_signin_switch' => $pointsSwitches['activity_signin_switch'],
                'points_enabled' => $pointsSwitches['points_enabled'],
                'activity_signin_enabled' => $pointsSwitches['activity_signin_enabled'],
                'member_switch' => $memberEnabled ? 1 : 0,
                'member_enabled' => $memberEnabled,
                'member_visible' => $memberVisible,
                'can_purchase' => (bool)($memberState['can_purchase'] ?? false),
                'can_renew' => (bool)($memberState['can_renew'] ?? false),
                'points_config' => $pointsSwitches,
            ];
        }

        $pageData = $page->toArray();
        $config = $this->extractUserCenterConfig($pageData['components'] ?? []);

        return [
            'page' => [
                'id' => (int)($pageData['id'] ?? 0),
                'page_name' => (string)($pageData['page_name'] ?? ''),
                'page_title' => (string)($pageData['page_title'] ?? ''),
            ],
            'service_menus' => $this->formatMobileUserServiceMenus($config['service_menu']),
            'service_menus_title' => $config['service_menu']['title'] ?? '我的服务',
            'order_menus' => $this->formatMobileUserOrderMenus($config['order_menu']),
            'order_menu_title' => $config['order_menu']['title'] ?? '我的订单',
            'banner' => $this->formatMobileUserBanner($config['banner']),
            'points_switch' => $pointsSwitches['points_switch'],
            'activity_signin_switch' => $pointsSwitches['activity_signin_switch'],
            'points_enabled' => $pointsSwitches['points_enabled'],
            'activity_signin_enabled' => $pointsSwitches['activity_signin_enabled'],
            'member_switch' => $memberEnabled ? 1 : 0,
            'member_enabled' => $memberEnabled,
            'member_visible' => $memberVisible,
            'can_purchase' => (bool)($memberState['can_purchase'] ?? false),
            'can_renew' => (bool)($memberState['can_renew'] ?? false),
            'points_config' => $pointsSwitches,
        ];
    }

    /**
     * 获取移动端个人中心需要展示的积分开关状态。
     *
     * @return array 个人中心展示所需的积分功能开关。
     */
    protected function getMobilePointsSwitches(): array
    {
        $pointsEnabled = (int)db_config(ConfigKeyEnum::POINTS_ENABLED, '1') === 1;
        $signinConfigEnabled = (int)db_config(ConfigKeyEnum::POINTS_ACTIVITY_SIGNIN_ENABLED, '1') === 1;
        $signinEnabled = $pointsEnabled && $signinConfigEnabled;

        return [
            'points_switch' => $pointsEnabled ? 1 : 0,
            'activity_signin_switch' => $signinEnabled ? 1 : 0,
            'points_enabled' => $pointsEnabled,
            'activity_signin_config_enabled' => $signinConfigEnabled,
            'activity_signin_enabled' => $signinEnabled,
        ];
    }

    /**
     * 从组件列表提取个人中心配置。
     *
     * @param array $components 装修页面的组件及配置列表。
     * @return array 从组件中提取并补齐默认值的个人中心配置。
     */
    protected function extractUserCenterConfig(array $components): array
    {
        $defaultConfig = $this->getDefaultUserCenterConfig();
        $config = $defaultConfig;

        foreach ($components as $component) {
            $type = (string)($component['component_type'] ?? '');
            $componentConfig = is_array($component['config'] ?? null) ? $component['config'] : [];
            if ($type === DiyComponentTypeEnum::USER_SERVICE_MENU) {
                $config['service_menu'] = $this->normalizeUserServiceMenuConfig($componentConfig);
            }
            if ($type === DiyComponentTypeEnum::USER_ORDER_MENU) {
                $config['order_menu'] = $this->normalizeUserOrderMenuConfig($componentConfig);
            }
            if ($type === DiyComponentTypeEnum::USER_BANNER) {
                $config['banner'] = $this->normalizeUserBannerConfig($componentConfig);
            }
        }

        return $config;
    }

    /**
     * 规范化个人中心服务菜单配置。
     *
     * @param array $config 个人中心服务菜单的开关、名称、图标及跳转配置。
     * @return array 规范化后的服务菜单配置。
     */
    protected function normalizeUserServiceMenuConfig(array $config): array
    {
        $defaultConfig = $this->getDefaultUserCenterConfig()['service_menu'];
        $items = is_array($config['items'] ?? null) ? $config['items'] : $defaultConfig['items'];

        return [
            'title' => (string)($config['title'] ?? $defaultConfig['title']),
            'columns' => in_array((int)($config['columns'] ?? 4), [3, 4, 5], true) ? (int)($config['columns'] ?? 4) : 4,
            'items' => array_values(array_map(function ($item) {
                return [
                    'name' => (string)($item['name'] ?? $item['text'] ?? ''),
                    'image' => (string)($item['image'] ?? $item['icon'] ?? ''),
                    'link_type' => (string)($item['link_type'] ?? ''),
                    'link_url' => (string)($item['link_url'] ?? $item['url'] ?? ''),
                    'link_id' => (int)($item['link_id'] ?? 0),
                    'link_name' => (string)($item['link_name'] ?? ''),
                    'platform_type' => (string)($item['platform_type'] ?? ''),
                ];
            }, $items)),
        ];
    }

    /**
     * 规范化个人中心订单菜单配置。
     *
     * @param array $config 个人中心订单菜单的开关、名称和图标配置。
     * @return array 规范化后的订单菜单配置。
     */
    protected function normalizeUserOrderMenuConfig(array $config): array
    {
        $defaultConfig = $this->getDefaultUserCenterConfig()['order_menu'];
        $items = is_array($config['items'] ?? null) ? array_values($config['items']) : [];
        $itemsByKey = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = (string)($item['key'] ?? '');
            if ($key !== '') {
                $itemsByKey[$key] = $item;
            }
        }

        $normalizedItems = [];
        foreach ($defaultConfig['items'] as $index => $defaultItem) {
            $customItem = $itemsByKey[$defaultItem['key']] ?? ($items[$index] ?? []);
            if (!is_array($customItem)) {
                $customItem = [];
            }

            $name = trim((string)($customItem['name'] ?? $customItem['text'] ?? $customItem['title'] ?? $defaultItem['name']));
            $icon = trim((string)($customItem['icon'] ?? ''));
            $image = trim((string)($customItem['image'] ?? ''));
            if ($icon === '' && $this->looksLikeIconClass($image)) {
                $icon = $image;
                $image = '';
            }
            if ($this->isDefaultUserCenterOrderImage($image)) {
                $image = '';
            }
            if ($image === '') {
                $image = (string)($defaultItem['image'] ?? '');
            }

            $normalizedItems[] = [
                'key' => (string)$defaultItem['key'],
                'name' => $name !== '' ? $name : (string)$defaultItem['name'],
                'icon' => $icon,
                'image' => $image,
            ];
        }

        $title = trim((string)($config['title'] ?? $defaultConfig['title']));

        return [
            'title' => $title !== '' ? $title : $defaultConfig['title'],
            'items' => $normalizedItems,
        ];
    }

    /**
     * 规范化个人中心广告配置。
     *
     * @param array $config 个人中心广告的开关、图片及跳转配置。
     * @return array 规范化后的个人中心广告配置。
     */
    protected function normalizeUserBannerConfig(array $config): array
    {
        $defaultConfig = $this->getDefaultUserCenterConfig()['banner'];

        return [
            'image' => (string)($config['image'] ?? $defaultConfig['image']),
            'title' => (string)($config['title'] ?? $defaultConfig['title']),
            'desc' => (string)($config['desc'] ?? $defaultConfig['desc']),
            'link_type' => (string)($config['link_type'] ?? ''),
            'link_url' => (string)($config['link_url'] ?? ''),
            'link_id' => (int)($config['link_id'] ?? 0),
            'link_name' => (string)($config['link_name'] ?? ''),
            'platform_type' => (string)($config['platform_type'] ?? ''),
        ];
    }

    /**
     * 格式化移动端服务菜单。
     *
     * @param array $config 当前装修组件的原始展示配置。
     * @return array 附带可用图片或图标的服务菜单列表。
     */
    protected function formatMobileUserServiceMenus(array $config): array
    {
        return array_values(array_filter(array_map(function ($item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') {
                return null;
            }

            return [
                'key' => md5($name . ($item['link_url'] ?? '')),
                'text' => $name,
                'image' => (string)($item['image'] ?? ''),
                'url' => (string)($item['link_url'] ?? ''),
            ];
        }, $config['items'] ?? [])));
    }

    /**
     * 格式化移动端订单菜单。
     *
     * @param array $config 当前装修组件的原始展示配置。
     * @return array 附带移动端图片地址的订单菜单列表。
     */
    protected function formatMobileUserOrderMenus(array $config): array
    {
        return array_values(array_map(function ($item) {
            return [
                'key' => (string)($item['key'] ?? ''),
                'text' => (string)($item['name'] ?? $item['text'] ?? ''),
                'icon' => (string)($item['icon'] ?? ''),
                'image' => $this->formatMobileImageUrl((string)($item['image'] ?? '')),
            ];
        }, $config['items'] ?? []));
    }

    /**
     * 格式化移动端图片地址。
     *
     * @param string $image 原始图片地址或默认图路径。
     * @return string 适合移动端访问的图片地址。
     */
    protected function formatMobileImageUrl(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\/\//i', $image) || str_starts_with($image, 'data:')) {
            return $image;
        }

        return get_image_url($image);
    }

    /**
     * 判断是否为图标类名。
     *
     * @param string $value 需要校验或规范化的原始输入值。
     * @return bool 输入是否为受支持的图标类名。
     */
    protected function looksLikeIconClass(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return (bool)preg_match('/^(el-icon-|icon[-_])[A-Za-z0-9_-]+$/', $value);
    }

    /**
     * 判断是否为后台个人中心订单默认图。
     *
     * @param string $image 原始图片地址或默认图路径。
     * @return bool 地址是否为后台默认的个人中心订单图片。
     */
    protected function isDefaultUserCenterOrderImage(string $image): bool
    {
        $image = trim($image);
        if ($image === '') {
            return false;
        }

        $image = preg_replace('/[?#].*$/', '', $image) ?: '';

        return str_contains($image, self::USER_CENTER_ORDER_IMAGE_BASE);
    }

    /**
     * 格式化移动端广告配置。
     *
     * @param array $config 当前装修组件的原始展示配置。
     * @return array 适合移动端直接展示的广告配置。
     */
    protected function formatMobileUserBanner(array $config): array
    {
        return [
            'image' => (string)($config['image'] ?? ''),
            'title' => (string)($config['title'] ?? ''),
            'desc' => (string)($config['desc'] ?? ''),
            'url' => (string)($config['link_url'] ?? ''),
        ];
    }

    /**
     * 获取默认首页数据
     *
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param bool $aggregate 是否预聚合按需组件；关闭时仅为其返回骨架，不关闭其他组件聚合。
     * @return array 默认首页及按需聚合后的组件数据。
     */
    public function getDefaultHomePage(int $userId = 0, bool $aggregate = false): array
    {
        $page = $this->getModel()
            ->where('page_type', 'home')
            ->where('is_default', 1)
            ->where('status', 1)
            ->with(['components' => function ($query) {
                $query->where('status', 1)->order('sort', 'asc');
            }])
            ->find();

        if (!$page) {
            return [
                'page' => null,
                'components' => [],
            ];
        }

        return $this->formatMobilePageData($page->toArray(), $aggregate, $userId);
    }

    /**
     * 获取移动端可直接消费的页面数据
     *
     * @param int $id 装修页面标识。
     * @param bool $aggregate 是否预聚合按需组件；关闭时仅为其返回骨架，不关闭其他组件聚合。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 移动端装修页面资料及组件数据。
     */
    public function getMobilePageDetail(int $id, bool $aggregate = true, int $userId = 0): array
    {
        $page = $this->get($id, ['*'], ['components']);
        if (!$page) {
            throw new AdminResponseException('页面不存在');
        }

        return $this->formatMobilePageData($page->toArray(), $aggregate, $userId);
    }

    /**
     * 格式化移动端页面数据
     *
     * @param array $pageData 待转换为移动端响应的页面数据。
     * @param bool $aggregate 是否预聚合按需组件；关闭时仅为其返回骨架，不关闭其他组件聚合。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @return array 已处理功能开关、组件骨架及聚合数据的页面。
     */
    protected function formatMobilePageData(array $pageData, bool $aggregate = true, int $userId = 0): array
    {
        $aggregateService = app()->make(DiyDataAggregateService::class);
        $displayConfig = app()->make(ContentDisplayConfigService::class);
        $components = [];

        foreach ($pageData['components'] ?? [] as $component) {
            $componentType = (string)($component['component_type'] ?? $component['type'] ?? '');
            if ($componentType !== '' && !$displayConfig->isDiyComponentEnabled($componentType)) {
                continue;
            }

            $componentName = (string)($component['component_name'] ?? $component['name'] ?? '');
            $config = is_array($component['config'] ?? null) ? $component['config'] : [];
            $componentData = [];

            if ($componentType !== '' && !empty($config) && ($aggregate || !$this->isLazyComponentType($componentType))) {
                if ($aggregate && $this->isLoadMoreComponentConfig($componentType, $config)) {
                    $componentData = $aggregateService->aggregateLazy(
                        $componentType,
                        $config,
                        $userId,
                        0,
                        1,
                        $this->getLoadMoreComponentLimit($componentType, $config)
                    );
                } else {
                    $componentData = $aggregateService->aggregate($componentType, $config, $userId);
                }
            } elseif ($componentType !== '' && !empty($config) && $this->isLazyComponentType($componentType)) {
                $componentData = $aggregateService->aggregateSkeleton($componentType, $config);
            } elseif (is_array($component['data'] ?? null)) {
                $componentData = $component['data'];
            }

            $components[] = [
                'id' => (int)($component['id'] ?? 0),
                'component_type' => $componentType,
                'type' => $componentType,
                'component_name' => $componentName,
                'name' => $componentName,
                'config' => $config,
                'data' => is_array($componentData) ? $componentData : [],
            ];
        }

        return [
            'page' => [
                'id' => (int)($pageData['id'] ?? 0),
                'page_name' => (string)($pageData['page_name'] ?? ''),
                'page_title' => (string)($pageData['page_title'] ?? ''),
                'global_config' => is_array($pageData['global_config'] ?? null) ? $pageData['global_config'] : [],
            ],
            'components' => $components,
        ];
    }

    /**
     * 获取移动端单个 DIY 组件的按需数据。
     *
     * @param int $componentId 装修组件记录标识。
     * @param int $userId 当前用户标识，匿名访问时为零。
     * @param int $categoryId 当前选择的分类标识，未选择时按模块默认范围查询。
     * @param int $pageNo 当前页码，从第一页开始。
     * @param int $limit 单组件按需请求的每页记录数。
     * @return array 指定组件的按需数据及可选分页信息。
     */
    public function getMobileComponentData(
        int $componentId,
        int $userId = 0,
        int $categoryId = 0,
        int $pageNo = 1,
        int $limit = 0
    ): array
    {
        $component = app()->make(DiyComponentService::class)->get($componentId);
        if (!$component) {
            throw new AdminResponseException('组件不存在');
        }

        $componentData = $component->toArray();
        if ((int)($componentData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('组件已禁用');
        }

        $pageId = (int)($componentData['diy_page_id'] ?? 0);
        $pageModel = $pageId > 0 ? $this->get($pageId) : null;
        if (!$pageModel || (int)($pageModel['status'] ?? 0) !== 1) {
            throw new AdminResponseException('页面不存在或已禁用');
        }

        $componentType = (string)($componentData['component_type'] ?? '');
        $config = is_array($componentData['config'] ?? null) ? $componentData['config'] : [];
        if ($componentType === '' || !$config) {
            return [];
        }
        if (!app()->make(ContentDisplayConfigService::class)->isDiyComponentEnabled($componentType)) {
            return [];
        }

        return app()->make(DiyDataAggregateService::class)->aggregateLazy(
            $componentType,
            $config,
            $userId,
            $categoryId,
            $pageNo,
            $limit
        );
    }

    /**
     * 判断是否为移动端按需加载组件。
     *
     * @param string $componentType 装修组件类型标识。
     * @return bool 组件是否采用移动端按需加载。
     */
    private function isLazyComponentType(string $componentType): bool
    {
        return in_array($componentType, self::LAZY_COMPONENT_TYPES, true);
    }

    /**
     * 判断组件是否需要以分页结构返回数据。
     *
     * @param string $componentType 装修组件类型标识。
     * @param array $config 组件类型对应的触底加载开关配置。
     * @return bool 组件是否启用分页触底加载。
     */
    private function isLoadMoreComponentConfig(string $componentType, array $config): bool
    {
        return in_array($componentType, [
            DiyComponentTypeEnum::CONTENT,
            DiyComponentTypeEnum::SPECIAL,
            DiyComponentTypeEnum::PRODUCT,
        ], true) && (bool)($config['load_more'] ?? false);
    }

    /**
     * 获取触底加载组件的每页数量。
     *
     * @param string $componentType 装修组件类型标识。
     * @param array $config 组件类型对应的展示数量和触底加载配置。
     * @return int 触底加载组件的有界分页数量。
     */
    private function getLoadMoreComponentLimit(string $componentType, array $config): int
    {
        $fallback = $componentType === DiyComponentTypeEnum::CONTENT ? 3 : 6;

        return max(1, min(50, (int)($config['show_count'] ?? $fallback)));
    }

    /**
     * 设置为默认页面
     *
     * @param int $id 装修页面标识。
     * @return bool 页面是否成功设置为默认页。
     */
    public function setDefault(int $id): bool
    {
        $page = $this->get($id);
        if (!$page) {
            throw new AdminResponseException('页面不存在');
        }

        if (!$page['status']) {
            throw new AdminResponseException('页面已禁用，无法设置为默认');
        }

        return $this->transaction(function () use ($page) {
            // 取消同类型其他页面的默认状态
            $this->getModel()
                ->where('page_type', $page['page_type'])
                ->where('id', '<>', $page['id'])
                ->update(['is_default' => 0]);

            // 设置当前页面为默认
            return $this->update($page['id'], ['is_default' => 1]);
        });
    }

    /**
     * 获取页面详情（含组件）
     *
     * @param int $id 装修页面标识。
     * @return array 装修页面及按顺序排列的组件详情。
     */
    public function getDetailWithComponents(int $id): array
    {
        $page = $this->get($id, ['*'], ['components']);
        if (!$page) {
            throw new AdminResponseException('页面不存在');
        }

        $data = $page->toArray();
        $data['components'] = $data['components'] ?? [];

        return $data;
    }

    /**
     * 创建页面
     *
     * @param array $data 装修页面主表资料，不包含组件保存操作。
     * @return int 新创建的装修页面标识。
     */
    public function createPage(array $data): int
    {
        return $this->transaction(function () use ($data) {
            $page = $this->create($data);
            return $page->id;
        });
    }

    /**
     * 更新页面
     *
     * @param int $id 装修页面标识。
     * @param array $data 待更新的装修页面主表字段。
     * @return bool 页面是否更新成功。
     */
    public function updatePage(int $id, array $data): bool
    {
        return $this->transaction(function () use ($id, $data) {
            return $this->update($id, $data);
        });
    }

    /**
     * 删除页面（同时删除关联组件）
     *
     * @param int $id 装修页面标识。
     * @return bool 页面及关联组件是否删除成功。
     */
    public function deletePage(int $id): bool
    {
        return $this->transaction(function () use ($id) {
            // 删除关联组件
            $componentService = app()->make(DiyComponentService::class);
            $componentService->deleteByPageId($id);

            // 删除页面
            return $this->destroy($id);
        });
    }

    /**
     * 获取页面下拉选项
     *
     * @param string $pageType 需要列出的装修页面类型。
     * @return array 指定页面类型的下拉选择项。
     */
    public function getPageOptions(string $pageType = 'home'): array
    {
        return $this->getModel()
            ->where('page_type', $pageType)
            ->where('status', 1)
            ->field('id, page_name')
            ->select()
            ->toArray();
    }
}
