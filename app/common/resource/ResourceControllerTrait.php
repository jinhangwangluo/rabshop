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

namespace app\common\resource;

use app\BaseController;
use app\common\exception\ApiValidateException;
use app\common\service\BaseService;
use app\Request;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Log;

/**
 * 资源控制器.
 * @package app\common\resourceTrait
 * @mixin BaseController
 * @property  Request $request
 * @property  BaseService $service
 */
trait ResourceControllerTrait
{

    /**
     * 错误提示.
     * @var string[][]
     */
    protected array $message
        = [
            'save'   => [
                'success' => '添加成功',
                'fail'    => '添加失败',
            ],
            'show'   => [
                'success' => '修改成功',
                'fail'    => '修改失败',
                'empty'   => '修改的ID不能为空',
                'status'  => '修改状态值不存在',
            ],
            'update' => [
                'success' => '修改成功',
                'fail'    => '修改失败',
                'empty'   => '修改的ID不能为空',
            ],
            'edit'   => [
                'fail'  => '没有查询到需要修改的数据',
                'empty' => '修改的ID不能为空',
            ],
            'delete' => [
                'success' => '删除成功',
                'fail'    => '没有查询到需要删除的数据',
                'empty'   => '修改的ID不能为空',
            ],
        ];

    /**
     * 获取状态字段.
     * @return string
     */
    abstract protected function getStatusField(): string;

    /**
     * 设置Validate类名.
     * @return string|null
     */
    abstract protected function getValidateClassName(): string|null;

    /**
     * 设置搜索字段.
     * @return string[]
     */
    abstract protected function getIndexFields(): array;

    /**
     * 设置请求参数获取字段.
     * @return string[]
     */
    abstract protected function getRequestFields(): array;

    /**
     * 获取表单字段.
     * @return string[]
     */
    abstract protected function getFormFields(): array;

    /**
     * 展示数据.
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $params = $this->request->getMore($this->getIndexFields());
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 创建数据.
     * @return mixed
     */
    public function create()
    {
        $params = $this->request->getMore($this->getFormFields());
        return $this->success($this->service->resourceCreate($params));
    }

    /**
     * 添加.
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->postMore($this->getRequestFields());

        $this->validateCheck($data);

        $this->eventMethod(event: 'save', data: $data);
        $res = $this->service->resourceSave($data);
        if ($res) {

            $this->eventMethod(event: 'save', eventType: 'after', data: $data, res: $res);

            return $this->success($this->message['save']['success'], is_array($res) ? $res : []);
        }

        $this->eventMethod(event: 'save', eventType: 'after', data: $data, res: $res);

        return $this->fail($this->message['save']['fail']);
    }

    /**
     * 隐藏展示.
     * @param mixed $id
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function show($id)
    {
        if (!$id) {
            return $this->fail($this->message['show']['empty']);
        }
        $status = $this->request->get('status');
        if ($status === null) {
            return $this->fail($this->message['show']['status']);
        }
        $showData = [$this->getStatusField() => $status];
        $this->eventMethod(event: 'show', data: $showData, id: $id);

        if ($this->service->resourceShowUpdate($id, $showData)) {

            $this->eventMethod(event: 'show', eventType: 'after', data: $showData, id: $id, res: true);

            return $this->success($this->message['show']['success']);
        }

        $this->eventMethod(event: 'show', eventType: 'after', data: $showData, id: $id, res: false);

        return $this->fail($this->message['show']['fail']);
    }

    /**
     * 获取修改数据.
     * @param mixed $id
     * @return mixed
     */
    public function read($id)
    {
        if (!$id) {
            return $this->fail($this->message['edit']['empty']);
        }
        return $this->success($this->service->resourceRead((int)$id));
    }

    /**
     * 修改数据.
     * @param mixed $id
     * @return mixed
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail($this->message['update']['empty']);
        }

        $data = $this->request->postMore($this->getRequestFields());

        $this->validateCheck($data);

        $this->eventMethod(event: 'update', data: $data, id: $id);
    
        if ($this->service->resourceUpdate($id, $data)) {

            $this->eventMethod(event: 'update', eventType: 'after', data: $data, id: $id, res: true);

            return $this->success($this->message['update']['success']);
        }

        $this->eventMethod(event: 'update', eventType: 'after', data: $data, id: $id, res: false);

        return $this->fail($this->message['update']['fail']);
    }

    /**
     * 删除数据.
     * @param mixed $id
     * @return mixed
     * @throws \ReflectionException
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail($this->message['delete']['empty']);
        }

        $this->eventMethod(event: 'delete', id: $id);

        if ($this->service->resourceDelete($id)) {

            $this->eventMethod(event: 'delete', eventType: 'after', id: $id, res: true);

            return $this->success($this->message['delete']['success']);
        }
        return $this->fail($this->message['delete']['fail']);
    }


    /**
     * 解析验证字段
     */
    protected function validateCheck(array $data)
    {
        $validate = $this->getValidateClassName();
        if ($validate) {
            $this->validate($data, $validate);
        }
    }

    /**
     * 触发事件方法
     * @param string $event
     * @param string $eventType
     * @param mixed $data
     * @param int $id
     * @param mixed|null $res
     * @return void
     */
    protected function eventMethod(string $event, string $eventType = 'before', mixed &$data = [], int $id = 0, mixed $res = null)
    {
        $method = $eventType . ucfirst($event);

        if (method_exists($this, $method)) {

            try {
                $this->$method($data, $id, $res);
            } catch (\Throwable $e) {
                Log::error('资源控制器出发事件异常：' . $e->getMessage(), [
                    'file'   => $e->getFile(),
                    'line'   => $e->getLine(),
                    'data'   => $data,
                    'method' => $method,
                    'id'     => $id,
                    'res'    => $res,
                ]);
                // 验证异常直接抛出
                if ($e instanceof ApiValidateException) {
                    throw $e;
                }
            }

        }
    }
}
