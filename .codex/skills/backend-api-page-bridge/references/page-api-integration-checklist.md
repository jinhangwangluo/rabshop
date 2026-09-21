# 页面接口对接执行清单

## 1. 页面功能拆解

开始编码前确认：

- 目标页面路径。
- 页面展示区块。
- 表单字段。
- 列表字段与分页需求。
- 按钮与交互行为。
- 进入页面、刷新、提交、删除、切换状态等接口需求。
- 是否需要登录态。

## 2. 接口定位清单

按以下顺序检索：

1. 用户手动提供的接口、文件或控制器。
2. 前端 API 文件：`template/uniapp/api/*.js` 或 `template/admin/src/api/*.js`。
3. 路由文件：`route/mobile.php`、`route/admin.php`、`route/merchant.php`。
4. 控制器：`app/controller/**`。
5. Service：`app/common/service/**`。
6. Model：`app/common/model/**`。
7. 数据库迁移：`database/migrations/*.php`。
8. 数据库设计文档：`docs/planning/06_DATABASE_DESIGN.md`。

定位接口后记录：

- 请求方式。
- 接口路径。
- 是否需要鉴权中间件。
- 控制器方法。
- Service 方法。
- 入参来源。
- 返回结构来源。

## 3. 字段意义确认清单

字段意义不明确时，必须至少查其中两个来源：

- 数据库迁移字段注释。
- 模型表名、关联、访问器、字段转换。
- Service 中状态枚举、金额转换、时间格式化。
- 控制器参数过滤和返回字段。
- 数据库设计文档或业务规划文档。
- 接口真实响应。

重点确认：

- 状态字段：`status`、`type`、`is_*`。
- 关联字段：`*_id`、`pid`、`user_id`、`merchant_id`。
- 金额字段：单位是元还是分，是否需要格式化。
- 时间字段：时间戳、日期字符串还是 datetime。
- 图片字段：是否需要 `resolveAssetUrl` 或后端已返回完整 URL。
- 分页字段：`list / total / page / limit / current_page` 的实际结构。

## 4. 测试 MCP 清单

接口测试必须使用 `api-tester` MCP。

- 调用测试工具前先获取工具描述。
- 查询接口用 `http_request` 或 `test_endpoint`。
- 写接口至少测试成功路径和必要失败路径。
- 记录实际返回：`code / msg / data`。
- 若需要登录，先实测登录接口获取 token，再带 `Authorization` 访问需登录接口。
- 接口失败时先定位原因，不得绕过测试继续写页面。

## 5. 前端 API 封装清单

UniApp：

- API 文件位于 `template/uniapp/api`。
- 使用 `get / post / put / del / request`。
- 需要登录接口设置 `AUTH_MODES.REQUIRED`。
- 方法名使用 `camelCase`。
- 每个导出方法补 JSDoc。

管理后台：

- API 文件位于 `template/admin/src/api`。
- 使用项目统一 request 封装。
- 方法名与业务动作一致。

共同要求：

- 不重复封装已有接口。
- 不在 API 层写页面逻辑。
- 不新增无意义中转层。

## 6. 页面改造清单

改页面时检查：

- 是否移除会覆盖接口数据的 mock 默认值。
- 是否有 `loading` 和 `finally` 收敛。
- 是否有空态和错误提示。
- 是否显式做接口字段到页面字段的转换。
- 是否避免直接盲传整个页面状态。
- 是否处理图片、金额、时间、状态文案格式化。
- 是否兼容 H5 和微信小程序。

## 7. 二次验证清单

交付前完成：

- 再次测试查询接口。
- 再次测试写接口。
- 页面使用字段与接口响应字段逐项对齐。
- 编辑文件 lint 通过。
- 简要说明字段依据和接口测试结果。
