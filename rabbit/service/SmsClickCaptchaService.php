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

namespace Rabbit\service;

use app\common\enum\ConfigKeyEnum;
use app\common\exception\ApiValidateException;
use app\common\job\sms\SmsClickCaptchaWarmBackgroundJob;
use app\common\service\cs\AiImageGeneratorService;
use app\common\service\sms\SmsService;
use Rabbit\util\Lock;
use Random\RandomException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 短信点击文字验证服务。
 * @package Rabbit\service
 */
class SmsClickCaptchaService
{
    /**
     * 验证场景缓存前缀，拼接场景 token 后缓存本次点击验证所需的完整场景数据。
     */
    private const SCENE_CACHE_PREFIX = 'sms_click_captcha_scene:';

    /**
     * 背景图资源池缓存键，缓存当前可直接复用的背景图列表。
     */
    private const BACKGROUND_POOL_CACHE_KEY = 'sms_click_captcha_background_pool:v1';

    /**
     * 按手机号记录“创建验证场景”请求次数的缓存前缀。
     */
    private const SCENE_REQUEST_PHONE_PREFIX = 'sms_click_captcha_scene_phone:';

    /**
     * 按 IP 记录“创建验证场景”请求次数的缓存前缀。
     */
    private const SCENE_REQUEST_IP_PREFIX = 'sms_click_captcha_scene_ip:';

    /**
     * 按手机号记录验证失败次数的缓存前缀，用于限制暴力尝试。
     */
    private const VERIFY_FAIL_PHONE_PREFIX = 'sms_click_captcha_verify_fail_phone:';

    /**
     * 按 IP 记录验证失败次数的缓存前缀，用于限制同源频繁试错。
     */
    private const VERIFY_FAIL_IP_PREFIX = 'sms_click_captcha_verify_fail_ip:';

    /**
     * 单个验证场景的并发处理锁前缀，避免同一 scene_token 并发重复通过。
     */
    private const VERIFY_SCENE_LOCK_PREFIX = 'sms_click_captcha_verify_scene:';

    /**
     * 单个验证场景的有效期，超时后必须重新获取场景。
     */
    private const SCENE_EXPIRE_SECONDS = 300;

    /**
     * 背景图资源池缓存时长，减少频繁重新生成背景图的开销。
     */
    private const BACKGROUND_POOL_EXPIRE_SECONDS = 86400;

    /**
     * 创建验证场景的限流统计窗口时长。
     */
    private const SCENE_REQUEST_WINDOW_SECONDS = 300;

    /**
     * 同一手机号在限流窗口内允许创建场景的最大次数。
     */
    private const SCENE_REQUEST_PHONE_MAX = 8;

    /**
     * 同一 IP 在限流窗口内允许创建场景的最大次数。
     */
    private const SCENE_REQUEST_IP_MAX = 30;

    /**
     * 验证失败次数统计窗口时长。
     */
    private const VERIFY_FAIL_WINDOW_SECONDS = 600;

    /**
     * 同一手机号在失败统计窗口内允许失败的最大次数。
     */
    private const VERIFY_FAIL_PHONE_MAX = 8;

    /**
     * 同一 IP 在失败统计窗口内允许失败的最大次数。
     */
    private const VERIFY_FAIL_IP_MAX = 20;

    /**
     * 单个验证场景处理锁过期时间。
     */
    private const VERIFY_SCENE_LOCK_SECONDS = 8;

    /**
     * 背景图资源池维持的最小数量，低于该值时需要补充预热。
     */
    private const BACKGROUND_POOL_MIN_SIZE = 6;

    /**
     * 背景图资源池允许保留的最大数量，避免资源堆积（对齐 AiImageGeneratorService 存储上限 10 张）。
     */
    private const BACKGROUND_POOL_MAX_SIZE = 10;

    /**
     * 背景图在存储系统中的目录。
     */
    private const BACKGROUND_STORAGE_DIRECTORY = 'sms/click_captcha/backgrounds';

    /**
     * 生成后的场景图在存储系统中的目录。
     */
    private const SCENE_STORAGE_DIRECTORY = 'sms/click_captcha/scenes';

    /**
     * 背景图预热互斥锁缓存键，避免并发重复预热。
     */
    private const BACKGROUND_WARM_LOCK_KEY = 'sms_click_captcha_background_warm_lock';

    /**
     * 允许作为背景图来源的图片扩展名白名单。
     *
     * @var string[]
     */
    private const ALLOWED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * 背景图预热锁的过期时间，防止异常情况下锁长期不释放。
     */
    private const BACKGROUND_WARM_LOCK_SECONDS = 300;

    /**
     * 验证画布宽度。
     */
    private const CANVAS_WIDTH = 600;

    /**
     * 验证画布高度。
     */
    private const CANVAS_HEIGHT = 320;

    /**
     * 需要用户按顺序点击的目标文字数量。
     */
    private const TARGET_COUNT = 3;

    /**
     * 单个场景中投放的总文字数量，包含目标文字与干扰文字。
     */
    private const ITEM_COUNT = 6;

    /**
     * 单个文字元素允许的最小宽度。
     */
    private const ITEM_MIN_WIDTH = 84;

