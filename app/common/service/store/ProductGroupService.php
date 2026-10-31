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
use app\common\model\store\ProductGroup;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 商品分组服务
 *
 * 商品运营分组（热卖/新品），店铺商品通过 group_id 关联。
 */
class ProductGroupService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     *
     * @return string 模型类名
     */
    protected function setModel(): string
    {
        return ProductGroup::class;
    }

    public function resourceRead(int|array $id, int $merchantId = 0)
    {
        $row = $this->get((int) $id);
        if (!$row || !$this->isVisibleToMerchant((int) ($row['merchant_id'] ?? 0), $merchantId)) {
            throw new AdminResponseException('分组不存在或无权查看');
        }
        return $row->toArray();
    }

    public function resourceUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId, '分组');
        $this->update($id, $data);
        return true;
    }

    public function resourceDelete(int $id, int $merchantId = 0)
    {
        $this->assertOwned($id, $merchantId, '分组');
        return $this->getModel()->destroy($id);
    }

    public function resourceShowUpdate(int|array $id, array $data, int $merchantId = 0)
    {
        $this->assertOwned((int) $id, $merchantId, '分组');
        return $this->update($id, $data);
    }

    /**
     * 商品分组资源列表（带归属过滤）
     *
     * @param array $params 搜索参数（name/status）
     * @param int $merchantId 当前商户ID（0=平台）
     * @return array 列表 + 分页
     */
    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], array|string|null $sort = null)
    {
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
            $query->where('status', (int)$params['status']);
        }

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return $this->listData($list, $count, [], $page, $limit);
    }

    /**
     * 商品分组选项（select 组件数据源）
     *
     * @param int $merchantId 当前商户ID
     * @return array 选项列表
     */
    public function options(int $merchantId = 0): array
    {
        $query = $this->getModel()
            ->where('status', 1)
            ->field(['id', 'name'])
            ->order('sort desc, id desc');
        if ($merchantId > 0) {
            $query->where(function ($q) use ($merchantId) {
                $q->where('merchant_id', 0)->whereOr('merchant_id', $merchantId);
            });
        }

        return $query->limit(200)->select()->toArray();
    }

    protected function assertOwned(int $id, int $merchantId, string $name): void
    {
        if ($merchantId <= 0) {
            return;
        }
        $ownerId = (int) $this->getModel()->where('id', $id)->value('merchant_id');
        if ($ownerId !== $merchantId) {
            throw new AdminResponseException('无权操作该' . $name);
        }
    }

    protected function isVisibleToMerchant(int $ownerId, int $merchantId): bool
    {
        return $merchantId <= 0 || $ownerId === 0 || $ownerId === $merchantId;
    }
}
