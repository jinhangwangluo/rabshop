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

use app\common\exception\AdminResponseException;
use app\common\model\user\UserAddress;
use app\common\service\BaseService;

/**
 * 用户收货地址服务
 */
class UserAddressService extends BaseService
{
    /**
     * 对应模型。
     */
    protected function setModel(): string
    {
        return UserAddress::class;
    }

    /**
     * 获取用户收货地址列表。
     *
     * @param int $userId 用户ID。
     * @return array
     */
    public function getUserAddressList(int $userId): array
    {
        if ($userId <= 0) {
            return ['list' => [], 'default_address' => null];
        }

        $list = $this->getModel()
            ->where('user_id', $userId)
            ->order('is_default', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $list = array_map(fn(array $item): array => $this->formatAddress($item), $list);
        $defaultAddress = null;
        foreach ($list as $item) {
            if ((int)$item['is_default'] === 1) {
                $defaultAddress = $item;
                break;
            }
        }

        return [
            'list' => $list,
            'default_address' => $defaultAddress,
        ];
    }

    /**
     * 获取用户收货地址详情。
     *
     * @param int $userId 用户ID。
     * @param int $id 地址ID。
     * @return array
     * @throws AdminResponseException
     */
    public function getUserAddressDetail(int $userId, int $id): array
    {
        $address = $this->getUserAddressModel($userId, $id);

        return $this->formatAddress($address->toArray());
    }

    /**
     * 获取用户默认收货地址。
     *
     * @param int $userId 用户ID。
     * @return array|null 默认地址，不存在时返回 null。
     */
    public function getDefaultUserAddress(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $address = $this->getModel()
            ->where('user_id', $userId)
            ->order('is_default', 'desc')
            ->order('id', 'desc')
            ->find();

        return $address ? $this->formatAddress($address->toArray()) : null;
    }

    /**
     * 创建用户收货地址。
     *
     * @param int $userId 用户ID。
     * @param array $data 地址数据。
     * @return array
     * @throws AdminResponseException
     */
    public function createUserAddress(int $userId, array $data): array
    {
        if ($userId <= 0) {
            throw new AdminResponseException('未获取到登录用户');
        }

        $data = $this->filterAddressData($data);
        $data['user_id'] = $userId;

        return $this->transaction(function () use ($userId, $data) {
            if ((int)$data['is_default'] === 1 || $this->count(['user_id' => $userId]) === 0) {
                $this->clearDefault($userId);
                $data['is_default'] = 1;
            }

            $address = $this->create($data);
            $addressId = (int)($address->id ?? 0);
            if ($addressId <= 0) {
                throw new AdminResponseException('收货地址创建失败');
            }

            return $this->getUserAddressDetail($userId, $addressId);
        });
    }

    /**
     * 更新用户收货地址。
     *
     * @param int $userId 用户ID。
     * @param int $id 地址ID。
     * @param array $data 地址数据。
     * @return array
     * @throws AdminResponseException
     */
    public function updateUserAddress(int $userId, int $id, array $data): array
    {
        $address = $this->getUserAddressModel($userId, $id);
        $data = $this->filterAddressData($data);

        return $this->transaction(function () use ($userId, $address, $data) {
            if ((int)$data['is_default'] === 1) {
                $this->clearDefault($userId, (int)$address->id);
            }

            $address->save($data);

            return $this->formatAddress($address->refresh()->toArray());
        });
    }

    /**
     * 删除用户收货地址。
     *
     * @param int $userId 用户ID。
     * @param int $id 地址ID。
     * @return bool
     * @throws AdminResponseException
     */
    public function deleteUserAddress(int $userId, int $id): bool
    {
        $address = $this->getUserAddressModel($userId, $id);

        return (bool)$address->delete();
    }

    /**
     * 查询指定用户地址模型。
     *
     * @param int $userId 用户ID。
     * @param int $id 地址ID。
     * @return UserAddress
     * @throws AdminResponseException
     */
    protected function getUserAddressModel(int $userId, int $id): UserAddress
    {
        if ($userId <= 0 || $id <= 0) {
            throw new AdminResponseException('地址参数不正确');
        }

        $address = $this->getModel()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$address) {
            throw new AdminResponseException('收货地址不存在');
        }

        return $address;
    }

