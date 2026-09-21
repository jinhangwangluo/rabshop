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

use app\common\resource\ResourceControllerInterface;
use app\common\resource\ResourceControllerTrait;
use app\common\service\setting\SensitiveWordsService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Response;

/**
 * 敏感词管理控制器
 */
class SensitiveWords extends AuthController implements ResourceControllerInterface
{
    use ResourceControllerTrait;

    /**
     * @var SensitiveWordsService
     */
    #[Inject]
    protected SensitiveWordsService $service;

    /**
     * 获取状态字段
     * @return string
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * 设置Validate类名
     * @return string|null
     */
    protected function getValidateClassName(): string|null
    {
        return null;
    }

    /**
     * 设置搜索字段
     * @return string[]
     */
    protected function getIndexFields(): array
    {
        return ['word', 'status'];
    }

    /**
     * 设置请求参数获取字段
     * @return string[]
     */
    protected function getRequestFields(): array
    {
        return ['word', 'remark', 'status'];
    }

    /**
     * 获取表单字段
     * @return string[]
     */
    protected function getFormFields(): array
    {
        return ['word', 'remark'];
    }

    /**
     * 获取敏感词列表
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function index()
    {
        $params = $this->request->getMore($this->getIndexFields());
        return $this->success($this->service->getResourceList(params:$params,sort: 'id desc'));
    }

    /**
     * 添加敏感词
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function save()
    {
        $data = $this->request->postMore($this->getRequestFields());

        // 验证数据
        if (empty($data['word'])) {
            return $this->fail('敏感词不能为空');
        }

        $result = $this->service->addWord($data['word'], $data['remark'] ?? '', $this->adminId);

        if ($result) {
            return $this->success('添加成功');
        }

        return $this->fail('添加失败，敏感词可能已存在');
    }

    /**
     * 批量添加敏感词
     * @return Response
     */
    public function batchSave()
    {
        $words = $this->request->post('words', []);

        if (empty($words) || !is_array($words)) {
            return $this->fail('请输入要添加的敏感词');
        }

        $result = $this->service->batchAddWords($words, $this->adminId);

        return $this->success('批量添加完成', $result);
    }

    /**
     * 修改敏感词
     * @param int $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function update($id)
    {
        if (!$id) {
            return $this->fail('ID不能为空');
        }

        $data = $this->request->postMore($this->getRequestFields());

        // 验证数据
        if (empty($data['word'])) {
            return $this->fail('敏感词不能为空');
        }

        // 检查是否已存在其他相同敏感词
        $exists = $this->service->getModel()
            ->where('word', $data['word'])
            ->where('id', '<>', $id)
            ->find();

        if ($exists) {
            return $this->fail('敏感词已存在');
        }

        $result = $this->service->update($id, $data);

        if ($result) {
            return $this->success('修改成功');
        }

        return $this->fail('修改失败');
    }

    /**
     * 删除敏感词
     * @param int $id
     * @return Response
     */
    public function delete($id)
    {
        if (!$id) {
            return $this->fail('ID不能为空');
        }

        $result = $this->service->deleteWord($id);

        if ($result) {
            return $this->success('删除成功');
        }

        return $this->fail('删除失败');
    }

    /**
     * 批量删除敏感词
     * @return Response
     */
    public function batchDelete()
    {
        $ids = $this->request->post('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return $this->fail('请选择要删除的敏感词');
        }

        $result = $this->service->batchDeleteWords($ids);

        if ($result) {
            return $this->success('批量删除成功');
        }

        return $this->fail('批量删除失败');
    }

    /**
     * 启用/禁用敏感词
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        if (!$id) {
            return $this->fail('ID不能为空');
        }

        $status = $this->request->get('status', 0);

        if (!in_array($status, [0, 1])) {
            return $this->fail('状态值不正确');
        }

        $result = $this->service->setStatus($id, $status);

        if ($result) {
            return $this->success($status ? '启用成功' : '禁用成功');
        }

        return $this->fail($status ? '启用失败' : '禁用失败');
    }

    /**
     * 获取敏感词详情
     * @param int $id
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function read($id)
    {
        if (!$id) {
            return $this->fail('ID不能为空');
        }

        $detail = $this->service->getWordDetail($id);

        if ($detail) {
            return $this->success($detail);
        }

        return $this->fail('未找到该敏感词');
    }

    /**
     * 导出敏感词
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function export()
    {
        $params = $this->request->getMore(['word', 'status']);
        $data = $this->service->exportWords($params);

        return $this->success('导出成功', $data);
    }

    /**
     * 导入敏感词（支持 .txt / .csv 格式）
     *
     * txt 格式：每行一个敏感词，支持 "词|备注" 格式（竖线分隔）
     * csv 格式：第一列为敏感词，第二列（可选）为备注；首行若为表头则自动跳过
     *
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function import(): Response
    {
        $file = $this->request->file('file');

        if (!$file) {
            return $this->fail('请选择要导入的文件');
        }

        // 校验扩展名
        $extension = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($extension, ['txt', 'csv'], true)) {
            return $this->fail('仅支持 .txt 和 .csv 格式的文件');
        }

        // 校验文件大小（最大 5MB）
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->fail('文件大小不能超过 5MB');
        }

        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);

        if ($content === false) {
            return $this->fail('文件读取失败，请重试');
        }

        // 统一换行符，去除 BOM 头
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if ($extension === 'csv') {
            $data = $this->service->parseCsv($content);
        } else {
            $data = $this->service->parseTxt($content);
        }

        if (empty($data)) {
            return $this->fail('文件内容为空或格式不正确，未解析到有效数据');
        }

        $result = $this->service->importWords($data, $this->adminId);

        $message = "导入完成：成功 {$result['success']} 条，失败 {$result['failed']} 条";

        return $this->success($message, $result);
    }
}
