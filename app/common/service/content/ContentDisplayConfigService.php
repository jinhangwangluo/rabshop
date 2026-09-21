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

namespace app\common\service\content;

use app\common\enum\ConfigKeyEnum;
use app\common\enum\DiyComponentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\service\plugin\PluginRuntimeService;

/**
 * 内容展示配置服务。
 */
class ContentDisplayConfigService
{
    /**
     * 内容是否允许在移动端展示。
     *
     * @return bool 是否允许在移动端展示内容。
     */
    public function isContentEnabled(): bool
    {
        return !$this->isClosed(ConfigKeyEnum::CONTENT_DISPLAY_CLOSED);
    }

    /**
     * 专题是否允许在移动端展示。
     *
     * @return bool 是否允许在移动端展示专题。
     */
    public function isSpecialEnabled(): bool
    {
        return !$this->isClosed(ConfigKeyEnum::SPECIAL_DISPLAY_CLOSED);
    }

    /**
     * DIY 组件是否允许使用和展示。
     *
     * @param string $componentType 装修组件类型标识。
     * @return bool 指定装修组件是否允许使用及展示。
     */
    public function isDiyComponentEnabled(string $componentType): bool
    {
        return match ($componentType) {
            DiyComponentTypeEnum::CONTENT => $this->isContentEnabled(),
            DiyComponentTypeEnum::SPECIAL => $this->isSpecialEnabled(),
            DiyComponentTypeEnum::SECKILL => $this->isSeckillEnabled(),
            default => true,
        };
    }

    /**
     * 仅在秒杀插件已安装并启用时允许使用其装修组件。
     *
     * @return bool 插件启用态才允许保存/展示秒杀组件。
     */
    protected function isSeckillEnabled(): bool
    {
        try {
            app()->make(PluginRuntimeService::class)->assertEnabled('seckill');
            return true;
        } catch (AdminResponseException) {
            return false;
        }
    }

    /**
     * 过滤后台可选 DIY 组件类型。
     *
     * @param array<int, array<string, mixed>> $groups 组件类型分组。
     * @return array<int, array<string, mixed>> 过滤后的可用组件分组。
     */
    public function filterDiyComponentGroups(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            $types = is_array($group['types'] ?? null) ? $group['types'] : [];
            $group['types'] = array_values(array_filter(
                $types,
                fn($item): bool => is_array($item)
                    && $this->isDiyComponentEnabled((string)($item['value'] ?? ''))
            ));

            if ($group['types'] !== []) {
                $result[] = $group;
            }
        }

        return $result;
    }

    /**
     * 构造移动端空分页数据。
     *
     * @param int $page 当前页码，从第一页开始。
     * @param int $limit 空分页响应使用的每页记录数。
     * @return array 空列表和规范化分页信息。
     */
    public function emptyPage(int $page = 1, int $limit = 10): array
    {
        $limit = max(1, min(30, $limit));
        $page = max(1, $page);

        return [
            'list' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'page_size' => $limit,
            'totalPage' => 0,
            'has_more' => false,
        ];
    }

    /**
     * 构造移动端 DIY 分类标签空数据。
     *
     * @return array 不含分类、激活项和记录的分类标签结构。
     */
    public function emptyCategoryTabs(): array
    {
        return [
            'mode' => 'category_tabs',
            'categories' => [],
            'list' => [],
            'active_category_id' => 0,
        ];
    }

    /**
     * 判断关闭开关是否启用。
     *
     * @param string $key 控制内容或专题关闭状态的配置键。
     * @return bool 指定关闭开关是否启用。
     */
    private function isClosed(string $key): bool
    {
        $value = db_config($key, 0);
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['true', 'yes', 'on', 'closed', 'close'], true);
    }
}
