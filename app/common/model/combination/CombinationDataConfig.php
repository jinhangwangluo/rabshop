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

namespace app\common\model\combination;

use app\common\model\Base;
use think\model\concern\SoftDelete;

/**
 * 组合数据配置模型.
 *
 * 保存分类关联、配置标识、设计器 schema 和预定义字段。
 */
class CombinationDataConfig extends Base
{
    use SoftDelete;

    /**
     * 数据表名.
     *
     * @var string
     */
    protected $name = 'combination_data_config';

    /**
     * 主键.
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 写入表单设计内容.
     *
     * @param mixed $value JSON 字符串或数组
     * @return string
     */
    public function setContentAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取表单设计内容.
     *
     * @param mixed $value 数据库原始值
     * @return array
     */
    public function getContentAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    /**
     * 写入字段 key 列表.
     *
     * @param mixed $value JSON 字符串或数组
     * @return string
     */
    public function setFieldKeysAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取字段 key 列表.
     *
     * @param mixed $value 数据库原始值
     * @return array
     */
    public function getFieldKeysAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }

    /**
     * 写入预定义字段列表.
     *
     * @param mixed $value JSON 字符串或数组
     * @return string
     */
    public function setFieldsAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 读取预定义字段列表.
     *
     * @param mixed $value 数据库原始值
     * @return array
     */
    public function getFieldsAttr($value): array
    {
        return $value ? json_decode($value, true) ?: [] : [];
    }
}