    /**
     * 单个文字元素允许的最大宽度。
     */
    private const ITEM_MAX_WIDTH = 112;

    /**
     * 单个文字元素允许的最小高度。
     */
    private const ITEM_MIN_HEIGHT = 78;

    /**
     * 单个文字元素允许的最大高度。
     */
    private const ITEM_MAX_HEIGHT = 102;

    /**
     * 元素布局时距画布左右边缘的最小安全边距。
     */
    private const POSITION_PADDING_X = 56;

    /**
     * 元素布局时距画布上下边缘的最小安全边距。
     */
    private const POSITION_PADDING_Y = 52;

    /**
     * 不同文字元素之间的最小间距，避免遮挡重叠。
     */
    private const POSITION_GAP = 22;

    /**
     * 点击命中容差，允许用户点击位置与目标中心存在一定偏移。
     * 收紧容差以提高自动化脚本的定位精度要求。
     */
    private const HIT_SLOP = 8;

    /**
     * 文字颜色色板（深色系，保证浅色背景下可辨识度）。
     * 每个文字随机取色，避免单一固定颜色被颜色分割直接定位。
     *
     * @var array<int, array{int, int, int}>
     */
    private const TEXT_COLOR_PALETTE = [
        [30, 30, 30],      // 墨黑
        [33, 41, 55],      // 深蓝
        [122, 38, 38],     // 深红
        [32, 88, 50],      // 深绿
        [92, 42, 112],     // 深紫
        [110, 62, 28],     // 深棕
        [22, 82, 92],      // 暗青
        [46, 52, 122],     // 靛蓝
    ];

    /**
     * 可选文字池。
     *
     * 只保留常见且差异较明显的汉字，避免过多形近字影响体验。
     *
     * @var string[]
     */
    private array $wordPool = [
        '山', '水', '云', '月', '风', '花', '桥', '舟', '林', '茶',
        '雪', '星', '灯', '竹', '泉', '石', '鹿', '野', '海', '霞',
        '雨', '荷', '柳', '松', '雾', '岚', '鱼', '鹤', '琴', '书',
        '湖', '溪', '沙', '麦', '谷', '果', '桃', '梨', '橙', '梅',
        '兰', '菊', '莲', '柏', '枫', '枝', '叶', '藤', '苔', '钟',
        '鼓', '伞', '扇', '窗', '门', '庭', '院', '巷', '塔', '城',
        '池', '岸', '滩', '港', '帆', '岛', '潮', '浪', '汀', '燕',
        '鸿', '雁', '鹭', '雀', '蝶', '蜂', '蝉', '狐', '熊', '马',
        '牛', '羊', '象', '猫', '犬', '兔', '龟', '龙', '锦', '玉',
        '金', '银', '珠', '贝', '瓷', '陶', '墨', '纸', '笔', '卷',
        '棋', '笛', '弦', '杯', '盘', '镜', '铃', '炉', '席', '案',
        '鼎', '印', '帘', '纱', '绣', '晨', '暮', '晓', '夕', '霜',
        '露', '晴', '暖', '寒', '秋', '夏', '春', '冬', '夜', '川',
        '峰', '岩', '谷', '渚', '溪', '渔', '汐', '沐', '岫', '岱',
    ];

    /**
     * 创建验证场景。
     *
     * @param string $phone 手机号。
     * @return array
     * @throws RandomException
     */
    public function createScene(string $phone): array
    {
        $phone = trim($phone);
        $this->assertPhone($phone);
        $this->assertSceneRequestAllowed($phone);
        $this->assertVerifyAllowed($phone);

        $sceneToken = bin2hex(random_bytes(16));
        $scene = $this->buildScene($phone, $sceneToken);

        Cache::set(self::SCENE_CACHE_PREFIX . $sceneToken, $scene['payload'], self::SCENE_EXPIRE_SECONDS);
        $this->markSceneRequest($phone);
        $this->cleanupExpiredSceneImages();

        return array_merge($scene['response'], [
            'scene_token' => $sceneToken,
            'expire_seconds' => self::SCENE_EXPIRE_SECONDS,
        ]);
    }

