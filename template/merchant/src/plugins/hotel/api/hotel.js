import request from '@/utils/request'

const base = '/plugins/hotel'
const id = value => {
  if (!/^[1-9]\d*$/.test(String(value))) throw new Error('酒店资源 ID 无效')
  return String(value)
}

/** @param {Object} params 筛选和分页。 @returns {Promise<Object>} 本商户酒店 list/count。 */
export const getHotels = (params = {}) => request({ url: `${base}/hotels`, method: 'get', params })
/** @param {number} hotelId 本商户酒店 ID。 @returns {Promise<Object>} 酒店及 rooms[]。 */
export const getHotel = hotelId => request({ url: `${base}/hotels/${id(hotelId)}`, method: 'get' })
/** @param {Object} data 酒店和 rooms[]。 @param {number} hotelId 编辑 ID。 @returns {Promise<Object>} 回填详情。 */
export const saveHotel = (data, hotelId = '') => request({ url: hotelId ? `${base}/hotels/${id(hotelId)}` : `${base}/hotels`, method: hotelId ? 'put' : 'post', data })
/** @param {number} hotelId 酒店 ID。 @param {number} status 上架或下架。 @returns {Promise<Object>} 更新详情。 */
export const setHotelStatus = (hotelId, status) => request({ url: `${base}/hotels/${id(hotelId)}/status`, method: 'put', data: { status }})
/** @param {number} hotelId 酒店 ID。 @param {Object} params 房型和日期范围。 @returns {Promise<Object>} nights[]。 */
export const getCalendar = (hotelId, params) => request({ url: `${base}/hotels/${id(hotelId)}/calendar`, method: 'get', params })
/** @param {number} hotelId 酒店 ID。 @param {Object} data room_type_id、stay_date 和覆写。 @returns {Promise<Object>} 房晚。 */
export const setCalendarNight = (hotelId, data) => request({ url: `${base}/hotels/${id(hotelId)}/calendar`, method: 'put', data })
/** @param {Object} params 状态和分页。 @returns {Promise<Object>} 本商户预订 list/count。 */
export const getBookings = (params = {}) => request({ url: `${base}/bookings`, method: 'get', params })
/** @param {number} bookingId 预订 ID。 @returns {Promise<Object>} 快照和时间线。 */
export const getBooking = bookingId => request({ url: `${base}/bookings/${id(bookingId)}`, method: 'get' })
/** @param {number} bookingId 预订 ID。 @param {Object} data action/remark。 @returns {Promise<Object>} 履约结果。 */
export const recordStay = (bookingId, data) => request({ url: `${base}/bookings/${id(bookingId)}/stay`, method: 'put', data })
