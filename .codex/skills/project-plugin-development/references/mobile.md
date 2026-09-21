# UniApp 移动端

移动端源码位于 `template/uniapp/src/plugins/{code}`，入口导出页面和插槽注册信息。页面必须在构建期由插件注册器合并到 `pages.json` 输入，插件 Skill 不直接修改宿主 `pages.json`。

页面同时兼容 H5 与微信小程序：使用 `@dcloudio/uni-app` 生命周期和项目 API 封装，平台差异用条件编译或统一工具方法处理。避免直接依赖 `window`、`document`、`navigator`、`wx`，避免复杂动态 `:style`、模板字符串 class，以及同元素 `v-if` 与 `v-for`。

移动端提交事件遵循与平台端相同的命名空间和幂等约束。页面、组件、API 文件不得把密钥、运行状态或构建产物写回插件源码目录。
