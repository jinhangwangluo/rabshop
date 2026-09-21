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

namespace app\common\service\setting;

use app\common\model\setting\system\SensitiveWords;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;

/**
 * 敏感词服务类
 */
class SensitiveWordsService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    /**
     * 替换字符
     */
    const REPLACE_CHAR = '*';

    /**
     * 敏感词列表缓存键
     */
    const WORDS_CACHE_KEY = 'sensitive_words_list';

    /**
     * 敏感词树缓存键
     */
    const TREE_CACHE_KEY = 'sensitive_words_tree';

    protected function setModel(): string
    {
        return SensitiveWords::class;
    }

    /**
     * 获取所有启用的敏感词
     * @param bool $refresh 是否刷新缓存
     * @return array
     */
    public function getAllWords(bool $refresh = false): array
    {
        $cacheKey = self::WORDS_CACHE_KEY;

        if ($refresh || !$this->cacheTag()->has($cacheKey)) {
            $words = $this->getModel()
                ->where('status', 1)
                ->order('id', 'asc')
                ->column('word');

            $this->cacheTag()->set($cacheKey, $words);
            return $words;
        }

        return $this->cacheTag()->get($cacheKey);
    }

    /**
     * 构建敏感词前缀树
     * @param bool $refresh 是否刷新缓存
     * @return array
     */
    public function getWordTree(bool $refresh = false): array
    {
        $cacheKey = self::TREE_CACHE_KEY;

        if ($refresh || !$this->cacheTag()->has($cacheKey)) {
            $words = $this->getAllWords();
            $tree = $this->buildPrefixTree($words);

            $this->cacheTag()->set($cacheKey, $tree);
            return $tree;
        }

        return $this->cacheTag()->get($cacheKey);
    }

    /**
     * 构建前缀树
     * @param array $words 敏感词数组
     * @return array
     */
    private function buildPrefixTree(array $words): array
    {
        $tree = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word))
                continue;

            $node = &$tree;
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($chars as $char) {
                if (!isset($node[$char])) {
                    $node[$char] = [];
                }
                $node = &$node[$char];
            }
            // 标记单词结束
            $node['end'] = true;
        }

        return $tree;
    }

    /**
     * 检测文本中是否包含敏感词
     * @param string $content 待检测内容
     * @param bool $returnWords 是否返回匹配的敏感词
     * @return bool|array
     */
    public function checkContent(string $content, bool $returnWords = false)
    {
        if (empty($content)) {
            return $returnWords ? [] : false;
        }

        $tree = $this->getWordTree();
        $matches = [];
        $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);
        $length = count($chars);

        for ($i = 0; $i < $length; $i++) {
            $node = $tree;
            $j = $i;

            while ($j < $length && isset($node[$chars[$j]])) {
                $node = $node[$chars[$j]];

                // 如果是单词结尾，说明找到了敏感词
                if (isset($node['end'])) {
                    $word = implode('', array_slice($chars, $i, $j - $i + 1));
                    if (!in_array($word, $matches)) {
                        $matches[] = $word;
                    }
                }

                $j++;
            }
        }

        if ($returnWords) {
            return $matches;
        }

        return !empty($matches);
    }

    /**
     * 过滤敏感词
     * @param string $content 待过滤内容
     * @param string $replaceChar 替换字符
     * @param bool $logMatches 是否记录匹配的日志
     * @return string
     */
    public function filterContent(string $content, string $replaceChar = null, bool $logMatches = false): string
    {
        if (empty($content)) {
            return $content;
        }

        $replaceChar = $replaceChar ?? self::REPLACE_CHAR;
        $tree = $this->getWordTree();
        $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);
        $length = count($chars);
        $result = [];
        $matches = [];

        for ($i = 0; $i < $length; $i++) {
            $node = $tree;
            $j = $i;
            $matchLength = 0;

            // 查找从当前位置开始的最长匹配
            while ($j < $length && isset($node[$chars[$j]])) {
                $node = $node[$chars[$j]];

                if (isset($node['end'])) {
                    $matchLength = $j - $i + 1;
                }

                $j++;
            }

            if ($matchLength > 0) {
                // 记录匹配的敏感词
                $matchedWord = implode('', array_slice($chars, $i, $matchLength));
                $matches[] = $matchedWord;

                // 替换敏感词
                for ($k = 0; $k < $matchLength; $k++) {
                    $result[] = $replaceChar;
                }

                $i += $matchLength - 1; // 跳过已处理的字符
            } else {
                $result[] = $chars[$i];
            }
        }

        $filteredContent = implode('', $result);

        // 记录日志
        if ($logMatches && !empty($matches)) {
            Log::info('敏感词过滤', [
                'original' => $content,
                'filtered' => $filteredContent,
                'matches'  => $matches
            ]);
        }

        return $filteredContent;
    }

    /**
     * 批量过滤内容数组
     * @param array $contents 内容数组
     * @param string $replaceChar 替换字符
     * @return array
     */
    public function filterContents(array $contents, string $replaceChar = null): array
    {
        $result = [];
        foreach ($contents as $key => $content) {
            if (is_string($content)) {
                $result[$key] = $this->filterContent($content, $replaceChar);
            } else {
                $result[$key] = $content;
            }
        }
        return $result;
    }

    /**
     * 添加敏感词
     * @param string $word 敏感词
     * @param string $remark 备注
     * @param int $adminId 管理员ID
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function addWord(string $word, string $remark = '', int $adminId = 0): bool
    {
        if (empty($word)) {
            return false;
        }

        $word = trim($word);

        // 检查是否已存在
        $exists = $this->getModel()
            ->where('word', $word)
            ->find();

        if ($exists) {
            return false;
        }

        $data = [
            'word'     => $word,
            'remark'   => $remark,
            'admin_id' => $adminId,
            'status'   => 1
        ];

        $result = $this->getModel()->insert($data);

        if ($result) {
            // 清除缓存
            $this->clearCache();
            return true;
        }

        return false;
    }

    /**
     * 批量添加敏感词
     * @param array $words 敏感词数组
     * @param int $adminId 管理员ID
     * @return array 返回添加结果 ['success' => 成功数量, 'failed' => 失败数量]
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function batchAddWords(array $words, int $adminId = 0): array
    {
        $success = 0;
        $failed = 0;

        foreach ($words as $word) {
            if ($this->addWord($word, '', $adminId)) {
                $success++;
            } else {
                $failed++;
            }
        }

        if ($success > 0) {
            $this->clearCache();
        }

        return [
            'success' => $success,
            'failed'  => $failed
        ];
    }

    /**
     * 删除敏感词
     * @param int $id 敏感词ID
     * @return bool
     */
    public function deleteWord(int $id): bool
    {
        $this->getModel()->destroy($id);

        $this->clearCache();

        return true;
    }

    /**
     * 批量删除敏感词
     * @param array $ids 敏感词ID数组
     * @return bool
     */
    public function batchDeleteWords(array $ids): bool
    {
        $this->getModel()->destroy($ids);

        $this->clearCache();

        return true;
    }

    /**
     * 启用/禁用敏感词
     * @param int $id 敏感词ID
     * @param int $status 状态 1启用 0禁用
     * @return bool
     */
    public function setStatus(int $id, int $status): bool
    {
        $this->getModel()
            ->where('id', $id)
            ->update([
                'status'      => $status,
                'update_time' => date('Y-m-d H:i:s')
            ]);

        $this->clearCache();

        return true;
    }

    /**
     * 获取敏感词详情
     * @param int $id 敏感词ID
     * @return array|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getWordDetail(int $id): ?array
    {
        return $this->getModel()
            ->where('id', $id)
            ->find();
    }

    /**
     * 获取敏感词列表
     * @param array $where 查询条件
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getWordList(array $where = [], int $page = 1, int $limit = 10): array
    {
        $query = $this->getModel();

        // 添加查询条件
        if (!empty($where['word'])) {
            $query->where('word', 'like', '%' . $where['word'] . '%');
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', $where['status']);
        }

        $total = $query->count();
        $list = $query
            ->page($page, $limit)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        return [
            'list'       => $list,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'total_page' => ceil($total / $limit)
        ];
    }

    /**
     * 清除缓存
     * @return bool
     */
    public function clearCache(): bool
    {
        return $this->cacheTag()->clear();
    }

    /**
     * 导出敏感词
     * @param array $where 查询条件
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function exportWords(array $where = []): array
    {
        $query = $this->getModel();

        // 添加查询条件
        if (!empty($where['word'])) {
            $query->where('word', 'like', '%' . $where['word'] . '%');
        }

        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', $where['status']);
        }

        return $query
            ->field('word,remark,status,create_time')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 导入敏感词
     * @param array $data 敏感词数据 [['word' => '敏感词', 'remark' => '备注'], ...]
     * @param int $adminId 管理员ID
     * @return array ['success' => 成功数量, 'failed' => 失败数量, 'errors' => 错误信息]
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function importWords(array $data, int $adminId = 0): array
    {
        $success = 0;
        $failed  = 0;
        $errors  = [];

        // ① 预处理：校验格式、去重（文件内自身去重）
        $toInsert   = [];   // 待批量插入的词 => 行号映射（用于定位错误）
        $wordRowMap = [];   // word => 首次出现行号

        foreach ($data as $index => $item) {
            $word   = trim($item['word'] ?? '');
            $remark = trim($item['remark'] ?? '');
            $row    = $index + 1;

            if ($word === '') {
                $errors[] = "第{$row}行：敏感词不能为空";
                $failed++;
                continue;
            }

            // 文件内重复词只保留首次
            if (isset($wordRowMap[$word])) {
                $errors[] = "第{$row}行：与第{$wordRowMap[$word]}行重复，已跳过";
                $failed++;
                continue;
            }

            $wordRowMap[$word] = $row;
            $toInsert[$word]   = ['word' => $word, 'remark' => $remark, 'row' => $row];
        }

        if (empty($toInsert)) {
            return ['success' => 0, 'failed' => $failed, 'errors' => $errors];
        }

        // ② 用原生 SQL 查出所有已存在的词（含软删除），完全绕开模型作用域
        $tableName    = $this->getModel()->getTable();
        $wordList     = array_keys($toInsert);
        $placeholders = implode(',', array_fill(0, count($wordList), '?'));

        $existingRows = \think\facade\Db::query(
            "SELECT `word`, `delete_time` FROM `{$tableName}` WHERE `word` IN ({$placeholders})",
            $wordList
        );

        // 整理为 word => delete_time 的映射
        $existingMap = [];
        foreach ($existingRows as $row) {
            $existingMap[$row['word']] = $row['delete_time'];
        }

        // 从待插入集合中分离出已存在的词
        $existingSet  = array_flip(array_keys($existingMap));
        $duplicates   = array_intersect_key($toInsert, $existingSet);
        $toInsert     = array_diff_key($toInsert, $existingSet);

        // 软删除的词执行恢复，正常存在的记为重复跳过
        $now          = date('Y-m-d H:i:s');
        $restoreWords = [];

        foreach ($duplicates as $word => $item) {
            if ($existingMap[$word] !== null) {
                // delete_time 不为 null → 软删除记录，恢复它
                $restoreWords[] = $word;
            } else {
                $errors[] = "第{$item['row']}行：敏感词 '{$word}' 已存在，跳过";
                $failed++;
            }
        }

        // ③ 构造批量插入数据（$toInsert 以 word 为 key，天然无重复）
        $insertRows = array_values(array_map(function (array $item) use ($adminId, $now): array {
            return [
                'word'        => $item['word'],
                'remark'      => $item['remark'],
                'admin_id'    => $adminId,
                'status'      => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }, $toInsert));

        // ④ 执行批量插入 + 软删除词恢复（全部使用原生 SQL，绕开模型自动时间戳和作用域）
        $this->transaction(function () use ($insertRows, $restoreWords, $tableName, $now, $adminId) {
            // 批量插入新词：使用 INSERT IGNORE 防止唯一索引冲突（兜底保障）
            foreach (array_chunk($insertRows, 500) as $chunk) {
                if (empty($chunk)) {
                    continue;
                }
                $columns      = '(`word`, `remark`, `admin_id`, `status`, `create_time`, `update_time`)';
                $rowPlaceholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?)'));
                $bindings     = [];
                foreach ($chunk as $r) {
                    $bindings[] = $r['word'];
                    $bindings[] = $r['remark'];
                    $bindings[] = $r['admin_id'];
                    $bindings[] = $r['status'];
                    $bindings[] = $r['create_time'];
                    $bindings[] = $r['update_time'];
                }
                \think\facade\Db::execute(
                    "INSERT IGNORE INTO `{$tableName}` {$columns} VALUES {$rowPlaceholders}",
                    $bindings
                );
            }

            // 恢复软删除的词：直接用原生 SQL 更新，跳过模型作用域
            foreach (array_chunk($restoreWords, 500) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                \think\facade\Db::execute(
                    "UPDATE `{$tableName}` SET `delete_time` = NULL, `status` = 1, `update_time` = ?"
                    . " WHERE `word` IN ({$placeholders})",
                    array_merge([$now], $chunk)
                );
            }
        });

        $success = count($insertRows) + count($restoreWords);

        if ($success > 0) {
            $this->clearCache();
        }

        return [
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ];
    }

    /**
     * 解析 TXT 文件内容
     *
     * 格式：每行一个敏感词，支持 "词|备注" 竖线分隔
     *
     * @param string $content 文件内容
     * @return array [['word' => '', 'remark' => ''], ...]
     */
    public function parseTxt(string $content): array
    {
        $lines = explode("\n", $content);
        $data  = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // 支持 "词|备注" 格式
            if (str_contains($line, '|')) {
                [$word, $remark] = array_pad(explode('|', $line, 2), 2, '');
            } else {
                $word   = $line;
                $remark = '';
            }

            $word = trim($word);
            if ($word !== '') {
                $data[] = ['word' => $word, 'remark' => trim($remark)];
            }
        }

        return $data;
    }

    /**
     * 解析 CSV 文件内容
     *
     * 格式：第一列为敏感词，第二列（可选）为备注
     * 首行若为表头（含 word / 敏感词 等关键字）则自动跳过
     *
     * @param string $content 文件内容
     * @return array [['word' => '', 'remark' => ''], ...]
     */
    public function parseCsv(string $content): array
    {
        $lines = explode("\n", $content);
        $data  = [];
        $isFirstRow = true;

        // 表头关键词（匹配到则跳过该行）
        $headerKeywords = ['word', '敏感词', 'sensitive', '词语', 'keyword'];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // 使用 str_getcsv 解析，支持带引号的字段
            $columns = str_getcsv($line);

            $firstCell = strtolower(trim($columns[0] ?? ''));

            // 首行若为表头则跳过
            if ($isFirstRow) {
                $isFirstRow = false;
                if (in_array($firstCell, $headerKeywords, true)) {
                    continue;
                }
            }

            $word   = trim($columns[0] ?? '');
            $remark = trim($columns[1] ?? '');

            if ($word !== '') {
                $data[] = ['word' => $word, 'remark' => $remark];
            }
        }

        return $data;
    }

    /**
     * 检测文本命中的敏感词列表（供 ContentAuditService 等外部调用）
     *
     * @param string $text 待检测文本
     * @return array 命中的敏感词数组
     */
    public function detect(string $text): array
    {
        if (empty($text)) {
            return [];
        }
        $result = $this->checkContent($text, true);
        return is_array($result) ? $result : [];
    }
}
