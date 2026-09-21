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

/**
 * 从线上库只读提取「安装填充数据」，生成 database/migrations/v1.2/upgrade.sql。
 *
 * 用法：
 *   php database/migrations/v1.2/_extract_seed_data.php            # 生成 upgrade.sql
 *   php database/migrations/v1.2/_extract_seed_data.php --dry-run  # 只统计不写文件
 *
 * 执行时机：安装流程 InstallService::runInstall() → runMigrations()（跑 v1.0 + v1.2 全部迁移）
 * → runUpgradeSql()（glob database/migrations/v{版本}/upgrade.sql 并按序逐条执行）。
 * 因此脚本只负责产出 SQL：表名统一写 `ll_xxx`，安装时由 InstallService 按用户选择的前缀替换。
 *
 * 约束（来自 InstallService::runUpgradeSql 的实现）：
 * - 只读线上库，绝不写库；只 SELECT / SHOW。
 * - 语句以分号结尾、不使用 USE / DELIMITER / 多语句块；一条失败会中断整个安装，故统一 INSERT IGNORE。
 * - 文件为 UTF-8 无 BOM。
 *
 * 下划线开头的文件不会被 phinx 当作迁移加载（同 v1.0/_extract_upgrade_seeds.php）。
 */

const SOURCE_ENV = __DIR__ . '/../../../.env';
const OUTPUT_FILE = __DIR__ . '/upgrade.sql';
const ROWS_PER_STATEMENT = 100;

/* ------------------------------------------------------------------ 配置：保留范围 */

// 保留一个商户（其从属数据：后台账号、角色、分类、优惠券、运费模板等）
$keepMerchantId = 1;

// 保留 4 个店铺商品：12 = 商户自有商品，9/10/11 = 平台自营商品
$keepStoreProductIds = [12, 9, 10, 11];

// 保留一个 AI 供应商与一个模型，其配置数据（api_key / params）全部清空
$keepAiProviderId = 1;
$keepAiModelId = 1;

// AI 相关分类（素材 type=1 / 话术 type=5 / 知识库 type=6）各保留一条
$keepAiCategoryIds = [69, 26, 24];

// 清空值的 config key：密钥、证书、第三方凭据、域名等环境相关数据（行保留）
$blankConfigKeys = [
    'wechat_private_key', 'wechat_public_key', 'wechat_cert_path', 'wechat_key_path', 'wechat_api_v3_key',
    'wechat_mch_serial_no', 'wechat_public_key_id', 'wechat_api_key', 'wechat_app_secret',
    'wechat_appid', 'wechat_secret', 'wechat_aes_key', 'wechat_token', 'wechat_path', 'wechat_num',
    'program_appid', 'program_app_secret',
    'alipay_private_key', 'alipay_public_key', 'alipay_app_id',
    'qiniu_access_key', 'qiniu_secret_key', 'qiniu_domain', 'live_qiniu_access_key', 'live_qiniu_secret_key',
    'live_qiniu_play_auth_key', 'live_ali_access_key', 'live_ali_access_secret',
    'oss_access_key', 'oss_secret_key', 'oss_bucket', 'oss_domain',
    'cos_secret_id', 'cos_secret_key', 'cos_bucket', 'cos_domain',
    'sms_access_key', 'sms_secret_key', 'sms_sign', 'sms_code_templateid',
    'tencent_map_key', 'express_query_appcode', 'message_push_email_api_key',
    'product_collector_key', 'product_collector_secret',
    'web_host', 'site_url', 'h5_url', 'admin_url', 'api_url',
];

/* ------------------------------------------------------------------ 依赖与工具 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** 解析 .env 的 [DATABASE] 段；不打印任何凭据。 */
function sourceConfig(): array
{
    $env = [];
    $section = '';
    foreach (file(SOURCE_ENV) as $line) {
        if (preg_match('/^\s*\[([A-Za-z_0-9]+)\]/', $line, $m)) {
            $section = strtoupper($m[1]);
            continue;
        }
        if (preg_match('/^\s*([A-Za-z_0-9]+)\s*=\s*(.*)$/', $line, $m)) {
            $env[$section . '.' . strtoupper($m[1])] = trim($m[2]);
        }
    }
    foreach (['HOSTNAME', 'HOSTPORT', 'DATABASE', 'USERNAME'] as $key) {
        if (($env['DATABASE.' . $key] ?? '') === '') {
            throw new RuntimeException('.env 缺少 [DATABASE] ' . $key);
        }
    }
    $env['DATABASE.PREFIX'] = $env['DATABASE.PREFIX'] ?? ($env['PREFIX'] ?? 'll_');
    return $env;
}

