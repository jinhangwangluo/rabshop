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
use app\common\model\store\ProductSpec;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品规格模板服务。
 *
 * product_spec.spec_template 保存模板下完整的规格维度和值。
 * 模板列表和详情直接读取该 JSON 字段。
 */
class ProductSpecService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型。
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return ProductSpec::class;
    }

    /**
     * 新增规格模板。
     *
     * @param array $data 模板数据
     * @return array 模板详情
     */
    public function resourceSave(array $data): array
    {
        $payload = $this->filterTemplateFields($data);
        $payload['merchant_id'] = (int) ($data['merchant_id'] ?? 0);
        $payload['spec_template'] = $data['specs'] ?? [];
        $merchantId = (int) $payload['merchant_id'];

        return $this->transaction(function () use ($payload, $merchantId): array {
            $template = $this->create($payload);
            return $this->buildTemplateData($template->toArray(), $merchantId);
        });
    }

    /**
     * 查询规格模板详情。
     *
     * @param int|array $id 模板ID
     * @param int $merchantId 当前商户ID，0 不限制
     * @return array 模板详情
     */
    public function resourceRead(int|array $id, int $merchantId = 0): array
    {
        $template = $this->get((int) $id);
        if (!$template || !$this->isVisibleToMerchant((int) ($template['merchant_id'] ?? 0), $merchantId)) {
            throw new AdminResponseException('规格模板不存在或无权查看');
        }

        return $this->buildTemplateData($template->toArray(), $merchantId);
    }

    /**
     * 更新规格模板。
     *
     * @param int|array $id 模板ID
     * @param array $data 模板数据
     * @param int $merchantId 当前商户ID，0 不限制
     * @return bool 更新结果
     */
    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0): bool
    {
        $templateId = (int) $id;
        $this->assertOwned($templateId, $merchantId, '规格模板');

        $payload = $this->filterTemplateFields($data);
        if (array_key_exists('specs', $data)) {
            $payload['spec_template'] = $data['specs'];
        }
        $this->transaction(function () use ($templateId, $payload): bool {
            if ($payload === []) {
                return true;
            }
            return (bool) $this->update($templateId, $payload);
        });

        return true;
    }

    /**
     * 删除规格模板。
     *
     * @param int $id 模板ID
     * @param int $merchantId 当前商户ID，0 不限制
     * @return bool 删除结果
     */
    public function resourceDelete(int $id, int $merchantId = 0): bool
    {
        $this->assertOwned($id, $merchantId, '规格模板');
        return (bool) $this->getModel()->destroy($id);
    }

    /**
     * 修改规格模板状态。
     *
     * @param int|array $id 模板ID
     * @param array $data 状态数据
     * @param int $merchantId 当前商户ID，0 不限制
     * @return bool 更新结果
     */
    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0): bool
    {
        $this->assertOwned((int) $id, $merchantId, '规格模板');
        return (bool) $this->update($id, $data);
    }

    /**
     * 规格模板列表。
     *
     * @param array $params 搜索参数（name/status/_merchant_id）
     * @param array|string $fields 查询字段
     * @param array $with 关联
     * @param array|string|null $sort 排序
     * @return mixed 分页数据
     */
    public function getResourceList(
        array $params = [],
        array|string $fields = [],
        array $with = [],
        array|string|null $sort = null
    ) {
        $merchantId = (int) ($params['_merchant_id'] ?? 0);
        $query = $this->getModel()->order('sort desc, id desc');

        if ($merchantId > 0) {
            $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }
        if (!empty($params['name'])) {
            $query->where('name', 'like', '%' . $params['name'] . '%');
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();
        foreach ($list as &$item) {
            $item = $this->buildTemplateData($item, $merchantId);
        }
        unset($item);

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 规格维度选项，供商品库和商品组合逻辑使用。
     *
     * @param int $merchantId 当前商户ID
     * @return array 规格维度列表
     */
    public function options(int $merchantId = 0): array
    {
        $query = $this->getModel()
            ->where('status', 1)
            ->order('sort desc, id desc');
        if ($merchantId > 0) {
            $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }

        $options = [];
        foreach ($query->limit(200)->select()->toArray() as $template) {
            $templateData = $this->buildTemplateData($template, $merchantId);
            foreach ($templateData['specs'] as $spec) {
                if ((int) ($spec['status'] ?? 1) !== 1) {
                    continue;
                }
                $spec['template_id'] = (int) $templateData['id'];
                $spec['template_name'] = (string) $templateData['name'];
                $spec['values'] = array_values(array_filter(
                    $spec['values'] ?? [],
                    static fn(array $value): bool => (int) ($value['status'] ?? 1) === 1
                ));
                $options[] = $spec;
            }
        }

        return $options;
    }

    /**
     * 校验模板是否对商户可见。
     *
     * @param int $id 模板ID
     * @param int $merchantId 当前商户ID
     * @return void
     */
    public function assertVisible(int $id, int $merchantId): void
    {
        if ($id <= 0) {
            throw new AdminResponseException('参数错误');
        }
        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if (!$this->isVisibleToMerchant($ownerId, $merchantId)) {
            throw new AdminResponseException('规格模板不存在或无权查看');
        }
    }

    /**
     * 校验模板是否归当前商户所有。
     *
     * @param int $id 模板ID
     * @param int $merchantId 当前商户ID
     * @param string $name 资源名称
     * @return void
     */
    protected function assertOwned(int $id, int $merchantId, string $name): void
    {
        if ($id <= 0) {
            throw new AdminResponseException('参数错误');
        }
        if ($merchantId <= 0) {
            return;
        }

        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该' . $name);
        }
    }

    /**
     * 过滤规格模板可写字段。
     *
     * @param array $data 原始数据
     * @return array 可写字段
     */
    protected function filterTemplateFields(array $data): array
    {
        $fields = ['name', 'sort', 'status'];
        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }
        if (array_key_exists('sort', $payload)) {
            $payload['sort'] = (int) $payload['sort'];
        }
        if (array_key_exists('status', $payload)) {
            $payload['status'] = (int) $payload['status'];
        }

        return $payload;
    }

    /**
     * 组装统一的模板响应。
     *
     * @param array $template 模板数据
     * @param int $merchantId 当前商户ID
     * @return array 模板响应
     */
    protected function buildTemplateData(array $template, int $merchantId): array
    {
        return [
            'id' => (int) ($template['id'] ?? 0),
            'merchant_id' => (int) ($template['merchant_id'] ?? 0),
            'name' => (string) ($template['name'] ?? ''),
            'sort' => (int) ($template['sort'] ?? 0),
            'status' => (int) ($template['status'] ?? 1),
            'create_time' => $template['create_time'] ?? null,
            'update_time' => $template['update_time'] ?? null,
            'delete_time' => $template['delete_time'] ?? null,
            'specs' => $this->getTemplateSpecs($template, $merchantId),
        ];
    }

    /**
     * 读取 JSON 模板内容。
     *
     * @param array $template 模板数据
     * @param int $merchantId 当前商户ID
     * @return array 规格维度列表
     */
    protected function getTemplateSpecs(array $template, int $merchantId = 0): array
    {
        $specs = $template['spec_template'] ?? [];
        if (is_string($specs)) {
            $specs = json_decode($specs, true) ?: [];
        }
        if (is_array($specs) && $specs !== []) {
            return $this->normalizeResponseSpecs($specs);
        }

        return [];
    }

    /**
     * 规范化响应中的规格维度。
     *
     * @param array $specs 规格维度
     * @return array 规格维度
     */
    protected function normalizeResponseSpecs(array $specs): array
    {
        return array_values(array_map(function (array $spec): array {
            return [
                'id' => (int) ($spec['id'] ?? 0),
                'name' => (string) ($spec['name'] ?? ''),
                'sort' => (int) ($spec['sort'] ?? 0),
                'status' => (int) ($spec['status'] ?? 1),
                'values' => $this->normalizeResponseValues(
                    is_array($spec['values'] ?? null) ? $spec['values'] : []
                ),
            ];
        }, $specs));
    }

    /**
     * 规范化响应中的规格值。
     *
     * @param array $values 规格值
     * @return array 规格值
     */
    protected function normalizeResponseValues(array $values): array
    {
        return array_values(array_map(static function (array $value): array {
            return [
                'id' => (int) ($value['id'] ?? 0),
                'value' => (string) ($value['value'] ?? ''),
                'pic' => (string) ($value['pic'] ?? ''),
                'sort' => (int) ($value['sort'] ?? 0),
                'status' => (int) ($value['status'] ?? 1),
            ];
        }, $values));
    }

    /**
     * 归属可见性判断。
     *
     * @param int $ownerId 所属商户ID
     * @param int $merchantId 当前商户ID
     * @return bool 是否可见
     */
    protected function isVisibleToMerchant(int $ownerId, int $merchantId): bool
    {
        return $merchantId <= 0 || $ownerId === 0 || $ownerId === $merchantId;
    }
}
