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

use app\common\enum\CombinationDataTypeEnum;
use think\migration\Migrator;

/**
 * 创建组合数据表.
 */
class CreateCombinationDataTable extends Migrator
{
    /**
     * 定义组合数据表结构.
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('combination_data', [
            'comment' => '组合数据表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分类ID']);
        $table->addColumn('config_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '组合数据配置ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID，0为平台']);
        $table->addColumn('data_type', 'string', ['limit' => 80, 'default' => '', 'comment' => '数据类型，来源于配置标识']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0禁用，1启用']);
        $table->addColumn('data_json', 'text', ['null' => true, 'comment' => '数据内容(JSON)']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['merchant_id', 'config_id'], ['name' => 'merchant_config']);
        $table->addIndex(['merchant_id', 'config_id', 'status', 'sort'], ['name' => 'merchant_config_status_sort']);
        $table->addIndex(['merchant_id', 'category_id'], ['name' => 'merchant_category']);
        $table->addIndex('data_type', ['name' => 'data_type']);
        $table->create();

        $this->seedMemberCombinationData();
        $this->seedMergedCombinationData();
    }

    /**
     * 写入后续迁移中归档到组合数据表的默认记录。
     */
    private function seedMergedCombinationData(): void
    {
        $now = date('Y-m-d H:i:s');
        $liveCoinConfigId = $this->getCombinationConfigId(CombinationDataTypeEnum::LIVE_COIN_RECHARGE);
        if ($liveCoinConfigId > 0) {
            $rows = [];
            foreach ([100, 500, 1000, 5000, 10000] as $index => $coinAmount) {
                $row = [
                    'category_id' => 0,
                    'config_id' => $liveCoinConfigId,
                    'merchant_id' => 0,
                    'data_type' => CombinationDataTypeEnum::LIVE_COIN_RECHARGE,
                    'sort' => 100 - ($index * 10),
                    'status' => 1,
                    'data_json' => json_encode(['coin_amount' => $coinAmount], JSON_UNESCAPED_UNICODE),
                    'create_time' => $now,
                    'update_time' => $now,
                ];
                if (!$this->combinationDataExists($row)) {
                    $rows[] = $row;
                }
            }
            if ($rows !== []) {
                $this->table('combination_data')->insert($rows)->saveData();
            }
        }

        $signinConfigId = $this->getCombinationConfigId(CombinationDataTypeEnum::ACTIVITY_SIGNIN_REWARD);
        if ($signinConfigId > 0) {
            $rows = [];
            foreach ([5, 5, 8, 8, 10, 10, 15] as $index => $points) {
                $row = [
                    'category_id' => 0,
                    'config_id' => $signinConfigId,
                    'merchant_id' => 0,
                    'data_type' => CombinationDataTypeEnum::ACTIVITY_SIGNIN_REWARD,
                    'sort' => 100 - ($index * 10),
                    'status' => 1,
                    'data_json' => json_encode([
                        'day' => $index + 1,
                        'points' => $points,
                        'coupon_id' => 0,
                    ], JSON_UNESCAPED_UNICODE),
                    'create_time' => $now,
                    'update_time' => $now,
                ];
                if (!$this->combinationDataExists($row)) {
                    $rows[] = $row;
                }
            }
            if ($rows !== []) {
                $this->table('combination_data')->insert($rows)->saveData();
            }
        }
    }

    /**
     * 获取数据库表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }

    /**
     * 写入会员套餐和会员权益默认数据。
     */
    private function seedMemberCombinationData(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->insertDefaultItems(
            CombinationDataTypeEnum::MEMBER_PACKAGE,
            $this->getCombinationConfigId(CombinationDataTypeEnum::MEMBER_PACKAGE),
            $this->defaultPackages(),
            $now
        );

        $this->insertDefaultItems(
            CombinationDataTypeEnum::MEMBER_BENEFIT,
            $this->getCombinationConfigId(CombinationDataTypeEnum::MEMBER_BENEFIT),
            $this->defaultBenefits(),
            $now
        );
    }

