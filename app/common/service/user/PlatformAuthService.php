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

namespace app\common\service\user;

use app\common\model\user\UserPlatformAuth;
use app\common\service\BaseService;

class PlatformAuthService extends BaseService
{
    protected function setModel(): string
    {
        return UserPlatformAuth::class;
    }

    /**
     * 根据平台和openid获取认证记录
     * @param string $platform
     * @param string $openid
     * @return array|null
     */
    public function getByPlatformOpenid(string $platform, string $openid): ?array
    {
        $record = $this->get([
            'platform' => $platform,
            'openid' => $openid,
        ]);

        return $record ? $record->toArray() : null;
    }

    /**
     * 绑定平台账号
     * @param int $uid
     * @param string $platform
     * @param string $openid
     * @param array $userInfo
     * @param array $extraData
     * @return UserPlatformAuth
     */
    public function bindPlatform(int $uid, string $platform, string $openid, array $userInfo, array $extraData = []): UserPlatformAuth
    {
        /** @var UserPlatformAuth $auth */
        $auth = $this->create([
            'uid' => $uid,
            'platform' => $platform,
            'openid' => $openid,
            'unionid' => $userInfo['unionid'] ?? null,
            'nickname' => $userInfo['nickname'] ?? '',
            'avatar' => $userInfo['avatar'] ?? '',
            'extra_data' => $extraData,
            'bind_time' => date('Y-m-d H:i:s'),
        ]);

        return $auth;
    }

    /**
     * 根据用户和平台获取认证记录
     */
    public function getByUidAndPlatform(int $uid, string $platform): ?array
    {
        if ($uid <= 0 || $platform === '') {
            return null;
        }

        $record = $this->get([
            'uid' => $uid,
            'platform' => $platform,
        ]);

        return $record ? $record->toArray() : null;
    }

    /**
     * 更新扩展数据
     * @param int $id
     * @param array $extraData
     * @return bool
     */
    public function updateExtraData(int $id, array $extraData): bool
    {
        $auth = $this->get($id);
        if (!$auth) {
            return false;
        }
        $currentExtra = $auth->extra_data ?? [];
        $auth->extra_data = array_merge($currentExtra, $extraData);
        $auth->update_time = date('Y-m-d H:i:s');
        return $auth->save();
    }
}
