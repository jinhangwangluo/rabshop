---
name: cs-vectorize-queue
description: This skill should be used when implementing or refactoring any long-running or bulk operations into asynchronous queue execution across this project, including but not limited to CS knowledge vectorization, order tasks, product tasks, and other admin/merchant batch actions.
---

# 全局队列对接规范

## 概述

将项目中的“批量 + 耗时 + 可重试”任务统一改造成异步队列执行，避免接口阻塞并提升系统稳定性。
本技能适用于全项目，不限定在客服知识库场景。

## 何时使用

在出现以下任一情况时启用本技能：

- 控制器接口存在批量循环处理，响应慢或超时风险高
- 业务操作需要失败重试、削峰填谷、后台异步化
- 需要新增标准 Job 并通过 `::dispatch(...)` 统一投递
- 需要统一 admin/merchant 两端提示与返回结构

## 全局标准工作流

### 1. 识别同步瓶颈入口

优先排查以下位置：

- 控制器中的批量接口或导入后自动处理逻辑
- Service 中遍历 ID 的同步循环
- 可能触发第三方调用、AI 计算、批量写库的逻辑

### 2. 定义标准 Job

在 `app/common/job/{domain}/` 新建任务类，并遵循：

- 继承 `BaseJob`
- 实现 `ShouldQueue`
- 使用 `Dispatchable`
- `handle()` 负责调度与兜底日志，不堆积复杂业务判断

### 3. Service 实现“单条处理 + 批量入队”

推荐结构：

- 保留单条处理方法（实际业务执行）
- 新增/改造批量入队方法（参数校验 + `::dispatch(...)`）
- 返回统一统计结构，建议包含：
  - `total`
  - `queued_count`
  - `invalid_count`
  - `queued_ids`
  - `invalid_ids`

### 4. Controller 轻量化

控制器仅负责：

- 参数校验
- 调用 Service 批量入队方法
- 成功返回“任务已加入队列”类文案
- 错误分支统一走 `$this->fail(...)`

### 5. 前端与运维协同

- 前端提示“已提交后台处理”，通过状态字段轮询/刷新查看结果
- 发布前确认消费者进程已启动且队列连接可用
- 生产环境建议守护进程常驻消费

## 项目约束

- 代码符合 PSR-12
- 方法参数尽量使用 PHP 8 类型提示
- 类实例化优先使用 `app()->make()`
- 若新增接口必须补路由，不允许以“控制器/方法名”直连

## 场景示例

客服知识库向量化是本规范的一个示例落地，详见：
`references/global_queue_integration_examples.md`
