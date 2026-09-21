---
name: uniapp-theme-switch
description: 当需要让 template/uniapp 页面、组件或新页面适配后台一键换色时使用；按 pages.json 页面入口注入主题变量，替换移动端品牌色硬编码，并兼容 H5 与微信小程序。
---

# UniApp Theme Switch

## 使用时机

用户提到移动端一键换色、主题色适配、页面硬编码品牌色、后台主题色需要落到 UniApp 页面时，使用本技能。

## 必读规则

1. 先读取仓库规则：`.claude/rules/uniapp.mdc`。
2. 同时使用并读取：
   - `.codex/skills/uni-css-guard/SKILL.md`
   - `.codex/skills/uniapp-mobile-ui-polish/SKILL.md`
3. 后台主题契约只保留三种颜色：`primary_color`、`secondary_color`、`gradient_color`。移动端页面变量都从这三项派生，不新增后台颜色字段。

## 标准实现

1. 主题运行时放在 `template/uniapp/utils/theme.js`：
   - 维护全局响应式 `themeState`。
   - `syncThemeConfig()` 从接口拉取并缓存主题。
   - `buildThemeVariables()` 只基于主色、辅助色、渐变色派生页面变量。
   - `createThemeMixin()` 暴露 `themePageStyle`、`themePrimaryColor`、`themeSecondaryColor`、`themeGradientColor`。
2. 在 `template/uniapp/main.js` 注册 `app.mixin(createThemeMixin())`。
3. 只给 `pages.json` 中声明的真实页面入口添加：

```vue
<page-meta :page-style="themePageStyle"></page-meta>
```

不要给普通组件批量添加 `page-meta`。

## 替换规则

1. CSS/SCSS 中的品牌色改用变量：
   - 主色：`var(--brand-primary, #ea580c)`
   - 辅助色：`var(--brand-primary-light, #fb923c)`
   - 浅背景：`var(--brand-primary-pale, #fff7ed)`
   - 渐变：`var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%))`
   - 阴影：`var(--brand-shadow, rgba(234, 88, 12, 0.18))`
2. 模板属性、canvas、`uni.showModal.confirmColor` 等需要真实颜色值的位置，不传 CSS 变量字符串：
   - 模板 prop 使用 `:color="themePrimaryColor"`、`:icon-color="themePrimaryColor"`、`:indicator-active-color="themePrimaryColor"`。
   - `<script setup>` 中使用 `themeState.primary_color`。
3. 不要把成功、失败、警告、直播红、会员金、灰色文本等业务状态色强行替换成品牌色。
4. 机械替换后必须搜索嵌套变量，修正类似 `var(--brand-primary, var(--brand-primary, #ea580c))` 的结果。

## 常用扫描

```bash
rg -n "#(ea580c|fb923c|c2410c|fff7ed|fed7aa|ff5a1f|ff6b00|f97316|ff7a00|ff5000|e9590f|f35a00|f34100|ff4500|ef2f1f|ff744d|ff6a00|fff2eb|fff4ee|fff2e8|ff9100)|rgba\\((234, 88, 12|255, 90, 31|255, 106, 0|255, 122, 0|249, 115, 22|239, 47, 31)" "template/uniapp/pages" "template/uniapp/components" -g "*.vue" -g "*.scss"
rg -n "color=\"var\\(--brand-primary|indicator-active-color=\"var\\(--brand-primary|icon-color=\"var\\(--brand-primary|confirmColor: 'var\\(--brand-primary" "template/uniapp/pages" "template/uniapp/components" -g "*.vue"
rg -n "var\\(--brand-[^\\n]*var\\(--|var\\(--theme-[^\\n]*var\\(--" "template/uniapp/pages" "template/uniapp/components" -g "*.vue" -g "*.scss"
```

## 验证

1. 运行 UniApp/H5 构建或当前项目可用的定向检查。
2. 至少验证：
   - 默认橙色主题正常。
   - 换成非橙色主题后，页面选中态、按钮、渐变、加载动画、弹窗确认色同步变化。
   - `/pages/category/index` 等 tabBar 导航页面不出现返回按钮。
   - 微信小程序侧没有复杂 `:style`、`var()` prop、grid/gap/sticky 等高风险兼容问题。
