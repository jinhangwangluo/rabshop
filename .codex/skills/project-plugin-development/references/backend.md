# 后端插件骨架

后端源码位于 `plugins/{code}/backend`，使用 `Plugin\\{StudlyCode}` 命名空间。入口 Provider 只负责向插件内核注册事件和能力；路由由宿主自动加载固定入口，不要修改根路由或 `app/event.php`。

```php
<?php

namespace Plugin\\Seckill;

final class SeckillPlugin
{
    public function register(object $registry): void
    {
        $registry->provider('seckill', $this);
    }
}
```

Controller 只读取参数、调用插件 Service 并返回统一响应；业务校验、事务和跨资源查询放到 Service。路由文件直接使用 ThinkPHP 8 的 `think\facade\Route` 注册路由，不返回自定义数组。每个端使用一个固定前缀分组，组内先声明静态路径，再声明动态参数路径：

```php
use Plugin\Seckill\Controller\Mobile\Seckill;
use think\facade\Route;

Route::group('api/mobile/plugins/seckill', function (): void {
    Route::get('activities', [Seckill::class, 'index']);
    Route::get('activities/:id', [Seckill::class, 'show'])->pattern(['id' => '\d+']);
});
```

宿主只在插件启用时加载清单声明的 `backend/routes/{surface}.php`。路由前缀必须为 `api/{admin|merchant|mobile}/plugins/{code}`，组内路径保持相对；不得使用安装脚本执行迁移或 Shell。

迁移文件放在 `backend/database/migrations/`，文件名和类名必须全局唯一。脚本只检查路径、类名和重复项，不执行 `up()`/`down()`；执行迁移属于安装器并需要单独授权。

后端接口命名遵循 `/api/{admin|merchant|mobile}/plugins/{code}/*`，权限遵循 `plugin:{code}:*`。所有外部输入仍需在实际 Service/Controller 中做类型、长度和白名单校验。
