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

namespace app\controller\mobile\store;

use app\BaseController;
use app\common\service\store\StoreCouponService;
use app\common\service\store\StoreCouponUserService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端优惠券控制器.
 */
class StoreCoupon extends BaseController
{
    /**
     * 优惠券服务.
     *
     * @var StoreCouponService
     */
    #[Inject]
    protected StoreCouponService $service;

    /**
     * 用户优惠券服务.
     *
     * @var StoreCouponUserService
     */
    #[Inject]
    protected StoreCouponUserService $userCouponService;

    /**
     * 获取优惠券领取中心列表.
     *
     * GET 参数：
     * - page int 页码，默认 1。
     * - limit int 每页数量，最大 20。
     * - scope_type string 使用范围类型。
     *
     * @return Response 返回可领取优惠券分页列表。
     */
    public function index(): Response
    {
        $userId = (int)$this->request->user_id;
        $params = [
            'page' => max(1, (int)$this->request->get('page/d', 1)),
            'limit' => max(1, min(20, (int)$this->request->get('limit/d', 10))),
            'scope_type' => $this->request->get('scope_type', ''),
        ];

        return $this->success('获取成功', $this->service->getMobileReceiveList($userId, $params));
    }

    /**
     * 获取我的优惠券列表.
     *
     * GET 参数：
     * - page int 页码，默认 1。
     * - limit int 每页数量，最大 20。
     * - status int|string 优惠券状态，0 未使用，1 已使用，2 已过期，空值表示全部。
     *
     * @return Response 返回用户已领取优惠券分页列表。
     */
    public function userList(): Response
    {
        $userId = (int)$this->request->user_id;
        $params = [
            'page' => max(1, (int)$this->request->get('page/d', 1)),
            'limit' => max(1, min(20, (int)$this->request->get('limit/d', 10))),
            'status' => $this->request->get('status', ''),
        ];

        return $this->success('获取成功', $this->userCouponService->getMobileUserList($userId, $params));
    }

    /**
     * 领取优惠券.
     *
     * POST 参数：
     * - coupon_id int 优惠券 ID；兼容 id。
     *
     * @return Response 返回领取结果。
     */
    public function receive(): Response
    {
        $userId = (int)$this->request->user_id;
        $couponId = (int)$this->request->post('coupon_id/d', $this->request->post('id/d', 0));
        if ($couponId <= 0) {
            return $this->fail('请选择要领取的优惠券');
        }

        return $this->success('领取成功', $this->userCouponService->receiveCoupon($userId, $couponId));
    }
}
