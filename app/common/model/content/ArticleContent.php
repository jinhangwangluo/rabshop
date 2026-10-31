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

namespace app\common\model\content;

use app\common\model\Base;

/**
 * 文章内容模型
 */
class ArticleContent extends Base
{
    /**
     * 表名
     */
    protected $name = 'article_content';

    /**
     * 主键
     */
    protected $pk = 'nid';

    /**
     * 自动写入时间戳
     */
    protected $autoWriteTimestamp = false;

    /**
     * 搜索器：文章ID
     */
    public function searchNidAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('nid', $value);
        }
    }

    public function setImagesAttr($value)
    {
        return $value ? json_encode($value) : '';
    }

    public function setTrialImagesAttr($value)
    {
        return $value ? json_encode($value) : '';
    }

    public function getImagesAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }
    
    public function getTrialImagesAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }
}
