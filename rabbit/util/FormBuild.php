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

namespace Rabbit\util;

use FormBuilder\Factory\Elm;
use FormBuilder\UI\Elm\Components\Frame;

class FormBuild extends Elm
{


    /**
     * @param $field
     * @param $title
     * @param $value
     * @return \FormBuilder\Driver\CustomComponent
     */
    public static function frameImageV1($field, $title, $value = '')
    {
        return self::createComponent('Upload')
            ->field($field)
            ->title($title)
            ->value($value)
            ->props(['limit' => 1]);
    }

    /**
     * @param $field
     * @param $title
     * @param array $value
     * @return \FormBuilder\Driver\CustomComponent
     */
    public static function frameImagesV1($field, $title, array $value = [])
    {
        return self::createComponent('Upload')
            ->field($field)
            ->title($title)
            ->value($value)
            ->props(['limit' => 20]);
    }

    /**
     * 用户选择弹窗。
     * @param $field
     * @param $title
     * @param $value
     * @return Frame
     */
    public static function frameUserV1($field, $title, $value = 0)
    {
        return self::frame($field, $title, config('app.admin_prefix') . '/system/user-select?fodder=' . $field, (string)(int)$value, Frame::TYPE_INPUT)
            ->icon('el-icon-user')
            ->width('965px')
            ->height('560px')
            ->props(['footer' => false]);
    }

    /**
     * 用户选择弹窗组件。
     * @param $field
     * @param $title
     * @param $value
     * @return \FormBuilder\Driver\CustomComponent
     */
    public static function userSelectV1($field, $title, $value = 0)
    {
        return self::createComponent('AdminUserSelect')
            ->field($field)
            ->title($title)
            ->value((int)$value)
            ->props(['placeholder' => '请选择绑定用户']);
    }
}
