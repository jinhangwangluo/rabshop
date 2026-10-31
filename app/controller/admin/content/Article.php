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

namespace app\controller\admin\content;

use app\common\enum\ContentTypeEnum;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\content\ArticleAiGenerateService;
use app\common\service\content\ArticleService;
use app\common\service\live\LiveGoodsService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 内容控制器（支持图文/文字/小说/图片/音频/视频）
 */
class Article extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 内容服务
     */
    #[Inject]
    protected ArticleService $service;

    /**
     * 列表
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $params = $this->request->getMore($this->getIndexFields());

        // 提取排序参数，白名单校验防止注入
        $allowSortFields = ['create_time', 'view_count', 'sales', 'price', 'id'];
        $sortField = in_array($params['sort_field'] ?? '', $allowSortFields, true)
            ? $params['sort_field'] : 'id';
        $sortType  = in_array($params['sort_type'] ?? '', ['asc', 'desc'], true)
            ? $params['sort_type'] : 'desc';
        unset($params['sort_field'], $params['sort_type']);

        return $this->success($this->service->getResourceList(
            $params,
            ['*'],
            [
                'articleCategory' => fn($q) => $q->field(['id', 'name']),
                'merchant' => fn($q) => $q->field(['id', 'mer_name']),
            ],
            [$sortField => $sortType]
        ));
    }

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
    protected function getValidateClassName(): ?string
    {
        return \app\common\validate\ArticleValidate::class . '.add';
    }

    /**
     * 搜索字段
     */
    protected function getIndexFields(): array
    {
        return [
            ['title', ''],
            ['article_category_id', ''],
            ['content_type', ''],
            ['content_source', ''],
            ['mer_id', ''],
            ['status', ''],
            ['audit_status', ''],
            ['is_hot', ''],
            ['is_banner', ''],
            ['is_trial', ''],
            ['create_time', '', 'time'],
            ['sort_field', ''],
            ['sort_type', ''],
        ];
    }

    /**
     * POST请求获取的字段（内容新增/编辑）
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
            ['media_url', ''],//地址
            ['media_duration', 0],// 时长秒
            ['trial_media_url', ''],// 尝试播放地址
            // 图片 使用
            ['images', []],
            ['trial_images', []],
            // 小说字数
            ['word_count', 0],
            ['virtual_learn_count', 0],
            // 价格设置
            ['price', 0.00],
            ['original_price', 0.00],
            ['gift_points', 0],
            ['is_trial', 0],
            // 其他
            ['sort', 0],
            ['is_hot', 0],
            ['is_banner', 0],
            ['status', 1],
            ['tag_ids', null],
            ['product_ids', null],
        ];
    }

    /**
     * 获取表单的前置字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 获取内容类型列表
     * @return Response
     */
    public function contentTypes(): Response
    {
        return $this->success('内容类型列表', [
            'list' => array_map(fn($k, $v) => ['value' => $k, 'label' => $v], array_keys(ContentTypeEnum::list()), ContentTypeEnum::list()),
        ]);
    }

    /**
     * URL采集内容。
     *
     * @return Response
     */
    public function collect(): Response
    {
        $url = (string)$this->request->post('url', '');
        return $this->success('采集成功', $this->service->collectFromUrl($url));
    }

    /**
     * AI润色写作。
     *
     * 接收当前表单中的标题、类型、简介、正文等上下文，调用默认 AI 对话模型生成：
     * 简介、正文内容、试读内容、售价和建议标签。建议标签只返回给前端展示，不直接创建或绑定。
     *
     * @return Response
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

        /** @var ArticleAiGenerateService $service */
        $service = app()->make(ArticleAiGenerateService::class);
        return $this->success('生成成功', $service->generate($data));
    }

    /**
     * AI续写正文/试读内容。
     *
     * 接收标题、类型、续写字段与该字段现有内容，调用默认 AI 对话模型生成续写片段，
     * 由前端追加到编辑器现有内容末尾。
     *
     * @return Response
     */
    public function aiContinue(): Response
    {
        $data = $this->request->postMore([
            ['title', ''],
            ['content_type', ContentTypeEnum::GRAPHIC],
            ['field', 'content'],
            ['content', ''],
        ]);

        /** @var ArticleAiGenerateService $service */
        $service = app()->make(ArticleAiGenerateService::class);
        return $this->success('续写成功', $service->continueWriting($data));
    }

    /**
     * 新增前钩子：富文本字段绕过全局输入过滤，保留 HTML。
     *
     * @param array $data
     * @param int $id
     * @param mixed|null $res
     */
    protected function beforeSave(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['content'] = (string)$this->request->post('content', '', null);
        $data['trial_content'] = (string)$this->request->post('trial_content', '', null);
    }

    /**
     * 编辑前钩子：富文本字段绕过全局输入过滤，保留 HTML。
     *
     * @param array $data
     * @param int $id
     * @param mixed|null $res
     */
    protected function beforeUpdate(array &$data, int $id = 0, mixed $res = null): void
    {
        $data['content'] = (string)$this->request->post('content', '', null);
        $data['trial_content'] = (string)$this->request->post('trial_content', '', null);
    }

    /**
     * 快捷编辑内容字段。
     *
     * 平台端快捷编辑不触发审核状态变更。
     *
     * @param int $id
     * @return Response
     */
    public function quickUpdate(int $id): Response
    {
        $data = $this->request->param();
        // 富文本字段绕过全局输入过滤，保留 HTML
        $data['content'] = (string)$this->request->param('content', '', null);
        $data['trial_content'] = (string)$this->request->param('trial_content', '', null);
        return $this->success('修改成功', $this->service->quickUpdate($id, $data));
    }

    /**
     * 搜索内容列表（专题内容选择用）
     * 支持 live_room_id 参数，自动排除已在该直播间添加的内容
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function searchList(): Response
    {
        $params     = $this->request->only(['title', 'content_type']);
        $liveRoomId = (int)$this->request->get('live_room_id', 0);
        if ($liveRoomId > 0) {
            $params['exclude_ids'] = app()->make(LiveGoodsService::class)
                ->getAddedGoodsIds($liveRoomId, 'article');
        }
        $result = $this->service->searchContentList($params);
        return $this->success('获取成功', $result);
    }
}
