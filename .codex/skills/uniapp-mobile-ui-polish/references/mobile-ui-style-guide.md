# UniApp 移动端 UI 开发规范

本规范用于 `template/uniapp` 的移动端页面与组件优化，默认兼容 H5 与微信小程序。每次页面 UI 优化后，如发现可复用经验，应追加到本文件，形成持续增长的项目技能。

## 1. 设计目标

- 移动端优先，信息密度适中，避免 Web 化的大面积空白和过度装饰。
- 视觉层级清晰：页面背景、导航、主内容、卡片、操作区、状态反馈必须一眼可分。
- 真实数据驱动：不使用假数据撑 UI，接口为空时展示空态，接口失败时展示错误态。
- 跨端稳定：H5 与微信小程序均可渲染，不依赖微信小程序高风险 CSS。

## 2. 颜色系统

优先使用 `template/uniapp/uni.scss` 和 `template/uniapp/App.vue` 中的语义变量。

### 品牌色

- 主色：`var(--brand-primary, #ea580c)` / `$brand-primary`
- 深色主色：`var(--brand-primary-dark, #c2410c)`
- 浅色主色：`var(--brand-primary-light, #fb923c)`
- 极浅品牌背景：`var(--brand-primary-pale, #fff7ed)`
- CTA 阴影：`var(--brand-shadow, rgba(234, 88, 12, 0.18))`

### 文本色

- 一级标题：`var(--text-title, #18181b)`
- 主要正文：`var(--text-primary, #18181b)`
- 普通正文：`var(--text-regular, #3f3f46)`
- 次要信息：`var(--text-secondary, #71717a)`
- 占位/弱提示：`var(--text-placeholder, #a1a1aa)`
- 反白文字：`var(--text-inverse, #ffffff)`

### 背景与边框

- 页面背景：`var(--surface-page, #f9f9f9)`
- 卡片背景：`var(--surface-card, #ffffff)`
- 弱卡片背景：`var(--surface-card-muted, #f3f4f6)`
- 柔和面背景：`var(--surface-card-soft, #f4f4f5)`
- 浅边框：`var(--border-light, #f4f4f5)`
- 默认边框：`var(--border-default, #e4e4e7)`

### 状态色

- 成功：`var(--text-success, #16a34a)`
- 警告：`var(--text-warning, #f59e0b)`
- 失败/危险：`var(--text-danger, #dc2626)`
- 信息：`var(--text-info, #2563eb)`

### 颜色使用规则

- 页面内品牌色不要直接写 `#ea580c`，使用 CSS 变量或 SCSS 变量。
- 灰色不要随意新增，优先从文本/背景/边框变量选择。
- 同一页面出现同一硬编码颜色超过 2 次，应抽成局部语义变量或改用全局变量。
- 深色背景上使用 `--text-inverse` 系列，避免灰字对比度不足。

## 3. 字号、行高、字重

统一使用 `rpx`。不要使用随视口变化的字体大小。

| 场景 | 字号 | 行高 | 字重 |
| --- | --- | --- | --- |
| 极小标签、角标、计数 | 18-20rpx | 28-30rpx | 400/500 |
| 辅助说明、时间、表单提示 | 22rpx | 32rpx | 400 |
| 正文、描述 | 24rpx | 34-36rpx | 400 |
| 列表/卡片标题 | 28rpx | 40rpx | 600/700 |
| 导航标题、区块标题 | 32rpx | 44rpx | 600/700 |
| 页面主标题 | 40rpx | 52rpx | 700 |
| 数据/金额展示 | 44-48rpx | 56-60rpx | 800/900 |

### 文本省略

- 单行标题：固定 `line-height`，使用 `white-space: nowrap; overflow: hidden; text-overflow: ellipsis;`。
- 两行标题/简介：固定 `height = line-height * 2`，再使用 `-webkit-line-clamp: 2`。固定高度是小程序端兜底。
- 卡片内标题默认最多 2 行；列表摘要默认最多 2 行；按钮文字必须单行完整显示。

## 4. 间距系统

页面宽度按 750rpx 设计。

### 页面级

- 页面左右安全边距：24rpx 或 32rpx。
- 页面顶部内容间距：24-32rpx，若有自定义导航由 `app-safe-top` 处理。
- 页面底部有固定操作栏时，内容区至少补 160rpx 底部空间，并考虑 H5 `var(--window-bottom)`。

### 区块级

- 区块上下间距：24-32rpx。
- 区块标题与内容：16-20rpx。
- 卡片列表项间距：20-24rpx。
- 紧密信息行间距：12-16rpx。

### 卡片内边距

- 紧凑卡片：24rpx。
- 标准卡片：28rpx。
- 大面板/弹窗：32rpx。
- 空态/错误态区域：48rpx 32rpx。

