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

namespace app\controller\admin\wechat;

use app\common\exception\AdminResponseException;
use app\common\service\wechat\WechatMenusService;
use app\controller\admin\AuthController;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 微信菜单管理控制器
 * Class Menus
 * @package app\controller\admin\wechat
 */
class Menus extends AuthController
{
    /**
     * @var WechatMenusService
     */
    #[Inject]
    protected WechatMenusService $service;

    /**
     * 获取菜单列表（sub_button 格式给前端展示）
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(): Response
    {
        return $this->success($this->service->getMenusForFrontend());
    }

    /**
     * 保存菜单（清空旧数据后重新写入并同步到微信）
     * @return Response
     * @throws AdminResponseException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function save(): Response
    {
        $button = $this->request->post('button/a', []);

        if (empty($button)) {
            return $this->fail('请添加至少一个菜单');
        }

        if ($this->service->saveMenus($button)) {
            return $this->success('保存并发布成功');
        }

        return $this->fail('保存失败');
    }
}
