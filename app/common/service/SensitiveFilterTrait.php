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

namespace app\common\service;

use app\common\service\setting\SensitiveWordsService;

/**
 * 敏感词过滤 Trait
 * 供各业务 Service 使用，在保存/更新数据前对指定文字字段执行敏感词替换
 *
 * 使用方式：
 *   use SensitiveFilterTrait;
 *
 *   // 在写入前过滤
 *   $data = $this->filterSensitiveFields($data, ['title', 'description', 'content']);
 */
trait SensitiveFilterTrait
{
    /**
     * 对数组中的指定字段执行敏感词过滤（用 * 替换敏感词）
     *
     * @param array         $data   数据数组
     * @param array<string> $fields 需要过滤的字段名列表
     * @return array        过滤后的数据数组
     */
    protected function filterSensitiveFields(array $data, array $fields): array
    {
        if (empty($fields) || empty($data)) {
            return $data;
        }

        try {
            /** @var SensitiveWordsService $sensitiveService */
            $sensitiveService = app()->make(SensitiveWordsService::class);
        } catch (\Throwable) {
            // 服务不可用时不阻断写入，直接返回原数据
            return $data;
        }

        foreach ($fields as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                continue;
            }
            $data[$field] = $sensitiveService->filterContent($data[$field]);
        }

        return $data;
    }

    /**
     * 对单个字符串执行敏感词过滤
     *
     * @param string $text 待过滤文本
     * @return string
     */
    protected function filterSensitiveText(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        try {
            /** @var SensitiveWordsService $sensitiveService */
            $sensitiveService = app()->make(SensitiveWordsService::class);
            return $sensitiveService->filterContent($text);
        } catch (\Throwable) {
            return $text;
        }
    }
}
