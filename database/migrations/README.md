# 数据库迁移（按版本组织）

迁移文件按**版本号**分目录存放，每个版本目录代表一次对外发布的版本迭代。

## 目录结构

```
database/migrations/
├── README.md          ← 本说明
├── v1.0/              ← v1.0 版本迁移
│   ├── 20220708161701_create_admin.php
│   ├── ...            ← 迁移文件（命名沿用 YYYYMMDDHHMMSS_xxx.php）
│   └── upgrade.sql    ← 可选：该版本可直接执行的增量 SQL（DBA 手动执行用）
└── v1.2/              ← 后续版本同理
    ├── 2026xxxx_xxx.php
    └── upgrade.sql
```

## 约定

- **版本目录名**：`vX.Y`（与 git 分支 / 发布版本号一致，如 `v1.0`、`v1.1`）。
- **迁移文件**：沿用 think-migration 命名 `YYYYMMDDHHMMSS_名称.php`，放入对应版本目录。
- **upgrade.sql（可选）**：该版本相对上一版本的增量 SQL，可直接在数据库客户端执行；
  没有增量 SQL（如版本无表结构变更）时可不放此文件。
- **整库参照**：`database/rabbit.sql` 为全库数据备份/参照，不属于版本增量 SQL。

## 命令行为（app 层覆盖，兼容版本目录，不修改 vendor）

think-migration 原生只扫描 `database/migrations` 平铺目录。为使 `migrate:*` 命令支持版本
子目录，在 **app 层注册了同名命令覆盖**（未改动任何 vendor 文件）：

| 文件 | 说明 |
|------|------|
| `app/common/command/migrate/Run.php` 等 4 个 | 继承 vendor 命令，仅重写 `getPath()` 为 `database/migrations/*`，使扫描 glob 覆盖所有版本子目录（影响 run / rollback / status / breakpoint） |
| `app/common/command/migrate/Create.php` + `VersionedCreator.php` | `migrate:create` 写入**最新版本目录**（`--version-dir v1.1` 可指定） |
| `app/common/command/migrate/RegisterVersionedMigrateCommands.php` | 命令注册器（带进程内去重） |

**覆盖原理**：think-migration 通过 `Console::starting` 注册命令回调，回调按注册顺序执行、后注册者
覆盖同名命令。因此注册器必须在应用初始化（所有 vendor 服务 boot）完成后执行：

- **CLI**：`think` 入口脚本在 `$app->initialize()` 后调用注册器；
- **HTTP**（安装流程无 `proc_open` 时走 `Console::call` 兜底）：`app/event.php` 中监听
  `\think\event\HttpRun` 事件触发注册。

命令用法：

| 命令 | 行为 |
|------|------|
| `php think migrate:run` / `migrate:rollback` / `migrate:status` / `migrate:breakpoint` | 自动扫描**所有版本目录**下的迁移文件（按版本号排序执行） |
| `php think migrate:run --version-dir v1.1` | **仅执行指定版本目录**的迁移（目录不存在会报错） |
| `php think migrate:run -t 20260808000200` | 执行到指定迁移版本号（vendor 原生 `-t/--target`） |
| `php think migrate:create 名称` | 创建迁移文件到**最新版本目录**（当前为 `v1.2`） |
| `php think migrate:create 名称 --version-dir v1.1` | 创建迁移文件到指定版本目录（目录不存在会自动创建） |

> 注：`migrate:create` 的 `--version` 选项被框架 Console 占用（显示版本号），故命名为 `--version-dir`。
