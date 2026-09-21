# 源码打包

`package_plugin.php` 首期只接受 `--mode=source`。流程为：读取并校验清单和开发映射；在临时暂存目录归一为 `backend/`、`resources/`、`frontend/{admin,merchant,mobile}/`、`tests/`；检查路径穿越、软链接、大小写冲突、文件数量和大小；生成固定排序的 `checksums.json`；统一 ZIP 时间戳与权限；写入临时 ZIP 后重新读取校验，最后原子改名到 `--output`。

以下内容永不进入源码包：`plugin.dev.json`、`.env*`、`.git`、`runtime`、`node_modules`、`vendor`、`dist`、`unpackage/dist`、缓存、日志、私钥、运行状态、临时文件和本机构建产物。排除项会在 dry-run/摘要中列出。

源码包不含正式签名。远程构建中心负责编译、SBOM、签名和制品发布；本地脚本不得上传或执行远程任务。`--dry-run` 只输出归集、排除和目标文件清单，不创建 ZIP 或修改开发源码。
