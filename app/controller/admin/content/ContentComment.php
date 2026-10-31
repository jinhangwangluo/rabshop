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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\content\ContentCommentService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 后台内容评论管理控制器
 */
class ContentComment extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 评论服务
     */
    #[Inject]
    protected ContentCommentService $service;

    /**
     * 状态字段（评论无状态切换，返回空字符串占位）
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 验证类（暂不启用）
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
            ['id', ''],
            ['article_id', ''],
            ['user_id', ''],
            ['nickname', ''],
            ['keyword', ''],
            ['is_root', ''],
            ['create_time', ''],
        ];
    }

    /**
     * POST 请求字段（后台不支持新增评论）
     */
    protected function getRequestFields(): array
    {
        return [];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 后台评论列表（覆盖默认 index，附带用户和文章关联）
     */
    public function index(): Response
    {
        $params = $this->request->getMore($this->getIndexFields());

        $result = $this->service->getAdminList($params);
        return $this->success($result);
    }
}
