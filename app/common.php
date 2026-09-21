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

use app\common\enum\ConfigKeyEnum;
use app\common\service\combination\CombinationDataService;
use app\common\service\setting\ConfigService;
use Rabbit\service\SnowflakeOrderService;
use Rabbit\service\wechat\OfficialAccount;
use Rabbit\util\Json;
use think\db\BaseQuery;
use Fastknife\Service\BlockPuzzleCaptchaService;
use Fastknife\Service\ClickWordCaptchaService;
use think\facade\Log;

if (!function_exists('is_today')) {
    /**
     * 判断是否是今天
     * @param $time
     * @return bool
     * @throws Exception
     */
    function is_today($time)
    {
        // 将目标时间转换为 DateTime 对象
        $targetTime = new \DateTime($time);

        // 获取今天的开始时间（00:00:00）
        $startOfDay = new \DateTime('today');

        // 获取今天的结束时间（23:59:59）
        $endOfDay = new \DateTime('today 23:59:59');

        // 判断目标时间是否在今天之内
        return $targetTime >= $startOfDay && $targetTime <= $endOfDay;
    }
}

if (!function_exists('array_merge_filter_unique')) {
    /**
     * 数组合并去重
     * @param array $data
     * @return array
     */
    function array_merge_filter_unique(array $data)
    {
        return array_merge(array_unique(array_filter($data)));
    }
}

if (!function_exists('get_image_url')) {
    /**
     * 获取图片地址
     * @param $url
     * @return mixed|string
     */
    function get_image_url($url)
    {
        if (!str_contains($url, 'https://') && !str_contains($url, 'http://')) {
            $url = db_config(ConfigKeyEnum::WEB_HOST) . $url;
        }
        return $url;
    }
}

if (!function_exists('get_upload_uri')) {

    /**
     * 获取上传地址
     * @return string
     */
    function get_upload_uri()
    {
        return db_config(ConfigKeyEnum::WEB_HOST) . '/api/admin/upload';
    }
}

