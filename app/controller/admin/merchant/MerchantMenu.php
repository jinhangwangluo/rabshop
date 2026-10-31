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

namespace app\controller\admin\merchant;

use app\common\service\merchant\MerchantMenuService;
use app\common\service\setting\ConfigTemplateService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\helper\Str;
use think\Response;

/**
 * 商户菜单管理控制器
 * Class MerchantMenu
 * @package app\controller\admin\merchant
 */
class MerchantMenu extends AuthController
{
    /**
     * @var MerchantMenuService
     */
    #[Inject]
    protected MerchantMenuService $service;

    /**
     * 获取商户菜单列表
     * @return Response
     */
    public function index(): Response
    {
        $where = $this->request->getMore([
            ['status', ''],
            ['title', ''],
            ['auth_type', '']
        ]);

        if (str_contains($where['auth_type'], ',')) {
            $where['auth_type'] = array_map('intval', explode(',', $where['auth_type']));
        }

        return $this->success($this->service->getMenusList($where));
    }

    /**
     * 获取路由地址（用于接口选择）
     * @return Response
     */
    public function getRule(): Response
    {
        $this->app = app();
        $rule = request()->get('rule', 'api/merchant');
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

        // 获取已存在的商户端接口URL
        $urlList = $this->service->getModel()
            ->where('platform_type', MerchantMenuService::PLATFORM_TYPE_MERCHANT)
            ->where('auth_type', 1)
            ->column('url');

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

        return $this->success(array_values($ruleNewList));
    }

    /**
     * 获取菜单信息
     * @param int $id
     * @return Response
     */
    public function read(int $id): Response
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        try {
            $menu = $this->service->getMenuInfo($id);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        if ($menu['auth_type'] === 1) {
            $menu['api_url'] = $menu['url'];
            $menu['path'] = '';
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
     * @param int $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function save(int $id = 0): Response
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
            ['auth_iden', '']
        ]);

        $data['name'] = $data['title'];
        $data['pid'] = $data['parent_id'];
        $data['url'] = $data['path'];
        $data['methods'] = $data['method'];
        unset($data['title'], $data['parent_id'], $data['path'], $data['method']);

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

        if ($data['auth_type'] === 1) {
            $data['url'] = $data['api_url'];
            $data['auth_iden'] = str_replace(['api/merchant', '/', '<', '>'], ['', '-', '', ''], $data['url']);
        } elseif ($data['auth_type'] === 2) {
            $data['api_url'] = '';
            $data['path'] = $data['url'];
            // 反查关联的配置模板id
            $configTemplate = app()->make(ConfigTemplateService::class)
                ->getModel()
                ->where('menu_id', $id)
                ->find();
            $data['config_template_id'] = $configTemplate ? $configTemplate->id : 0;
        }
        unset($data['api_url']);
        $data['platform_type'] = 2;
        if ($id) {
            $data['pid'] = is_array($data['pid']) ? $data['pid'][count($data['pid']) - 1] : $data['pid'];
            $this->service->updateMenu($id, $data);
            return $this->success('修改成功');
        } else {
            $this->service->createMenu($data);
            return $this->success('添加成功');
        }

    }

    /**
     * 修改菜单状态
     * @param int $id
     * @param int $status
     * @return Response
     */
    public function status(int $id, int $status): Response
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }
        if ('' === $status) {
            return $this->fail('缺少修改数据');
        }


        $this->service->updateMenu($id, ['status' => $status]);
        return $this->success('修改成功');

    }

    /**
     * 删除菜单
     * @param int $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delete(int $id): Response
    {
        if (!$id) {
            return $this->fail('缺少参数');
        }

        $this->service->deleteMenu($id);
        return $this->success('删除成功');
    }

    /**
     * 获取商户菜单树（用于权限分配）
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function tree(): Response
    {
        $tree = $this->service->getMenusTree(false);
        return $this->success($tree);
    }
}
