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
 * 文章表
 */
class CreateArticleTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('article', ['comment' => '内容表', 'collation' => 'utf8mb4_general_ci']);
        $table->addColumn('mer_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID：0-平台发布']);
        $table->addColumn('article_category_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '分类ID']);
        $table->addColumn('title', 'string', ['limit' => 100, 'default' => '', 'comment' => '文章标题']);
        $table->addColumn('author', 'string', ['limit' => 30, 'default' => '', 'comment' => '作者']);
        $table->addColumn('source', 'string', ['limit' => 50, 'default' => '', 'comment' => '来源']);
        $table->addColumn('keywords', 'string', ['limit' => 100, 'default' => '', 'comment' => '关键词']);
        $table->addColumn('description', 'string', ['limit' => 1000, 'default' => '', 'comment' => '描述']);
        $table->addColumn('cover', 'string', ['limit' => 255, 'default' => '', 'comment' => '封面图片']);
        $table->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $table->addColumn('view_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '浏览量']);
        $table->addColumn('virtual_learn_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '虚拟学习人数']);
        $table->addColumn('like_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '点赞数']);
        $table->addColumn('content_type', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '内容类型：1-图文 2-文字 3-小说 4-图片 5-音频 6-视频']);
        $table->addColumn('media_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '音频/视频URL']);
        $table->addColumn('media_duration', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '媒体时长（秒）']);
        $table->addColumn('trial_media_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '试听/试看片段URL']);
        $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '购买价格：0-免费']);
        $table->addColumn('original_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '原价（划线价）']);
        $table->addColumn('gift_points', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '付费内容购买赠送积分']);
        $table->addColumn('word_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '字数统计（小说/文字类型使用）']);
        $table->addColumn('chapter_count', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '章节数（小说类型使用）']);
        $table->addColumn('audit_remark', 'string', ['limit' => 500, 'default' => '', 'comment' => '审核备注']);
        $table->addColumn('audit_time', 'timestamp', ['null' => true, 'default' => null, 'comment' => '最近一次审核时间']);
        $table->addColumn('audit_admin_id', 'integer', ['limit' => 11, 'null' => false, 'default' => 0, 'comment' => '最近一次审核管理员ID']);
        $table->addColumn('is_trial', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否支持试看/试读：0-否，1-是']);
        $table->addColumn('is_hot', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否热门：0-否，1-是']);
        $table->addColumn('is_banner', 'boolean', ['limit' => 1, 'default' => 0, 'comment' => '是否轮播图：0-否，1-是']);
        $table->addColumn('is_member_only', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否会员专属：0否 1是']);
        $table->addColumn('status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $table->addColumn('audit_status', 'boolean', ['limit' => 1, 'default' => 1, 'comment' => '审核状态：0-待审核 1-已通过 2-已驳回']);
        $table->addTimestamps();
        $table->addSoftDelete();
        $table->addIndex('id');
        $table->addIndex('article_category_id');
        $table->addIndex('status');
        $table->addIndex('is_hot');
        $table->addIndex('is_banner');
        $table->addIndex('mer_id');
        $table->addIndex('audit_status');
        $table->addIndex('content_type');
        $table->create();
    }
}
