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

namespace app\common\model\setting\system;

use app\common\model\Base;

/**
 * 文件库 AI 生图任务模型。
 */
class FileAiGenerationTask extends Base
{
    /**
     * @var string
     */
    protected $name = 'file_ai_generation_task';

    /**
     * @var string
     */
    protected $pk = 'id';

    /**
     * 任务参数搜索器。
     */
    public function searchStatusAttr($query, $value): void
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * 标题/提示词搜索器。
     */
    public function searchKeywordAttr($query, $value): void
    {
        $keyword = trim((string)$value);
        if ($keyword !== '') {
            $query->where('title|prompt', 'like', '%' . $keyword . '%');
        }
    }
}
