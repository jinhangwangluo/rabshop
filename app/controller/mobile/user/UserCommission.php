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

namespace app\controller\mobile\user;

use app\BaseController;
use app\common\service\user\UserCommissionService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户佣金控制器。
 */
class UserCommission extends BaseController
{
    /**
     * 用户佣金服务。
     *
     * @var UserCommissionService
     */
    #[Inject]
    protected UserCommissionService $commissionService;

    /**
     * 获取佣金中心首页数据。
     *
     * @return Response
     */
    public function index(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);

        return $this->success('获取成功', $this->commissionService->getMobileIndex($userId));
    }

    /**
     * 获取佣金中心统计数据。
     *
     * @return Response
     */
    public function statistics(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);

        return $this->success('获取成功', $this->commissionService->getMobileStatistics($userId));
    }

    /**
     * 获取佣金收益明细。
     *
     * GET 参数：
     * - page int 页码。
     * - limit int 每页数量。
     * - start_date string 开始日期，格式 YYYY-MM-DD。
     * - end_date string 结束日期，格式 YYYY-MM-DD。
     *
     * @return Response
     */
    public function logs(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);
        $page = max(1, (int) $this->request->get('page/d', 1));
        $limit = max(1, min(50, (int) $this->request->get('limit/d', 10)));
        $startDate = trim((string) $this->request->get('start_date', ''));
        $endDate = trim((string) $this->request->get('end_date', ''));

        return $this->success(
            '获取成功',
            $this->commissionService->getMobileLogs($userId, $page, $limit, $startDate, $endDate)
        );
    }

    /**
     * 获取分销推广海报与二维码数据。
     *
     * @return Response
     */
    public function promotionPoster(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);
        $platform = trim((string) $this->request->header('X-Terminal-Type', ''));

        return $this->success('获取成功', $this->commissionService->getMobilePromotionPoster($userId, $platform));
    }
}
