import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const liveRouter = {
  path: prefix + 'live',
  component: () => import('@/layout'),
  name: 'Live',
  meta: {
    title: '直播管理',
    icon: 'el-icon-video-camera'
  },
  children: [
    {
      path: 'index',
      component: () => import('@/views/live/index'),
      name: 'LiveRoomList',
      meta: { title: '直播间管理', noCache: true }
    },
    {
      path: 'create',
      component: () => import('@/views/live/create'),
      name: 'LiveRoomCreate',
      meta: { title: '创建直播间', noCache: true, activeMenu: prefix + 'live/index' }
    },
    {
      path: 'anchor',
      component: () => import('@/views/live/anchor/index'),
      name: 'LiveAnchorList',
      meta: { title: '主播管理', noCache: true }
    },
    {
      path: 'gifts',
      component: () => import('@/views/live/live_gift'),
      name: 'LiveGiftList',
      meta: { title: '礼物管理', noCache: true }
    },
    {
      path: 'goods',
      component: () => import('@/views/live/live_goods'),
      name: 'LiveGoodsList',
      meta: { title: '直播商品管理', noCache: true }
    },
    {
      path: 'users',
      component: () => import('@/views/live/live_user'),
      name: 'LiveUserManage',
      meta: { title: '直播用户管理', noCache: true }
    },
    {
      path: 'reward',
      component: () => import('@/views/live/live_reward'),
      name: 'LiveOrderStats',
      meta: { title: '直播订单统计', noCache: true }
    },
    {
      path: 'category',
      component: () => import('@/views/live/category'),
      name: 'LiveCategoryList',
      meta: { title: '直播分类', noCache: true }
    },
    {
      path: 'level_config',
      component: () => import('@/views/live/level_config'),
      name: 'LiveRoomLevelConfig',
      meta: { title: '等级配置', noCache: true }
    },
    {
      path: 'room_rank',
      component: () => import('@/views/live/room_rank'),
      name: 'LiveRoomRank',
      meta: { title: '直播间贡献榜', noCache: true, activeMenu: prefix + 'live/index' }
    }
  ]
}

// 中台独立渲染，避免不同后台菜单样式再次包裹侧栏、导航和标签栏。
const liveConsoleRouter = {
  path: prefix + 'live/console',
  component: () => import('@/views/live/live_console'),
  name: 'LiveConsole',
  hidden: true,
  meta: { title: '直播中控台', noCache: true, breadcrumb: false }
}

export default [liveRouter, liveConsoleRouter]