    /**
     * 清除用户其他默认地址。
     *
     * @param int $userId 用户ID。
     * @param int $excludeId 排除地址ID。
     * @return void
     */
    protected function clearDefault(int $userId, int $excludeId = 0): void
    {
        $query = $this->getModel()->where('user_id', $userId)->where('is_default', 1);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        $query->update(['is_default' => 0]);
    }

    /**
     * 过滤并校验地址数据。
     *
     * @param array $data 原始地址数据。
     * @return array
     * @throws AdminResponseException
     */
    protected function filterAddressData(array $data): array
    {
        $realName = trim((string)($data['real_name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $province = trim((string)($data['province'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        $district = trim((string)($data['district'] ?? ''));
        $detail = trim((string)($data['detail'] ?? ''));
        $userAddress = trim((string)($data['user_address'] ?? ''));
        $provinceId = (int)($data['province_id'] ?? 0);
        $cityId = (int)($data['city_id'] ?? 0);
        $districtId = (int)($data['district_id'] ?? 0);
        $areaId = (int)($data['area_id'] ?? 0);
        $latitude = $this->formatCoordinate($data['latitude'] ?? '');
        $longitude = $this->formatCoordinate($data['longitude'] ?? '');
        $mapName = trim((string)($data['map_name'] ?? ''));
        $isDefault = (int)($data['is_default'] ?? 0) === 1 ? 1 : 0;

        if ($realName === '') {
            throw new AdminResponseException('请输入收货人姓名');
        }

        if (!is_valid_phone($phone)) {
            throw new AdminResponseException('请输入正确的手机号');
        }

        if ($province === '' || $city === '' || $district === '') {
            throw new AdminResponseException('请选择所在地区');
        }

        if ($detail === '') {
            throw new AdminResponseException('请输入详细地址');
        }

        if ($userAddress === '') {
            $userAddress = trim($province . ' ' . $city . ' ' . $district . ' ' . $detail);
        }

        return [
            'real_name' => $realName,
            'phone' => $phone,
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'district_id' => $districtId,
            'area_id' => $areaId > 0 ? $areaId : $districtId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'map_name' => $mapName,
            'detail' => $detail,
            'user_address' => $userAddress,
            'is_default' => $isDefault,
        ];
    }

    /**
     * 格式化地图经纬度。
     *
     * @param mixed $value 经纬度原始值。
     * @return string
     */
    protected function formatCoordinate(mixed $value): string
    {
        $coordinate = trim((string)$value);
        if ($coordinate === '') {
            return '0.000000';
        }

        return number_format((float)$coordinate, 6, '.', '');
    }

    /**
     * 格式化地址返回字段。
     *
     * @param array $address 地址数据。
     * @return array
     */
    protected function formatAddress(array $address): array
    {
        return [
            'id' => (int)($address['id'] ?? 0),
            'user_id' => (int)($address['user_id'] ?? 0),
            'real_name' => (string)($address['real_name'] ?? ''),
            'phone' => (string)($address['phone'] ?? ''),
            'province' => (string)($address['province'] ?? ''),
            'city' => (string)($address['city'] ?? ''),
            'district' => (string)($address['district'] ?? ''),
            'province_id' => (int)($address['province_id'] ?? 0),
            'city_id' => (int)($address['city_id'] ?? 0),
            'district_id' => (int)($address['district_id'] ?? 0),
            'area_id' => (int)($address['area_id'] ?? 0),
            'latitude' => (string)($address['latitude'] ?? ''),
            'longitude' => (string)($address['longitude'] ?? ''),
            'map_name' => (string)($address['map_name'] ?? ''),
            'detail' => (string)($address['detail'] ?? ''),
            'user_address' => (string)($address['user_address'] ?? ''),
            'is_default' => (int)($address['is_default'] ?? 0),
            'create_time' => (string)($address['create_time'] ?? ''),
            'update_time' => (string)($address['update_time'] ?? ''),
        ];
    }
}
