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
use app\common\enum\CombinationDataTypeEnum;
use app\common\service\user\UserAssetService;
use app\common\service\user\UserWithdrawService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户提现控制器。
 */
class UserWithdraw extends BaseController
{
    /**
     * 用户资产服务.
     *
     * @var UserAssetService
     */
    #[Inject]
    protected UserAssetService $assetService;

    #[Inject]
    protected UserWithdrawService $withdrawService;

    /**
     * 获取提现页面配置数据。
     *
     * GET 参数：
     * - source string 提现来源，仅支持 commission；余额不可提现。
     *
     * @return Response 返回提现配置、可提现资产摘要和快捷金额。
     */
    public function index(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);
        $config = $this->withdrawService->getCommissionWithdrawConfig();

        // 2. 用户资产摘要
        $asset     = $this->assetService->getInfo($userId);
        $available = (float) ($asset->commission ?? 0);

        $summary = [
            'available' => $available,
            'total'     => $available,
            'pending'   => 0,
        ];

        // 3. 快捷金额 — 从 combination_data 读取
        $rawAmounts   = db_combination_data(CombinationDataTypeEnum::WITHDRAWAL_AMOUNT);
        $quickAmounts = [];
        foreach ($rawAmounts as $item) {
            $val = (float) ($item['amount'] ?? 0);
            if ($val > 0) {
                $quickAmounts[] = $val;
            }
        }
        $quickAmounts = array_values(array_unique($quickAmounts));
        sort($quickAmounts);
        if (empty($quickAmounts)) {
            $quickAmounts = [100, 500, 1000, 5000];
        }

        return $this->success('ok', compact('config', 'summary', 'quickAmounts'));
    }

    /**
     * 提交提现申请.
     *
     * POST 参数：
     * - source string 提现来源，仅支持 commission；余额不可提现。
     * - amount float 提现金额。
     *
     * @return Response
     */
    public function submit(): Response
    {
        $userId = (int) ($this->request->user_id ?? 0);
        if ($userId <= 0) {
            return $this->fail('请先登录');
        }

        $data = [
            'source' => 'commission',
            'amount' => $this->request->post('amount', 0),
        ];

        $result = $this->withdrawService->submitWithdraw($userId, $data);
        return $this->success('提现申请已提交', $result);
    }
}
