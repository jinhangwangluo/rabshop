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

namespace app\common\model\wechat;

use app\common\model\Base;

/**
 * 微信菜单模型
 * Class WechatMenus
 * @package app\common\model\wechat
 */
class WechatMenus extends Base
{
    /**
     * @var string
     */
    protected $name = 'wechat_menus';

    /**
     * 主键字段名
     * @var string
     */
    protected $pk = 'id';

    /**
     * 菜单类型映射
     * @var array
     */
    protected $typeMap = [
        'click' => '点击推事件',
        'view' => '跳转URL',
        'miniprogram' => '跳转小程序'
    ];

    /**
     * 获取菜单类型文本
     * @param $value
     * @param $data
     * @return string
     */
    public function getTypeTextAttr($value, $data)
    {
        return $this->typeMap[$data['type']] ?? $data['type'];
    }

    /**
     * 状态搜索器
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
     * 类型搜索器
     * @param $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('type', $value);
        }
    }


    /**
     * 转换为微信菜单格式
     * @param array $menus
     * @return array
     */
    public function toWechatFormat(array $menus)
    {
        $result = [];
        foreach ($menus as $menu) {
            $item = [
                'name' => $menu['name']
            ];

            if (isset($menu['children']) && !empty($menu['children'])) {
                // 有子菜单
                $item['sub_button'] = $this->toWechatFormat($menu['children']);
            } else {
                // 一级菜单或无子菜单
                switch ($menu['type']) {
                    case 'click':
                        $item['type'] = 'click';
                        $item['key'] = $menu['key'];
                        break;
                    case 'view':
                        $item['type'] = 'view';
                        $item['url'] = $menu['url'];
                        break;
                    case 'miniprogram':
                        $item['type'] = 'miniprogram';
                        $item['appid'] = $menu['appid'];
                        $item['pagepath'] = $menu['pagepath'];
                        $item['url'] = $menu['url'];
                        break;
                }
            }

            $result[] = $item;
        }

        return $result;
    }
}