$source = sourceConfig();
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $source['DATABASE.HOSTNAME'], $source['DATABASE.HOSTPORT'], $source['DATABASE.DATABASE']);
$prefix = $source['DATABASE.PREFIX'];

/** 只读查询：远端库偶发断连，逐次重连重试。 */
function q(string $sql): array
{
    global $dsn, $source;
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        try {
            $pdo = new PDO($dsn, $source['DATABASE.USERNAME'], $source['DATABASE.PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 15,
            ]);
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            if ($attempt === 5) {
                throw $exception;
            }
            usleep(800000);
        }
    }
    return [];
}

/** 补全表前缀：允许调用处传带前缀或不带前缀的表名。 */
function fullTable(string $table): string
{
    global $prefix;
    return str_starts_with($table, $prefix) ? $table : $prefix . $table;
}

/** 读取表字段（带缓存）；用于自动排除软删过滤与未知列。 */
function tableColumns(string $table): array
{
    static $cache = [];
    $table = fullTable($table);
    if (!isset($cache[$table])) {
        $cache[$table] = array_column(q("SHOW COLUMNS FROM `{$table}`"), 'Field');
    }
    return $cache[$table];
}

/** 校验表存在，避免把线上库不存在的表写进填充脚本。 */
function tableExists(string $table): bool
{
    $table = fullTable($table);
    return q("SHOW TABLES LIKE '" . str_replace('_', '\\_', $table) . "'") !== [];
}

/** 组装查询：自动补软删过滤，未指定排序时按主键升序，保证输出可复现。 */
function tableRows(string $table, string $where = '1=1', string $orderBy = '', int $limit = 0): array
{
    $table = fullTable($table);
    if (!tableExists($table)) {
        return [];
    }
    $columns = tableColumns($table);
    if (in_array('delete_time', $columns, true)) {
        $where = "({$where}) AND `delete_time` IS NULL";
    }
    $orderBy = $orderBy !== '' ? $orderBy : (in_array('id', $columns, true) ? '`id` ASC' : '');
    $sql = "SELECT * FROM `{$table}` WHERE {$where}" . ($orderBy !== '' ? " ORDER BY {$orderBy}" : '') . ($limit > 0 ? " LIMIT {$limit}" : '');
    return q($sql);
}

/** SQL 字面量：null 写 NULL，其余按连接转义后加引号。 */
function sqlValue($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$value) . "'";
}

/* ------------------------------------------------------------------ 收集数据 */

$statements = [];
$summary = [];
$report = [];

/**
 * 生成 INSERT IGNORE 语句（分批），并登记统计。
 *
 * @param array<string,mixed> $rows 待写入行
 * @param array<string,string|callable> $blanks 列 => 固定替换值或回调（行数组 => 新值）
 */
function addRows(string $table, array $rows, array $blanks = []): void
{
    global $statements, $summary, $prefix;
    $table = fullTable($table);
    if ($rows === []) {
        $summary[$table] = 0;
        return;
    }
    $columns = tableColumns($table);
    foreach ($rows as $index => $row) {
        foreach ($blanks as $column => $rule) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $row[$column] = is_callable($rule) ? $rule($row) : $rule;
        }
        // 先应用清空/替换，再按表结构裁剪列，避免把改动丢掉
        $rows[$index] = array_intersect_key($row, array_flip($columns));
    }
    $columnSql = '`' . implode('`, `', $columns) . '`';
    foreach (array_chunk($rows, ROWS_PER_STATEMENT) as $chunk) {
        $values = [];
        foreach ($chunk as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = sqlValue($row[$column] ?? null);
            }
            $values[] = '  (' . implode(', ', $line) . ')';
        }
        $statements[] = "INSERT IGNORE INTO `{$table}` ({$columnSql}) VALUES\n" . implode(",\n", $values) . ';';
    }
    $summary[$table] = count($rows);
}

/* 分类字典：平台与保留商户的全量分类（含商品分类、专题/直播分类、DIY 链接、内容、标签、配置分组） */
$aiCategoryIds = implode(',', array_map('intval', $keepAiCategoryIds));
$categoryWhere = "(owner_id IN (0, {$keepMerchantId}) AND type NOT IN (1, 5, 6))"
    . " OR (type IN (1, 5, 6) AND id IN ({$aiCategoryIds}))";
