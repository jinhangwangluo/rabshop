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

$upgradeFile = __DIR__ . '/upgrade.sql';
$lines = file($upgradeFile, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Failed to read upgrade.sql\n");
    exit(1);
}

$outputMap = [
    'agreement' => 'agreement_seed.sql',
    'city_area' => 'city_area_seed.sql',
    'combination_data' => 'combination_data_seed.sql',
    'combination_data_config' => 'combination_data_config_seed.sql',
    'diy_page' => 'diy_page_seed.sql',
    'menus' => 'menus_seed.sql',
    'message_push_template' => 'message_push_template_seed.sql',
    'sensitive_words' => 'sensitive_words_seed.sql',
];

$excludeOnly = [
    'diy_page_link' => true,
];

$markers = [];
foreach ($lines as $index => $line) {
    if (preg_match('/^-- 转存表中的数据 `lf_([^`]+)`$/', trim($line), $matches)) {
        $markers[] = [
            'line' => $index + 1,
            'table' => $matches[1],
        ];
    }
}

if ($markers === []) {
    fwrite(STDERR, "No seed markers found\n");
    exit(1);
}

$keepMask = array_fill(0, count($lines), true);

foreach ($markers as $position => $marker) {
    $start = $marker['line'];
    $end = isset($markers[$position + 1]) ? $markers[$position + 1]['line'] - 1 : count($lines);
    $table = $marker['table'];

    if (!isset($outputMap[$table]) && !isset($excludeOnly[$table])) {
        continue;
    }

    for ($i = $start - 1; $i < $end; $i++) {
        $keepMask[$i] = false;
    }

    if (isset($outputMap[$table])) {
        $chunk = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
        $chunk = preg_replace('/^INSERT\s+INTO\s+/m', 'INSERT IGNORE INTO ', $chunk);
        $chunk = preg_replace('/`(?:ll|lf)_/', '`lf_', $chunk);
        $seedFile = __DIR__ . '/' . $outputMap[$table];
        file_put_contents($seedFile, trim($chunk) . "\n");
    }
}

$remaining = [];
foreach ($lines as $index => $line) {
    if ($keepMask[$index]) {
        $remaining[] = $line;
    }
}

file_put_contents($upgradeFile, implode("\n", $remaining) . "\n");

