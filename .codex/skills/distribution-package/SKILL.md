---
name: distribution-package
description: 生成和验证项目分布完整包；适用于需要把源码、public 静态/编译产物、前台编译前源码、技能资源和默认 SQL 一起交付，同时排除敏感信息、内部目录、文档测试与构建缓存的发布任务。
---

# 分布完整包打包

## 目标

先使用项目根目录的 `build.sh` 完成前端编译，再使用 `scripts/package_distribution.py` 生成可复现的 ZIP 完整包。该 Skill 只做编译、静态归集和校验，不执行数据库迁移、安装、清理、远程上传、发布或生产写入。

完整包包含：

- 后端运行源码、配置模板、依赖和 `public/` 下的静态及已编译产物。
- `.codex/skills/` 保持仓库当前相对路径；存在时，`specs/`、`rules/` 和根目录 `AGENTS.md` 继续重定位到 `public/source/spec/`。
- 指定的默认 SQL 复制为 `database/install.sql`，并依据其中出现的表名生成 `database/clean.sql`。
- `PACKAGE-MANIFEST.json`，记录 SQL 表名、清单哈希和排除项，不包含本机绝对路径。

## 硬性约束

1. 不允许把 `.env*`、私钥/证书、常见云密钥、日志、归档包和运行态凭据放入包内。
   `vendor/` 的目录和文件均保留；OSS 测试凭据和 php-jwt 文档中的公开私钥示例仅在生成的 ZIP 内替换为占位值，源文件不改动。若上游文件变化导致脱敏失败，则停止打包。
2. 项目自身文本文件中不得保留带图片扩展名的 `http://` 或 `https://` 图片地址。先把图片落到仓库可交付的本地路径，再打包；脚本不会擅自下载远程图片。完整纳入的第三方 `vendor/` 不检查远程图片引用，但仍检查敏感信息。
   安装 SQL 中的 `/storage/...` 图片路径必须在 ZIP 的 `public/storage/...` 下有对应文件；缺失时不得生成“完整包”。
3. 安装 SQL 必须至少包含一条 `INSERT ... INTO` 或 `REPLACE INTO` 默认数据语句，并且必须能提取出表名。
4. `database/clean.sql` 只根据安装 SQL 的表名生成 `TRUNCATE TABLE`，不会连接数据库，也不会执行删除。
5. 除 `vendor/` 外，下列目录或文件不进入完整包：`docs`、`generated`、`material`、`tests`、`tools`、`build`、`assets`、`.vscode`、`.zcode`、`.qoder`、`.hyperframes`、`.git`、`.codexbuddy`、`.agents`、`.claude`、`node_modules`、`runtime`、`__pycache__`。所有目录仍排除 `.DS_Store`、`*.log`、`*.pyc` 及敏感文件。另排除 `.codebuddy`、`.idea`、`.trae`、`.codex` 原目录（技能内容按上面的规则重定位）、根目录测试缓存、IDE 元数据、非交付文档、`api_docs.php`、生成失败记录 `upgrade.local.failures.json`、演示用 `template/uniapp/src/styles/iconfont/demo.css` 与 `demo_index.html`，以及被本地化 SQL 替代的 `database/migrations/v1.2/upgrade.sql`。
6. 脚本拒绝跟随源树软链接、拒绝路径穿越，并且不覆盖已有输出文件。

## 用法

在仓库根目录直接执行：

```bash
bash build.sh
```

前端构建完成后，再执行：

```bash
python3 .codex/skills/distribution-package/scripts/package_distribution.py build \
  --root . \
  --install-sql database/migrations/v1.2/upgrade.local.sql \
  --output runtime/distribution/example-full.zip \
  --version 1.2.0
```

先看清单但不创建 ZIP：

```bash
python3 .codex/skills/distribution-package/scripts/package_distribution.py build \
  --root . \
  --install-sql database/migrations/v1.2/upgrade.local.sql \
  --output runtime/distribution/example-full.zip \
  --dry-run
```

对已经生成的包做独立复核：

```bash
python3 .codex/skills/distribution-package/scripts/package_distribution.py verify \
  --package runtime/distribution/example-full.zip
```

除 `template/` 外，脚本默认不重定位项目目录；如确有额外归集需求，可重复传入 `--public-source path=destination`，路径必须位于仓库根目录内。包名、版本和输出目录由调用方决定，脚本不会猜测生产版本。

## 验收边界

- `quick_validate.py` 只验证 Skill 元数据和脚手架完整性。
- `build --dry-run` 验证归集计划、SQL 表名、敏感信息、远程图片和排除规则。
- `build` 后必须继续执行 `verify`，并检查 ZIP 条目无绝对路径或 `..` 穿越、默认 SQL 含数据写入、清理 SQL 存在、排除项没有泄漏。
- 这套流程不等价于真实安装、数据库执行、前台运行或生产部署；这些需要另行授权和联调。

详细的包布局和 SQL 清理规则见 [references/package-layout.md](references/package-layout.md)。
