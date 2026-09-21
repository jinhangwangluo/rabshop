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

declare (strict_types=1);

namespace app;

use app\common\service\BaseService;
use think\App;
use think\exception\ValidateException;
use think\facade\View;
use think\Response;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \app\Request
     */
    protected Request $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
    }

    /**
     * 验证数据
     * @access protected
     * @param array $data 数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array $message 提示信息
     * @param bool $batch 是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * @param $name
     * @param null $value
     * @return \think\View
     */
    protected function assign($name, $value = null)
    {
        return View::assign($name, $value);
    }

    /**
     * @param string $template
     * @param array $vars
     * @return string
     */
    protected function fetch(string $template = '', array $vars = [])
    {
        return View::fetch($template, $vars);
    }

    /**
     * 正确返回
     * @param string|object|array $message
     * @param array|object $data
     * @return Response
     */
    protected function success(mixed $message = 'ok', array|object $data = [])
    {
        if ($message instanceof BaseService) {
            $message = $message->toArray();
        }

        if ($data instanceof BaseService) {
            $data = $data->toArray();
        }

        return d_json()->success($message, $data);
    }

    /**
     * 失败返回
     * @param mixed $message
     * @param array $data
     * @return Response
     */
    protected function fail(mixed $message = 'error', array $data = [])
    {
        if ($message instanceof BaseService) {
            $message = $message->toArray();
        }

        if ($data instanceof BaseService) {
            $data = $data->toArray();
        }

        return d_json()->fail($message, $data);
    }
}
