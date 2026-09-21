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

namespace app\common\model\live;

use app\common\model\Base;

/**
 * 直播间等级配置模型
 */
class LiveRoomLevelConfig extends Base
{
    protected $name = 'live_room_level_config';
    protected $pk   = 'id';

    /**
     * benefits 自动 JSON 解码
     */
    protected $type = [
        'benefits' => 'json',
    ];

    /**
     * 名称搜索器
     */
    public function searchNameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 状态搜索器
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }

    /**
     * 获取所有启用的等级配置，按最低贡献值升序
     */
    public function getEnabledLevels(): array
    {
        return $this->where('status', 1)
            ->order('min_contribution', 'asc')
            ->select()
            ->toArray();
    }
}
