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

namespace Rabbit\service\express;

use Rabbit\interfaces\ConfigInterface;

/**
 * 物流查询驱动接口
 */
interface ExpressInterface
{
    /**
     * 查询物流轨迹
     *
     * @param string $expressNo 快递单号
     * @param string $cpCode 快递公司编码
     * @param string $mobile 收件人或寄件人手机号后四位，接口需要时传入
     * @return array
     */
    public function query(string $expressNo, string $cpCode, string $mobile = ''): array;

    /**
     * 获取驱动标识
     */
    public function getProvider(): string;

    /**
     * 设置配置服务
     */
    public function setConfigServe(ConfigInterface $configService): static;
}
