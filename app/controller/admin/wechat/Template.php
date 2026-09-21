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

/**
 * 微信模板消息控制器
 * Class WechatTemplate
 * @package app\admin\controller\wechat
 */
class Template extends AuthController
{

    protected $cacheTag = '_system_wechat';

    public function index()
    {
        $where = $this->request->postMore([
            ['name', ''],
            ['status', '']
        ], $this->request);
        $this->assign('where', $where);
        $this->assign(WechatTemplateModel::SystemPage($where));
        try {
            $industry = Cache::tag($this->cacheTag)->remember('_wechat_industry', function () {
                $cache = WechatTemplateService::getIndustry();
                if (!$cache) return [];
                Cache::tag($this->cacheTag, ['_wechat_industry']);
                return $cache->toArray();
            }, 0) ?: [];
        } catch (\Exception $e) {
            $industry = [];
        }
        !is_array($industry) && $industry = [];
        $this->assign('industry', $industry);
        return $this->fetch();
    }

    /**
     * 添加模板消息
     * @return mixed
     */
    public function create()
    {
        $f = array();
        $f[] = Form::input('tempkey', '模板编号');
        $f[] = Form::input('tempid', '模板ID');
        $f[] = Form::input('name', '模板名');
        $f[] = Form::input('content', '回复内容')->type('textarea');
        $f[] = Form::radio('status', '状态', 1)->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]);
        $form = Form::make_post_form('添加模板消息', $f, Url::build('save'), 2);
        $this->assign(compact('form'));
        return $this->fetch('public/form-builder');
    }

    public function save(Request $request)
    {
        $data = $this->request->postMore([
            'tempkey',
            'tempid',
            'name',
            'content',
            ['status', 0]
        ], $request);
        if ($data['tempkey'] == '') return $this->fail('请输入模板编号');
        if ($data['tempkey'] != '' && WechatTemplateModel::be($data['tempkey'], 'tempkey'))
            return $this->fail('请输入模板编号已存在,请重新输入');
        if ($data['tempid'] == '') return $this->fail('请输入模板ID');
        if ($data['name'] == '') return $this->fail('请输入模板名');
        if ($data['content'] == '') return $this->fail('请输入回复内容');
        $data['add_time'] = time();
        WechatTemplateModel::set($data);
        return $this->success('添加模板消息成功!');
    }

    /**
     * 编辑模板消息
     * @param $id
     * @return mixed|\think\response\Json|void
     */
    public function edit($id)
    {
        if (!$id) return $this->failed('数据不存在');
        $product = WechatTemplateModel::get($id);
        if (!$product) return $this->fail('数据不存在!');
        $f = array();
        $f[] = Form::input('tempkey', '模板编号', $product->getData('tempkey'))->disabled(1);
        $f[] = Form::input('name', '模板名', $product->getData('name'))->disabled(1);
        $f[] = Form::input('tempid', '模板ID', $product->getData('tempid'));
        $f[] = Form::radio('status', '状态', $product->getData('status'))->options([['label' => '开启', 'value' => 1], ['label' => '关闭', 'value' => 0]]);
        $form = Form::make_post_form('编辑模板消息', $f, Url::build('update', compact('id')), 2);
        $this->assign(compact('form'));
        return $this->fetch('public/form-builder');
    }

    public function update(Request $request, $id)
    {
        $data = $this->request->postMore([
            'tempid',
            ['status', 0]
        ], $request);
        if ($data['tempid'] == '') return $this->fail('请输入模板ID');
        if (!$id) return $this->failed('数据不存在');
        $product = WechatTemplateModel::get($id);
        if (!$product) return $this->fail('数据不存在!');
        WechatTemplateModel::edit($data, $id);
        return $this->success('修改成功!');
    }

    /**
     * 删除模板消息
     * @param $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        if (!$id) return $this->fail('数据不存在!');
        if (!WechatTemplateModel::del($id))
            return $this->fail(WechatTemplateModel::getErrorInfo('删除失败,请稍候再试!'));
        else
            return $this->success('删除成功!');
    }


}
