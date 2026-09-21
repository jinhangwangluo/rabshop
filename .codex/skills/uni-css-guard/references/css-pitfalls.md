# UniApp H5 / 微信小程序 CSS 避坑表

## 1. 根选择器与页面背景

### 风险写法

```scss
body,
html,
* {
  margin: 0;
}
```

### 问题

UniApp 中页面根节点更接近 `page`，微信小程序没有标准浏览器中的 `body/html` 语义。通配选择器也可能引发性能和跨端作用域差异。

### 推荐写法

```scss
page {
  background: #f7f8fa;
}
```

页面局部背景优先写到根容器：

```scss
.my-page {
  min-height: 100vh;
  background: #f7f8fa;
}
```

注意：`page` 选择器放在 `scoped` 中可能不生效或作用域不符合预期。

## 2. scoped 样式隔离差异

### 问题

H5 与非 H5 端的样式隔离策略存在差异，组件样式可能在某些端互相影响。

### 建议

- 页面/组件默认使用 `<style lang="scss" scoped>`。
- 需要设置 `page` 全局背景时，使用非 scoped 样式块或根容器背景。
- 修改子组件内部样式时谨慎使用深度选择器，优先通过组件 props 或外层 class 控制。

## 3. rpx、px、vh/vw 单位

### 风险写法

```scss
.card {
  width: 375px;
  min-height: 100vh;
}
```

### 问题

- `px` 在不同屏幕下不自适应。
- `100vh` 在 H5 上可能受浏览器地址栏、顶部导航、tabBar 影响；在小程序中也容易和自定义导航、安全区叠加。
- `vw/vh` 在部分端与预期视觉高度不一致。

### 推荐写法

- 设计稿布局尺寸优先换算为 `rpx`。
- 1px 边框可保留 `px`。
- 页面高度优先用根容器自然撑开、`flex: 1` 或明确容器高度。
- H5 tabBar 场景用 `var(--window-bottom)` 修正底部区域。

## 4. flex gap

### 风险写法

```scss
.list {
  display: flex;
  gap: 20rpx;
}
```

### 问题

`gap` 在部分微信基础库、老机型或复杂 flex 场景中表现不稳定；H5 与小程序渲染间距可能不一致。

### 推荐写法

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

简单单向列表可用相邻选择器：

```scss
.item + .item {
  margin-top: 20rpx;
}
```

## 5. CSS Grid

### 风险写法

```scss
.grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
}
```

### 问题

CSS Grid 在微信小程序与 H5 兼容细节、对齐和间距表现可能不一致，不适合作为核心布局依赖。

### 推荐写法

```scss
.grid {
  display: flex;
  flex-wrap: wrap;
  margin: -10rpx;
}

.grid__item {
  width: 50%;
  padding: 10rpx;
  box-sizing: border-box;
}
```

## 6. position: sticky

### 风险写法

```scss
.header {
  position: sticky;
  top: 0;
}
```

### 问题

`sticky` 在小程序、嵌套滚动容器和自定义导航场景中表现不稳定。

### 推荐写法

- 简单吸顶使用 `position: fixed` + 内容区补 `padding-top`。
- 列表内部吸顶优先考虑 `scroll-view` 与状态控制。
- 确需 H5 sticky 时用条件编译，仅 H5 启用。

## 7. fixed、tabBar 与安全区

### 风险写法

```scss
.footer {
  position: fixed;
  bottom: 0;
}
```

### 问题

H5 tabBar 是前端模拟元素，底部 fixed 可能被 tabBar 遮挡；微信小程序还要考虑 iPhone 安全区。

### 推荐写法

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

如果项目有自定义 tabBar 或页面底部栏，额外为内容区补 `padding-bottom`。

## 8. calc 与 env

### 风险写法

```scss
.panel {
  height: calc(100vh - 92rpx);
  padding-bottom: calc(220rpx + env(safe-area-inset-bottom));
}
```

### 问题

`calc` 与 `env` 在不同端的计算时机、单位混合、兼容兜底可能不同，容易导致高度溢出或底部被遮挡。

### 推荐写法

