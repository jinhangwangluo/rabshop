import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
export default {
  path: prefix + 'setting',
  component: () => import('@/layout'),
  name: 'setting',
  meta: {
    title: '系统设置',
    icon: 'el-icon-setting'
  },
  redirect: prefix + 'setting/merchant',
  children: [
    {
      path: '',
      name: 'SettingIndex',
      component: () => import('@/views/setting/store/index.vue'),
      meta: { title: '店铺设置' }
    },
    {
      path: 'logs',
      name: 'SettingLogs',
      component: () => import('@/views/auth/logs/index.vue'),
      meta: { title: '网站日志' }
    },
    {
      path: 'roles',
      name: 'SettingRoles',
      component: () => import('@/views/auth/roles/index.vue'),
      meta: { title: '角色管理' }
    },
    {
      path: 'admin',
      name: 'SettingAdmin',
      component: () => import('@/views/auth/permissions/index.vue'),
      meta: { title: '管理员' }
    },
    {
      path: 'admin/info',
      name: 'SettingAdminInfo',
      component: () => import('@/views/auth/admin/index.vue'),
      meta: { title: '管理员信息', activeMenu: prefix + 'setting/admin' }
    },
    {
      path: 'merchant',
      name: 'SettingMerchant',
      component: () => import('@/views/setting/store/index.vue'),
      meta: { title: '店铺设置' }
    },
    {
      path: 'combination_data',
      name: 'CombinationData',
      component: () => import('@/views/setting/combinationData/index.vue'),
      meta: { title: '组合数据', noCache: true }
    },
    {
      path: 'combination_data/list',
      name: 'CombinationDataList',
      component: () => import('@/views/setting/combinationData/list.vue'),
      meta: {
        title: '组合数据列表',
        activeMenu: prefix + '/setting/combination_data',
        noCache: true
      },
      hidden: true
    },
    {
      path: 'config/form/:id',
      name: 'ConfigFormRender',
      component: () => import('@/views/setting/config/form.vue'),
      meta: {
        title: '表单预览'
      }
    }
  ]
}
