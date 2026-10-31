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

use app\common\migration\SeedSqlTrait;
use app\common\enum\ConfigKeyEnum;
use app\common\enum\OrderConfigKeyEnum;
use think\migration\Migrator;

/**
 * 创建配置模板表，并写入每日签到所需的默认配置模板。
 *
 * 签到配置放在本迁移中，是为了让新环境创建配置模板表后即可拥有：
 * 每日任务开关和每日任务积分配置。
 */
class CreateConfigTemplate extends Migrator
{
    use SeedSqlTrait;

    private const MEMBER_CONFIG_TEMPLATE_KEY = 'member_config';
    private const LIVE_COIN_CONFIG_TEMPLATE_KEY = 'live_coin_config';
    private const CS_AI_CONFIG_TEMPLATE_KEY = 'cs_ai_config';
    private const ORDER_POINTS_CONFIG_TEMPLATE_KEY = 'order_points_config';
    private const MERCHANT_FUND_CONFIG_TEMPLATE_KEY = 'merchant_fund_config';
    private const DISTRIBUTION_CONFIG_TEMPLATE_KEY = 'distribution_config';
    private const GIFT_POINTS_CONFIG_TEMPLATE_KEY = 'order_gift_points_config';
    private const COMMISSION_WITHDRAW_CONFIG_TEMPLATE_KEY = 'commission_withdraw_config';
    private const DEFAULT_CS_AI_FALLBACK_REPLY = '暂未检索到相关知识库内容，建议联系人工客服协助处理。';

    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('config_template', ['comment' => '配置模板', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB', 'row_format' => 'DYNAMIC']);
        $table->addColumn('cate_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分类id']);
        $table->addColumn('admin_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '管理员id']);
        $table->addColumn('menu_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '菜单id']);
        $table->addColumn('admin_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '管理员名称']);
        $table->addColumn('config_name', 'string', ['limit' => 50, 'null' => true, 'default' => '', 'comment' => '配置名称']);
        $table->addColumn('config_desc', 'string', ['limit' => 255, 'null' => true, 'default' => '', 'comment' => '配置信息']);
        $table->addColumn('config_key', 'string', ['limit' => 50, 'default' => '', 'comment' => '标识英文']);
        $table->addColumn('content', 'text', ['comment' => '模板内容']);
        $table->addColumn('field_keys', 'text', ['comment' => '字段键']);
        $table->addColumn('fields', 'text', ['null'=> true, 'default' => null,'comment' => '预定义字段列表（JSON）','after'   => 'field_keys']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('admin_id');
        $table->create();

        $this->seedLegacyConfigTemplates();
        $this->seedSigninConfig();
        $this->seedMemberConfigTemplate();
        $this->seedLiveCoinConfigTemplate();
        $this->seedCsAiConfigTemplate();
        $this->seedOrderPointsConfigTemplate();
        $this->seedMerchantFundConfigTemplate();
        $this->seedDistributionConfigTemplate();
        $this->seedGiftPointsConfigTemplate();
        $this->seedCommissionWithdrawConfigTemplate();
    }

    /**
     * 写入历史配置模板。
     *
     * config_template 原始数据从 config_seed.sql 拆分，避免在 config 表迁移阶段访问尚未创建的模板表。
     */
    private function seedLegacyConfigTemplates(): void
    {
        $this->executeSeedSqlFile(
            __DIR__ . '/config_template_seed.sql',
            $this->getPrefix()
        );
    }

    /**
     * 获取数据库表前缀。
     */
    public function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }

    /**
     * 写入每日签到默认配置和配置模板。
     *
     * config 表保存真实配置值，config_template 表保存后台配置页结构。
     * 所有写入都使用 INSERT ... SELECT ... WHERE NOT EXISTS，避免重复执行时覆盖线上已调整的配置。
     */
    private function seedSigninConfig(): void
    {
        $prefix = $this->getPrefix();
        $now = date('Y-m-d H:i:s');

        $configs = [
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_ENABLED,
                'title' => '关注店铺任务开关',
                'into' => '控制每日任务中关注一个店铺是否展示和生效',
                'value' => json_encode('1', JSON_UNESCAPED_UNICODE),
                'sort' => 282,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_POINTS,
                'title' => '关注店铺任务积分',
                'into' => '完成关注一个店铺任务可获得的积分',
                'value' => json_encode('5', JSON_UNESCAPED_UNICODE),
                'sort' => 283,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_ENABLED,
                'title' => '下单商品任务开关',
                'into' => '控制每日任务中下单一个商品是否展示和生效',
                'value' => json_encode('1', JSON_UNESCAPED_UNICODE),
                'sort' => 284,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_POINTS,
                'title' => '下单商品任务积分',
                'into' => '完成下单一个商品任务可获得的积分',
                'value' => json_encode('10', JSON_UNESCAPED_UNICODE),
                'sort' => 285,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_ENABLED,
                'title' => '加入购物车任务开关',
                'into' => '控制每日任务中加入购物车是否展示和生效',
                'value' => json_encode('1', JSON_UNESCAPED_UNICODE),
                'sort' => 286,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_POINTS,
                'title' => '加入购物车任务积分',
                'into' => '完成加入购物车任务可获得的积分',
                'value' => json_encode('5', JSON_UNESCAPED_UNICODE),
                'sort' => 287,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_ENABLED,
                'title' => '浏览专题任务开关',
                'into' => '控制每日任务中浏览一个专题是否展示和生效',
                'value' => json_encode('1', JSON_UNESCAPED_UNICODE),
                'sort' => 288,
            ],
            [
                'key' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_POINTS,
                'title' => '浏览专题任务积分',
                'into' => '完成浏览一个专题任务可获得的积分',
                'value' => json_encode('5', JSON_UNESCAPED_UNICODE),
                'sort' => 289,
            ],
        ];

        foreach ($configs as $config) {
            $this->insertConfigIfMissing($prefix, $config, $now);
        }

        $this->insertSigninConfigTemplate($prefix, $now);
    }

    /**
     * 插入单个配置项，不覆盖已有配置。
     *
     * @param string $prefix 数据库表前缀。
     * @param array $config 配置 key、标题、说明、默认值和排序。
     * @param string $now 当前时间。
     */
    private function insertConfigIfMissing(string $prefix, array $config, string $now): void
    {
        $key = addslashes((string)$config['key']);
        $title = addslashes((string)$config['title']);
        $into = addslashes((string)$config['into']);
        $value = addslashes((string)$config['value']);
        $sort = (int)$config['sort'];

        $this->execute(
            "INSERT INTO `{$prefix}config` "
            . "(`key_name`, `cate_id`, `value`, `title`, `into`, `sort`, `status`, `create_time`, `update_time`) "
            . "SELECT '{$key}', 0, '{$value}', '{$title}', '{$into}', {$sort}, 0, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config` WHERE `key_name` = '{$key}' LIMIT 1)"
        );
    }

    /**
     * 插入每日签到配置模板，不覆盖已有模板。
     *
     * 模板用于后台统一编辑4个每日任务的开关和积分。
     */
    private function insertSigninConfigTemplate(string $prefix, string $now): void
    {
        $templateKey = 'activity_signin_config';
        $fieldKeys = [
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_ENABLED,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_POINTS,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_ENABLED,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_POINTS,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_ENABLED,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_POINTS,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_ENABLED,
            ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_POINTS,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_ENABLED, 'label' => '关注店铺任务开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_POINTS, 'label' => '关注店铺任务积分', 'type' => 'number'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_ENABLED, 'label' => '下单商品任务开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_POINTS, 'label' => '下单商品任务积分', 'type' => 'number'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_ENABLED, 'label' => '加入购物车任务开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_POINTS, 'label' => '加入购物车任务积分', 'type' => 'number'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_ENABLED, 'label' => '浏览专题任务开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_POINTS, 'label' => '浏览专题任务积分', 'type' => 'number'],
        ];

        $content = addslashes(json_encode($this->buildSigninConfigContent(), JSON_UNESCAPED_UNICODE));
        $fieldKeysJson = addslashes(json_encode($fieldKeys, JSON_UNESCAPED_UNICODE));
        $fieldsJson = addslashes(json_encode($fields, JSON_UNESCAPED_UNICODE));
        $safeTemplateKey = addslashes($templateKey);

        $this->execute(
            "INSERT INTO `{$prefix}config_template` "
            . "(`cate_id`, `admin_id`, `menu_id`, `admin_name`, `config_name`, `config_desc`, `config_key`, "
            . "`content`, `field_keys`, `fields`, `status`, `create_time`, `update_time`) "
            . "SELECT 0, 0, 0, '', '签到配置', '配置每日签到任务奖励', '{$safeTemplateKey}', "
            . "'{$content}', '{$fieldKeysJson}', '{$fieldsJson}', 1, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config_template` WHERE `config_key` = '{$safeTemplateKey}' LIMIT 1)"
        );
    }

    /**
     * 写入会员配置模板。
     */
    private function seedMemberConfigTemplate(): void
    {
        $now = date('Y-m-d H:i:s');
        $fieldKeys = [
            ConfigKeyEnum::MEMBER_ENABLED,
            ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER,
            ConfigKeyEnum::MEMBER_DISCOUNT_RATE,
            ConfigKeyEnum::MEMBER_POINTS_BANNER_ENABLED,
            ConfigKeyEnum::MEMBER_POINTS_BANNER_TITLE,
            ConfigKeyEnum::MEMBER_POINTS_BANNER_DESC,
            ConfigKeyEnum::MEMBER_POINTS_BANNER_IMAGE,
        ];

        $this->table('config_template')->insert([[
            'cate_id' => 0,
            'admin_id' => 0,
            'menu_id' => 0,
            'admin_name' => '',
            'config_name' => '会员配置',
            'config_desc' => '配置会员开关、积分、折扣和积分页广告',
            'config_key' => self::MEMBER_CONFIG_TEMPLATE_KEY,
            'content' => json_encode($this->buildMemberConfigContent(), JSON_UNESCAPED_UNICODE),
            'field_keys' => json_encode($fieldKeys, JSON_UNESCAPED_UNICODE),
            'fields' => json_encode($this->buildMemberConfigFields(), JSON_UNESCAPED_UNICODE),
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    /**
     * 写入直播金币配置模板。
     */
    private function seedLiveCoinConfigTemplate(): void
    {
        $now = date('Y-m-d H:i:s');
        $content = json_encode([$this->field(
            'live_coin_exchange_rate',
            'number',
            '直播金币兑换比例',
            ConfigKeyEnum::LIVE_COIN_EXCHANGE_RATE,
            [
                'min' => 1,
                'max' => 999999,
                'step' => 1,
                'defaultValue' => 10,
                'description' => '1 元可兑换金币数',
            ]
        )], JSON_UNESCAPED_UNICODE);
        $fieldKeys = json_encode([ConfigKeyEnum::LIVE_COIN_EXCHANGE_RATE], JSON_UNESCAPED_UNICODE);
        $fields = json_encode([[
            'field' => ConfigKeyEnum::LIVE_COIN_EXCHANGE_RATE,
            'label' => '直播金币兑换比例',
            'type' => 'number',
        ]], JSON_UNESCAPED_UNICODE);

        $this->table('config_template')->insert([[
            'cate_id' => 0,
            'admin_id' => 0,
            'menu_id' => 0,
            'admin_name' => '',
            'config_name' => '直播金币配置',
            'config_desc' => '配置直播金币充值兑换比例',
            'config_key' => self::LIVE_COIN_CONFIG_TEMPLATE_KEY,
            'content' => $content,
            'field_keys' => $fieldKeys,
            'fields' => $fields,
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    /**
     * 写入 AI 客服配置模板。
     */
    private function seedCsAiConfigTemplate(): void
    {
        $now = date('Y-m-d H:i:s');
        $content = json_encode([$this->field(
            'cs_ai_fallback_reply',
            'textarea',
            'AI客服兜底回复',
            ConfigKeyEnum::CS_AI_FALLBACK_REPLY,
            [
                'defaultValue' => self::DEFAULT_CS_AI_FALLBACK_REPLY,
                'description' => '知识库未命中或模型返回为空时给用户展示的默认回复',
                'placeholder' => '请输入AI客服兜底回复',
            ]
        )], JSON_UNESCAPED_UNICODE);
        $fieldKeys = json_encode([ConfigKeyEnum::CS_AI_FALLBACK_REPLY], JSON_UNESCAPED_UNICODE);
        $fields = json_encode([[
            'field' => ConfigKeyEnum::CS_AI_FALLBACK_REPLY,
            'label' => 'AI客服兜底回复',
            'type' => 'textarea',
        ]], JSON_UNESCAPED_UNICODE);

        $this->table('config_template')->insert([[
            'cate_id' => 0,
            'admin_id' => 0,
            'menu_id' => 0,
            'admin_name' => '',
            'config_name' => 'AI客服配置',
            'config_desc' => '配置AI客服知识库未命中时的兜底回复',
            'config_key' => self::CS_AI_CONFIG_TEMPLATE_KEY,
            'content' => $content,
            'field_keys' => $fieldKeys,
            'fields' => $fields,
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]])->saveData();
    }

    /**
     * 构建后台配置表单。
     *
     * 返回值会被 JSON 化写入 config_template.content，后台根据该结构渲染配置表单。
     */
    private function buildSigninConfigContent(): array
    {
        return [
            $this->field('activity_signin_task_follow_merchant_enabled', 'radio', '关注店铺任务开关', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不展示关注一个店铺任务',
            ]),
            $this->field('activity_signin_task_follow_merchant_points', 'number', '关注店铺任务积分', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_FOLLOW_MERCHANT_POINTS, [
                'min' => 0,
                'max' => 999999,
                'step' => 1,
                'defaultValue' => 5,
                'description' => '完成关注任意一个商家店铺后可领取的积分',
            ]),
            $this->field('activity_signin_task_order_goods_enabled', 'radio', '下单商品任务开关', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不展示下单一个商品任务',
            ]),
            $this->field('activity_signin_task_order_goods_points', 'number', '下单商品任务积分', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ORDER_GOODS_POINTS, [
                'min' => 0,
                'max' => 999999,
                'step' => 1,
                'defaultValue' => 10,
                'description' => '创建一笔商品订单后可领取的积分',
            ]),
            $this->field('activity_signin_task_add_cart_enabled', 'radio', '加入购物车任务开关', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不展示加入购物车任务',
            ]),
            $this->field('activity_signin_task_add_cart_points', 'number', '加入购物车任务积分', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_ADD_CART_POINTS, [
                'min' => 0,
                'max' => 999999,
                'step' => 1,
                'defaultValue' => 5,
                'description' => '添加任意商品到购物车后可领取的积分',
            ]),
            $this->field('activity_signin_task_browse_special_enabled', 'radio', '浏览专题任务开关', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不展示浏览一个专题任务',
            ]),
            $this->field('activity_signin_task_browse_special_points', 'number', '浏览专题任务积分', ConfigKeyEnum::ACTIVITY_SIGNIN_TASK_BROWSE_SPECIAL_POINTS, [
                'min' => 0,
                'max' => 999999,
                'step' => 1,
                'defaultValue' => 5,
                'description' => '进入任意专题详情后可领取的积分',
            ]),
        ];
    }

    /**
     * 构建会员配置表单。
     */
    private function buildMemberConfigContent(): array
    {
        return [
            $this->field('member_enabled', 'radio', 'VIP会员开关', ConfigKeyEnum::MEMBER_ENABLED, [
                'options' => $this->switchOptions(),
                'defaultValue' => '1',
                'description' => '关闭后移动端不展示会员入口和权益，不允许购买会员。',
            ]),
            $this->field('member_points_multiplier', 'number', '会员积分倍数', ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER, [
                'min' => 1,
                'max' => 99,
                'step' => 0.1,
                'defaultValue' => 2,
            ]),
            $this->field('member_discount_rate', 'number', '会员折扣率', ConfigKeyEnum::MEMBER_DISCOUNT_RATE, [
                'min' => 0.01,
                'max' => 1,
                'step' => 0.01,
                'defaultValue' => 1,
                'description' => '0.80 表示 8 折，1.00 表示原价。',
            ]),
            $this->field('member_points_banner_enabled', 'radio', '积分广告开关', ConfigKeyEnum::MEMBER_POINTS_BANNER_ENABLED, [
                'options' => $this->switchOptions(),
                'defaultValue' => '1',
            ]),
            $this->field('member_points_banner_title', 'input', '积分广告标题', ConfigKeyEnum::MEMBER_POINTS_BANNER_TITLE, [
                'maxlength' => 60,
                'defaultValue' => '黑卡会员多倍积分',
            ]),
            $this->field('member_points_banner_desc', 'textarea', '积分广告简介', ConfigKeyEnum::MEMBER_POINTS_BANNER_DESC, [
                'rows' => 3,
                'defaultValue' => '立即升级，专享购物多倍积分',
            ]),
            $this->field('member_points_banner_image', 'input', '积分广告图片', ConfigKeyEnum::MEMBER_POINTS_BANNER_IMAGE, [
                'maxlength' => 255,
                'defaultValue' => '/static/images/coin.png',
            ]),
        ];
    }

    /**
     * 会员配置字段摘要。
     */
    private function buildMemberConfigFields(): array
    {
        return [
            ['field' => ConfigKeyEnum::MEMBER_ENABLED, 'label' => 'VIP会员开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER, 'label' => '会员积分倍数', 'type' => 'number'],
            ['field' => ConfigKeyEnum::MEMBER_DISCOUNT_RATE, 'label' => '会员折扣率', 'type' => 'number'],
            ['field' => ConfigKeyEnum::MEMBER_POINTS_BANNER_ENABLED, 'label' => '积分广告开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::MEMBER_POINTS_BANNER_TITLE, 'label' => '积分广告标题', 'type' => 'input'],
            ['field' => ConfigKeyEnum::MEMBER_POINTS_BANNER_DESC, 'label' => '积分广告简介', 'type' => 'textarea'],
            ['field' => ConfigKeyEnum::MEMBER_POINTS_BANNER_IMAGE, 'label' => '积分广告图片', 'type' => 'input'],
        ];
    }

    /**
     * 写入合并自后续迁移的配置模板。
     */
    private function seedOrderPointsConfigTemplate(): void
    {
        $fieldKeys = [
            ConfigKeyEnum::POINTS_ENABLED,
            ConfigKeyEnum::POINTS_ACTIVITY_SIGNIN_ENABLED,
            ConfigKeyEnum::ORDER_POINTS_RATIO,
            ConfigKeyEnum::ORDER_POINTS_DEDUCT_LIMIT,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::POINTS_ENABLED, 'label' => '积分功能总开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::POINTS_ACTIVITY_SIGNIN_ENABLED, 'label' => '积分签到功能开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::ORDER_POINTS_RATIO, 'label' => '积分抵扣比例', 'type' => 'number'],
            ['field' => ConfigKeyEnum::ORDER_POINTS_DEDUCT_LIMIT, 'label' => '抵扣上限积分', 'type' => 'number'],
        ];
        $content = [
            $this->field('points_switch', 'radio', '积分功能总开关', ConfigKeyEnum::POINTS_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不可使用积分抵扣，签到也不可发放积分。',
            ]),
            $this->field('activity_signin_switch', 'radio', '积分签到功能开关', ConfigKeyEnum::POINTS_ACTIVITY_SIGNIN_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不可签到和领取签到任务积分。',
            ]),
            $this->field('order_points_ratio', 'number', '积分抵扣比例', ConfigKeyEnum::ORDER_POINTS_RATIO, [
                'min' => 1,
                'max' => 999999,
                'step' => 1,
                'defaultValue' => 100,
                'description' => '每抵扣 1 元需要消耗的积分数，积分只按整数消耗。',
            ]),
            $this->field('order_points_deduct_limit', 'number', '抵扣上限积分', ConfigKeyEnum::ORDER_POINTS_DEDUCT_LIMIT, [
                'min' => 0,
                'max' => 999999999,
                'step' => 1,
                'defaultValue' => 0,
                'description' => '单笔订单最多可使用的积分数量，0 表示不限制。',
            ]),
        ];

        $this->insertMergedTemplate(
            self::ORDER_POINTS_CONFIG_TEMPLATE_KEY,
            '订单积分配置',
            '配置积分开关、下单积分抵扣比例和单笔抵扣上限',
            $content,
            $fieldKeys,
            $fields
        );
    }

    /**
     * 写入商户资金冻结配置模板。
     */
    private function seedMerchantFundConfigTemplate(): void
    {
        $key = ConfigKeyEnum::MERCHANT_FUND_FREEZE_DAYS;
        $content = [
            $this->field('merchant_fund_freeze_days', 'number', '商户资金冻结天数', $key, [
                'min' => 0,
                'max' => 3650,
                'step' => 1,
                'defaultValue' => 0,
                'description' => '订单完成后商户收入继续冻结的天数，0 表示立即释放',
            ]),
        ];
        $this->insertMergedTemplate(
            self::MERCHANT_FUND_CONFIG_TEMPLATE_KEY,
            '商户资金配置',
            '配置商户订单收入冻结与释放规则',
            $content,
            [$key],
            [['field' => $key, 'label' => '商户资金冻结天数', 'type' => 'number']]
        );
    }

    /**
     * 写入分销配置模板。
     */
    private function seedDistributionConfigTemplate(): void
    {
        $fieldKeys = [
            ConfigKeyEnum::DISTRIBUTION_ENABLED,
            ConfigKeyEnum::DISTRIBUTION_LEVEL,
            ConfigKeyEnum::DISTRIBUTION_MODE,
            ConfigKeyEnum::DISTRIBUTION_FIRST_RATE,
            ConfigKeyEnum::DISTRIBUTION_SECOND_RATE,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::DISTRIBUTION_ENABLED, 'label' => '分销总开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::DISTRIBUTION_LEVEL, 'label' => '分销等级', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::DISTRIBUTION_MODE, 'label' => '分销模式', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::DISTRIBUTION_FIRST_RATE, 'label' => '一级返佣比例', 'type' => 'number'],
            ['field' => ConfigKeyEnum::DISTRIBUTION_SECOND_RATE, 'label' => '二级返佣比例', 'type' => 'number'],
        ];
        $content = [
            $this->field('distribution_enabled', 'radio', '分销总开关', ConfigKeyEnum::DISTRIBUTION_ENABLED, [
                'defaultValue' => '0',
                'options' => $this->switchOptions(),
            ]),
            $this->field('distribution_level', 'radio', '分销等级', ConfigKeyEnum::DISTRIBUTION_LEVEL, [
                'defaultValue' => '2',
                'options' => [
                    ['label' => '一级', 'value' => '1'],
                    ['label' => '二级', 'value' => '2'],
                ],
            ]),
            $this->field('distribution_mode', 'radio', '分销模式', ConfigKeyEnum::DISTRIBUTION_MODE, [
                'defaultValue' => 'specified',
                'options' => [
                    ['label' => '人人分销', 'value' => 'all'],
                    ['label' => '指定分销', 'value' => 'specified'],
                ],
            ]),
            $this->field('distribution_first_rate', 'number', '一级返佣比例', ConfigKeyEnum::DISTRIBUTION_FIRST_RATE, [
                'min' => 0,
                'max' => 100,
                'step' => 0.01,
                'defaultValue' => 0,
                'description' => '百分比口径，5 表示 5%。',
            ]),
            $this->field('distribution_second_rate', 'number', '二级返佣比例', ConfigKeyEnum::DISTRIBUTION_SECOND_RATE, [
                'min' => 0,
                'max' => 100,
                'step' => 0.01,
                'defaultValue' => 0,
                'description' => '百分比口径，5 表示 5%。分销等级为一级时不生效。',
            ]),
        ];

        $this->insertMergedTemplate(
            self::DISTRIBUTION_CONFIG_TEMPLATE_KEY,
            '分销配置',
            '配置分销开关、等级、模式和全局返佣比例',
            $content,
            $fieldKeys,
            $fields
        );
    }

    /**
     * 写入订单赠送积分配置模板。
     */
    private function seedGiftPointsConfigTemplate(): void
    {
        $key = OrderConfigKeyEnum::GIFT_POINTS_FREEZE_DAYS;
        $content = [
            $this->field('gift_points_freeze_days', 'number', '赠送积分冻结天数', $key, [
                'min' => 0,
                'max' => 365,
                'step' => 1,
                'defaultValue' => 7,
                'description' => '内容/专题订单支付后赠送积分冻结天数，到期后自动释放并完成订单。',
            ]),
        ];
        $this->insertMergedTemplate(
            self::GIFT_POINTS_CONFIG_TEMPLATE_KEY,
            '订单赠送积分配置',
            '配置内容/专题订单赠送积分冻结释放周期',
            $content,
            [$key],
            [['field' => $key, 'label' => '赠送积分冻结天数', 'type' => 'number']]
        );
    }

    /**
     * 写入佣金提现配置模板。
     */
    private function seedCommissionWithdrawConfigTemplate(): void
    {
        $fieldKeys = [
            ConfigKeyEnum::COMMISSION_WITHDRAW_ENABLED,
            ConfigKeyEnum::COMMISSION_WITHDRAW_FEE_RATE,
            ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_FEE,
            ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_AMOUNT,
            ConfigKeyEnum::COMMISSION_WITHDRAW_MAX_AMOUNT,
            ConfigKeyEnum::COMMISSION_WITHDRAW_ARRIVAL_TIME,
            ConfigKeyEnum::COMMISSION_WITHDRAW_NOTES,
        ];
        $fields = [
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_ENABLED, 'label' => '提现开关', 'type' => 'radio'],
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_FEE_RATE, 'label' => '服务费率', 'type' => 'number'],
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_FEE, 'label' => '最低手续费', 'type' => 'number'],
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_AMOUNT, 'label' => '单笔最低金额', 'type' => 'number'],
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_MAX_AMOUNT, 'label' => '单笔最高金额', 'type' => 'number'],
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_ARRIVAL_TIME, 'label' => '预计到账时间', 'type' => 'input'],
            ['field' => ConfigKeyEnum::COMMISSION_WITHDRAW_NOTES, 'label' => '提现须知', 'type' => 'textarea'],
        ];
        $content = [
            $this->field('commission_withdraw_enabled', 'radio', '提现开关', ConfigKeyEnum::COMMISSION_WITHDRAW_ENABLED, [
                'defaultValue' => '1',
                'options' => $this->switchOptions(),
                'description' => '关闭后移动端不可提交佣金提现申请。',
            ]),
            $this->field('commission_withdraw_fee_rate', 'number', '服务费率', ConfigKeyEnum::COMMISSION_WITHDRAW_FEE_RATE, [
                'min' => 0,
                'max' => 1,
                'step' => 0.0001,
                'defaultValue' => 0.001,
                'description' => '小数口径，如 0.001 表示 0.1%。',
            ]),
            $this->field('commission_withdraw_min_fee', 'number', '最低手续费', ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_FEE, [
                'min' => 0,
                'max' => 99999,
                'step' => 0.01,
                'defaultValue' => 0.1,
                'description' => '手续费低于该金额时按该金额收取，单位元。',
            ]),
            $this->field('commission_withdraw_min_amount', 'number', '单笔最低金额', ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_AMOUNT, [
                'min' => 0,
                'max' => 999999,
                'step' => 0.01,
                'defaultValue' => 10,
                'description' => '用户单笔佣金提现最低金额，单位元。',
            ]),
            $this->field('commission_withdraw_max_amount', 'number', '单笔最高金额', ConfigKeyEnum::COMMISSION_WITHDRAW_MAX_AMOUNT, [
                'min' => 0,
                'max' => 999999999,
                'step' => 0.01,
                'defaultValue' => 50000,
                'description' => '用户单笔佣金提现最高金额，单位元。',
            ]),
            $this->field('commission_withdraw_arrival_time', 'input', '预计到账时间', ConfigKeyEnum::COMMISSION_WITHDRAW_ARRIVAL_TIME, [
                'defaultValue' => '预计 2 小时内到账',
                'description' => '移动端佣金提现页展示的预计到账时间文案。',
            ]),
            $this->field('commission_withdraw_notes', 'textarea', '提现须知', ConfigKeyEnum::COMMISSION_WITHDRAW_NOTES, [
                'rows' => 4,
                'defaultValue' => "单笔最低提现 10.00 元，最高提现 50,000.00 元。\n手续费按提现金额的 0.1% 收取，最低 0.1 元。",
                'description' => '换行分隔多条，移动端会拆成列表展示。',
            ]),
        ];

        $this->insertMergedTemplate(
            self::COMMISSION_WITHDRAW_CONFIG_TEMPLATE_KEY,
            '佣金提现配置',
            '配置佣金提现开关、手续费、提现限额和到账文案',
            $content,
            $fieldKeys,
            $fields
        );
    }

    /**
     * 插入模板，不覆盖已有配置。
     */
    private function insertMergedTemplate(
        string $templateKey,
        string $configName,
        string $configDesc,
        array $content,
        array $fieldKeys,
        array $fields
    ): void {
        $prefix = $this->getPrefix();
        $now = date('Y-m-d H:i:s');
        $safeTemplateKey = addslashes($templateKey);
        $safeConfigName = addslashes($configName);
        $safeConfigDesc = addslashes($configDesc);
        $contentJson = addslashes(json_encode($content, JSON_UNESCAPED_UNICODE));
        $fieldKeysJson = addslashes(json_encode($fieldKeys, JSON_UNESCAPED_UNICODE));
        $fieldsJson = addslashes(json_encode($fields, JSON_UNESCAPED_UNICODE));

        $this->execute(
            "INSERT INTO `{$prefix}config_template` "
            . "(`cate_id`, `admin_id`, `menu_id`, `admin_name`, `config_name`, `config_desc`, `config_key`, "
            . "`content`, `field_keys`, `fields`, `status`, `create_time`, `update_time`) "
            . "SELECT 0, 0, 0, '', '{$safeConfigName}', '{$safeConfigDesc}', '{$safeTemplateKey}', "
            . "'{$contentJson}', '{$fieldKeysJson}', '{$fieldsJson}', 1, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config_template` WHERE `config_key` = '{$safeTemplateKey}' LIMIT 1)"
        );
    }

    /**
     * 构造配置字段。
     *
     * @param string $id 前端表单组件唯一ID。
     * @param string $type 表单组件类型。
     * @param string $label 后台展示名称。
     * @param string $field 绑定的 config.key_name。
     * @param array $props 组件属性覆盖项。
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
     * 开关选项。
     */
    private function switchOptions(): array
    {
        return [
            ['label' => '开启', 'value' => '1'],
            ['label' => '关闭', 'value' => '0'],
        ];
    }
}
