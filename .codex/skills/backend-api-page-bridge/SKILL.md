---
name: backend-api-page-bridge
description: 当需要为 UniApp 或管理后台页面对接后端接口、补充前端 API 封装、根据页面功能自动检索或按用户给定文件定位接口，并通过数据库字典与接口测试确认字段意义和返回结构后再编写页面时，应使用此技能。
---

# backend-api-page-bridge

## 概述

用于把前端页面功能与后端接口进行可靠联调：先理解页面需要什么数据和交互，再自动检索或按用户给定文件定位接口、路由、控制器、服务、模型与数据库字段，最后使用测试 MCP 实测接口返回，确认字段含义后再改页面。

核心原则：禁止盲目猜接口返回字段，禁止只看前端旧代码就直接绑定字段；字段意义不明确时，必须回查数据库迁移、模型、服务、控制器、路由和接口实测响应，形成字段映射后再编码。

## 触发场景

在以下场景使用此技能：

- 用户要求“页面对接接口”“联调接口”“把静态数据换成接口数据”。
- 用户提供页面路径、API 文件、后端控制器、路由或接口地址，要求完成前后端对接。
- 页面功能已明确，但接口文件或字段不明确，需要自动检索项目找到可用接口。
- 接口返回字段含义不确定，需要结合数据库字典或迁移文件判断字段意义。
- 新增或修改前端 `template/uniapp/api/*`、`template/admin/src/api/*` 与页面调用逻辑。

## 必要输入与自动检索

优先从用户输入、当前打开文件、页面功能和项目结构中自行收集信息。

### 用户可能手动提供

- 前端页面文件，例如 `template/uniapp/pages/users/userinfo/index.vue`。
- API 文件，例如 `template/uniapp/api/auth.js`。
- 后端路由文件，例如 `route/mobile.php`、`route/admin.php`、`route/merchant.php`。
- 控制器、Service、Model 或接口地址。
- 需要完成的功能清单，例如“获取详情、提交保存、删除、分页列表”。

### 未提供时自动检索

按页面功能关键词自动检索：

1. 在前端页面与 API 目录中搜索业务词、页面路由、旧 mock 字段、按钮文案。
2. 在 `route/*.php` 中搜索对应接口路径与控制器映射。
3. 在 `app/controller/*` 中搜索控制器方法。
4. 在 `app/common/service/*` 中搜索业务处理与返回字段组装。
5. 在 `app/common/model/*` 与 `database/migrations/*` 中搜索表名、字段名、字段注释。
6. 在 `docs/planning/06_DATABASE_DESIGN.md` 和相关规划文档中查找表结构说明。

## 固定工作流

### 第 1 步：拆解页面功能

执行以下动作：

1. 读取目标页面，列出页面展示区、表单项、按钮、筛选项、列表项、弹层、状态切换。
2. 把页面行为拆成接口需求，例如：
   - 进入页面获取详情。
   - 下拉刷新 / 分页获取列表。
   - 保存表单。
   - 删除 / 取消 / 支付 / 退出登录。
3. 标记每个功能需要的入参、展示字段、提交字段、鉴权要求和刷新策略。
4. 不要先写页面字段绑定；先完成接口和字段确认。

### 第 2 步：定位候选接口

执行以下动作：

1. 若用户给了接口或文件，先以用户输入为主定位。
2. 若用户未给接口，按页面功能关键词在 `template/uniapp/api`、`template/admin/src/api`、`route`、`app/controller` 中检索。
3. 确认接口的请求方式、路径、鉴权中间件、控制器方法、Service 方法。
4. 若存在多个候选接口，优先选择路由显式定义、控制器方法清晰、Service 有业务语义、前端已有 API 封装的接口。
5. 若没有合适接口，先说明缺口，再按项目后端规范补后端接口、路由、Service、Model 或验证逻辑。

### 第 3 步：建立字段字典与意义映射

字段含义不明确时，必须先对照数据库字典，不得靠字段名猜测。

执行顺序：

1. 查数据库迁移：`database/migrations/*.php`，读取字段类型、默认值、注释、索引、关联关系。
2. 查模型：`app/common/model/*`，读取表名、关联、访问器、转换器、隐藏字段、时间字段。
3. 查 Service：`app/common/service/*`，读取字段聚合、格式化、状态枚举、金额/时间转换。
4. 查控制器：`app/controller/*`，读取入参、校验、返回结构、字段过滤。
5. 查文档：优先 `docs/planning/06_DATABASE_DESIGN.md`，再看相关业务规划文档。
6. 输出或在思考中形成字段映射：页面字段 → 接口字段 → 数据库字段 → 字段意义 → 是否需要格式化。

重点字段必须明确：

