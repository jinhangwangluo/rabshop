import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const productRouter = {
  path: prefix + 'product',
  component: () => import('@/layout'),
  redirect: prefix + 'product/list',
  name: 'MerchantProduct',
  meta: {
    title: '商品管理',
    icon: 'el-icon-goods'
  },
  children: [
    {
      path: 'list',
      component: () => import('@/views/product/list/index'),
      name: 'MerchantProductList',
      meta: {
        title: '商品列表',
        noCache: true
      }
    },
    {
      path: 'list/create',
      component: () => import('@/views/product/list/create'),
      name: 'MerchantProductCreate',
      hidden: true,
      meta: {
        title: '上架商品',
        noCache: true,
        activeMenu: prefix + 'product/list'
      }
    },
    {
      path: 'library',
      component: () => import('@/views/product/library/index'),
      name: 'MerchantProductLibrary',
      meta: {
        title: '商品库',
        noCache: true
      }
    },
    {
      path: 'library/create',
      component: () => import('@/views/product/library/create'),
      name: 'MerchantProductLibraryCreate',
      hidden: true,
      meta: {
        title: '添加商品库商品',
        noCache: true,
        activeMenu: prefix + 'product/library'
      }
    },
    {
      path: 'tag',
      component: () => import('@/views/product/tag/index'),
      name: 'MerchantProductTag',
      meta: {
        title: '商品标签',
        noCache: true
      }
    },
    {
      path: 'category',
      component: () => import('@/views/product/category/index'),
      name: 'MerchantProductCategory',
      meta: {
        title: '商品分类',
        noCache: true
      }
    },
    {
      path: 'service',
      component: () => import('@/views/product/service/index'),
      name: 'MerchantProductService',
      meta: {
        title: '商品服务',
        noCache: true
      }
    },
    {
      path: 'group',
      component: () => import('@/views/product/group/index'),
      name: 'MerchantProductGroup',
      meta: {
        title: '商品分组',
        noCache: true
      }
    },
    {
      path: 'attribute',
      component: () => import('@/views/product/attribute/index'),
      name: 'MerchantProductAttribute',
      meta: {
        title: '商品属性',
        noCache: true
      }
    },
    {
      path: 'spec',
      component: () => import('@/views/product/spec/index'),
      name: 'MerchantProductSpec',
      meta: {
        title: '商品规格模板',
        noCache: true
      }
    },
    {
      path: 'freight',
      component: () => import('@/views/product/freight/index'),
      name: 'MerchantProductFreight',
      meta: {
        title: '运费模板',
        noCache: true
      }
    }
  ]
}

export default productRouter
