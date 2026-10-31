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

namespace app\common\service\merchant;

use app\common\enum\AuditStatusEnum;
use app\common\exception\AdminResponseException;
use app\common\model\merchant\Merchant;
use app\common\model\setting\system\Admin;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\SpecialService;
use app\common\service\message\BackendMessageEventService;
use app\common\service\plugin\PluginBusinessEventService;
use app\common\service\setting\AdminService;
use app\common\service\user\UserFavoriteService;
use Rabbit\service\websocket\TokenHelper;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 商户资料、审核、店铺配置和移动端公开展示服务。
 *
 * @package app\common\service\merchant
 */
class MerchantService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 声明当前服务使用的模型类。
     *
     * @return string 当前服务对应的模型类名。
     */
    protected function setModel(): string
    {
        return Merchant::class;
    }

    /**
     * 获取商户名称映射。
     *
     * 购物车和订单拆单展示只需要商户名称，集中放在商户 Service 中，避免订单侧跨模型直查。
     *
     * @param array $ids 商户 ID 列表。
     * @return array<int, string> 以商户标识为键的名称映射。
     */
    public function getNameMapByIds(array $ids): array
    {
        $ids = $this->normalizeLookupIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = $this->getModel()
            ->whereIn('id', $ids)
            ->column('mer_name', 'id');

        $result = [];
        foreach ($rows as $id => $name) {
            $result[(int)$id] = (string)$name;
        }

        return $result;
    }

    /**
     * 获取订单展示所需的商户信息映射。
     *
     * @param array $ids 商户 ID 列表。
     * @return array<int, array> 以商户标识为键的订单展示资料。
     */
    public function getOrderDisplayMapByIds(array $ids): array
    {
        $ids = $this->normalizeLookupIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = $this->getModel()
            ->whereIn('id', $ids)
            ->field([
                'id',
                'mer_name',
                'mer_phone',
                'contact_name',
                'contact_phone',
                'logo',
                'mer_avatar',
                'business_status',
                'status',
            ])
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $avatar = trim((string)($row['mer_avatar'] ?? ''));
            $logo = trim((string)($row['logo'] ?? ''));
            $phone = trim((string)($row['contact_phone'] ?? ''));
            if ($phone === '') {
                $phone = trim((string)($row['mer_phone'] ?? ''));
            }
            $businessStatus = (int)($row['business_status'] ?? 1);
            $name = trim((string)($row['mer_name'] ?? ''));

            $result[$id] = [
                'id' => $id,
                'mer_name' => $name,
                'name' => $name,
                'mer_phone' => trim((string)($row['mer_phone'] ?? '')),
                'phone' => $phone,
                'contact_name' => trim((string)($row['contact_name'] ?? '')),
                'contact_phone' => trim((string)($row['contact_phone'] ?? '')),
                'logo' => $logo,
                'mer_avatar' => $avatar,
                'avatar' => $avatar !== '' ? $avatar : $logo,
                'business_status' => $businessStatus,
                'business_status_text' => $businessStatus === 1 ? '营业中' : '休息中',
                'status' => (int)($row['status'] ?? 1),
            ];
        }

        return $result;
    }

    /**
     * 获取商户会员折扣参与状态映射。
     *
     * @param array $ids 商户 ID 列表。
     * @return array<int, bool> 以商户标识为键的会员折扣参与状态。
     */
    public function getMemberDiscountJoinedMapByIds(array $ids): array
    {
        $ids = $this->normalizeLookupIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = $this->getModel()
            ->whereIn('id', $ids)
            ->column('is_member_discount', 'id');

        $result = [];
        foreach ($rows as $id => $joined) {
            $result[(int)$id] = (int)$joined === 1;
        }

        return $result;
    }

    /**
     * 获取商户抽佣比例映射。
     *
     * @param array $ids 商户 ID 列表。
     * @return array<int, float> 以商户标识为键的抽佣比例。
     */
    public function getCommissionRateMapByIds(array $ids): array
    {
        $ids = $this->normalizeLookupIds($ids);
        if ($ids === []) {
            return [];
        }

        $rows = $this->getModel()
            ->whereIn('id', $ids)
            ->column('commission_rate', 'id');

        $result = [];
        foreach ($rows as $id => $rate) {
            $result[(int)$id] = max(0.0, min(100.0, (float)$rate));
        }

        return $result;
    }

    /**
     * 标准化批量查询的商户 ID。
     *
     * @param array $ids 需要批量读取的商户标识集合。
     * @return array 去重后的有效正整数商户标识。
     */
    private function normalizeLookupIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));
    }

    /**
     * 商户被禁用后撤销其全部 WebSocket token。
     *
     * @param int $merchantId 商户 ID
     * @param array $data 本次更新数据
     * @return void 无返回值。
     */
    private function revokeTokensWhenDisabled(int $merchantId, array $data): void
    {
        if ($merchantId > 0 && array_key_exists('status', $data) && (int)$data['status'] !== 1) {
            TokenHelper::revokeMerchantTokens($merchantId);
        }
    }

    /**
     * 新增商户（重写 resourceSave，创建商户并创建管理员账号）
     *
     * @param array $data 商户资料及用于新建商户管理员的账号、密码字段。
     * @return mixed 已创建管理员账号的商户资料。
     * @throws AdminResponseException
     */
    public function resourceSave(array $data): mixed
    {
        if (empty($data['account'])) {
            throw new AdminResponseException('请填写登录账号');
        }
        if (empty($data['password'])) {
            throw new AdminResponseException('请填写登录密码');
        }

        // 商户名称唯一性校验
        $nameExists = $this->getModel()->where('mer_name', $data['mer_name'])->find();
        if ($nameExists) {
            throw new AdminResponseException('该商户名称已存在');
        }

        // 账号唯一性校验（检查 admin 表）
        $adminService = app()->make(AdminService::class);
        $accountExists = $adminService->getModel()->where('account', $data['account'])->find();
        if ($accountExists) {
            throw new AdminResponseException('该登录账号已存在');
        }

        return $this->transaction(function () use ($data, $adminService) {
            // 创建商户记录
            $merchantData = $data;
            unset($merchantData['account'], $merchantData['password']);
            // 平台直接添加的商户，默认审核通过
            $merchantData['audit_status'] = 1;

            $merchant = $this->create($merchantData);

            // 创建商户管理员账号
            $adminService->create([
                'account'       => $data['account'],
                'password'      => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]),
                'real_name'     => $data['mer_name'] ?? '',
                'platform_type' => Admin::PLATFORM_TYPE_MERCHANT,
                'merchant_id'   => $merchant->id,
                'status'        => 1,
                'roles'         => [],
            ]);

            $this->publishMerchantEvent('merchant.created', $merchant, [
                'operation' => 'resource_save',
                'source' => 'merchant_admin',
            ]);

            return $merchant;
        });
    }

    /**
     * 更新商户（重写 resourceUpdate，编辑时不允许修改账号密码）
     *
     * @param int|array $id 商户记录标识或资源查询条件。
     * @param array $data 商户资料、状态或店铺设置字段。
     * @return bool 商户资料是否更新成功。
     */
    public function resourceUpdate(int|array $id, array $data): bool
    {
        // 编辑时不修改账号密码
        unset($data['account'], $data['password']);

        $merchantId = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $this->update($id, $data);
        $this->revokeTokensWhenDisabled($merchantId, $data);
        if ($merchantId > 0) {
            $this->publishMerchantEvent('merchant.updated', $merchantId, [
                'operation' => 'resource_update',
                'changed_keys' => array_values(array_keys($data)),
            ]);
        }
        return true;
    }

    /**
     * 更新商户启用状态，并在禁用时撤销商户下全部 WebSocket token。
     *
     * @param int|array $id 商户 ID 或条件
     * @param array $data 更新数据
     * @return bool 商户启用状态是否更新成功。
     */
    public function resourceShowUpdate(int|array $id, array $data): bool
    {
        $merchantId = is_array($id) ? (int)($id['id'] ?? 0) : (int)$id;
        $this->update($id, $data);
        $this->revokeTokensWhenDisabled($merchantId, $data);

        return true;
    }

    /**
     * 单独更新商户平台抽佣比例。
     *
     * @param int $id 商户记录标识。
     * @param float|string $commissionRate 平台抽佣比例。
     * @return bool 抽佣比例是否更新成功。
     */
    public function updateCommissionRate(int $id, float|string $commissionRate): bool
    {
        if ($id <= 0) {
            throw new AdminResponseException('商户ID不能为空');
        }

        if (!$this->get($id, ['id'])) {
            throw new AdminResponseException('商户不存在');
        }

        $rate = max(0, min(100, (float)$commissionRate));
        $this->update($id, [
            'commission_rate' => number_format($rate, 2, '.', ''),
        ]);

        return true;
    }

    /**
     * 读取商户详情（重写 resourceRead，关联管理员账号）
     *
     * @param int|array $id 商户记录标识或资源查询条件。
     * @return array 关联管理员账号的商户详情。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function resourceRead(int|array $id): array
    {
        $model = $this->get($id);
        if (!$model) {
            throw new \think\db\exception\ModelNotFoundException('商户不存在');
        }

        $data = $model->toArray();

        // 查找商户对应的管理员账号
        $adminService = app()->make(AdminService::class);
        $admin = $adminService->getModel()
            ->where('merchant_id', $model->id)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->field(['account', 'real_name', 'status'])
            ->find();

        // 附加管理员账号信息
        $data['account'] = $admin ? $admin->account : '';
        $data['admin_status'] = $admin ? $admin->status : 0;
        $data['admin_real_name'] = $admin ? $admin->real_name : '';

        return $data;
    }

    /**
     * 修改商户管理员密码
     *
     * @param int $id 商户 ID
     * @param string $newPassword 新密码
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function changePassword(int $id, string $newPassword): void
    {
        if (empty($newPassword)) {
            throw new AdminResponseException('新密码不能为空');
        }
        if (mb_strlen($newPassword) < 6) {
            throw new AdminResponseException('密码长度不能少于6位');
        }

        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        // 查找商户对应的管理员账号
        $adminService = app()->make(AdminService::class);
        $admin = $adminService->getModel()
            ->where('merchant_id', $id)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$admin) {
            throw new AdminResponseException('商户管理员账号不存在');
        }

        $adminService->update($admin->id, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10])
        ]);
        TokenHelper::revokeMerchantToken($id, (int)$admin->id);
    }

    /**
     * 平台端一键登录商户后台：生成商户 token
     *
     * @param int $id 商户 ID
     * @return array 商户登录令牌及后台跳转地址。
     * @throws AdminResponseException
     */
    public function generateMerchantToken(int $id): array
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }
        if (!$merchant->status) {
            throw new AdminResponseException('该商户已被禁用');
        }
        if ($merchant->audit_status != 1) {
            throw new AdminResponseException('该商户未通过审核');
        }

        // 查找商户对应的管理员账号
        $adminService = app()->make(AdminService::class);
        $admin = $adminService->getModel()
            ->where('merchant_id', $id)
            ->where('platform_type', Admin::PLATFORM_TYPE_MERCHANT)
            ->find();

        if (!$admin) {
            throw new AdminResponseException('商户管理员账号不存在');
        }

        $timeout = config('app.token_timeout', 864000);
        $token   = $admin->setToken((int)$admin->id, 'merchant');

        // 写入商户端缓存（使用 Admin 表的 cacheTag，与 MerchantAuthService 一致）
        $cacheTag = new \Rabbit\util\CacheTag($adminService->getModel()->getName() . '_mechant');
        $cacheTag->set(md5($token), $token, $timeout);

        return [
            'token'              => $token,
            'expiresIn'          => $timeout,
            'merchant_ui_prefix' => $this->getMerchantUiPrefix(),
            'merchant_url'       => $this->getMerchantUiBasePath(),
            'login_url'          => $this->getMerchantUiBasePath() . '/login?token=' . rawurlencode($token),
        ];
    }

    /**
     * 获取商户端前端路由前缀。
     *
     * @return string 商户前端路由前缀。
     */
    private function getMerchantUiPrefix(): string
    {
        $prefix = trim((string)config('app.merchant_ui_prefix', 'merchant'), " \t\n\r\0\x0B/");

        return $prefix !== '' ? $prefix : 'merchant';
    }

    /**
     * 获取商户端前端基础路径。
     *
     * @return string 商户前端部署基础路径。
     */
    private function getMerchantUiBasePath(): string
    {
        $configuredUrl = request()->domain();
        if ($configuredUrl !== '' && preg_match('/^https?:\/\//i', $configuredUrl) === 1) {
            return rtrim($configuredUrl, '/');
        }

        return '/' . $this->getMerchantUiPrefix();
    }

    /**
     * 获取商户详情
     *
     * @param int $id 商户记录标识。
     * @return array 当前商户的完整资料。
     * @throws AdminResponseException
     */
    public function getMerchantInfo(int $id): array
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }
        return $merchant->toArray();
    }

    /**
     * 获取移动端公开商家首页数据。
     *
     * @param int $merchantId 商户ID
     * @param int $userId 当前用户ID，匿名为0。
     * @return array 公开商户资料、独立底部菜单及首屏展示模块。
     * @throws AdminResponseException
     */
    public function getMobileHome(int $merchantId, int $userId = 0): array
    {
        if ($merchantId <= 0) {
            throw new AdminResponseException('商户ID不能为空');
        }

        $merchant = $this->get($merchantId);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $merchantData = $merchant->toArray();
        if ((int)($merchantData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('商户已禁用');
        }
        if ((int)($merchantData['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('商户暂不可访问');
        }

        $banners = $this->formatMobileHomeBanners($merchantData['merchant_home_banner'] ?? []);
        $legacySpecialIds = $this->extractRecommendSpecialIds($merchantData['merchant_home_recommend_special'] ?? []);
        $recommendSpecials = app()->make(SpecialService::class)->getMerchantHomeRecommendList(
            $merchantId,
            $legacySpecialIds,
            $userId
        );

        return [
            'merchant' => $this->formatMobileHomeMerchant($merchantData),
            'banners' => $banners,
            'recommend_specials' => $recommendSpecials,
        ] + app()->make(MerchantMobileDisplayService::class)->getHomeData($merchantId, $legacySpecialIds, $userId);
    }

    /**
     * 获取移动端公开商家店铺详情。
     *
     * @param int $merchantId 商户ID
     * @return array 店铺公开详情与独立的底部菜单配置。
     * @throws AdminResponseException
     */
    public function getMobileDetail(int $merchantId): array
    {
        if ($merchantId <= 0) {
            throw new AdminResponseException('商户ID不能为空');
        }

        $merchant = $this->get($merchantId);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $merchantData = $merchant->toArray();
        if ((int)($merchantData['status'] ?? 0) !== 1) {
            throw new AdminResponseException('商户已禁用');
        }
        if ((int)($merchantData['audit_status'] ?? 0) !== AuditStatusEnum::APPROVED) {
            throw new AdminResponseException('商户暂不可访问');
        }

        $displayConfig = app()->make(MerchantMobileDisplayService::class)->getConfig(
            $merchantId,
            $this->extractRecommendSpecialIds($merchantData['merchant_home_recommend_special'] ?? [])
        );
        return $this->formatMobileDetailMerchant($merchantData) + ['menu' => $displayConfig['menu']];
    }

    /**
     * 获取店铺完整信息（三标签页数据）
     *
     * @param int $id 商户记录标识。
     * @return array 按 basicInfo、storeInfo、funcInfo 分组的基本资料、店铺配置和功能设置。
     * @throws AdminResponseException
     */
    public function getStoreFullInfo(int $id): array
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $data = $merchant->toArray();

        // 基本信息
        $basicInfo = [
            'mer_name' => $data['mer_name'] ?? '',
            'contact_phone' => $data['contact_phone'] ?? '',
            'mer_category' => $data['mer_category'] ?? '',
            'mer_type' => $data['mer_type'] ?? '',
            'mer_class' => $data['mer_class'] ?? 2,
            'mer_class_text' => ($data['mer_class'] ?? 2) == 1 ? '自营' : '非自营',
            'contact_name' => $data['contact_name'] ?? '',
            'create_time' => $data['create_time'] ?? '',
            'status' => $data['status'] ?? 1,
        ];

        // 店铺信息
        $storeInfo = [
            'mer_banner' => $data['mer_banner'] ?? '',
            'merchant_home_banner' => $this->normalizeMerchantHomeBanners($data['merchant_home_banner'] ?? []),
            'merchant_home_recommend_special' => $this->extractRecommendSpecialIds(
                $data['merchant_home_recommend_special'] ?? []
            ),
            'merchant_home_config' => app()->make(MerchantMobileDisplayService::class)->getConfig(
                $id,
                $this->extractRecommendSpecialIds($data['merchant_home_recommend_special'] ?? [])
            ),
            'mer_avatar' => $data['mer_avatar'] ?? '',
            'mer_street_view' => $data['mer_street_view'] ?? '',
            'qualification' => $merchant->qualification ?? [],
            'description' => $data['description'] ?? '',
            'mer_keyword' => $data['mer_keyword'] ?? '',
            'mer_address' => $data['mer_address'] ?? '',
            'lat' => $data['lat'] ?? 0,
            'lng' => $data['lng'] ?? 0,
            'mer_notice' => $data['mer_notice'] ?? '',
            'business_hours' => $merchant->business_hours ?? [],
        ];

        // 功能信息
        $funcInfo = [
            'commission_rate' => $data['commission_rate'] ?? 0,
            'product_audit_mode' => $data['product_audit_mode'] ?? 1,
            'product_audit_mode_text' => ($data['product_audit_mode'] ?? 1) == 1 ? '平台审核' : '平台免审核',
            'live_audit_mode' => $data['live_audit_mode'] ?? 1,
            'live_audit_mode_text' => ($data['live_audit_mode'] ?? 1) == 1 ? '平台审核' : '平台免审核',
            'live_product_audit_mode' => $data['live_product_audit_mode'] ?? 1,
            'live_product_audit_mode_text' => ($data['live_product_audit_mode'] ?? 1) == 1 ? '平台审核' : '平台免审核',
            'content_audit_mode' => $data['content_audit_mode'] ?? 1,
            'content_audit_mode_text' => ($data['content_audit_mode'] ?? 1) == 1 ? '平台审核' : '平台免审核',
            'is_recommend' => $data['is_recommend'] ?? 0,
            'is_recommend_text' => ($data['is_recommend'] ?? 0) == 1 ? '是' : '否',
        ];

        return [
            'basicInfo' => $basicInfo,
            'storeInfo' => $storeInfo,
            'funcInfo' => $funcInfo,
        ];
    }

    /**
     * 保存商户（新增/编辑）
     *
     * @param array $data 商户资料、状态或店铺设置字段。
     * @param int|null $id 待编辑的商户标识；为空时新增商户。
     * @return array 新增或编辑后的商户资料。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function saveMerchant(array $data, ?int $id = null): array
    {
        // 处理资质信息JSON
        if (!empty($data['qualification']) && is_array($data['qualification'])) {
            $data['qualification'] = json_encode($data['qualification']);
        }

        // 处理营业时间JSON
        if (!empty($data['business_hours']) && is_array($data['business_hours'])) {
            $data['business_hours'] = json_encode($data['business_hours']);
        }

        if ($id) {
            $this->update($id, $data);
            $this->revokeTokensWhenDisabled($id, $data);
            $merchant = $this->get($id);
            if ($merchant) {
                $this->publishMerchantEvent('merchant.updated', $merchant, [
                    'operation' => 'save_merchant',
                    'changed_keys' => array_values(array_keys($data)),
                ]);
            }
            return $merchant ? $merchant->toArray() : [];
        }

        $result = $this->create($data);
        $merchant = $this->get((int) ($result['id'] ?? 0));
        if ($merchant) {
            $this->publishMerchantEvent('merchant.created', $merchant, [
                'operation' => 'save_merchant',
                'source' => 'merchant_service',
            ]);
        }

        return $merchant ? $merchant->toArray() : [];
    }

    /**
     * 审核商户
     *
     * @param int $id 商户记录标识。
     * @param int $auditStatus 0-待审核 1-已通过 2-已驳回
     * @param string $remark 审核或操作备注。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function auditMerchant(int $id, int $auditStatus, string $remark = ''): void
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $data = [
            'audit_status' => $auditStatus,
            'audit_remark' => $remark,
        ];

        if ($auditStatus == 1) {
            // 审核通过
            $data['status'] = 1; // 启用商户
            $data['audit_time'] = date('Y-m-d H:i:s');
        }

        $this->update($id, $data);
        $updatedMerchant = $this->get($id) ?: $merchant;
        app()->make(BackendMessageEventService::class)->merchantAuditResult($updatedMerchant->toArray(), $auditStatus, $remark);
        $this->publishMerchantEvent('merchant.audit.changed', $updatedMerchant, [
            'operation' => 'audit',
            'audit_status' => $auditStatus,
            'audit_remark' => $remark,
        ]);
    }

    /**
     * 更新商户营业状态
     *
     * @param int $id 商户记录标识。
     * @param int $status 0-休息 1-营业
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function updateBusinessStatus(int $id, int $status): void
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $this->update($id, ['business_status' => $status]);
        $updatedMerchant = $this->get($id) ?: $merchant;
        $this->publishMerchantEvent('merchant.business_status.changed', $updatedMerchant, [
            'operation' => 'business_status',
            'business_status' => $status,
        ]);
    }

    /**
     * 更新商户店铺设置
     *
     * @param int $id 商户记录标识。
     * @param array $data 商户资料、状态或店铺设置字段。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function updateStoreSetting(int $id, array $data): void
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $hasDisplayConfig = array_key_exists('merchant_home_config', $data);
        if ($hasDisplayConfig && !is_array($data['merchant_home_config'])) {
            throw new AdminResponseException('移动端展示配置必须为对象');
        }

        $allowFields = [
            // 店铺信息
            'mer_name', 'mer_avatar', 'mer_banner', 'merchant_home_banner',
            'merchant_home_recommend_special', 'mer_street_view', 'mer_notice',
            'description', 'mer_keyword', 'qualification',
            // 联系方式
            'contact_name', 'contact_phone', 'mer_address', 'lat', 'lng',
            // 营业信息
            'business_hours', 'business_status',
        ];

        $updateData = [];
        foreach ($allowFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // 处理营业时间JSON
        if (!empty($updateData['business_hours']) && is_array($updateData['business_hours'])) {
            $updateData['business_hours'] = json_encode($updateData['business_hours']);
        }

        // 处理资质信息JSON
        if (!empty($updateData['qualification']) && is_array($updateData['qualification'])) {
            $updateData['qualification'] = json_encode($updateData['qualification']);
        }

        if (array_key_exists('merchant_home_banner', $updateData)) {
            $updateData['merchant_home_banner'] = json_encode(
                $this->normalizeMerchantHomeBanners($updateData['merchant_home_banner']),
                JSON_UNESCAPED_UNICODE
            );
        }

        if (array_key_exists('merchant_home_recommend_special', $updateData)) {
            $updateData['merchant_home_recommend_special'] = json_encode(
                $this->extractRecommendSpecialIds($updateData['merchant_home_recommend_special']),
                JSON_UNESCAPED_UNICODE
            );
        }

        if (empty($updateData) && !$hasDisplayConfig) {
            return;
        }

        // 店铺资料与展示配置必须同时成功；旧客户端不传新字段时保留已保存配置。
        $this->transaction(function () use ($id, $updateData, $data, $hasDisplayConfig): void {
            if ($updateData !== []) {
                $this->update($id, $updateData);
            }
            if ($hasDisplayConfig) {
                app()->make(MerchantMobileDisplayService::class)->saveConfig($id, $data['merchant_home_config']);
            }
        });
        $updatedMerchant = $this->get($id) ?: $merchant;
        $changedKeys = array_values(array_keys($updateData));
        if ($hasDisplayConfig) {
            $changedKeys[] = 'merchant_home_config';
        }
        $this->publishMerchantEvent('merchant.updated', $updatedMerchant, [
            'operation' => 'store_setting',
            'changed_keys' => $changedKeys,
        ]);
    }

    /**
     * 更新商户统计数据
     *
     * @param int $id 商户记录标识。
     * @param string $field 字段名：total_sales|total_orders|follow_count
     * @param float|int $value 增加值
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function updateStatistics(int $id, string $field, float|int $value): void
    {
        $allowFields = ['total_sales', 'total_orders', 'follow_count'];
        if (!in_array($field, $allowFields)) {
            throw new AdminResponseException('非法的统计字段');
        }

        $this->getModel()->where('id', $id)->inc($field, $value)->update();
    }

    /**
     * 发布商户插件事件，只携带最小必要信息。
     *
     * @param array|object|int $merchant 商户模型、数组或商户ID。
     * @param array<string, mixed> $context 事件上下文。
     * @param string $eventName 需要发布的业务事件名称。
     * @return void 无返回值。
     */
    public function publishMerchantEvent(string $eventName, array|object|int $merchant, array $context = []): void
    {
        $snapshot = [];
        if (is_int($merchant)) {
            $row = $this->get($merchant);
            if (is_object($row) && method_exists($row, 'toArray')) {
                $snapshot = $row->toArray();
            } elseif (is_array($row)) {
                $snapshot = $row;
            }
        } elseif (is_object($merchant) && method_exists($merchant, 'toArray')) {
            $snapshot = $merchant->toArray();
        } elseif (is_array($merchant)) {
            $snapshot = $merchant;
        }

        $merchantId = (int) ($snapshot['id'] ?? $context['merchant_id'] ?? 0);
        if ($merchantId <= 0) {
            return;
        }

        $merchantName = trim((string) ($snapshot['mer_name'] ?? $snapshot['name'] ?? ''));
        $payload = [
            'merchant_id' => $merchantId,
            'merchant_name' => $merchantName,
            'name' => $merchantName,
            'status' => (int) ($snapshot['status'] ?? $context['status'] ?? 0),
            'audit_status' => (int) ($snapshot['audit_status'] ?? $context['audit_status'] ?? 0),
            'business_status' => (int) ($snapshot['business_status'] ?? $context['business_status'] ?? 0),
            'operation' => (string) ($context['operation'] ?? ''),
            'meta' => array_diff_key($context, array_flip(['merchant_id', 'status', 'audit_status', 'business_status', 'operation'])),
        ];

        $meta = $payload['meta'];
        if (is_array($meta)) {
            ksort($meta);
        }
        $metaHash = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        app()->make(PluginBusinessEventService::class)->publish(
            $eventName,
            $payload,
            [
                'subject_type' => 'merchant',
                'subject_id' => (string) $merchantId,
                'merchant_id' => $merchantId,
                'idempotency_key' => 'merchant:' . substr(hash('sha256', implode('|', [
                    $eventName,
                    (string) $merchantId,
                    (string) ($snapshot['update_time'] ?? $snapshot['create_time'] ?? ''),
                    $metaHash === false ? '' : $metaHash,
                ])), 0, 64),
            ]
        );
    }

    /**
     * 移动端切换店铺/平台关注状态。
     *
     * @param int $userId 用户ID。
     * @param int $merchantId 商户ID，0 表示平台。
     * @return array 切换后的关注状态和关注数量。
     * @throws AdminResponseException
     */
    public function toggleMobileFollow(int $userId, int $merchantId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }
        if ($merchantId < 0) {
            throw new AdminResponseException('商户ID不能小于0');
        }
        if ($merchantId === 0) {
            return $this->toggleMobilePlatformFollow($userId);
        }

        $merchant = $this->get($merchantId);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $favoriteService = app()->make(UserFavoriteService::class);
        $wasFollowed = $favoriteService->isFavorite($userId, UserFavoriteService::TARGET_TYPE_MERCHANT, $merchantId);
        $result = $favoriteService->toggleFavorite($userId, UserFavoriteService::TARGET_TYPE_MERCHANT, $merchantId, $merchantId);
        $isFollowed = (bool)($result['is_favorite'] ?? false);

        if ($wasFollowed !== $isFollowed) {
            if ($isFollowed) {
                $this->updateStatistics($merchantId, 'follow_count', 1);
            } elseif ((int)($merchant['follow_count'] ?? 0) > 0) {
                $this->updateStatistics($merchantId, 'follow_count', -1);
            }
        }

        $nextCount = max(0, (int)($merchant['follow_count'] ?? 0) + ($wasFollowed === $isFollowed ? 0 : ($isFollowed ? 1 : -1)));

        return [
            'merchant_id' => $merchantId,
            'target_type' => UserFavoriteService::TARGET_TYPE_MERCHANT,
            'target_id' => $merchantId,
            'is_followed' => $isFollowed,
            'follow_count' => $nextCount,
            'follow_count_text' => $this->formatCountText($nextCount, '粉丝'),
        ];
    }

    /**
     * 获取移动端店铺/平台关注状态。
     *
     * @param int $userId 用户ID。
     * @param int $merchantId 商户ID，0 表示平台。
     * @return array 当前用户的关注状态和店铺关注数量。
     * @throws AdminResponseException
     */
    public function getMobileFollowStatus(int $userId, int $merchantId): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }
        if ($merchantId < 0) {
            throw new AdminResponseException('商户ID不能小于0');
        }
        if ($merchantId === 0) {
            return $this->getMobilePlatformFollowStatus($userId);
        }

        $merchant = $this->get($merchantId);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $isFollowed = app()->make(UserFavoriteService::class)->isFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_MERCHANT,
            $merchantId
        );
        $followCount = max(0, (int)($merchant['follow_count'] ?? 0));

        return [
            'merchant_id' => $merchantId,
            'target_type' => UserFavoriteService::TARGET_TYPE_MERCHANT,
            'target_id' => $merchantId,
            'is_followed' => $isFollowed,
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
        ];
    }

    /**
     * 切换移动端平台关注状态。
     *
     * @param int $userId 用户ID。
     * @return array 切换后的平台关注状态和数量。
     * @throws AdminResponseException
     */
    protected function toggleMobilePlatformFollow(int $userId): array
    {
        $favoriteService = app()->make(UserFavoriteService::class);
        $result = $favoriteService->toggleFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_PLATFORM,
            0,
            0
        );
        $isFollowed = (bool)($result['is_favorite'] ?? false);
        $followCount = $this->getMobilePlatformFollowCount();

        return [
            'merchant_id' => 0,
            'target_type' => UserFavoriteService::TARGET_TYPE_PLATFORM,
            'target_id' => 0,
            'is_followed' => $isFollowed,
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
        ];
    }

    /**
     * 获取移动端平台关注状态。
     *
     * @param int $userId 用户ID。
     * @return array 当前用户的平台关注状态和数量。
     */
    protected function getMobilePlatformFollowStatus(int $userId): array
    {
        $favoriteService = app()->make(UserFavoriteService::class);
        $isFollowed = $favoriteService->isFavorite(
            $userId,
            UserFavoriteService::TARGET_TYPE_PLATFORM,
            0
        );
        $followCount = $this->getMobilePlatformFollowCount();

        return [
            'merchant_id' => 0,
            'target_type' => UserFavoriteService::TARGET_TYPE_PLATFORM,
            'target_id' => 0,
            'is_followed' => $isFollowed,
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
        ];
    }

    /**
     * 获取平台关注人数。
     *
     * @return int 平台当前关注人数。
     */
    protected function getMobilePlatformFollowCount(): int
    {
        return (int)app()->make(UserFavoriteService::class)->getModel()
            ->where('target_type', UserFavoriteService::TARGET_TYPE_PLATFORM)
            ->where('target_id', 0)
            ->where('status', 1)
            ->count();
    }

    /**
     * 获取移动端商户搜索列表.
     *
     * @param array $params 查询参数.
     * @return array 公开商户搜索列表及分页信息。
     */
    public function getMobileSearchList(array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(30, (int)($params['limit'] ?? 10)));
        $keyword = trim((string)($params['keyword'] ?? ''));
        $sort = trim((string)($params['sort'] ?? 'default'));

        $query = $this->getModel()
            ->where('status', 1)
            ->where('audit_status', AuditStatusEnum::APPROVED);

        if ($keyword !== '') {
            $query->where(function ($subQuery) use ($keyword): void {
                $subQuery->whereLike('mer_name', '%' . $keyword . '%')
                    ->whereOr('mer_keyword', 'like', '%' . $keyword . '%')
                    ->whereOr('description', 'like', '%' . $keyword . '%');
            });
        }

        $total = (int)(clone $query)->count();
        $list = $this->applyMobileSearchSort($query, $sort)
            ->field([
                'id', 'mer_name', 'logo', 'mer_avatar', 'mer_banner',
                'description', 'mer_keyword', 'business_status',
                'follow_count', 'total_sales', 'total_orders',
                'is_recommend', 'sort', 'create_time'
            ])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => array_map(fn(array $item): array => $this->formatMobileSearchMerchant($item), $list),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
            'has_more' => $page * $limit < $total,
        ];
    }

    /**
     * 获取商户统计数据
     *
     * @param int $id 商户记录标识。
     * @return array 当前商户经营统计数据。
     * @throws AdminResponseException
     */
    public function getMerchantStatistics(int $id): array
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        return [
            'total_sales' => $merchant['total_sales'] ?? 0,
            'total_orders' => $merchant['total_orders'] ?? 0,
            'follow_count' => $merchant['follow_count'] ?? 0,
        ];
    }

    /**
     * 设置推荐状态
     *
     * @param int $id 商户记录标识。
     * @param int $isRecommend 0-否 1-是
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function setRecommend(int $id, int $isRecommend): void
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $this->update($id, ['is_recommend' => $isRecommend]);
    }

    /**
     * 设置排序
     *
     * @param int $id 商户记录标识。
     * @param int $sort 商户展示排序值。
     * @return void 无返回值。
     * @throws AdminResponseException
     */
    public function setMerchantSort(int $id, int $sort): void
    {
        $merchant = $this->get($id);
        if (!$merchant) {
            throw new AdminResponseException('商户不存在');
        }

        $this->update($id, ['sort' => $sort]);
    }

    /**
     * 格式化移动端商家首页商户信息。
     *
     * @param array $merchant 商户数据
     * @return array 商户首页所需的公开资料和展示文案。
     */
    protected function formatMobileHomeMerchant(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $logo = trim((string)($merchant['logo'] ?? ''));
        $avatar = trim((string)($merchant['mer_avatar'] ?? ''));

        return [
            'id' => $merchantId,
            'name' => trim((string)($merchant['mer_name'] ?? '')),
            'logo' => $logo !== '' ? $logo : $avatar,
            'avatar' => $avatar,
            'banner' => trim((string)($merchant['mer_banner'] ?? '')),
            'description' => trim((string)($merchant['description'] ?? '')),
            'follow_count' => (int)($merchant['follow_count'] ?? 0),
            'follow_count_text' => $this->formatCountText((int)($merchant['follow_count'] ?? 0), '粉丝'),
            'total_sales' => (float)($merchant['total_sales'] ?? 0),
            'total_orders' => (int)($merchant['total_orders'] ?? 0),
            'business_status' => (int)($merchant['business_status'] ?? 1),
            'business_status_text' => (int)($merchant['business_status'] ?? 1) === 1 ? '营业中' : '休息中',
        ];
    }

    /**
     * 格式化移动端商家店铺详情。
     *
     * @param array $merchant 商户数据
     * @return array 附带资质、营业时间和统计文案的店铺详情。
     */
    protected function formatMobileDetailMerchant(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $qualification = $this->normalizeQualification($merchant['qualification'] ?? []);
        $businessHours = $this->normalizeBusinessHours($merchant['business_hours'] ?? []);

        return [
            'id' => $merchantId,
            'mer_name' => trim((string)($merchant['mer_name'] ?? '')),
            'mer_phone' => trim((string)($merchant['mer_phone'] ?? '')),
            'mer_address' => trim((string)($merchant['mer_address'] ?? '')),
            'contact_name' => trim((string)($merchant['contact_name'] ?? '')),
            'contact_phone' => trim((string)($merchant['contact_phone'] ?? '')),
            'logo' => trim((string)($merchant['logo'] ?? '')),
            'mer_avatar' => trim((string)($merchant['mer_avatar'] ?? '')),
            'mer_banner' => trim((string)($merchant['mer_banner'] ?? '')),
            'merchant_home_banner' => $this->normalizeMerchantHomeBanners($merchant['merchant_home_banner'] ?? []),
            'merchant_home_recommend_special' => $this->extractRecommendSpecialIds(
                $merchant['merchant_home_recommend_special'] ?? []
            ),
            'mer_street_view' => trim((string)($merchant['mer_street_view'] ?? '')),
            'mer_notice' => trim((string)($merchant['mer_notice'] ?? '')),
            'description' => trim((string)($merchant['description'] ?? '')),
            'mer_keyword' => trim((string)($merchant['mer_keyword'] ?? '')),
            'business_hours' => $businessHours,
            'business_status' => (int)($merchant['business_status'] ?? 1),
            'business_status_text' => (int)($merchant['business_status'] ?? 1) === 1 ? '营业中' : '休息中',
            'qualification' => $qualification,
            'lat' => (float)($merchant['lat'] ?? 0),
            'lng' => (float)($merchant['lng'] ?? 0),
            'mer_class' => (int)($merchant['mer_class'] ?? 2),
            'mer_class_text' => (int)($merchant['mer_class'] ?? 2) === 1 ? '自营' : '非自营',
            'follow_count' => (int)($merchant['follow_count'] ?? 0),
            'follow_count_text' => $this->formatCountText((int)($merchant['follow_count'] ?? 0), '粉丝'),
            'total_sales' => (float)($merchant['total_sales'] ?? 0),
            'total_orders' => (int)($merchant['total_orders'] ?? 0),
            'create_time' => (string)($merchant['create_time'] ?? ''),
            'audit_status' => (int)($merchant['audit_status'] ?? 0),
            'status' => (int)($merchant['status'] ?? 0),
        ];
    }

    /**
     * 应用移动端商户搜索排序.
     *
     * @param mixed $query 查询对象.
     * @param string $sort 排序标识.
     * @return mixed 已应用搜索排序规则的查询对象。
     */
    protected function applyMobileSearchSort(mixed $query, string $sort): mixed
    {
        return match ($sort) {
            'sales' => $query->order('total_sales', 'desc')->order('total_orders', 'desc')->order('id', 'desc'),
            'fans' => $query->order('follow_count', 'desc')->order('id', 'desc'),
            'latest' => $query->order('create_time', 'desc')->order('id', 'desc'),
            default => $query->order('is_recommend', 'desc')->order('sort', 'desc')->order('follow_count', 'desc')->order('id', 'desc'),
        };
    }

    /**
     * 格式化移动端商户搜索项.
     *
     * @param array $merchant 商户数据.
     * @return array 商户搜索列表所需的展示字段。
     */
    protected function formatMobileSearchMerchant(array $merchant): array
    {
        $merchantId = (int)($merchant['id'] ?? 0);
        $logo = trim((string)($merchant['logo'] ?? ''));
        $avatar = trim((string)($merchant['mer_avatar'] ?? ''));
        $banner = trim((string)($merchant['mer_banner'] ?? ''));
        $cover = $banner !== '' ? $banner : ($avatar !== '' ? $avatar : $logo);
        $followCount = (int)($merchant['follow_count'] ?? 0);

        return [
            'id' => $merchantId,
            'name' => trim((string)($merchant['mer_name'] ?? '')),
            'keyword' => trim((string)($merchant['mer_keyword'] ?? '')),
            'logo' => $logo !== '' ? $logo : $avatar,
            'avatar' => $avatar,
            'cover' => $cover,
            'description' => trim((string)($merchant['description'] ?? '')),
            'business_status' => (int)($merchant['business_status'] ?? 1),
            'business_status_text' => (int)($merchant['business_status'] ?? 1) === 1 ? '营业中' : '休息中',
            'follow_count' => $followCount,
            'follow_count_text' => $this->formatCountText($followCount, '粉丝'),
            'total_sales' => (float)($merchant['total_sales'] ?? 0),
            'total_orders' => (int)($merchant['total_orders'] ?? 0),
            'is_recommend' => (int)($merchant['is_recommend'] ?? 0),
            'route_url' => '/pages/merchant/user/index?merchant_id=' . $merchantId,
            'create_time' => (string)($merchant['create_time'] ?? ''),
        ];
    }

    /**
     * 格式化商家首页幻灯片配置。
     *
     * @param mixed $items 商户表首页轮播图配置
     * @return array 提取的商户首页轮播图片和默认空跳转字段。
     */
    protected function formatMobileHomeBanners(mixed $items): array
    {
        $result = [];
        foreach ($this->normalizeMerchantHomeBanners($items) as $index => $image) {
            $result[] = [
                'id' => $index + 1,
                'image' => $image,
                'link_type' => '',
                'link_value' => '',
            ];
        }

        return $result;
    }

    /**
     * 归一化商家首页轮播图，最多保留8张。
     *
     * @param mixed $items 商户表轮播图原始值
     * @return array 提取非空地址、去除首尾空白并截取最多八张后的图片列表，不校验地址合法性。
     */
    protected function normalizeMerchantHomeBanners(mixed $items): array
    {
        if (is_string($items)) {
            $value = trim($items);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);
            $items = is_array($decoded) ? $decoded : [$value];
        }

        if (!is_array($items)) {
            return [];
        }

        $images = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $image = trim($item);
            } elseif (is_array($item)) {
                $image = trim((string)($item['image'] ?? $item['url'] ?? $item['path'] ?? ''));
            } else {
                $image = '';
            }

            if ($image !== '') {
                $images[] = $image;
            }
        }

        return array_slice($images, 0, 8);
    }

    /**
     * 从商户首页推荐专题配置中提取专题ID。
     *
     * @param mixed $items 推荐专题原始值
     * @return array 兼容旧配置并保留顺序的推荐专题标识。
     */
    protected function extractRecommendSpecialIds(mixed $items): array
    {
        if (is_string($items)) {
            $value = trim($items);
            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);
            $items = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (!is_array($items)) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $specialId = (int)($item['special_id'] ?? $item['id'] ?? $item['value'] ?? 0);
            } else {
                $specialId = (int)$item;
            }

            if ($specialId > 0) {
                $ids[] = $specialId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * 归一化资质信息。
     *
     * @param mixed $qualification 原始资质字段
     * @return array 兼容历史存储格式的资质列表。
     */
    protected function normalizeQualification(mixed $qualification): array
    {
        if (is_string($qualification)) {
            $decoded = json_decode($qualification, true);
            $qualification = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($qualification)) {
            $qualification = [];
        }

        $otherCerts = $qualification['other_certs'] ?? [];
        if (!is_array($otherCerts)) {
            $otherCerts = [];
        }

        return [
            'business_license' => trim((string)($qualification['business_license'] ?? '')),
            'legal_person_idcard' => trim((string)($qualification['legal_person_idcard'] ?? '')),
            'bank_account' => trim((string)($qualification['bank_account'] ?? '')),
            'other_certs' => array_values($otherCerts),
        ];
    }

    /**
     * 归一化营业时间。
     *
     * @param mixed $businessHours 原始营业时间字段
     * @return array 规范化后的商户营业时间配置。
     */
    protected function normalizeBusinessHours(mixed $businessHours): array
    {
        if (is_string($businessHours)) {
            $decoded = json_decode($businessHours, true);
            $businessHours = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($businessHours)) {
            $businessHours = [];
        }

        return [
            'start' => trim((string)($businessHours['start'] ?? '09:00')),
            'end' => trim((string)($businessHours['end'] ?? '18:00')),
            'is_all_day' => (bool)($businessHours['is_all_day'] ?? false),
        ];
    }

    /**
     * 格式化数量文案。
     *
     * @param int $count 数量
     * @param string $suffix 后缀
     * @return string 用于移动端展示的数量文案。
     */
    protected function formatCountText(int $count, string $suffix = ''): string
    {
        if ($count >= 10000) {
            $value = rtrim(rtrim(number_format($count / 10000, $count % 10000 === 0 ? 0 : 1, '.', ''), '0'), '.');
            return $value . 'w' . $suffix;
        }

        return $count . $suffix;
    }
}
