---
name: thinkphp-api-chain-test
description: 在本项目中按 ThinkPHP8 route/*.php 与 OpenAPI 导出结果执行接口测试，覆盖登录、低风险 GET、POST/PUT/DELETE 链路、问题标记、清理测试数据和根目录报告沉淀。
---

# ThinkPHP API Chain Test

用于本项目接口测试，尤其是用户要求“测试 route/admin.php、route/mobile.php、route/merchant.php 接口”、“继续测 POST/PUT/DELETE”、“把问题记录到根目录报告”时。

## 基本原则

- 开始前读取 `.claude/rules/api-test-account.mdc` 和 `.claude/rules/host.mdc`，确认账号、登录地址、默认域名和本机服务端口。
- 优先使用已导出的 `runtime/openapi/thinkphp8-*-routes.openapi.json` 获取接口清单；缺少或过期时先使用 `.codex/skills/thinkphp8_route_export.py` 重新导出。
- 接口测试先登录获取 token，报告中只能写 `Authorization: Bearer <token>` 或“token 已获取”，不能写真实 token。
- 只读 GET 可批量测试；POST/PUT/DELETE 会写入、更新或删除数据，执行前必须取得用户明确确认。
- 写接口链路只能更新/删除本轮创建的数据；支付、退款、回调、短信、小程序登录、文件上传等外部依赖或高风险接口默认跳过并记录原因。
- 发现接口失败时先区分：路由未命中、控制器不存在、方法不存在、参数缺失、业务失败、数据库字段错误、第三方依赖失败。

## 测试顺序

1. 登录测试账号，确认 HTTP 200、业务状态 200，并保存临时 token。
2. 执行低风险 GET 批量测试：
   - 跳过动态 path 参数，如 `/:id`、`/{id}`。
   - 跳过明显有副作用或重操作的路径，如 `logout`、`delete`、`status`、`show`、`export`、`download`、`pay`、`refund`、`notify`、`callback`。
   - 将问题写入根目录报告，例如 `ADMIN_API_TEST_REPORT.md`、`MOBILE_API_TEST_REPORT.md`。
3. 执行 POST → PUT → DELETE 链路测试：
   - 优先选择可闭环、可清理的资源，例如移动端收货地址、评论。
   - 每个链路保存创建出的 ID，并在后续 update/delete 中使用该 ID。
   - 删除步骤放入清理结果，清理失败要醒目标记。
   - 对真实 DELETE 路由数量很少的文件，可做路由烟测；如果空载荷业务失败，要标记为“路由可达但缺少业务载荷”。
4. 回测修复：
   - 修复代码后，如果 Swoole 服务未重载，可临时使用 `php think run -H 127.0.0.1 -p <port>` 验证最新代码。
   - 验证后停止临时服务。

## 报告格式

报告放在项目根目录，建议命名：

- `ADMIN_API_TEST_REPORT.md`
- `MOBILE_API_TEST_REPORT.md`
- `MERCHANT_API_TEST_REPORT.md`
- `MOBILE_API_CHAIN_TEST_REPORT.md`

报告至少包含：

- 测试时间、Base URL、路由来源、测试策略。
- 登录结果，但不记录真实 token。
- 汇总：候选接口数、实际测试数、通过数、问题数、跳过数。
- 问题接口表：方法、路径、名称、HTTP、业务状态、问题标记、响应摘要。
- 已通过接口表。
- 跳过接口或未自动执行接口清单及原因。
- 链路测试报告还要记录创建数据 ID、清理结果和残留测试数据说明。

## Mobile 链路优先样例

移动端可以优先覆盖这些低风险链路：

- 收货地址：`POST /api/mobile/user/address/create` → `GET /api/mobile/user/address/detail?id=<id>` → `POST /api/mobile/user/address/update` → `POST /api/mobile/user/address/delete`
- 评论：`POST /api/mobile/comment/create` → `POST /api/mobile/comment/reply` → `POST /api/mobile/comment/delete`
- 客服：`POST /api/mobile/cs/session` → `POST /api/mobile/cs/message` → `PUT /api/mobile/cs/message/mark_session_read`

注意：客服消息通常没有用户端删除接口，执行后会保留测试会话/消息，需要在报告中标记残留记录。

## 修复判断

优先修复明显代码错误：

- `controller not exists`
- `method not exists`
- `Undefined array key`
- `method not exist:think\db\Query->search`
- `SQLSTATE[42S22] Unknown column`

参数缺失类错误通常不是代码错误，应从列表接口抽取真实 ID 或补充必需参数后再测。