### 规则

- 不使用 `gap` 作为核心布局间距；用 `margin`、`padding`、相邻选择器替代。
- 横向卡片不要贴边，保持 24rpx 以上页面边距。
- 信息列表内边距不低于 24rpx，避免触控区域过小。

## 5. 圆角、阴影、边框

### 圆角

| 场景 | 圆角 |
| --- | --- |
| 小标签、小图标底 | 8rpx |
| 小按钮、输入框 | 12rpx |
| 标准列表项/普通卡片 | 16rpx |
| 大卡片、信息面板 | 20-24rpx |
| 弹窗、底部面板 | 28-32rpx |
| 胶囊按钮、标签组 | 999rpx |
| 头像/圆形按钮 | 50% |

### 阴影

- 普通卡片：`0 4rpx 12rpx rgba(15, 23, 42, 0.03)`
- 重点卡片：`0 8rpx 24rpx rgba(15, 23, 42, 0.04)`
- 弹窗/浮层：`0 14rpx 36rpx rgba(15, 23, 42, 0.06)`
- CTA：`0 12rpx 24rpx var(--brand-shadow, rgba(234, 88, 12, 0.18))`
- 底部栏：`0 -8rpx 24rpx rgba(0, 0, 0, 0.04)`

### 边框

- 细分割线使用 `1px solid var(--border-light, #f4f4f5)`。
- 卡片边框只在需要弱分割或白底卡片叠白底时使用。
- 不要同时使用重阴影和明显边框。

## 6. 常见组件规范

### 页面容器

- 根容器使用 `min-height: 100vh; background: var(--surface-page, #f9f9f9);`，但涉及自定义导航和底部栏时优先用 flex 布局避免复杂 `calc`。
- 页面结构推荐：`app-safe-top`、`scroll-view`、内容容器、固定底部操作栏。

### 导航栏

- 优先使用 `app-safe-top`。
- 标题 32rpx/44rpx/600。
- 返回按钮触控区不小于 64rpx。
- 右侧图标操作必须有稳定宽高，避免标题偏移。
- 普通商品和插件商品详情共用 `product-detail-navigation`：保留返回/快捷菜单组合及右侧分享，快捷菜单沿用首页、搜索、购物车、收藏、个人中心的路由类型；微信小程序用胶囊尺寸工具避让状态栏和右侧胶囊，不再在页面叠加一份顶部占位。
- 微信小程序首页这类需要把搜索框放进自定义导航行的场景，不能用固定 `rpx` 猜顶部高度；应优先使用 `utils/mini_program_nav.js` 的 `getMiniProgramSearchNavMetrics()`，按 `menuButton.top - statusBarHeight` 得到上下留白，右侧内边距按 `windowWidth - menuButton.left + 8px` 避让系统胶囊。
- 小程序自定义导航里的搜索框不要直接使用胶囊按钮高度作为最终视觉高度；胶囊高度通常只有 32-37px，搜索框建议使用 `max(menuButton.height, 42px)`，但顶部留白仍保留 `menuButton.top - statusBarHeight` 的原始值，不能因为搜索框加高就反向扣减顶部留白，否则部分机型会把搜索框挤进状态栏区域。
- 页面已经先渲染 `app-safe-top` 占住状态栏后，紧接着的搜索行不要再叠加 `topGap`，小程序端该搜索行通常只需要 `padding-right: rightInset` 避让胶囊，并把 `padding-top` 归零。
- 小程序首页把首个 DIY 搜索框外提到导航行后，导航行必须继续占据页面文档流，并额外保留约 `12px` 内容间距；若下方首个组件是轮播，导航行还需要有明确背景色，避免轮播图片贴到搜索框下方造成“搜索漂浮在轮播上”的视觉问题。

### 卡片

- 卡片背景使用 `--surface-card`。
- 标准卡片圆角 16-24rpx。
- 卡片内部标题 28rpx，描述 22-24rpx。
- 图片必须固定宽高或 `aspect-ratio` 等价结构，避免加载后布局跳动。
- 不要把页面大区块都做成浮卡；只有重复项、面板、弹窗、工具区才使用卡片。

### 首页 DIY 组件

