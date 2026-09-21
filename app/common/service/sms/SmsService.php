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

namespace app\common\service\sms;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use app\common\enum\ConfigKeyEnum;
use app\common\model\Base;
use app\common\service\BaseService;
use Darabonba\OpenApi\Models\Config as OpenApiConfig;
use Rabbit\util\Lock;
use think\facade\Cache;
use think\facade\Log;

/**
 * 短信验证码服务
 * 使用阿里云 Dysms
 */
class SmsService extends BaseService
{
    /**
     * 同一手机号短信发送冷却时间。
     */
    private const PHONE_LIMIT_SECONDS = 60;

    /**
     * 同一 IP 短信发送统计窗口。
     */
    private const IP_LIMIT_SECONDS = 3600;

    /**
     * 同一 IP 在统计窗口内最多发送次数。
     * 收紧限制，降低代理 IP 池刷量收益。
     */
    private const IP_LIMIT_MAX = 5;

    /**
     * 验证码有效期。
     */
    private const CODE_EXPIRE_SECONDS = 300;

    /**
     * 单个手机号发送锁过期时间。
     */
    private const SEND_LOCK_SECONDS = 8;

    /**
     * 设置模型（短信服务本身不依赖具体模型）
     */
    protected function setModel(): string
    {
        return Base::class;
    }

