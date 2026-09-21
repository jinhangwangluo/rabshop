import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const liveRouter = {
  path: prefix + 'live',
  component: () => import('@/layout'),
  redirect: prefix + 'live/index',
  name: 'MerchantLive',
  meta: {
    title: '直播',
    icon: 'el-icon-video-camera'
  },
  children: [
    // 直播间列表
    {
      path: 'index',
      component: () => import('@/views/live/index'),
      name: 'MerchantLiveRoomList',
      meta: {
        title: '直播间管理',
        noCache: true
      }
    },
    // 创建/编辑直播间（隐藏菜单）
    {
      path: 'create',
      component: () => import('@/views/live/create'),
      name: 'MerchantLiveRoomCreate',
      hidden: true,
      meta: {
        title: '创建直播间',
        noCache: true,
        activeMenu: prefix + 'live/index'
      }
    },
    // 直播中控台（隐藏菜单，全屏无导航栏）
    {
      path: 'console',
      component: () => import('@/views/live/live_console'),
      name: 'MerchantLiveConsole',
      hidden: true,
      meta: {
        title: '直播中控台',
        noCache: true,
        hideHeader: true,
        activeMenu: prefix + 'live/index'
      }
    },
    // 主播管理
    {
      path: 'anchor',
      component: () => import('@/views/live/anchor/index'),
      name: 'MerchantLiveAnchorList',
      meta: {
        title: '主播管理',
        noCache: true
      }
    },
    // 直播打赏统计
    {
      path: 'reward',
      component: () => import('@/views/live/live_reward'),
      name: 'MerchantLiveOrderStats',
      meta: {
        title: '打赏统计',
        noCache: true
      }
    },
    // 贡献榜（隐藏菜单，通过直播间列表跳转）
    {
      path: 'room_rank',
      component: () => import('@/views/live/room_rank'),
      name: 'MerchantLiveRoomRank',
      hidden: true,
      meta: {
        title: '贡献榜',
        noCache: true,
        activeMenu: prefix + 'live/index'
      }
    },
    // 礼物列表（只读，平台管理）
    {
      path: 'gift',
      component: () => import('@/views/live/live_gift'),
      name: 'MerchantLiveGiftList',
      meta: {
        title: '礼物列表',
        noCache: true
      }
    },
    // 等级设置（只读，平台管理）
    {
      path: 'level_config',
      component: () => import('@/views/live/level_config'),
      name: 'MerchantLiveLevelConfig',
      meta: {
        title: '等级设置',
        noCache: true,
        hideHeader: true
      }
    },
    // 直播分类（只读，平台管理）
    {
      path: 'category',
      component: () => import('@/views/live/category'),
      name: 'MerchantLiveCategoryList',
      meta: {
        title: '直播分类',
        noCache: true
      }
    }
  ]
}

export default liveRouter
