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
use app\common\service\diy\DiyComponentService;
use app\controller\admin\AuthController;
use think\annotation\Inject;

/**
 * DIY组件管理控制器
 */
class DiyComponent extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var DiyComponentService
     */
    #[Inject]
    protected DiyComponentService $service;

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
            ['diy_page_id', 0],
            ['component_type', ''],
            ['status', ''],
        ];
    }

    /**
     * POST请求获取的字段（用于新增/编辑）
     */
    protected function getRequestFields(): array
    {
        return [
            ['diy_page_id', 0],
            ['component_type', ''],
            ['component_name', ''],
            ['sort', 0],
            ['config', []], // 确保使用 config 字段
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
     * 全量保存页面所有组件（先删旧再批量插入，原子事务）
     */
    public function saveAll(): mixed
    {
        $pageId     = (int)$this->request->post('page_id', 0);
        $components = $this->request->post('components', []);

        if (!$pageId) {
            return $this->fail('页面ID不能为空');
        }

        if (!is_array($components)) {
            return $this->fail('组件数据格式错误');
        }

        $result = $this->service->saveAllComponents($pageId, $components);
        return $this->success('保存成功', $result);
    }

    /**
     * 批量更新排序
     */
    public function batchSort(): mixed
    {
        $pageId = (int)$this->request->post('diy_page_id', 0);
        $sortData = $this->request->post('sort_data', []);
        
        if (!$pageId) {
            return $this->fail('页面ID不能为空');
        }
        
        $this->service->batchUpdateSort($pageId, $sortData);
        return $this->success('排序更新成功');
    }

    /**
     * 获取组件类型选项（按分类分组）
     */
    public function types(): mixed
    {
        $groups = app()->make(ContentDisplayConfigService::class)
            ->filterDiyComponentGroups(DiyComponentTypeEnum::getGroupedByCategory());

        return $this->success('ok', $groups);
    }

    /**
     * 复制组件
     */
    public function copy(): mixed
    {
        $id = (int)$this->request->post('id', 0);
        $targetPageId = (int)$this->request->post('target_page_id', 0);
        
        $newId = $this->service->copyComponent($id, $targetPageId ?: null);
        return $this->success('复制成功', ['id' => $newId]);
    }

    /**
     * 获取页面下的组件列表
     */
    public function listByPage(): mixed
    {
        $pageId = (int)$this->request->get('diy_page_id', 0);
        if (!$pageId) {
            return $this->fail('页面ID不能为空');
        }
        
        $list = $this->service->getComponentsByPageId($pageId);
        return $this->success('ok', $list);
    }
}
