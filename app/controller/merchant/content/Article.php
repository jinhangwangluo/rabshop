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

use app\common\enum\ContentTypeEnum;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\content\ArticleCategoryService;
use app\common\service\content\ArticleAiGenerateService;
use app\common\service\content\ArticleService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 管理当前商户多种类型内容，固定归属并校验操作权限，审核与标签规则由内容服务执行。
 *
 * @package app\controller\merchant\content
 */
class Article extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 当前商户内容管理与归属校验服务。
     *
     * @var ArticleService
     */
    #[Inject]
    protected ArticleService $service;

    /**
     * 分页查询当前商户内容并附带分类信息，商户隔离不能由请求覆盖。
     *
     * @return Response 包含内容列表与分页信息的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());
        // 请求中的mer_id不能覆盖当前登录商户的隔离条件。
        $params['mer_id'] = (int)$this->merchantId;
        return $this->success($this->service->getResourceList(
            params: $params,
            with: ['articleCategory' => fn($q) => $q->field(['id', 'name'])],
            sort: ['id' => 'desc']
        ));
    }

    /**
     * 指定内容资源化状态切换使用的字段。
     *
     * @return string 内容状态字段名。
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 声明当前内容控制器不额外配置资源验证器。
     *
     * @return string|null 当前返回 null，由业务服务执行校验。
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 声明内容列表的标题、类型、分类、状态与当前商户筛选字段。
     *
     * @return array 列表筛选字段及默认值配置。
     */
    protected function getIndexFields(): array
    {
        return [
            ['title', ''],
            ['article_category_id', ''],
            ['content_type', ''],
            ['status', ''],
            ['audit_status', ''],
            ['is_hot', ''],
            ['is_banner', ''],
            ['is_trial', ''],
            ['create_time', '', 'time'],
            ['mer_id', $this->merchantId],
        ];
    }

    /**
     * 声明多种内容类型新增编辑的白名单，关联标签缺省时保持原设置。
     *
     * @return array 内容资料、价格、媒体及标签请求字段配置。
     */
    protected function getRequestFields(): array
    {
        return [
            ['title', ''],
            ['article_category_id', 0],
            ['author', ''],
            ['source', ''],
            ['keywords', ''],
            ['description', ''],
            ['cover', ''],
            ['content_type', ContentTypeEnum::GRAPHIC],
            // 图文/文字/小说 使用
            ['content', ''],
            ['trial_content', ''],
            // 音频/视频 使用
            ['media_url', ''],
            ['media_duration', 0],
            ['trial_media_url', ''],
            // 图片 使用
            ['images', []],
            ['trial_images', []],
            // 小说字数
            ['word_count', 0],
            ['virtual_learn_count', 0],
            // 价格设置
            ['price', 0.00],
            ['original_price', 0.00],
            ['is_trial', 0],
            // 其他
            ['sort', 0],
            ['is_hot', 0],
            ['is_banner', 0],
            ['status', 1],
            ['tag_ids', null],
        ];
    }

    /**
     * 声明内容表单构建需要的前置字段。
     *
     * @return array 当前无需前置字段，返回空数组。
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 新增前固定当前商户归属，并保留正文与试读的富文本 HTML。
     *
     * @param array $data 待保存内容字段；按引用补充归属和富文本。
     * @param int $id 资源化保存钩子传入的记录 ID，新增时为 0。
     * @param mixed|null $res 资源化钩子传入的前置结果。
     * @return void 无返回值。
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['mer_id'] = $this->merchantId;
        // 富文本字段绕过全局输入过滤，保留 HTML
        $data['content'] = (string)$this->request->post('content', '', null);
        $data['trial_content'] = (string)$this->request->post('trial_content', '', null);
    }

    /**
     * 编辑前校验当前商户归属，并保留正文与试读的富文本 HTML。
     *
     * @param array $data 待更新内容字段；按引用补充归属和富文本。
     * @param int $id 待编辑内容 ID。
     * @param mixed|null $res 资源化钩子传入的前置结果。
     * @return void 无返回值。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function beforeUpdate(array &$data, int $id = 0, mixed $res = null): void
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '内容');
        $data['mer_id'] = $this->merchantId;
        // 富文本字段绕过全局输入过滤，保留 HTML
        $data['content'] = (string)$this->request->post('content', '', null);
        $data['trial_content'] = (string)$this->request->post('trial_content', '', null);
    }

    /**
     * 删除前校验内容属于当前认证商户。
     *
     * @param array $data 资源化删除钩子传入的内容字段。
     * @param int $id 待删除内容 ID。
     * @param mixed|null $res 资源化钩子传入的前置结果。
     * @return void 无返回值。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function beforeDelete(array $data, int $id = 0, mixed $res = null): void
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '内容');
    }

    /**
     * 校验内容归属后切换指定内容的启用状态。
     *
     * @param int $id 当前商户的内容 ID。
     * @param int $status 目标内容启用状态。
     * @return Response 内容状态更新结果响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function status(int $id, int $status): Response
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '内容');
        $this->service->resourceShowUpdate($id, [$this->getStatusField() => $status]);
        return $this->success('修改成功');
    }

    /**
     * 快捷修改当前商户内容字段，关键内容变更后由服务重新进入待审核。
     *
     * @param int $id 待快捷编辑的内容 ID。
     * @return Response 包含快捷更新结果的响应。
     */
    public function quickUpdate(int $id): Response
    {
        $data = $this->request->param();
        // 富文本字段绕过全局输入过滤，保留 HTML
        $data['content'] = (string)$this->request->param('content', '', null);
        $data['trial_content'] = (string)$this->request->param('trial_content', '', null);
        return $this->success('修改成功', $this->service->quickUpdate($id, $data, true, (int)$this->merchantId));
    }

    /**
     * 校验归属后读取内容详情，并补充关联分类的 ID 与名称。
     *
     * @param int $id 当前商户的内容 ID。
     * @return Response 包含内容及分类资料，或内容不存在的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function detail(int $id): Response
    {
        $this->service->checkMerchantOwner($this->merchantId, $id, '内容');
        $detail = $this->service->resourceRead($id);
        if (!$detail) {
            return $this->fail('内容不存在');
        }
        // 附加分类信息
        if (!empty($detail['article_category_id'])) {
            $category = app()->make(ArticleCategoryService::class)
                ->get($detail['article_category_id']);
            $detail['articleCategory'] = $category ? $category->visible(['id', 'name']) : null;
        }
        return $this->success('获取成功', $detail);
    }

    /**
     * 通过商户专用删除服务校验归属并删除内容。
     *
     * @param mixed $id 待删除内容 ID。
     * @return mixed|Response 删除成功、失败或缺少记录 ID 的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail($this->message['delete']['empty']);
        }

        if ($this->service->deleteMerchantArticle($this->merchantId, $id)) {
            return $this->success($this->message['delete']['success']);
        }
        return $this->fail($this->message['delete']['fail']);
    }

    /**
     * 返回当前支持的图文、文字、小说、图片、音频与视频类型选项。
     *
     * @return Response 包含类型值和名称列表的响应。
     */
    public function contentTypes(): Response
    {
        return $this->success('内容类型列表', [
            'list' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(ContentTypeEnum::list()), ContentTypeEnum::list()),
        ]);
    }

    /**
     * 根据当前表单生成简介、正文、试读、售价与建议标签，不直接绑定建议标签。
     *
     * @return Response 包含 AI 内容发布建议的响应。
     */
    public function aiGenerate(): Response
    {
        $data = $this->request->postMore([
            ['title', ''],
            ['content_type', ContentTypeEnum::GRAPHIC],
            ['author', ''],
            ['keywords', ''],
            ['description', ''],
            ['content', ''],
            ['trial_content', ''],
            ['price', 0],
            ['is_trial', 0],
        ]);

        /**
         * 内容创作服务，生成结果仅作为发布建议。
         *
         * @var ArticleAiGenerateService $service
         */
        $service = app()->make(ArticleAiGenerateService::class);
        return $this->success('生成成功', $service->generate($data, (int)$this->merchantId));
    }

    /**
     * 根据标题、类型及当前字段内容生成续写片段，由前端追加原文。
     *
     * @return Response 包含 AI 续写片段的响应。
     */
    public function aiContinue(): Response
    {
        $data = $this->request->postMore([
            ['title', ''],
            ['content_type', ContentTypeEnum::GRAPHIC],
            ['field', 'content'],
            ['content', ''],
        ]);

        /**
         * 内容创作服务，生成结果仅作为发布建议。
         *
         * @var ArticleAiGenerateService $service
         */
        $service = app()->make(ArticleAiGenerateService::class);
        return $this->success('续写成功', $service->continueWriting($data, (int)$this->merchantId));
    }

    /**
     * 为专题内容选择器搜索当前商户内容，并保留类型与分页条件。
     *
     * @return Response 包含当前商户可选内容的响应。
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function searchList(): Response
    {
        $params = $this->request->only(['title', 'content_type', 'page', 'page_size']);
        // 只搜索当前商户的内容
        $params['merchant_id'] = $this->merchantId;
        $result = $this->service->searchContentList($params);
        return $this->success('获取成功', $result);
    }
}
