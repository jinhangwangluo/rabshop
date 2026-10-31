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
 * 创建组合数据配置表.
 */
class CreateCombinationDataConfigTable extends Migrator
{
    /**
     * 定义组合数据配置表结构.
     *
     * @return void
     */
    public function change()
    {
        $table = $this->table('combination_data_config', [
            'comment' => '组合数据配置表',
            'collation' => 'utf8mb4_general_ci',
            'engine' => 'InnoDB',
            'row_format' => 'DYNAMIC',
        ]);
        $table->addColumn('category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分类ID']);
        $table->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID，0为平台']);
        $table->addColumn('config_name', 'string', ['limit' => 50, 'default' => '', 'comment' => '配置名称']);
        $table->addColumn('config_desc', 'string', ['limit' => 255, 'default' => '', 'comment' => '配置描述']);
        $table->addColumn('config_key', 'string', ['limit' => 80, 'default' => '', 'comment' => '配置标识，作为数据类型']);
        $table->addColumn('content', 'text', ['null' => true, 'comment' => '表单设计内容(JSON)']);
        $table->addColumn('field_keys', 'text', ['null' => true, 'comment' => '字段键(JSON)']);
        $table->addColumn('fields', 'text', ['null' => true, 'comment' => '预定义字段列表(JSON)']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0禁用，1启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex(['merchant_id', 'category_id'], ['name' => 'merchant_category']);
        $table->addIndex(['merchant_id', 'config_key'], ['name' => 'merchant_config_key']);
        $table->create();

        $this->seedMemberCombinationConfigs();
        $this->seedMergedCombinationConfigs();
    }

