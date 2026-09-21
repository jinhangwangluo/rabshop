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

namespace app\controller\admin\order;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\order\OrderReviewService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 后台订单评价管理控制器
 */
class OrderReview extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var OrderReviewService
     */
    #[Inject]
    protected OrderReviewService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['order_sn', ''],
            ['star', ''],
            ['is_replied', ''],
        ];
    }

    /**
     * POST 请求获取的字段（评价不支持新增/编辑，留空）
     */
    protected function getRequestFields(): array
    {
        return [];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 评价列表
     */
    public function index(): Response
    {
        $where = $this->request->getMore($this->getIndexFields());
        $data  = $this->service->getAdminList($where);

        return $this->success('获取成功', $data);
    }

    /**
     * 管理员回复评价
     */
    public function reply($id): Response
    {
        if (!$id) {
            return $this->fail('参数错误');
        }

        $reply = trim((string)$this->request->post('reply', ''));
        if ($reply === '') {
            return $this->fail('回复内容不能为空');
        }

        $this->service->adminReply((int)$id, $reply);

        return $this->success('回复成功');
    }

    /**
     * 删除评价（软删除）
     */
    public function delete($id): Response
    {
        if (!$id) {
            return $this->fail('参数错误');
        }

        $this->service->adminDelete((int)$id);

        return $this->success('删除成功');
    }
}