- 优化 DIY 组件时，后台配置字段控制的 `style_config`、`bg_color`、`padding_lr`、`padding_x`、`margin_top`、`margin_bottom`、`border_radius`、`image_radius`、文字颜色和图片圆角必须优先生效，默认样式只能作为无配置时的兜底；其中 `padding_lr` 表示组件左右留白，应落在包装层 `padding-left/right`，不能用 `margin-left/right` 模拟，否则小程序端内部 `swiper`、宫格等 `width: 100%` 元素容易把总宽度撑出屏幕；`padding_x` 表示组件内容左右内边距。
- DIY 组件内部可优化默认排版、文本省略、点击反馈、图片固定高度、空态和小程序兼容写法，但不能改动接口字段、组件数据结构、跳转逻辑或后台配置语义。
- DIY 组件的根容器、渲染器和通用包装层不要写死 `width: 100%`，应使用块级元素默认的 `width: auto`，否则配置 `padding_lr` 左右外边距时会把总宽度撑出父容器导致视觉不居中；内部图片、封面、轮播项、热区图片和滚动内容容器需要铺满自身父级时可以继续使用 `width: 100%`。
- DIY 栅格类布局优先使用 `display: flex; flex-wrap: wrap;` 和子项百分比宽度，不使用 `display: grid` 作为核心布局；横向滚动卡片间距优先用子项 `margin-right`。
- DIY 组件的默认品牌背景不要使用写死主色、标题和装饰的位图承载；默认视觉应使用 `--theme-gradient`、`--brand-primary` 等主题变量绘制，只有后台明确上传的自定义背景图才按配置展示。
- DIY 卡片标题默认最多 2 行，固定 `height = line-height * 2` 后再使用 `-webkit-line-clamp: 2`；辅助信息和标签默认单行省略，避免小屏下挤压价格、按钮或浏览量。
- 首页内容组件的购物车入口仅在标准价格大于 `0` 时显示；免费内容保留卡片详情点击，不显示加购按钮。
- 首页内容类型图标使用 `pages/diy/static` 的图片资源：图文与文字使用 `imgtxt.png`，小说使用 `novel.png`，图片使用 `img.png`，音频使用 `aud.png`，视频使用 `vid.png`；列表图标为 `30rpx`，网格辅助信息图标为 `26rpx`。
- DIY 搜索组件与轮播组件上下紧邻时，搜索框默认使用 `rgba(255, 255, 255, 0.72)` 半透明玻璃底和轻阴影；H5 可用 `backdrop-filter: blur(18rpx)` 做高斯模糊增强，微信小程序端以半透明底色兜底，不强依赖滤镜。
- 涉及魔方、热区、自由布局这类后台可视化配置能力时，不做破坏性替换；如需替换 `grid`、`calc` 或动态样式，必须先确认不会改变后台布局结果。

### 按钮

- 主按钮高度：80-88rpx，圆角 999rpx。
- 次按钮高度：64-72rpx。
- 图标按钮触控区：64rpx 以上。
- 主按钮颜色使用 `--brand-primary`，禁用态用 `--text-disabled` 或浅背景。
- hover/pressed：使用 `opacity: 0.86-0.9` 或轻微背景变化。
- 不要用长文字塞进小按钮；按钮文字超出时优先调整布局而不是缩小到不可读。

### 表单与搜索

- 输入框高度：72-88rpx。
- 输入框圆角：16rpx 或 999rpx。
- placeholder 使用 `--text-placeholder`。
- 搜索栏图标与文字间距 12rpx。

### 标签与徽章

- 标签字号 20-22rpx。
- 标签高度 36-44rpx。
- 标签内边距 10-16rpx。
- 标签圆角 8rpx 或 999rpx。

### VIP 会员价

- VIP 图片统一使用 `/static/images/vip.png`，尺寸 `40rpx × 22rpx`，使用 `mode="aspectFill"` 裁掉透明留白。
- VIP 图片与金额间距统一为 `6rpx`。
- VIP 金额统一使用 `24rpx/34rpx/700`，颜色使用 `#ffd791`。
- 上述尺寸与颜色维护在 `uni.scss` 的 `$vip-price-*` 变量中，页面和组件不得重复硬编码。

### 空态、加载态、错误态

- 空态优先使用项目状态图或 `getStatusImageUrl`；没有图时使用简洁图标 + 文案。
- 空态图片宽高 220-300rpx。
- 空态标题 28-30rpx，描述 24rpx。
- 加载态优先使用 `six-petal-spiral`。
- 商品详情首次加载使用与实际布局对应的骨架屏：主图保留固定高度，SKU 缩略图保留固定尺寸且不收缩，价格、标题、信息行及底部操作栏分别占位。骨架不绑定业务点击事件；已有详情的刷新不替换整页内容。请求失败必须退出骨架并展示重试，H5 对 `prefers-reduced-motion` 停用占位动画。
- 错误态必须有重试入口，按钮高度 60-72rpx。

### 弹窗与底部面板

