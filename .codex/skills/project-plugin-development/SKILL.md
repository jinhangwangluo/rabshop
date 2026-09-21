---
name: project-plugin-development
description: 在 knowledge-payment v1.2 中初始化、开发、校验和打包插件源码，覆盖后端、平台端、商户端和 UniApp；适用于需要遵循 plugin.json、plugin.dev.json、事件声明及确定性源码包契约的插件工作。
---

# 项目插件开发

## 适用范围

本 Skill 只处理当前 `v1.2` 分支的插件开发态源码：生成四端骨架、检查清单和源码映射、归集未签名源码包。它不执行数据库迁移、安装/卸载、核心文件覆盖、远程上传、市场购买、正式签名或发布。

开始任何插件任务前，完整读取仓库根目录的 `AGENTS.md`，再按涉及端读取对应规则：

- PHP、路由、迁移：`.claude/rules/code.mdc`，必要时 `.claude/rules/service.mdc`。
- 平台端或商户端 Vue：`.claude/rules/Element.mdc`、`.claude/rules/vue3.mdc`、`.claude/rules/frontend-vue3-admin.mdc`。
- 插件平台端页面（`template/admin/src/plugins/{code}/`）：**必须**先读 `docs/plugins/admin-ui.md`，页面骨架、Element Plus 选型、CSS 变量、命名与自检清单均在其中；生成模板已按该规范内置。
- UniApp：`.claude/rules/uniapp.mdc`，并按需使用 `uni-css-guard`。
- 路由：按需使用 `thinkphp8-route-guard`。

## 固定目录契约

开发态源码分散在可直接编译的工程中：

```text
plugins/{code}/
├── plugin.json
├── plugin.dev.json
├── resources/
├── backend/
└── tests/
template/admin/src/plugins/{code}/
template/merchant/src/plugins/{code}/
template/uniapp/src/plugins/{code}/
```

`plugin.json` 使用打包后的标准相对路径；`plugin.dev.json` 只保存仓库相对的开发源码映射，不能写入绝对路径，也不能进入 ZIP。插件编码使用小写蛇形命名（例如 `seckill`），PHP 命名空间必须是 `Plugin\\{StudlyCode}`。

## 工作流

1. 冻结业务规则和事件：先从项目事件目录选择已存在的事件，不根据名称臆造事件。清单中的 `events`、权限、菜单、路由和迁移必须与实现一致。
2. 初始化：运行 `scripts/init_plugin.php`。脚本会在仓库内临时目录生成完整镜像，预检所有目标不存在后才落盘；任一目标已存在或路径不安全时整体失败，不覆盖、不合并。
3. 开发：只编辑插件目录和四端源码目录。后端路由文件使用 ThinkPHP 8 原生分组，宿主检测已启用插件后自动加载固定入口；`app/event.php`、`pages.json` 和公共组件仍由宿主注册机制维护，Skill 不为单个插件直接修改它们。
4. 校验：运行 `scripts/validate_plugin.php --code={code}`。开发态校验会检查 JSON、端映射、路径/软链接、命名空间、路由顺序、事件、迁移、覆盖目标和注册入口。
5. 打包：运行 `scripts/package_plugin.php --code={code} --mode=source --output=...`。脚本只读归集源码，排除状态、密钥、缓存和构建产物，按固定顺序/时间戳生成 ZIP；失败时不创建可用制品。`--dry-run` 只显示清单。
6. 向用户报告生成文件、校验结果、未冻结的业务规则和可运行入口；不要把本地源码 ZIP 描述为已签名或可安装制品。

## 命令

```bash
php83 .codex/skills/project-plugin-development/scripts/init_plugin.php \
  --name="秒杀" --code="seckill" \
  --surfaces="backend,admin,merchant,mobile" \
  --description="限时秒杀活动插件"

php83 .codex/skills/project-plugin-development/scripts/validate_plugin.php \
  --code="seckill"

php83 .codex/skills/project-plugin-development/scripts/package_plugin.php \
  --code="seckill" --mode="source" \
  --output="runtime/plugin_packages/seckill/seckill-0.1.0-source.zip"
```

脚本支持 `--root` 指定仓库根目录，便于在隔离临时仓库中测试；`validate_plugin.php` 还支持 `--package=/path/to/source.zip` 验证已归集源码包。所有参数都经过白名单和长度检查。

## 参考资料

- 清单字段和路径：阅读 [references/manifest.md](references/manifest.md)。
- 后端 Provider、Controller、Service 和迁移：阅读 [references/backend.md](references/backend.md)。
- 平台端/商户端注册入口：阅读 [references/admin-merchant.md](references/admin-merchant.md)。
- UniApp 页面、注册器和跨端限制：阅读 [references/mobile.md](references/mobile.md)。
- 事件声明、同步/异步语义和幂等：阅读 [references/events.md](references/events.md)。
- 归集、排除、确定性 ZIP：阅读 [references/packaging.md](references/packaging.md)。
- 路径、密钥、状态和发布边界：阅读 [references/release-security.md](references/release-security.md)。

## 安全边界

- 所有开发映射解析后的真实路径必须仍位于仓库根目录内；绝对路径、`..`、危险软链接、大小写冲突和目标路径覆盖都拒绝。
- 插件不得通过安装脚本执行 Shell 命令；迁移只作为人工审核的源码，不由这些脚本执行。
- `.env*`、`.git`、`runtime`、`node_modules`、`vendor`、`dist`、`unpackage/dist`、日志、缓存、私钥和运行状态永不进入源码包。
- 事件处理器必须幂等；异步处理失败不能通过前端伪造成功状态。正式签名、远程构建和上传由官网/独立服务负责。
