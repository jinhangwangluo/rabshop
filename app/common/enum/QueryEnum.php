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

namespace app\common\enum;

enum QueryEnum
{
    /** @var string 等于 */
    const EQ = '=';
    /** @var string 不等于 */
    const NEQ = '<>';
    /** @var string 大于 */
    const GT = '>';
    /** @var string 大于等于 */
    const EGT = '>=';
    /** @var string 小于 */
    const LT = '<';
    /** @var string 小于等于 */
    const ELT = '<=';
    /** @var string 模糊查询 */
    const LIKE = 'LIKE';
    /** @var string 非模糊查询 */
    const NOT_LIKE = 'NOT LIKE';
    /** @var string IN查询 */
    const IN = 'IN';
    /** @var string NOT IN查询 */
    const NOT_IN = 'NOT IN';
    /** @var string 区间查询 */
    const BETWEEN = 'BETWEEN';
    /** @var string 非区间查询 */
    const NOT_BETWEEN = 'NOT BETWEEN';
    /** @var string 空值查询 */
    const NULL = 'NULL';
    /** @var string 非空值查询 */
    const NOT_NULL = 'NOT NULL';
    /** @var string 时间查询 */
    const TIME = 'TIME';
}
