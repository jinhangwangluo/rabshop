---
name: apifox-sync
description: 从 ThinkPHP8 显式路由、控制器、验证器、模型和接口实测结果导出 OpenAPI/Markdown 接口文档；适用于需要整理、导出或更新后端接口契约的任务，Apifox 导入仅作为用户明确要求后的可选步骤。
version: 2.0.0
source: thinkphp8-route-controller-contract
---

# ThinkPHP8 接口导出

## 概述

当用户要求“导出接口”“生成接口文档”“整理后端接口契约”“把接口转成 OpenAPI/Markdown”或继续使用旧称“同步 Apifox 文档”时，使用本技能。

本技能的目标是基于 ThinkPHP8 项目的真实代码导出接口文档，而不是凭控制器名、方法名或旧注释猜测。接口来源以 `route/*.php` 中显式声明的路由为准，结合 Controller、Validate、Service、Model、前端调用和接口实测结果补齐请求与响应结构。

当用户要求全量导出并导入 Apifox 时，先用 `.codex/skills/thinkphp8_route_export.py` 生成 OpenAPI JSON，再用 `.codex/skills/apifox_push.py` 导入。导入前必须确认外部 API 写入权限。

## 适用边界

- 默认先生成本地文档或规范文件，不自动调用外部平台 API。
- 如用户明确要求推送到 Apifox，可在导出 OpenAPI 后再使用 `.codex/skills/apifox_push.py`，并在发送令牌、访问外部 API 前按高风险操作确认机制征得同意。
- 不执行数据库迁移、删除历史文档、批量更新线上数据或生产接口写操作，除非用户明确确认。
- 遇到接口代码和旧文档冲突时，以显式路由、当前 Controller/Service 行为和实测响应为准，并在结果中标注取舍。

## 项目约定

当前项目是 ThinkPHP8 后端，接口路由集中在：

- `route/admin.php`
- `route/merchant.php`
- `route/mobile.php`
- `route/route.php`

导出时遵守项目规则：

- 所有接口必须来自显式 `Route::*` 或 `Route::resource` 定义，不能依赖控制器自动路由推断。
- 静态路由优先于动态路由，处理 `:id`、`:roomId` 等动态参数时转换为 OpenAPI 的 `{id}`、`{roomId}`。
- Controller 只作为入口分析请求参数、调用 Service 和返回结构；业务含义优先下钻到 Service、Model、枚举和字段注释确认。
- 成功响应通常来自 `$this->success()`，失败响应通常来自 `$this->fail()` 或统一异常机制。
- 分页接口优先识别 Service 中的 `getPageValue()` / `getPagesValue()` 以及列表响应字段。

## 导出流程

### 1. 确认导出范围

先明确用户要导出的范围：

- 指定路由文件、路由分组、URL 前缀或单个 Controller。
- 管理端、商户端、移动端或全部接口。
- 输出格式：默认 Markdown；需要机器可读或导入工具时输出 OpenAPI 3.0 JSON。

如果用户未指定范围，优先根据本轮修改文件或用户提到的业务模块定位，避免一次性导出全项目。

### 2. 读取真实路由

从 `route/*.php` 读取路由定义，提取：

- HTTP 方法：`get`、`post`、`put`、`delete`、`patch`、`any`、`resource`
- 完整路径：父级 `Route::group()` 前缀 + 子路由路径
- 控制器映射：如 `merchant.cs.CsKnowledge/index`
- 动态参数：`:id`、`:configId` 等
- 路由约束：`->pattern(['id' => '\d+'])`
- 中间件：当前路由、父分组和链式 `->middleware()` 的合并结果

`Route::resource()` 按 ThinkPHP 资源路由展开为常见动作：

| 动作 | 方法 | 路径 | 控制器方法 |
| --- | --- | --- | --- |
| index | GET | `/resource` | `index` |
| create | GET | `/resource/create` | `create` |
| save/store | POST | `/resource` | `save` 或 `store` |
| read/show | GET | `/resource/{id}` | `read` 或 `show` |
| edit | GET | `/resource/{id}/edit` | `edit` |
| update | PUT | `/resource/{id}` | `update` |
| delete/destroy | DELETE | `/resource/{id}` | `delete` 或 `destroy` |

展开时必须结合 `except()`、`only()`、自定义方法名和项目实际 Controller 方法存在性核对。

### 3. 分析 Controller 和数据契约

定位控制器文件后读取目标方法及其直接依赖，提取：

- Query 参数、Body 参数、Path 参数和 Header/Auth 要求。
- `request()->param()`、`$this->request->param()`、`$this->request->post()`、`$this->request->get()`、`$this->request->only()`、`$this->request->params()` 等入参来源。
- Validate 类、`validate()` 调用、规则数组、场景 `scene()` 和自定义错误信息。
- Service 方法调用链、DTO/数组组装、事务和业务异常。
- Model 字段、搜索器、关联、字段注释、枚举/常量映射。
- 返回数据结构，尤其是列表分页、详情、选项、保存后返回、状态切换和删除响应。

不要为了让文档“完整”而把一个字段解释成另一个业务字段。字段含义不确定时标为“待确认”，并写明证据不足的位置。

### 4. 补充实测证据

接口可访问时，用本项目接口测试规则获取账号、域名和 token 后实测关键接口：

- 默认本地域名参考 `http://127.0.0.1:25780`。
- 写接口测试前说明会写入哪些测试数据；生产或远程写接口必须先确认。
- 至少覆盖一个成功场景和一个边界场景；只读接口优先实测。
- 将请求体、响应体和代码推导结果逐字段核对。

接口不可访问时，不声称已联调；在导出结果中列出具体阻塞原因和未验证范围。

### 5. 生成 Markdown 文档

Markdown 文档建议包含以下信息：

