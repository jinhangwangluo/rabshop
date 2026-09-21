---
name: thinkphp8-route-guard
description: This skill should be used when defining or reviewing ThinkPHP8 routes to enforce safe ordering rules, especially preventing static GET paths from being shadowed by dynamic parameter routes.
---

## 概述

统一编写与审查 ThinkPHP8 路由定义，重点避免“静态路由被动态路由抢匹配”的错误。
在新增、重构、批量整理 `route/*.php` 时，优先应用本技能。

## 触发场景

出现以下任一情况时启用本技能：

- 新增或修改 ThinkPHP8 路由（如 `Route::get/post/put/delete`）
- 重构分组路由（如 `Route::group`、`Route::resource`）
- 代码评审中发现路由 404、命中错误控制器、参数路由误匹配
- 业务存在同前缀静态路径与动态路径共存（如 `knowledge/search` 与 `knowledge/:id`）

## 路由顺序硬规则（必须遵守）

对“同一 HTTP 方法 + 同一前缀”路由集合，按以下顺序定义：

1. **静态路径优先**：先写固定字面量路径
2. **受限动态参数次之**：再写带参数约束的动态路由
3. **兜底动态参数最后**：最后写宽泛 `:id`、`:name` 等路由

### 典型规则

- `GET knowledge/search` 必须放在 `GET knowledge/:id` 前面
- `GET article/hot`、`GET article/recommend` 必须放在 `GET article/:id` 前面
- 若必须使用动态参数，优先添加 `->pattern(['id' => '\\d+'])` 等约束，降低误匹配

## 标准编写流程

### 1) 先分组再落路由

先按模块分组（如 `Route::group('cs', function () {...})`），再在组内按资源前缀聚合。

### 2) 按方法分段与排序

同一资源下，先按方法分段（GET/POST/PUT/DELETE），每段内部执行“静态优先动态”排序。

### 3) 冲突检查

新增动态路由前，先检查是否已有同前缀静态路由。
如存在，确保静态路由在文件中先出现。

### 4) 语义与风格统一

- 查询用 GET、创建用 POST、更新用 PUT、删除用 DELETE
- 批量动作若项目既有约定使用 POST，可遵循项目现状保持一致
- 禁止把前端接口写成“控制器名/方法名直出 URL”，统一在路由文件显式定义

## 正反示例

### 正确示例

- `Route::get('knowledge/search', 'admin.cs.CsKnowledge/search');`
- `Route::get('knowledge/:id', 'admin.cs.CsKnowledge/read')->pattern(['id' => '\\d+']);`

### 错误示例

- `Route::get('knowledge/:id', 'admin.cs.CsKnowledge/read');`
- `Route::get('knowledge/search', 'admin.cs.CsKnowledge/search');`

上面错误示例会导致 `knowledge/search` 可能被 `:id` 抢先匹配。

## 交付前检查清单

在提交路由改动前，逐项确认：

- 同方法同前缀下，静态路由是否都在动态路由之前
- 动态参数是否加了必要 `pattern` 约束
- 路由是否全部落在 `route/*.php`，而非依赖隐式控制器直达
- 新增接口是否与现有命名风格一致（下划线/资源化约定保持一致）

## 与本项目配合的约定

- 保持项目既有 `route/admin.php`、`route/mobile.php` 的分层组织
- 遵循项目“所有接口必须显式写路由”的规则
- 对知识库等高频冲突资源，优先人工复核静态与动态顺序