if (!function_exists('get_sn')) {
    /**
     * 生成订单号（使用雪花算法）
     * @param string $type 订单类型前缀
     * @param int $datacenterId 数据中心ID
     * @param int $workerId 工作节点ID
     * @return string
     */
    function get_sn(string $type = 'PD', int $datacenterId = 1, int $workerId = 1)
    {
        try {
            // 如果没有指定数据中心和工作节点ID，则从配置或环境变量获取
            $datacenterId = $datacenterId ?? (defined('SNOWFLAKE_DATACENTER_ID') ? SNOWFLAKE_DATACENTER_ID : 1);
            $workerId = $workerId ?? (defined('SNOWFLAKE_WORKER_ID') ? SNOWFLAKE_WORKER_ID : gethostname());

            // 将主机名转换为数字ID
            if (!is_numeric($workerId)) {
                $workerId = crc32($workerId) % 31 + 1; // 确保在1-31范围内
            }

            // 创建雪花算法服务实例
            $snowflakeService = new SnowflakeOrderService($datacenterId, $workerId);

            // 生成订单号
            return $snowflakeService->generateOrderId($type);

        } catch (\Exception $e) {
            // 如果雪花算法失败，回退到原来的实现
            Log::error('雪花算法生成订单号失败，使用备用方案', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            // 原来的实现作为备选方案
            list($msec, $sec) = explode(' ', microtime());
            $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
            $sn = $type . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
            return $sn;
        }
    }
}

if (!function_exists('get_list_compose_children')) {
    /**
     * 根据列表数据生成tree形状的数据
     * [
     * "idPath" => "900000905,200000087,200005083",
     * "namePath" => "冷冻饮品,冰品,雪糕/冰淇淋",
     * "level" => 3,
     * "name" => "雪糕/冰淇淋",
     * "id" => 200005083
     * ]
     * @param array $data 数据
     * @param string $childrenname 子数据名
     * @param string $idPathkeyName
     * @param string $namePathKeyName
     * @param string $catIdKey
     * @return array
     */
    function get_list_compose_children(array $data, string $childrenname = 'children', string $idPathkeyName = 'idPath', string $namePathKeyName = 'namePath', string $catIdKey = 'value'): array
    {
        $tree = [];

        foreach ($data as $item) {
            $idPath = explode(',', $item[$idPathkeyName]);
            $namePath = explode(',', $item[$namePathKeyName]);
            $current = &$tree;

            for ($i = 0; $i < count($idPath); $i++) {
                $id = $idPath[$i];
                $name = $namePath[$i];
                $found = false;
                $index = 0;

                foreach ($current as $key => $node) {
                    if ($node[$catIdKey] == $id) {
                        $found = true;
                        $index = $key;
                        break;
                    }
                }

                if (!$found) {
                    $newNode = [
                        'value' => $id,
                        'label' => $name,
                        $childrenname => []
                    ];
                    $current[] = $newNode;
                    $current = &$current[count($current) - 1][$childrenname];
                } else {
                    $current = &$current[$index][$childrenname];
                }
            }
        }


        return unset_empty_children($tree, $childrenname);
    }
}

if (!function_exists('unset_empty_children')) {
    /**
     * 处理多级的最后一级空的children
     * @param array $treeData
     * @param string $childrenname
     * @return array
     */
    function unset_empty_children(array $treeData, string $childrenname)
    {
        foreach ($treeData as $i => $item) {
            if (!empty($item[$childrenname]) && is_array($item[$childrenname])) {
                $treeData[$i][$childrenname] = unset_empty_children($item[$childrenname], $childrenname);
            } else {
                if (empty($item[$childrenname])) {
                    unset($item[$childrenname]);
                    $treeData[$i] = $item;
                }
            }
        }

        return $treeData;
    }
}

if (!function_exists('get_tree_children')) {
    /**
     * tree 子菜单
     * @param array $data 数据
     * @param string $childrenname 子数据名
     * @param string $keyName 数据key名
     * @param string $pidName 数据上级key名
     * @return array
     */
    function get_tree_children(array $data, string $childrenname = 'children', string $keyName = 'id', string $pidName = 'pid'): array
    {
        $list = [];
        foreach ($data as $value) {
            $list[$value[$keyName]] = $value;
        }
        $tree = []; //格式化好的树
        foreach ($list as $item) {
            if (isset($list[$item[$pidName]])) {
                $list[$item[$pidName]][$childrenname][] = &$list[$item[$keyName]];
            } else {
                $tree[] = &$list[$item[$keyName]];
            }
        }
        return $tree;
    }
}

if (!function_exists('d_json')) {

    /**
     * json返回操作
     * @return Json
     */
    function d_json()
    {
        return app()->make(Json::class);
    }
}


if (!function_exists('get_menu_index')) {
    /**
     * @param array $path
     * @param int $id
     * @return string
     */
    function get_menu_index(array $path, $id)
    {
        return ($path ? implode('-', $path) . '-' : '') . $id;
    }
}


if (!function_exists('set_model_time')) {

    /**
     * 时间搜索
     * @param BaseQuery $model
     * @param string $section
     * @param string $prefix
     * @param string $field
     * @return BaseQuery
     */
    function set_model_time(BaseQuery $model, string $section, string $prefix = 'create_time', string $field = '-')
    {
        if (!isset($section))
            return $model;
        switch ($section) {
            case 'today':
                $model->whereBetween($prefix, [date('Y-m-d H:i:s', strtotime('today')), date('Y-m-d H:i:s', strtotime('tomorrow -1second'))]);
                break;
            case 'week':
                $model->whereBetween($prefix, [date('Y-m-d H:i:s', strtotime('this week 00:00:00')), date('Y-m-d H:i:s', strtotime('next week 00:00:00 -1second'))]);
                break;
            case 'month':
                $model->whereBetween($prefix, [date('Y-m-d H:i:s', strtotime('first Day of this month 00:00:00')), date('Y-m-d H:i:s', strtotime('first Day of next month 00:00:00 -1second'))]);
                break;
            case 'year':
                $model->whereBetween($prefix, [date('Y-m-d H:i:s', strtotime('this year 1/1')), date('Y-m-d H:i:s', strtotime('next year 1/1 -1second'))]);
                break;
            case 'yesterday':
                $model->whereBetween($prefix, [date('Y-m-d H:i:s', strtotime('yesterday')), date('Y-m-d H:i:s', strtotime('today -1second'))]);
                break;
            case 'quarter':
                [$startTime, $endTime] = get_quarter_time();
                $model = $model->where($prefix, '>', $startTime);
                $model = $model->where($prefix, '<', $endTime);
                break;
            case 'lately7':
                $model = $model->where($prefix, 'between', [date('Y-m-d', strtotime("-7 day")), date('Y-m-d H:i:s')]);
                break;
            case 'lately30':
                $model = $model->where($prefix, 'between', [date('Y-m-d', strtotime("-30 day")), date('Y-m-d H:i:s')]);
                break;
            default:
                if (strstr($section, $field) !== false) {
                    [$startTime, $endTime] = explode($field, $section);
                    if (strlen($startTime) == 4) {
                        $model->whereBetweenTime($prefix, date('Y-m-d H:i:s', strtotime($section)), date('Y-m-d H:i:s', strtotime($section . ' +1day -1second')));
                    } else {
                        if ($startTime == $endTime) {
                            $model = $model->whereBetweenTime($prefix, date('Y-m-d 0:0:0', strtotime($startTime)), date('Y-m-d 23:59:59', strtotime($endTime)));
                        } else {
                            $model = $model->whereBetweenTime($prefix, date('Y-m-d H:i:s', strtotime($startTime)), date('Y-m-d H:i:s', strtotime($endTime)));
                        }
                    }
                }
                break;
        }
        return $model;
    }
}

if (!function_exists('aj_captcha_check_one')) {
    /**
     * 验证滑块1次验证
     * @param string $captchaType
     * @param string $token
     * @param string $pointJson
     * @return void
     */
    function aj_captcha_check_one(string $captchaType, string $token, string $pointJson)
    {
        aj_get_serevice($captchaType)->check($token, $pointJson);
    }
}

if (!function_exists('aj_captcha_check_two')) {
    /**
     * 验证滑块2次验证
     * @param string $captchaType
     * @param string $captchaVerification
     * @return bool
     */
    function aj_captcha_check_two(string $captchaType, string $captchaVerification)
    {
        aj_get_serevice($captchaType)->verificationByEncryptCode($captchaVerification);
    }
}


if (!function_exists('aj_captcha_create')) {
    /**
     * 创建验证码
     * @return array
     */
    function aj_captcha_create(string $captchaType = 'blockPuzzle')
    {
        return aj_get_serevice($captchaType)->get();
    }
}

if (!function_exists('aj_get_serevice')) {

    /**
     * @param string $captchaType
     * @return ClickWordCaptchaService|BlockPuzzleCaptchaService
     */
    function aj_get_serevice(string $captchaType = 'blockPuzzle')
    {
        $config = \think\facade\Config::get('ajcaptcha');
        switch ($captchaType) {
            case "clickWord":
                $service = new ClickWordCaptchaService($config);
                break;
            case "blockPuzzle":
                $service = new BlockPuzzleCaptchaService($config);
                break;
            default:
                throw new \think\exception\ValidateException('captchaType参数不正确！');
        }
        return $service;
    }
}

if (!function_exists('get_quarter_time')) {
    /**
     * 获取当前季度开始和结束时间
     * @return array
     */
    function get_quarter_time(): array
    {
        // 获取当前月份
        $month = date('n');

        // 根据月份计算季度开始月份
        if ($month >= 1 && $month <= 3) {
            $quarter_start_month = 1;
        } elseif ($month >= 4 && $month <= 6) {
            $quarter_start_month = 4;
        } elseif ($month >= 7 && $month <= 9) {
            $quarter_start_month = 7;
        } elseif ($month >= 10 && $month <= 12) {
            $quarter_start_month = 10;
        }

        // 计算季度开始日期和结束日期
        $quarter_start_date = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter_start_month, 1, date('Y')));
        $quarter_end_date = date('Y-m-d H:i:s', mktime(0, 0, 0, $quarter_start_month + 2, date('t', mktime(0, 0, 0, $quarter_start_month + 2, 1, date('Y'))), date('Y')));
        return [$quarter_start_date, $quarter_end_date];
    }
}