- 能用 flex 布局避免高度计算时，不写复杂 `calc`。
- 必须使用时保持表达式简单，并分别在 H5 / MP-WEIXIN 验证。
- H5 tabBar 场景结合 `var(--window-bottom)`。

## 9. backdrop-filter / filter

### 风险写法

```scss
.glass {
  backdrop-filter: blur(20rpx);
}
```

### 问题

毛玻璃和滤镜在微信小程序、低端机、WebView 上兼容与性能都不稳定，可能直接不生效。

### 推荐写法

```scss
.glass {
  background: rgba(255, 255, 255, 0.88);
}

/* #ifdef H5 */
.glass {
  backdrop-filter: blur(20rpx);
}
/* #endif */
```

把滤镜作为增强，不作为识别内容的唯一视觉手段。

## 10. overflow 滚动

### 风险写法

```scss
.list {
  height: 600rpx;
  overflow-y: auto;
}
```

### 问题

普通 `view` 的滚动在微信小程序中不等价于 H5，嵌套滚动、回弹、滚动条和触底事件可能不一致。

### 推荐写法

```vue
<scroll-view scroll-y class="list-scroll">
  <view class="list-scroll__inner">...</view>
</scroll-view>
```

```scss
.list-scroll {
  height: 600rpx;
}
```

## 11. 文本单行与多行省略

### 风险写法

```scss
.title {
  line-clamp: 2;
}
```

### 问题

标准 `line-clamp` 兼容性不稳定；仅写 `line-clamp` 可能在小程序或 H5 任一端不生效。

### 推荐写法

单行：

```scss
.title {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
```

多行：

```scss
.desc {
  display: -webkit-box;
  overflow: hidden;
  text-overflow: ellipsis;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
```

## 12. 深度选择器

### 风险写法

```scss
/deep/ .child {}
>>> .child {}
```

### 问题

不同构建版本和端对深度选择器的处理不同，且容易污染子组件内部结构。

### 推荐写法

```scss
::v-deep .child {}
```

优先通过组件 props、外层 class、CSS 变量控制样式，深度选择器只用于第三方组件不得不覆盖的场景。

## 13. 动态 style 与 WXML 表达式

### 风险写法

```vue
<view :style="(config || {}).style || null" />
<child :config="component.config || {}" :data="component.data || []" />
```

### 问题

微信小程序编译后的 WXML 对复杂表达式、对象字面量兜底更敏感，可能出现 `unexpected token` 一类错误。

### 推荐写法

```js
computed: {
  safeConfig() {
    return this.config && typeof this.config === 'object' ? this.config : {}
  },
  safeStyle() {
    return this.safeConfig.style || null
  }
}
```

```vue
<view :style="safeStyle" />
<child :config="safeConfig" />
```

## 14. 伪元素与复杂选择器

### 风险写法

```scss
.card:hover {}
.card:nth-child(2n) {}
.card::before {}
```

### 问题

H5 的鼠标伪类、小程序触摸环境、复杂选择器支持和渲染行为可能不同。

### 推荐写法

- 点击态使用 class 状态切换。
- 装饰元素优先写真实 `view`，少依赖 `::before/::after`。
- 列表奇偶样式优先在数据中加状态类或用简单选择器。

## 15. z-index 与层级

### 风险写法

```scss
.popup {
  position: fixed;
  z-index: 999999;
}
```

### 问题

小程序原生组件、视频、地图、输入框等可能有特殊层级；H5 与小程序 fixed 层级也可能不一致。

### 推荐写法

- 弹层统一放在页面根部末尾。
- 避免无意义超大 `z-index`，建立项目层级表。
- 遇到原生组件遮挡，使用平台能力或组件提供的覆盖方案。

## 16. 条件编译使用边界

### 适用

- H5 tabBar 底部避让。
- 小程序安全区与胶囊导航差异。
- H5 增强效果，如 `backdrop-filter`。
- 端特有滚动、固定定位修正。

### 不适用

- 普通颜色、间距、字号。
- 可以通过安全通用写法统一解决的布局。
- 为了省事复制两套页面样式。
