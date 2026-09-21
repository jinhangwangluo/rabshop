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

namespace app\common\service\plugin\dto;

use app\common\exception\PluginLifecycleException;

/**
 * 生命周期操作人快照。
 */
final class PluginActor
{
    public function __construct(
        public readonly string $type = 'system',
        public readonly int $id = 0
    ) {
        if (!in_array($this->type, ['admin', 'system', 'callback'], true)) {
            throw new PluginLifecycleException('操作人类型无效', 'INVALID_ACTOR_TYPE');
        }
        if ($this->id < 0) {
            throw new PluginLifecycleException('操作人 ID 无效', 'INVALID_ACTOR_ID');
        }
    }

    /**
     * @param array<string,mixed>|self|null $value
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (!is_array($value)) {
            return new self();
        }

        return new self(
            (string)($value['type'] ?? 'system'),
            max(0, (int)($value['id'] ?? 0))
        );
    }

    /**
     * @return array{type:string,id:int}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
