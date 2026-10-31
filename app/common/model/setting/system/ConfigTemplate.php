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
 * 系统配置模版
 */
class ConfigTemplate extends Base
{
    use SoftDelete;

    /**
     * @var string
     */
    protected $name = 'config_template';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * 分类搜索器。
     */
    public function searchCateIdAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('cate_id', (int)$value);
        }
    }

    /**
     * 配置标识搜索器。
     */
    public function searchConfigKeyAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('config_key', trim((string)$value));
        }
    }

    public function setFieldKeysAttr($value)
    {
        return is_string($value) ? $value : json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
    }

    public function getFieldKeysAttr($value): array
    {
        return $this->decodeJsonArray($value);
    }

    public function setContentAttr($value)
    {
        return is_string($value) ? $value : json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
    }

    public function getContentAttr($value): array
    {
        return $this->decodeJsonArray($value);
    }

    public function setFieldsAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
    }

    public function getFieldsAttr($value): array
    {
        return $this->decodeJsonArray($value);
    }

    private function decodeJsonArray($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
