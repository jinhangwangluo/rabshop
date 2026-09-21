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

namespace Rabbit\service\wechat\http;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Swoole 响应流适配器。
 *
 * 当前微信接口均按同步响应读取，该类提供最小 ResponseStreamInterface 实现，
 * 用于满足 Symfony HttpClientInterface::stream() 的接口约束。
 */
class SwooleResponseStream implements ResponseStreamInterface
{
    /**
     * 当前迭代位置。
     */
    protected int $position = 0;

    /**
     * 响应对象列表。
     *
     * @var array<int,ResponseInterface>
     */
    protected array $responses;

    /**
     * 构造响应流。
     *
     * @param ResponseInterface|iterable<ResponseInterface> $responses 一个或多个响应对象。
     */
    public function __construct(ResponseInterface|iterable $responses)
    {
        if ($responses instanceof ResponseInterface) {
            $this->responses = [$responses];
            return;
        }

        $this->responses = [];
        foreach ($responses as $response) {
            if ($response instanceof ResponseInterface) {
                $this->responses[] = $response;
            }
        }
    }

    /**
     * 获取当前响应对应的数据块。
     *
     * @return ChunkInterface 当前数据块。
     */
    public function current(): ChunkInterface
    {
        return new SwooleStreamChunk($this->position === 0, true, '');
    }

    /**
     * 移动到下一个响应。
     *
     * @return void
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * 获取当前响应对象。
     *
     * @return ResponseInterface 当前响应。
     */
    public function key(): ResponseInterface
    {
        return $this->responses[$this->position];
    }

    /**
     * 判断当前位置是否有效。
     *
     * @return bool 有可迭代响应时返回 true。
     */
    public function valid(): bool
    {
        return isset($this->responses[$this->position]);
    }

    /**
     * 重置迭代位置。
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }
}
