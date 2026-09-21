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

namespace app\controller\admin\setting;


use app\common\service\setting\ConfigTemplateService;
use app\common\service\setting\MenuService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\helper\Str;
use think\Response;


/**
 * 菜单管理
 * Class Menu
 * @package app\admin\controller
 */
class Menu extends AuthController
{

    /**
     * @var MenuService
     */
    #[Inject]
    protected MenuService $service;

    /**
     * 获取菜单
     * @return Response
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['title', ''],
            ['auth_type', '']
        ]);
        
        if (str_contains($where['auth_type'], ',')) {
            $where['auth_type'] = array_map('intval', explode(',', $where['auth_type']));
        }
        $where['platform_type'] = 1;

        return $this->success($this->service->getMenusList($where));
    }

    /**
     * 获取路由地址
     * @return Response
     */
    public function getRule()
    {
        $this->app = app();
        $rule = request()->get('rule', 'api/admin');
        $this->app->route->setTestMode(true);
        $this->app->route->clear();


        $path = $this->app->getRootPath() . 'route' . DIRECTORY_SEPARATOR;


        $files = is_dir($path) ? scandir($path) : [];

        foreach ($files as $file) {
            if (strpos($file, '.php')) {
                include $path . $file;
            }
        }

        $ruleList = $this->app->route->getRuleList();

        $ruleNewList = [];

        $urlList = $this->service->getModel()->where('auth_type', 1)->where('platform_type', 1)->column('url');
        foreach ($ruleList as $item) {
            if (Str::contains($item['rule'], $rule) && !in_array($item['rule'], $urlList)) {
                $ruleNewList[] = $item;
            }
        }
        foreach ($ruleNewList as $key => &$value) {
            $only = $value['option']['only'] ?? [];
            $route = is_string($value['route']) ? explode('/', $value['route']) : [];
            $value['route'] = is_string($value['route']) ? $value['route'] : '';
            $action = $route[count($route) - 1] ?? null;
            if ($only && $action && !in_array($action, $only)) {
                unset($ruleNewList[$key]);
            }
            $except = $value['option']['except'] ?? [];
            if ($except && $action && in_array($action, $except)) {
                unset($ruleNewList[$key]);
            }
        }

        return $this->success($ruleNewList);
    }

    /**
     * 获取菜单信息
     * @param $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function find($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        $menu = $this->service->get($id);
        if (!$menu) {
            return $this->fail('没有查询到菜单信息');
        }
        $menu = $menu->toArray();
        if ($menu['auth_type'] === 1) {
            $menu['api_url'] = $menu['url'];
            $menu['path'] = '';
        } elseif ($menu['auth_type'] === 2) {
            $menu['api_url'] = '';
            $menu['path'] = $menu['url'];
            // 反查关联的配置模板id
            $configTemplate = app()->make(ConfigTemplateService::class)
                ->getModel()
                ->where('menu_id', $id)
                ->find();
            $menu['config_template_id'] = $configTemplate ? $configTemplate->id : 0;
        } else {
            $menu['api_url'] = '';
            $menu['path'] = $menu['url'];
        }
        $menu['title'] = $menu['name'];
        $menu['parent_id'] = $menu['pid'];
        $menu['method'] = $menu['methods'];
        return $this->success($menu);
    }

    /**
     * 保存菜单
     * @param $id
     * @return Response
     */
    public function save($id)
    {
        $data = $this->request->postMore([
            ['auth_type', 0],
            ['parent_id', 0],
            ['path', ''],
            ['title', ''],
            ['icon', ''],
            ['status', 1],
            ['sort', 0],
            ['api_url', ''],
            ['method', ''],
            ['is_hidden', 0],
            ['config_template_id', 0],
        ]);

        $data['name'] = $data['title'];
        $data['pid'] = $data['parent_id'];
        $data['url'] = $data['path'];
        $data['methods'] = $data['method'];
        $configTemplateId = $data['config_template_id'];
        unset($data['title'], $data['parent_id'], $data['path'], $data['method'], $data['config_template_id']);

        if (!$data['name']) {
            return $this->fail($data['auth_type'] ? '请填写接口名' : '请填写菜单名');
        }
        if (!$data['auth_type'] && !$data['url']) {
            return $this->fail('请填写菜单地址');
        }
        if ($data['auth_type'] === 1 && !$data['api_url']) {
            return $this->fail('请填写接口地址');
        }
        if ($data['auth_type'] === 1 && !$data['methods']) {
            return $this->fail('请选择请求方式');
        }
        if ($data['auth_type'] === 2 && !$configTemplateId) {
            return $this->fail('请选择配置模板');
        }

        $data['auth_iden'] = '';
        if ($data['auth_type'] === 1) {
            $data['url'] = $data['api_url'];
            $data['auth_iden'] = str_replace(['api/admin', '/', '<', '>'], ['', '-', '', ''], $data['url']);
        } elseif ($data['auth_type'] === 2) {
            $data['url'] = '/setting/config/form/' . $configTemplateId;
        }

        unset($data['api_url']);

        if ($id) {
            $data['pid'] = is_array($data['pid']) ? $data['pid'][count($data['pid']) - 1] : $data['pid'];
            $this->service->update($id, $data);
            if ($configTemplateId) {
                app()->make(ConfigTemplateService::class)->update($configTemplateId, ['menu_id' => $id]);
            }
            return $this->success('修改成功');
        } else {
            $res = $this->service->create($data);

            if ($res && $configTemplateId) {
                app()->make(ConfigTemplateService::class)->update($configTemplateId, ['menu_id' => $res->id]);
            }
            return $this->success('添加成功');
        }
    }

    /**
     * 修改菜单状态
     * @param $id
     * @param $status
     * @return Response
     */
    public function status($id, $status)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        if ('' === $status) {
            return $this->fail('缺少修改数据');
        }

        $this->service->update($id, ['status' => $status]);

        return $this->success('修改成功');
    }

    /**
     * 删除菜单
     * @param $id
     * @return Response
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        $this->service->destroy($id);

        return $this->success('删除成功');
    }
}
