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

namespace app\common\command\migrate;

use think\migration\command\migrate\Rollback as BaseRollback;

/**
 * 覆盖 migrate:rollback：扫描版本子目录下的迁移文件。
 */
class Rollback extends BaseRollback
{
    use VersionedPath;
}
