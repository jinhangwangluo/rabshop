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

namespace app\common\model\setting\system;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 支付配置模型。
 */
class PaymentConfig extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'payment_config';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * 图片字段兼容后台表单的 image 命名。
     */
    public function getImageAttr($value, array $data): string
    {
        return (string)($data['icon'] ?? '');
    }

    /**
     * 图片字段兼容后台表单的 image 命名。
     */
    public function setImageAttr($value): string
    {
        return (string)$value;
    }

    /**
     * 设置终端字段。
     *
     * @param mixed $value 终端配置。
     * @return string
     */
    public function setTerminalsAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取终端字段。
     *
     * @param mixed $value 终端配置 JSON。
     * @return array
     */
    public function getTerminalsAttr($value): array
    {
        return $value ? json_decode($value, true) : [];
    }
}
