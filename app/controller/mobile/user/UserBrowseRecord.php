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

namespace app\controller\mobile\user;

use app\BaseController;
use app\common\service\user\UserBrowseRecordService;
use think\annotation\Inject;
use think\Response;

/**
 * 移动端用户浏览记录控制器.
 */
class UserBrowseRecord extends BaseController
{
    /**
     * 用户浏览记录服务.
     *
     * @var UserBrowseRecordService
     */
    #[Inject]
    protected UserBrowseRecordService $service;

    /**
     * 获取用户浏览记录列表。
     *
     * GET 参数：
     * - page int 页码，默认 1。
     * - limit int 每页数量，默认 10。
     *
     * @return Response 返回浏览记录分页列表。
     */
    public function index(): Response
    {
        $userId = $this->getLoginUserId();
        $page = (int)$this->request->get('page/d', 1);
        $limit = (int)$this->request->get('limit/d', 10);

        return $this->success('获取成功', $this->service->getMobileHistoryList($userId, $page, $limit));
    }

    /**
     * 删除用户选中的浏览记录。
     *
     * POST 参数：
     * - ids array|string 浏览记录 ID 集合。
     *
     * @return Response 返回删除结果。
     */
    public function delete(): Response
    {
        $userId = $this->getLoginUserId();
        $rawIds = $this->request->post('ids', '');
        $ids = parse_ids(
            $this->request->post('ids/a', []),
            is_array($rawIds) ? '' : (string)$rawIds
        );

        if ($ids === []) {
            return $this->fail('请选择要删除的足迹');
        }

        return $this->success('删除成功', $this->service->deleteMobileRecords($userId, $ids));
    }

    /**
     * 记录用户浏览行为。
     *
     * POST 参数：
     * - target_type string 浏览对象类型：content/special/product。
     * - target_id int 浏览对象 ID。
     *
     * @return Response 返回记录结果。
     */
    public function record(): Response
    {
        $userId = $this->getLoginUserId();
        $targetType = trim((string)$this->request->post('target_type', ''));
        $targetId = (int)$this->request->post('target_id/d', 0);

        if ($targetType === '' || $targetId <= 0) {
            return $this->fail('浏览对象参数不完整');
        }

        return $this->success('记录成功', $this->service->recordMobileBrowse($userId, $targetType, $targetId));
    }

    /**
     * 获取当前登录用户ID。
     *
     * @return int 当前登录用户 ID。
     */
    protected function getLoginUserId(): int
    {
        return (int)($this->request->user_id ?? 0);
    }
}
