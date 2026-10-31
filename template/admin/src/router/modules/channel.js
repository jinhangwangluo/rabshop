import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')

const wechatRouter = {
  path: prefix + 'channel',
  component: () => import('@/layout'),
  name: 'channel',
  meta: {
    title: '渠道'
  },
  children: [
    {
      path: 'wechat/menus',
      component: () => import('@/views/wechat/menus/index'),
      name: 'WechatMenus',
      meta: { title: '微信菜单' }
    },
    {
      path: 'wechat/reply/keyword',
      component: () => import('@/views/wechat/reply/index'),
      name: 'WechatReplyKeyword',
      meta: { title: '关键字回复' }
    },
    {
      path: 'wechat/reply/keyword/save/:id?',
      component: () => import('@/views/wechat/reply/save'),
      name: 'WechatReplyKeywordSave',
      meta: { title: '编辑关键字' },
      hidden: true
    },
    {
      path: 'wechat/reply/subscribe',
      component: () => import('@/views/wechat/reply/save'),
      name: 'WechatReplySubscribe',
      meta: { title: '关注回复' }
    },
    {
      path: 'wechat/reply/default',
      component: () => import('@/views/wechat/reply/save'),
      name: 'WechatReplyDefault',
      meta: { title: '无效关键字回复' }
    }
  ]

}

export default wechatRouter
