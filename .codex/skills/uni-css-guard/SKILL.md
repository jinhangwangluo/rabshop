---
name: uni-css-guard
description: 当开发或审查 `template/uniapp` 中需要同时兼容 H5 与微信小程序的页面、组件、SCSS/CSS 样式时，应使用此技能；重点识别跨端表现不一致、微信小程序不支持或 H5 与小程序渲染差异明显的 CSS 写法，并给出安全替代方案。
---

# uni-css-guard

## 概述

用于在 UniApp 页面、组件和样式开发中提前规避 H5 与微信小程序的 CSS 兼容差异。

执行时先检查目标代码是否存在高风险样式，再按“安全写法优先、条件编译兜底、真实双端验证”的原则修改或给出建议。

## 触发场景

在以下任务中启用此技能：

- 编写或还原 UniApp 页面样式。
- 调整 `template/uniapp` 下 `.vue`、`.scss`、`.css` 文件。
- 用户提到 H5、微信小程序、跨端兼容、样式错位、CSS 不生效、微信开发者工具报样式错误。
- 审查页面是否存在小程序与 H5 显示不一致的样式风险。

## 固定工作流

### 第 1 步：先检索高风险写法

在修改前检索目标文件或目录中的以下模式：

- `gap:`
- `position: sticky`
- `100vh` / `100vw`
- `calc(` / `env(safe-area-inset-*)`
- `backdrop-filter` / `filter`
- `display: grid` / `grid-template-*`
- `overflow: auto` / `overflow: scroll`
- `line-clamp` / `-webkit-line-clamp`
- `::v-deep` / `/deep/` / `>>>`
- `body` / `html` / `*` 根选择器
- `:style` 中复杂对象、三元嵌套、对象字面量兜底
- `position: fixed` + `bottom` / `top` / `z-index`

### 第 2 步：按风险类型判断是否改造

参考 `references/css-pitfalls.md` 逐项判断：

1. 是否在微信小程序上不支持或表现不稳定。
2. 是否在 H5 上受浏览器地址栏、tabBar、安全区影响。
3. 是否在 scoped 样式、页面样式隔离、组件样式穿透上存在差异。
4. 是否可用更稳定的布局方式替代。
5. 是否需要通过 `#ifdef H5` / `#ifdef MP-WEIXIN` 分端处理。

### 第 3 步：优先使用安全写法

修改样式时遵守以下优先级：

1. 优先使用 `rpx` 做布局尺寸，`px` 保留给 1px 边框、少量字体或平台 API 要求。
2. 优先使用 `flex + margin` 替代依赖兼容性的 `gap`。
3. 优先使用 `flex-wrap`、百分比和固定宽度组合，避免核心布局依赖 CSS Grid。
4. 优先使用 `scroll-view` 处理小程序滚动区域，避免普通 `view` 依赖 `overflow: auto`。
5. 优先使用明确容器高度与 `flex: 1`，谨慎使用 `100vh` 计算页面高度。
6. 优先使用普通半透明背景替代 `backdrop-filter`，把模糊效果作为增强。
7. 优先使用 `page` 选择器设置页面背景，不使用 `body` / `html`。
8. 优先把复杂动态样式放到 `computed` 中生成简单字符串或对象，模板中只绑定字段。

### 第 4 步：必要时使用条件编译

当同一写法无法同时兼容 H5 与微信小程序时，使用 UniApp 条件编译隔离：

```scss
/* #ifdef H5 */
.safe-bottom {
  padding-bottom: calc(var(--window-bottom) + 24rpx);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.safe-bottom {
  padding-bottom: calc(env(safe-area-inset-bottom) + 24rpx);
}
/* #endif */
```

条件编译只用于确有跨端差异的地方，不要为了普通样式拆成两套。

### 第 5 步：检查模板动态样式

微信小程序编译产物对复杂 WXML 表达式更敏感。检查 `:style`、`:class`、组件 props 和配置对象时，必须把下面这类写法视为**高优先级风险**：

- 避免模板中出现 `foo || {}`、`foo || []`、`(foo || {}).bar`。
- **禁止** 在模板的 `:style` 中直接调用函数，例如 `:style="buildMarkerStyle(point)"`、`:style="getRowStyle(item)"`。
- **禁止** 在模板的 `:class` 中直接使用模板字符串，例如 `:class="`card--${index}`"`。
- **禁止** 在模板属性里直接写复杂三元、对象字面量嵌套、链式兜底表达式。
- 把复杂逻辑前置到 `computed`、`data` 或列表预处理阶段，模板只写 `:style="point.markerStyle"`、`:class="item.className"`、`:class="{ 'is-active': isActive }"`。
- 传给子组件的对象和数组先在 JS 中规范化，避免生成非法 WXML 表达式。

#### 微信小程序模板硬限制（需要特别强调）

在 `template/uniapp` 开发中，凡是要兼容微信小程序，默认遵守以下规则：

- `:style` **不要直接调用函数**；先在 `computed` / 数据加工阶段生成简单字段再绑定。
- `:class` **不要使用模板字符串拼类名**；改用对象式、数组式或提前生成好的 `className`。
- 如果同一列表项需要多个动态样式字段，优先把列表先 `map` 成带 `style` / `className` 的新数组，再在模板里渲染。
- 审查时遇到这类写法，不能只说“可能有风险”，应直接判定为**需要改造**。

### 第 6 步：完成后必须自查

修改完成后执行：

1. 读取已改文件，确认没有引入高风险写法。
2. 运行 lint 或诊断检查。
3. 人工核对 H5 与微信小程序重点差异：安全区、底部栏、滚动区、固定定位、文本省略、弹层层级。
4. 若发现跨端无法统一，补充条件编译并说明原因。

## 常见安全替代写法

### 用 margin 替代 gap

```scss
.list {
  display: flex;
  flex-wrap: wrap;
  margin: -10rpx;
}

.list__item {
  margin: 10rpx;
}
```

### 用 scroll-view 替代普通 view 滚动

```vue
<scroll-view scroll-y class="panel-scroll">
  <view class="panel-scroll__inner">...</view>
</scroll-view>
```

```scss
.panel-scroll {
  height: 600rpx;
}
```

### 页面背景写在非 scoped 的 page 中

```scss
page {
  background: #f7f8fa;
}
```

若当前页面 `<style scoped>` 中需要设置页面背景，改为给根容器设置背景和最小高度。

### fixed 底部兼容安全区

```scss
.footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding-bottom: env(safe-area-inset-bottom);
}

/* #ifdef H5 */
.footer {
  bottom: var(--window-bottom);
}
/* #endif */
```

## 输出要求

使用此技能完成任务时，输出应包含：

- 检查到的高风险 CSS 写法。
- 已采用的替代方案或条件编译说明。
- 已检查 H5 与微信小程序的关键点。
- lint / 语法检查结果。

## 参考资料

详细避坑表读取 `references/css-pitfalls.md`。
