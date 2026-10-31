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

namespace app\controller\mobile\payment;

use app\BaseController;
use app\common\enum\PayTypeEnum;
use app\common\enum\PaymentTerminalEnum;
use app\common\service\setting\PaymentConfigService;
use app\common\service\user\UserAssetService;
use think\Response;

/**
 * 移动端支付配置控制器。
 */
class Payment extends BaseController
{
    /**
     * 获取当前终端可用支付方式列表。
     */
    public function methods(): Response
    {
        $userId = (int)($this->request->user_id ?? 0);
        $terminal = $this->resolveTerminal($this->getRequestTerminal());

        $methods = $this->filterSupportedMethods(
            app()->make(PaymentConfigService::class)->getEnabledMethodsByTerminal($terminal)
        );
        $balance = 0.0;
        if ($userId > 0) {
            $asset = app()->make(UserAssetService::class)->getInfo($userId);
            $balance = (float)($asset ? ($asset['balance'] ?? 0) : 0);
        }

        return $this->success('获取成功', [
            'methods' => $methods,
            'balance' => $balance,
        ]);
    }

    /**
     * 将前端终端标识映射为后端支付终端枚举值。
     */
    protected function resolveTerminal(string $terminal): string
    {
        return match ($terminal) {
            'miniapp', 'mini_program', 'mp_weixin', 'wechat_mp' => PaymentTerminalEnum::TERMINAL_MP_WEIXIN,
            'wechat-h5', 'official_account', 'wechat_h5', 'wechat_oa' => PaymentTerminalEnum::TERMINAL_WECHAT_H5,
            'pc' => PaymentTerminalEnum::TERMINAL_PC,
            default => PaymentTerminalEnum::TERMINAL_H5,
        };
    }

    /**
     * 获取当前请求终端标识，优先使用统一请求头，兼容旧 query 参数。
     */
    protected function getRequestTerminal(): string
    {
        $headerTerminal = trim((string)$this->request->header('X-Terminal-Type', ''));
        if ($headerTerminal !== '') {
            return $headerTerminal;
        }

        return trim((string)$this->request->get('terminal', ''));
    }

    /**
     * 支付弹窗只允许余额、微信和支付宝。
     */
    protected function filterSupportedMethods(array $methods): array
    {
        $supportedTypes = [
            PayTypeEnum::PAY_TYPE_YUE,
            PayTypeEnum::PAY_TYPE_WECHAT,
            PayTypeEnum::PAY_TYPE_ALIPAY,
        ];

        return array_values(array_filter($methods, static function (array $method) use ($supportedTypes) {
            return in_array((string)($method['pay_type'] ?? ''), $supportedTypes, true);
        }));
    }
}
