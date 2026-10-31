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

namespace app\common\job\store;

use app\common\job\BaseJob;
use app\common\job\Dispatchable;
use app\common\job\ShouldQueue;
use app\common\service\setting\FileService;
use app\common\service\store\StoreProductService;
use app\common\service\store\StoreProductSkuService;
use app\common\service\store\StoreProductSpecConfigService;
use think\facade\Log;

/**
 * 店铺商品图片本地化队列。
 */
class StoreProductImageLocalizeJob extends BaseJob implements ShouldQueue
{
    use Dispatchable;

    private const MAX_IMAGE_COUNT = 120;
    private const MAX_IMAGE_SIZE = 5242880;
    private const TIMEOUT = 30;

    /**
     * 本地化店铺商品和 SKU 的远程图片。
     *
     * 首次执行收集商品封面、轮播图和 SKU 图片，随后逐张下载并重新上传，
     * 最后统一回写商品表与 SKU 表中的图片地址。
     *
     * @param int $productId 店铺商品ID。
     * @param array $urls 待处理图片地址集合。
     * @param int $index 当前处理下标。
     * @param array $replacements 原始地址到新地址的替换映射。
     * @return void
     */
    public function handle(int $productId, array $urls = [], int $index = 0, array $replacements = []): void
    {
        if ($productId <= 0) {
            return;
        }

        try {
            if ($urls === []) {
                $urls = $this->collectRemoteImages($productId);
                if ($urls === []) {
                    return;
                }

                self::dispatch($productId, $urls, 0, []);
                return;
            }

            $index = max(0, $index);
            $url = (string) ($urls[$index] ?? '');
            if ($url !== '' && $this->isRemoteHttpUrl($url)) {
                try {
                    $localUrl = $this->downloadAndUploadImage($url);
                    if ($localUrl !== '') {
                        $replacements[$url] = $localUrl;
                    }
                } catch (\Throwable $exception) {
                    Log::error('店铺商品图片本地化单图失败：product_id=' . $productId . '|url=' . $url . '|error=' . $exception->getMessage());
                }
            }

            $nextIndex = $index + 1;
            if ($nextIndex < count($urls)) {
                self::dispatch($productId, $urls, $nextIndex, $replacements);
                return;
            }

            $this->finishProductLocalize($productId, $replacements);
        } catch (\Throwable $exception) {
            Log::error('店铺商品图片本地化失败：product_id=' . $productId . '|index=' . $index . '|error=' . $exception->getMessage());
        }
    }

