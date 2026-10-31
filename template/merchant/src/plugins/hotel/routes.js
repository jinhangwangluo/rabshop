import settings from '@/settings'

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
const meta = (title, extra = {}) => ({ title, pluginCode: 'hotel', ...extra })

export default [{
  path: prefix + 'hotel', name: 'hotelMerchantLayout', component: () => import('@/layout'), meta: meta('酒店预订'),
  children: [
    { path: 'hotels', name: 'hotelMerchantHotels', component: () => import('./views/hotel/index.vue'), meta: meta('酒店管理') },
    { path: 'hotels/create', name: 'hotelMerchantCreate', component: () => import('./views/hotel/form.vue'), hidden: true, meta: meta('新增酒店', { activeMenu: prefix + 'hotel/hotels' }) },
    { path: 'hotels/:id/edit', name: 'hotelMerchantEdit', component: () => import('./views/hotel/form.vue'), hidden: true, meta: meta('编辑酒店', { activeMenu: prefix + 'hotel/hotels' }) },
    { path: 'hotels/:id/calendar', name: 'hotelMerchantCalendar', component: () => import('./views/hotel/calendar.vue'), hidden: true, meta: meta('房态日历', { activeMenu: prefix + 'hotel/hotels' }) },
    { path: 'bookings', name: 'hotelMerchantBookings', component: () => import('./views/booking/index.vue'), meta: meta('酒店预订', { hotelEvidence: true }) },
    { path: 'bookings/:id', name: 'hotelMerchantBookingDetail', component: () => import('./views/booking/detail.vue'), hidden: true, meta: meta('预订详情', { activeMenu: prefix + 'hotel/bookings', hotelEvidence: true }) }
  ]
}]
