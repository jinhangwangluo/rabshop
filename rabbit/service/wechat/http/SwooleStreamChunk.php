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

/**
 * Swoole 响应流数据块。
 *
 * 用于配合 SwooleResponseStream 提供 Symfony ChunkInterface 的最小实现。
 */
class SwooleStreamChunk implements ChunkInterface
{
    /**
     * 构造响应数据块。
     *
     * @param bool $first 是否为首个数据块。
     * @param bool $last 是否为最后一个数据块。
     * @param string $content 数据块内容。
     * @param int $offset 数据块偏移量。
     * @param string|null $error 错误信息。
     */
    public function __construct(
        protected bool $first,
        protected bool $last,
        protected string $content,
        protected int $offset = 0,
        protected ?string $error = null
    ) {
    }

    /**
     * 判断是否为超时块。
     *
     * @return bool 当前适配器不产生超时块，固定返回 false。
     */
    public function isTimeout(): bool
    {
        return false;
    }

    /**
     * 判断是否为首个数据块。
     *
     * @return bool 是首个数据块返回 true。
     */
    public function isFirst(): bool
    {
        return $this->first;
    }

    /**
     * 判断是否为最后一个数据块。
     *
     * @return bool 是最后一个数据块返回 true。
     */
    public function isLast(): bool
    {
        return $this->last;
    }

    /**
     * 获取 1xx 信息状态。
     *
     * @return array{0:int,1:array<string,array<int,string>>}|null 当前适配器不处理 1xx，固定返回 null。
     */
    public function getInformationalStatus(): ?array
    {
        return null;
    }

    /**
     * 获取数据块内容。
     *
     * @return string 数据块内容。
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * 获取数据块偏移量。
     *
     * @return int 偏移量。
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * 获取错误信息。
     *
     * @return string|null 错误信息，无错误时为 null。
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
