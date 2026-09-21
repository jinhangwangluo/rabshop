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

use think\facade\Log;

/**
 * Curl类
 */
class Curl
{
    /**
     * curl请求
     * @param string $url
     * @param string $method
     * @param string|array $data
     * @param array $header
     * @param int $timeout
     * @return false|string
     */
    public function request(string $url, string $method = 'GET', string|array $data = [], array $header = [], int $timeout = 20)
    {
        $curl = curl_init($url);
        $method = strtoupper($method);
        //请求方式
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        //携带参数
        if ($method == 'POST' || $method == 'PUT' || $method == 'DELETE') {
            if (array_filter($header, function ($v) {
                return $v !== 'multipart/form-data';
            })) {
                $data = is_array($data) ? http_build_query($data) : $data;
            }
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        } elseif ($method == 'GET' && count($data)) {
            $url .= (str_contains($url, '&') ? '&' : '?') . http_build_query($data);

            Log::info('请求数据：' . json_encode(['url' => $url, 'data' => $data], JSON_UNESCAPED_UNICODE));

            curl_setopt($curl, CURLOPT_URL, $url);
        }
        //超时时间
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        //设置header头
        if ($header) {
            $h = [];
            foreach ($header as $key => $value) {
                $h[] = $key . ':' . $value;
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $h);
        }

        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        //返回抓取数据
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //输出header头信息
        curl_setopt($curl, CURLOPT_HEADER, true);
        //TRUE 时追踪句柄的请求字符串，从 PHP 5.1.3 开始可用。这个很关键，就是允许你查看请求header
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        //https请求
        if (1 == strpos("$" . $url, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        [$content, $status] = [curl_exec($curl), curl_getinfo($curl), curl_close($curl)];

        $content = trim(substr($content, $status['header_size']));

        return (intval($status["http_code"]) === 200) ? $content : false;
    }
}
