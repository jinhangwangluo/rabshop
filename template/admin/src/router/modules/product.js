import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
const productRouter = {
  path: prefix + 'product',
  component: () => import('@/layout'),
  name: 'Product',
  meta: {
    title: '商品管理',
    icon: 'el-icon-goods'
  },
  children: [
    {
      path: 'library',
      component: () => import('@/views/product/library/index'),
      name: 'ProductLibraryList',
      meta: {
        title: '商品库',
        noCache: true
      }
    },
    {
      path: 'library/create',
      component: () => import('@/views/product/library/create'),
      name: 'ProductLibraryCreate',
      meta: {
        title: '添加商品库商品',
        noCache: true,
        activeMenu: '/product/library'
      }
    },
    {
      path: 'list',
      component: () => import('@/views/product/list/index'),
      name: 'ProductList',
      meta: {
        title: '商品列表',
        noCache: true
      }
    },
    {
      path: 'list/create',
      component: () => import('@/views/product/list/create'),
      name: 'ProductCreate',
      meta: {
        title: '上架商品',
        noCache: true,
        activeMenu: '/product/list'
      }
    },
    {
      path: 'brand',
      component: () => import('@/views/product/brand/index'),
      name: 'ProductBrand',
      meta: {
        title: '商品品牌',
        noCache: true
      }
    },
    {
      path: 'category',
      component: () => import('@/views/product/category/index'),
      name: 'ProductCategory',
      meta: {
        title: '商品分类',
        noCache: true
      }
    },
    {
      path: 'service',
      component: () => import('@/views/product/service/index'),
      name: 'ProductServiceList',
      meta: {
        title: '商品服务',
        noCache: true
      }
    },
    {
      path: 'tag',
      component: () => import('@/views/product/tag/index'),
      name: 'ProductTagManage',
      meta: {
        title: '商品标签',
        noCache: true
      }
    },
    {
      path: 'spec',
      component: () => import('@/views/product/spec/index'),
      name: 'ProductSpec',
      meta: {
        title: '规格管理',
        noCache: true
      }
    },
    {
      path: 'group',
      component: () => import('@/views/product/group/index'),
      name: 'ProductGroup',
      meta: {
        title: '商品分组',
        noCache: true
      }
    },
    {
      path: 'attribute',
      component: () => import('@/views/product/attribute/index'),
      name: 'ProductAttribute',
      meta: {
        title: '商品属性',
        noCache: true
      }
    },
    {
      path: 'freight',
      component: () => import('@/views/product/freight/index'),
      name: 'ProductFreight',
      meta: {
        title: '运费模板',
        noCache: true
      }
    }
  ]
}

export default productRouter
