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

namespace app\controller\mobile\order;

use app\BaseController;
use app\common\model\order\Cart as CartModel;
use app\common\service\order\CartService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端购物车控制器.
 */
class Cart extends BaseController
{
    /**
     * 购物车服务.
     *
     * @var CartService
     */
    #[Inject]
    protected CartService $cartService;

    /**
     * 获取当前用户购物车列表.
     *
     * GET 参数：
     * - include_new int 是否包含立即购买临时项，1包含，0不包含。
     *
     * @return Response 返回购物车分组列表。
     */
    public function index(): Response
    {
        $userId = $this->request->user_id;
        $includeNew = (bool)$this->request->get('include_new/d', 0);

        return $this->success('获取成功', $this->cartService->getList($userId, $includeNew));
    }

    /**
     * 加入购物车.
     *
     * POST 参数：
     * - goods_id int 商品或内容 ID。
     * - cart_num int 购买数量，默认 1。
     * - product_attr_unique string SKU 唯一值，普通商品多规格时传入。
     * - order_type int 订单类型，默认商品订单。
     * - plugin_payload array 插件商品载荷，服务端按商品归属插件保留命名空间。
     *
     * @return Response 返回新增后的购物车记录。
     */
    public function add(): Response
    {
        $userId = $this->request->user_id;
        $goodsId = (int)$this->request->post('goods_id/d', 0);
        $cartNum = (int)$this->request->post('cart_num/d', 1);
        $productAttrUnique = trim((string)$this->request->post('product_attr_unique', ''));
        $orderType = (int)$this->request->post('order_type/d', CartModel::ORDER_TYPE_GOODS);
        $pluginPayload = $this->request->post('plugin_payload/a', []);

        if ($goodsId <= 0) {
            return $this->fail('请选择要加入购物车的商品');
        }

        $data = $this->cartService->add($userId, $goodsId, $cartNum, $productAttrUnique, $orderType, $pluginPayload);

        return $this->success('加入购物车成功', $data);
    }

    /**
     * 创建立即购买临时购物车项.
     *
     * POST 参数：
     * - goods_id int 商品或内容 ID。
     * - cart_num int 购买数量，默认 1。
     * - product_attr_unique string SKU 唯一值，普通商品多规格时传入。
     * - order_type int 订单类型，默认商品订单。
     * - plugin_payload array 插件商品载荷，服务端按商品归属插件保留命名空间。
     *
     * @return Response 返回立即购买购物车项。
     */
    public function buyNow(): Response
    {
        $userId = $this->request->user_id;
        $goodsId = (int)$this->request->post('goods_id/d', 0);
        $cartNum = (int)$this->request->post('cart_num/d', 1);
        $productAttrUnique = trim((string)$this->request->post('product_attr_unique', ''));
        $orderType = (int)$this->request->post('order_type/d', CartModel::ORDER_TYPE_GOODS);
        $pluginPayload = $this->request->post('plugin_payload/a', []);

        if ($goodsId <= 0) {
            return $this->fail('请选择要购买的商品');
        }

        $data = $this->cartService->buyNow($userId, $goodsId, $cartNum, $productAttrUnique, $orderType, $pluginPayload);

        return $this->success('获取成功', $data);
    }

    /**
     * 修改购物车商品数量.
     *
     * POST 参数：
     * - cart_id int 购物车 ID；兼容 id。
     * - cart_num int 调整后的数量。
     *
     * @return Response 返回修改后的购物车记录。
     */
    public function changeNum(): Response
    {
        $userId = $this->request->user_id;
        $cartId = (int)$this->request->post('cart_id/d', $this->request->post('id/d', 0));
        $cartNum = (int)$this->request->post('cart_num/d', 1);

        if ($cartId <= 0) {
            return $this->fail('请选择要修改的购物车商品');
        }

        $data = $this->cartService->changeNum($userId, $cartId, $cartNum);

        return $this->success('修改成功', $data);
    }

    /**
     * 修改购物车商品规格.
     *
     * POST 参数：
     * - cart_id int 购物车 ID；兼容 id。
     * - product_attr_unique string 商品规格唯一值。
     *
     * @return Response 返回修改后的购物车记录。
     */
    public function changeSku(): Response
    {
        $userId = $this->request->user_id;
        $cartId = (int)$this->request->post('cart_id/d', $this->request->post('id/d', 0));
        $productAttrUnique = trim((string)$this->request->post('product_attr_unique', ''));

        if ($cartId <= 0) {
            return $this->fail('请选择要修改的购物车商品');
        }

        if ($productAttrUnique === '') {
            return $this->fail('请选择商品规格');
        }

        $data = $this->cartService->changeSku($userId, $cartId, $productAttrUnique);

        return $this->success('修改成功', $data);
    }

    /**
     * 删除购物车商品.
     *
     * POST 参数：
     * - ids array|string 购物车 ID 列表，支持数组或逗号分隔字符串。
     *
     * @return Response 返回删除数量。
     */
    public function delete(): Response
    {
        $userId = $this->request->user_id;
        $rawIds = $this->request->post('ids', '');
        $cartIds = parse_ids(
            $this->request->post('ids/a', []),
            is_array($rawIds) ? '' : (string)$rawIds
        );

        if ($cartIds === []) {
            return $this->fail('请选择要删除的购物车商品');
        }

        if (count($cartIds) > 100) {
            return $this->fail('单次最多删除 100 条购物车记录');
        }

        $deleted = $this->cartService->deleteCart($userId, $cartIds);

        return $this->success('删除成功', ['deleted' => $deleted]);
    }

    /**
     * 获取购物车结算预览.
     *
     * POST 参数：
     * - cart_ids array|string 购物车 ID 列表，支持数组或逗号分隔字符串。
     * - is_new int 是否立即购买，1是，0否。
     * - plugin_payload array 插件结算载荷；商品归属和持久化载荷以服务端记录为准。
     *
     * @return Response 返回结算商品、金额、可用优惠等预览数据。
     */
    public function settlement(): Response
    {
        $userId = $this->request->user_id;
        $isNew = (bool)$this->request->post('is_new/d', 0);
        $rawCartIds = $this->request->post('cart_ids', '');
        $cartIds = parse_ids(
            $this->request->post('cart_ids/a', []),
            is_array($rawCartIds) ? '' : (string)$rawCartIds
        );
        $pluginPayload = $this->request->post('plugin_payload/a', []);

        $data = $this->cartService->getSettlementPreview($userId, $cartIds, $isNew, true, $pluginPayload);

        return $this->success('获取成功', $data);
    }
}
