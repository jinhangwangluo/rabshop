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

namespace app\controller\admin\user;

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\user\UserService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

/**
 * 用户管理控制器
 */
class User extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * 用户服务
     */
    #[Inject]
    protected UserService $service;

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
     * 搜索字段（列表页筛选）
     */
    protected function getIndexFields(): array
    {
        return [
            ['nickname', ''],
            ['phone', ''],
            ['status', ''],
            ['sex', ''],
            ['is_distribution', ''],
            ['distribution_permission', ''],
            ['spread_uid', ''],
            ['occupation', ''],
            ['address', ''],
            ['create_time', ''],
        ];
    }

    /**
     * POST 请求获取的字段（新增/编辑）
     */
    protected function getRequestFields(): array
    {
        return [
            // 基础信息
            ['nickname', ''],
            ['avatar', ''],
            ['phone', ''],
            ['password', ''],
            ['confirm_password', ''], // 确认密码
            ['sex', 0],
            ['status', 1],
            ['remarks', ''],
            // 画像信息
            ['birthday', ''],
            ['age', 0],
            ['education', ''],
            ['occupation', ''],
            ['interests', ''],
            ['hobbies', ''],
            ['address', ''],
            // 分销信息
            ['is_distribution', 0],
            ['distribution_permission', -1],
            ['spread_uid', 0],
        ];
    }

    /**
     * 表单初始化字段
     */
    protected function getFormFields(): array
    {
        return [];
    }

    /**
     * 调整用户余额
     */
    public function adjustBalance(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['amount', 0],
            ['change_type', 'in'],
            ['remark', ''],
        ]);

        if (empty($data['user_id']) || $data['amount'] <= 0) {
            return $this->fail('参数错误');
        }

        if (!in_array($data['change_type'], ['in', 'out'])) {
            return $this->fail('变动类型错误');
        }

        $this->service->adjustBalance(
            (int)$data['user_id'],
            $data['amount'],
            $data['change_type'],
            $data['remark']
        );

        return $this->success('余额调整成功');
    }

    /**
     * 调整用户积分
     */
    public function adjustPoints(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['amount', 0],
            ['change_type', 'in'],
            ['remark', ''],
        ]);

        if (empty($data['user_id']) || $data['amount'] <= 0) {
            return $this->fail('参数错误');
        }

        if (!in_array($data['change_type'], ['in', 'out'])) {
            return $this->fail('变动类型错误');
        }

        $this->service->adjustPoints(
            (int)$data['user_id'],
            (float)$data['amount'],
            $data['change_type'],
            $data['remark']
        );

        return $this->success('积分调整成功');
    }

    /**
     * 设置用户分销权限
     */
    public function setDistribution(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['is_distribution', 0],
            ['distribution_permission', null],
        ]);

        if (empty($data['user_id'])) {
            return $this->fail('请指定用户');
        }

        $permission = $data['distribution_permission'] ?? $data['is_distribution'];
        $this->service->setDistribution((int)$data['user_id'], (int)$permission);
        return $this->success('分销权限设置成功');
    }

    /**
     * 获取用户的下级推广人列表
     */
    public function subordinates(int $userId): Response
    {
        $params = $this->request->getMore([
            ['page', 1],
            ['limit', 15],
            ['nickname', ''],
            ['phone', ''],
        ]);
        $result = $this->service->getSubordinates($userId, $params);
        return $this->success($result->toArray());
    }

    /**
     * 绑定上级分销人
     */
    public function bindSpreadUser(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['spread_uid', 0],
        ]);

        if (empty($data['user_id']) || empty($data['spread_uid'])) {
            return $this->fail('参数错误');
        }

        $this->service->bindSpreadUser((int)$data['user_id'], (int)$data['spread_uid']);
        return $this->success('上级分销人绑定成功');
    }

    /**
     * 设置用户禁评/允评
     */
    public function setCommentBan(): Response
    {
        $data = $this->request->postMore([
            ['user_id', 0],
            ['is_comment_ban', 0],
        ]);

        if (empty($data['user_id'])) {
            return $this->fail('请指定用户');
        }

        $this->service->setCommentBan((int)$data['user_id'], (int)$data['is_comment_ban']);

        $msg = (int)$data['is_comment_ban'] === 1 ? '已禁止该用户发表评论' : '已恢复该用户评论权限';
        return $this->success($msg);
    }

    /**
     * 更新用户基本信息（昵称、性别、生日、职业等）
     */
    public function updateBasic(int $id): Response
    {
        $params = $this->request->param();
        $allowed = ['nickname', 'avatar', 'sex', 'birthday', 'age', 'education', 'occupation', 'interests', 'hobbies', 'address'];
        $data = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $params)) {
                $data[$field] = $params[$field];
            }
        }

        if (empty($data)) {
            return $this->fail('请提交需要修改的字段');
        }

        $this->service->updateBasic($id, $data, $allowed);

        return $this->success('基本信息更新成功');
    }

    /**
     * 修改用户手机号
     */
    public function updatePhone(int $id): Response
    {
        $data = $this->request->postMore([
            ['phone', ''],
        ]);

        if (empty($data['phone'])) {
            return $this->fail('手机号不能为空');
        }

        $this->service->updatePhone($id, $data['phone']);

        return $this->success('手机号修改成功');
    }

    /**
     * 修改用户密码
     */
    public function updatePassword(int $id): Response
    {
        $data = $this->request->postMore([
            ['password', ''],
            ['confirm_password', ''],
        ]);

        if (empty($data['password'])) {
            return $this->fail('密码不能为空');
        }

        if ($data['password'] !== $data['confirm_password']) {
            return $this->fail('两次输入的密码不一致');
        }

        $this->service->updatePassword($id, $data['password']);

        return $this->success('密码修改成功');
    }
}