$categoryParent = [];
$categoryRows = tableRows($prefix . 'category', $categoryWhere);
foreach ($categoryRows as $categoryRow) {
    $categoryParent[(int)$categoryRow['id']] = (int)$categoryRow['pid'];
}
$keptCategoryIds = array_flip(array_map('intval', array_column($categoryRows, 'id')));
// 商品引用的分类可能属于其他 owner，连同其祖先一起保留，避免商品与分类关系悬空
$keepProductIdList = implode(',', array_map('intval', $keepStoreProductIds) ?: [0]);
$keptProductRows = tableRows($prefix . 'store_product', "id IN ({$keepProductIdList})");
$keptProductIdList = implode(',', array_map('intval', array_column($keptProductRows, 'id')) ?: [0]);
$referencedCategoryIds = array_filter(array_map('intval', array_column($keptProductRows, 'category_id')));
foreach (tableRows($prefix . 'product_category_relation', "store_product_id IN ({$keptProductIdList})") as $relation) {
    $referencedCategoryIds[] = (int)$relation['category_id'];
}
foreach (array_unique($referencedCategoryIds) as $categoryId) {
    for ($cursor = $categoryId; $cursor > 0; $cursor = (int)$categoryParent[$cursor]) {
        if (isset($keptCategoryIds[$cursor])) {
            break;
        }
        $extraCategoryRows = tableRows($prefix . 'category', "id = {$cursor}");
        foreach ($extraCategoryRows as $extraRow) {
            $categoryRows[] = $extraRow;
            $keptCategoryIds[(int)$extraRow['id']] = true;
            $categoryParent[(int)$extraRow['id']] = (int)$extraRow['pid'];
        }
        if ($extraCategoryRows === []) {
            break;
        }
    }
}
usort($categoryRows, static fn (array $left, array $right): int => (int)$left['id'] <=> (int)$right['id']);
addRows('category', $categoryRows);

/* 配置：全表保留，敏感/环境相关值清空 */
$blankKeys = array_flip($blankConfigKeys);
$configRows = tableRows($prefix . 'config');
$blanked = [];
foreach ($configRows as $row) {
    if (isset($blankKeys[(string)$row['key_name']]) && (string)$row['value'] !== '') {
        $blanked[] = (string)$row['key_name'];
    }
}
addRows('config', $configRows, [
    'value' => static function (array $row) use ($blankKeys): string {
        // config.value 平台按 json_encode 存储，清空写 JSON 空串保持一致
        return isset($blankKeys[(string)$row['key_name']]) ? '""' : (string)$row['value'];
    },
]);
if ($blanked !== []) {
    $report[] = 'config 已清空值的 key（' . count($blanked) . ' 个）：' . implode(', ', $blanked);
}
addRows('config_template', tableRows($prefix . 'config_template'));
addRows('combination_data_config', tableRows($prefix . 'combination_data_config'));
addRows('combination_data', tableRows($prefix . 'combination_data'));
addRows('payment_config', tableRows($prefix . 'payment_config'));

/* 权限与菜单：平台/商户菜单与接口权限（安装后后台导航依赖） */
$menuParentName = [];
foreach (tableRows($prefix . 'menus') as $menuRow) { $menuParentName[(int)$menuRow['id']] = (string)$menuRow['name']; }
$actionWordPattern = '/(查看|获取|查询|新增|添加|创建|修改|更新|编辑|删除|移除|导出|导入|上传|保存|设置|启用|停用|审核|取消|确认|发布|上架|下架|重置|绑定|解绑|标记|发送|同步|执行|切换|踢出|禁言|邀请|分配|转移|关闭|开启|统计|列表|详情|配置|管理|操作|领取|退款|提现|发货|收货|备注)/u';
$menuRows = tableRows($prefix . 'menus');
foreach ($menuRows as $index => $row) {
    $url = ltrim((string)$row['url'], '/');
    if ((int)$row['auth_type'] !== 2 || !str_starts_with($url, 'api/')) {
        continue;
    }
    // checkRole 用小写方法比对，存量大写方法会导致鉴权永远不通过
    $method = strtolower(trim((string)$row['methods']));
    $menuRows[$index]['methods'] = $method;
    if (preg_match($actionWordPattern, (string)$row['name']) !== 1) {
        $parentName = (string)($menuParentName[$row['pid']] ?? '');
        $menuRows[$index]['name'] = menuPermissionName($method, $url, '', $parentName);
    }
}
addRows('menus', $menuRows);
addRows('admin_role', tableRows($prefix . 'admin_role', "merchant_id IN (0, {$keepMerchantId})"));

