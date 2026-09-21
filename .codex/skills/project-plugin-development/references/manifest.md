# 插件清单与开发映射

## 最小清单

`plugin.json` 是可移植的发布清单，所有路径相对于源码包根目录：

```json
{
  "schema_version": "1.0",
  "code": "seckill",
  "name": "秒杀",
  "version": "0.1.0",
  "namespace": "Plugin\\Seckill",
  "core": {"constraint": ">=1.0.0 <2.0.0"},
  "surfaces": ["backend", "admin", "merchant", "mobile"],
  "initial_state": "resources/initial-state.json",
  "entrypoints": {
    "provider": "Plugin\\Seckill\\SeckillPlugin",
    "admin_routes": "backend/routes/admin.php",
    "merchant_routes": "backend/routes/merchant.php",
    "mobile_routes": "backend/routes/mobile.php",
    "admin_frontend": "frontend/admin/index.js",
    "merchant_frontend": "frontend/merchant/index.js",
    "mobile_frontend": "frontend/mobile/index.js"
  },
  "dependencies": [],
  "conflicts": [],
  "events": {
    "consumes": ["product.updated", "order.created", "order.paid", "order.cancelled", "refund.approved"],
    "publishes": ["plugin.seckill.activity.started"],
    "frontend_consumes": {
      "admin": ["admin.product.form.submit.before", "admin.product.form.submit.after"],
      "merchant": ["merchant.store.form.submit.before", "merchant.store.form.submit.after"],
      "mobile": ["mobile.order.submit.before", "mobile.order.submit.after"]
    }
  },
  "overrides": []
}
```

清单中的 `surfaces` 只能使用 `backend`、`admin`、`merchant`、`mobile`，不得重复。声明某端就必须有相应入口和开发映射；未声明的端不能在映射或事件中出现。插件编码必须与目录名一致，且只允许 `^[a-z][a-z0-9_]*$`。

## 开发映射

```json
{
  "schema_version": "1.0",
  "plugin_code": "seckill",
  "source_roots": {
    "backend": "plugins/seckill/backend",
    "resources": "plugins/seckill/resources",
    "tests": "plugins/seckill/tests",
    "admin": "template/admin/src/plugins/seckill",
    "merchant": "template/merchant/src/plugins/seckill",
    "mobile": "template/uniapp/src/plugins/seckill"
  }
}
```

映射只允许仓库相对路径，且解析后的真实路径必须位于仓库根目录内。`resources`、`backend`、`tests` 是插件基础目录；前端根目录按声明端一一对应。映射文件是开发机描述，不得复制到发布包。