    /**
     * 收集商品与 SKU 中的远程图片地址。
     *
     * @param int $productId 店铺商品ID。
     * @return array
     */
    private function collectRemoteImages(int $productId): array
    {
        $productService = app()->make(StoreProductService::class);
        $skuService = app()->make(StoreProductSkuService::class);
        $product = $productService->get($productId);
        if (!$product) {
            return [];
        }

        $productData = $product->toArray();
        $urls = [];
        $this->pushRemoteImage($urls, (string) ($productData['cover'] ?? ''));
        $this->pushRemoteImages($urls, is_array($productData['pics'] ?? null) ? $productData['pics'] : []);
        $this->pushRemoteImages($urls, $this->extractImagesFromHtml((string) ($productData['detail'] ?? '')));

        foreach ($skuService->getSkuList($productId) as $sku) {
            $this->pushRemoteImage($urls, (string) ($sku['pics'] ?? ''));
        }

        $config = app()->make(StoreProductSpecConfigService::class)->findByProductId($productId);
        foreach (is_array($config['custom_skus'] ?? null) ? $config['custom_skus'] : [] as $sku) {
            if (is_array($sku)) {
                $this->pushRemoteImage($urls, (string) ($sku['pics'] ?? ''));
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, self::MAX_IMAGE_COUNT);
    }

    /**
     * 完成本地化并回写商品数据。
     *
     * @param int $productId 店铺商品ID。
     * @param array $replacements 地址替换映射。
     * @return void
     */
    private function finishProductLocalize(int $productId, array $replacements): void
    {
        $productService = app()->make(StoreProductService::class);
        $skuService = app()->make(StoreProductSkuService::class);
        $product = $productService->get($productId);
        if (!$product) {
            return;
        }

        if ($replacements !== []) {
            $productData = $product->toArray();
            $update = [];

            $cover = (string) ($productData['cover'] ?? '');
            if ($cover !== '' && isset($replacements[$cover])) {
                $update['cover'] = $replacements[$cover];
            }

            $pics = $this->replaceArrayImages(is_array($productData['pics'] ?? null) ? $productData['pics'] : [], $replacements);
            if ($pics !== ($productData['pics'] ?? [])) {
                $update['pics'] = $pics;
            }

            $detail = strtr((string) ($productData['detail'] ?? ''), $replacements);
            if ($detail !== (string) ($productData['detail'] ?? '')) {
                $update['detail'] = $detail;
            }

            if ($update !== []) {
                $productService->update($productId, $update);
            }

            foreach ($skuService->getSkuList($productId) as $sku) {
                $picsValue = (string) ($sku['pics'] ?? '');
                if ($picsValue !== '' && isset($replacements[$picsValue])) {
                    $skuService->update((int) $sku['id'], ['pics' => $replacements[$picsValue]]);
                }
            }

            $this->finishSpecConfigLocalize($productId, $replacements);
        }
    }

    /**
     * 回写规格配置快照中的 SKU 图片地址。
     *
     * @param int $productId 店铺商品ID。
     * @param array $replacements 地址替换映射。
     * @return void
     */
    private function finishSpecConfigLocalize(int $productId, array $replacements): void
    {
        $configService = app()->make(StoreProductSpecConfigService::class);
        $config = $configService->findByProductId($productId);
        if ($config === []) {
            return;
        }

        $customSkus = is_array($config['custom_skus'] ?? null) ? $config['custom_skus'] : [];
        $updatedSkus = $this->replaceCustomSkuImages($customSkus, $replacements);
        if ($updatedSkus === $customSkus) {
            return;
        }

        $config['custom_skus'] = $updatedSkus;
        $configService->saveByProductId($productId, $config);
    }

    /**
     * 下载远程图片并上传到当前系统上传服务。
     *
     * @param string $url 远程图片地址。
     * @return string
     */
    private function downloadAndUploadImage(string $url): string
    {
        $content = '';
        $contentType = '';
        $currentUrl = $url;

        for ($i = 0; $i <= 3; $i++) {
            $location = '';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $currentUrl,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT => 'KnowledgePaymentBot/1.0',
                CURLOPT_HEADERFUNCTION => static function ($ch, string $headerLine) use (&$location): int {
                    if (stripos($headerLine, 'Location:') === 0) {
                        $location = trim(substr($headerLine, 9));
                    }
                    return strlen($headerLine);
                },
            ]);

            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 300 && $httpCode < 400 && $httpCode !== 304) {
                if ($location === '') {
                    Log::warning('店铺商品图片本地化跳转缺少 Location：url=' . $currentUrl . '|http_code=' . $httpCode);
                    return '';
                }

                $currentUrl = $this->resolveUrl($location, $currentUrl);
                if (!$this->isRemoteHttpUrl($currentUrl)) {
                    return '';
                }

                continue;
            }

            if (!is_string($body) || $body === '' || $httpCode >= 400 || !$this->isRemoteHttpUrl($currentUrl)) {
                Log::warning('店铺商品图片本地化下载失败：url=' . $currentUrl . '|http_code=' . $httpCode . '|error=' . $curlError);
                return '';
            }

