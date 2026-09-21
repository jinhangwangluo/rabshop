---
name: category-service
description: 创建基于CategoryService的分类功能模块。当需要创建新的分类类型（如文章分类、商品分类、视频分类等）时使用此技能。包含Service继承、控制器实现、路由配置的完整流程。
---

# 分类模块开发技能

## 概述

本项目使用统一的 `CategoryService` 服务类处理所有分类功能，通过 `type` 字段区分不同分类类型。新增分类功能只需：

1. 在 Service 中定义新的类型常量
2. 创建对应的控制器
3. 配置路由

## 数据模型

分类表 `category` 核心字段：

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| pid | int | 父级ID |
| owner_id | int | 所属者ID（0为平台，商户ID为商户） |
| name | string | 分类名称 |
| path | string | 分类路径 |
| type | tinyint | 分类类型 |
| sort | int | 排序 |
| status | tinyint | 状态 |

## 一、Service 层实现

### 1.1 类型常量定义

在 `app/common/service/setting/CategoryService.php` 中添加新类型：

```php
class CategoryService extends BaseService implements ResourceServiceInterfaceTrait
{
    // 已有类型
    const CONFIG_TYPE = 0;   // 系统配置分类
    const FILE_TYPE = 1;     // 文件分类
    const DIY_TYPE = 2;      // 自定义分类
    const ARTICLE_TYPE = 3;  // 文章分类
    
    // 新增类型示例
    const PRODUCT_TYPE = 4;  // 商品分类
    const VIDEO_TYPE = 5;    // 视频分类
}
```

### 1.2 核心方法说明

```php
// 设置分类类型（链式调用）
public function setCategoryType(int $type): self

// 获取分类树
public function getCategoryTree(int $type = 0, int $ownerId = 0): array

// 获取分类列表
public function getCategoryList(array $where = []): array

// 获取创建/编辑表单
public function resourceCreate(array $params): array

// 保存资源（自动注入type）
public function resourceSave(array $data): Modelable

// 获取资源列表
public function getResourceList(array $params = [], ...): array
```

## 二、控制器实现

### 2.1 平台端控制器（继承 AuthController）

```php
<?php

namespace app\controller\admin\setting;

use app\common\service\setting\CategoryService;
use app\controller\admin\AuthController;
use think\annotation\Inject;
use think\Response;

class XxxCategory extends AuthController
{
    #[Inject]
    protected CategoryService $service;

    // 定义分类类型
    protected int $type = CategoryService::PRODUCT_TYPE;

    /**
     * 获取分类列表（树形）
     */
    public function list(): Response
    {
        return $this->success($this->service->getCategoryTree($this->type));
    }

    /**
     * 获取创建表单
     */
    public function create(): Response
    {
        $id = $this->request->get('id/d', 0);
        $pid = $this->request->get('pid/d', 0);

        return $this->success($this->service->setCategoryType($this->type)->resourceCreate([
            'id'  => $id,
            'pid' => $pid,
            'url' => '/setting/xxx_category/' . $id
        ]));
    }

    /**
     * 保存分类
     */
    public function save(int $id = 0): Response
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['sort', 0],
            ['path', []],
            ['pid', 0],
        ]);

        if (!$data['name']) {
            return $this->fail('分类名称必须填写');
        }

        if ($id) {
            $this->service->update($data, ['id' => $id]);
            return $this->success('修改成功');
        } else {
            $data['type'] = $this->type;
            $this->service->create($data);
            return $this->success('保存成功');
        }
    }

    /**
     * 删除分类
     */
    public function delete(int $id): Response
    {
        if ($this->service->count(['pid' => $id, 'type' => $this->type])) {
            return $this->fail('请先删除子分类');
        }

        if ($this->service->delete($id)) {
            return $this->success('删除成功');
        }
        return $this->fail('删除失败');
    }
}
```

### 2.2 商户端控制器（继承 merchant\AuthController）

