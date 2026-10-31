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
use app\common\service\user\UserAssetService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户资产控制器。
 */
class UserAsset extends BaseController
{
    /**
     * 用户资产服务.
     *
     * @var UserAssetService
     */
    #[Inject]
    protected UserAssetService $assetService;

    /**
     * 获取用户资产及月度统计。
     *
     * @return Response 返回余额或积分统计。
     */
    public function statistics(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);
        $type = trim((string) $this->request->get('type', UserAssetService::TYPE_BALANCE));

        return $this->success('获取成功', $this->assetService->getMobileStatistics($userId, $type));
    }

    /**
     * 获取用户余额变动记录列表。
     *
     * GET 参数：
     * - type string 资产类型，可选 balance/points。
     * - change_type string 变动方向，可选 in/out。
     * - page int 页码，默认 1。
     * - limit int 每页数量，最大 50。
     *
     * @return Response 返回资产流水分页列表。
     */
    public function logs(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);
        $type = trim((string) $this->request->get('type', UserAssetService::TYPE_BALANCE));
        $changeType = trim((string) $this->request->get('change_type', ''));
        $page = max(1, (int) $this->request->get('page/d', 1));
        $limit = max(1, min(50, (int) $this->request->get('limit/d', 10)));

        return $this->success('获取成功', $this->assetService->getMobileLogs(
            $userId,
            $type,
            $changeType,
            $page,
            $limit
        ));
    }
}