    /**
     * 发送短信验证码
     * @param string $phone 手机号
     * @return bool
     * @throws \Exception
     */
    public function sendVerificationCode(string $phone): bool
    {
        $phone = trim($phone);
        if (!is_valid_phone($phone)) {
            throw new \Exception('请输入正确的手机号', 400);
        }

        /** @var Lock $lock */
        $lock = app()->make(Lock::class);
        $lockKey = "sms_send_verification:{$phone}";
        $lockValue = bin2hex(random_bytes(16));

        try {
            if (!$lock->tryLock($lockKey, $lockValue, self::SEND_LOCK_SECONDS)) {
                throw new \Exception('操作过于频繁，请稍后再试', 429);
            }
        } catch (\RuntimeException $exception) {
            Log::warning('短信发送锁获取失败', [
                'phone' => $phone,
                'message' => $exception->getMessage(),
            ]);
            throw new \Exception('短信发送处理中，请稍后再试', 429);
        }

        try {
            return $this->sendVerificationCodeWithLock($phone);
        } finally {
            try {
                $lock->unlock($lockKey, $lockValue);
            } catch (\Throwable $throwable) {
                Log::warning('短信发送锁释放失败', [
                    'phone' => $phone,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }
    }

    /**
     * 在手机号发送锁内发送短信验证码。
     *
     * @param string $phone 手机号
     * @return bool
     * @throws \Exception
     */
    private function sendVerificationCodeWithLock(string $phone): bool
    {
        $limitKey = "sms_limit:{$phone}";
        if (Cache::get($limitKey)) {
            throw new \Exception('操作过于频繁，请稍后再试', 429);
        }

        $ip = request()->ip();
        $ipLimitKey = "sms_ip_limit:" . $ip;
        $ipCount = $this->incrementCounter($ipLimitKey, self::IP_LIMIT_SECONDS);
        if ($ipCount > self::IP_LIMIT_MAX) {
            throw new \Exception('操作过于频繁，请稍后再试', 429);
        }

        Cache::set($limitKey, 1, self::PHONE_LIMIT_SECONDS);
        $code = random_int(100000, 999999);

        try {
            $result = $this->sendSms($phone, $code);
        } catch (\Throwable $throwable) {
            Cache::delete($limitKey);
            Log::warning('短信验证码发送异常', [
                'phone' => $phone,
                'message' => $throwable->getMessage(),
            ]);
            throw new \Exception('短信发送失败，请稍后再试', 500, $throwable);
        }

        if (!$result) {
            Cache::delete($limitKey);
            throw new \Exception('短信发送失败，请稍后再试', 500);
        }

        Cache::set("sms_code:{$phone}", (string) $code, self::CODE_EXPIRE_SECONDS);

        return true;
    }

    /**
     * 验证短信验证码
     * @param string $phone 手机号
     * @param string $code 验证码
     * @return bool
     * @throws \Exception
     */
    public function verifyCode(string $phone, string $code): bool
    {
        $cachedCode = Cache::get("sms_code:{$phone}");
        if (!$cachedCode) {
            throw new \Exception('验证码已过期', 400);
        }

        if ($cachedCode != $code) {
            throw new \Exception('验证码错误', 400);
        }

        // 验证成功后删除验证码
        Cache::delete("sms_code:{$phone}");

        return true;
    }

    /**
     * 调用阿里云发送短信
     * @param string $phone
     * @param int $code
     * @return bool
     */
    private function sendSms(string $phone, int $code): bool
    {
        $accessKeyId = trim((string)db_config(ConfigKeyEnum::SMS_ACCESS_KEY, ''));
        $accessSecret = trim((string)db_config(ConfigKeyEnum::SMS_SECRET_KEY, ''));
        $signName = trim((string)db_config(ConfigKeyEnum::SMS_SIGN, ''));
        $templateCode = trim((string)db_config(ConfigKeyEnum::SMS_CODE_TEMPLATE_ID, ''));
        if ($accessKeyId === '' || $accessSecret === '' || $signName === '' || $templateCode === '') {
            Log::warning('阿里云短信配置未完整设置，验证码短信未发送', ['phone' => $phone]);
            return false;
        }

        $client = new Dysmsapi(new OpenApiConfig([
            'accessKeyId' => $accessKeyId,
            'accessKeySecret' => $accessSecret,
            'endpoint' => 'dysmsapi.aliyuncs.com',
        ]));
        $request = new SendSmsRequest([
            'phoneNumbers' => $phone,
            'signName' => $signName,
            'templateCode' => $templateCode,
            'templateParam' => json_encode(['code' => $code], JSON_UNESCAPED_UNICODE),
        ]);
        $response = $client->sendSms($request)->toArray();
        $responseCode = (string)($response['body']['Code'] ?? '');
        if ($responseCode !== 'OK') {
            Log::warning('阿里云短信验证码发送失败', [
                'phone' => $phone,
                'response' => $response,
            ]);
            return false;
        }

        return true;
    }

    /**
     * 获取计数器。
     *
     * @param string $key 缓存键
     * @return int
     */
    private function getCounter(string $key): int
    {
        return max(0, (int) Cache::get($key, 0));
    }

    /**
     * 原子递增计数器并刷新过期时间。
     *
     * @param string $key 缓存键
     * @param int $expireSeconds 过期秒数
     * @return int
     */
    private function incrementCounter(string $key, int $expireSeconds): int
    {
        $count = Cache::inc($key, 1);
        if ($count === false) {
            $count = $this->getCounter($key) + 1;
            Cache::set($key, $count, $expireSeconds);

            return $count;
        }

        $this->refreshCounterExpire($key, $expireSeconds);

        return max(0, (int) $count);
    }

    /**
     * 刷新计数器过期时间。
     *
     * @param string $key 缓存键
     * @param int $expireSeconds 过期秒数
     * @return void
     */
    private function refreshCounterExpire(string $key, int $expireSeconds): void
    {
        try {
            $store = Cache::store();
            $cacheKey = method_exists($store, 'getCacheKey') ? $store->getCacheKey($key) : $key;
            $handler = method_exists($store, 'handler') ? $store->handler() : null;
            if (is_object($handler) && method_exists($handler, 'expire') && $handler->expire($cacheKey, $expireSeconds)) {
                return;
            }
        } catch (\Throwable $exception) {
            Log::warning('短信发送计数器过期时间刷新失败', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);
        }

        Cache::set($key, $this->getCounter($key), $expireSeconds);
    }
}
