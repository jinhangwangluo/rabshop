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

use app\common\enum\ConfigKeyEnum;
use app\common\enum\OrderConfigKeyEnum;
use think\migration\Migrator;

/**
 * 配置表
 * Class Config
 */
class CreateConfig extends Migrator
{
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
        $table = $this->table('config', ['comment' => '配置表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('key_name', 'string', ['limit' => 255, 'default' => '', 'comment' => '字段名称']);
        $table->addColumn('cate_id', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '配置分类id']);
        $table->addColumn('value', 'string', ['limit' => 5000, 'default' => '', 'comment' => '默认值']);
        $table->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'comment' => '配置名称']);
        $table->addColumn('into', 'string', ['limit' => 255, 'default' => '', 'comment' => '配置简介']);
        $table->addColumn('sort', 'integer', ['limit' => 10, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('status', 'boolean', ['limit' => 255, 'default' => 0, 'comment' => '是否隐藏']);
        $table->addIndex(['key_name', 'status'], ['name' => 'key_status']);
        $table->addIndex('key_name', ['name' => 'key_name']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->create();

        $this->seedDefaultConfigs();
        $this->seedMergedConfigs();
        $this->seedUpgradeConfigs();
    }

    /**
     * 写入单表配置默认值。
     */
    private function seedDefaultConfigs(): void
    {
        $now = date('Y-m-d H:i:s');
        $configs = [
            [
                'key_name' => 'live_ali_access_key',
                'title' => '阿里云直播 AccessKeyId',
                'into' => '阿里云 RAM 用户 AccessKey ID，需开通直播服务权限',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 10,
                'status' => 0,
            ],
            [
                'key_name' => 'live_ali_access_secret',
                'title' => '阿里云直播 AccessKeySecret',
                'into' => '阿里云 RAM 用户 AccessKey Secret，请勿泄露',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 11,
                'status' => 0,
            ],
            [
                'key_name' => 'live_ali_app_name',
                'title' => '阿里云直播应用名',
                'into' => '阿里云直播控制台中创建的应用名，如 live',
                'value' => json_encode('live', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 12,
                'status' => 0,
            ],
            [
                'key_name' => 'live_ali_push_domain',
                'title' => '阿里云推流域名',
                'into' => '阿里云直播控制台配置的推流域名，如 push.example.com',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 13,
                'status' => 0,
            ],
            [
                'key_name' => 'live_ali_pull_domain',
                'title' => '阿里云拉流域名',
                'into' => '阿里云直播控制台配置的播放域名，如 pull.example.com',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 14,
                'status' => 0,
            ],
            [
                'key_name' => 'live_ali_expire_seconds',
                'title' => '阿里云推流鉴权过期秒数',
                'into' => '推流地址签名有效期（秒），建议 7200',
                'value' => json_encode('7200', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 15,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_access_key',
                'title' => '七牛云直播 AccessKey',
                'into' => '七牛云账号的 AccessKey，在密钥管理中获取',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 20,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_secret_key',
                'title' => '七牛云直播 SecretKey',
                'into' => '七牛云账号的 SecretKey，请勿泄露',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 21,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_hub',
                'title' => '七牛云直播 Hub 名称',
                'into' => '七牛云直播控制台中创建的 Hub 名称',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 22,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_push_domain',
                'title' => '七牛云推流域名',
                'into' => '七牛云 Hub 绑定的推流域名，如 push.example.com',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 23,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_pull_domain',
                'title' => '七牛云拉流域名',
                'into' => '七牛云 Hub 绑定的播放域名，如 pull.example.com',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 24,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_expire_seconds',
                'title' => '七牛云推流鉴权过期秒数',
                'into' => '推流地址签名有效期（秒），建议 7200',
                'value' => json_encode('7200', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 25,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_play_auth_key',
                'title' => '七牛云直播播放鉴权 Key（时间戳防盗链）',
                'into' => '用于对七牛 Pili 播放地址生成 sign/t 的时间戳防盗链主密钥',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 26,
                'status' => 0,
            ],
            [
                'key_name' => 'live_default_provider',
                'title' => '默认直播云服务商',
                'into' => '当前生效的主要云服务商，可选值：aliyun / qiniu',
                'value' => json_encode('aliyun', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 30,
                'status' => 0,
            ],
            [
                'key_name' => 'live_ali_callback_token',
                'title' => '阿里云直播回调 Token',
                'into' => '32位鉴权Token，系统将自动拼接至回调地址末尾（?token=xxx），请填写随机32位字符串',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 33,
                'status' => 0,
            ],
            [
                'key_name' => 'live_qiniu_callback_token',
                'title' => '七牛云直播回调 Token',
                'into' => '32位鉴权Token，系统将自动拼接至回调地址末尾（?token=xxx），请填写随机32位字符串',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 34,
                'status' => 0,
            ],
            [
                'key_name' => 'order_unpaid_cancel_minutes',
                'title' => '未支付订单自动取消分钟数',
                'into' => '订单创建后超过该分钟数仍未支付时自动取消，默认 30 分钟',
                'value' => json_encode('30', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 100,
                'status' => 0,
            ],
            [
                'key_name' => 'order_unpaid_cancel_scan_seconds',
                'title' => '未支付订单自动取消扫描间隔秒数',
                'into' => 'Swoole 定时任务扫描未支付订单的间隔秒数，默认 60 秒',
                'value' => json_encode('60', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 101,
                'status' => 0,
            ],
            [
                'key_name' => 'order_unpaid_cancel_batch_limit',
                'title' => '未支付订单自动取消每轮数量',
                'into' => '每轮最多自动取消的超时未支付订单数量，默认 50 条',
                'value' => json_encode('50', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 102,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::COMMISSION_FREEZE_DAYS,
                'title' => '佣金冻结天数',
                'into' => '佣金满足发放条件后继续冻结的天数，0 表示立即释放',
                'value' => json_encode('0', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 103,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::COMMISSION_PROMOTION_AD_IMAGE,
                'title' => '推广广告图',
                'into' => '佣金中心收益明细上方展示的推广广告图',
                'value' => json_encode('', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 104,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_ENABLED,
                'title' => 'VIP会员开关',
                'into' => '控制移动端会员展示、购买、折扣和积分权益是否生效',
                'value' => json_encode('1', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 199,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_POINTS_MULTIPLIER,
                'title' => '会员积分倍数',
                'into' => '会员购买内容时积分翻倍倍数，默认 2',
                'value' => json_encode('2', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 200,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_DISCOUNT_RATE,
                'title' => '会员折扣率',
                'into' => '会员购买内容折扣率，如 0.80 表示 8 折，默认 1.00 表示原价',
                'value' => json_encode('1.00', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 201,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_POINTS_BANNER_ENABLED,
                'title' => '会员积分广告开关',
                'into' => '控制积分页会员广告是否展示',
                'value' => json_encode('1', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 202,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_POINTS_BANNER_TITLE,
                'title' => '会员积分广告标题',
                'into' => '积分页会员广告标题',
                'value' => json_encode('黑卡会员多倍积分', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 203,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_POINTS_BANNER_DESC,
                'title' => '会员积分广告简介',
                'into' => '积分页会员广告简介',
                'value' => json_encode('立即升级，专享购物多倍积分', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 204,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::MEMBER_POINTS_BANNER_IMAGE,
                'title' => '会员积分广告图片',
                'into' => '积分页会员广告图片路径',
                'value' => json_encode('/static/images/coin.png', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 205,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::LIVE_COIN_EXCHANGE_RATE,
                'title' => '直播金币兑换比例',
                'into' => '1 元可兑换金币数',
                'value' => json_encode('10', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 260,
                'status' => 0,
            ],
            [
                'key_name' => ConfigKeyEnum::CS_AI_FALLBACK_REPLY,
                'title' => 'AI客服兜底回复',
                'into' => '知识库未命中或模型返回为空时给用户展示的默认回复',
                'value' => json_encode('暂未检索到相关知识库内容，建议联系人工客服协助处理。', JSON_UNESCAPED_UNICODE),
                'cate_id' => 0,
                'sort' => 270,
                'status' => 0,
            ],
        ];

        foreach ($configs as &$config) {
            $config['create_time'] = $now;
            $config['update_time'] = $now;
        }
        unset($config);

        $this->table('config')->insert($configs)->saveData();
    }

    /**
     * 写入后续迁移中归档到配置表的默认值。
     */
    private function seedMergedConfigs(): void
    {
        $now = date('Y-m-d H:i:s');
        $configs = [
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_ENABLED,
                'title' => '佣金提现开关',
                'into' => '关闭后移动端不可提交佣金提现申请',
                'value' => '1',
                'sort' => 105,
            ],
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_FEE_RATE,
                'title' => '佣金提现服务费率',
                'into' => '小数口径，如 0.001 表示 0.1%，用于计算提现手续费',
                'value' => '0.001',
                'sort' => 106,
            ],
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_FEE,
                'title' => '佣金提现最低手续费',
                'into' => '手续费低于该金额时按该金额收取，单位元',
                'value' => '0.1',
                'sort' => 107,
            ],
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_MIN_AMOUNT,
                'title' => '佣金提现单笔最低金额',
                'into' => '用户单笔佣金提现最低金额，单位元',
                'value' => '10',
                'sort' => 108,
            ],
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_MAX_AMOUNT,
                'title' => '佣金提现单笔最高金额',
                'into' => '用户单笔佣金提现最高金额，单位元',
                'value' => '50000',
                'sort' => 109,
            ],
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_ARRIVAL_TIME,
                'title' => '佣金提现预计到账时间',
                'into' => '移动端佣金提现页展示的预计到账时间文案',
                'value' => '预计 2 小时内到账',
                'sort' => 110,
            ],
            [
                'key' => ConfigKeyEnum::COMMISSION_WITHDRAW_NOTES,
                'title' => '佣金提现须知',
                'into' => '移动端佣金提现页展示的提现须知，换行分隔多条',
                'value' => "单笔最低提现 10.00 元，最高提现 50,000.00 元。\n手续费按提现金额的 0.1% 收取，最低 0.1 元。",
                'sort' => 111,
            ],
            [
                'key' => ConfigKeyEnum::MERCHANT_FUND_FREEZE_DAYS,
                'title' => '商户资金冻结天数',
                'into' => '订单完成后商户收入继续冻结的天数，0 表示立即释放',
                'value' => '0',
                'sort' => 105,
            ],
            [
                'key' => ConfigKeyEnum::DISTRIBUTION_ENABLED,
                'title' => '分销总开关',
                'into' => '控制移动端推广、绑定和订单返佣是否生效',
                'value' => '0',
                'sort' => 300,
            ],
            [
                'key' => ConfigKeyEnum::DISTRIBUTION_LEVEL,
                'title' => '分销等级',
                'into' => '1 表示一级分销，2 表示二级分销',
                'value' => '2',
                'sort' => 301,
            ],
            [
                'key' => ConfigKeyEnum::DISTRIBUTION_MODE,
                'title' => '分销模式',
                'into' => 'all=人人分销，specified=指定分销',
                'value' => 'specified',
                'sort' => 302,
            ],
            [
                'key' => ConfigKeyEnum::DISTRIBUTION_FIRST_RATE,
                'title' => '一级返佣比例',
                'into' => '百分比口径，5 表示 5%',
                'value' => '0.00',
                'sort' => 303,
            ],
            [
                'key' => ConfigKeyEnum::DISTRIBUTION_SECOND_RATE,
                'title' => '二级返佣比例',
                'into' => '百分比口径，5 表示 5%',
                'value' => '0.00',
                'sort' => 304,
            ],
            [
                'key' => ConfigKeyEnum::POINTS_ENABLED,
                'title' => '积分功能总开关',
                'into' => '关闭后移动端不可使用积分抵扣，签到也不可发放积分',
                'value' => '1',
                'sort' => 218,
            ],
            [
                'key' => ConfigKeyEnum::POINTS_ACTIVITY_SIGNIN_ENABLED,
                'title' => '积分签到功能开关',
                'into' => '关闭后移动端不可签到和领取签到任务积分',
                'value' => '1',
                'sort' => 219,
            ],
            [
                'key' => ConfigKeyEnum::ORDER_POINTS_RATIO,
                'title' => '积分抵扣比例',
                'into' => '每抵扣 1 元需要消耗的积分数，例如 100 表示 100 积分抵扣 1 元',
                'value' => '100',
                'sort' => 220,
            ],
            [
                'key' => ConfigKeyEnum::ORDER_POINTS_DEDUCT_LIMIT,
                'title' => '抵扣上限积分',
                'into' => '单笔订单最多可使用的积分数量，0 表示不限制',
                'value' => '0',
                'sort' => 221,
            ],
            [
                'key' => OrderConfigKeyEnum::GIFT_POINTS_FREEZE_DAYS,
                'title' => '订单赠送积分冻结天数',
                'into' => '内容/专题订单支付后赠送积分冻结天数，到期后自动释放并完成订单',
                'value' => '7',
                'sort' => 230,
            ],
        ];

        foreach ($configs as $config) {
            $this->insertConfigIfMissing($config, $now);
        }

        $this->deleteObsoleteConfigs();
    }

    /**
     * 插入单个配置项，不覆盖已有配置。
     */
    private function insertConfigIfMissing(array $config, string $now): void
    {
        $prefix = $this->getPrefix();
        $key = addslashes((string)$config['key']);
        $title = addslashes((string)$config['title']);
        $into = addslashes((string)$config['into']);
        $value = addslashes(json_encode((string)$config['value'], JSON_UNESCAPED_UNICODE));
        $sort = (int)$config['sort'];

        $this->execute(
            "INSERT INTO `{$prefix}config` "
            . "(`key_name`, `cate_id`, `value`, `title`, `into`, `sort`, `status`, `create_time`, `update_time`) "
            . "SELECT '{$key}', 0, '{$value}', '{$title}', '{$into}', {$sort}, 0, '{$now}', '{$now}' "
            . "WHERE NOT EXISTS (SELECT 1 FROM `{$prefix}config` WHERE `key_name` = '{$key}' LIMIT 1)"
        );
    }

    /**
     * 清理已被新配置替代的旧签到和佣金提现配置。
     */
    private function deleteObsoleteConfigs(): void
    {
        $prefix = $this->getPrefix();
        $keys = [
            'activity_signin_daily_points',
            'activity_signin_week_rewards',
            'commission_withdraw_promotion_enabled',
            'commission_withdraw_promotion_text',
        ];
        $safeKeys = array_map(static fn(string $key): string => "'" . addslashes($key) . "'", $keys);
        $this->execute("DELETE FROM `{$prefix}config` WHERE `key_name` IN (" . implode(',', $safeKeys) . ")");
    }

    /**
     * 执行原 upgrade.sql 中属于配置表的初始化数据。
     */
    private function seedUpgradeConfigs(): void
    {
        $seedFile = __DIR__ . '/config_seed.sql';
        if (!is_file($seedFile)) {
            return;
        }

        $sql = file_get_contents($seedFile);
        if (!is_string($sql) || trim($sql) === '') {
            return;
        }

        $prefix = $this->getPrefix();
        $sql = preg_replace(
            '/`(?:lf|ll)_config`/',
            '`' . $prefix . 'config`',
            $sql
        );

        if (is_string($sql) && trim($sql) !== '') {
            $this->execute($sql);
        }
    }

    private function getPrefix(): string
    {
        return config('database.connections.' . config('database.default') . '.prefix');
    }
}
