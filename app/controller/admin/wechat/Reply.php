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

namespace app\controller\admin\wechat;

use app\controller\admin\AuthController;
use app\common\service\wechat\WechatReplyService;
use think\annotation\Inject;
use Rabbit\service\Upload;

/**
 * 微信关键字回复控制器
 * Class Reply
 * @package app\controller\admin\wechat
 */
class Reply extends AuthController
{
    #[Inject]
    protected WechatReplyService $service;

    /**
     * 关键字回复分页列表（排除subscribe/default）
     */
    public function index(): mixed
    {
        $where = $this->request->getMore([
            ['key', ''],
            ['type', ''],
        ]);

        $result = $this->service->getKeywordReplyList($where);

        return $this->success($result);
    }

    /**
     * 按key获取回复详情（subscribe/default）
     */
    public function keyword(): mixed
    {
        $key = $this->request->get('key', '');
        if (empty($key)) {
            return $this->fail('参数key不能为空');
        }

        $detail = $this->service->getReplyDetail($key);

        return $this->success($detail ?: []);
    }

    /**
     * 按ID获取回复详情
     */
    public function info_keyword(): mixed
    {
        $id = (int)$this->request->get('id', 0);
        if (!$id) {
            return $this->fail('参数id不能为空');
        }

        $detail = $this->service->getReplyDetailById($id);
        if (!$detail) {
            return $this->fail('数据不存在');
        }

        return $this->success($detail);
    }

    /**
     * 保存subscribe/default回复（关注回复 / 无效关键字回复）
     * key 固定为 subscribe 或 default，不支持多关键字
     */
    public function save(): mixed
    {
        $data = $this->request->postMore([
            ['type', ''],
            ['key', ''],
            ['status', 0],
            ['data', []],
            ['sort', 0],
        ]);

        $key = trim($data['key']);
        if (!in_array($key, ['subscribe', 'default'], true)) {
            return $this->fail('参数key有误，仅允许 subscribe 或 default');
        }

        if (empty($data['type'])) {
            return $this->fail('请选择回复类型');
        }
        if (!$this->service->isValidReplyType($data['type'])) {
            return $this->fail('回复类型有误!');
        }
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $this->fail('回复消息参数有误!');
        }

        try {
            $existReply = $this->service->getReplyDetail($key);

            if ($existReply) {
                $result = $this->service->updateReply((int)$existReply['id'], $data);
            } else {
                $result = $this->service->createReply($data);
            }

            return $result ? $this->success('保存成功!') : $this->fail('保存失败');
        } catch (\Exception $e) {
            return $this->fail('保存失败: ' . $e->getMessage());
        }
    }

    /**
     * 保存关键字回复
     */
    public function saveKeyword(): mixed
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['key', ''],
            ['type', ''],
            ['status', 0],
            ['data', []],
            ['sort', 0],
        ]);

        if (empty($data['key'])) {
            return $this->fail('请输入关键字');
        }

        // 将逗号分隔的多个关键字进行清洗和去重
        $keywords = array_values(array_unique(
            array_filter(
                array_map('trim', explode(',', $data['key']))
            )
        ));

        if (empty($keywords)) {
            return $this->fail('请输入关键字');
        }

        // 保存时将关键字用逗号拼接存入 key 字段
        $data['key'] = implode(',', $keywords);

        if (empty($data['type'])) {
            return $this->fail('请选择回复类型');
        }
        if (!$this->service->isValidReplyType($data['type'])) {
            return $this->fail('回复类型有误!');
        }
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $this->fail('回复消息参数有误!');
        }

        try {
            $id = (int)$data['id'];
            unset($data['id']);

            if ($id) {
                // 编辑时同样传入 $keywords，确保 wechat_reply_key 表同步更新
                $result = $this->service->updateReply($id, $data, $keywords);
            } else {
                $result = $this->service->createReply($data, $keywords);
            }

            return $result ? $this->success('保存成功!') : $this->fail('保存失败');
        } catch (\Exception $e) {
            return $this->fail('保存失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除关键字回复
     */
    public function delete(): mixed
    {
        $id = (int)$this->request->param('id', 0);
        if (!$id) {
            return $this->fail('参数id不能为空');
        }

        try {
            if ($this->service->deleteReply($id)) {
                return $this->success('删除成功!');
            }
            return $this->fail('删除失败,请稍候再试!');
        } catch (\Exception $e) {
            return $this->fail('删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 修改状态
     * @param $id
     * @return mixed
     */
    public function status($id): mixed
    {
        if (!$id) {
            return $this->fail('参数id不能为空');
        }
        $status = (int)$this->request->post('status', 0);
        $this->service->update($id, ['status' => $status]);

        return $this->success('修改状态成功!');
    }

    /**
     * 上传图片
     */
    public function upload_img(): mixed
    {
        $name = $this->request->post('file');
        if (!$name) {
            return $this->fail('请上传图片');
        }
        $res = Upload::image($name, 'wechat/image');
        return $res->status === true ? $this->success('上传成功', $res->filePath) : $this->fail($res->error);
    }

    /**
     * 上传语音文件
     */
    public function upload_file(): mixed
    {
        $name = $this->request->post('file');
        if (!$name) {
            return $this->fail('请上传声音');
        }
        $autoValidate['size'] = 2097152;
        $res = Upload::file($name, 'wechat/voice', true, $autoValidate);
        return $res->status === true ? $this->success('上传成功', $res->filePath) : $this->fail($res->error);
    }

    /**
     * 获取回复类型选项
     */
    public function get_reply_types(): mixed
    {
        return $this->success($this->service->getReplyTypes());
    }

    /**
     * 获取匹配类型选项
     */
    public function get_match_types(): mixed
    {
        return $this->success($this->service->getMatchTypes());
    }
}
