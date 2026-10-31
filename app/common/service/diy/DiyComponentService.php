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

namespace app\common\service\diy;

use app\common\enum\DiyComponentTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\diy\DiyComponent;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\content\ContentDisplayConfigService;

/**
 * DIY组件服务
 */
class DiyComponentService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 设置模型
     */
    protected function setModel(): string
    {
        return DiyComponent::class;
    }

    /**
     * 获取页面下的组件列表
     */
    public function getComponentsByPageId(int $pageId): array
    {
        return $this->getModel()
            ->where('diy_page_id', $pageId)
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 创建组件
     */
    public function createComponent(array $data): int
    {
        $this->assertComponentEnabled((string)($data['component_type'] ?? ''));

        // 如果没有设置排序，自动设置为当前页面最大排序+1
        if (!isset($data['sort'])) {
            $maxSort = $this->getModel()
                ->where('diy_page_id', $data['diy_page_id'])
                ->max('sort');
            $data['sort'] = $maxSort + 1;
        }

        return $this->transaction(function () use ($data) {
            $component = $this->create($data);
            return $component->id;
        });
    }

    /**
     * 更新组件
     */
    public function updateComponent(int $id, array $data): bool
    {
        if (isset($data['component_type'])) {
            $this->assertComponentEnabled((string)$data['component_type']);
        }

        return $this->transaction(function () use ($id, $data) {
            return $this->update($id, $data);
        });
    }

    /**
     * 删除组件
     */
    public function deleteComponent(int $id): bool
    {
        return $this->destroy($id);
    }

    /**
     * 根据页面ID删除所有组件（物理删除）
     */
    public function deleteByPageId(int $pageId): bool
    {
        $components = $this->getModel()
            ->where('diy_page_id', $pageId)
            ->select();
        
        foreach ($components as $component) {
            $component->force()->delete();
        }
        
        return true;
    }

    /**
     * 批量更新排序
     */
    public function batchUpdateSort(int $pageId, array $sortData): bool
    {
        return $this->transaction(function () use ($pageId, $sortData) {
            foreach ($sortData as $item) {
                if (isset($item['id']) && isset($item['sort'])) {
                    $this->getModel()
                        ->where('id', $item['id'])
                        ->where('diy_page_id', $pageId)
                        ->update(['sort' => $item['sort']]);
                }
            }
            return true;
        });
    }

    /**
     * 获取组件详情
     */
    public function getComponentDetail(int $id): array
    {
        $component = $this->get($id);
        if (!$component) {
            throw new AdminResponseException('组件不存在');
        }
        return $component->toArray();
    }

    /**
     * 复制组件
     */
    public function copyComponent(int $id, ?int $targetPageId = null): int
    {
        $component = $this->get($id);
        if (!$component) {
            throw new AdminResponseException('组件不存在');
        }

        $data = $component->toArray();
        unset($data['id'], $data['create_time'], $data['update_time'], $data['delete_time']);

        if ($targetPageId) {
            $data['diy_page_id'] = $targetPageId;
        }

        // 设置新排序
        $maxSort = $this->getModel()
            ->where('diy_page_id', $data['diy_page_id'])
            ->max('sort');
        $data['sort'] = $maxSort + 1;

        return $this->createComponent($data);
    }

    /**
     * 全量保存页面组件（先删旧、再批量插入，事务保证原子性）
     *
     * @param int   $pageId     页面ID
     * @param array $components 组件数组，每项包含 component_type/config/sort/status 等字段
     * @return array 新插入的组件列表（含 id、component_type、sort 等）
     */
    public function saveAllComponents(int $pageId, array $components): array
    {
        foreach ($components as $item) {
            $this->assertComponentEnabled((string)($item['component_type'] ?? ''));
        }

        return $this->transaction(function () use ($pageId, $components) {
            // 1. 软删除该页面所有旧组件
            $this->deleteByPageId($pageId);

            // 2. 批量插入新组件
            $result = [];
            foreach ($components as $index => $item) {
                $componentType = (string)($item['component_type'] ?? '');

                $data = [
                    'diy_page_id'    => $pageId,
                    'component_type' => $componentType,
                    'component_name' => $item['component_name'] ?? '',
                    'sort'           => $item['sort'] ?? $index,
                    'config'         => $item['config'] ?? [],
                    'status'         => $item['status'] ?? 1,
                ];
                $component = $this->create($data);
                $result[] = [
                    'id'             => $component->id,
                    'component_type' => $component->component_type,
                    'sort'           => $component->sort,
                    'status'         => $component->status,
                ];
            }

            return $result;
        });
    }

    /**
     * 根据源页面ID复制所有组件到目标页面
     */
    public function copyByPageId(int $sourcePageId, int $targetPageId): bool
    {
        $components = $this->getModel()
            ->where('diy_page_id', $sourcePageId)
            ->order('sort', 'asc')
            ->select();

        foreach ($components as $component) {
            $data = $component->toArray();
            unset($data['id'], $data['create_time'], $data['update_time'], $data['delete_time']);
            $data['diy_page_id'] = $targetPageId;
            $this->createComponent($data);
        }

        return true;
    }

    /**
     * 校验组件类型当前是否允许在 DIY 中使用。
     */
    private function assertComponentEnabled(string $componentType): void
    {
        if (app()->make(ContentDisplayConfigService::class)->isDiyComponentEnabled($componentType)) {
            return;
        }

        throw new AdminResponseException(match ($componentType) {
            DiyComponentTypeEnum::CONTENT => '内容功能已关闭，不能使用内容组件',
            DiyComponentTypeEnum::SPECIAL => '专题功能已关闭，不能使用专题组件',
            default => '当前组件已关闭，不能使用',
        });
    }
}
