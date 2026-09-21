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

/**
 * 文件管理
 * Class File
 * @package app\common\model\setting
 */
class File extends Base
{
    private const USER_UPLOAD_PATH_KEYWORD = 'file/mobile/';

    /**
     * @var string
     */
    protected $name = 'file';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * @var bool
     */
    protected bool $isPathStr = true;

    /**
     * @param $query
     * @param $value
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2022/11/12
     */
    public function searchNameLikeAttr($query, $value)
    {
        if ('' !== $value) {
            $query->where('name|real_name', 'like', '%' . $value . '%');
        }
    }

    /**
     * @param $query
     * @param $value
     * @author 橙猫
     * @email 17349395805@163.com
     * @date 2022/11/12
     */
    public function searchCateIdAttr($query, $value)
    {
        if ('' !== $value) {
            $query->where('cate_id', (int)$value);
        }
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    public function searchFileTypeAttr($query, $value): void
    {
        if ($value === '' || $value === null) {
            return;
        }

        if (is_array($value)) {
            $query->whereIn('file_type', array_map('intval', $value));
            return;
        }

        if (is_string($value) && str_contains($value, ',')) {
            $query->whereIn('file_type', array_map('intval', explode(',', $value)));
            return;
        }

        $query->where('file_type', (int)$value);
    }

    /**
     * 搜索器：后台附件库隐藏移动端用户上传记录。
     */
    public function searchHideUserUploadAttr($query, $value): void
    {
        if ((int)$value === 1) {
            $query->where('path', 'not like', '%' . self::USER_UPLOAD_PATH_KEYWORD . '%');
        }
    }

}
