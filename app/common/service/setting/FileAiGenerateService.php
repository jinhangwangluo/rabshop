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

namespace app\common\service\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\exception\AdminResponseException;
use app\common\job\setting\FileAiGenerateJob;
use app\common\model\setting\system\FileAiGenerationTask;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;
use app\common\service\cs\AiImageGeneratorService;
use app\common\service\cs\AiModelConfigService;
use app\common\util\FileTypeService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Rabbit\service\uploader\UploaderService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;

/**
 * 文件库 AI 生图任务服务。
 */
class FileAiGenerateService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_COMPLETED = 2;
    public const STATUS_FAILED = 3;
    public const STATUS_CANCELED = 4;

    private const AI_CATEGORY_NAME = 'AI创作';
    private const STORAGE_DIRECTORY = 'file/ai_create';
    private const MAX_COUNT = 4;

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return FileAiGenerationTask::class;
    }

    /**
     * 创建任务并投递队列。
     */
    public function createTask(array $data, int $adminId = 0, int $merchantId = 0): array
    {
        $merchantId = max(0, $merchantId);
        $payload = $this->normalizeTaskPayload($data);
        $runtime = $this->resolveRuntime($merchantId);
        $now = date('Y-m-d H:i:s');

        /** @var FileAiGenerationTask $task */
        $task = $this->create([
            'title' => $payload['title'],
            'prompt' => $payload['prompt'],
            'negative_prompt' => $payload['negative_prompt'],
            'style' => $payload['style'],
            'size' => $payload['size'],
            'ratio' => $payload['ratio'],
            'count' => $payload['count'],
            'quality' => $payload['quality'],
            'output_format' => $payload['output_format'],
            'output_compression' => $payload['output_compression'],
            'seed' => $payload['seed'],
            'random_seed' => $payload['random_seed'],
            'high_quality' => $payload['high_quality'],
            'reference_file_id' => $payload['reference_file_id'],
            'model' => $runtime['model_id'],
            'provider' => $runtime['provider'],
            'params' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => self::STATUS_PENDING,
            'progress' => 0,
            'file_ids' => '[]',
            'result_images' => '[]',
            'fail_reason' => '',
            'merchant_id' => $merchantId,
            'admin_id' => $adminId,
            'create_time' => $now,
            'update_time' => $now,
        ]);

        FileAiGenerateJob::dispatch((int)$task->id);

        return $this->formatTask($task);
    }

    /**
     * 获取任务列表。
     *
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getTaskList(array $params = [], int $merchantId = 0): array
    {
        $merchantId = max(0, $merchantId);
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(50, (int)($params['limit'] ?? 10)));
        $where = [
            'status' => $params['status'] ?? '',
            'keyword' => $params['keyword'] ?? '',
        ];

        $query = $this->getModel()->searchWhere($where)->where('merchant_id', $merchantId)->order('id desc');
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
     * 获取任务详情。
     */
    public function getTaskDetail(int $id, int $merchantId = 0): array
    {
        return $this->formatTask($this->mustGetTask($id, max(0, $merchantId)));
    }

    /**
     * 取消任务。
     */
    public function cancelTask(int $id, int $merchantId = 0): array
    {
        $task = $this->mustGetTask($id, max(0, $merchantId));
        if (in_array((int)$task->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)) {
            throw new AdminResponseException('当前任务状态不允许取消');
        }

        $task->save([
            'status' => self::STATUS_CANCELED,
            'progress' => 0,
            'finished_time' => date('Y-m-d H:i:s'),
        ]);

        return $this->formatTask($task->refresh());
    }

    /**
     * 重试任务。
     */
    public function retryTask(int $id, int $merchantId = 0): array
    {
        $merchantId = max(0, $merchantId);
        $task = $this->mustGetTask($id, $merchantId);
        if (!in_array((int)$task->status, [self::STATUS_FAILED, self::STATUS_CANCELED], true)) {
            throw new AdminResponseException('仅失败或已取消任务可以重试');
        }

        $runtime = $this->resolveRuntime($merchantId);
        $task->save([
            'status' => self::STATUS_PENDING,
            'progress' => 0,
            'fail_reason' => '',
            'file_ids' => '[]',
            'result_images' => '[]',
            'model' => $runtime['model_id'],
            'provider' => $runtime['provider'],
            'started_time' => null,
            'finished_time' => null,
        ]);

        FileAiGenerateJob::dispatch((int)$task->id);

        return $this->formatTask($task->refresh());
    }

    /**
     * 队列执行单个任务。
     */
    public function processTask(int $id): bool
    {
        $task = $this->mustGetTask($id);
        if (!in_array((int)$task->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)) {
            return true;
        }

        $merchantId = (int)($task->merchant_id ?? 0);
        $referencePaths = [];

        $task->save([
            'status' => self::STATUS_PROCESSING,
            'progress' => 10,
            'started_time' => date('Y-m-d H:i:s'),
            'fail_reason' => '',
        ]);

        try {
            $params = $this->decodeJsonArray($task->params);
            $runtime = $this->resolveRuntime($merchantId, (string)$task->model);
            $params['directory'] = $this->buildStorageDirectory($merchantId);
            $params['count'] = (int)$task->count;
            $params['use_uploader'] = true;
            $params['merchant_id'] = $merchantId;
            $referencePaths = $this->resolveReferencePaths((int)$task->reference_file_id, $merchantId);
            $params['reference_paths'] = $referencePaths;

            /** @var AiImageGeneratorService $generator */
            $generator = app()->make(AiImageGeneratorService::class);
            $images = $generator->generateImages((string)$task->prompt, $params, $runtime);
            if ($images === []) {
                throw new AdminResponseException('AI 图片接口没有返回可保存的图片');
            }

            $current = $this->mustGetTask($id);
            if ((int)$current->status === self::STATUS_CANCELED) {
                return true;
            }

            $categoryId = $this->ensureAiCategoryId($merchantId);
            $fileIds = [];
            $resultImages = [];
            /** @var FileService $fileService */
            $fileService = app()->make(FileService::class);

            foreach ($images as $index => $image) {
                $realName = $this->buildGeneratedRealName((string)$task->title, (string)($image['extension'] ?? 'png'), $index + 1);
                $fileData = [
                    'path' => (string)$image['url'],
                    'name' => (string)$image['name'],
                    'real_name' => $realName,
                    'file_size' => (string)($image['size'] ?? ''),
                    'file_type' => FileTypeService::TYPE_IMAGE,
                    'cate_id' => $categoryId,
                    'update_type' => $this->normalizeUploaderType((string)($image['update_type'] ?? UploaderService::LOCAL_UPLOADER)),
                ];
                $file = $merchantId > 0
                    ? $fileService->saveWithMerchant($fileData, $merchantId)
                    : $fileService->create($fileData + ['merchant_id' => 0]);

                $fileIds[] = (int)$file->id;
                $resultImages[] = [
                    'file_id' => (int)$file->id,
                    'path' => (string)$image['url'],
                    'name' => $realName,
                    'size' => (string)($image['size'] ?? ''),
                ];
            }

            $current->save([
                'status' => self::STATUS_COMPLETED,
                'progress' => 100,
                'file_ids' => json_encode($fileIds, JSON_UNESCAPED_UNICODE),
                'result_images' => json_encode($resultImages, JSON_UNESCAPED_UNICODE),
                'finished_time' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable $exception) {
            $latest = $this->mustGetTask($id);
            if ((int)$latest->status === self::STATUS_CANCELED) {
                return true;
            }

            $failReason = $this->formatFailureReason($exception);
            $latest->save([
                'status' => self::STATUS_FAILED,
                'progress' => 0,
                'fail_reason' => $failReason,
                'finished_time' => date('Y-m-d H:i:s'),
            ]);
            Log::error('文件库 AI 生图任务失败', [
                'task_id' => $id,
                'merchant_id' => $merchantId,
                'fail_reason' => $failReason,
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return false;
        } finally {
            $this->cleanupTempReferencePaths($referencePaths);
        }
    }

    /**
     * 规范化任务参数。
     */
    private function normalizeTaskPayload(array $data): array
    {
        $prompt = trim((string)($data['prompt'] ?? ''));
        if ($prompt === '') {
            throw new AdminResponseException('提示词不能为空');
        }

        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr($prompt, 0, 30);
        }

        $count = max(1, min(self::MAX_COUNT, (int)($data['count'] ?? self::MAX_COUNT)));
        $quality = $this->normalizeQuality((string)($data['quality'] ?? 'auto'), (int)($data['high_quality'] ?? 0) === 1);
        $outputFormat = $this->normalizeOutputFormat((string)($data['output_format'] ?? 'png'));

        return [
            'title' => mb_substr($title, 0, 100),
            'prompt' => $prompt,
            'negative_prompt' => trim((string)($data['negative_prompt'] ?? '')),
            'style' => trim((string)($data['style'] ?? '')),
            'size' => $this->normalizeSize((string)($data['size'] ?? '1024x1024')),
            'ratio' => trim((string)($data['ratio'] ?? '1:1')) ?: '1:1',
            'count' => $count,
            'quality' => $quality,
            'output_format' => $outputFormat,
            'output_compression' => max(0, min(100, (int)($data['output_compression'] ?? 100))),
            'seed' => trim((string)($data['seed'] ?? '')),
            'random_seed' => (int)($data['random_seed'] ?? 1) === 1 ? 1 : 0,
            'high_quality' => (int)($data['high_quality'] ?? 0) === 1 ? 1 : 0,
            'reference_file_id' => max(0, (int)($data['reference_file_id'] ?? 0)),
        ];
    }

    /**
     * 获取默认图片模型运行时。
     */
    private function resolveRuntime(int $merchantId = 0, string $modelId = ''): array
    {
        /** @var AiModelConfigService $configService */
        $configService = app()->make(AiModelConfigService::class);
        $runtime = $configService->resolveRuntime(AiModelConfigService::TYPE_IMAGE, max(0, $merchantId), trim($modelId));
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['model_id'])) {
            if (trim($modelId) !== '') {
                throw new AdminResponseException('当前任务绑定的 AI 图片模型不存在或已禁用');
            }
            throw new AdminResponseException($merchantId > 0 ? '请先配置当前商户默认启用的 AI 图片模型' : '请先配置默认启用的 AI 图片模型');
        }

        if (strtolower((string)$runtime['model_id']) === 'gpt-image2') {
            $runtime['model_id'] = 'gpt-image-2';
        }

        return $runtime;
    }

    /**
     * 获取任务，不存在则抛业务异常。
     */
    private function mustGetTask(int $id, ?int $merchantId = null): FileAiGenerationTask
    {
        $query = $this->getModel()->where('id', $id);
        if ($merchantId !== null) {
            $query->where('merchant_id', max(0, $merchantId));
        }
        $task = $query->find();
        if (!$task) {
            throw new AdminResponseException('任务不存在');
        }

        return $task;
    }

    /**
     * 确保 AI 创作分类存在。
     */
    private function ensureAiCategoryId(int $merchantId = 0): int
    {
        $merchantId = max(0, $merchantId);
        /** @var CategoryService $categoryService */
        $categoryService = app()->make(CategoryService::class);
        $category = $categoryService->getModel()
            ->where('type', CategoryService::FILE_TYPE)
            ->where('owner_id', $merchantId)
            ->where('pid', 0)
            ->where('name', self::AI_CATEGORY_NAME)
            ->find();

        if ($category) {
            return (int)$category->id;
        }

        $created = $categoryService->create([
            'pid' => 0,
            'owner_id' => $merchantId,
            'name' => self::AI_CATEGORY_NAME,
            'path' => '',
            'image' => '',
            'description' => $merchantId > 0 ? '商户AI生成图片自动归档' : 'AI生成图片自动归档',
            'sort' => 0,
            'type' => CategoryService::FILE_TYPE,
            'status' => 1,
            'other' => '{}',
        ]);

        return (int)$created->id;
    }

    /**
     * 解析参考图本地路径。
     */
    private function resolveReferencePaths(int $referenceFileId, int $merchantId = 0): array
    {
        if ($referenceFileId <= 0) {
            return [];
        }

        /** @var FileService $fileService */
        $fileService = app()->make(FileService::class);
        $file = $fileService->getModel()
            ->where('id', $referenceFileId)
            ->where('merchant_id', max(0, $merchantId))
            ->find();
        if (!$file) {
            throw new AdminResponseException('参考图不存在');
        }

        $path = (string)$file->path;
        if (!FileTypeService::isImage((string)$file->real_name) && !FileTypeService::isImage((string)$file->name)) {
            throw new AdminResponseException('参考文件必须是图片');
        }

        if (str_starts_with($path, '/storage/')) {
            $localPath = app()->getRootPath() . 'public' . $path;
            if (is_file($localPath)) {
                return [$localPath];
            }
        }

        if (str_starts_with($path, '/')) {
            $localPath = app()->getRootPath() . 'public' . $path;
            if (is_file($localPath)) {
                return [$localPath];
            }
        }

        $referenceUrl = $this->resolveReferenceUrl($path);
        if ($referenceUrl !== '') {
            return [$this->downloadReferenceToTemp($referenceUrl)];
        }

        throw new AdminResponseException('参考图文件不存在或无法访问');
    }

    /**
     * 构建任务图片存储目录。
     */
    private function buildStorageDirectory(int $merchantId = 0): string
    {
        $merchantId = max(0, $merchantId);
        $prefix = $merchantId > 0 ? 'merchant/' . $merchantId : 'platform';

        return self::STORAGE_DIRECTORY . '/' . $prefix . '/' . date('Y/m');
    }

    /**
     * 解析参考图远程访问地址。
     */
    private function resolveReferenceUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            return 'https:' . $path;
        }

        if (str_starts_with($path, '/')) {
            $webHost = trim((string)db_config(ConfigKeyEnum::WEB_HOST));
            return $webHost !== '' ? rtrim($webHost, '/') . $path : '';
        }

        return '';
    }

    /**
     * 下载远程参考图到临时目录，供 multipart 图片编辑接口使用。
     */
    private function downloadReferenceToTemp(string $url): string
    {
        $client = new Client([
            'timeout' => 20,
        ]);

        try {
            $response = $client->get($url);
        } catch (GuzzleException $exception) {
            throw new AdminResponseException('参考图下载失败：' . $exception->getMessage());
        }

        $content = (string)$response->getBody()->getContents();
        if ($content === '') {
            throw new AdminResponseException('参考图下载失败：文件内容为空');
        }

        $directory = $this->getTempReferenceDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new AdminResponseException('参考图临时目录创建失败');
        }

        $extension = $this->detectReferenceExtension($url, trim((string)$response->getHeaderLine('Content-Type')));
        $path = $directory . DIRECTORY_SEPARATOR . date('YmdHis') . '_' . substr(md5($url . uniqid('', true)), 0, 12) . '.' . $extension;
        $saved = @file_put_contents($path, $content);
        if ($saved === false || $saved <= 0) {
            throw new AdminResponseException('参考图临时文件保存失败');
        }

        return $path;
    }

    /**
     * 清理远程参考图临时文件。
     */
    private function cleanupTempReferencePaths(array $paths): void
    {
        $directory = realpath($this->getTempReferenceDirectory());
        if ($directory === false) {
            return;
        }

        foreach ($paths as $path) {
            $realPath = realpath((string)$path);
            if ($realPath === false || !str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)) {
                continue;
            }

            @unlink($realPath);
        }
    }

    /**
     * 获取参考图临时目录。
     */
    private function getTempReferenceDirectory(): string
    {
        return app()->getRootPath() . 'runtime/temp/ai_reference';
    }

    /**
     * 识别参考图后缀。
     */
    private function detectReferenceExtension(string $url, string $contentType): string
    {
        $extension = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $extension === 'jpg' ? 'jpeg' : $extension;
        }

        if (str_contains($contentType, 'jpeg')) {
            return 'jpeg';
        }

        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }

        return 'png';
    }

    /**
     * 格式化任务输出。
     */
    private function formatTask(array|FileAiGenerationTask $task): array
    {
        $item = $task instanceof FileAiGenerationTask ? $task->toArray() : $task;
        $item['id'] = (int)($item['id'] ?? 0);
        $item['merchant_id'] = (int)($item['merchant_id'] ?? 0);
        $item['admin_id'] = (int)($item['admin_id'] ?? 0);
        $item['status'] = (int)($item['status'] ?? 0);
        $item['status_text'] = $this->getStatusText($item['status']);
        $item['status_type'] = $this->getStatusType($item['status']);
        $item['progress'] = (int)($item['progress'] ?? 0);
        $item['count'] = (int)($item['count'] ?? 0);
        $item['reference_file_id'] = (int)($item['reference_file_id'] ?? 0);
        $item['file_ids'] = $this->decodeJsonArray($item['file_ids'] ?? '[]');
        $item['result_images'] = $this->decodeJsonArray($item['result_images'] ?? '[]');
        $item['params'] = $this->decodeJsonArray($item['params'] ?? '{}');
        $item['fail_reason'] = trim((string)($item['fail_reason'] ?? ''));

        return $item;
    }

    /**
     * 格式化任务失败原因。
     */
    private function formatFailureReason(\Throwable $exception): string
    {
        $messages = [];
        $current = $exception;
        while ($current instanceof \Throwable) {
            $message = trim($current->getMessage());
            if ($message !== '' && !in_array($message, $messages, true)) {
                $messages[] = $message;
            }
            $current = $current->getPrevious();
        }

        $message = implode('；原始异常：', $messages);
        if ($message === '') {
            $message = '任务执行失败：' . get_class($exception);
        }

        return mb_substr($message, 0, 1000);
    }

    /**
     * JSON 字符串转数组。
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 任务状态文案。
     */
    private function getStatusText(int $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '排队中',
            self::STATUS_PROCESSING => '生成中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
            self::STATUS_CANCELED => '已取消',
            default => '未知',
        };
    }

    /**
     * 任务状态标签类型。
     */
    private function getStatusType(int $status): string
    {
        return match ($status) {
            self::STATUS_PROCESSING => 'primary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELED => 'info',
            default => 'warning',
        };
    }

    /**
     * 规范化质量。
     */
    private function normalizeQuality(string $quality, bool $highQuality): string
    {
        $quality = strtolower(trim($quality));
        if ($highQuality) {
            return 'high';
        }

        return in_array($quality, ['low', 'medium', 'high', 'auto'], true) ? $quality : 'auto';
    }

    /**
     * 规范化尺寸。
     */
    private function normalizeSize(string $size): string
    {
        $size = strtolower(trim($size));
        $allowed = ['auto', '1024x1024', '1024x1536', '1536x1024'];

        return in_array($size, $allowed, true) ? $size : '1024x1024';
    }

    /**
     * 规范化输出格式。
     */
    private function normalizeOutputFormat(string $format): string
    {
        $format = strtolower(trim($format));

        return in_array($format, ['png', 'jpeg', 'webp'], true) ? $format : 'png';
    }

    /**
     * 构建生成图片原始名称。
     */
    private function buildGeneratedRealName(string $title, string $extension, int $index): string
    {
        $safeTitle = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|\\s]+/u', '_', trim($title));
        $safeTitle = $safeTitle ?: 'AI生成图片';

        return mb_substr($safeTitle, 0, 40) . '_' . $index . '.' . $extension;
    }

    /**
     * 将 uploader 类型映射为 file.update_type。
     */
    private function normalizeUploaderType(string $type): int
    {
        return match ($type) {
            UploaderService::LOCAL_UPLOADER => 1,
            UploaderService::QINIU_UPLOADER => 2,
            UploaderService::COS_UPLOADER => 3,
            UploaderService::OSS_UPLOADER => 4,
            default => 1,
        };
    }
}
