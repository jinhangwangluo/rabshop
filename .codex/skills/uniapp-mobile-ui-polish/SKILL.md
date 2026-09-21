---
name: uniapp-mobile-ui-polish
description: "项目专用 UniApp 移动端 UI 审查与优化流程，适用于 template/uniapp 页面和组件。用于提升移动端界面美观度、提取或应用 UI 规范、优化当前页面、检查 H5 与微信小程序 CSS 兼容性、统一颜色、字号、间距、圆角、阴影、空态、加载态、错误态、底部固定栏、弹窗、卡片、列表和交互状态；也用于在页面优化后反哺并更新本项目移动端 UI 规范技能。"
---

# UniApp 移动端 UI 优化

## 目标

使用本技能对 `template/uniapp` 移动端页面进行一次性 UI 审查与优化，同时保持项目既有架构，并保证 H5 与微信小程序兼容。

详细样式体系位于 `references/mobile-ui-style-guide.md`。修改任何页面或组件前必须先读取该文件。

## 必须执行的流程

1. 读取目标页面或组件，以及相邻的可复用组件。
2. 读取 `references/mobile-ui-style-guide.md`。
3. 如果改动涉及 CSS/SCSS，同时使用项目内 `uni-css-guard` 技能，并执行高风险 CSS 扫描。
4. 先识别可见 UI 问题：层级、间距、卡片形态、文字溢出、空态、加载态、错误态、图片比例、底部安全区、点击反馈、固定定位元素和跨端 CSS 风险。
5. 聚焦当前页面做优化，不做无关重构。
6. 新增组件前优先复用项目已有基础能力：
   - `app-safe-top`
   - `app-bottom-sheet`
   - `six-petal-spiral`
   - `guess-you-like`
   - `uni-icons` / 现有 iconfont
   - `template/uniapp/api/*`
   - `utils/platform.js`
   - `utils/runtime_config.js`
   - `utils/diy_adapter.js`
7. 对改动文件运行 `git diff --check`。
8. 重新读取改动文件，并检查规范中列出的微信小程序风险点。
9. 如果本次优化沉淀出可复用规则，在 `references/mobile-ui-style-guide.md` 的对应章节更新规则。新规则必须具体，并且贴合本项目。

## 输出要求

完成后说明：

- 修改了哪些文件。
- 修复了哪些主要 UI 问题。
- H5 / 微信小程序兼容性处理方式。
- 校验命令结果。
- 是否新增了 UI 规范规则。

## 约束

- 不要为了页面好看而引入假数据。必须使用真实接口字段，或明确展示空态、加载态、错误态。
- 未经用户明确要求，不新增 UI 组件库。
- 浏览器专属 API 必须放在平台条件保护内。
- 不要为了视觉效果把复杂业务逻辑塞进页面。
- 不要扩大范围做无关重构。
- 除非有明确兼容性理由和兜底方案，不要使用 `gap`、CSS Grid、`sticky`、`backdrop-filter`、复杂内联 `:style` 或模板字符串类名。
