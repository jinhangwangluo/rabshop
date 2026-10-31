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

namespace app\common\model\store;

use app\common\model\Base;

/**
 * 商品批量采集任务模型。
 */
class ProductCollectTask extends Base
{
    /**
     * @var string
     */
    protected $name = 'product_collect_task';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * 采集状态搜索器。
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * 上架状态搜索器。
     */
    public function searchPublishStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('publish_status', (int)$value);
        }
    }

    /**
     * 批次号搜索器。
     */
    public function searchBatchNoAttr($query, $value): void
    {
        $batchNo = trim((string)$value);
        if ($batchNo !== '') {
            $query->where('batch_no', $batchNo);
        }
    }

    /**
     * 标题/链接关键字搜索器。
     */
    public function searchKeywordAttr($query, $value): void
    {
        $keyword = trim((string)$value);
        if ($keyword !== '') {
            $query->where('title|source_url', 'like', '%' . $keyword . '%');
        }
    }
}
