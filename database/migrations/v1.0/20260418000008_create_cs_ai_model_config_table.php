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

use think\migration\Migrator;

/**
 * AI模型配置中心表
 */
class CreateCsAiModelConfigTable extends Migrator
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $providerTable = $this->table('cs_ai_provider', ['comment' => 'AI服务商配置表', 'collation' => 'utf8mb4_general_ci']);
        $providerTable->addColumn('type', 'string', ['limit' => 20, 'default' => 'chat', 'comment' => '类型：chat/embedding']);
        $providerTable->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID，0为平台']);
        $providerTable->addColumn('provider_code', 'string', ['limit' => 64, 'default' => '', 'comment' => '服务商编码']);
        $providerTable->addColumn('provider_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '服务商名称']);
        $providerTable->addColumn('driver', 'string', ['limit' => 32, 'default' => 'openai_compatible', 'comment' => '底层驱动：aliyun/openai/openai_compatible']);
        $providerTable->addColumn('base_url', 'string', ['limit' => 255, 'default' => '', 'comment' => '请求地址']);
        $providerTable->addColumn('api_key', 'string', ['limit' => 255, 'default' => '', 'comment' => 'API Key']);
        $providerTable->addColumn('is_builtin', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否内置：0-否，1-是']);
        $providerTable->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $providerTable->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $providerTable->addTimestamps();
        $providerTable->addSoftDelete();
        $providerTable->addIndex(['type', 'provider_code']);
        $providerTable->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $providerTable->addIndex(['merchant_id', 'type', 'status'], ['name' => 'idx_merchant_type_status']);
        $providerTable->addIndex('driver');
        $providerTable->addIndex('status');
        $providerTable->create();

        $modelTable = $this->table('cs_ai_model', ['comment' => 'AI模型配置表', 'collation' => 'utf8mb4_general_ci']);
        $modelTable->addColumn('provider_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '服务商ID']);
        $modelTable->addColumn('merchant_id', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '商户ID，0为平台']);
        $modelTable->addColumn('type', 'string', ['limit' => 20, 'default' => 'chat', 'comment' => '类型：chat/embedding']);
        $modelTable->addColumn('model_id', 'string', ['limit' => 100, 'default' => '', 'comment' => '模型ID']);
        $modelTable->addColumn('model_name', 'string', ['limit' => 100, 'default' => '', 'comment' => '模型名称']);
        $modelTable->addColumn('params', 'text', ['null' => true, 'comment' => '模型参数(JSON)']);
        $modelTable->addColumn('timeout', 'integer', ['limit' => 11, 'default' => 30, 'comment' => '请求超时(秒)']);
        $modelTable->addColumn('is_default', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '是否默认：0-否，1-是']);
        $modelTable->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态：0-禁用，1-启用']);
        $modelTable->addColumn('sort', 'integer', ['limit' => 11, 'default' => 0, 'comment' => '排序']);
        $modelTable->addTimestamps();
        $modelTable->addSoftDelete();
        $modelTable->addIndex('provider_id');
        $modelTable->addIndex('merchant_id', ['name' => 'idx_merchant_id']);
        $modelTable->addIndex(['merchant_id', 'type', 'is_default', 'status'], ['name' => 'idx_merchant_type_default_status']);
        $modelTable->addIndex('type');
        $modelTable->addIndex('is_default');
        $modelTable->addIndex('status');
        $modelTable->create();

        $this->seedBuiltinProviders();
        $this->seedBuiltinModels();
    }

    /**
     * 初始化内置服务商
     */
    private function seedBuiltinProviders(): void
    {
        $this->table('cs_ai_provider')->insert([
            [
                'id' => 1,
                'type' => 'chat',
                'provider_code' => 'aliyun',
                'provider_name' => '阿里云百炼',
                'driver' => 'aliyun',
                'base_url' => 'https://dashscope.aliyuncs.com',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 100,
            ],
            [
                'id' => 2,
                'type' => 'chat',
                'provider_code' => 'openai',
                'provider_name' => 'OpenAI',
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 90,
            ],
            [
                'id' => 3,
                'type' => 'chat',
                'provider_code' => 'openai_compatible',
                'provider_name' => 'OpenAI兼容',
                'driver' => 'openai_compatible',
                'base_url' => '',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 80,
            ],
            [
                'id' => 4,
                'type' => 'embedding',
                'provider_code' => 'aliyun',
                'provider_name' => '阿里云百炼',
                'driver' => 'aliyun',
                'base_url' => 'https://dashscope.aliyuncs.com',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 100,
            ],
            [
                'id' => 5,
                'type' => 'embedding',
                'provider_code' => 'openai',
                'provider_name' => 'OpenAI',
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 90,
            ],
            [
                'id' => 6,
                'type' => 'embedding',
                'provider_code' => 'openai_compatible',
                'provider_name' => 'OpenAI兼容',
                'driver' => 'openai_compatible',
                'base_url' => '',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 80,
            ],
            [
                'id' => 7,
                'type' => 'image',
                'provider_code' => 'openai',
                'provider_name' => 'OpenAI',
                'driver' => 'openai',
                'base_url' => 'https://api.openai.com',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 90,
            ],
            [
                'id' => 8,
                'type' => 'image',
                'provider_code' => 'openai_compatible',
                'provider_name' => 'OpenAI兼容',
                'driver' => 'openai_compatible',
                'base_url' => '',
                'api_key' => '',
                'is_builtin' => 1,
                'status' => 1,
                'sort' => 80,
            ],
        ])->saveData();
    }

    /**
     * 初始化内置模型
     */
    private function seedBuiltinModels(): void
    {
        $this->table('cs_ai_model')->insert([
            [
                'provider_id' => 1,
                'type' => 'chat',
                'model_id' => 'qwen-turbo',
                'model_name' => 'Qwen Turbo',
                'params' => json_encode([], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
                'is_default' => 1,
                'status' => 1,
                'sort' => 100,
            ],
            [
                'provider_id' => 2,
                'type' => 'chat',
                'model_id' => 'gpt-4o-mini',
                'model_name' => 'GPT-4o Mini',
                'params' => json_encode([], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
                'is_default' => 0,
                'status' => 1,
                'sort' => 90,
            ],
            [
                'provider_id' => 4,
                'type' => 'embedding',
                'model_id' => 'text-embedding-v2',
                'model_name' => 'Text Embedding V2',
                'params' => json_encode([], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
                'is_default' => 1,
                'status' => 1,
                'sort' => 100,
            ],
            [
                'provider_id' => 5,
                'type' => 'embedding',
                'model_id' => 'text-embedding-3-small',
                'model_name' => 'Text Embedding 3 Small',
                'params' => json_encode([], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
                'is_default' => 0,
                'status' => 1,
                'sort' => 90,
            ],
            [
                'provider_id' => 7,
                'type' => 'image',
                'model_id' => 'gpt-image-1',
                'model_name' => 'GPT Image 1',
                'params' => json_encode(['size' => '1024x1024', 'quality' => 'standard'], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
                'is_default' => 1,
                'status' => 1,
                'sort' => 90,
            ],
            [
                'provider_id' => 8,
                'type' => 'image',
                'model_id' => 'gpt-image-1',
                'model_name' => 'OpenAI Compatible Image',
                'params' => json_encode(['size' => '1024x1024', 'quality' => 'standard'], JSON_UNESCAPED_UNICODE),
                'timeout' => 30,
                'is_default' => 0,
                'status' => 1,
                'sort' => 80,
            ],
        ])->saveData();
    }
}
