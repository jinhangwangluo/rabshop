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

namespace app\common\job\content;

use app\common\enum\ConfigKeyEnum;
use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\content\ArticleContentService;
use app\common\service\content\ArticleService;
use app\common\service\setting\FileService;
use Rabbit\service\uploader\UploaderService;
use think\facade\Log;

/**
 * 文章远程图片本地化队列。
 */
class ArticleImageLocalizeJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    private const MAX_IMAGE_COUNT = 120;
    private const MAX_IMAGE_SIZE = 5242880;
    private const TIMEOUT = 10000;

    /**
     * 本地化文章中的远程图片。
     *
     * 首次执行只负责收集图片并投递第一张图片任务；后续每个队列任务只处理一张图片，
     * 当前图片处理完成后再投递下一张，最后一张处理完成后统一回写内容与采集状态。
     *
     * @param int $articleId 文章ID。
     * @param array $urls 待处理图片地址集合。
     * @param int $index 当前处理下标。
     * @param array $replacements 图片替换映射。
     * @return void
     */
    public function handle(int $articleId, array $urls = [], int $index = 0, array $replacements = []): void
    {
        if ($articleId <= 0) {
            return;
        }

        try {
            if ($urls === []) {
                $urls = $this->collectRemoteImages($articleId);
                if ($urls === []) {
                    $this->finishArticleLocalize($articleId, []);
                    return;
                }
                self::dispatch($articleId, $urls, 0, []);
                return;
            }

            $index = max(0, $index);
            $url = (string)($urls[$index] ?? '');
            if ($url !== '' && $this->isRemoteHttpUrl($url)) {
                $localUrl = $this->downloadAndUploadImage($url);
                if ($localUrl !== '') {
                    $replacements[$url] = $localUrl;
                }
            }

            $nextIndex = $index + 1;
            if ($nextIndex < count($urls)) {
                self::dispatch($articleId, $urls, $nextIndex, $replacements);
                return;
            }

            $this->finishArticleLocalize($articleId, $replacements);
        } catch (\Throwable $e) {
            Log::error('文章图片本地化失败：article_id=' . $articleId . '|index=' . $index . '|error=' . $e->getMessage());
        }
    }

    /**
     * 收集文章中的远程图片地址。
     *
     * @param int $articleId 文章ID。
     * @return array
     */
    private function collectRemoteImages(int $articleId): array
    {
        $articleService = app()->make(ArticleService::class);
        $contentService = app()->make(ArticleContentService::class);
        $article = $articleService->get($articleId);
        if (!$article) {
            return [];
        }

        $content = $contentService->findByArticleId($articleId);
        $urls = [];

        $this->pushRemoteImage($urls, (string)($article['cover'] ?? ''));
        if ($content) {
            $this->pushRemoteImages($urls, $this->extractImagesFromHtml((string)($content['content'] ?? '')));
            $this->pushRemoteImages($urls, $this->extractImagesFromHtml((string)($content['trial_content'] ?? '')));
            $this->pushRemoteImages($urls, is_array($content['images'] ?? null) ? $content['images'] : []);
            $this->pushRemoteImages($urls, is_array($content['trial_images'] ?? null) ? $content['trial_images'] : []);
        }

        return array_slice(array_values(array_unique($urls)), 0, self::MAX_IMAGE_COUNT);
    }

    /**
     * 完成文章图片本地化并更新采集状态。
     *
     * @param int $articleId 文章ID。
     * @param array $replacements 图片替换映射。
     * @return void
     */
    private function finishArticleLocalize(int $articleId, array $replacements): void
    {
        $articleService = app()->make(ArticleService::class);
        $contentService = app()->make(ArticleContentService::class);
        $article = $articleService->get($articleId);
        if (!$article) {
            return;
        }

        if ($replacements !== []) {
            $cover = (string)($article['cover'] ?? '');
            if (isset($replacements[$cover])) {
                $articleService->update($articleId, ['cover' => $replacements[$cover]]);
            }

            $content = $contentService->findByArticleId($articleId);
            if ($content) {
                $contentData = [
                    'content' => strtr((string)($content['content'] ?? ''), $replacements),
                    'trial_content' => strtr((string)($content['trial_content'] ?? ''), $replacements),
                    'images' => $this->replaceArrayImages(is_array($content['images'] ?? null) ? $content['images'] : [], $replacements),
                    'trial_images' => $this->replaceArrayImages(is_array($content['trial_images'] ?? null) ? $content['trial_images'] : [], $replacements),
                ];
                $contentService->saveOrCreateByArticleId($articleId, $contentData);
            }
        }

        $this->updateCollectStatus($articleService, $articleId);
    }

    /**
     * 更新采集完成状态。
     *
     * @param ArticleService $articleService 文章服务。
     * @param int $articleId 文章ID。
     * @return void
     */
    private function updateCollectStatus(ArticleService $articleService, int $articleId): void
    {
        $fields = $articleService->getModel()->getTableFields();
        foreach (['collect_status', 'image_localize_status', 'localize_status'] as $field) {
            if (in_array($field, $fields, true)) {
                $articleService->update($articleId, [$field => 2]);
                return;
            }
        }
    }

    /**
     * 提取 HTML 中的图片地址。
     *
     * @param string $html HTML内容。
     * @return array
     */
    private function extractImagesFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('/<img\b[^>]*\bsrc=["\']?([^"\'\s>]+)["\']?[^>]*>/i', $html, $matches);
        return $matches[1] ?? [];
    }

    /**
     * 批量追加远程图片地址。
     *
     * @param array $urls 地址集合。
     * @param array $images 图片地址集合。
     * @return void
     */
    private function pushRemoteImages(array &$urls, array $images): void
    {
        foreach ($images as $image) {
            $this->pushRemoteImage($urls, (string)$image);
        }
    }

    /**
     * 追加远程图片地址。
     *
     * @param array $urls 地址集合。
     * @param string $url 图片地址。
     * @return void
     */
    private function pushRemoteImage(array &$urls, string $url): void
    {
        if ($this->isRemoteHttpUrl($url) && !$this->isManagedImageUrl($url)) {
            $urls[] = $url;
        }
    }

    /**
     * 判断图片是否已在本系统文件库登记，避免本地化时重复下载保存。
     */
    private function isManagedImageUrl(string $url): bool
    {
        $candidates = [$url];
        $path = (string)(parse_url($url, PHP_URL_PATH) ?: '');
        if ($path !== '') {
            $candidates[] = $path;
            $candidates[] = ltrim($path, '/');
        }

        $fileService = app()->make(FileService::class);
        return $fileService->getModel()
            ->whereIn('path', array_values(array_unique($candidates)))
            ->count() > 0;
    }

    /**
     * 下载并上传远程图片。
     *
     * @param string $url 图片地址。
     * @return string
     */
    private function downloadAndUploadImage(string $url): string
    {
        $maxRedirects = 3;
        $currentUrl = $url;
        $content = '';
        $contentType = '';

        for ($i = 0; $i <= $maxRedirects; $i++) {
            $location = '';

            $ch = curl_init();
            $options = [
                CURLOPT_URL => $currentUrl,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'KnowledgePaymentBot/1.0',
                CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$location) {
                    if (stripos($headerLine, 'Location:') === 0) {
                        $location = trim(substr($headerLine, 9));
                    }
                    return strlen($headerLine);
                },
            ];
            $proxy = $this->getProxyUrl();
            if ($proxy !== '') {
                $options[CURLOPT_PROXY] = $proxy;
            }
            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode >= 300 && $httpCode < 400 && $httpCode !== 304) {
                if ($location === '') {
                    return '';
                }
                $currentUrl = $this->resolveUrl($location, $currentUrl);
                if (!$this->isRemoteHttpUrl($currentUrl)) {
                    return '';
                }
                continue;
            }

            if (!is_string($body) || $body === '' || $httpCode >= 400 || !$this->isRemoteHttpUrl($currentUrl)) {
                return '';
            }
            $content = $body;
            $contentType = $responseType;
            break;
        }

        if ($content === '' || strlen($content) > self::MAX_IMAGE_SIZE || !$this->isImageContentType($contentType)) {
            return '';
        }

        $extension = $this->detectImageExtension($url, $contentType);
        $upload = app('uploader')->storage();
        $fileName = uniqid('article_', true) . '.' . $extension;
        $res = $upload->to('file/admin/' . date('Ymd'))->validate()->stream($content, $fileName);
        if ($res === false) {
            Log::error('文章图片本地化上传失败：' . $upload->getError());
            return '';
        }

        $updateType = match ($res['updateType']) {
            UploaderService::LOCAL_UPLOADER => 1,
            UploaderService::COS_UPLOADER => 3,
            UploaderService::OSS_UPLOADER => 4,
            UploaderService::QINIU_UPLOADER => 2,
            default => 1,
        };

        app()->make(FileService::class)->create([
            'path' => $res['filePath'],
            'name' => $res['fileName'],
            'real_name' => $res['fileName'],
            'cate_id' => 1,
            'update_type' => $updateType,
        ]);

        if ($updateType === 1) {
            return db_config(ConfigKeyEnum::WEB_HOST) . $res['filePath'];
        }

        return (string)$res['filePath'];
    }

    /**
     * 解析跳转地址。
     *
     * @param string $url 跳转地址。
     * @param string $baseUrl 当前地址。
     * @return string
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return $url;
        }

        $host = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        if (str_starts_with($url, '//')) {
            return $base['scheme'] . ':' . $url;
        }
        if (str_starts_with($url, '/')) {
            return $host . $url;
        }

        $path = (string)($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $host . ($dir === '' ? '' : $dir) . '/' . ltrim($url, '/');
    }

    /**
     * 替换数组中的图片地址。
     *
     * @param array $images 图片数组。
     * @param array $replacements 替换映射。
     * @return array
     */
    private function replaceArrayImages(array $images, array $replacements): array
    {
        foreach ($images as $index => $image) {
            $image = (string)$image;
            if (isset($replacements[$image])) {
                $images[$index] = $replacements[$image];
            }
        }

        return array_values($images);
    }

    /**
     * 判断是否远程 HTTP 地址。
     *
     * 仅做格式校验，不执行 DNS 查询（防止 DNS 卡死导致队列 Worker 挂起）。
     *
     * @param string $url 地址。
     * @return bool
     */
    private function isRemoteHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }

        $host = (string)($parts['host'] ?? '');
        if ($host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            return false;
        }

        // 仅验证主机名格式（不执行 DNS 查询，防止 DNS 卡死导致 Worker 挂起）
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // 主机名必须包含至少一个点，防止裸主机名（如 http://internal/）
        return str_contains($host, '.') && preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/iD', $host) === 1;
    }

    /**
     * 判断响应类型是否图片。
     *
     * @param string $contentType 响应类型。
     * @return bool
     */
    private function isImageContentType(string $contentType): bool
    {
        return str_starts_with(strtolower(trim($contentType)), 'image/');
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
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
        ];
        $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        if (isset($map[$contentType])) {
            return $map[$contentType];
        }

        $extension = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? ($extension === 'jpeg' ? 'jpg' : $extension) : 'jpg';
    }

    /**
     * 获取 HTTP 代理地址。
     *
     * @return string
     */
    private function getProxyUrl(): string
    {
        $env = $_SERVER;
        $proxy = $env['HTTPS_PROXY'] ?? $env['https_proxy'] ?? $env['HTTP_PROXY'] ?? $env['http_proxy'] ?? '';
        if ($proxy !== '' && filter_var($proxy, FILTER_VALIDATE_URL) !== false) {
            return $proxy;
        }
        return '';
    }
}
