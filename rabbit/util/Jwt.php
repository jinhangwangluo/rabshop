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

namespace Rabbit\util;

use Firebase\JWT\JWT as JwtToken;
use Firebase\JWT\Key;


class Jwt
{

    /**
     * 创建token
     * @param int $id
     * @param string|null $type
     * @return string
     */
    public static function create(int $id, int $exp = 0, ?string $type = null)
    {
        $key = config('app.app_key', '');
        $host = app()->request->host();
        $time = time();
        $exp = $exp ?: strtotime('+ 10day');

        $payload = [
            "iss" => $host,
            "aud" => $host,
            "iat" => $time,
            "nbf" => $time,
            'exp' => $exp
        ];
        $payload['jti'] = compact('id', 'type');

        return JwtToken::encode($payload, $key,'HS256');
    }

    /**
     * @param string $jwt
     * @return bool
     */
    public static function decode(string $jwt)
    {
        $key = config('app.app_key', '');

        try {
            $decoded = JwtToken::decode($jwt, new Key($key, 'HS256'));
            return $decoded->jti;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
