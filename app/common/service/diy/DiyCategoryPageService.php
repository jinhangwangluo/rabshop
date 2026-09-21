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

use app\common\exception\AdminResponseException;
use app\common\model\diy\DiyPage;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\content\ContentDisplayConfigService;
use app\common\service\content\SpecialCategoryService;
use app\common\service\store\ProductCategoryService;

/**
 * DIY 分类页面配置服务
 */
class DiyCategoryPageService extends DiyPageService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    private const PAGE_TYPE = 'category_page';

    private const STYLE_OPTIONS = [
        [
            'value' => 'product_grid',
            'label' => '商品三级分类图',
            'description' => '使用商品三级分类图标展示分类',
        ],
        [
            'value' => 'product_tabs',
            'label' => '顶部选项卡 + 左侧二级',
            'description' => '顶部一级分类、左侧二级分类、右侧三级分类',
        ],
        [
            'value' => 'product_expand',
            'label' => '层级展开 + 商品展示',
            'description' => '按商品分类层级展开并展示商品',
        ],
        [
            'value' => 'special',
            'label' => '专题分类样式',
            'description' => '使用专题分类数据展示分类内容',
        ],
    ];

    /**
     * 获取当前配置。
     */
    public function getConfig(): array
    {
        $model = $this->getCategoryPageModel();
        if (!$model) {
            $model = $this->getModel()->create([
                'page_name' => '分类页面配置',
                'page_type' => self::PAGE_TYPE,
                'page_title' => '分类页面',
                'global_config' => [
                    'style_type' => 'product_grid',
                    'category_ids' => [],
                ],
                'is_default' => 1,
                'status' => 1,
            ]);
        }

        $config = is_array($model->global_config) ? $model->global_config : [];
        $styleType = (string)($config['style_type'] ?? 'product_grid');
        if ($styleType === 'special' && !app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            $styleType = 'product_grid';
            $config['style_type'] = $styleType;
            $config['category_ids'] = [];
        }

        return [
            'id' => (int)$model->id,
            'style_type' => $styleType,
            'config' => $config,
            'style_options' => $this->getAvailableStyleOptions(),
        ];
    }

    /**
     * 获取移动端分类页面轻量配置。
     */
    public function getMobileConfig(): array
    {
        $config = $this->getConfig();
        $globalConfig = is_array($config['config'] ?? null) ? $config['config'] : [];

        return [
            'style_type' => (string)($config['style_type'] ?? 'product_grid'),
            'config' => [
                'category_ids' => $this->normalizeIds($globalConfig['category_ids'] ?? []),
            ],
        ];
    }

    /**
     * 获取样式和分类选项。
     */
    public function getOptions(): array
    {
        return [
            'style_options' => $this->getAvailableStyleOptions(),
        ];
    }

    /**
     * 保存分类页面配置。
     */
    public function saveConfig(string $styleType, array $config): array
    {
        $styleType = trim($styleType);
        if (!$this->isValidStyleType($styleType)) {
            throw new AdminResponseException('分类页面样式不存在');
        }
        if ($styleType === 'special' && !app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            throw new AdminResponseException('专题功能已关闭，不能使用专题分类样式');
        }

        $categoryIds = $this->normalizeIds($config['category_ids'] ?? []);
        $this->validateCategoryIds($styleType, $categoryIds);

        $saveConfig = [
            'category_ids' => $categoryIds,
        ];

        $this->transaction(function () use ($styleType, $saveConfig) {
            $model = $this->getCategoryPageModel();
            if (!$model) {
                $this->getModel()->create([
                    'page_name' => '分类页面配置',
                    'page_type' => self::PAGE_TYPE,
                    'page_title' => '分类页面',
                    'global_config' => array_merge(['style_type' => $styleType], $saveConfig),
                    'is_default' => 1,
                    'status' => 1,
                ]);
                return;
            }

            $model->save([
                'global_config' => array_merge(['style_type' => $styleType], $saveConfig),
            ]);
        });

        return $this->getConfig();
    }

    /**
     * 获取当前可用的分类页样式。
     */
    protected function getAvailableStyleOptions(): array
    {
        if (app()->make(ContentDisplayConfigService::class)->isSpecialEnabled()) {
            return self::STYLE_OPTIONS;
        }

        return array_values(array_filter(
            self::STYLE_OPTIONS,
            static fn(array $option): bool => $option['value'] !== 'special'
        ));
    }

    protected function getCategoryPageModel(): ?DiyPage
    {
        $model = $this->getModel()
            ->where('page_type', self::PAGE_TYPE)
            ->order('id', 'asc')
            ->find();

        return $model instanceof DiyPage ? $model : null;
    }

    protected function isValidStyleType(string $styleType): bool
    {
        foreach (self::STYLE_OPTIONS as $option) {
            if ($option['value'] === $styleType) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }

    protected function validateCategoryIds(string $styleType, array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        if ($styleType === 'special') {
            $service = app()->make(SpecialCategoryService::class);
            $model = $service->getModel();
            $count = $model->whereIn('id', $categoryIds)
                ->where(['type' => $service::SPECIAL_TYPE, 'owner_id' => 0, 'status' => 1])
                ->count();
            if ($count !== count($categoryIds)) {
                throw new AdminResponseException('存在无效的专题分类');
            }
            return;
        }

        $service = app()->make(ProductCategoryService::class);
        $model = $service->getModel();
        $categories = $model->whereIn('id', $categoryIds)
            ->where(['type' => $service::PRODUCT_TYPE, 'owner_id' => 0, 'status' => 1])
            ->field(['id', 'pid', 'path'])
            ->select()
            ->toArray();
        if (count($categories) !== count($categoryIds)) {
            throw new AdminResponseException('存在无效的商品分类');
        }

        foreach ($categories as $category) {
            $path = $category['path'] ?? [];
            if (is_string($path)) {
                $path = array_filter(explode('/', trim($path, '/')));
            }
            $depth = count($path) + 1;
            if ($depth > 3) {
                throw new AdminResponseException('商品分类最多支持三级');
            }
        }
    }
}
