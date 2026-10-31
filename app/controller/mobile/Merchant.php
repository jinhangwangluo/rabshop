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

namespace app\controller\mobile;

use app\BaseController;
use app\common\exception\AdminResponseException;
use app\common\service\merchant\MerchantMobileDisplayService;
use app\common\service\merchant\MerchantService;
use think\annotation\Inject;
use think\Response;

/**
 * 提供移动端商户公开资料、首页模块、分类及用户关注接口。
 */
class Merchant extends BaseController
{
    /**
     * 商户服务.
     *
     * @var MerchantService
     */
    #[Inject]
    protected MerchantService $service;

    /**
     * 首页与分类展示服务。
     */
    #[Inject]
    protected MerchantMobileDisplayService $displayService;

    /**
     * 查询商户首页第一页数据，包括公开资料与按配置顺序排列的内容模块。
     *
     * GET 参数：
     * - merchant_id int 商户 ID。
     *
     * @return Response 包含商户资料、幻灯片、展示配置与模块第一页，或业务失败的响应。
     */
    public function home(): Response
    {
        $merchantId = (int)$this->request->get('merchant_id/d', 0);
        if ($merchantId <= 0) {
            return $this->fail('商户ID不能为空');
        }


        $data = $this->service->getMobileHome($merchantId, (int)($this->request->user_id ?? 0));
        return $this->success('获取成功', $data);
    }

    /**
     * 按商户配置分页加载首页最后启用模块，客户端不能扩大每页数量。
     *
     * GET参数：merchant_id、type、page；每页数量使用商户配置，不能由客户端扩大。
     *
     * @return Response 包含模块类型、列表及分页信息的响应。
     */
    public function homeSection(): Response
    {
        $type = $this->request->get('type', '');
        if (!is_string($type)) {
            return $this->fail('首页模块类型错误');
        }
        return $this->success('获取成功', $this->displayService->getHomeSection(
            (int)$this->request->get('merchant_id/d', 0),
            $type,
            ['page' => $this->request->get('page', null)],
            (int)($this->request->user_id ?? 0)
        ));
    }

    /**
     * 查询商户设置的专题或商品分类展示模式。
     *
     * GET参数：merchant_id。
     *
     * @return Response 包含展示模式和分类树的响应。
     */
    public function category(): Response
    {
        return $this->success('获取成功', $this->displayService->getCategory(
            (int)$this->request->get('merchant_id/d', 0)
        ));
    }

    /**
     * 按选中分类分页查询商户专题或商品。
     *
     * GET参数：merchant_id、category_id、page、limit。
     *
     * @return Response 包含展示模式、列表及分页信息的响应。
     */
    public function categoryList(): Response
    {
        return $this->success('获取成功', $this->displayService->getCategoryList(
            (int)$this->request->get('merchant_id/d', 0),
            (int)$this->request->get('category_id/d', 0),
            ['page' => $this->request->get('page', null), 'limit' => $this->request->get('limit', null)],
            (int)($this->request->user_id ?? 0)
        ));
    }

    /**
     * 读取指定商户公开店铺资料。
     *
     * GET 参数：
     * - merchant_id int 商户 ID。
     *
     * @return Response 包含商户公开资料或业务失败的响应。
     */
    public function detail(): Response
    {
        $merchantId = (int)$this->request->get('merchant_id/d', 0);
        if ($merchantId <= 0) {
            return $this->fail('商户ID不能为空');
        }


        $data = $this->service->getMobileDetail($merchantId);
        return $this->success('获取成功', $data);
    }

    /**
     * 切换当前用户对商户或平台的关注状态，平台以商户 ID 为 0 表示。
     *
     * POST 参数：
     * - merchant_id int 商户 ID，0 表示平台。
     *
     * @return Response 包含关注状态与粉丝数，或业务失败的响应。
     */
    public function toggleFollow(): Response
    {
        $merchantIdInput = $this->request->post('merchant_id', null);
        if ($merchantIdInput === null || $merchantIdInput === '') {
            return $this->fail('商户ID不能为空');
        }

        $merchantId = (int)$merchantIdInput;
        if ($merchantId < 0) {
            return $this->fail('商户ID不能小于0');
        }


        $data = $this->service->toggleMobileFollow((int)($this->request->user_id ?? 0), $merchantId);
        return $this->success('操作成功', $data);
    }

    /**
     * 查询当前用户对商户或平台的关注状态。
     *
     * GET 参数：
     * - merchant_id int 商户 ID，0 表示平台。
     *
     * @return Response 包含关注状态与粉丝数，或业务失败的响应。
     */
    public function followStatus(): Response
    {
        $merchantIdInput = $this->request->get('merchant_id', null);
        if ($merchantIdInput === null || $merchantIdInput === '') {
            return $this->fail('商户ID不能为空');
        }

        $merchantId = (int)$merchantIdInput;
        if ($merchantId < 0) {
            return $this->fail('商户ID不能小于0');
        }


        $data = $this->service->getMobileFollowStatus((int)($this->request->user_id ?? 0), $merchantId);
        return $this->success('获取成功', $data);
    }
}
