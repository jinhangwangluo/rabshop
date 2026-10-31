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

namespace app\controller\mobile;

use app\BaseController;
use app\common\service\diy\DiyCategoryPageService;
use app\common\service\diy\DiyPageService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端 DIY 页面控制器.
 */
class Diy extends BaseController
{
    /**
     * DIY 页面服务.
     *
     * @var DiyPageService
     */
    #[Inject]
    protected DiyPageService $service;

    /**
     * 分类页面配置服务.
     *
     * @var DiyCategoryPageService
     */
    #[Inject]
    protected DiyCategoryPageService $categoryPageService;

    /**
     * 获取首页 DIY 配置.
     *
     * @return Response 返回默认首页 DIY 配置。
     */
    public function home(): Response
    {
        $includeData = $this->request->get('include_data', '0') === '1';

        return $this->success('获取成功', $this->service->getDefaultHomePage(
            (int)($this->request->user_id ?? 0),
            $includeData
        ));
    }

    /**
     * 获取指定页面 DIY 配置.
     *
     * GET 参数：
     * - id int DIY 页面 ID；未传时返回默认首页配置。
     *
     * @return Response 返回页面 DIY 配置。
     */
    public function page(): Response
    {
        $id = (int)$this->request->get('id', 0);
        $userId = (int)($this->request->user_id ?? 0);
        $includeData = $this->request->get('include_data', '0') === '1';
        $data = $id > 0
            ? $this->service->getMobilePageDetail($id, $includeData, $userId)
            : $this->service->getDefaultHomePage($userId, $includeData);

        return $this->success('获取成功', $data);
    }

    /**
     * 获取移动端 DIY 单组件按需数据.
     *
     * @return Response 返回组件真实数据。
     */
    public function componentData(): Response
    {
        $componentId = (int)$this->request->get('component_id', 0);
        if ($componentId <= 0) {
            return $this->fail('组件 ID 不能为空');
        }

        return $this->success('获取成功', $this->service->getMobileComponentData(
            $componentId,
            (int)($this->request->user_id ?? 0),
            (int)$this->request->get('category_id', 0),
            max(1, (int)$this->request->get('page', 1)),
            max(0, (int)$this->request->get('limit', 0))
        ));
    }

    /**
     * 获取个人中心 DIY 配置.
     *
     * @return Response 返回个人中心 DIY 配置。
     */
    public function userCenter(): Response
    {
        return $this->success('获取成功', $this->service->getMobileUserCenterConfig(
            (int)($this->request->user_id ?? 0)
        ));
    }

    /**
     * 获取移动端分类页样式配置.
     *
     * @return Response 返回 style_type 与分类筛选配置。
     */
    public function categoryPageConfig(): Response
    {
        return $this->success('获取成功', $this->categoryPageService->getMobileConfig());
    }

    /**
     * 预览指定 DIY 页面.
     *
     * GET 参数：
     * - id int DIY 页面 ID。
     *
     * @return Response 返回预览页面配置。
     */
    public function preview(): Response
    {
        $id = (int)$this->request->get('id', 0);
        if ($id <= 0) {
            return $this->fail('页面 ID 不能为空');
        }

        $includeData = $this->request->get('include_data', '1') === '1';

        return $this->success('获取成功', $this->service->getMobilePageDetail(
            $id,
            $includeData,
            (int)($this->request->user_id ?? 0)
        ));
    }
}
