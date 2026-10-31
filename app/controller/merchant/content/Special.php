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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\content\ArticleAiGenerateService;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialContentService;
use app\common\service\content\SpecialService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 管理当前商户专题资料及内容选择，固定商户归属并在修改前校验权限。
 *
 * @package app\controller\merchant\content
 */
class Special extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait {
        update as protected resourceTraitUpdate;
        delete as protected resourceTraitDelete;
    }

    /**
     * 当前商户专题管理与归属校验服务。
     *
     * @var SpecialService
     */
    #[Inject]
    protected SpecialService $service;

    /**
     * 分页查询当前商户专题，归属不能被客户端参数覆盖。
     *
     * @return Response 包含专题列表及分页信息的响应。
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());
        $params['merchant_id'] = (int)$this->merchantId;
        return $this->success($this->service->getResourceList($params));
    }

    /**
     * 编辑专题前显式校验归属。
     *
     * ResourceControllerTrait::eventMethod 会吞掉 AdminResponseException，
     * 仅依赖 beforeUpdate 钩子无法阻止跨商户修改，因此这里在进入资源流程前直接校验。
     *
     * @param mixed $id 专题标识
     * @return mixed 资源更新结果
     */
    public function update($id)
    {
        $this->service->checkMerchantOwner((int)$this->merchantId, (int)$id, '专题');
        return $this->resourceTraitUpdate($id);
    }

    /**
     * 删除专题前显式校验归属，避免跨商户删除及其关联商品数据。
     *
     * @param mixed $id 专题标识
     * @return mixed 资源删除结果
     */
    public function delete($id)
    {
        $this->service->checkMerchantOwner((int)$this->merchantId, (int)$id, '专题');
        return $this->resourceTraitDelete($id);
    }

    /**
     * 指定专题资源化状态切换使用的字段。
     *
     * @return string 专题状态字段名。
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 声明当前专题控制器不额外配置资源验证器。
     *
     * @return string|null 当前返回 null，由业务服务执行校验。
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 声明专题列表允许的分类、类型、审核、价格与时间筛选字段。
     *
     * @return array 专题筛选字段与当前商户默认归属配置。
     */
    protected function getIndexFields(): array
    {
        return [
            ['title', ''],
            ['category_id', ''],
            ['special_type', ''],
            ['status', ''],
            ['audit_status', ''],
            ['is_hot', ''],
            ['price', ''],
            ['create_time', '', 'time'],
            ['merchant_id', $this->merchantId],
        ];
    }

    /**
     * 声明专题新增编辑字段，缺少内容关联或标签字段时保留原设置。
     *
     * @return array 专题资料、价格和内容关联请求白名单。
     */
    protected function getRequestFields(): array
    {
        return [
            ['title', ''],
            ['category_id', 0],
            ['keywords', ''],
            ['description', ''],
            ['cover', ''],
            ['special_type', 0],
            ['price', 0.00],
            ['original_price', 0.00],
            ['sort', 0],
            ['is_hot', 0],
            ['status', 1],
            // contents为 null 表示不修改内容列表
            ['contents', null],
            ['tag_ids', null],
            ['product_ids', null],
        ];
    }

    /**
     * 声明专题表单构建需要的前置字段。
     *
     * @return array 当前无需前置字段，返回空数组。
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 新增专题前固定当前认证商户归属。
     *
     * @param array $data 待保存专题字段；按引用补充商户 ID。
     * @param int $id 资源化保存钩子传入的记录 ID，新增时为 0。
     * @param mixed|null $res 资源化钩子传入的前置结果。
     * @return void 无返回值。
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['merchant_id'] = $this->merchantId;
    }

    /**
     * 编辑前校验专题属于当前认证商户。
     *
     * @param array $data 资源化编辑钩子传入的专题字段。
     * @param int $id 待编辑专题 ID。
     * @param mixed|null $res 资源化钩子传入的前置结果。
     * @return void 无返回值。
     */
    protected function beforeUpdate(array &$data, int $id = 0, mixed $res = null): void
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '专题');
    }

    /**
     * 删除前校验专题属于当前认证商户。
     *
     * @param array $data 资源化删除钩子传入的专题字段。
     * @param int $id 待删除专题 ID。
     * @param mixed|null $res 资源化钩子传入的前置结果。
     * @return void 无返回值。
     */
    protected function beforeDelete(array $data, int $id = 0, mixed $res = null): void
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '专题');
    }

    /**
     * 校验专题归属后更新启用状态。
     *
     * @param int $id 当前商户专题 ID。
     * @param int $status 目标启用状态。
     * @return Response 专题状态更新结果响应。
     */
    public function status(int $id, int $status): Response
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '专题');
        $this->service->resourceShowUpdate($id, [$this->getStatusField() => $status]);
        return $this->success('修改成功');
    }

    /**
     * 校验专题归属后获取关联内容及其详情。
     *
     * @param int $id 当前商户专题 ID。
     * @return Response 包含专题内容详情列表的响应。
     */
    public function contentList(int $id): Response
    {
        // 校验专题归属
        $this->service->checkMerchantOwner($this->merchantId, $id, '专题');
        $contents = app()->make(SpecialContentService::class)->getContentListWithDetail($id);
        return $this->success('获取成功', ['list' => $contents]);
    }

    /**
     * 校验当前商户归属后读取专题详情。
     *
     * @param int $id 当前商户专题 ID。
     * @return Response 包含专题详情的响应。
     */
    public function detail(int $id): Response
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '专题');
        return $this->success('获取成功', $this->service->resourceRead($id));
    }

    /**
     * 根据当前专题表单生成介绍、关键词、售价与建议标签，不直接绑定标签。
     *
     * @return Response 包含 AI 专题发布建议的响应。
     */
    public function aiGenerate(): Response
    {
        $data = $this->request->postMore([
            ['title', ''],
            ['keywords', ''],
            ['description', ''],
            ['price', 0],
            ['special_type', 0],
        ]);

        /**
         * 专题发布建议生成服务，不直接修改内容或标签。
         *
         * @var ArticleAiGenerateService $service
         */
        $service = app()->make(ArticleAiGenerateService::class);
        return $this->success('生成成功', $service->generateSpecial($data, (int)$this->merchantId));
    }

    /**
     * 为专题添加内容搜索当前商户的内容，限制标题与类型筛选。
     *
     * @return Response 包含当前商户可选内容的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function searchContent(): Response
    {
        $params = $this->request->only(['title', 'content_type']);
        // 只搜索当前商户的内容
        $params['merchant_id'] = $this->merchantId;
        $result = app()->make(ArticleService::class)->searchContentList($params);
        return $this->success('获取成功', $result);
    }
}
