# 平台端管理后台（template/admin）

本项目衍生于 [vue-element-admin](https://panjiachen.github.io/vue-element-admin)，已迁移为 **Vue 3 + Element Plus + Pinia + Vue Router 4 + Vite**，对接知识付费/电商系统后端（thinkphp8）。

## 技术栈

- 框架：Vue 3（`<script setup>` 组合式 API）
- UI：Element Plus 2.14.4（主题主色 `#f34100`，见 `src/styles/element-variables.scss`）
- 状态：Pinia（`src/store/modules/`）
- 路由：Vue Router 4（静态路由，`src/router/modules/*.js` 按业务模块拆分）
- 构建：Vite
- 样式：SCSS + `<style lang="scss" scoped>`，颜色使用项目 CSS 变量（`--ds-*` / `--brand-*`，见 `src/styles/`）

## 目录结构

```
src/
├── api/            # 接口封装（一个业务域一个文件，统一经 utils/request.js）
├── utils/          # request.js：axios 封装（baseURL/token/错误码拦截）
├── router/         # 路由（modules/*.js 自动加载，pattern: /admin/<path>）
├── store/          # Pinia stores（user/app/settings/tagsView 等）
├── components/     # 通用组件（Upload、pagesHeader、TagPreview 等）
├── views/          # 业务页面（按模块分目录，如 article/、product/）
├── styles/         # 全局样式与 CSS 变量
└── layout/         # 布局（侧边栏/顶栏/TagsView）
```

## 开发

```bash
npm install
npm run dev
```

- dev 服务默认端口 **9527**（vite.config.js）
- 接口 baseURI：`http://127.0.0.1:25780/api/admin`（`src/settings.js` 硬编码，可通过环境变量 `VITE_APP_BASE_API` 覆盖，`${location.origin}/api/admin` 兜底），**需先启动后端**（rabbit.net → nginx/PHP，或 `php83 think run`）
- 登录：admin / 123456（见 `.claude/rules/api-test-account.mdc`）

## 构建与检查

```bash
npm run build      # 生产构建（vite build）
npm run preview    # 预览构建产物
npm run lint       # eslint 自动修复
```

## 协作约定

1. **接口调用**：一律 `src/api/*.js` + `src/utils/request.js`，页面不直接拼请求；响应结构 `{ status, message, data }`，列表为 `{ list, total, limit, page, totalPage }`。
2. **新增页面**：`src/views/<模块>/<页面>/index.vue` + `src/router/modules/<模块>.js` 路由条目（路由 path 与后端菜单一致）。
3. **菜单**：由后端 `setting/menu` 接口管理（平台端：设置 → 菜单管理），前端静态路由负责组件映射；新增菜单后刷新页面生效。
4. **组件写法**：`defineOptions({ name })` 声明组件名；事件 `handleXxx`、数据 `getXxx/fetchXxx`;表单重置用 `Object.assign`；分页用原生 `el-pagination`。
5. **样式**：优先 `<style lang="scss" scoped>`，覆盖 element-plus 用 `:deep()`；禁止硬编码色值，用 `var(--ds-*)`/`$brand-*` 变量。
