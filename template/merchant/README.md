# 商户端管理后台（template/merchant）

本项目衍生于 [vue-element-admin](https://panjiachen.github.io/vue-element-admin)，已迁移为 **Vue 3 + Element Plus + Pinia + Vue Router 4 + Vite**，对接知识付费/电商系统后端（thinkphp8）的商户端接口。

## 技术栈

- 框架：Vue 3（`<script setup>` 组合式 API）
- UI：Element Plus（主题主色 `#c9a84c`，即商户端金色体系，见 `src/styles/element-variables.scss`）
- 状态：Pinia（`src/store/modules/`）
- 路由：Vue Router 4（静态路由，`src/router/modules/*.js` 按业务模块拆分）
- 构建：Vite
- 样式：SCSS + `<style lang="scss" scoped>`，颜色使用 CSS 变量 `var(--brand-*)` / `var(--text-*)`（速查表见 `.claude/rules/scss.mdc`）

## 目录结构

```
src/
├── api/            # 接口封装（一个业务域一个文件，统一经 utils/request.js）
├── utils/          # request.js：axios 封装（baseURL/token/错误码拦截）
├── router/         # 路由（modules/*.js 自动加载，pattern: /merchant/<path>）
├── store/          # Pinia stores
├── components/     # 通用组件
├── composables/    # 组合式函数
├── views/          # 业务页面（按模块分目录）
├── styles/         # 全局样式与 CSS/SCSS 变量
└── layout/         # 布局
```

## 开发

```bash
npm install
npm run dev      # 或 npm run merdev
```

- dev 服务默认端口 **9527**
- 接口 baseURI：`http://127.0.0.1:25780/api/merchant`（`src/settings.js`，可通过环境变量 `VITE_APP_BASE_API` 覆盖），**需先启动后端**（rabbit.net → nginx/PHP，或 `php83 think run`）
- 登录：（见 `.claude/rules/api-test-account.mdc`）

## 构建与检查

```bash
npm run build      # 生产构建（vite build，或 merbuild）
npm run preview    # 预览构建产物
npm run lint       # eslint 自动修复（或 merlint）
```

## 协作约定

1. **接口调用**：一律 `src/api/*.js` + `src/utils/request.js`；响应结构 `{ status, message, data }`，列表为 `{ list, total, limit, page, totalPage }`。
2. **新增页面**：`src/views/<模块>/<页面>/index.vue` + `src/router/modules/<模块>.js` 路由条目（路由 path 与后端菜单 path 一致）。
3. **菜单**：由后端 `merchant_menu` 接口管理（商户端：菜单管理），前端静态路由负责组件映射。
4. **组件写法**：`defineOptions({ name })` 声明组件名；事件 `handleXxx`、数据 `getXxx/fetchXxx`；表单重置用 `Object.assign`；分页用原生 `el-pagination`。
5. **样式**：优先 `<style lang="scss" scoped>`，覆盖 element-plus 用 `:deep()`；禁止硬编码色值，使用 `var(--brand-*)` / `var(--text-*)` 变量。
