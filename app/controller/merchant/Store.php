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

namespace app\controller\merchant;

use app\common\service\merchant\MerchantService;
use think\annotation\Inject;
use think\Response;

/**
 * 管理当前认证商户店铺资料、营业状态及移动首页展示设置，不接受客户端替换商户归属。
 *
 * @package app\controller\merchant
 */
class Store extends AuthController
{
    /**
     * 当前商户店铺设置的业务服务。
     *
     * @var MerchantService
     */
    #[Inject]
    protected MerchantService $service;

    /**
     * 查询当前商户的完整店铺设置，包括资料、营业与移动展示配置。
     *
     * @return Response 包含当前商户完整设置的响应。
     */
    public function info(): Response
    {
        $info = $this->service->getStoreFullInfo($this->merchantId);
        return $this->success($info);
    }

    /**
     * 保存当前商户店铺设置，旧请求缺少展示配置时不覆盖已保存配置。
     *
     * @return Response 店铺设置更新结果响应。
     */
    public function updateInfo(): Response
    {
        $data = $this->request->postMore([
            // 店铺信息
            ['mer_name', ''],
            ['mer_avatar', ''],
            ['mer_banner', ''],
            ['merchant_home_banner', []],
            ['merchant_home_recommend_special', []],
            ['mer_street_view', ''],
            ['mer_notice', ''],
            ['description', ''],
            ['mer_keyword', ''],
            ['qualification', []],
            // 联系方式
            ['contact_name', ''],
            ['contact_phone', ''],
            ['mer_address', ''],
            ['lat', 0],
            ['lng', 0],
            // 营业信息
            ['business_hours', []],
            ['business_status', 1],
            ['plugin_payload', []],
        ]);

        // 不给新配置字段设置默认值，避免旧版后台保存资料时覆盖展示配置。
        $posted = $this->request->post();
        if (array_key_exists('merchant_home_config', $posted)) {
            $data['merchant_home_config'] = $posted['merchant_home_config'];
        }

        $this->service->updateStoreSetting($this->merchantId, $data);
        return $this->success('更新成功');
    }

    /**
     * 读取目标营业状态并更新当前商户店铺。
     *
     * @return Response 营业状态更新结果响应。
     */
    public function businessStatus(): Response
    {
        $data = $this->request->postMore([
            ['business_status', 1],
        ]);

        $this->service->updateBusinessStatus($this->merchantId, (int)$data['business_status']);
        return $this->success('操作成功');
    }
}
