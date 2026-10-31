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

namespace app\common\service;

use app\common\exception\AdminResponseException;
use Rabbit\service\SpreadsheetExcelService;

class ExcelService
{
    protected SpreadsheetExcelService $spreadsheetExcelService;

    public function __construct()
    {
        $this->spreadsheetExcelService = new SpreadsheetExcelService();
    }

    public function export($header, $title, $export = [], $filename = '', $path = '', $suffix = 'xlsx')
    {
        return $this->spreadsheetExcelService->create()
            ->setExcelHeader($header, count($title['mark']) + 2)
            ->setExcelTile($title)
            ->setExcelContent($export)
            ->save($filename, $suffix, $path);
    }

    /**
     * 盘点导出。
     */
    public function stocktak(array $where, $options)
    {
        unset($where, $options);
        $this->throwProductExportUnavailable();
    }

    /**
     * 库存列表导出。
     */
    public function stock($where)
    {
        unset($where);
        $this->throwProductExportUnavailable();
    }

    /**
     * 调拨导出。
     */
    public function allocation(array $where)
    {
        unset($where);
        $this->throwProductExportUnavailable();
    }

    /**
     * 商品资料导出。
     */
    public function excelProduct(array $where)
    {
        unset($where);
        $this->throwProductExportUnavailable();
    }

    /**
     * 库存流水列表。
     */
    public function stockRecord(array $where, $page, $limit)
    {
        unset($where, $page, $limit);
        $this->throwProductExportUnavailable();
    }

    /**
     * 库存流水 CSV 导出。
     */
    public function stockRecordCsv(array $where)
    {
        unset($where);
        $this->throwProductExportUnavailable();
    }

    private function throwProductExportUnavailable(): void
    {
        throw new AdminResponseException('当前版本暂不支持商品/库存导出');
    }
}