/* 商户与其后台账号（密码重置为平台默认 123456，与安装创建的超管一致） */
$merchantRows = tableRows($prefix . 'merchant', "id = {$keepMerchantId}");
$defaultHash = (string)(q("SELECT password FROM `{$prefix}admin` ORDER BY id ASC LIMIT 1")[0]['password'] ?? '');
if ($defaultHash === '' || !password_verify('123456', $defaultHash)) {
    $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
}
addRows('merchant', $merchantRows);
addRows('admin', tableRows($prefix . 'admin', "merchant_id = {$keepMerchantId} AND platform_type = 2"), ['password' => $defaultHash]);
$report[] = '商户后台账号密码已重置为默认 123456（与平台超管一致）';

/* 商品：保留 4 个店铺商品及其库商品、SKU、规格、参数、分类关系 */
$storeProductIds = implode(',', array_map('intval', $keepStoreProductIds));
$storeProducts = tableRows($prefix . 'store_product', "id IN ({$storeProductIds})");
$keptStoreProductIds = array_map('intval', array_column($storeProducts, 'id'));
$keptStoreProductIds = $keptStoreProductIds !== [] ? $keptStoreProductIds : [0];
$storeProductIdList = implode(',', $keptStoreProductIds);

$skus = tableRows($prefix . 'store_product_sku', "store_product_id IN ({$storeProductIdList})");
$libraryIds = array_filter(array_map('intval', array_column($storeProducts, 'library_product_id')));
foreach ($skus as $sku) {
    $libraryIds[] = (int)($sku['library_product_id'] ?? 0);
    foreach (explode(',', (string)($sku['spec_value_ids'] ?? '')) as $valueId) {
        $libraryIds[] = (int)$valueId; // 规格值 id 与库商品 id 不使用同一空间，下面按需过滤
    }
}
$libraryIds = array_values(array_unique(array_filter($libraryIds)));
$libraryIdList = implode(',', $libraryIds !== [] ? $libraryIds : [0]);

addRows('product_library', tableRows($prefix . 'product_library', "id IN ({$libraryIdList})"));
addRows('store_product', $storeProducts);
addRows('store_product_sku', $skus);
addRows('store_product_spec_config', tableRows($prefix . 'store_product_spec_config', "store_product_id IN ({$storeProductIdList})"));
addRows('product_category_relation', tableRows($prefix . 'product_category_relation', "store_product_id IN ({$storeProductIdList})"));
addRows('product_spec', tableRows($prefix . 'product_spec'));
addRows('product_spec_item', tableRows($prefix . 'product_spec_item'));
addRows('product_spec_value', tableRows($prefix . 'product_spec_value'));
addRows('product_attribute', tableRows($prefix . 'product_attribute'));
addRows('product_attribute_value', tableRows($prefix . 'product_attribute_value'));
addRows('product_attribute_category_relation', tableRows($prefix . 'product_attribute_category_relation'));
addRows('product_brand', tableRows($prefix . 'product_brand'));
addRows('product_group', tableRows($prefix . 'product_group'));
addRows('product_service', tableRows($prefix . 'product_service'));
addRows('freight_template', tableRows($prefix . 'freight_template'));
addRows('freight_template_rule', tableRows($prefix . 'freight_template_rule'));

/* 内容与专题：专题、专题内容、文章、标签 */
$specials = tableRows($prefix . 'special', "merchant_id IN (0, {$keepMerchantId})");
$specialIds = implode(',', array_map('intval', array_column($specials, 'id')) ?: [0]);
$articles = tableRows($prefix . 'article', "mer_id IN (0, {$keepMerchantId})");
$articleIds = implode(',', array_map('intval', array_column($articles, 'id')) ?: [0]);
addRows('special', $specials);
addRows('special_content', tableRows($prefix . 'special_content', "special_id IN ({$specialIds}) AND article_id IN ({$articleIds})"));
addRows('article', $articles);
addRows('article_content', tableRows($prefix . 'article_content', "article_id IN ({$articleIds})"));
$tags = tableRows($prefix . 'tag', "owner_id IN (0, {$keepMerchantId})");
addRows('tag', $tags);
$keptTagIds = array_map('intval', array_column($tags, 'id'));
$keptRelationIds = array_merge(
    array_map('intval', array_column($specials, 'id')),
    array_map('intval', array_column($articles, 'id')),
    $keptStoreProductIds
);
addRows('tag_relation', array_values(array_filter(
    tableRows($prefix . 'tag_relation'),
    static fn (array $row): bool => in_array((int)$row['tag_id'], $keptTagIds, true) && in_array((int)$row['relation_id'], $keptRelationIds, true)
)));

