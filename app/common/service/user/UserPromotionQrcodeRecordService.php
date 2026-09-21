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

namespace app\common\service\user;

use app\common\model\user\UserPromotionQrcodeRecord;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 用户推广二维码生成记录服务。
 */
class UserPromotionQrcodeRecordService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型。
     *
     * @return string
     */
    protected function setModel(): string
    {
        return UserPromotionQrcodeRecord::class;
    }

    /**
     * 查找有效推广二维码记录。
     *
     * @param int $userId 用户 ID。
     * @param string $platform 终端平台。
     * @param string $promotionFactor 推广因子。
     * @param string $targetUrl 二维码目标链接或小程序页面。
     * @return array|null 记录数组，不存在返回 null。
     */
    public function findValid(int $userId, string $platform, string $promotionFactor, string $targetUrl): ?array
    {
        $record = $this->getModel()
            ->where('user_id', $userId)
            ->where('platform', trim($platform))
            ->where('promotion_factor', trim($promotionFactor))
            ->where('target_url', trim($targetUrl))
            ->where('status', 1)
            ->find();

        return $record ? $record->toArray() : null;
    }

    /**
     * 获取或创建推广二维码记录。
     *
     * @param int $userId 用户 ID。
     * @param string $platform 终端平台。
     * @param string $promotionFactor 推广因子。
     * @param string $targetUrl 二维码目标链接或小程序页面。
     * @param string $qrcodeUrl 二维码图片地址。
     * @param array $extra 扩展数据。
     * @return array 记录数组。
     */
    public function record(
        int $userId,
        string $platform,
        string $promotionFactor,
        string $targetUrl,
        string $qrcodeUrl = '',
        array $extra = []
    ): array {
        $platform = trim($platform);
        $promotionFactor = trim($promotionFactor);
        $targetUrl = trim($targetUrl);
        $qrcodeUrl = trim($qrcodeUrl);

        $record = $this->getModel()
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->where('promotion_factor', $promotionFactor)
            ->where('target_url', $targetUrl)
            ->where('status', 1)
            ->find();

        $saveData = [
            'qrcode_url' => $qrcodeUrl,
            'extra' => $extra,
            'last_generate_time' => date('Y-m-d H:i:s'),
        ];

        if ($record) {
            $record->save($saveData);
            return $record->refresh()->toArray();
        }

        return $this->create(array_merge($saveData, [
            'user_id' => $userId,
            'platform' => $platform,
            'promotion_factor' => $promotionFactor,
            'target_url' => $targetUrl,
            'status' => 1,
        ]))->toArray();
    }
}
