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

namespace app\controller\merchant\content;

use app\common\service\content\TagRelationService;
use app\common\service\content\TagService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 商户标签控制器。
 *
 * 商户端标签固定使用当前 merchantId 作为 owner_id，只能管理商户自己的标签和资源绑定关系。
 */
class Tag extends AuthController
{
    #[Inject]
    protected TagService $service;

    /**
     * 获取商户标签分页列表。
     *
     * @return Response 返回 list、total、page、limit、totalPage 等分页字段。
     */
    public function index(): Response
    {
        $params = $this->request->getMore([
            ['name', ''],
            ['tag_type', TagService::TAG_TYPE_CONTENT],
            ['category_id', ''],
            ['status', ''],
        ]);

        return $this->success($this->service->getResourceListByOwner($params, (int)$this->merchantId));
    }

    /**
     * 获取商户可选标签列表。
     *
     * @return Response 返回 list，GET 参数支持 tag_type、category_id、name。
     */
    public function options(): Response
    {
        $params = $this->request->getMore([
            ['tag_type', TagService::TAG_TYPE_CONTENT],
            ['category_id', 0],
            ['name', ''],
        ]);

        return $this->success('获取成功', $this->service->options($params, (int)$this->merchantId));
    }

    /**
     * 快捷新增商户标签。
     *
     * @return Response 返回适合前端选择器追加的标签选项：id、value、label、name。
     */
    public function quickAdd(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        return $this->success('添加成功', $this->service->quickAdd($data, (int)$this->merchantId));
    }

    /**
     * 导入 AI 建议标签。
     *
     * @return Response 返回导入后的 tag_ids、tag_list、created_count、exists_count。
     */
    public function aiImport(): Response
    {
        $data = $this->request->postMore([
            ['tag_type', TagService::TAG_TYPE_CONTENT],
            ['category_id', 0],
            ['tag_suggestions', []],
        ]);

        return $this->success('导入成功', $this->service->importAiSuggestions(
            (array)$data['tag_suggestions'],
            (int)$this->merchantId,
            (int)$data['category_id'],
            (int)$data['tag_type']
        ));
    }

    /**
     * 获取商户资源已绑定的标签。
     *
     * @return Response 返回 tag_ids 与 tag_list；GET 参数 relation_type、relation_id。
     */
    public function relation(): Response
    {
        $relationType = (string)$this->request->get('relation_type', '');
        $relationId = (int)$this->request->get('relation_id', 0);
        return $this->success(
            '获取成功',
            app()->make(TagRelationService::class)->getRelationTags($relationType, $relationId, (int)$this->merchantId)
        );
    }

    /**
     * 保存商户资源绑定的标签。
     *
     * @return Response 返回同步后的 tag_ids 与 tag_list；POST 参数 relation_type、relation_id、tag_type、tag_ids。
     */
    public function saveRelation(): Response
    {
        $data = $this->request->postMore([
            ['relation_type', ''],
            ['relation_id', 0],
            ['tag_type', 0],
            ['tag_ids', []],
        ]);
        $result = app()->make(TagRelationService::class)->syncRelation(
            (string)$data['relation_type'],
            (int)$data['relation_id'],
            (array)$data['tag_ids'],
            (int)$this->merchantId,
            (int)$data['tag_type']
        );
        return $this->success('保存成功', $result);
    }

    /**
     * 新增商户标签。
     *
     * @return Response 返回保存后的标签数据。
     */
    public function save(): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        return $this->success('添加成功', $this->service->saveTag($data, (int)$this->merchantId));
    }

    /**
     * 读取商户标签详情。
     *
     * @param int $id 标签 ID。
     * @return Response 返回标签详情，包含 category_name、value、label 等字段。
     */
    public function read(int $id): Response
    {
        return $this->success('获取成功', $this->service->readTag($id, (int)$this->merchantId));
    }

    /**
     * 更新商户标签。
     *
     * @param int $id 标签 ID。
     * @return Response 返回修改结果。
     */
    public function update(int $id): Response
    {
        $data = $this->request->postMore($this->getRequestFields());
        $this->service->updateTag($id, $data, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 删除商户标签。
     *
     * @param int $id 标签 ID。
     * @return Response 返回删除结果，并由 Service 清理标签关联。
     */
    public function delete(int $id): Response
    {
        $this->service->deleteTag($id, (int)$this->merchantId);
        return $this->success('删除成功');
    }

    /**
     * 修改商户标签状态。
     *
     * @param int $id 标签 ID。
     * @return Response 返回状态修改结果，GET 参数 status：0=禁用，1=启用。
     */
    public function show(int $id): Response
    {
        $status = (int)$this->request->get('status', 1);
        $this->service->showTag($id, $status, (int)$this->merchantId);
        return $this->success('修改成功');
    }

    /**
     * 获取新增/编辑商户标签允许提交的字段。
     *
     * @return array 请求字段配置：name、tag_type、category_id、effect_type、颜色、icon、sort、status。
     */
    protected function getRequestFields(): array
    {
        return [
            ['name', ''],
            ['tag_type', TagService::TAG_TYPE_CONTENT],
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
