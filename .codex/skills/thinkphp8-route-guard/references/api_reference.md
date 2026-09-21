## ThinkPHP8 路由冲突快速检查

### 高风险冲突模式

- `GET xxx/search` vs `GET xxx/:id`
- `GET xxx/detail` vs `GET xxx/:slug`
- `GET xxx/hot` vs `GET xxx/:name`

### 快速修复顺序

1. 同方法路由内，把所有静态路径上移
2. 给动态参数增加 `pattern` 限制
3. 将最宽泛动态路由放末尾

### 复核口令（评审时）

- 同方法、同前缀、静态先行
- 参数约束、避免吞路由
- 扩展动作（search/hot/recommend）永远在 `:id` 前
