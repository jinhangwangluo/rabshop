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

namespace app\common\service;


use app\common\exception\AdminResponseException;
use app\common\model\Base;
use Rabbit\util\CacheTag;
use think\facade\Db;

/**
 * Class BaseService
 * @package app\common\service
 * @property CacheTag $cacheTag
 * @method Base search(array $where = [])
 */
abstract class BaseService
{
    use HelpService;

    /**
     * 设置模型
     * @return string
     */
    abstract protected function setModel(): string;

    /**
     * 获取模型
     * @return Base
     */
    public function getModel()
    {
        return app()->make($this->setModel());
    }

    /**
     * 判断当前默认连接下是否存在指定数据表。
     *
     * 供卸载、清理等可能面对不完整表结构的场景使用：表不存在时应跳过相关查询与删除，
     * 而不是让缺表异常中断整个生命周期操作。这里只读取连接元信息，不实例化模型，
     * 因为模型构造本身会按表结构初始化字段，缺表时同样会抛异常。
     *
     * @param string $table 不含表前缀的数据表名，只接受小写字母、数字和下划线。
     * @return bool 数据表存在时返回 true。
     */
    protected function hasTable(string $table): bool
    {
        $name = trim($table);
        if ($name === '' || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) !== 1) {
            throw new AdminResponseException('数据表名无效');
        }
        $connection = Db::connect();
        // SHOW 语句不支持参数绑定，按库名与完整表名精确查询 information_schema。
        $exists = $connection->query(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
            [(string)$connection->getConfig('database'), (string)$connection->getConfig('prefix') . $name]
        );
        return $exists !== [];
    }
}
