# 全局队列对接示例（含客服知识库）

## 通用接入模板

1. 在 `app/common/job/{domain}/` 新建 `{Action}Job.php`
2. Job 继承 `BaseJob` + 实现 `ShouldQueue` + `use Dispatchable`
3. Service 保留单条执行方法，并提供 `batch{Action}(array $ids, int $ownerId = -1): array`
4. 批量方法内部完成合法性校验并统一 `::dispatch(...)`
5. Controller 只做参数校验与调用，返回“任务已加入队列”
6. 前端基于状态字段展示进度，避免等待同步结果

## 示例 A：客服知识库向量化

### 改造目标

- `batchVectorize` 从同步执行改为异步入队
- admin/merchant 两端入口行为一致
- 导入后自动向量化改为后台处理

### 关键落地点

- Job：`app/common/job/cs/CsKnowledgeVectorizeJob.php`
- Service：`app/common/service/cs/CsKnowledgeService.php`
- Controller：
  - `app/controller/admin/cs/CsKnowledge.php`
  - `app/controller/merchant/cs/CsKnowledge.php`

### 返回结构建议

- `total`
- `queued_count`
- `invalid_count`
- `queued_ids`
- `invalid_ids`

## 示例 B：其他批量任务复用建议

以下任务均可直接复用同一模式：

- 批量订单状态计算
- 批量商品信息重建/索引更新
- 批量内容解析、摘要、向量化、审核
- 批量数据迁移或回填脚本的在线化接口

## 运行注意

- 队列消费者必须已启动
- Redis/队列连接参数需与部署环境一致
- 生产建议使用守护进程，避免消费中断
- 日志建议在 Service 记录业务异常，Job 保留兜底日志