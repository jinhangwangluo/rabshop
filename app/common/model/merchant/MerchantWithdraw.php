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

namespace app\common\model\merchant;

use app\common\model\Base;

/**
 * 商户提现申请模型
 */
class MerchantWithdraw extends Base
{
    public const TYPE_BANK = 'bank';
    public const TYPE_WECHAT = 'wechat';
    public const TYPE_ALIPAY = 'alipay';
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    /**
     * 表名
     */
    protected $name = 'merchant_withdraw';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     */
    protected $type = [
        'merchant_id' => 'integer',
        'amount' => 'float',
        'status' => 'integer',
    ];

    /**
     * 搜索器：商户ID
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 搜索器：状态
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：提现方式
     */
    public function searchWithdrawTypeAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('withdraw_type', $value);
        }
    }

    /**
     * 获取器：状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return self::getStatusText((int)($data['status'] ?? self::STATUS_PENDING));
    }

    /**
     * 获取器：提现方式文本
     */
    public function getWithdrawTypeTextAttr($value, $data): string
    {
        return self::getWithdrawTypeText((string)($data['withdraw_type'] ?? self::TYPE_BANK));
    }

    /**
     * 商户关联。
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public static function getWithdrawTypeText(string $type): string
    {
        return [
            self::TYPE_BANK => '银行卡',
            self::TYPE_WECHAT => '微信',
            self::TYPE_ALIPAY => '支付宝',
        ][$type] ?? '银行卡';
    }

    public static function getStatusText(int $status): string
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已拒绝',
        ][$status] ?? '待审核';
    }
}
