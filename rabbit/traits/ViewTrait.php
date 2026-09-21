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

namespace Rabbit\traits;

use app\common\exception\AdminException;
use FormBuilder\Exception\FormBuilderException;
use Rabbit\util\FormBuild;
use think\annotation\Inject;

/**
 *
 * Trait ViewTrait
 * @package Rabbit\traits
 */
trait ViewTrait
{

    #[Inject]
    protected FormBuild $build;

    /**
     *
     * @param array $rule
     * @param string $url
     * @param string $method
     * @param string $title
     * @param array $formData
     * @return array
     * @throws FormBuilderException
     */
    public function buildView(array $rule, string $url, string $method = 'POST', string $title = '', array $formData = []): array
    {
        $form = FormBuild::createForm($url)->formData($formData)->setRule($rule)->setTitle($title)->setMethod($method);
        $rule = $form->formRule();
        $action = $form->getAction();
        return compact('rule', 'title', 'action', 'method');
    }

    /**
     * @param string $title
     * @param $rule
     * @param string $url
     * @param string $method
     * @param array $options
     * @return array
     * @throws FormBuilderException
     */
    public function createElementForm(string $title, $rule, string $url, string $method = 'POST', array $options = [])
    {
        $methodData = ['POST', 'PUT', 'GET', 'DELETE'];
        if (!in_array(strtoupper($method), $methodData)) {
            throw new AdminException('请求方式错误');
        }

        $formService = app()->make(FormBuild::class);

        if ($rule instanceof \Closure) {
            $field = $rule($formService);
        } else if (is_array($rule)) {
            $field = $rule;
        } else {
            throw new AdminException('参数类型错误');
        }

        $form = $formService->createForm($url)->setMethod($method)->setTitle($title)->setRule($field);

        if (!empty($options['data'])) {
            $form->formData($options['data']);
        }

        $rule = $form->formRule();
        $action = $form->getAction();
        return compact('rule', 'title', 'action', 'method');
    }
}
