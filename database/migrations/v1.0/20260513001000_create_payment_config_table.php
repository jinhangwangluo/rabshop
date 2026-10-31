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

use think\migration\Migrator;

/**
 * 支付配置表。
 */
class CreatePaymentConfigTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('payment_config', ['comment' => '支付配置表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('pay_type', 'string', ['limit' => 32, 'default' => '', 'comment' => '支付类型：yue/wechat/alipay']);
        $table->addColumn('name', 'string', ['limit' => 64, 'default' => '', 'comment' => '支付方式名称']);
        $table->addColumn('icon', 'string', ['limit' => 512, 'default' => '', 'comment' => '支付方式图标']);
        $table->addColumn('text', 'string', ['limit' => 64, 'default' => '', 'comment' => '列表展示文字']);
        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '支付标题']);
        $table->addColumn('subtitle', 'string', ['limit' => 255, 'default' => '', 'comment' => '支付副标题']);
        $table->addColumn('description', 'string', ['limit' => 255, 'default' => '', 'comment' => '支付方式描述']);
        $table->addColumn('terminals', 'text', ['null' => true, 'comment' => '支持终端 JSON']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('pay_type', ['unique' => true]);
        $table->addIndex('status');
        $table->addIndex('sort');
        $table->create();

        $this->migrateLegacyDisplayConfig();
    }

    /**
     * 获取数据库表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }

    /**
     * 将旧支付展示配置迁移到 payment_config 表。
     */
    private function migrateLegacyDisplayConfig(): void
    {
        $prefix = $this->getPrefix();
        $row = $this->fetchRow(
            "SELECT `value` FROM `{$prefix}config` WHERE `key_name` = 'payment_display_config' LIMIT 1"
        );
        if (!$row || !isset($row['value'])) {
            return;
        }

        $config = json_decode((string)$row['value'], true);
        $methods = is_array($config) && isset($config['methods']) && is_array($config['methods'])
            ? $config['methods']
            : [];
        if ($methods === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($methods as $method) {
            if (!is_array($method)) {
                continue;
            }

            $payType = trim((string)($method['pay_type'] ?? ''));
            if ($payType === '') {
                continue;
            }

            $data = $this->buildRowData($method, $now);
            $exists = $this->fetchRow(
                "SELECT `id` FROM `{$prefix}payment_config` WHERE `pay_type` = '" . addslashes($payType)
                . "' AND `delete_time` IS NULL LIMIT 1"
            );

            if ($exists && (int)($exists['id'] ?? 0) > 0) {
                $this->updatePaymentConfig((int)$exists['id'], $data);
                continue;
            }

            $data['pay_type'] = $payType;
            $data['create_time'] = $now;
            $this->table('payment_config')->insert([$data])->save();
        }
    }

    /**
     * 构造支付配置行。
     *
     * @param array $method 旧支付展示配置。
     * @param string $now 当前时间。
     */
    private function buildRowData(array $method, string $now): array
    {
        $title = trim((string)($method['title'] ?? $method['name'] ?? ''));
        $text = trim((string)($method['text'] ?? ''));
        $subtitle = trim((string)($method['subtitle'] ?? $method['description'] ?? ''));
        $terminals = $method['terminals'] ?? [];

        return [
            'name' => $title !== '' ? $title : $text,
            'icon' => trim((string)($method['image'] ?? $method['icon'] ?? '')),
            'text' => $text,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $subtitle,
            'terminals' => json_encode(is_array($terminals) ? array_values($terminals) : [], JSON_UNESCAPED_UNICODE),
            'sort' => max(0, (int)($method['sort'] ?? 0)),
            'status' => (int)($method['status'] ?? 0) === 1 ? 1 : 0,
            'update_time' => $now,
        ];
    }

    /**
     * 更新已有支付配置。
     *
     * @param int $id 支付配置ID。
     * @param array $data 支付配置数据。
     */
    private function updatePaymentConfig(int $id, array $data): void
    {
        $sets = [];
        foreach ($data as $field => $value) {
            $sets[] = "`{$field}` = '" . addslashes((string)$value) . "'";
        }

        if ($sets === []) {
            return;
        }

        $prefix = $this->getPrefix();
        $this->execute("UPDATE `{$prefix}payment_config` SET " . implode(', ', $sets) . " WHERE `id` = {$id}");
    }
}
