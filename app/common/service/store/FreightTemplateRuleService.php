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

declare(strict_types=1);

namespace app\common\service\store;

use app\common\exception\AdminResponseException;
use app\common\model\store\FreightTemplateRule;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 运费模板规则服务
 *
 * 指定地区运费规则子资源：
 * - 规则保存/替换由本服务统一处理（控制器不直接操作多表事务）
 * - 同模板内地区不可重复、不可交叉覆盖，避免一个收货地址同时命中多条规则
 * - city_ids 保存 city_area.id 逗号串，展示名称经 city_area 查询生成
 */
class FreightTemplateRuleService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return FreightTemplateRule::class;
    }

    /**
     * 按模板获取有效规则列表
     *
     * @param int $templateId 模板ID
     * @return array 规则列表（sort desc, id asc）
     */
    public function getRulesByTemplateId(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        return $this->getModel()
            ->where('template_id', $templateId)
            ->where('status', 1)
            ->order('sort desc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取规则列表（附带地区名称展示）
     *
     * @param int $templateId 模板ID
     * @return array 规则列表（含 city_names）
     */
    public function rulesWithCityNames(int $templateId): array
    {
        $rules = $this->getRulesByTemplateId($templateId);
        if ($rules === []) {
            return [];
        }

        // 收集地区ID → 名称映射
        $areaIds = [];
        foreach ($rules as $rule) {
            foreach (array_filter(explode(',', (string) ($rule['city_ids'] ?? ''))) as $id) {
                $areaIds[(int) $id] = (int) $id;
            }
        }

        $nameMap = [];
        if ($areaIds !== []) {
            $rows = app()->make(\app\common\model\setting\system\CityArea::class)
                ->whereIn('id', array_values($areaIds))
                ->field(['id', 'name'])
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $nameMap[(int) $row['id']] = (string) ($row['name'] ?? '');
            }
        }

        foreach ($rules as &$rule) {
            $ids = array_map('intval', array_filter(explode(',', (string) ($rule['city_ids'] ?? ''))));
            $rule['city_names'] = array_values(array_filter(array_map(static fn(int $id): string => $nameMap[$id] ?? '', $ids)));
        }
        unset($rule);

        return $rules;
    }

    /**
     * 统计模板规则数量
     *
     * @param int $templateId 模板ID
     * @return int 规则数量
     */
    public function countRules(int $templateId): int
    {
        if ($templateId <= 0) {
            return 0;
        }

        return (int) $this->getModel()
            ->where('template_id', $templateId)
            ->where('status', 1)
            ->count();
    }

    /**
     * 替换模板规则（整体删除重建，校验地区不重复/不交叉）
     *
     * @param int $templateId 模板ID
     * @param array $rules 规则数组
     * @param int $merchantId 当前商户ID
     * @return void
     * @throws AdminResponseException 地区重复/交叉或参数非法时抛出
     */
    public function replaceRules(int $templateId, array $rules, int $merchantId = 0): void
    {
        // 物理删除旧规则（含软删记录清理）
        $oldRules = $this->getModel()
            ->withTrashed()
            ->where('template_id', $templateId)
            ->select();
        foreach ($oldRules as $oldRule) {
            $oldRule->force()->delete();
        }

        $seenCityIds = [];
        $sort = 100;
        foreach ($rules as $index => $rule) {
            $cityIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($rule['city_ids'] ?? ''))))));
            if ($cityIds === []) {
                throw new AdminResponseException('第' . ($index + 1) . '行规则必须选择地区');
            }
            sort($cityIds);

            // 同模板内地区不可重复/交叉覆盖
            foreach ($cityIds as $cityId) {
                if (isset($seenCityIds[$cityId])) {
                    throw new AdminResponseException('地区不能重复配置');
                }
                $seenCityIds[$cityId] = $cityId;
            }

            $this->create([
                'template_id' => $templateId,
                'rule_type' => 1,
                'city_ids' => implode(',', $cityIds),
                'first_quantity' => (float) ($rule['first_quantity'] ?? 1),
                'first_amount' => (float) ($rule['first_amount'] ?? 0),
                'continue_quantity' => (float) ($rule['continue_quantity'] ?? 0),
                'continue_amount' => (float) ($rule['continue_amount'] ?? 0),
                'free_amount' => (float) ($rule['free_amount'] ?? 0),
                'sort' => $sort - $index,
                'status' => 1,
            ]);
        }
    }
}
