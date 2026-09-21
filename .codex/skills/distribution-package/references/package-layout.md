# 包布局与验证规则

## 归集布局

普通源码保持仓库相对路径。为了让部署包的入口集中在 `public/`，以下路径会被重定位：

| 源路径 | 包内路径 |
| --- | --- |
| `template/` | `public/source/template/` |
| `.codex/skills/` | `.codex/skills/`（保持原路径） |
| `specs/`（存在时） | `public/source/spec/specs/` |
| `rules/`（存在时） | `public/source/spec/rules/` |
| `AGENTS.md`（存在时） | `public/source/spec/AGENTS.md` |
| `--install-sql` 指定文件 | `database/install.sql` |
| 自动生成 | `database/clean.sql` |

`public/` 原有内容不重写，前台 `build.sh` 生成的 admin、merchant、H5 文件因此继续作为部署入口。`template/*/dist` 不从源码目录重复复制；编译后的文件应先由 `build.sh` 同步到 `public/`。

## SQL 清理

脚本从安装 SQL 中识别 `CREATE TABLE`、`INSERT INTO`、`REPLACE INTO`、`ALTER TABLE`、`DELETE FROM` 和 `UPDATE` 后面的表名，去除反引号和可选 schema 前缀并排序去重。`clean.sql` 的结构固定为：

```sql
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE `table_a`;
TRUNCATE TABLE `table_b`;
SET FOREIGN_KEY_CHECKS=1;
```

这是供运维人员审核后执行的清理脚本，不会在打包期间连接数据库。若安装 SQL 没有默认数据写入语句，打包直接失败，避免生成只有表结构或空数据的“完整包”。

## 安全扫描

- 路径过滤优先于内容扫描；被排除的文件不会被读取。
- `template/uniapp/src/styles/iconfont/demo.css`、`demo_index.html` 属于开发演示资料，按排除清单处理；`vendor/` 是例外，第三方依赖的 README、demo、tests 等按原目录保留。
- 内容扫描只对可识别的 UTF-8 文本执行，检测实际私钥主体、AWS Access Key、常见 `sk-...` 密钥格式以及带图片扩展名的远程 URL；`vendor/` 只豁免远程图片检查，不豁免密钥检查。
- 二进制图片可以进入 `public/`，但引用必须是包内相对路径或站内路径；脚本不会联网下载远程图片。
- 安装 SQL 中的 `/storage/...` 图片引用必须在包内的 `public/storage/...` 找到对应文件，构建和独立复核都检查；缺失时中止，不能把缺图包标成完整包。
- 生成 ZIP 后再次读取并校验，防止暂存目录与最终归档内容不一致。