```php
<?php

namespace app\controller\merchant\setting;

use app\common\service\setting\CategoryService;
use app\controller\merchant\AuthController;
use think\annotation\Inject;
use think\Response;

class XxxCategory extends AuthController
{
    #[Inject]
    protected CategoryService $service;

    protected int $type = CategoryService::PRODUCT_TYPE;

    /**
     * 获取分类列表（商户独立）
     */
    public function list(): Response
    {
        // 使用 merchantId 作为 owner_id 隔离数据
        return $this->success($this->service->getCategoryTree($this->type, $this->merchantId));
    }

    /**
     * 获取创建表单
     */
    public function create(): Response
    {
        $id = $this->request->get('id/d', 0);
        $pid = $this->request->get('pid/d', 0);

        return $this->success($this->service->setCategoryType($this->type)->resourceCreate([
            'id'       => $id,
            'pid'      => $pid,
            'url'      => '/setting/xxx_category/' . $id,
            'owner_id' => $this->merchantId,  // 重要：传入商户ID
        ]));
    }

    /**
     * 保存分类
     */
    public function save(int $id = 0): Response
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['sort', 0],
            ['path', []],
            ['pid', 0],
        ]);

        if (!$data['name']) {
            return $this->fail('分类名称必须填写');
        }

        // 设置 owner_id 为当前商户ID
        $data['owner_id'] = $this->merchantId;

        if ($id) {
            // 编辑时校验所有权
            $category = $this->service->getModel()
                ->where('id', $id)
                ->where('owner_id', $this->merchantId)
                ->find();
            
            if (!$category) {
                return $this->fail('分类不存在');
            }
            
            $this->service->update($data, ['id' => $id]);
            return $this->success('修改成功');
        } else {
            $data['type'] = $this->type;
            $this->service->create($data);
            return $this->success('保存成功');
        }
    }

    /**
     * 删除分类
     */
    public function delete(int $id): Response
    {
        // 校验所有权
        $category = $this->service->getModel()
            ->where('id', $id)
            ->where('owner_id', $this->merchantId)
            ->find();
        
        if (!$category) {
            return $this->fail('分类不存在');
        }

        if ($this->service->count(['pid' => $id, 'type' => $this->type, 'owner_id' => $this->merchantId])) {
            return $this->fail('请先删除子分类');
        }

        if ($this->service->delete($id)) {
            return $this->success('删除成功');
        }
        return $this->fail('删除失败');
    }
}
```

## 三、路由配置

### 3.1 关键规则：静态路由必须在动态路由之前

**错误示例**（会导致 `create` 被 `:id` 匹配）：
```php
Route::get('category/:id', '.../read');      // 动态路由在前
Route::get('category/create', '.../create'); // 永远不会匹配到
```

**正确示例**：
```php
Route::get('category', '.../list');
Route::get('category/create', '.../create');  // 静态路由在前
Route::post('category/move', '.../move');     // 静态路由在前
Route::get('category/:id', '.../read');       // 动态路由在后
Route::post('category/:id', '.../save');
Route::delete('category/:id', '.../delete');
```

### 3.2 平台端路由示例（route/admin.php）

```php
Route::group('xxx_category', function () {
    // 列表
    Route::get('', 'admin.setting.XxxCategory/list');
    // 创建表单（静态路由在动态路由前）
    Route::get('create', 'admin.setting.XxxCategory/create');
    // 保存
    Route::post('', 'admin.setting.XxxCategory/save');
    // 详情（动态路由在静态路由后）
    Route::get(':id', 'admin.setting.XxxCategory/read');
    // 更新
    Route::put(':id', 'admin.setting.XxxCategory/save');
    // 删除
    Route::delete(':id', 'admin.setting.XxxCategory/delete');
});
```

### 3.3 商户端路由示例（route/merchant.php）

```php
Route::group('xxx_category', function () {
    // 静态路由在前
    Route::get('', 'merchant.setting.XxxCategory/list');
    Route::get('create', 'merchant.setting.XxxCategory/create');
    Route::post('move', 'merchant.setting.XxxCategory/move');
    // 动态路由在后
    Route::post(':id', 'merchant.setting.XxxCategory/save');
    Route::delete(':id', 'merchant.setting.XxxCategory/delete');
});
```

## 四、前端API配置

```javascript
// api/xxx_category.js
import request from '@/utils/request'

// 获取分类列表
export function getXxxCategoryListApi(params) {
  return request({
    url: '/setting/xxx_category',
    method: 'get',
    params
  })
}

// 获取创建表单
export function getXxxCategoryFormApi(params) {
  return request({
    url: '/setting/xxx_category/create',
    method: 'get',
    params
  })
}

// 保存分类
export function saveXxxCategoryApi(id, data) {
  return request({
    url: `/setting/xxx_category/${id || 0}`,
    method: 'post',
    data
  })
}

// 删除分类
export function deleteXxxCategoryApi(id) {
  return request({
    url: `/setting/xxx_category/${id}`,
    method: 'delete'
  })
}
```

## 五、检查清单

开发新分类功能时：

- [ ] 在 CategoryService 中添加类型常量
- [ ] 创建控制器，设置 $type 属性
- [ ] 商户端控制器需传入 owner_id 参数
- [ ] 商户端控制器需校验数据所有权
- [ ] 路由配置：静态路由在动态路由之前
- [ ] 创建前端 API 文件
- [ ] 测试所有接口功能
