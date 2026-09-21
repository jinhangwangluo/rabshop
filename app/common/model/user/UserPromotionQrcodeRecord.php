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

namespace app\common\model\user;

use app\common\model\Base;

/**
 * 用户推广二维码生成记录模型。
 */
class UserPromotionQrcodeRecord extends Base
{
    /**
     * 表名。
     *
     * @var string
     */
    protected $name = 'user_promotion_qrcode_record';

    /**
     * 主键。
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 写入扩展数据。
     *
     * @param mixed $value 扩展数据。
     * @return string
     */
    public function setExtraAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取扩展数据。
     *
     * @param mixed $value 数据库原始值。
     * @return array
     */
    public function getExtraAttr($value): array
    {
        return $value ? json_decode((string)$value, true) ?: [] : [];
    }
}
