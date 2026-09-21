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

namespace app\controller\admin\content;

use app\common\enum\AuditStatusEnum;
use app\common\exception\AdminResponseException;
use app\common\service\content\ArticleService;
use app\common\service\content\SpecialService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 内容审核控制器
 */
class ContentAudit extends AuthController
{
    #[Inject]
    protected ArticleService $articleService;

    #[Inject]
    protected SpecialService $specialService;

    /**
     * 获取待审核数量统计
     */
    public function pendingCount(): Response
    {
        $articlePendingTabs = $this->articleService->getPendingAuditTabCounts();
        $specialPending = $this->specialService->getPendingAuditCount();

        return $this->success('获取成功', [
            'article' => $articlePendingTabs['merchant'],
            'special' => $specialPending,
            'total' => $articlePendingTabs['merchant'] + $specialPending,
            'article_pending' => $articlePendingTabs,
            'tabs' => $articlePendingTabs,
        ]);
    }

    /**
     * 审核文章内容
     */
    public function auditArticle(int $id): Response
    {
        [$auditStatus, $remark] = $this->getAuditPayload();
        $this->articleService->auditArticle($id, $auditStatus, $remark, (int)$this->adminId);

        return $this->success('审核操作成功');
    }

    /**
     * 批量审核文章内容
     */
    public function batchAuditArticle(): Response
    {
        [$auditStatus, $remark] = $this->getAuditPayload();
        $ids = $this->getBatchIds('article_ids');
        $this->articleService->batchAuditArticle($ids, $auditStatus, $remark, (int)$this->adminId);

        return $this->success('批量审核成功');
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

    /**
     * 获取批量审核 ID
     */
    protected function getBatchIds(string $field): array
    {
        $ids = $this->request->post($field, $this->request->post('ids', []));
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            throw new AdminResponseException('请选择要审核的数据');
        }

        return $ids;
    }
}