    /**
     * 校验点击顺序并发送短信。
     *
     * @param string $phone 手机号。
     * @param string $sceneToken 场景令牌。
     * @param array $selectedPoints 用户点击坐标。
     * @return array
     * @throws \Exception
     */
    public function verifyAndSendCode(string $phone, string $sceneToken, array $selectedPoints): array
    {
        $phone = trim($phone);
        $sceneToken = trim($sceneToken);
        $selectedPoints = $this->normalizeSelectedPoints($selectedPoints);

        $this->assertPhone($phone);
        $this->assertVerifyAllowed($phone);

        if ($sceneToken === '') {
            throw new ApiValidateException('验证场景不存在，请刷新后重试');
        }

        if ($selectedPoints === []) {
            throw new ApiValidateException('请先完成安全验证');
        }

        /** @var Lock $lock */
        $lock = app()->make(Lock::class);
        $lockKey = self::VERIFY_SCENE_LOCK_PREFIX . $sceneToken;
        $lockValue = bin2hex(random_bytes(16));

        try {
            if (!$lock->tryLock($lockKey, $lockValue, self::VERIFY_SCENE_LOCK_SECONDS)) {
                throw new ApiValidateException('验证处理中，请勿重复提交');
            }
        } catch (ApiValidateException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('短信点击验证场景锁获取失败', [
                'scene_token' => $sceneToken,
                'message' => $exception->getMessage(),
            ]);
            throw new ApiValidateException('验证服务繁忙，请稍后重试');
        }

        try {
            $cacheKey = self::SCENE_CACHE_PREFIX . $sceneToken;
            $scene = Cache::get($cacheKey);
            if (!is_array($scene)) {
                $this->markVerifyFailure($phone);
                throw new ApiValidateException('验证场景已失效，请刷新后重试');
            }

            if ((string) ($scene['phone'] ?? '') !== $phone) {
                $this->consumeScene($cacheKey, $scene);
                $this->markVerifyFailure($phone);
                throw new ApiValidateException('验证场景与当前手机号不匹配，请刷新后重试');
            }

            $answerAreas = array_values((array) ($scene['answer_areas'] ?? []));
            if (count($selectedPoints) !== count($answerAreas) || !$this->isSelectedPointsValid($selectedPoints, $answerAreas)) {
                $this->consumeScene($cacheKey, $scene);
                $this->markVerifyFailure($phone);
                throw new ApiValidateException('点击顺序不正确，请刷新后重试');
            }

            $this->consumeScene($cacheKey, $scene);
            $this->clearVerifyFailure($phone);
            app()->make(SmsService::class)->sendVerificationCode($phone);

            return [
                'sent' => true,
                'countdown_seconds' => 60,
                'scene_consumed' => true,
            ];
        } finally {
            try {
                $lock->unlock($lockKey, $lockValue);
            } catch (\Throwable $exception) {
                Log::warning('短信点击验证场景锁释放失败', [
                    'scene_token' => $sceneToken,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * 构建场景数据。
     *
     * @param string $phone 手机号。
     * @param string $sceneToken 场景令牌。
     * @return array
     * @throws RandomException
     */
    private function buildScene(string $phone, string $sceneToken): array
    {
        $targets = $this->pickWords(self::TARGET_COUNT);
        $distractors = $this->pickWords(self::ITEM_COUNT - self::TARGET_COUNT, $targets);
        $texts = array_merge($targets, $distractors);
        shuffle($texts);

        $positions = $this->pickPositions(count($texts));
        $sceneItems = [];
        $textAreaMap = [];

        foreach ($texts as $index => $text) {
            $position = $positions[$index] ?? [
                'x' => 90,
                'y' => 90,
                'rotate' => 0,
                'font_size' => 44,
                'width' => 92,
                'height' => 88,
            ];

            $item = [
                'text' => $text,
                'x' => (int) $position['x'],
                'y' => (int) $position['y'],
                'rotate' => (int) $position['rotate'],
                'font_size' => (int) ($position['font_size'] ?? 46),
                'width' => (int) ($position['width'] ?? 96),
                'height' => (int) ($position['height'] ?? 92),
            ];
            $sceneItems[] = $item;
            $textAreaMap[$text] = [
                'x' => $item['x'],
                'y' => $item['y'],
                'width' => $item['width'],
                'height' => $item['height'],
            ];
        }

        $answerAreas = [];
        foreach ($targets as $target) {
            if (isset($textAreaMap[$target])) {
                $answerAreas[] = $textAreaMap[$target];
            }
        }

        $sceneImage = $this->renderSceneImage($sceneToken, $sceneItems, $targets);

        return [
            'payload' => [
                'phone' => $phone,
                'answer_areas' => $answerAreas,
                'scene_image_relative_path' => $sceneImage['relative_path'],
            ],
            'response' => [
                'instruction_text' => '请依次点击以下文字',
                'targets' => $targets,
                'background_url' => db_config(ConfigKeyEnum::WEB_HOST) . $sceneImage['url'],
                'canvas_width' => self::CANVAS_WIDTH,
                'canvas_height' => self::CANVAS_HEIGHT,
            ],
        ];
    }

    /**
     * 挑选随机文字。
     *
     * @param int $count 数量。
     * @param array $exclude 排除项。
     * @return array
     */
    private function pickWords(int $count, array $exclude = []): array
    {
        if ($count <= 0) {
            return [];
        }

        $pool = array_values(array_unique(array_diff($this->wordPool, $exclude)));
        shuffle($pool);

        return array_slice($pool, 0, min($count, count($pool)));
    }

    /**
     * 生成随机且尽量不重叠的点击区域坐标。
     *
     * @param int $count 项目数量。
     * @return array
     * @throws RandomException
     */
    private function pickPositions(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $positions = [];
        $maxAttempts = 120;

        for ($index = 0; $index < $count; $index++) {
            $position = null;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $width = random_int(self::ITEM_MIN_WIDTH, self::ITEM_MAX_WIDTH);
                $height = random_int(self::ITEM_MIN_HEIGHT, self::ITEM_MAX_HEIGHT);
                $x = random_int(self::POSITION_PADDING_X + (int) ceil($width / 2), self::CANVAS_WIDTH - self::POSITION_PADDING_X - (int) ceil($width / 2));
                $y = random_int(self::POSITION_PADDING_Y + (int) ceil($height / 2), self::CANVAS_HEIGHT - self::POSITION_PADDING_Y - (int) ceil($height / 2));
                $candidate = [
                    'x' => $x,
                    'y' => $y,
                    'rotate' => random_int(-18, 18),
                    'font_size' => random_int(42, 52),
                    'width' => $width,
                    'height' => $height,
                ];

                if ($this->isPositionAvailable($candidate, $positions)) {
                    $position = $candidate;
                    break;
                }
            }

            if ($position === null) {
                // 尝试耗尽后仍生成随机坐标，避免使用固定点位导致位置可预测
                $width = random_int(self::ITEM_MIN_WIDTH, self::ITEM_MAX_WIDTH);
                $height = random_int(self::ITEM_MIN_HEIGHT, self::ITEM_MAX_HEIGHT);
                $position = [
                    'x' => random_int(self::POSITION_PADDING_X + (int) ceil($width / 2), self::CANVAS_WIDTH - self::POSITION_PADDING_X - (int) ceil($width / 2)),
                    'y' => random_int(self::POSITION_PADDING_Y + (int) ceil($height / 2), self::CANVAS_HEIGHT - self::POSITION_PADDING_Y - (int) ceil($height / 2)),
                    'rotate' => random_int(-18, 18),
                    'font_size' => random_int(42, 52),
                    'width' => $width,
                    'height' => $height,
                ];
            }

            $positions[] = $position;
        }

        return $positions;
    }

    /**
     * 判断坐标是否可用。
     *
     * @param array $candidate 候选坐标。
     * @param array $positions 已有坐标。
     * @return bool
     */
    private function isPositionAvailable(array $candidate, array $positions): bool
    {
        $candidateLeft = (int) $candidate['x'] - (int) ceil(((int) $candidate['width'] + self::POSITION_GAP) / 2);
        $candidateRight = (int) $candidate['x'] + (int) ceil(((int) $candidate['width'] + self::POSITION_GAP) / 2);
        $candidateTop = (int) $candidate['y'] - (int) ceil(((int) $candidate['height'] + self::POSITION_GAP) / 2);
        $candidateBottom = (int) $candidate['y'] + (int) ceil(((int) $candidate['height'] + self::POSITION_GAP) / 2);

        foreach ($positions as $position) {
            $left = (int) $position['x'] - (int) ceil(((int) $position['width'] + self::POSITION_GAP) / 2);
            $right = (int) $position['x'] + (int) ceil(((int) $position['width'] + self::POSITION_GAP) / 2);
            $top = (int) $position['y'] - (int) ceil(((int) $position['height'] + self::POSITION_GAP) / 2);
            $bottom = (int) $position['y'] + (int) ceil(((int) $position['height'] + self::POSITION_GAP) / 2);

            $hasOverlap = $candidateLeft < $right
                && $candidateRight > $left
                && $candidateTop < $bottom
                && $candidateBottom > $top;
            if ($hasOverlap) {
                return false;
            }
        }

        return true;
    }

    /**
     * 渲染验证码场景位图。
     *
     * @param string $sceneToken 场景令牌。
     * @param array $items 场景文字项。
     * @param array $targets 目标文字。
     * @return array
     */
    private function renderSceneImage(string $sceneToken, array $items, array $targets): array
    {
        $backgroundUrl = $this->pickBackground();
        $canvas = $this->createBackgroundCanvas($backgroundUrl, implode('', $targets) . '|' . $sceneToken);
        $fontFile = $this->resolveFontFile();

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        // 先绘制干扰元素（噪点与干扰线），文字绘制在干扰层之上
        $this->drawNoiseAndLines($canvas);

        foreach ($items as $item) {
            $centerX = (int) ($item['x'] ?? 0);
            $centerY = (int) ($item['y'] ?? 0);
            $fontSize = (int) ($item['font_size'] ?? 46);
            $rotate = (int) ($item['rotate'] ?? 0);
            $text = (string) ($item['text'] ?? '');

            // 每个文字随机取色，防止固定单色被颜色分割脚本直接定位
            [$textR, $textG, $textB] = self::TEXT_COLOR_PALETTE[random_int(0, count(self::TEXT_COLOR_PALETTE) - 1)];
            $shadowColor = imagecolorallocatealpha($canvas, 255, 255, 255, 88);
            $textColor = imagecolorallocatealpha($canvas, $textR, $textG, $textB, random_int(0, 10));

            $box = imagettfbbox($fontSize, $rotate, $fontFile, $text);
            $textWidth = $this->getBoundingBoxWidth($box);
            $textHeight = $this->getBoundingBoxHeight($box);
            $textX = (int) round($centerX - ($textWidth / 2));
            $textY = (int) round($centerY + ($textHeight / 2) - 4);

            imagettftext($canvas, $fontSize, $rotate, $textX + 2, $textY + 2, $shadowColor, $fontFile, $text);
            imagettftext($canvas, $fontSize, $rotate, $textX, $textY, $textColor, $fontFile, $text);
        }

        $storage = $this->resolveSceneStorage();
        if (!is_dir($storage['path'])) {
            mkdir($storage['path'], 0777, true);
        }

        $filename = $sceneToken . '.png';
        $filePath = $storage['path'] . DIRECTORY_SEPARATOR . $filename;
        imagepng($canvas, $filePath, 6);
        imagedestroy($canvas);

        return [
            'url' => $storage['url'] . '/' . $filename,
            'relative_path' => self::SCENE_STORAGE_DIRECTORY . '/' . $filename,
        ];
    }

    /**
     * 获取文字边界框宽度。
     *
     * @param array $box 边界框。
     * @return int
     */
    private function getBoundingBoxWidth(array $box): int
    {
        return (int) (max($box[2], $box[4]) - min($box[0], $box[6]));
    }

    /**
     * 获取文字边界框高度。
     *
     * @param array $box 边界框。
     * @return int
     */
    private function getBoundingBoxHeight(array $box): int
    {
        return (int) (max($box[1], $box[3]) - min($box[5], $box[7]));
    }

    /**
     * 绘制干扰元素（噪点与干扰线）。
     *
     * 噪点与干扰线为随机半透明色，避免单一颜色聚类定位文字，同时不遮挡文字可读性。
     *
     * @param \GdImage $canvas 画布。
     * @return void
     */
    private function drawNoiseAndLines(\GdImage $canvas): void
    {
        $noiseCount = random_int(320, 480);
        for ($i = 0; $i < $noiseCount; $i++) {
            $x = random_int(0, self::CANVAS_WIDTH - 1);
            $y = random_int(0, self::CANVAS_HEIGHT - 1);
            $gray = random_int(0, 255);
            imagesetpixel($canvas, $x, $y, imagecolorallocatealpha($canvas, $gray, $gray, $gray, random_int(30, 100)));
        }

        $lineCount = random_int(8, 14);
        for ($i = 0; $i < $lineCount; $i++) {
            $x1 = random_int(0, self::CANVAS_WIDTH);
            $y1 = random_int(0, self::CANVAS_HEIGHT);
            $length = random_int(24, 90);
            $angle = random_int(0, 359) * M_PI / 180;
            $x2 = (int) round($x1 + $length * cos($angle));
            $y2 = (int) round($y1 + $length * sin($angle));
            $r = random_int(0, 200);
            $g = random_int(0, 200);
            $b = random_int(0, 200);
            imageline($canvas, $x1, $y1, $x2, $y2, imagecolorallocatealpha($canvas, $r, $g, $b, random_int(60, 110)));
        }
    }

    /**
     * 创建背景画布。
     *
     * 背景图先随机放大再随机裁剪窗口并施加轻微随机滤镜，
     * 使同一背景图在不同场景中呈现不同裁切与色调，打破固定背景的先验。
     *
     * @param string $backgroundUrl 背景地址。
     * @param string $seed 随机种子。
     * @return \GdImage
     */
    private function createBackgroundCanvas(string $backgroundUrl, string $seed): \GdImage
    {
        $canvas = imagecreatetruecolor(self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $background = $this->createImageFromBackground($backgroundUrl);
        if ($background !== null) {
            $srcW = imagesx($background);
            $srcH = imagesy($background);
            // 随机放大 1.0~1.8 倍后随机裁窗，保证同一背景不同裁切
            $scale = 1.0 + (random_int(0, 80) / 100);
            $zoomW = max(self::CANVAS_WIDTH, (int) round($srcW * $scale));
            $zoomH = max(self::CANVAS_HEIGHT, (int) round($srcH * $scale));
            $scaled = imagecreatetruecolor($zoomW, $zoomH);
            imagecopyresampled($scaled, $background, 0, 0, 0, 0, $zoomW, $zoomH, $srcW, $srcH);
            $offsetX = $zoomW > self::CANVAS_WIDTH ? random_int(0, $zoomW - self::CANVAS_WIDTH) : 0;
            $offsetY = $zoomH > self::CANVAS_HEIGHT ? random_int(0, $zoomH - self::CANVAS_HEIGHT) : 0;
            imagecopy($canvas, $scaled, 0, 0, $offsetX, $offsetY, self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
            imagedestroy($scaled);
            imagedestroy($background);

            // 轻微随机滤镜：轻微调整亮度或对比度
            $filter = random_int(0, 2);
            if ($filter === 0) {
                imagefilter($canvas, IMG_FILTER_BRIGHTNESS, random_int(-12, 12));
            } elseif ($filter === 1) {
                imagefilter($canvas, IMG_FILTER_CONTRAST, random_int(-8, 8));
            }
        } else {
            $this->drawFallbackBackground($canvas, $seed);
        }

        return $canvas;
    }

    /**
     * 从背景地址创建图像资源。
     *
     * @param string $backgroundUrl 背景地址。
     * @return mixed
     */
    private function createImageFromBackground(string $backgroundUrl)
    {
        $backgroundUrl = trim($backgroundUrl);
        if ($backgroundUrl === '') {
            return null;
        }

        $filePath = '';
        if (str_starts_with($backgroundUrl, '/')) {
            $filePath = public_path() . ltrim($backgroundUrl, '/');
        }

        if ($filePath === '' || !is_file($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if (!is_string($content) || $content === '') {
            return null;
        }

        $image = @imagecreatefromstring($content);
        if ($image === false || $image === null) {
            return null;
        }

        return $image;
    }

    /**
     * 绘制兜底渐变背景。
     *
     * @param \GdImage $canvas 画布。
     * @param string $seed 随机种子。
     * @return void
     */
    private function drawFallbackBackground(\GdImage $canvas, string $seed): void
    {
        $palettes = [
            [[248, 239, 227], [243, 208, 177], [240, 177, 132], [115, 71, 43]],
            [[238, 243, 251], [200, 218, 246], [158, 187, 241], [53, 90, 139]],
            [[241, 239, 232], [221, 212, 191], [178, 198, 171], [83, 96, 77]],
            [[248, 238, 244], [239, 198, 217], [212, 181, 246], [116, 80, 140]],
        ];

        $palette = $palettes[abs(crc32($seed)) % count($palettes)];
        for ($y = 0; $y < self::CANVAS_HEIGHT; $y++) {
            $ratio = $y / max(1, self::CANVAS_HEIGHT - 1);
            $r = (int) round($palette[0][0] + (($palette[1][0] - $palette[0][0]) * $ratio));
            $g = (int) round($palette[0][1] + (($palette[1][1] - $palette[0][1]) * $ratio));
            $b = (int) round($palette[0][2] + (($palette[1][2] - $palette[0][2]) * $ratio));
            imageline($canvas, 0, $y, self::CANVAS_WIDTH, $y, imagecolorallocate($canvas, $r, $g, $b));
        }

        imagefilledellipse($canvas, 110, 92, 170, 170, imagecolorallocatealpha($canvas, $palette[2][0], $palette[2][1], $palette[2][2], 92));
        imagefilledellipse($canvas, 510, 68, 180, 180, imagecolorallocatealpha($canvas, 255, 255, 255, 98));
        imagefilledellipse($canvas, 506, 264, 220, 200, imagecolorallocatealpha($canvas, $palette[3][0], $palette[3][1], $palette[3][2], 108));
        imagefilledarc($canvas, 288, 338, 780, 224, 180, 360, imagecolorallocatealpha($canvas, 255, 255, 255, 84), IMG_ARC_PIE);
        imagefilledarc($canvas, 176, 346, 860, 180, 180, 360, imagecolorallocatealpha($canvas, 255, 255, 255, 108), IMG_ARC_PIE);
    }

    /**
     * 解析字体文件。
     *
     * @return string
     */
    private function resolveFontFile(): string
    {
        $candidates = [
            app()->getRootPath() . 'public/storage/font/SourceHanSansCN-Normal.ttf',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        throw new ApiValidateException('验证码字体资源不存在，请联系管理员');
    }

    /**
     * 获取背景图。
     *
     * @return string
     */
    private function pickBackground(): string
    {
        $pool = $this->getBackgroundPool();
        if ($pool === []) {
            return '';
        }

        return (string) $pool[array_rand($pool)];
    }

    /**
     * 获取背景池。
     *
     * 请求链路只读取本地已生成图片，不再同步调用 AI 生图。
     * 当库存不足时，仅异步投递补池任务。
     *
     * @return array
     */
    private function getBackgroundPool(): array
    {
        $pool = $this->getLocalBackgrounds(self::BACKGROUND_STORAGE_DIRECTORY);

        if (count($pool) < self::BACKGROUND_POOL_MIN_SIZE) {
            $this->dispatchWarmBackgroundPool();
        }

        $pool = array_slice(array_values(array_unique($pool)), 0, self::BACKGROUND_POOL_MAX_SIZE);
        Cache::set(self::BACKGROUND_POOL_CACHE_KEY, $pool, self::BACKGROUND_POOL_EXPIRE_SECONDS);

        return $pool;
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
            return self::BACKGROUND_STORAGE_DIRECTORY;
        }

        $segments = array_values(array_filter(explode('/', $directory), static function ($segment) {
            return $segment !== '' && $segment !== '.' && $segment !== '..';
        }));

        return $segments === [] ? self::BACKGROUND_STORAGE_DIRECTORY : implode('/', $segments);
    }

    /**
     * 预热本地背景图目录。
     *
     * @param int $targetCount 目标图片数量。
     * @return array
     */
    public function warmBackgroundPool(int $targetCount = 0): array
    {
        $prompts = [
            'soft abstract chinese ink wash background, warm light, elegant texture, no text, no watermark, no letters',
            'minimal poetic landscape background, gentle cloud and mountain tones, chinese aesthetic, no text, no watermark',
            'paper texture artistic background, calm warm colors, premium mobile verification wallpaper, no text, no letters',
            'clean modern oriental abstract illustration background, soft gradient, no text, no watermark',
            'subtle nature inspired background with cloud, lake and light, calm composition, no text, no letters',
        ];

        /** @var AiImageGeneratorService $imageService */
        $imageService = app()->make(AiImageGeneratorService::class);
        $maxCount = $imageService->getMaxLocalBackgroundCount();
        $targetCount = $targetCount > 0 ? min(max(1, $targetCount), $maxCount) : $maxCount;

        $generatedCount = 0;
        try {
            $localPool = $imageService->getLocalBackgrounds(self::BACKGROUND_STORAGE_DIRECTORY);
            $existingCount = count($localPool);
            $remainCount = max(0, $targetCount - $existingCount);

            for ($index = 0; $index < $remainCount; $index++) {
                $prompt = $prompts[$index % count($prompts)];
                $url = $imageService->generateBackground($prompt, [
                    'size' => '1024x1024',
                    'directory' => self::BACKGROUND_STORAGE_DIRECTORY,
                ]);
                if (!is_string($url) || trim($url) === '') {
                    Log::info('短信点击验证背景图预热生成失败', ['index' => $index]);
                    continue;
                }

                $generatedCount++;
            }

            $latestPool = $imageService->getLocalBackgrounds(self::BACKGROUND_STORAGE_DIRECTORY);
            Cache::set(
                self::BACKGROUND_POOL_CACHE_KEY,
                array_slice(array_values(array_unique($latestPool)), 0, self::BACKGROUND_POOL_MAX_SIZE),
                self::BACKGROUND_POOL_EXPIRE_SECONDS
            );

            return [
                'target_count' => $targetCount,
                'existing_count' => $existingCount,
                'generated_count' => $generatedCount,
                'current_count' => count($latestPool),
            ];
        } finally {
            $this->releaseWarmBackgroundPoolLock();
        }
    }

    /**
     * 异步投递背景图预热任务。
     *
     * @return void
     */
    private function dispatchWarmBackgroundPool(): void
    {
        if (Cache::get(self::BACKGROUND_WARM_LOCK_KEY)) {
            return;
        }

        Cache::set(self::BACKGROUND_WARM_LOCK_KEY, 1, self::BACKGROUND_WARM_LOCK_SECONDS);

        try {
            SmsClickCaptchaWarmBackgroundJob::dispatch();
        } catch (\Throwable $exception) {
            $this->releaseWarmBackgroundPoolLock();
            Log::warning('短信点击验证背景图预热任务投递失败', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 释放背景图预热锁。
     *
     * @return void
     */
    private function releaseWarmBackgroundPoolLock(): void
    {
        Cache::delete(self::BACKGROUND_WARM_LOCK_KEY);
    }

    /**
     * 校验获取场景的频率限制。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function assertSceneRequestAllowed(string $phone): void
    {
        $ip = $this->getRequestIp();
        if ($this->getCounter(self::SCENE_REQUEST_PHONE_PREFIX . $phone) >= self::SCENE_REQUEST_PHONE_MAX) {
            throw new ApiValidateException('验证获取过于频繁，请稍后再试');
        }

        if ($this->getCounter(self::SCENE_REQUEST_IP_PREFIX . $ip) >= self::SCENE_REQUEST_IP_MAX) {
            throw new ApiValidateException('当前设备请求过于频繁，请稍后再试');
        }
    }

    /**
     * 记录获取场景次数。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function markSceneRequest(string $phone): void
    {
        $ip = $this->getRequestIp();
        $this->incrementCounter(self::SCENE_REQUEST_PHONE_PREFIX . $phone, self::SCENE_REQUEST_WINDOW_SECONDS);
        $this->incrementCounter(self::SCENE_REQUEST_IP_PREFIX . $ip, self::SCENE_REQUEST_WINDOW_SECONDS);
    }

    /**
     * 校验失败次数限制。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function assertVerifyAllowed(string $phone): void
    {
        $ip = $this->getRequestIp();
        if ($this->getCounter(self::VERIFY_FAIL_PHONE_PREFIX . $phone) >= self::VERIFY_FAIL_PHONE_MAX) {
            throw new ApiValidateException('验证失败次数过多，请稍后再试');
        }

        if ($this->getCounter(self::VERIFY_FAIL_IP_PREFIX . $ip) >= self::VERIFY_FAIL_IP_MAX) {
            throw new ApiValidateException('当前设备验证失败次数过多，请稍后再试');
        }
    }

    /**
     * 归一化用户点击坐标。
     *
     * @param array $selectedPoints 原始点击数据。
     * @return array
     */
    private function normalizeSelectedPoints(array $selectedPoints): array
    {
        $points = [];
        foreach ($selectedPoints as $point) {
            if (!is_array($point)) {
                continue;
            }

            $x = isset($point['x']) ? (int) $point['x'] : null;
            $y = isset($point['y']) ? (int) $point['y'] : null;
            if ($x === null || $y === null) {
                continue;
            }

            $points[] = [
                'x' => max(0, min(self::CANVAS_WIDTH, $x)),
                'y' => max(0, min(self::CANVAS_HEIGHT, $y)),
            ];
        }

        return $points;
    }

    /**
     * 校验点击坐标是否命中目标区域。
     *
     * @param array $selectedPoints 用户点击坐标。
     * @param array $answerAreas 目标区域。
     * @return bool
     */
    private function isSelectedPointsValid(array $selectedPoints, array $answerAreas): bool
    {
        foreach ($answerAreas as $index => $area) {
            $point = $selectedPoints[$index] ?? null;
            if (!is_array($point) || !is_array($area)) {
                return false;
            }

            $halfWidth = (int) ceil(((int) ($area['width'] ?? 0)) / 2);
            $halfHeight = (int) ceil(((int) ($area['height'] ?? 0)) / 2);
            $left = (int) ($area['x'] ?? 0) - $halfWidth - self::HIT_SLOP;
            $right = (int) ($area['x'] ?? 0) + $halfWidth + self::HIT_SLOP;
            $top = (int) ($area['y'] ?? 0) - $halfHeight - self::HIT_SLOP;
            $bottom = (int) ($area['y'] ?? 0) + $halfHeight + self::HIT_SLOP;

            $x = (int) ($point['x'] ?? -1);
            $y = (int) ($point['y'] ?? -1);
            if ($x < $left || $x > $right || $y < $top || $y > $bottom) {
                return false;
            }
        }

        return true;
    }

    /**
     * 记录验证失败。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function markVerifyFailure(string $phone): void
    {
        $ip = $this->getRequestIp();
        $this->incrementCounter(self::VERIFY_FAIL_PHONE_PREFIX . $phone, self::VERIFY_FAIL_WINDOW_SECONDS);
        $this->incrementCounter(self::VERIFY_FAIL_IP_PREFIX . $ip, self::VERIFY_FAIL_WINDOW_SECONDS);
    }

    /**
     * 清理验证失败计数。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function clearVerifyFailure(string $phone): void
    {
        Cache::delete(self::VERIFY_FAIL_PHONE_PREFIX . $phone);
    }

    /**
     * 销毁场景缓存与图片资源。
     *
     * @param string $cacheKey 缓存键。
     * @param array $scene 场景缓存数据。
     * @return void
     */
    private function consumeScene(string $cacheKey, array $scene): void
    {
        Cache::delete($cacheKey);
        $this->deleteSceneImage((string) ($scene['scene_image_relative_path'] ?? ''));
    }

    /**
     * 获取计数器。
     *
     * @param string $key 缓存键。
     * @return int
     */
    private function getCounter(string $key): int
    {
        return max(0, (int) Cache::get($key, 0));
    }

    /**
     * 增加计数器。
     *
     * @param string $key 缓存键。
     * @param int $expireSeconds 过期秒数。
     * @return int
     */
    private function incrementCounter(string $key, int $expireSeconds): int
    {
        $count = Cache::inc($key, 1);
        if ($count === false) {
            $count = $this->getCounter($key) + 1;
            Cache::set($key, $count, $expireSeconds);

            return $count;
        }

        $this->refreshCounterExpire($key, $expireSeconds);

        return max(0, (int) $count);
    }

    /**
     * 刷新计数器过期时间。
     *
     * ThinkPHP 的 Redis 缓存前缀只在 get/set/inc 等封装方法中处理，直接透传 expire 时需要补齐真实 key。
     *
     * @param string $key 缓存键。
     * @param int $expireSeconds 过期秒数。
     * @return void
     */
    private function refreshCounterExpire(string $key, int $expireSeconds): void
    {
        try {
            $store = Cache::store();
            $cacheKey = method_exists($store, 'getCacheKey') ? $store->getCacheKey($key) : $key;
            $handler = method_exists($store, 'handler') ? $store->handler() : null;
            if (is_object($handler) && method_exists($handler, 'expire') && $handler->expire($cacheKey, $expireSeconds)) {
                return;
            }
        } catch (\Throwable $exception) {
            Log::warning('短信点击验证计数器过期时间刷新失败', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);
        }

        Cache::set($key, $this->getCounter($key), $expireSeconds);
    }

    /**
     * 获取请求 IP。
     *
     * @return string
     */
    private function getRequestIp(): string
    {
        return (string) request()->ip();
    }

    /**
     * 解析场景图片存储目录。
     *
     * @return array
     */
    private function resolveSceneStorage(): array
    {
        return [
            'path' => app()->getRootPath() . 'public/storage/' . self::SCENE_STORAGE_DIRECTORY,
            'url' => '/storage/' . self::SCENE_STORAGE_DIRECTORY,
        ];
    }

    /**
     * 删除场景图片。
     *
     * @param string $relativePath 相对路径。
     * @return void
     */
    private function deleteSceneImage(string $relativePath): void
    {
        $relativePath = trim($relativePath, '/');
        if ($relativePath === '') {
            return;
        }

        $filePath = public_path() . 'storage/' . $relativePath;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * 清理过期场景图片。
     *
     * @return void
     * @throws RandomException
     */
    private function cleanupExpiredSceneImages(): void
    {
        if (random_int(1, 10) !== 1) {
            return;
        }

        $storage = $this->resolveSceneStorage();
        if (!is_dir($storage['path'])) {
            return;
        }

        $files = scandir($storage['path']);
        if (!is_array($files)) {
            return;
        }

        $expireBefore = time() - self::SCENE_EXPIRE_SECONDS;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $storage['path'] . DIRECTORY_SEPARATOR . $file;
            if (!is_file($filePath)) {
                continue;
            }

            if ((int) filemtime($filePath) < $expireBefore) {
                @unlink($filePath);
            }
        }
    }

    /**
     * 校验手机号。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function assertPhone(string $phone): void
    {
        if (!is_valid_phone($phone)) {
            throw new ApiValidateException('请输入正确的手机号');
        }
    }
}