            $content = $body;
            $contentType = $responseType;
            break;
        }

        if ($content === '') {
            return '';
        }

        if (strlen($content) > self::MAX_IMAGE_SIZE) {
            Log::warning('店铺商品图片本地化图片过大：url=' . $url . '|size=' . strlen($content));
            return '';
        }

        if (!$this->isImageContent($contentType, $content)) {
            Log::warning('店铺商品图片本地化响应不是图片：url=' . $url . '|content_type=' . $contentType);
            return '';
        }

        $extension = $this->detectImageExtension($url, $contentType);
        $upload = app('uploader')->storage();
        $fileName = uniqid('product_', true) . '.' . $extension;
        $uploaded = $upload->to('file/admin/' . date('Ymd'))->validate()->stream($content, $fileName);
        if ($uploaded === false) {
            Log::error('店铺商品图片本地化上传失败：' . $upload->getError());
            return '';
        }

        $fileService = app()->make(FileService::class);
        $file = $fileService->recordUploadedFile($uploaded, 0);
        $fileInfo = $fileService->formatFileRecord($file);

        return (string) ($fileInfo['path'] ?? $fileInfo['filePath'] ?? '');
    }

    /**
     * 解析相对跳转地址。
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

        $path = (string) ($base['path'] ?? '/');
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $host . ($dir === '' ? '' : $dir) . '/' . ltrim($url, '/');
    }

    /**
     * 批量替换数组中的图片地址。
     *
     * @param array $images 图片数组。
     * @param array $replacements 替换映射。
     * @return array
     */
    private function replaceArrayImages(array $images, array $replacements): array
    {
        foreach ($images as $index => $image) {
            $image = (string) $image;
            if (isset($replacements[$image])) {
                $images[$index] = $replacements[$image];
            }
        }

        return array_values($images);
    }

    /**
     * 替换规格配置快照中的 SKU 图片。
     *
     * @param array $skus SKU配置列表。
     * @param array $replacements 地址替换映射。
     * @return array
     */
    private function replaceCustomSkuImages(array $skus, array $replacements): array
    {
        foreach ($skus as $index => $sku) {
            if (!is_array($sku)) {
                continue;
            }

            $pics = (string) ($sku['pics'] ?? '');
            if ($pics !== '' && isset($replacements[$pics])) {
                $skus[$index]['pics'] = $replacements[$pics];
            }
        }

        return array_values($skus);
    }

    /**
     * 提取 HTML 图片地址。
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
     * 追加远程图片地址。
     *
     * @param array $urls 地址集合。
     * @param string $url 图片地址。
     * @return void
     */
    private function pushRemoteImage(array &$urls, string $url): void
    {
        $url = trim($url);
        if ($this->isRemoteHttpUrl($url) && !$this->isManagedImageUrl($url)) {
            $urls[] = $url;
        }
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
            $this->pushRemoteImage($urls, (string) $image);
        }
    }

    /**
     * 判断图片是否已在系统文件库登记。
     *
     * @param string $url 图片地址。
     * @return bool
     */
    private function isManagedImageUrl(string $url): bool
    {
        $candidates = [$url];
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
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
     * 判断是否远程 HTTP 地址。
     *
     * @param string $url 地址。
     * @return bool
     */
    private function isRemoteHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return false;
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return str_contains($host, '.') && preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/iD', $host) === 1;
    }

    /**
     * 判断响应类型是否为图片。
     *
     * @param string $contentType 响应类型。
     * @return bool
     */
    private function isImageContentType(string $contentType): bool
    {
        return str_starts_with(strtolower(trim($contentType)), 'image/');
    }

    /**
     * 判断响应内容是否为图片。
     *
     * @param string $contentType 响应类型。
     * @param string $content 响应内容。
     * @return bool
     */
    private function isImageContent(string $contentType, string $content): bool
    {
        if ($this->isImageContentType($contentType)) {
            return true;
        }

        return @getimagesizefromstring($content) !== false;
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

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? ($extension === 'jpeg' ? 'jpg' : $extension) : 'jpg';
    }
}