/* 协议、关于我们、消息推送模板 */
addRows('agreement', tableRows($prefix . 'agreement'));
addRows('about_us', tableRows($prefix . 'about_us'));
addRows('message_push_template', tableRows($prefix . 'message_push_template'));

/* DIY 装修：排除插件注册页面（plg_*）与指向插件页面的链接 */
$diyPages = array_values(array_filter(
    tableRows($prefix . 'diy_page'),
    static fn (array $row): bool => !str_starts_with((string)$row['page_type'], 'plg_')
        && ((int)$row['is_default'] === 1 || in_array((string)$row['page_type'], ['home', 'user_center', 'category_page'], true))
));
$diyPageIds = implode(',', array_map('intval', array_column($diyPages, 'id')) ?: [0]);
addRows('diy_page', $diyPages);
addRows('diy_page_link', array_values(array_filter(
    tableRows($prefix . 'diy_page_link'),
    static fn (array $row): bool => !str_contains((string)$row['link_url'], '/plugins/')
)));
addRows('diy_component', tableRows($prefix . 'diy_component', "diy_page_id IN ({$diyPageIds})"));

/* 直播：礼物与等级字典 */
addRows('live_gift', tableRows($prefix . 'live_gift'));
addRows('live_room_level_config', tableRows($prefix . 'live_room_level_config'));

/* 营销：店铺优惠券（不含用户领取记录） */
addRows('store_coupon', tableRows($prefix . 'store_coupon', "merchant_id IN (0, {$keepMerchantId})"));
addRows('store_coupon_relation', tableRows($prefix . 'store_coupon_relation'));

/* AI：保留 1 个供应商与 1 个模型，配置数据全部清空 */
addRows('cs_ai_provider', tableRows($prefix . 'cs_ai_provider', "id = {$keepAiProviderId}"), ['api_key' => '']);
addRows('cs_ai_model', tableRows($prefix . 'cs_ai_model', "id = {$keepAiModelId}"), ['params' => '{}']);
$report[] = 'AI 供应商 api_key 与模型 params 已清空';

/* 基础字典：地区、快递公司、敏感词 */
addRows('city_area', tableRows($prefix . 'city_area'));
addRows('express_company', tableRows($prefix . 'express_company'));
addRows('sensitive_words', tableRows($prefix . 'sensitive_words'));

/* ------------------------------------------------------------------ 菜单与接口权限 */

/**
 * 解析路由文件，返回 [方法, 路径, 中文注释]；支持 Route::group('前缀', fn) 嵌套与逐行注释。
 *
 * @return list<array{method:string,path:string,comment:string}>
 */
function parseRoutes(string $file): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
    $prefixes = [];
    $routes = [];
    $comment = '';
    $depth = 0;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, 'use ') || str_starts_with($trimmed, 'declare')) {
            continue;
        }
        // 注释（含中文说明），供接口命名使用
        if (preg_match('#^//\s*(.+?)\s*$#u', $trimmed, $m)) {
            $comment = trim($m[1], " \t");
            continue;
        }
        if (preg_match("/Route::group\(\s*'([^']*)'/", $trimmed, $m)) {
            $prefixes[] = trim($m[1], '/');
            $depth++;
            continue;
        }
        if (preg_match('/Route::group\(\s*function/', $trimmed)) {
            $prefixes[] = '';
            $depth++;
            continue;
        }
        if (preg_match("/^Route::(get|post|put|patch|delete)\(\s*'([^']+)'/", $trimmed, $m)) {
            $segments = array_values(array_filter(array_merge($prefixes, [trim($m[2], '/')]), static fn (string $s): bool => $s !== ''));
            $routes[] = ['method' => strtolower($m[1]), 'path' => implode('/', $segments), 'comment' => $comment];
            $comment = '';
            continue;
        }
        // 结束一行内可能出现的 group 闭合
        if (str_contains($trimmed, '});') && $depth > 0 && !str_contains($trimmed, 'Route::')) {
            array_pop($prefixes);
            $depth--;
        }
    }
    return $routes;
}

