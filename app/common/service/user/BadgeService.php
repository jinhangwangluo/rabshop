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

namespace app\common\service\user;

use app\common\exception\AdminException;
use app\common\model\user\Badge;
use app\common\service\BaseService;
use Rabbit\util\FormBuild as Build;
use Rabbit\traits\ViewTrait;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;


class BadgeService extends BaseService
{

    use ViewTrait;

    protected function setModel(): string
    {
        return Badge::class;
    }

    /**
     * 获取修改和新建表单
     * @param int $id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getForm(int $id)
    {
        if ($id) {
            $badge = $this->getModel()->find($id);
            if (!$badge) {
                throw new AdminException('没有查询到数据');
            }
        }

        return $this->buildView([
            Build::input('name', '昵称', $badge['name'] ?? '')->required(),
            Build::uploadImage('image', '图标', $badge['image'] ?? '')->url('/admin/index/upload')->required(),
            Build::inputNum('sort', '排序', $badge['sort'] ?? ''),
        ], '/admin/user.badge/save?id=' . $id);
    }

}
