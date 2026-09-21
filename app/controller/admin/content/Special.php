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

use app\common\enum\AuditStatusEnum;
use app\common\exception\AdminResponseException;
use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialContentService;
use app\common\service\content\SpecialService;
use app\common\service\live\LiveGoodsService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 专题控制器
 */
class Special extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 专题服务
     */
    #[Inject]
    protected SpecialService $service;

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
            ['title', ''],
            ['merchant_id', ''],
            ['category_id', ''],
            ['special_type', ''],
            ['content_source', 'all'],
            ['audit_status', ''],
            ['status', ''],
            ['is_hot', ''],
            ['price', ''],
            ['create_time', '', 'time'],
        ];
    }

    /**
     * POST请求获取的字段
     */
    protected function getRequestFields(): array
    {
        return [
            ['title', ''],
            ['merchant_id', 0],
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
     * 获取专题列表
     * 支持 live_room_id 参数，自动排除已在该直播间添加的专题
     * @return mixed
     */
    public function index(): mixed
    {
        $params = $this->request->getMore($this->getIndexFields());
        $liveRoomId = (int)$this->request->get('live_room_id', 0);
        if ($liveRoomId > 0) {
            $params['exclude_ids'] = app()->make(LiveGoodsService::class)
                ->getAddedGoodsIds($liveRoomId, 'special');
        }

        return $this->success($this->service->getResourceList(
            $params,
            ['*'],
            [
                'merchant' => fn($query) => $query->field(['id', 'mer_name']),
            ]
        ));
    }

    /**
     * 审核专题内容
     */
    public function audit(int $id): Response
    {
        [$auditStatus, $remark] = $this->getAuditPayload();
        $this->service->auditSpecial($id, $auditStatus, $remark, (int)$this->adminId);

        return $this->success('审核操作成功');
    }

    /**
     * 获取专题内容列表（携带内容详情）
     * @param int $id 专题ID
     * @return Response
     */
    public function contentList(int $id): Response
    {
        $contents = app()->make(SpecialContentService::class)->getContentListWithDetail($id);
        return $this->success('获取成功', ['list' => $contents]);
    }

    /**
     * 内容选择器：搜索内容列表（专题添加内容时使用）
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function searchContent(): Response
    {
        $params = $this->request->only(['title', 'content_type']);
        // 平台端专题只能选择平台内容，避免混入商户内容。
        $params['merchant_id'] = 0;
        $result = app()->make(ArticleService::class)->searchContentList($params);
        return $this->success('获取成功', $result);
    }

    /**
     * 获取审核参数
     */
    protected function getAuditPayload(): array
    {
        $auditStatus = (int)$this->request->post('audit_status', AuditStatusEnum::APPROVED);
        $remark = trim((string)$this->request->post('audit_remark', ''));

        if (!array_key_exists($auditStatus, AuditStatusEnum::list())) {
            throw new AdminResponseException('审核状态不正确');
        }
        if (AuditStatusEnum::isViolation($auditStatus) && $remark === '') {
            throw new AdminResponseException('驳回或下架时请填写审核备注');
        }

        return [$auditStatus, $remark];
    }
}