/** 方法对应的中文动作，保证新增/修改/删除/查看可区分。 */
function menuActionLabel(string $method, string $path): string
{
    return match (true) {
        str_contains($path, 'export') || str_contains($path, 'download') => '导出',
        str_contains($path, 'import') || str_contains($path, 'upload') => '导入',
        str_contains($path, 'status') && $method === 'put' => '修改状态',
        str_contains($path, 'audit') || str_contains($path, 'review') => '审核',
        str_contains($path, 'cancel') => '取消',
        str_contains($path, 'toggle') || str_contains($path, 'switch') => '切换',
        str_contains($path, 'reply') => '回复',
        str_contains($path, 'assign') => '分配',
        str_contains($path, 'transfer') => '转移',
        $method === 'get' => '查看',
        $method === 'post' => '新增',
        $method === 'put', $method === 'patch' => '修改',
        $method === 'delete' => '删除',
        default => '操作',
    };
}

/** 生成接口权限名：中文动作 + 路由注释（去重动词）或父菜单名。 */
function menuPermissionName(string $method, string $path, string $comment, string $parentName): string
{
    $action = menuActionLabel($method, $path);
    $label = trim((string)preg_replace('/[（(].*$/u', '', $comment));
    if ($label === '') {
        return $action . ($parentName !== '' ? $parentName : '接口');
    }
    // 注释已含动作词时不再叠加，避免出现「查看获取列表」
    if (preg_match('/^(查看|获取|查询|新增|添加|创建|修改|更新|编辑|删除|移除|导出|导入|上传|保存|设置|启用|停用|审核|取消|确认|发布|上下架|重置|绑定|解绑)/u', $label)) {
        return $label;
    }
    return $action . $label;
}

/** 生成权限标识：与既有菜单数据一致（去掉 api 前缀，层级改短横线，参数名去掉尖括号）。 */
function menuAuthIden(string $path): string
{
    $iden = preg_replace('#^api/(admin|merchant)/#', '', $path) ?? $path;
    $iden = str_replace(['<', '>', '/', ':'], '-', $iden);
    $iden = preg_replace('/-+/', '-', $iden) ?? $iden;
    return trim($iden, '-');
}

// 保留现有菜单树（含既有权限行），并按其 url 前缀建立「接口 → 父菜单」锚点
$menuRows = tableRows('menus');
$menuColumns = tableColumns('menus');
$menuIndex = [];
$anchors = [];
foreach ($menuRows as $row) {
    $menuIndex[(int)$row['id']] = $row;
    $url = ltrim((string)$row['url'], '/');
    if ($url === '' || !str_starts_with($url, 'api/')) {
        continue;
    }
    $pid = (int)$row['pid'];
    if ($pid > 0) {
        $anchors[$url] = $pid;
        $segments = array_values(array_filter(explode('/', $url), static fn (string $s): bool => $s !== ''));
        for ($cut = 1; $cut <= 3; $cut++) {
            $prefixSegments = array_slice($segments, 0, -$cut);
            if (count($prefixSegments) < 3) {
                break;   // 至少保留 api/{surface}/{模块}，过宽前缀会把无关接口挂到同一菜单
            }
            $anchors[implode('/', $prefixSegments)] = $pid;
        }
    }
}

// 模块前缀 → 菜单名（同一模块下的接口挂到该菜单，保持中文树结构）
$moduleMenuNames = [
    'admin' => [
        'dashboard' => '首页', 'statistics' => '首页', 'turnover' => '首页',
        'user' => '用户', 'merchant' => '商户', 'order' => '订单', 'product' => '商品',
        'setting/config' => '配置管理', 'setting/menu' => '菜单管理', 'setting/role' => '角色管理',
        'setting/admin' => '权限配置', 'setting/about' => '关于我们', 'setting/sensitive_words' => '敏感词',
        'diy' => '页面装修', 'article' => '内容', 'special' => '专题', 'live' => '直播',
        'finance' => '财务', 'marketing' => '营销', 'distribution' => '分销', 'channel' => '渠道',
        'cs' => '客服', 'plugin' => '插件', 'message' => '消息',
        'content_comment' => '评论管理', 'tag_category' => '标签管理', 'tag' => '标签管理',
        'special_category' => '专题分类', 'audit' => '内容', 'content' => '内容',
        'wechat/wechat_reply' => '关键字回复', 'wechat/menus' => '服务号菜单', 'wechat' => '微信管理',
    ],
    'merchant' => [
        'dashboard' => '商户首页', 'product' => '商品列表', 'order' => '订单', 'store' => '店铺',
        'finance' => '财务', 'live' => '直播', 'marketing' => '营销', 'cs' => '客服',
        'statistics' => '首页', 'dashboard' => '首页', 'site_info' => '首页', 'auth' => '首页',
        'common/config' => '店铺设置', 'common' => '首页', 'setting' => '店铺设置',
    ],
];
$menuByName = [];
$menuSegmentIndex = [];
foreach ($menuRows as $row) {
    if ((int)$row['auth_type'] !== 0) {
        continue;
    }
    $key = (int)$row['platform_type'] . '|' . (string)$row['name'];
    $menuByName[$key] = $menuByName[$key] ?? (int)$row['id'];
    $menuUrl = trim((string)$row['url'], '/');
    if ($menuUrl === '' || str_starts_with($menuUrl, 'api/')) {
        continue;
    }
    $menuSegmentIndex[(int)$row['platform_type']][] = [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'segments' => array_map('strtolower', explode('/', $menuUrl)),
    ];
}

