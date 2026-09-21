# 平台端与商户端

前端源码必须位于各自 Vite 工程的 `src/plugins/{code}`，这样工程可以直接编译。入口 `index.js` 导出 `register()`，由宿主生成式注册表调用；插件不得读取宿主页面私有的 `form`、`router` 或组件 `ref`。

`routes.js` 导出静态路由数组，路由名须带插件编码前缀以避免冲突，组件使用插件目录内的相对导入。API 文件只通过项目已有 request 封装请求加密网关，不在插件中散写 `fetch`/`axios`。

提交事件处理器只能写入 `plugin_payload.{pluginCode}` 命名空间。同步 `before` 处理器必须返回明确的 `allow`/`reject` 结构，`after` 处理器不得改变核心业务结果。插件禁用或卸载后不得进入运行时注册表。

Skill 不改 `template/admin/src/router/index.js`、`template/merchant/src/router/index.js` 或公共组件；宿主注册器的生成清单应由独立宿主任务维护。
