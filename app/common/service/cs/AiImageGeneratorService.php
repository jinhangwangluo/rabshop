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

namespace app\common\service\cs;

use app\common\model\Base;
use app\common\service\BaseService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Rabbit\service\uploader\UploaderService;
use think\facade\Log;

/**
 * AI 图片生成服务。
 */
class AiImageGeneratorService extends BaseService
{
    private const DEFAULT_STORAGE_DIRECTORY = 'sms/click_captcha/backgrounds';
    private const MAX_STORAGE_IMAGE_COUNT = 10;
    private const ALLOWED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * 设置模型。
     */
    protected function setModel(): string
    {
        return Base::class;
    }

    /**
     * 获取本地背景图列表。
     *
     * @param string $directory 存储目录。
     * @return array
     */
    public function getLocalBackgrounds(string $directory = ''): array
    {
        $storage = $this->resolveStorageDirectory($directory);
        if (!is_dir($storage['path'])) {
            return [];
        }

        $files = scandir($storage['path']);
        if (!is_array($files)) {
            return [];
        }

        $urls = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $storage['path'] . DIRECTORY_SEPARATOR . $file;
            if (!is_file($filePath)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
                continue;
            }

            $urls[] = $storage['url'] . '/' . $file;
        }

        sort($urls);

        return $urls;
    }

    /**
     * 获取本地背景图数量上限。
     *
     * @return int
     */
    public function getMaxLocalBackgroundCount(): int
    {
        return self::MAX_STORAGE_IMAGE_COUNT;
    }

    /**
     * 生成单张背景图并保存到本地目录。
     *
     * @param string $prompt 提示词。
     * @param array $options 运行时选项。
     * @return string|null 成功时返回本地图片地址。
     */
    public function generateBackground(string $prompt, array $options = [], array $runtimeOverride = []): ?string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        $directory = (string) ($options['directory'] ?? self::DEFAULT_STORAGE_DIRECTORY);
        if (count($this->getLocalBackgrounds($directory)) >= self::MAX_STORAGE_IMAGE_COUNT) {
            return null;
        }

        $runtime = $runtimeOverride;
        if ($runtime === []) {
            /** @var AiModelConfigService $configService */
            $configService = app()->make(AiModelConfigService::class);
            $runtime = $configService->resolveRuntime(
                AiModelConfigService::TYPE_IMAGE,
                max(0, (int)($options['merchant_id'] ?? 0))
            ) ?? [];
        }
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            return null;
        }

        $provider = (string) $runtime['provider'];
        if ($provider === AiModelConfigService::DRIVER_OPENAI) {
            return $this->requestOpenAiImage((array) $runtime['options'], $prompt, $options, false);
        }

        if ($provider === AiModelConfigService::DRIVER_OPENAI_COMPATIBLE) {
            return $this->requestOpenAiImage((array) $runtime['options'], $prompt, $options, true);
        }

        return null;
    }

    /**
     * 生成多张图片并保存到本地文件库目录。
     *
     * @param string $prompt 提示词。
     * @param array $options 运行时选项。
     * @return array
     */
    public function generateImages(string $prompt, array $options = [], array $runtimeOverride = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return [];
        }

        $runtime = $runtimeOverride;
        if ($runtime === []) {
            /** @var AiModelConfigService $configService */
            $configService = app()->make(AiModelConfigService::class);
            $runtime = $configService->resolveRuntime(
                AiModelConfigService::TYPE_IMAGE,
                max(0, (int)($options['merchant_id'] ?? 0))
            ) ?? [];
        }
        if (!is_array($runtime) || !isset($runtime['provider'], $runtime['options'])) {
            throw new \RuntimeException('请先配置默认启用的 AI 图片模型');
        }

        $provider = (string)$runtime['provider'];
        if ($provider === AiModelConfigService::DRIVER_OPENAI) {
            return $this->requestOpenAiImages((array)$runtime['options'], $prompt, $options, false);
        }

        if ($provider === AiModelConfigService::DRIVER_OPENAI_COMPATIBLE) {
            return $this->requestOpenAiImages((array)$runtime['options'], $prompt, $options, true);
        }

        throw new \RuntimeException('当前 AI 图片服务商暂不支持生图接口');
    }

    /**
     * 调用 OpenAI / OpenAI Compatible 图片接口。
     *
     * @param array $runtime 运行时配置。
     * @param string $prompt 提示词。
     * @param array $options 附加选项。
     * @param bool $requireBaseUrl 是否必须提供 base_url。
     * @return string|null
     */
    private function requestOpenAiImage(array $runtime, string $prompt, array $options, bool $requireBaseUrl): ?string
    {
        try {
            $images = $this->requestOpenAiImages(
                $runtime,
                $prompt,
                array_merge($options, ['count' => 1]),
                $requireBaseUrl,
                true
            );

            return $images[0]['url'] ?? null;
        } catch (\Throwable $exception) {
            Log::warning('AI 图片生成请求失败', [
                'provider' => $requireBaseUrl ? AiModelConfigService::DRIVER_OPENAI_COMPATIBLE : AiModelConfigService::DRIVER_OPENAI,
                'message' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 调用 OpenAI / OpenAI Compatible 图片接口并保存结果。
     *
     * @param array $runtime 运行时配置。
     * @param string $prompt 提示词。
     * @param array $options 附加选项。
     * @param bool $requireBaseUrl 是否必须提供 base_url。
     * @param bool $enforceStorageLimit 是否套用背景图本地数量限制。
     * @return array
     */
    private function requestOpenAiImages(
        array $runtime,
        string $prompt,
        array $options,
        bool $requireBaseUrl,
        bool $enforceStorageLimit = false
    ): array {
        $apiKey = trim((string) ($runtime['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('AI 图片模型 API Key 未配置');
        }

        $defaultBaseUrl = $requireBaseUrl ? '' : 'https://api.openai.com';
        $baseUrl = $this->normalizeBaseUrl((string) ($runtime['base_url'] ?? $defaultBaseUrl));
        if ($baseUrl === '') {
            throw new \RuntimeException('AI 图片模型 Base URL 未配置');
        }

        $params = (array) ($runtime['params'] ?? []);
        $timeout = max(1, (int) ($runtime['timeout'] ?? 30));
        $directory = (string)($options['directory'] ?? self::DEFAULT_STORAGE_DIRECTORY);
        $useUploader = (bool)($options['use_uploader'] ?? false);
        $model = $this->normalizeImageModel((string)($options['model'] ?? ($runtime['model'] ?? 'gpt-image-1')));
        $body = $this->buildOpenAiImageBody($model, $prompt, $params, $options);
        $referencePaths = $this->normalizeReferencePaths((array)($options['reference_paths'] ?? []));
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
        ];

        if ($referencePaths !== []) {
            $response = $this->requestOpenAiEditImages($baseUrl, $headers, $body, $referencePaths, $timeout);
        } else {
            $response = $this->postJson($this->buildOpenAiApiUrl($baseUrl, '/images/generations'), $headers + [
                'Content-Type' => 'application/json',
            ], $body, $timeout);
        }

        return $this->storeOpenAiImageResponse(
            $response,
            $body['prompt'],
            $directory,
            $timeout,
            (string)($body['output_format'] ?? 'png'),
            (int)$body['n'],
            $baseUrl,
            $enforceStorageLimit,
            $useUploader
        );
    }

    /**
     * 调用图片编辑接口，批量失败时回退为逐张生成。
     *
     * @param string $baseUrl API 地址。
     * @param array $headers 请求头。
     * @param array $body 请求体字段。
     * @param array $referencePaths 参考图路径。
     * @param int $timeout 超时时间。
     * @return array
     */
    private function requestOpenAiEditImages(string $baseUrl, array $headers, array $body, array $referencePaths, int $timeout): array
    {
        if ((int)$body['n'] <= 1) {
            return $this->postMultipart($this->buildOpenAiApiUrl($baseUrl, '/images/edits'), $headers, $body, $referencePaths, $timeout);
        }

        try {
            return $this->postMultipart($this->buildOpenAiApiUrl($baseUrl, '/images/edits'), $headers, $body, $referencePaths, $timeout);
        } catch (\Throwable $exception) {
            $items = [];
            $singleBody = $body;
            $singleBody['n'] = 1;
            for ($index = 0; $index < (int)$body['n']; $index++) {
                $response = $this->postMultipart($this->buildOpenAiApiUrl($baseUrl, '/images/edits'), $headers, $singleBody, $referencePaths, $timeout);
                foreach ((array)($response['data'] ?? []) as $item) {
                    $items[] = $item;
                }
            }

            if ($items === []) {
                throw $exception;
            }

            return ['data' => $items];
        }
    }

    /**
     * 构建图片接口请求体。
     *
     * @param string $model 模型。
     * @param string $prompt 提示词。
     * @param array $params 模型默认参数。
     * @param array $options 任务参数。
     * @return array
     */
    private function buildOpenAiImageBody(string $model, string $prompt, array $params, array $options): array
    {
        $isGptImage2 = $this->isGptImage2($model);
        $outputFormat = $this->normalizeOutputFormat((string)($options['output_format'] ?? ($params['output_format'] ?? 'png')));
        $body = [
            'model' => $model !== '' ? $model : 'gpt-image-1',
            'prompt' => $this->buildPrompt($prompt, $options),
            'n' => $this->normalizeImageCount((int)($options['count'] ?? ($params['n'] ?? 1))),
            'size' => $this->normalizeImageSize((string)($options['size'] ?? ($params['size'] ?? '1024x1024'))),
            'quality' => $this->normalizeImageQuality((string)($options['quality'] ?? ($params['quality'] ?? 'auto')), $isGptImage2),
            'output_format' => $outputFormat,
        ];

        $background = $this->normalizeBackground((string)($options['background'] ?? ($params['background'] ?? 'auto')), $isGptImage2);
        if ($background !== '') {
            $body['background'] = $background;
        }

        $compression = (int)($options['output_compression'] ?? ($params['output_compression'] ?? 0));
        if ($compression > 0 && in_array($outputFormat, ['jpeg', 'webp'], true)) {
            $body['output_compression'] = max(0, min(100, $compression));
        }

        if (!$isGptImage2 && ($params['response_format'] ?? '') === 'url') {
            $body['response_format'] = 'url';
        } elseif (!$isGptImage2 && isset($params['response_format'])) {
            $body['response_format'] = 'b64_json';
        }

        return $body;
    }

    /**
     * 合成最终提示词。
     */
    private function buildPrompt(string $prompt, array $options): string
    {
        $segments = [trim($prompt)];
        $style = trim((string)($options['style'] ?? ''));
        $negativePrompt = trim((string)($options['negative_prompt'] ?? ''));

        if ($style !== '') {
            $segments[] = '风格：' . $style;
        }

        if ($negativePrompt !== '') {
            $segments[] = '避免出现：' . $negativePrompt;
        }

        return implode("\n", array_filter($segments));
    }

    /**
     * 归一化图片模型名称。
     */
    private function normalizeImageModel(string $model): string
    {
        $model = trim($model);
        if (strtolower($model) === 'gpt-image2') {
            return 'gpt-image-2';
        }

        return $model !== '' ? $model : 'gpt-image-1';
    }

    /**
     * 判断是否为 GPT Image 2。
     */
    private function isGptImage2(string $model): bool
    {
        return strtolower($model) === 'gpt-image-2';
    }

    /**
     * 归一化生成数量。
     */
    private function normalizeImageCount(int $count): int
    {
        return max(1, min(4, $count));
    }

    /**
     * 归一化图片尺寸。
     */
    private function normalizeImageSize(string $size): string
    {
        $size = strtolower(trim($size));
        $allowed = ['auto', '1024x1024', '1024x1536', '1536x1024'];

        return in_array($size, $allowed, true) ? $size : '1024x1024';
    }

    /**
     * 归一化图片质量。
     */
    private function normalizeImageQuality(string $quality, bool $isGptImage2): string
    {
        $quality = strtolower(trim($quality));
        if ($isGptImage2) {
            return in_array($quality, ['low', 'medium', 'high', 'auto'], true) ? $quality : 'auto';
        }

        return $quality !== '' ? $quality : 'auto';
    }

    /**
     * 归一化背景参数。
     */
    private function normalizeBackground(string $background, bool $isGptImage2): string
    {
        $background = strtolower(trim($background));
        if ($isGptImage2) {
            return in_array($background, ['auto', 'opaque'], true) ? $background : 'auto';
        }

        return $background;
    }

    /**
     * 归一化输出格式。
     */
    private function normalizeOutputFormat(string $format): string
    {
        $format = strtolower(trim($format));

        return in_array($format, ['png', 'jpeg', 'webp'], true) ? $format : 'png';
    }

    /**
     * 归一化参考图路径。
     */
    private function normalizeReferencePaths(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            $path = (string)$path;
            if ($path !== '' && is_file($path)) {
                $result[] = $path;
            }
        }

        return $result;
    }

    /**
     * 保存生成图片到本地。
     *
     * @param string $image 图片原始响应。
     * @param string $prompt 提示词。
     * @param string $directory 目标目录。
     * @param int $timeout 下载超时。
     * @return string|null
     */
    private function storeGeneratedImage(string $image, string $prompt, string $directory, int $timeout): ?string
    {
        if (count($this->getLocalBackgrounds($directory)) >= self::MAX_STORAGE_IMAGE_COUNT) {
            return null;
        }

        if (str_starts_with($image, 'data:image/')) {
            return $this->storeBase64Image($image, $prompt, $directory);
        }

        return $this->storeRemoteImage($image, $prompt, $directory, $timeout);
    }

    /**
     * 保存 base64 图片。
     *
     * @param string $dataUri base64 数据。
     * @param string $prompt 提示词。
     * @param string $directory 目标目录。
     * @return string|null
     */
    private function storeBase64Image(string $dataUri, string $prompt, string $directory): ?string
    {
        if (!preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUri, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            return null;
        }

        return $this->writeImageBinary($binary, $directory, $this->normalizeImageExtension($matches[1]), $prompt);
    }

    /**
     * 下载并保存远程图片。
     *
     * @param string $url 图片地址。
     * @param string $prompt 提示词。
     * @param string $directory 目标目录。
     * @param int $timeout 下载超时。
     * @return string|null
     */
    private function storeRemoteImage(string $url, string $prompt, string $directory, int $timeout): ?string
    {
        $download = $this->downloadImage($url, $timeout);
        if ($download === null || $download['content'] === '') {
            return null;
        }

        return $this->writeImageBinary(
            $download['content'],
            $directory,
            $this->detectImageExtension($url, $download['content_type']),
            $prompt
        );
    }

    /**
     * 下载远程图片内容。
     *
     * @param string $url 图片地址。
     * @param int $timeout 下载超时。
     * @return array|null
     */
    private function downloadImage(string $url, int $timeout): ?array
    {
        $client = new Client([
            'timeout' => max(1, $timeout),
        ]);

        try {
            $response = $client->get($url);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('图片下载失败：' . $exception->getMessage(), 0, $exception);
        }

        $content = (string) $response->getBody()->getContents();
        if ($content === '') {
            return null;
        }

        return [
            'content' => $content,
            'content_type' => trim((string) $response->getHeaderLine('Content-Type')),
        ];
    }

    /**
     * 写入图片二进制内容。
     *
     * @param string $binary 图片二进制。
     * @param string $directory 目标目录。
     * @param string $extension 文件后缀。
     * @param string $prompt 提示词。
     * @return string|null
     */
    private function writeImageBinary(string $binary, string $directory, string $extension, string $prompt): ?string
    {
        $result = $this->writeImageBinaryResult($binary, $directory, $extension, $prompt, true);

        return $result['url'] ?? null;
    }

    /**
     * 写入图片二进制内容并返回文件信息。
     *
     * @param string $binary 图片二进制。
     * @param string $directory 目标目录。
     * @param string $extension 文件后缀。
     * @param string $prompt 提示词。
     * @param bool $enforceStorageLimit 是否限制本地背景图数量。
     * @param bool $useUploader 是否使用统一上传服务保存。
     * @return array|null
     */
    private function writeImageBinaryResult(
        string $binary,
        string $directory,
        string $extension,
        string $prompt,
        bool $enforceStorageLimit = false,
        bool $useUploader = false
    ): ?array {
        $extension = $this->normalizeImageExtension($extension);
        $filename = date('YmdHis') . '_' . substr(md5($prompt . uniqid('', true)), 0, 16) . '.' . $extension;

        if ($useUploader) {
            return $this->uploadImageBinary($binary, $directory, $filename, $extension);
        }

        $storage = $this->resolveStorageDirectory($directory);
        if (!is_dir($storage['path']) && !mkdir($storage['path'], 0777, true) && !is_dir($storage['path'])) {
            return null;
        }

        if ($enforceStorageLimit && count($this->getLocalBackgrounds($directory)) >= self::MAX_STORAGE_IMAGE_COUNT) {
            return null;
        }

        $filePath = $storage['path'] . DIRECTORY_SEPARATOR . $filename;
        $saved = @file_put_contents($filePath, $binary);
        if ($saved === false || $saved <= 0) {
            return null;
        }

        return [
            'name' => $filename,
            'extension' => $extension,
            'size' => (string)$saved,
            'path' => $filePath,
            'url' => $storage['url'] . '/' . $filename,
            'update_type' => UploaderService::LOCAL_UPLOADER,
        ];
    }

    /**
     * 使用统一上传服务保存图片二进制。
     */
    private function uploadImageBinary(string $binary, string $directory, string $filename, string $extension): ?array
    {
        $directory = $this->normalizeStorageDirectory($directory);
        $uploader = app('uploader')->storage();
        $uploaded = $uploader->to($directory)->stream($binary, $filename);
        if ($uploaded === false || !is_array($uploaded)) {
            $error = method_exists($uploader, 'getError') ? $uploader->getError() : 'Upload failure';
            throw new \RuntimeException('图片上传失败：' . $error);
        }

        $filePath = trim((string)($uploaded['filePath'] ?? ''));
        if ($filePath === '') {
            throw new \RuntimeException('图片上传失败：上传服务没有返回文件地址');
        }

        $fileName = trim((string)($uploaded['fileName'] ?? $filename));

        return [
            'name' => basename($fileName ?: $filename),
            'extension' => $extension,
            'size' => (string)strlen($binary),
            'path' => $filePath,
            'url' => $filePath,
            'update_type' => (string)($uploaded['updateType'] ?? UploaderService::LOCAL_UPLOADER),
        ];
    }

    /**
     * 保存 OpenAI 图片响应。
     *
     * @param array $response 接口响应。
     * @param string $prompt 提示词。
     * @param string $directory 目录。
     * @param int $timeout 下载超时。
     * @param string $outputFormat 输出格式。
     * @param int $limit 最多保存数量。
     * @param string $baseUrl API 地址。
     * @param bool $enforceStorageLimit 是否限制本地背景图数量。
     * @param bool $useUploader 是否使用统一上传服务保存。
     * @return array
     */
    private function storeOpenAiImageResponse(
        array $response,
        string $prompt,
        string $directory,
        int $timeout,
        string $outputFormat,
        int $limit,
        string $baseUrl,
        bool $enforceStorageLimit,
        bool $useUploader
    ): array {
        $items = $response['data'] ?? [];
        if (!is_array($items) || $items === []) {
            throw new \RuntimeException('接口没有返回图片数据');
        }

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $base64 = trim((string)($item['b64_json'] ?? ''));
            if ($base64 !== '') {
                $binary = base64_decode($base64, true);
                if ($binary !== false && $binary !== '') {
                    $saved = $this->writeImageBinaryResult(
                        $binary,
                        $directory,
                        $outputFormat,
                        $prompt,
                        $enforceStorageLimit,
                        $useUploader
                    );
                    if ($saved !== null) {
                        $results[] = $saved;
                    }
                }
            }

            if (count($results) >= $limit) {
                break;
            }

            $url = trim((string)($item['url'] ?? ''));
            if ($url !== '') {
                $saved = $this->storeRemoteImageResult(
                    $this->resolveResponseUrl($url, $baseUrl),
                    $prompt,
                    $directory,
                    $timeout,
                    $enforceStorageLimit,
                    $useUploader
                );
                if ($saved !== null) {
                    $results[] = $saved;
                }
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        if ($results === []) {
            throw new \RuntimeException('接口返回的数据无法保存为图片');
        }

        return $results;
    }

    /**
     * 下载并保存远程图片，返回文件信息。
     */
    private function storeRemoteImageResult(
        string $url,
        string $prompt,
        string $directory,
        int $timeout,
        bool $enforceStorageLimit = false,
        bool $useUploader = false
    ): ?array {
        $download = $this->downloadImage($url, $timeout);
        if ($download === null || $download['content'] === '') {
            return null;
        }

        return $this->writeImageBinaryResult(
            $download['content'],
            $directory,
            $this->detectImageExtension($url, $download['content_type']),
            $prompt,
            $enforceStorageLimit,
            $useUploader
        );
    }

    /**
     * 解析存储目录。
     *
     * @param string $directory 目标目录。
     * @return array
     */
    private function resolveStorageDirectory(string $directory): array
    {
        $directory = $this->normalizeStorageDirectory($directory);

        return [
            'relative' => $directory,
            'path' => app()->getRootPath() . 'public/storage/' . $directory,
            'url' => '/storage/' . $directory,
        ];
    }

    /**
     * 归一化存储目录。
     *
     * @param string $directory 原始目录。
     * @return string
     */
    private function normalizeStorageDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        if ($directory === '') {
            return self::DEFAULT_STORAGE_DIRECTORY;
        }

        $segments = array_values(array_filter(explode('/', $directory), static function ($segment) {
            return $segment !== '' && $segment !== '.' && $segment !== '..';
        }));

        return $segments === [] ? self::DEFAULT_STORAGE_DIRECTORY : implode('/', $segments);
    }

    /**
     * 识别图片后缀。
     *
     * @param string $url 图片地址。
     * @param string $contentType 响应类型。
     * @return string
     */
    private function detectImageExtension(string $url, string $contentType): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            return $extension;
        }

        if (str_contains($contentType, '/')) {
            $typeExtension = strtolower(substr(strrchr($contentType, '/'), 1) ?: '');
            return $this->normalizeImageExtension($typeExtension);
        }

        return 'png';
    }

    /**
     * 归一化图片后缀。
     *
     * @param string $extension 原始后缀。
     * @return string
     */
    private function normalizeImageExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));
        if ($extension === 'jpg') {
            return 'jpg';
        }

        return in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true) ? $extension : 'png';
    }

    /**
     * 发送 JSON 请求。
     *
     * @param string $url 请求地址。
     * @param array $headers 请求头。
     * @param array $body 请求体。
     * @param int $timeout 超时时间。
     * @return array
     */
    private function postJson(string $url, array $headers, array $body, int $timeout = 30): array
    {
        $client = new Client([
            'timeout' => max(1, $timeout),
        ]);

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $body,
            ]);
        } catch (RequestException $exception) {
            throw new \RuntimeException('HTTP请求失败：' . $this->formatRequestException($exception), 0, $exception);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('HTTP请求失败：' . $exception->getMessage(), 0, $exception);
        }

        $content = (string) $response->getBody()->getContents();
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('响应解析失败');
        }

        $this->assertSuccessfulImageResponse($decoded);

        return $decoded;
    }

    /**
     * 发送 multipart 请求。
     *
     * @param string $url 请求地址。
     * @param array $headers 请求头。
     * @param array $fields 表单字段。
     * @param array $imagePaths 图片路径。
     * @param int $timeout 超时时间。
     * @return array
     */
    private function postMultipart(string $url, array $headers, array $fields, array $imagePaths, int $timeout = 30): array
    {
        $client = new Client([
            'timeout' => max(1, $timeout),
        ]);
        $multipart = [];
        $handles = [];
        $hasImage = false;

        foreach ($fields as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $multipart[] = [
                'name' => (string)$name,
                'contents' => (string)$value,
            ];
        }

        try {
            foreach ($imagePaths as $imagePath) {
                $handle = fopen((string)$imagePath, 'rb');
                if ($handle === false) {
                    continue;
                }

                $handles[] = $handle;
                $hasImage = true;
                $multipart[] = [
                    'name' => 'image',
                    'contents' => $handle,
                    'filename' => basename((string)$imagePath),
                    'headers' => [
                        'Content-Type' => $this->guessImageMimeType((string)$imagePath),
                    ],
                ];
            }

            if (!$hasImage) {
                throw new \RuntimeException('请上传参考图');
            }

            $response = $client->post($url, [
                'headers' => $headers,
                'multipart' => $multipart,
            ]);
        } catch (RequestException $exception) {
            throw new \RuntimeException('HTTP请求失败：' . $this->formatRequestException($exception), 0, $exception);
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('HTTP请求失败：' . $exception->getMessage(), 0, $exception);
        } finally {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }

        $content = (string)$response->getBody()->getContents();
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('响应解析失败');
        }

        $this->assertSuccessfulImageResponse($decoded);

        return $decoded;
    }

    /**
     * 归一化 base_url。
     *
     * @param string $baseUrl 原始地址。
     * @return string
     */
    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl !== '' && !str_contains($baseUrl, '://')) {
            $baseUrl = 'https://' . $baseUrl;
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * 构建 OpenAI 图片接口地址，兼容 base_url 是否已包含 /v1。
     */
    private function buildOpenAiApiUrl(string $baseUrl, string $endpoint): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = '/' . ltrim($endpoint, '/');
        if (str_ends_with($baseUrl, '/v1')) {
            return $baseUrl . $endpoint;
        }

        return $baseUrl . '/v1' . $endpoint;
    }

    /**
     * 从响应中提取图片地址。
     *
     * @param array $response 响应数据。
     * @return string|null
     */
    private function extractImageUrl(array $response, string $baseUrl = ''): ?string
    {
        $data = $response['data'][0] ?? [];
        if (!is_array($data)) {
            return null;
        }

        $base64 = trim((string) ($data['b64_json'] ?? ''));
        if ($base64 !== '') {
            return 'data:image/png;base64,' . $base64;
        }

        $url = trim((string) ($data['url'] ?? ''));
        $url = $url !== '' ? $url : null;

        if ($url === null) {
            return null;
        }
        return $this->resolveResponseUrl($url, $baseUrl);
    }

    /**
     * 解析图片响应 URL。
     */
    private function resolveResponseUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * 猜测图片 MIME 类型。
     */
    private function guessImageMimeType(string $path): string
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };
    }

    /**
     * 格式化接口异常，尽量保留上游错误正文。
     */
    private function formatRequestException(RequestException $exception): string
    {
        $message = $exception->getMessage();
        $response = $exception->getResponse();
        if ($response === null) {
            return $message;
        }

        $body = trim((string)$response->getBody()->getContents());
        $status = trim($response->getStatusCode() . ' ' . $response->getReasonPhrase());
        if ($body === '') {
            return $status !== '' ? $status . '：' . $message : $message;
        }

        $error = $this->extractImageResponseErrorMessage($body);
        if ($error !== '') {
            return mb_substr(($status !== '' ? $status . '：' : '') . $error, 0, 1000);
        }

        return mb_substr(($status !== '' ? $status . '：' : '') . $body, 0, 1000);
    }

    /**
     * 检查接口响应中的业务错误。
     */
    private function assertSuccessfulImageResponse(array $response): void
    {
        $error = $this->extractImageResponseErrorMessage($response);
        if ($error !== '') {
            throw new \RuntimeException('AI 图片接口返回错误：' . $error);
        }
    }

    /**
     * 从上游响应中提取最具体的错误消息。
     */
    private function extractImageResponseErrorMessage(array|string $payload): string
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $this->extractImageResponseErrorMessage($decoded);
            }

            return trim($payload);
        }

        if (isset($payload['error'])) {
            $error = $payload['error'];
            if (is_string($error)) {
                return trim($error);
            }

            if (is_array($error)) {
                return $this->formatImageErrorMessage($error);
            }
        }

        if (array_key_exists('data', $payload) && !empty($payload['data'])) {
            return '';
        }

        return $this->formatImageErrorMessage($payload);
    }

    /**
     * 格式化错误消息，并补充 code/type/param 等关键信息。
     */
    private function formatImageErrorMessage(array $error): string
    {
        $message = '';
        foreach (['message', 'msg', 'detail'] as $field) {
            if (isset($error[$field]) && is_scalar($error[$field])) {
                $message = trim((string)$error[$field]);
                if ($message !== '') {
                    break;
                }
            }
        }

        if ($message === '') {
            return '';
        }

        $meta = [];
        foreach (['code', 'type', 'param'] as $field) {
            if (isset($error[$field]) && is_scalar($error[$field]) && trim((string)$error[$field]) !== '') {
                $meta[] = $field . '=' . trim((string)$error[$field]);
            }
        }

        return $meta === [] ? $message : $message . '（' . implode('，', $meta) . '）';
    }
}