    /**
     * 获取组合数据配置 ID。
     *
     * @param string $configKey 组合数据配置标识。
     */
    private function getCombinationConfigId(string $configKey): int
    {
        $prefix = $this->getPrefix();
        $safeKey = addslashes($configKey);
        $row = $this->fetchRow(
            "SELECT `id` FROM `{$prefix}combination_data_config` WHERE `merchant_id` = 0 AND `config_key` = '{$safeKey}' LIMIT 1"
        );

        return (int)($row['id'] ?? 0);
    }

    /**
     * 写入默认组合数据。
     *
     * @param string $dataType 数据类型。
     * @param int $configId 组合数据配置ID。
     * @param array $items 默认数据列表。
     * @param string $now 当前时间。
     */
    private function insertDefaultItems(string $dataType, int $configId, array $items, string $now): void
    {
        if ($configId <= 0) {
            return;
        }

        $rows = [];
        foreach ($items as $index => $item) {
            $row = [
                'category_id' => 0,
                'config_id' => $configId,
                'merchant_id' => 0,
                'data_type' => $dataType,
                'sort' => 100 - $index,
                'status' => 1,
                'data_json' => json_encode($item, JSON_UNESCAPED_UNICODE),
                'create_time' => $now,
                'update_time' => $now,
            ];
            if (!$this->combinationDataExists($row)) {
                $rows[] = $row;
            }
        }

        if ($rows !== []) {
            $this->table('combination_data')->insert($rows)->saveData();
        }
    }

    /**
     * 判断组合数据是否已存在。
     *
     * @param array $row 组合数据记录。
     */
    private function combinationDataExists(array $row): bool
    {
        $prefix = $this->getPrefix();
        $categoryId = (int)($row['category_id'] ?? 0);
        $configId = (int)($row['config_id'] ?? 0);
        $merchantId = (int)($row['merchant_id'] ?? 0);
        $dataType = addslashes((string)($row['data_type'] ?? ''));
        $sort = (int)($row['sort'] ?? 0);
        $status = (int)($row['status'] ?? 0);
        $dataJson = addslashes((string)($row['data_json'] ?? ''));

        $sql = "SELECT `id` FROM `{$prefix}combination_data`"
            . " WHERE `category_id` = {$categoryId}"
            . " AND `config_id` = {$configId}"
            . " AND `merchant_id` = {$merchantId}"
            . " AND `data_type` = '{$dataType}'"
            . " AND `sort` = {$sort}"
            . " AND `status` = {$status}"
            . " AND `data_json` = '{$dataJson}'"
            . " LIMIT 1";

        return (bool)$this->fetchRow($sql);
    }

    /**
     * 默认会员套餐。
     */
    private function defaultPackages(): array
    {
        return [
            ['key' => 'month', 'name' => '月卡', 'price' => 19.9, 'original_price' => 25, 'duration_days' => 30, 'tag' => ''],
            ['key' => 'year', 'name' => '年卡', 'price' => 158, 'original_price' => 299, 'duration_days' => 365, 'tag' => '最划算'],
            ['key' => 'quarter', 'name' => '季卡', 'price' => 49.9, 'original_price' => 75, 'duration_days' => 90, 'tag' => ''],
        ];
    }

    /**
     * 默认会员权益。
     */
    private function defaultBenefits(): array
    {
        return [
            ['key' => 'points', 'label' => '双倍积分', 'symbol' => '↗', 'desc' => '购物获得更多积分'],
            ['key' => 'discount', 'label' => '专属折扣', 'symbol' => '◇', 'desc' => '会员专属折扣权益'],
            ['key' => 'delivery', 'label' => '极速发货', 'symbol' => '▱', 'desc' => '优先处理会员订单'],
            ['key' => 'service', 'label' => '专属客服', 'symbol' => '☎', 'desc' => '会员专属客服通道'],
            ['key' => 'coupon', 'label' => '免费领券', 'symbol' => '□', 'desc' => '定期领取会员券'],
            ['key' => 'birthday', 'label' => '生日礼包', 'symbol' => '✚', 'desc' => '生日月专属礼包'],
            ['key' => 'trial', 'label' => '新品试用', 'symbol' => '✧', 'desc' => '优先参与新品试用'],
            ['key' => 'offline', 'label' => '线下权益', 'symbol' => '▤', 'desc' => '尊享线下活动权益'],
        ];
    }
}