/**
 * 按路径段匹配父菜单：取公共前缀最长的菜单节点，其次取层级更深的节点。
 *
 * @param list<array{id:int,name:string,segments:list<string>}> $candidates 同端菜单节点
 * @param list<string> $routeSegments 路由相对路径段
 * @return array{id:int,name:string}|null 匹配到的父菜单
 */
function matchMenuBySegments(array $candidates, array $routeSegments): ?array
{
    $best = null;
    $bestScore = 0;
    foreach ($candidates as $candidate) {
        $score = 0;
        foreach ($candidate['segments'] as $position => $segment) {
            if (($routeSegments[$position] ?? null) !== $segment) {
                break;
            }
            $score++;
        }
        if ($score === 0) {
            continue;
        }
        $better = $score > $bestScore
            || ($score === $bestScore && $best !== null && count($candidate['segments']) > count($best['segments']))
            || ($score === $bestScore && $best !== null && count($candidate['segments']) === count($best['segments']) && $candidate['id'] < $best['id']);
        if ($best === null || $better) {
            $best = ['id' => $candidate['id'], 'name' => $candidate['name'], 'segments' => $candidate['segments']];
            $bestScore = $score;
        }
    }
    return $best === null ? null : ['id' => (int)$best['id'], 'name' => (string)$best['name']];
}

$existingPermissions = [];
foreach ($menuRows as $row) {
    if (str_starts_with(ltrim((string)$row['url'], '/'), 'api/')) {
        $existingPermissions[strtolower((string)$row['url']) . '|' . strtolower((string)$row['methods'])] = true;
    }
}

$generatedAt = date('Y-m-d H:i:s');
$nextMenuId = max(array_keys($menuIndex) ?: [0]) + 1;
$generated = [];
$generatedKeys = [];
$unmatched = [];
foreach ([['api/admin', 1, 'route/admin.php'], ['api/merchant', 2, 'route/merchant.php']] as [$surfacePrefix, $platformType, $routeFile]) {
    foreach (parseRoutes(dirname(__DIR__, 3) . '/' . $routeFile) as $route) {
        $path = $route['path'];
        if (!str_starts_with($path, $surfacePrefix)) {
            continue;
        }
        $relativeCheck = trim(substr($path, strlen($surfacePrefix)), '/');
        if (preg_match('#^(login|captcha|auth/refresh_token|plugins/runtime)(/|$)#', $relativeCheck) === 1) {
            continue;   // 登录、验证码、刷新 token 等公开接口不参与鉴权
        }
        $key = strtolower($path) . '|' . $route['method'];
        if (isset($existingPermissions[$key]) || isset($generatedKeys[$key])) {
            continue;   // 线上或本次已生成同路径同方法，避免重复
        }
        $generatedKeys[$key] = true;
        $relative = trim(substr($path, strlen($surfacePrefix)), '/');
        $parentId = 0;
        if (isset($anchors[$path])) {
            $parentId = $anchors[$path];
        } else {
            $segments = explode('/', $path);
            for ($cut = 1; $cut <= 3 && count($segments) > $cut; $cut++) {
                $candidate = implode('/', array_slice($segments, 0, -$cut));
                if (isset($anchors[$candidate])) {
                    $parentId = $anchors[$candidate];
                    break;
                }
            }
        }
        $surfaceName = $platformType === 1 ? 'admin' : 'merchant';
        $parentName = '';
        if ($parentId > 0 && isset($menuIndex[$parentId])) {
            $parentName = (string)$menuIndex[$parentId]['name'];
        } else {
            foreach ($moduleMenuNames[$surfaceName] as $module => $name) {
                if (!str_starts_with($relative, $module)) {
                    continue;
                }
                $candidateId = $menuByName[$platformType . '|' . $name] ?? 0;
                if ($candidateId > 0) {
                    $parentId = $candidateId;
                    $parentName = $name;
                    break;   // 命中真实菜单才停止，否则继续尝试更具体的模块键
                }
            }
        }
        if ($parentId === 0) {
            $matched = matchMenuBySegments($menuSegmentIndex[$platformType] ?? [], array_map('strtolower', explode('/', $relative)));
            if ($matched !== null) {
                $parentId = $matched['id'];
                $parentName = $matched['name'];
            }
        }
        if ($parentId === 0) {
            $unmatched[$surfaceName . '/' . strtok($relative, '/')] = true;
        }
        $generated[] = [
            'id' => $nextMenuId++,
            'pid' => $parentId,
            'icon' => '',
            'name' => menuPermissionName($route['method'], $path, $route['comment'], $parentName),
            'url' => $path,
            'methods' => $route['method'],
            'sort' => 0,
            'access' => '',
            'path' => $parentId > 0 ? ((string)($menuIndex[$parentId]['path'] ?? '') . '/' . $parentId . '/') : '',
            'auth_type' => 2,
            'auth_iden' => menuAuthIden($path),
            'status' => 1,
            'is_hidden' => 1,
            'platform_type' => $platformType,
            'create_time' => $generatedAt,
            'update_time' => $generatedAt,
        ];
    }
}

