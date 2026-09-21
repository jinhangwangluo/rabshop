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

use app\common\enum\FreightEnum;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\CityArea;
use app\common\model\store\FreightTemplate;
use app\common\model\store\FreightTemplateRule;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 运费模板服务
 *
 * "基础信息 + 默认运费 + 指定地区运费"三段式配置：
 * - 模板 CRUD、默认模板互斥（事务内先取消旧默认再设当前）
 * - 指定地区规则子资源（保存/替换/查询，同模板地区不可重复/交叉）
 * - calcGroupPostage 按店铺和运费模板分组计算订单运费
 * - 运费快照（模板ID/名称/发货时间/计价方式/命中规则ID/实际运费）随订单商品快照保存
 */
class FreightTemplateService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return FreightTemplate::class;
    }

    /**
     * 运费模板资源列表
     *
     * @param array $params 搜索参数（name/is_default/charge_type/status）
     * @param int $merchantId 当前商户ID（0=平台）
     * @return array 列表 + 分页
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], array|string|null $sort = null)
    {
        $merchantId = (int) ($params['_merchant_id'] ?? 0);
        $query = $this->getModel()->order('sort desc, id desc');

        if ($merchantId > 0) {
            $query->where('merchant_id', $merchantId);
        }
        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . $params['name'] . '%');
        }
        if (isset($params['is_default']) && $params['is_default'] !== '') {
            $query->where('is_default', (int)$params['is_default']);
        }
        if (isset($params['charge_type']) && $params['charge_type'] !== '') {
            $query->where('charge_type', (int)$params['charge_type']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int)$params['status']);
        }

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        // 附带规则数量
        $ruleService = app()->make(FreightTemplateRuleService::class);
        foreach ($list as &$item) {
            $item['rule_count'] = $ruleService->countRules((int) $item['id']);
        }
        unset($item);

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 创建运费模板（默认互斥在事务内处理）
     *
     * @param array $data 模板数据（含 rules 指定地区规则数组）
     * @param int $merchantId 当前商户ID
     * @return \think\model\contract\Modelable 创建结果
     */
    public function resourceSave(array $data, int $merchantId = 0)
    {
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : [];

        return $this->transaction(function () use ($data, $rules, $merchantId) {
            $payload = $this->filterTemplateFields($data);
            $payload['merchant_id'] = $merchantId;
            $payload['status'] = (int) ($payload['status'] ?? 1);

            // 默认模板互斥：同商户先取消旧默认
            if ((int) ($payload['is_default'] ?? 0) === FreightEnum::IS_DEFAULT_YES) {
                $this->getModel()
                    ->where('merchant_id', $merchantId)
                    ->update(['is_default' => FreightEnum::IS_DEFAULT_NO]);
            }

            $created = $this->create($payload);
            if ($rules !== []) {
                app()->make(FreightTemplateRuleService::class)->replaceRules((int) $created->id, $rules, $merchantId);
            }
            return $created;
        });
    }

    /**
     * 更新运费模板（默认互斥在事务内处理，规则整体替换）
     *
     * @param int|array $id 主键
     * @param array $data 更新数据
     * @param int $merchantId 当前商户ID
     * @return bool 更新结果
     */
    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId);
        $rules = is_array($data['rules'] ?? null) ? $data['rules'] : null;

        return $this->transaction(function () use ($id, $data, $rules, $merchantId) {
            $payload = $this->filterTemplateFields($data);

            if (array_key_exists('is_default', $payload)
                && (int) $payload['is_default'] === FreightEnum::IS_DEFAULT_YES) {
                $this->getModel()
                    ->where('merchant_id', $merchantId)
                    ->where('id', '<>', (int) $id)
                    ->update(['is_default' => FreightEnum::IS_DEFAULT_NO]);
            }

            $this->update($id, $payload);

            if ($rules !== null) {
                app()->make(FreightTemplateRuleService::class)->replaceRules((int) $id, $rules, $merchantId);
            }
            return true;
        });
    }

    /**
     * 删除运费模板（软删）
     *
     * @param int $id 主键
     * @param int $merchantId 当前商户ID
     * @return bool 删除结果
     */
    public function resourceDelete(int $id, int $merchantId = 0)
    {
        $this->assertOwned($id, $merchantId);
        return (bool) $this->getModel()->destroy($id);
    }

    public function resourceRead(int|array $id, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId);
        $row = $this->get((int) $id);
        if (!$row) {
            throw new AdminResponseException('运费模板不存在');
        }
        return $row->toArray();
    }

    /**
     * 修改状态
     *
     * @param int|array $id 主键
     * @param array $data 状态数据
     * @return bool 更新结果
     */
    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId);
        return $this->update($id, $data);
    }

    /**
     * 设置默认模板（事务内互斥）
     *
     * @param int $id 模板ID
     * @param int $merchantId 当前商户ID
     * @return bool 更新结果
     */
    public function setDefault(int $id, int $merchantId = 0): bool
    {
        $this->assertOwned($id, $merchantId);
        return $this->transaction(function () use ($id, $merchantId) {
            $this->getModel()
                ->where('merchant_id', $merchantId)
                ->update(['is_default' => FreightEnum::IS_DEFAULT_NO]);
            $this->update($id, ['is_default' => FreightEnum::IS_DEFAULT_YES]);
            return true;
        });
    }

    /**
     * 过滤模板可写字段
     *
     * @param array $data 原始数据
     * @return array 可写字段
     */
    protected function filterTemplateFields(array $data): array
    {
        $fields = ['name', 'delivery_time', 'is_default', 'is_free', 'charge_type', 'default_first_quantity', 'default_first_amount', 'default_continue_quantity', 'default_continue_amount', 'free_amount', 'sort', 'status'];
        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        $payload['is_default'] = (int) ($payload['is_default'] ?? FreightEnum::IS_DEFAULT_NO);
        $payload['is_free'] = (int) ($payload['is_free'] ?? FreightEnum::IS_FREE_NO);
        $payload['charge_type'] = (int) ($payload['charge_type'] ?? FreightEnum::CHARGE_TYPE_COUNT);
        return $payload;
    }

    /**
     * 校验模板归属（平台或归属商户可操作）
     *
     * @param int $id 模板ID
     * @param int $merchantId 当前商户ID
     * @return void
     * @throws AdminResponseException 无权限时抛出
     */
    public function assertOwned(int $id, int $merchantId): void
    {
        if ($id <= 0) {
            throw new AdminResponseException('参数错误');
        }
        if ($merchantId <= 0) {
            return;
        }

        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该运费模板');
        }
    }

    /**
     * 发货时间选项（下拉框数据源）
     *
     * @return array 选项值 => 文案
     */
    public function deliveryTimeOptions(): array
    {
        return FreightEnum::deliveryTimeOptions();
    }

    /**
     * 地区树（city_area，指定地区规则选择器数据源）
     *
     * @return array 地区树形数据
     */
    public function cityTree(): array
    {
        $model = app()->make(CityArea::class);
        $list = $model->where('status', 1)
            ->field(['id', 'parent_id', 'name'])
            ->order('sort desc, id asc')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['pid'] = (int) ($item['parent_id'] ?? 0); // get_tree_children 需要 pid 字段
            $item['label'] = (string) ($item['name'] ?? '');
            $item['value'] = (int) ($item['id'] ?? 0);
        }
        unset($item);

        return get_tree_children($list);
    }

    /**
     * 运费模板选项（select 组件数据源，平台公共 + 当前商户自建）
     *
     * @param int $merchantId 当前商户ID
     * @return array 选项列表
     */
    public function options(int $merchantId = 0): array
    {
        $query = $this->getModel()
            ->where('status', 1)
            ->field(['id', 'name', 'is_free', 'charge_type', 'is_default'])
            ->order('sort desc, id desc');
        if ($merchantId > 0) {
            $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }

        return $query->limit(200)->select()->toArray();
    }

    /**
     * 按店铺和运费模板分组计算运费
     *
     * 规则（规划 10.4）：
     * 1. 虚拟/内容商品不参与实物运费；有实物商品但无收货地址时抛"请选择收货地址"
     * 2. 商品 is_free_shipping=1 直接包邮；模板 is_free=1 该组运费为 0
     * 3. 按地址末级区域→区县→市→省逐级匹配指定地区规则，未命中用默认运费
     * 4. 按件数使用购买总件数；按重量汇总 SKU 重量（克）换算为千克
     * 5. 阶梯：首费 + ceil(max(计费数量-首量, 0)/续量) × 续费；续量为 0 仅收首费
     * 6. 商品金额达到 free_amount 门槛则该组运费为 0
     *
     * @param array $items 商品行（含 goods_type/is_free_shipping/freight_template_id/cart_num/price 等）
     * @param array $address 收货地址（province/city/district 名称）
     * @return array{postage: float, freight: array} 运费与快照信息
     * @throws AdminResponseException 有实物商品无地址时抛出
     */
    public function calcGroupPostage(array $items, array $address = []): array
    {
        // 1. 实物商品判定
        $physicalItems = array_values(array_filter($items, static fn(array $item): bool => (int) ($item['goods_type'] ?? 0) === 1));

        if ($physicalItems === []) {
            // 无实物商品：运费为 0
            return ['postage' => 0.0, 'freight' => []];
        }

        $addressText = trim((string) ($address['province'] ?? '')) . trim((string) ($address['city'] ?? '')) . trim((string) ($address['district'] ?? '')) . trim((string) ($address['detail'] ?? ''));
        if ($addressText === '') {
            throw new AdminResponseException('请选择收货地址');
        }

        // 按运费模板分组（包邮商品直接计 0，不参与模板分组）
        $groups = [];
        $postage = 0.0;
        foreach ($physicalItems as $item) {
            if ((int) ($item['is_free_shipping'] ?? 0) === 1) {
                continue; // 商品级包邮，直接 0
            }
            $templateId = (int) ($item['freight_template_id'] ?? 0);
            $groups[$templateId][] = $item;
        }

        $freightSnapshots = [];
        $areaIds = $this->addressToAreaIds($address);

        foreach ($groups as $templateId => $groupItems) {
            if ($templateId <= 0) {
                throw new AdminResponseException('商品未配置运费模板');
            }
            $template = $this->getModel()->where('id', $templateId)->find();
            if (!$template || (int) $template['status'] !== 1) {
                throw new AdminResponseException('运费模板不存在或已禁用');
            }
            $template = $template->toArray();

            // 2. 模板包邮
            if ((int) ($template['is_free'] ?? 0) === FreightEnum::IS_FREE_YES) {
                $freightSnapshots[] = $this->buildSnapshot($template, 0, 0.0);
                continue;
            }

            // 3. 命中指定地区规则（无地址交集时用默认）
            $rule = $this->matchRule((int) $templateId, $areaIds);

            // 4. 计费数量：按件数=总件数；按重量=总重量(克→千克)
            $chargeType = (int) ($template['charge_type'] ?? FreightEnum::CHARGE_TYPE_COUNT);
            $countable = 0;
            $goodsAmount = 0.0;
            foreach ($groupItems as $item) {
                $num = max(1, (int) ($item['cart_num'] ?? 1));
                if ($chargeType === FreightEnum::CHARGE_TYPE_WEIGHT) {
                    $weightGrams = (float) ($item['weight'] ?? 0);
                    // SKU 重量单位克 → 千克
                    $countable += $weightGrams * $num / 1000;
                } else {
                    $countable += $num;
                }
                $goodsAmount += (float) ($item['line_total'] ?? 0);
            }

            $firstQuantity = $rule ? (float) $rule['first_quantity'] : (float) ($template['default_first_quantity'] ?? 1);
            $firstAmount = $rule ? (float) $rule['first_amount'] : (float) ($template['default_first_amount'] ?? 0);
            $continueQuantity = $rule ? (float) $rule['continue_quantity'] : (float) ($template['default_continue_quantity'] ?? 0);
            $continueAmount = $rule ? (float) $rule['continue_amount'] : (float) ($template['default_continue_amount'] ?? 0);
            $freeAmount = $rule ? (float) $rule['free_amount'] : (float) ($template['free_amount'] ?? 0);

            // 5. 阶梯公式
            $amount = $firstAmount;
            if ($continueQuantity > 0 && $countable > $firstQuantity) {
                $steps = (int) ceil((max($countable - $firstQuantity, 0)) / $continueQuantity);
                $amount += $steps * $continueAmount;
            }

            // 6. 满额免运费
            if ($freeAmount > 0 && $goodsAmount >= $freeAmount) {
                $amount = 0.0;
            }

            $postage += (float) $amount;
            $freightSnapshots[] = $this->buildSnapshot($template, $rule ? (int) $rule['id'] : 0, (float) $amount);
        }

        return [
            'postage' => (float) $postage,
            'freight' => $freightSnapshots,
        ];
    }

    /**
     * 构建运费快照（随订单商品快照保存，模板后续修改不影响历史订单）
     *
     * @param array $template 模板数据
     * @param int $ruleId 命中的规则ID（0=默认运费）
     * @param float $amount 实际运费
     * @return array 快照数据
     */
    protected function buildSnapshot(array $template, int $ruleId, float $amount): array
    {
        return [
            'freight_template_id' => (int) ($template['id'] ?? 0),
            'freight_template_name' => (string) ($template['name'] ?? ''),
            'delivery_time' => (string) ($template['delivery_time'] ?? ''),
            'charge_type' => (int) ($template['charge_type'] ?? FreightEnum::CHARGE_TYPE_COUNT),
            'rule_id' => $ruleId,
            'postage' => round($amount, 2),
        ];
    }

    /**
     * 匹配指定地区规则（按地址 ID 集合交集，sort desc 优先）
     *
     * @param int $templateId 模板ID
     * @param array $areaIds 地址涉及的区域ID集合（末级+各级父级）
     * @return array|null 命中的规则，未命中返回 null
     */
    protected function matchRule(int $templateId, array $areaIds): ?array
    {
        if ($areaIds === []) {
            return null;
        }

        $rules = app()->make(FreightTemplateRuleService::class)
            ->getRulesByTemplateId($templateId);

        foreach ($rules as $rule) {
            $ruleCityIds = array_map('intval', array_filter(explode(',', (string) ($rule['city_ids'] ?? ''))));
            foreach ($areaIds as $areaId) {
                if (in_array($areaId, $ruleCityIds, true)) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * 将收货地址（省/市/区名称）解析为区域ID集合（含末级及各级父级）
     *
     * 返回 [末级ID, 区县ID, 市ID, 省ID]，保证指定地区规则选择省/市/区任一级均可命中。
     *
     * @param array $address 收货地址
     * @return array 区域ID集合（升序去重）
     */
    protected function addressToAreaIds(array $address): array
    {
        $model = app()->make(CityArea::class);
        $ids = [];

        foreach (['area_id', 'district_id', 'city_id', 'province_id'] as $field) {
            $id = (int)($address[$field] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids !== []) {
            return array_values(array_unique(array_filter($ids)));
        }

        $province = trim((string) ($address['province'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $district = trim((string) ($address['district'] ?? ''));

        // 省 → 市 → 区县逐级向下匹配，parent 链保证归属正确
        $parentId = 0;
        foreach ([$province, $city, $district] as $name) {
            if ($name === '') {
                continue;
            }
            $query = $model->where('name', $name);
            if ($parentId > 0) {
                $query->where('parent_id', $parentId);
            } else {
                // 首级（省）按 level=1 定位
                $query->where('level', 1);
            }
            $row = $query->order('id asc')->find();
            if (!$row) {
                break;
            }
            $ids[] = (int) $row->id;
            $parentId = (int) $row->id;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
