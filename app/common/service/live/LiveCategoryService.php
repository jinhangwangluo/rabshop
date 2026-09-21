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

namespace app\common\service\live;

use app\common\exception\AdminResponseException;
use app\common\service\setting\CategoryService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 直播分类服务
 */
class LiveCategoryService extends CategoryService
{
    // 直播分类类型值
    const LIVE_TYPE = 4;

    /**
     * 初始化时固定分类类型为直播分类
     */
    public function __construct()
    {
        $this->categoryType = self::LIVE_TYPE;
    }

    /**
     * 获取直播分类下拉选项（启用状态）
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOptions(): array
    {
        return $this->getModel()
            ->where(['type' => self::LIVE_TYPE, 'status' => 1])
            ->field(['id', 'name'])
            ->order('sort desc, id desc')
            ->select()
            ->toArray();
    }
}