    /**
     * 写入后续迁移中归档到组合配置表的默认配置。
     */
    private function seedMergedCombinationConfigs(): void
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            [
                'category_id' => 0,
                'merchant_id' => 0,
                'config_name' => '直播金币充值快捷数量',
                'config_desc' => '直播间金币充值快捷档位，只维护金币数量，支付金额按兑换比例实时计算',
                'config_key' => CombinationDataTypeEnum::LIVE_COIN_RECHARGE,
                'content' => json_encode($this->buildLiveCoinRechargeSchema(), JSON_UNESCAPED_UNICODE),
                'field_keys' => json_encode(['coin_amount'], JSON_UNESCAPED_UNICODE),
                'fields' => json_encode([['key' => 'coin_amount', 'label' => '金币数量']], JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'category_id' => 0,
                'merchant_id' => 0,
                'config_name' => '7天签到奖励',
                'config_desc' => '每日签到7天循环奖励配置，支持积分和平台优惠券叠加',
                'config_key' => CombinationDataTypeEnum::ACTIVITY_SIGNIN_REWARD,
                'content' => json_encode($this->buildSigninRewardSchema(), JSON_UNESCAPED_UNICODE),
                'field_keys' => json_encode(['day', 'points', 'coupon_id'], JSON_UNESCAPED_UNICODE),
                'fields' => json_encode([
                    ['field' => 'day', 'label' => '签到天数', 'type' => 'number'],
                    ['field' => 'points', 'label' => '奖励积分', 'type' => 'number'],
                    ['field' => 'coupon_id', 'label' => '优惠券ID', 'type' => 'number'],
                ], JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'category_id' => 0,
                'merchant_id' => 0,
                'config_name' => '分销推广海报',
                'config_desc' => '用户分销推广页海报底图配置',
                'config_key' => CombinationDataTypeEnum::COMMISSION_PROMOTION_POSTER,
                'content' => json_encode($this->buildPromotionPosterSchema(), JSON_UNESCAPED_UNICODE),
                'field_keys' => json_encode(['title', 'image'], JSON_UNESCAPED_UNICODE),
                'fields' => json_encode([
                    ['key' => 'title', 'label' => '海报名称'],
                    ['key' => 'image', 'label' => '海报图片'],
                ], JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
        ];

        $pendingRows = [];
        foreach ($rows as $row) {
            if (!$this->combinationDataConfigExists((string)$row['config_key'])) {
                $pendingRows[] = $row;
            }
        }

        if ($pendingRows !== []) {
            $this->table('combination_data_config')->insert($pendingRows)->saveData();
        }
    }

    /**
     * 写入会员套餐和会员权益默认配置。
     */
    private function seedMemberCombinationConfigs(): void
    {
        $now = date('Y-m-d H:i:s');

        $rows = [
            [
                'category_id' => 0,
                'merchant_id' => 0,
                'config_name' => '会员套餐',
                'config_desc' => '会员中心套餐与支付金额配置',
                'config_key' => CombinationDataTypeEnum::MEMBER_PACKAGE,
                'content' => json_encode($this->buildPackageSchema(), JSON_UNESCAPED_UNICODE),
                'field_keys' => json_encode(['key', 'name', 'price', 'original_price', 'duration_days', 'tag'], JSON_UNESCAPED_UNICODE),
                'fields' => json_encode($this->buildPackageFields(), JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
            [
                'category_id' => 0,
                'merchant_id' => 0,
                'config_name' => '会员权益',
                'config_desc' => '会员中心权益展示配置',
                'config_key' => CombinationDataTypeEnum::MEMBER_BENEFIT,
                'content' => json_encode($this->buildBenefitSchema(), JSON_UNESCAPED_UNICODE),
                'field_keys' => json_encode(['key', 'label', 'symbol', 'desc'], JSON_UNESCAPED_UNICODE),
                'fields' => json_encode($this->buildBenefitFields(), JSON_UNESCAPED_UNICODE),
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ],
        ];

        $pendingRows = [];
        foreach ($rows as $row) {
            if (!$this->combinationDataConfigExists((string)$row['config_key'])) {
                $pendingRows[] = $row;
            }
        }

        if ($pendingRows !== []) {
            $this->table('combination_data_config')->insert($pendingRows)->saveData();
        }
    }

    /**
     * 构造表单字段。
     *
     * @param string $id 表单字段ID。
     * @param string $type 表单字段类型。
     * @param string $label 字段名称。
     * @param string $field 数据字段名。
     * @param array $props 字段属性覆盖项。
     */
    private function field(string $id, string $type, string $label, string $field, array $props = []): array
    {
        return [
            '_id' => $id,
            'type' => $type,
            'label' => $label,
            'props' => array_merge([
                'field' => $field,
                'label' => $label,
                'placeholder' => '请输入' . $label,
                'required' => false,
                'disabled' => false,
                'defaultValue' => '',
                'description' => '',
            ], $props),
        ];
    }

    /**
     * 会员套餐表单结构。
     */
    private function buildPackageSchema(): array
    {
        return [
            $this->field('package_key', 'input', '套餐标识', 'key'),
            $this->field('package_name', 'input', '套餐名称', 'name'),
            $this->field('package_price', 'number', '支付金额', 'price', ['min' => 0, 'max' => 99999, 'step' => 0.01, 'defaultValue' => 0]),
            $this->field('package_original_price', 'number', '原价', 'original_price', ['min' => 0, 'max' => 99999, 'step' => 0.01, 'defaultValue' => 0]),
            $this->field('package_duration_days', 'number', '有效天数', 'duration_days', ['min' => 1, 'max' => 99999, 'step' => 1, 'defaultValue' => 30]),
            $this->field('package_tag', 'input', '角标', 'tag'),
        ];
    }

    /**
     * 会员套餐字段摘要。
     */
    private function buildPackageFields(): array
    {
        return [
            ['key' => 'key', 'label' => '套餐标识'],
            ['key' => 'name', 'label' => '套餐名称'],
            ['key' => 'price', 'label' => '支付金额'],
            ['key' => 'original_price', 'label' => '原价'],
            ['key' => 'duration_days', 'label' => '有效天数'],
            ['key' => 'tag', 'label' => '角标'],
        ];
    }

    /**
     * 会员权益表单结构。
     */
    private function buildBenefitSchema(): array
    {
        return [
            $this->field('benefit_key', 'input', '权益标识', 'key'),
            $this->field('benefit_label', 'input', '权益名称', 'label'),
            $this->field('benefit_symbol', 'input', '权益图标', 'symbol'),
            $this->field('benefit_desc', 'textarea', '权益说明', 'desc', ['rows' => 3]),
        ];
    }

    /**
     * 会员权益字段摘要。
     */
    private function buildBenefitFields(): array
    {
        return [
            ['key' => 'key', 'label' => '权益标识'],
            ['key' => 'label', 'label' => '权益名称'],
            ['key' => 'symbol', 'label' => '权益图标'],
            ['key' => 'desc', 'label' => '权益说明'],
        ];
    }

    /**
     * 判断组合数据配置是否已存在。
     *
     * @param string $configKey 配置标识。
     */
    private function combinationDataConfigExists(string $configKey): bool
    {
        $prefix = $this->getPrefix();
        $safeKey = addslashes($configKey);
        $sql = "SELECT `id` FROM `{$prefix}combination_data_config`"
            . " WHERE `merchant_id` = 0 AND `config_key` = '{$safeKey}' LIMIT 1";

        return (bool)$this->fetchRow($sql);
    }

    /**
     * 获取数据库表前缀。
     */
    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }

    private function buildLiveCoinRechargeSchema(): array
    {
        return [[
            '_id' => 'live_coin_recharge_coin_amount',
            'type' => 'number',
            'label' => '金币数量',
            'props' => [
                'field' => 'coin_amount',
                'label' => '金币数量',
                'placeholder' => '请输入金币数量',
                'required' => true,
                'disabled' => false,
                'defaultValue' => 100,
                'description' => '支付金额由金币数量和直播金币兑换比例自动计算',
                'min' => 1,
                'max' => 99999999,
                'step' => 1,
            ],
        ]];
    }

    private function buildSigninRewardSchema(): array
    {
        return [
            $this->field('signin_reward_day', 'number', '签到天数', 'day', [
                'required' => true,
                'defaultValue' => 1,
                'min' => 1,
                'max' => 7,
                'step' => 1,
            ]),
            $this->field('signin_reward_points', 'number', '奖励积分', 'points', [
                'defaultValue' => 0,
                'min' => 0,
                'max' => 999999,
                'step' => 1,
            ]),
            $this->field('signin_reward_coupon_id', 'number', '优惠券ID', 'coupon_id', [
                'defaultValue' => 0,
                'min' => 0,
                'max' => 999999999,
                'step' => 1,
            ]),
        ];
    }

    private function buildPromotionPosterSchema(): array
    {
        return [
            $this->field('poster_title', 'input', '海报名称', 'title'),
            $this->field('poster_image', 'upload', '海报图片', 'image', [
                'required' => true,
                'accept' => 'image/*',
                'limit' => 1,
                'buttonText' => '上传海报图片',
                'description' => '建议上传竖版推广海报底图，前端会在右下角合成二维码。',
            ]),
        ];
    }
}
