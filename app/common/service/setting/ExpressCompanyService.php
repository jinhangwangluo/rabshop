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

namespace app\common\service\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\model\setting\system\ExpressCompany;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 快递公司服务
 */
class ExpressCompanyService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return ExpressCompany::class;
    }

    public function getResourceList(array $params = [], array|string $fields = [], array $with = [], array|string|null $sort = null)
    {
        $query = $this->getModel()->order('is_common desc, sort desc, id desc');
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }
        if (($params['status'] ?? '') !== '') {
            $query->where('status', (int)$params['status']);
        }

        [$page, $limit] = $this->getPagesValue();
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return $this->listData($list, $count, [], $page, $limit);
    }

    public function resourceSave(array $data)
    {
        $data = $this->filterSaveData($data);
        $this->assertUniqueCode($data['code']);

        return $this->create($data);
    }

    public function resourceUpdate(int|array $id, array $data)
    {
        $data = $this->filterSaveData($data);
        $this->assertUniqueCode($data['code'], (int)$id);
        $this->update($id, $data);

        return true;
    }

    public function options(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $query = $this->getModel()
            ->where('status', 1)
            ->field(['id', 'name', 'code', 'is_common'])
            ->order('is_common desc, sort desc, id desc');
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }

        return $query->limit(500)->select()->toArray();
    }

    /**
     * 根据编码获取启用的快递公司
     * @throws AdminResponseException
     */
    public function findEnabledByCode(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new AdminResponseException('请选择快递公司');
        }

        $company = $this->getModel()
            ->where('code', $code)
            ->where('status', 1)
            ->find();
        if (!$company) {
            throw new AdminResponseException('快递公司不存在或已禁用');
        }

        return $company->toArray();
    }

    public function getQueryConfigStatus(): array
    {
        $appcode = trim((string)db_config(ConfigKeyEnum::EXPRESS_QUERY_APPCODE));

        return [
            'provider' => 'aliyun_market',
            'appcode_configured' => $appcode !== '',
        ];
    }

    public function saveQueryConfig(string $appcode): array
    {
        $appcode = trim($appcode);
        app()->make(ConfigService::class)->saveKeyValues([
            ConfigKeyEnum::EXPRESS_QUERY_APPCODE => $appcode,
        ], 0, [
            ConfigKeyEnum::EXPRESS_QUERY_APPCODE => [
                'title' => '物流查询 AppCode',
                'into' => '阿里云市场物流查询接口 AppCode',
                'sort' => 0,
                'status' => 1,
            ],
        ]);

        return $this->getQueryConfigStatus();
    }

    private function filterSaveData(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        if ($name === '') {
            throw new AdminResponseException('请输入快递公司名称');
        }
        if ($code === '') {
            throw new AdminResponseException('请输入快递公司编码');
        }

        return [
            'name' => $name,
            'code' => $code,
            'is_common' => (int)($data['is_common'] ?? 0) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    private function assertUniqueCode(string $code, int $ignoreId = 0): void
    {
        $query = $this->getModel()->where('code', $code);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->count() > 0) {
            throw new AdminResponseException('快递公司编码已存在');
        }
    }
}