foreach (array_chunk($generated, ROWS_PER_STATEMENT) as $chunk) {
    $values = [];
    foreach ($chunk as $row) {
        $values[] = '  (' . implode(', ', array_map('sqlValue', $row)) . ')';
    }
    $statements[] = 'INSERT IGNORE INTO `' . fullTable('menus') . '` (`id`, `pid`, `icon`, `name`, `url`, `methods`, `sort`, `access`, `path`, `auth_type`, `auth_iden`, `status`, `is_hidden`, `platform_type`, `create_time`, `update_time`) VALUES'
        . "\n" . implode(",\n", $values) . ';';
}
$report[] = sprintf('按路由文件补齐接口权限 %d 条（auth_type=2，按方法校验），另有 %d 条线上已存在', count($generated), count($existingPermissions));
if ($unmatched !== []) {
    $report[] = '未匹配到父菜单的模块（pid=0，需人工确认）：' . implode(', ', array_slice(array_keys($unmatched), 0, 20));
}

/* ------------------------------------------------------------------ 输出 */

$header = [
    '-- 安装填充数据（由 database/migrations/v1.2/_extract_seed_data.php 从线上库只读生成）',
    '-- 生成时间：' . date('Y-m-d H:i:s'),
    '-- 表名统一写 `' . $prefix . 'xxx`，安装时由 InstallService 按用户选择的前缀替换；全部使用 INSERT IGNORE，可安全重复执行。',
    '-- 保留范围：1 个商户（id=' . $keepMerchantId . '）、' . count($keepStoreProductIds) . ' 个商品（id=' . implode(',', $keepStoreProductIds) . '）、'
        . '分类/直播/DIY/内容字典全量、AI 供应商与模型各 1 条（配置清空）。',
    '-- 敏感数据：config 中的密钥/证书/第三方凭据/域名等值已清空，运行时数据（订单/日志/统计/队列/会话）不在本文件内。',
    '',
];
$body = [];
foreach ($summary as $table => $count) {
    $body[] = "-- {$table}: {$count} 行";
}
$sql = implode("\n", $header) . implode("\n", $body) . "\n\n" . implode("\n\n", $statements) . "\n";

$totalRows = array_sum($summary);
echo "=== 提取统计 ===\n";
foreach ($summary as $table => $count) {
    if ($count > 0) {
        printf("  %-40s %6d 行\n", $table, $count);
    }
}
$empty = array_keys(array_filter($summary, static fn (int $count): bool => $count === 0));
if ($empty !== []) {
    echo '  （空表或未部署：' . implode(', ', $empty) . "）\n";
}
foreach ($report as $line) {
    echo "  · {$line}\n";
}
printf("  合计 %d 行 / %d 条语句\n", $totalRows, count($statements));

if ($dryRun) {
    echo "dry-run：未写入文件\n";
    exit(0);
}
file_put_contents(OUTPUT_FILE, $sql);
printf("已写入 %s（%.1f KB）\n", OUTPUT_FILE, strlen($sql) / 1024);
