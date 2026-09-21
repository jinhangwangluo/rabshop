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
use Rabbit\util\Jwt;
use think\model\concern\SoftDelete;

/**
 * Class Merchant
 * @package app\common\model\merchant
 */
class Merchant extends Base
{
    use SoftDelete;
    /**
     * @var string
     */
    protected $name = 'merchant';

    /**
     * @var string
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 字段类型转换
     * @var array
     */
    protected $type = [
        'qualification' => 'json',
        'business_hours' => 'json',
        'merchant_home_banner' => 'json',
        'merchant_home_recommend_special' => 'json',
        'commission_rate' => 'float',
        'total_sales' => 'float',
        'is_member_discount' => 'integer',
        'product_audit_mode' => 'integer',
        'live_audit_mode' => 'integer',
        'live_product_audit_mode' => 'integer',
        'content_audit_mode' => 'integer',
    ];

    /**
     * 搜索器：商户名称
     * @param $query
     * @param $value
     */
    public function searchMerNameAttr($query, $value)
    {
        $query->where('mer_name', 'like', '%' . $value . '%');
    }

    /**
     * 搜索器：状态
     * @param $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 搜索器：审核状态
     * @param $query
     * @param $value
     */
    public function searchAuditStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('audit_status', $value);
        }
    }

    /**
     * 搜索器：营业状态
     * @param $query
     * @param $value
     */
    public function searchBusinessStatusAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('business_status', $value);
        }
    }

    /**
     * 搜索器：是否推荐
     * @param $query
     * @param $value
     */
    public function searchIsRecommendAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_recommend', $value);
        }
    }

    /**
     * 搜索器：是否参与会员折扣
     */
    public function searchIsMemberDiscountAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_member_discount', $value);
        }
    }

    /**
     * 搜索器：创建时间
     * @param $query
     * @param $value
     */
    public function searchCreateTimeAttr($query, $value)
    {
        if (!empty($value) && is_array($value)) {
            $query->whereBetweenTime('create_time', $value[0], $value[1]);
        }
    }

    /**
     * 搜索器：账号
     * @param $query
     * @param $value
     */
    public function searchAccountAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('account', $value);
        }
    }

    /**
     * 生成Token
     * @param int $id
     * @return string
     */
    public function setToken(int $id): string
    {
        return Jwt::create($id, 0, 'merchant');
    }

    /**
     * 获取器：营业状态文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getBusinessStatusTextAttr($value, $data)
    {
        $status = [0 => '休息中', 1 => '营业中'];
        return $status[$data['business_status'] ?? 1] ?? '营业中';
    }

    /**
     * 获取器：审核状态文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getAuditStatusTextAttr($value, $data)
    {
        $status = [0 => '待审核', 1 => '已通过', 2 => '已驳回'];
        return $status[$data['audit_status'] ?? 0] ?? '待审核';
    }

    /**
     * 获取器：店铺完整信息
     * @param $value
     * @param $data
     * @return array
     */
    public function getStoreInfoAttr($value, $data)
    {
        return [
            'mer_name' => $data['mer_name'] ?? '',
            'mer_avatar' => $data['mer_avatar'] ?? '',
            'mer_notice' => $data['mer_notice'] ?? '',
            'description' => $data['description'] ?? '',
            'business_status' => $data['business_status'] ?? 1,
            'business_status_text' => $this->getBusinessStatusTextAttr('', $data),
            'business_hours' => $this->getBusinessHoursAttr('', $data),
            'contact_name' => $data['contact_name'] ?? '',
            'contact_phone' => $data['contact_phone'] ?? '',
            'mer_address' => $data['mer_address'] ?? '',
        ];
    }

    /**
     * 获取器：营业时间
     * @param $value
     * @param $data
     * @return array
     */
    public function getBusinessHoursAttr($value, $data)
    {
        $hours = $data['business_hours'] ?? '';
        if (empty($hours)) {
            return [
                'start' => '09:00',
                'end' => '18:00',
                'is_all_day' => false,
            ];
        }
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }
        return $hours;
    }

    /**
     * 获取器：资质信息
     * @param $value
     * @param $data
     * @return array
     */
    public function getQualificationAttr($value, $data)
    {
        $qualification = $data['qualification'] ?? '';
        if (empty($qualification)) {
            return [
                'business_license' => '',      // 营业执照
                'legal_person_idcard' => '',   // 法人身份证
                'bank_account' => '',          // 银行账户
                'other_certs' => [],           // 其他资质
            ];
        }
        if (is_string($qualification)) {
            $qualification = json_decode($qualification, true);
        }
        return $qualification;
    }
}