```markdown
## 接口名称

- 方法与路径：GET /api/mobile/product/list
- 控制器：app/controller/mobile/store/Product.php::list
- 权限：可选登录 / 必须登录 / 管理端登录 / 商户端登录
- 说明：一句话描述真实业务行为

### 请求参数

| 字段 | 位置 | 类型 | 必填 | 说明 | 约束/默认值 |
| --- | --- | --- | --- | --- | --- |

### 响应字段

| 字段 | 类型 | 说明 |
| --- | --- | --- |

### 示例

请求示例和响应示例只使用已确认字段；敏感值脱敏。

### 证据

- 路由：route/mobile.php:36
- 控制器：app/controller/mobile/store/Product.php
- 验证器/服务/模型：按实际文件列出
```

### 6. 生成 OpenAPI 3.0 JSON

需要 OpenAPI 时，默认输出到 `runtime/openapi/thinkphp8-routes.openapi.json` 或用户指定路径。推荐结构：

```json
{
  "openapi": "3.0.3",
  "info": {
    "title": "Knowledge Payment API",
    "version": "1.0.0"
  },
  "servers": [
    {
      "url": "http://127.0.0.1:25780",
      "description": "local"
    }
  ],
  "paths": {},
  "components": {
    "securitySchemes": {
      "bearerAuth": {
        "type": "http",
        "scheme": "bearer",
        "bearerFormat": "JWT"
      }
    },
    "schemas": {}
  }
}
```

映射规则：

- ThinkPHP 路径参数 `:id` 转为 OpenAPI `{id}`，并补充 `in: path` 参数。
- `GET` 参数放入 `parameters`，`POST/PUT/PATCH/DELETE` 的业务入参优先放入 `requestBody`。
- 中间件含登录鉴权时加 `security: [{"bearerAuth": []}]`，可选登录需在描述中说明。
- `Route::any()` 可按实际 Controller 行为拆分，无法确认时使用用户指定方法或标注为多方法接口。
- 响应结构只写已确认字段；通用成功包装可复用统一 schema。

### 7. 全量 route 导出

当用户要求把根目录 `route` 下所有路由全部整理成 JSON 时，使用脚本：

```bash
python3 ".codex/skills/thinkphp8_route_export.py"
```

默认行为：

- 扫描 `route/admin.php`、`route/merchant.php`、`route/mobile.php`、`route/route.php`。
- 输出 `runtime/openapi/thinkphp8-routes.openapi.json`。
- 排除 `Route::miss()` 页面兜底路由。
- 保留 `api/test`、`api/rule`、安装接口、部署钩子、直播回调等显式 API 路由。
- 展开 `Route::resource()` 为常见资源接口。
- 将 ThinkPHP 动态参数 `:id` 转为 OpenAPI `{id}`。
- 自动读取 Controller 方法，整理 `$this->request->getMore()`、`postMore()`、`get()`、`post()`、`param()`、`only()` 中可识别的请求参数。
- 支持从 `getIndexFields()`、`getRequestFields()` 等同类辅助方法返回数组中补充字段。
- 支持从方法注释的“GET 参数 / POST 参数”列表中补充参数类型和说明。
- 同 method + path 的重复定义只保留第一处，并在接口上记录 `x-duplicate-route-definitions`。

Apifox 目录规则：

- 一级目录按路由文件名：`admin.php`、`merchant.php`、`mobile.php`、`route.php`。
- 二级目录优先使用业务 `Route::group('模块名')`。
- 无命名业务分组时使用路由路径首段。
- 仍无法判断时归入 `未分组`。
- 每个接口通过 `x-apifox-folder` 写入目录路径，如 `merchant.php/content`。

导入前先执行 dry-run 预览：

```bash
python3 ".codex/skills/thinkphp8_route_export.py" --dry-run
```

## 验证要求

交付前必须检查：

- 导出接口均能追溯到显式路由。
- 动态路由没有覆盖静态路由；必要时使用 `thinkphp8-route-guard` 规则核对顺序。
- 检查 `x-duplicate-route-count`，确认重复 method + path 是否符合预期。
- 请求字段、响应字段、数据库字段和页面/API 调用字段没有错误兼容映射。
- Markdown 或 OpenAPI JSON 语法有效。
- 如修改或新增导出脚本，执行脚本自测；如只是手工导出文档，至少重读导出片段和证据链接。

## 可选 Apifox 导入

只有用户明确要求把 OpenAPI 导入 Apifox 且确认外部写入风险后才执行：

```bash
python3 ".codex/skills/apifox_push.py" --spec-file "runtime/openapi/thinkphp8-routes.openapi.json"
```

导入脚本默认使用 `OVERWRITE_EXISTING` 覆盖同 method + path 接口，`KEEP_EXISTING` 保留模型，`updateFolderOfChangedEndpoint` 同步目录，不删除 Apifox 中未出现在本次 JSON 的旧接口。

导入前确认：

- 所有 Apifox 相关 `.env` 配置必须使用 `APIFOX_` 前缀。
- `APIFOX_ACCESS_TOKEN`
- `APIFOX_PROJECT_ID`
- `APIFOX_BASE_URL`（仅 `--mode url` 时必填；命令行 `--base-url` 可覆盖）
- `APIFOX_ENDPOINT_FOLDER_ID`（可选）
- `APIFOX_SCHEMA_FOLDER_ID`（可选）
- 是否允许向 Apifox 外部 API 发送接口规范

导入后把脚本输出的新增、更新、失败数量转述给用户。

## 触发示例

- “导出这个 ThinkPHP8 接口”
- “生成接口文档”
- “把商户端接口整理成 OpenAPI”
- “导出后台用户模块接口”
- “更新 Apifox 文档”
- “使用 apifox-sync”
