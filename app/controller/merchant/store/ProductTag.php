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

declare(strict_types=1);

namespace app\controller\merchant\store;

use app\common\service\content\TagRelationService;
use app\common\service\content\TagService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户商品标签控制器。
 *
 * 复用标签体系，但固定 tag_type=2，relation_type=product。
 */
class ProductTag extends AuthController
{
    #[Inject]
    protected TagService $service;

    /**
     * 商品标签分页列表。
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['category_id', ''],
            ['status', ''],
        ]);
        $params['tag_type'] = TagService::TAG_TYPE_PRODUCT;

        return $this->success($this->service->getResourceListByOwner($params, (int)$this->merchantId));
    }

    /**
     * 商品标签选项。
     */
    public function options(): Response
    {
        $params = $this->request->getMore([
            ['category_id', 0],
            ['name', ''],
        ]);
        $params['tag_type'] = TagService::TAG_TYPE_PRODUCT;

        return $this->success('获取成功', $this->service->options($params, (int)$this->merchantId));
    }

    /**
     * 快捷新增商品标签。
     */
    public function quickAdd(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $data['tag_type'] = TagService::TAG_TYPE_PRODUCT;
        return $this->success('添加成功', $this->service->quickAdd($data, (int)$this->merchantId));
    }

    /**
     * 导入 AI 商品标签建议。
     */
    public function aiImport(): Response
    {
        $data = $this->request->postMore([
            ['category_id', 0],
            ['tag_suggestions', []],
        ]);

        return $this->success('导入成功', $this->service->importAiSuggestions(
            (array)$data['tag_suggestions'],
            (int)$this->merchantId,
            (int)$data['category_id'],
            TagService::TAG_TYPE_PRODUCT
        ));
    }

    /**
     * 获取商品已绑定标签。
     */
    public function relation(): Response
    {
        $relationId = (int)$this->request->get('relation_id', 0);
        return $this->success(
            '获取成功',
            app()->make(TagRelationService::class)->getRelationTags(
                TagService::RELATION_TYPE_PRODUCT,
                $relationId,
                (int)$this->merchantId
            )
        );
    }

    /**
     * 保存商品标签绑定。
     */
    public function saveRelation(): Response
    {
        $data = $this->request->postMore([
            ['relation_id', 0],
            ['tag_ids', []],
        ]);
        $result = app()->make(TagRelationService::class)->syncRelation(
            TagService::RELATION_TYPE_PRODUCT,
            (int)$data['relation_id'],
            (array)$data['tag_ids'],
            (int)$this->merchantId,
            TagService::TAG_TYPE_PRODUCT
        );
        return $this->success('保存成功', $result);
    }

    /**
     * 新增商品标签。
     */
    public function save(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $data['tag_type'] = TagService::TAG_TYPE_PRODUCT;
        return $this->success('添加成功', $this->service->saveTag($data, (int)$this->merchantId));
    }

    /**
     * 读取商品标签详情。
     */
    public function read(int $id): Response
    {
        return $this->success('获取成功', $this->assertProductTag($id));
    }

    /**
     * 更新商品标签。
     */
    public function update(int $id): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $data['tag_type'] = TagService::TAG_TYPE_PRODUCT;
        $this->assertProductTag($id);
        $this->service->updateTag($id, $data, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 删除商品标签。
     */
    public function delete(int $id): Response
    {
        $this->assertProductTag($id);
        $this->service->deleteTag($id, (int)$this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 修改商品标签状态。
     */
    public function show(int $id): Response
    {
        $status = (int)$this->request->get('status', 1);
        $this->assertProductTag($id);
        $this->service->showTag($id, $status, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 校验标签属于商品标签。
     */
    protected function assertProductTag(int $id): array
    {
        $tag = $this->service->readTag($id, (int)$this->merchantId);
        if ((int)($tag['tag_type'] ?? 0) !== TagService::TAG_TYPE_PRODUCT) {
            throw new \app\common\exception\AdminResponseException('商品标签不存在');
        }

        return $tag;
    }

    /**
     * 商品标签允许提交字段。
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['tag_type', TagService::TAG_TYPE_PRODUCT],
            ['category_id', 0],
            ['effect_type', 1],
            ['text_color', ''],
            ['bg_color', ''],
            ['border_color', ''],
            ['icon', ''],
            ['sort', 0],
            ['status', 1],
        ];
    }
}