if (!function_exists('db_config')) {
    /**
     * 获取数据库配置
     * @param string $key
     * @param mixed|null $value
     * @return mixed
     */
    function db_config(string $key, mixed $value = null)
    {
        $modelValue = app()->make(ConfigService::class)->getValue($key);
        if (is_null($modelValue)) {
            return $value;
        } else {
            return $modelValue;
        }
    }
}

if (!function_exists('db_combination_data')) {
    /**
     * 获取启用的组合数据列表
     * @param string $dataType 数据类型
     * @param int $merchantId 商户ID，0 表示平台端
     * @param array $default 默认值
     * @return array
     */
    function db_combination_data(string $dataType, int $merchantId = 0, array $default = []): array
    {
        $list = app()->make(CombinationDataService::class)->getEnabledDataByType($dataType, $merchantId);
        return $list === [] ? $default : $list;
    }
}

if (!function_exists('parse_ids')) {
    /**
     * 解析ids
     * @param array $ids
     * @param string $raw
     * @return array
     */
    function parse_ids(array $ids = [], string $raw = ''): array
    {
        if ($ids !== []) {
            return array_values(array_unique(array_filter(array_map('intval', $ids))));
        }

        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
    }
}

if (!function_exists('is_valid_phone')) {
    /**
     * 验证手机号
     * @param $phone
     * @return bool
     */
    function is_valid_phone($phone)
    {
        return (bool)preg_match('/^1\d{10}$/', $phone);
    }
}