- `status`、`type`、`is_*`、`*_id`、`pid`、`sort`。
- 金额字段，如 `price`、`amount`、`pay_price`。
- 时间字段，如 `create_time`、`update_time`、`expire_time`。
- 图片/文件字段，如 `image`、`cover`、`avatar`。
- 登录用户相关字段，如 `user_id`、`uid`、`phone`、`nickname`。

### 第 4 步：使用测试 MCP 实测接口

接口字段确认必须以真实响应为准。项目要求接口测试使用 `api-tester` MCP，不使用普通 `curl` 方式。

执行规则：

1. 调用 MCP 前必须先获取对应工具描述。
2. 普通请求使用 `api-tester` 的 `http_request`。
3. 需要断言状态码或字段时使用 `test_endpoint`。
4. 需要同时检查多个健康接口时使用 `api_health_check`。
5. 登录接口联调可使用平台管理员账号记忆：`admin / 123456`；移动端需要用户 token 时，先定位现有登录接口并实测登录响应。
6. 记录真实响应中的 `code / msg / data` 结构、字段名、字段类型、空值情况、分页结构。
7. 若接口失败，先判断是鉴权、参数、路由、后端异常还是数据为空；不要绕过失败直接写页面。

### 第 5 步：确认前端 API 封装

根据前端端类型选择位置：

- UniApp：统一写入或复用 `template/uniapp/api/*.js`。
- 管理后台：统一写入或复用 `template/admin/src/api/*.js`。

执行规则：

1. 优先复用已有业务 API 文件；禁止重复封装同一个接口。
2. API 方法使用 `camelCase`，方法名表达业务含义。
3. UniApp API 必须通过 `api/request.js` 暴露的 `get / post / put / del / request` 发起。
4. API 层只做请求与返回，不写页面跳转、弹窗状态、DOM 操作。
5. 新增导出方法必须写 JSDoc：用途、参数、返回值、鉴权要求、典型使用场景。
6. 鉴权模式必须与后端路由中间件一致，例如需登录接口使用 `AUTH_MODES.REQUIRED`。

### 第 6 步：改造页面数据流

完成接口和字段确认后再改页面。

执行规则：

1. 移除或替换页面 mock 数据，不保留会覆盖接口数据的默认假数据。
2. 使用真实接口字段绑定页面；字段名不一致时，用明确的 `normalizeXxx` 方法转换。
3. 所有接口调用必须处理 loading、空态、错误态和 finally 收敛。
4. 表单提交前按页面字段和后端字段做显式映射，不直接把整个页面状态对象盲传给后端。
5. 列表分页要确认接口分页字段，如 `list / total / page / limit / current_page`，不得猜测。
6. 图片、金额、时间、状态文案必须根据字段字典或 Service 返回规则格式化。
7. H5 与微信小程序兼容：避免未保护的 `window / document / navigator / wx`。

### 第 7 步：二次实测与页面自检

编码完成后必须再次实测关键接口。

至少完成：

1. 实测页面进入时调用的查询接口。
2. 实测提交、删除、切换状态等写接口。
3. 对比页面实际使用字段与接口真实返回字段，确认没有未定义字段。
4. 检查 API 封装、页面 import、loading、空态、错误态、鉴权跳转。
5. 对编辑过的文件运行 lint；若引入错误，先修复再交付。

## 项目检索入口

本项目常用入口：

- UniApp API：`template/uniapp/api/request.js`、`template/uniapp/api/*.js`。
- UniApp 页面：`template/uniapp/pages/**/index.vue`、`template/uniapp/components/**/*.vue`。
- 管理后台 API：`template/admin/src/api/*.js`。
- 移动端路由：`route/mobile.php`。
- 管理端路由：`route/admin.php`。
- 商户端路由：`route/merchant.php`。
- 后端控制器：`app/controller/**`。
- 后端服务：`app/common/service/**`。
- 后端模型：`app/common/model/**`。
- 数据库迁移字典：`database/migrations/*.php`。
- 数据库设计文档：`docs/planning/06_DATABASE_DESIGN.md`。

需要细查执行清单时，读取 `references/page-api-integration-checklist.md`。

## 交付要求

每次使用此技能，至少说明：

- 页面功能拆解结果。
- 已定位的接口、请求方式、路由、控制器/Service 来源。
- 字段映射依据：接口响应、数据库迁移/模型/文档中的哪一处确认了字段意义。
- 已新增或修改的 API 封装。
- 页面如何消费接口、如何处理 loading / 空态 / 错误态。
- 测试 MCP 实测结果和 lint 自检结果。

## 禁止事项

- 禁止没测试接口就直接写页面字段绑定。
- 禁止不知道字段意义时只按字段名猜测。
- 禁止用普通 `curl` 替代 `api-tester` MCP 做接口测试。
- 禁止在页面中直接写 `uni.request` 或直接拼完整后端地址。
- 禁止新增重复 API 封装或无意义中转层。
- 禁止把后端返回原始对象无选择地整体塞进页面状态。
