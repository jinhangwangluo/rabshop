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

namespace app\controller\admin\setting;

use app\common\enum\ConfigKeyEnum;
use app\common\service\setting\ConfigService;
use app\controller\admin\AuthController;
use Random\RandomException;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * Class Config
 * @package app\admin\controller
 */
class Config extends AuthController
{

    /**
     * @var ConfigService
     */
    #[Inject]
    protected ConfigService $service;

    /**
     * 获取配置
     * @return Response
     */
    public function index()
    {
        $keys = $this->request->get('keys', []);
        if (!is_array($keys)) {
            $keys = (string)$keys;
        }
        return $this->success($this->service->getConfigValues($keys));
    }

    /**
     * 获取分类
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function cate()
    {
        $pid = $this->request->get('pid', 0);
        return $this->success($this->service->getConfigCate((int)$pid));
    }

    /**
     * 保存配置
     * @return mixed
     * @throws \Exception
     */
    public function save()
    {
        $id = $this->request->post('id/d', 0);
        if (!$id) {
            return $this->fail('参数错误');
        }
        $data = $this->request->post('form_data/a', []);

        $this->service->configSave($id, $data);

        return $this->success('保存成功');
    }

    /**
     * 生成 32 位随机 Token
     * @return Response
     * @throws RandomException
     */
    public function generateToken(): Response
    {
        $type = $this->request->get('type','qiniu');
        $token = bin2hex(random_bytes(16));
        $url = db_config(ConfigKeyEnum::WEB_HOST).'/api/live/callback?type='.$type.'&token='.$token;
        return $this->success('生成成功', ['token' => $token,'url' => $url]);
    }
}
