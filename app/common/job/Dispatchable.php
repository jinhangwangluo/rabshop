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

namespace app\common\job;


use think\facade\Queue;

/**
 * 队列任务
 */
trait Dispatchable
{

    /**
     * 列名
     * @return null
     */
    protected static function queueName()
    {
        return null;
    }

    /**
     * 加入队列
     * @param mixed ...$arguments
     * @return mixed
     */
    public static function dispatch(...$arguments)
    {
        return Queue::push(__CLASS__ . '@jobhandle', $arguments, self::queueName());
    }

    /**
     * 加入动态方法
     * @param string $action
     * @param ...$arguments
     * @return mixed|null
     */
    public static function dispatchDo(string $action, ...$arguments)
    {
        return Queue::push(__CLASS__ . '@job' . $action, $arguments, self::queueName());
    }

    /**
     * 延迟加入消息队列
     * @param int $secs
     * @param mixed ...$arguments
     * @return mixed
     */
    public static function dispatchSecs(int $secs, ...$arguments)
    {
        return Queue::later($secs, __CLASS__ . '@jobhandle', $arguments, self::queueName());
    }

    /**
     * 延迟加入消息队列
     * @param int $secs
     * @param string $action
     * @param mixed ...$arguments
     * @return mixed
     */
    public static function dispatchSecsDo(int $secs, string $action, ...$arguments)
    {
        return Queue::later($secs, __CLASS__ . '@job' . $action, $arguments, self::queueName());
    }
}