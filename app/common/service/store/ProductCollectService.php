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

namespace app\common\service\store;

use app\common\enum\ProductEnum;
use app\common\exception\AdminResponseException;
use app\common\job\store\ProductBatchCollectJob;
use app\common\job\store\ProductBatchPublishJob;
use app\common\model\store\ProductCollectTask;
use app\common\model\store\ProductLibrary;
use app\common\service\BaseService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\file\UploadedFile;
use think\facade\Log;
use think\Response;
use think\response\File;

/**
 * 商品批量采集任务服务。
 *
 * Excel 导入采集链接 → 建任务并逐条入队 → 队列内复用
 * ProductLibraryService::collectFromExternal（含按 SKU 拆分）→
 * 补全分类/品牌后批量上架（按采集规格生成自定义规格店铺商品，不选中商品库）。
 */
class ProductCollectService extends BaseService
{
    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_COMPLETED = 2;
    public const STATUS_FAILED = 3;

    public const PUBLISH_NONE = 0;
    public const PUBLISH_PROCESSING = 1;
    public const PUBLISH_PUBLISHED = 2;
    public const PUBLISH_FAILED = 3;

    /** 单次导入链接上限 */
    private const MAX_IMPORT_ROWS = 1000;
    /** 上传文件大小上限 */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    /** 允许的表格扩展名 */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return ProductCollectTask::class;
    }

    /**
     * 导入表格并批量创建采集任务。
     *
     * @param UploadedFile $file 上传的表格文件
     * @param int $adminId 操作管理员ID
     * @return array{total:int, queued_count:int, duplicate_count:int, invalid_count:int, errors:array}
     * @throws AdminResponseException 文件不合法时抛出
     */
    public function importFromFile(UploadedFile $file, int $adminId = 0): array
    {
        $extension = strtolower($file->getOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new AdminResponseException('仅支持 xlsx/xls/csv 格式表格');
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new AdminResponseException('表格文件不能超过 5MB');
        }

        $links = $this->parseLinksFromFile($file->getPathname());
        if ($links['links'] === []) {
            throw new AdminResponseException('表格中未解析到有效商品链接，请参考模板填写');
        }
        if (count($links['links']) > self::MAX_IMPORT_ROWS) {
            throw new AdminResponseException('单次最多导入 ' . self::MAX_IMPORT_ROWS . ' 条链接');
        }

        $invalidCount = (int)$links['invalid'];
        $links = $links['links'];
        // 表内去重 + 与存量任务去重（未删除的全部状态都视为已存在）
        $unique = [];
        foreach ($links as $link) {
            $unique[$link] = true;
        }
        $exists = $this->getModel()
            ->whereIn('source_url', array_keys($unique))
            ->column('source_url');
        $duplicates = count($links) - count($unique) + count(array_intersect($exists, array_keys($unique)));

        $batchNo = 'BC' . date('YmdHis') . mt_rand(100, 999);
        $now = date('Y-m-d H:i:s');
        $queuedIds = [];
        foreach (array_keys($unique) as $link) {
            if (in_array($link, $exists, true)) {
                continue;
            }
            /** @var ProductCollectTask $task */
            $task = $this->create([
                'batch_no' => $batchNo,
                'source_url' => $link,
                'status' => self::STATUS_PENDING,
                'library_product_ids' => '[]',
                'missing_fields' => '',
                'publish_status' => self::PUBLISH_NONE,
                'error_msg' => '',
                'admin_id' => $adminId,
            ]);
            $queuedIds[] = (int)$task->id;
        }

        // 队列一条条执行，插入逻辑与单个采集完全一致
        foreach ($queuedIds as $taskId) {
            ProductBatchCollectJob::dispatch($taskId);
        }

        return [
            'total' => count($links),
            'queued_count' => count($queuedIds),
            'duplicate_count' => $duplicates,
            'invalid_count' => $invalidCount,
            'batch_no' => $batchNo,
            'errors' => [],
        ];
    }

    /**
     * 从表格文件解析商品链接：只认每行第一列，首个非空行若不是合法链接按表头跳过。
     *
     * @return array{links: string[], invalid: int} 有效链接与无效行数
     */
    protected function parseLinksFromFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable) {
            throw new AdminResponseException('表格文件解析失败，请使用下载的模板填写');
        }

        $links = [];
        $invalid = 0;
        $headerSkipped = false;
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, true);
            foreach ($rows as $row) {
                $firstCell = '';
                foreach ($row as $cell) {
                    $text = trim(strip_tags((string)$cell));
                    if ($text !== '') {
                        $firstCell = $text;
                        break;
                    }
                }
                if ($firstCell === '') {
                    continue;
                }

                $link = $this->normalizeCollectUrl($firstCell);
                if ($link === null) {
                    continue;
                }
                if ($link === '') {
                    // 首个非空行不是合法链接 → 视为表头跳过；其余计为无效行
                    if (!$headerSkipped) {
                        $headerSkipped = true;
                        continue;
                    }
                    $invalid++;
                    continue;
                }
                $links[] = $link;
            }
        }

        return ['links' => $links, 'invalid' => $invalid];
    }

    /**
     * 归一化采集链接：http(s) 链接或淘宝纯数字商品 ID。
     *
     * @param string $text 单元格文本
     * @return string|null 合法链接；空文本返回 null（跳过整行）；非法内容返回 ''（无效行）
     */
    protected function normalizeCollectUrl(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $text)) {
            return mb_substr($text, 0, 500);
        }
        // 纯数字按淘宝商品 ID 处理（OneBound 驱动约定）
        if (preg_match('/^\d{6,20}$/', $text)) {
            return $text;
        }
        return '';
    }

    /**
     * 任务列表。
     */
    public function getTaskList(array $params = []): array
    {
        // $params 只含业务筛选字段，分页固定从当前请求读取，避免因数组非空而取不到 page/limit 恒返回第一页
        [$page, $limit] = $this->getPagesValue(true, true, [], 50);
        $where = [
            'status' => $params['status'] ?? '',
            'publish_status' => $params['publish_status'] ?? '',
            'batch_no' => $params['batch_no'] ?? '',
            'keyword' => $params['keyword'] ?? '',
        ];

        $query = $this->getModel()->searchWhere($where)->order('id desc');
        $total = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => array_map(fn(array $item): array => $this->formatTask($item), $list),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPage' => $total && $limit ? (int)ceil($total / $limit) : 0,
        ];
    }

    /**
     * 队列执行单个采集任务（逻辑与单个采集完全一致，复用 collectFromExternal）。
     */
    public function processTask(int $id): bool
    {
        $task = $this->mustGetTask($id);
        if (!in_array((int)$task->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)) {
            return true;
        }

        $task->save([
            'status' => self::STATUS_PROCESSING,
            'started_time' => date('Y-m-d H:i:s'),
            'error_msg' => '',
        ]);

        try {
            $libraryService = app()->make(ProductLibraryService::class);
            $data = $libraryService->collectFromExternal((string)$task->source_url);

            $libraryIds = $this->extractLibraryIds($data);
            if ($libraryIds === []) {
                throw new AdminResponseException('采集结果未生成库商品');
            }

            [$complete, $missing] = $this->judgeCompleteness($libraryIds);

            $task->save([
                'status' => self::STATUS_COMPLETED,
                'platform' => $this->resolvePlatform((string)$task->source_url),
                'title' => mb_substr((string)($data['name'] ?? ''), 0, 200),
                'library_product_ids' => json_encode($libraryIds, JSON_UNESCAPED_UNICODE),
                'library_count' => count($libraryIds),
                'is_complete' => $complete ? 1 : 0,
                'missing_fields' => $missing,
                'finished_time' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            // 采集业务失败（链接不支持/商品无效/Key 未配等）不做框架级重试，落状态供手动重试
            $task->save([
                'status' => self::STATUS_FAILED,
                'error_msg' => mb_substr($exception->getMessage(), 0, 500),
                'finished_time' => date('Y-m-d H:i:s'),
            ]);
            Log::warning('批量采集任务失败', ['task_id' => $id, 'error' => $exception->getMessage()]);
        }

        return true;
    }

    /**
     * 批量补全任务的库商品信息（分类/品牌/运费模板）。
     *
     * @param array $taskIds 任务ID列表
     * @param array $data category_id/brand_id 必传（>0），freight_template_id 可选
     * @param int $adminId 操作管理员ID
     * @return array{updated:int, tasks:int}
     * @throws AdminResponseException 参数不合法时抛出
     */
    public function completeTasks(array $taskIds, array $data, int $adminId = 0): array
    {
        $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds))));
        if ($taskIds === []) {
            throw new AdminResponseException('请选择要补全的任务');
        }

        $categoryId = (int)($data['category_id'] ?? 0);
        $brandId = (int)($data['brand_id'] ?? 0);
        $freightTemplateId = (int)($data['freight_template_id'] ?? 0);
        if ($categoryId <= 0 || $brandId <= 0) {
            throw new AdminResponseException('请选择平台分类和商品品牌');
        }
        if ($freightTemplateId < 0) {
            throw new AdminResponseException('运费模板参数不合法');
        }

        $libraryService = app()->make(ProductLibraryService::class);
        $updateFields = array_filter([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'freight_template_id' => $freightTemplateId,
        ], static fn($value): bool => $value > 0);

        $updated = 0;
        $touchedTasks = 0;
        foreach ($taskIds as $taskId) {
            $task = $this->mustGetTask($taskId);
            if ((int)$task->publish_status === self::PUBLISH_PUBLISHED) {
                continue;
            }
            $libraryIds = $this->decodeLibraryIds((string)$task->library_product_ids);
            foreach ($libraryIds as $libraryId) {
                $libraryService->resourceUpdate($libraryId, $updateFields, 0);
                $updated++;
            }

            [$complete, $missing] = $libraryIds === []
                ? [false, '未关联库商品']
                : $this->judgeCompleteness($libraryIds);
            $task->save([
                'is_complete' => $complete ? 1 : 0,
                'missing_fields' => $missing,
            ]);
            $touchedTasks++;
        }
        unset($adminId);

        return ['updated' => $updated, 'tasks' => $touchedTasks];
    }

    /**
     * 批量上架：校验通过后逐条入队（队列一个个插入店铺商品）。
     *
     * @return array{queued_count:int, skipped_count:int, errors:array}
     */
    public function batchPublish(array $taskIds, int $adminId = 0): array
    {
        $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds))));
        if ($taskIds === []) {
            throw new AdminResponseException('请选择要上架的任务');
        }

        $queued = 0;
        $skipped = 0;
        $errors = [];
        foreach ($taskIds as $taskId) {
            $task = $this->mustGetTask($taskId);
            if ((int)$task->status !== self::STATUS_COMPLETED) {
                $skipped++;
                $errors[] = "任务#{$taskId} 采集未完成";
                continue;
            }
            if ((int)$task->is_complete !== 1) {
                $skipped++;
                $errors[] = "任务#{$taskId} 数据缺少：" . ($task->missing_fields ?: '未知');
                continue;
            }
            if (in_array((int)$task->publish_status, [self::PUBLISH_PROCESSING, self::PUBLISH_PUBLISHED], true)) {
                $skipped++;
                continue;
            }

            $task->save([
                'publish_status' => self::PUBLISH_PROCESSING,
                'publish_error' => '',
            ]);
            ProductBatchPublishJob::dispatch((int)$task->id);
            $queued++;
        }
        unset($adminId);

        return ['queued_count' => $queued, 'skipped_count' => $skipped, 'errors' => $errors];
    }

    /**
     * 队列执行单个上架任务。
     *
     * 库商品仍是采集拆分后的单规格池，但店铺商品按采集到的尺码/颜色写成自定义规格，
     * 不再调用 assembleFromLibrary，避免编辑页选中这些库商品。
     */
    public function processPublish(int $id): bool
    {
        $task = $this->mustGetTask($id);
        if ((int)$task->publish_status !== self::PUBLISH_PROCESSING) {
            return true;
        }

        try {
            $libraryIds = $this->decodeLibraryIds((string)$task->library_product_ids);
            if ($libraryIds === []) {
                throw new AdminResponseException('任务未关联库商品');
            }

            $libraryService = app()->make(ProductLibraryService::class);
            $libraryMap = $libraryService->getAvailableMapByIds($libraryIds, 0);
            $libraries = [];
            foreach ($libraryIds as $libraryId) {
                if (!isset($libraryMap[$libraryId])) {
                    throw new AdminResponseException('采集库商品不存在：' . $libraryId);
                }
                $libraries[] = $libraryMap[$libraryId];
            }
            $first = $libraries[0];

            $storeProductService = app()->make(StoreProductService::class);
            /** @var \think\Model $product */
            $product = $storeProductService->publishCollectedLibraries(0, [
                'name' => (string)($task->title ?: $first['name']),
                'category_id' => (int)$first['category_id'],
                'brand_id' => (int)$first['brand_id'],
                'freight_template_id' => (int)($first['freight_template_id'] ?? 0),
                'goods_type' => (int)($first['goods_type'] ?? ProductEnum::GOODS_TYPE_PHYSICAL),
            ], $libraries);

            $task->save([
                'publish_status' => self::PUBLISH_PUBLISHED,
                'store_product_id' => (int)$product->id,
                'publish_error' => '',
            ]);
        } catch (\Throwable $exception) {
            $task->save([
                'publish_status' => self::PUBLISH_FAILED,
                'publish_error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
            Log::warning('批量采集上架失败', ['task_id' => $id, 'error' => $exception->getMessage()]);
        }

        return true;
    }

    /**
     * 重试采集任务（仅失败任务）。
     */
    public function retryTask(int $id): array
    {
        $task = $this->mustGetTask($id);
        if ((int)$task->status !== self::STATUS_FAILED) {
            throw new AdminResponseException('仅采集失败的任务可以重试');
        }

        $task->save([
            'status' => self::STATUS_PENDING,
            'error_msg' => '',
            'started_time' => null,
            'finished_time' => null,
        ]);
        ProductBatchCollectJob::dispatch($id);

        return $this->formatTask($task->refresh()->toArray());
    }

    /**
     * 批量重试全部采集失败的任务：状态统一重置为待采集，
     * 队列按序号逐秒延迟投递（第 N 条延迟 N 秒），避免瞬时并发请求采集源被限流。
     *
     * @return array{total:int, queued_count:int, queued_ids:int[]}
     */
    public function retryFailedTasks(): array
    {
        // 按 ID 升序保证延迟序号与任务列表顺序一致
        $taskIds = array_map(
            static fn ($id): int => (int)$id,
            $this->getModel()->where('status', self::STATUS_FAILED)->order('id asc')->column('id')
        );
        if ($taskIds === []) {
            return ['total' => 0, 'queued_count' => 0, 'queued_ids' => []];
        }

        // 先统一重置状态再入队：延迟执行的 Job 只处理待采集/采集中状态的任务
        $this->getModel()->where('status', self::STATUS_FAILED)->update([
            'status' => self::STATUS_PENDING,
            'error_msg' => '',
            'started_time' => null,
            'finished_time' => null,
        ]);

        foreach ($taskIds as $index => $taskId) {
            // 序号从 0 开始，延迟 1 秒、2 秒、3 秒……依次错峰
            ProductBatchCollectJob::dispatchSecs($index + 1, $taskId);
        }

        return [
            'total' => count($taskIds),
            'queued_count' => count($taskIds),
            'queued_ids' => $taskIds,
        ];
    }

    /**
     * 删除任务（软删任务行，不动已生成的库商品）。
     */
    public function deleteTask(int $id): bool
    {
        $task = $this->mustGetTask($id);
        if ((int)$task->publish_status === self::PUBLISH_PROCESSING) {
            throw new AdminResponseException('任务正在上架中，无法删除');
        }

        return (bool)$this->getModel()->destroy($id);
    }

    /**
     * 批量删除采集任务：上架中或已不存在的任务跳过，其余批量删除（不动已生成的库商品）。
     *
     * @param array $taskIds 任务ID列表
     * @return array{total:int, deleted_count:int, skipped_ids:int[]}
     */
    public function deleteTasks(array $taskIds): array
    {
        // 去重并过滤非正整数，防止外部传入非法ID
        $taskIds = array_values(array_unique(array_filter(
            array_map('intval', $taskIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($taskIds === []) {
            return ['total' => 0, 'deleted_count' => 0, 'skipped_ids' => []];
        }

        // 一次查出待删任务的上架状态，逐条判断可删性，保持与单条删除相同的约束
        $publishStatusMap = $this->getModel()
            ->whereIn('id', $taskIds)
            ->column('publish_status', 'id');
        $skippedIds = [];
        $deletableIds = [];
        foreach ($taskIds as $taskId) {
            if (!isset($publishStatusMap[$taskId]) || (int)$publishStatusMap[$taskId] === self::PUBLISH_PROCESSING) {
                $skippedIds[] = $taskId;
            } else {
                $deletableIds[] = $taskId;
            }
        }

        if ($deletableIds !== []) {
            $this->getModel()->destroy($deletableIds);
        }

        return [
            'total' => count($taskIds),
            'deleted_count' => count($deletableIds),
            'skipped_ids' => $skippedIds,
        ];
    }

    /**
     * 生成导入模板（单列「商品链接」+ 示例行），xlsx 流式下载。
     */
    public function templateDownload(): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('批量采集');
        $sheet->setCellValue('A1', '商品链接');
        $sheet->setCellValue('A2', 'https://item.taobao.com/item.htm?id=123456789');
        $sheet->setCellValue('A3', 'https://item.jd.com/100012043978.html');
        $sheet->getColumnDimension('A')->setWidth(60);

        $writer = new Xlsx($spreadsheet);
        $path = tempnam(sys_get_temp_dir(), 'collect_tpl_') . '.xlsx';
        $writer->save($path);
        $content = (string)file_get_contents($path);
        @unlink($path);

        $response = Response::create($content, File::class);
        $response->isContent(true)->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->name('批量采集导入模板.xlsx', false);
        return $response;
    }

    /**
     * 解析采集结果中的库商品ID列表（兼容多 SKU 拆分与单 SKU 两种返回）。
     */
    private function extractLibraryIds(array $data): array
    {
        $ids = [];
        $skus = $data['collected_skus'] ?? [];
        if (is_array($skus) && $skus !== []) {
            foreach ($skus as $sku) {
                $id = (int)($sku['library_product_id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        if ($ids === [] && (int)($data['library_product_id'] ?? 0) > 0) {
            $ids[] = (int)$data['library_product_id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * 校验库商品行上架完整性：name 非空 + 分类/品牌已选。
     *
     * @return array{0:bool, 1:string} 是否齐全、缺失字段说明
     */
    private function judgeCompleteness(array $libraryIds): array
    {
        $rows = app()->make(ProductLibrary::class)
            ->whereIn('id', $libraryIds)
            ->column('name, category_id, brand_id', 'id');
        if (count($rows) < count(array_unique($libraryIds))) {
            return [false, '存在缺失的库商品行'];
        }

        $missing = [];
        foreach ($rows as $row) {
            if (trim((string)$row['name']) === '') {
                $missing[] = '商品名称';
            }
            if ((int)$row['category_id'] <= 0) {
                $missing[] = '商品分类';
            }
            if ((int)$row['brand_id'] <= 0) {
                $missing[] = '商品品牌';
            }
        }
        $missing = array_values(array_unique($missing));

        return [$missing === [], implode('、', $missing)];
    }

    /**
     * 从 URL 解析平台标识（失败返回空串，不阻断流程）。
     */
    private function resolvePlatform(string $url): string
    {
        try {
            return (string)app()->make(\rabbit\service\collector\CollectorService::class)->getPlatformByUrl($url);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * 解码任务关联的库商品ID数组。
     */
    private function decodeLibraryIds(string $json): array
    {
        $ids = json_decode($json, true);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * 获取任务，不存在时抛出。
     */
    private function mustGetTask(int $id): ProductCollectTask
    {
        /** @var ProductCollectTask $task */
        $task = $this->getModel()->where('id', $id)->find();
        if (!$task) {
            throw new AdminResponseException('采集任务不存在');
        }

        return $task;
    }

    /**
     * 格式化任务行（库商品ID 解码为数组）。
     */
    private function formatTask(array $item): array
    {
        $item['library_product_ids'] = $this->decodeLibraryIds((string)($item['library_product_ids'] ?? ''));

        return $item;
    }
}
