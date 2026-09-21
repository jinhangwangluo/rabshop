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

namespace app\common\service\setting;

use app\common\model\setting\system\Admin;
use app\common\model\setting\system\AdminLog;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 系统日志服务类
 */
class AdminLogService extends BaseService
{


    /**
     * @return string
     */
    protected function setModel(): string
    {
        return AdminLog::class;
    }

    /**
     * 获取操作日志列表
     * @param array $where
     * @param int $merchantId 商户ID（0表示平台端，查询所有）
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getOperationLogList(array $where, int $merchantId = 0)
    {
        [$page, $limit] = $this->getPageValue();

        $model = $this->getModel()->search($where);

        // 商户端过滤：只查询该商户的管理员日志
        if ($merchantId > 0) {
            $adminTable = (new Admin())->getTable();
            $adminLogTable = $this->getModel()->getTable();
            $model->whereExists(function ($query) use ($merchantId, $adminTable, $adminLogTable) {
                $query->table($adminTable)
                    ->whereColumn($adminTable . '.id', $adminLogTable . '.admin_id')
                    ->where('merchant_id', $merchantId);
            });
        }

        $count = $model->count();
        $list = $model->with([
            'adminUser' => function ($query) {
                $query->field(['id', 'account']);
            }
        ])->order('id', 'desc')->page($page, $limit)->field(['id', 'admin_id', 'path', 'method', 'ip', 'create_time'])->select()->toArray();

        return compact('count', 'list');
    }

    /**
     * 写入日志
     * @param int $adminId
     * @param string $adminName
     * @param string $method
     * @param string $rule
     * @param string $ip
     * @return bool
     */
    public function saveAdminLog(int $adminId, string $adminName, string $method, string $rule, string $ip)
    {
        $service = app()->make(MenuService::class);
        $data = [
            'method'     => $method,
            'admin_name' => $adminName,
            'path'       => $rule,
            'page'       => $service->getRuleName($rule) ?: '未知',
            'ip'         => $ip,
            'admin_id'   => $adminId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        if ($this->save($data)) {
            return true;
        } else {
            return false;
        }
    }
}
