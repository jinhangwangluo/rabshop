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

namespace app\controller\admin\diy;

use app\common\enum\DiyComponentTypeEnum;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\content\ContentDisplayConfigService;
use app\common\service\diy\DiyPageService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * DIY页面管理控制器
 */
class DiyPage extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var DiyPageService
     */
    #[Inject]
    protected DiyPageService $service;

    /**
     * 状态字段
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['page_name', ''],
            ['page_type', ''],
            ['status', ''],
        ];
    }

    /**
     * POST请求获取的字段（用于新增/编辑）
     */
    protected function getRequestFields(): array
    {
        return [
            ['page_name', ''],
            ['page_type', 'home'],
            ['page_title', ''],
            ['global_config', []],
            ['status', 1],
        ];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 设置为默认页面
     */
    public function setDefault(): mixed
    {
        $id = (int)$this->request->param('id', 0);
        $this->service->setDefault($id);
        return $this->success('设置成功');
    }

    /**
     * 获取页面详情（含组件）
     */
    public function detail(): mixed
    {
        $id = (int)$this->request->param('id', 0);
        $data = $this->service->getDetailWithComponents($id);
        return $this->success('ok', $data);
    }

    /**
     * 预览页面
     */
    public function preview(): mixed
    {
        $id = (int)$this->request->param('id', 0);
        
        // 获取页面和组件
        $page = $this->service->get($id, ['*'], ['components']);
        if (!$page) {
            return $this->fail('页面不存在');
        }

        // 聚合组件数据
        $aggregateService = app()->make(\app\common\service\diy\DiyDataAggregateService::class);
        $components = [];
        
        foreach ($page->components as $component) {
            $componentData = [
                'id' => $component->id,
                'type' => $component->component_type,
                'name' => $component->component_name,
                'config' => $component->config,
                'data' => $aggregateService->aggregate($component->component_type, $component->config ?? []),
            ];
            $components[] = $componentData;
        }

        return $this->success('ok', [
            'page' => $page->toArray(),
            'components' => $components,
        ]);
    }

    /**
     * 获取组件类型选项（按分类分组）
     */
    public function componentTypes(): mixed
    {
        $groups = app()->make(ContentDisplayConfigService::class)
            ->filterDiyComponentGroups(DiyComponentTypeEnum::getGroupedByCategory());

        return $this->success('ok', $groups);
    }

    /**
     * 获取个人中心装修配置。
     */
    public function userCenter(): mixed
    {
        return $this->success('ok', $this->service->getUserCenterDecorateDetail());
    }

    /**
     * 保存个人中心装修配置。
     */
    public function saveUserCenter(): mixed
    {
        $data = [
            'page_name' => $this->request->post('page_name', '个人中心默认装修'),
            'page_title' => $this->request->post('page_title', '个人中心'),
            'status' => (int)$this->request->post('status', 1),
            'global_config' => $this->request->post('global_config', []),
            'service_menu' => $this->request->post('service_menu', []),
            'order_menu' => $this->request->post('order_menu', []),
            'banner' => $this->request->post('banner', []),
        ];

        return $this->success('保存成功', $this->service->saveUserCenterDecorate($data));
    }

    /**
     * 另存为模板
     */
    public function saveAsTemplate(): mixed
    {
        $id = (int)$this->request->param('id', 0);
        if (!$id) {
            return $this->fail('页面ID不能为空');
        }

        $sourcePage = $this->service->get($id);
        if (!$sourcePage) {
            return $this->fail('页面不存在');
        }

        $newPageId = $this->service->createPage([
            'page_name'  => $sourcePage->page_name . '_模板',
            'page_type'  => $sourcePage->page_type,
            'page_title' => $sourcePage->page_title,
            'global_config' => $sourcePage->global_config ?? [],
            'status'     => 1,
        ]);

        // 复制组件
        $componentService = app()->make(\app\common\service\diy\DiyComponentService::class);
        $componentService->copyByPageId($id, $newPageId);

        return $this->success('另存成功', ['id' => $newPageId]);
    }

    /**
     * 删除页面（重写，同时删除关联组件）
     */
    public function delete($id): mixed
    {
        $this->service->deletePage((int)$id);
        return $this->success('删除成功');
    }
}
