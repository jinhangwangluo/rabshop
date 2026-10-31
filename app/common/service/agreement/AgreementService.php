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

namespace app\common\service\agreement;

use app\common\enum\AgreementTypeEnum;
use app\common\exception\AdminResponseException;
use app\common\model\agreement\Agreement;
use app\common\resource\ResourceServiceInterfaceTrait;
use app\common\resource\ResourceServiceTrait;
use app\common\service\BaseService;

/**
 * 协议服务
 */
class AgreementService extends BaseService implements ResourceServiceInterfaceTrait
{
    use ResourceServiceTrait;

    protected function setModel(): string
    {
        return Agreement::class;
    }

    /**
     * 获取后台协议配置页数据。
     */
    public function schema(): array
    {
        $rows = $this->getModel()
            ->whereIn('type', AgreementTypeEnum::types())
            ->order('id', 'desc')
            ->select()
            ->toArray();
        $rowMap = [];

        foreach ($rows as $row) {
            $type = (string)($row['type'] ?? '');
            if ($type !== '' && !isset($rowMap[$type])) {
                $rowMap[$type] = $row;
            }
        }

        $agreements = [];
        foreach (AgreementTypeEnum::options() as $item) {
            $type = (string)$item['type'];
            $row = $rowMap[$type] ?? [];
            $agreements[] = $this->formatAgreement($type, $row);
        }

        return [
            'agreements' => $agreements,
        ];
    }

    /**
     * 保存指定类型的协议内容。
     */
    public function saveContent(string $type, string $content): array
    {
        $type = trim($type);
        if (!AgreementTypeEnum::has($type)) {
            throw new AdminResponseException('协议类型不存在');
        }

        $this->transaction(function () use ($type, $content) {
            $agreement = $this->getModel()
                ->where('type', $type)
                ->order('id', 'desc')
                ->find();
            $data = [
                'type' => $type,
                'title' => AgreementTypeEnum::title($type),
                'content' => $content,
                'status' => 1,
                'sort' => AgreementTypeEnum::sort($type),
            ];

            if ($agreement) {
                $this->update((int)$agreement->id, $data);
                return;
            }

            $this->create(array_merge($data, [
                'version' => '',
            ]));
        });

        $agreement = $this->getModel()
            ->where('type', $type)
            ->order('id', 'desc')
            ->find();

        return $this->formatAgreement($type, $agreement ? $agreement->toArray() : []);
    }

    /**
     * 按类型获取协议详情
     */
    public function getByType(string $type): array
    {
        if (!AgreementTypeEnum::has($type)) {
            throw new AdminResponseException('协议不存在');
        }

        $agreement = $this->getModel()
            ->where('type', $type)
            ->where('status', 1)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->find();

        if (!$agreement) {
            throw new AdminResponseException('协议不存在');
        }

        $data = $agreement->toArray();

        return [
            'id' => (int)($data['id'] ?? 0),
            'type' => (string)($data['type'] ?? ''),
            'title' => AgreementTypeEnum::title($type),
            'content' => (string)($data['content'] ?? ''),
            'version' => (string)($data['version'] ?? ''),
        ];
    }

    /**
     * 格式化后台协议配置。
     */
    protected function formatAgreement(string $type, array $row = []): array
    {
        return [
            'id' => (int)($row['id'] ?? 0),
            'type' => $type,
            'title' => AgreementTypeEnum::title($type),
            'content' => (string)($row['content'] ?? ''),
            'version' => (string)($row['version'] ?? ''),
            'status' => 1,
            'sort' => AgreementTypeEnum::sort($type),
            'update_time' => (string)($row['update_time'] ?? ''),
        ];
    }
}