- 优先使用 `app-bottom-sheet` 或 `uni-popup`。
- 底部面板顶部圆角 28-32rpx。
- 面板底部必须处理安全区。
- 遮罩使用 `rgba(0, 0, 0, 0.4)`。
- 商品海报分享复用 `product-share-popup`，由页面传入真实图片、名称、当前价格和完整分享路径；原生分享生命周期仍在页面注册。插件路径须保留自身商品 ID、场次和安装代次，H5 二维码按当前 history/hash 路由生成，不固定拼接 `/#`。海报按需串行合成，关闭/重开或卸载后忽略旧结果，失败展示重试；小程序沿用好友原生分享和海报保存，不能将“右上角分享”占位描述成已生成小程序码。

### 底部固定操作栏

- 高度 112-140rpx 加安全区。
- 背景 `--surface-card`。
- 阴影使用底栏阴影。
- H5 端需要考虑 `var(--window-bottom)`，微信小程序需要考虑 `env(safe-area-inset-bottom)`。

## 7. 交互规范

- 可点击元素必须有 `hover-class` 或清晰点击反馈。
- 列表项点击反馈使用轻微背景变化或 `opacity`，不要引发布局位移。
- 加载、提交、删除、支付等动作必须有 loading 或防重复点击状态。
- 危险操作使用 `uni.showModal` 二次确认。
- 跳转失败或数据缺失使用 `uni.showToast({ icon: 'none' })` 给出明确反馈。
- 下拉刷新结束必须在 `finally` 收敛状态。
- 同一页面只能保留一种下拉刷新机制：使用 `scroll-view` 的 `refresher-enabled` 时，`pages.json` 不得再开启 `enablePullDownRefresh`，页面也不得重复注册 `onPullDownRefresh`，否则 H5 可能叠加刷新指示器并重复拉伸内容区。
- WebSocket/异步消息类 UI 必须有断线、重连、补偿或错误提示状态。

## 8. 图片与媒体

- 图片必须指定固定尺寸或比例，避免加载后撑开布局。
- 头像：80-112rpx，圆形或 16-24rpx 圆角。
- 列表缩略图：160-240rpx 宽，高度固定。
- 卡片封面：常用 16:9、4:3 或固定 220-320rpx。
- 空图/占位图背景使用 `--surface-card-muted`，不要纯白不可见。
- 远程图片通过项目工具解析时优先使用 `resolveAssetUrl`。

## 9. H5 + 微信小程序 CSS 写法

### 安全写法

- 布局优先 `flex`、`flex-wrap`、固定 `width/height`、`margin/padding`。
- 滚动区域优先使用 `scroll-view`。
- 动态样式先在 computed 或数据预处理阶段生成简单字符串，再绑定模板。
- 条件编译用于真实平台差异，不要复制两套普通样式。

### 高风险写法

修改前必须搜索并评估：

- `gap:`
- `display: grid`
- `position: sticky`
- `100vh` / `100vw`
- `calc(` / `env(` 的复杂混合
- `backdrop-filter` / `filter`
- 普通 view 的 `overflow: auto` / `overflow: scroll`
- `-webkit-line-clamp`
- `body` / `html` / `*`
- 模板中函数式 `:style`
- 模板字符串式 `:class`
- 同元素 `v-if` + `v-for`

### 替代规则

- `gap` -> 父级负 margin + 子级 padding，或相邻选择器 margin。
- Grid -> flex-wrap + 百分比宽度。
- sticky -> fixed + 内容区补位，或业务滚动状态控制。
- backdrop-filter -> 半透明纯色背景。
- 普通滚动 view -> `scroll-view`。
- 多行省略 -> 固定高度 + `-webkit-line-clamp` 兜底。
- 复杂 `:style` -> computed 产出 `item.styleText`。

## 10. 页面 UI 优化清单

每次优化一个页面时按此顺序检查：

1. 是否有真实 loading / empty / error 状态。
2. 顶部导航与页面背景是否统一。
3. 内容区左右边距是否为 24/32rpx。
4. 卡片圆角、阴影、边框是否过重或不统一。
5. 标题、正文、辅助文案是否落在字号表内。
6. 长标题、长按钮、长价格、长地址是否会溢出。
7. 图片是否有固定尺寸、占位和裁剪方式。
8. 底部固定区域是否处理 H5 tabBar 与安全区。
9. 点击项是否有 hover/pressed 反馈。
10. 微信小程序模板是否避免复杂 `:style`、模板字符串 class、同元素 `v-if + v-for`。
11. 是否存在可沉淀为规范的新规则。

## 11. 技能反哺规则

优化页面后，如果出现以下情况，必须更新本规范：

- 某个 UI bug 在多个页面出现。
- 某个修复方式可复用。
- 某个尺寸、颜色、交互状态需要统一。
- 某个微信小程序兼容问题被验证。
- 某个页面形成了高质量模式，可作为后续页面模板。

追加规则时要求：

- 写具体数值，不写“适当”“好看一点”。
- 写触发场景和替代方案。
- 保持规则项目化，不写和本项目无关的通用理论。
