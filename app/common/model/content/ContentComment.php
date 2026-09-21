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

namespace app\common\model\content;

use app\common\model\Base;
use app\common\model\content\Article;
use app\common\model\user\User;
use think\model\concern\SoftDelete;

/**
 * 内容评论模型
 */
class ContentComment extends Base
{
    use SoftDelete;

    /**
     * 表名
     */
    protected $name = 'content_comment';

    /**
     * 主键
     */
    protected $pk = 'id';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'datetime';

    /**
     * JSON 字段
     */
    protected $json = ['images'];

    /**
     * 字段类型转换
     */
    protected $type = [
        'images'          => 'array',
        'pid'             => 'integer',
        'root_id'         => 'integer',
        'user_id'         => 'integer',
        'article_id'      => 'integer',
        'reply_to_uid'    => 'integer',
    ];

    /**
     * 最大图片数量
     */
    const MAX_IMAGES = 5;

    /**
     * 一级评论 pid
     */
    const PID_ROOT = 0;

    /**
     * 关联：评论者
     */
    public function user(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uid');
    }

    /**
     * 关联：所属文章
     */
    public function article(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id', 'id');
    }

    /**
     * 关联：父评论
     */
    public function parent(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(self::class, 'pid', 'id');
    }

    /**
     * 关联：子评论（一级下所有二级）
     */
    public function children(): \think\model\relation\HasMany
    {
        return $this->hasMany(self::class, 'pid', 'id');
    }

    // ===========================
    //        搜索器
    // ===========================

    /**
     * 搜索器：评论ID
     */
    public function searchIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('content_comment.id', (int)$value);
        }
    }

    /**
     * 搜索器：文章ID
     */
    public function searchArticleIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('content_comment.article_id', (int)$value);
        }
    }

    /**
     * 搜索器：用户ID
     */
    public function searchUserIdAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('content_comment.user_id', (int)$value);
        }
    }

    /**
     * 搜索器：仅一级评论（pid=0）
     */
    public function searchIsRootAttr($query, $value): void
    {
        if ($value !== '') {
            if ((int)$value === 1) {
                $query->where('content_comment.pid', self::PID_ROOT);
            } else {
                $query->where('content_comment.pid', '>', 0);
            }
        }
    }

    /**
     * 搜索器：关键词（评论内容模糊搜索）
     */
    public function searchKeywordAttr($query, $value): void
    {
        if ($value !== '') {
            $query->where('content_comment.content', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器：创建时间范围
     */
    public function searchCreateTimeAttr($query, $value): void
    {
        if (is_array($value) && count($value) === 2) {
            $query->whereBetweenTime('content_comment.create_time', $value[0], $value[1]);
        } elseif ($value !== '') {
            $query->where('content_comment.create_time', 'between', $value);
        }
    }

    /**
     * 搜索器：用户昵称（需要 join user 表时使用）
     */
    public function searchNicknameAttr($query, $value): void
    {
        if ($value !== '') {
            $query->whereExists(function ($q) use ($value): void {
                $q->table('user')
                    ->whereColumn('user.uid', 'content_comment.user_id')
                    ->where('user.nickname', 'like', '%' . $value . '%');
            });
        }
    }
}
