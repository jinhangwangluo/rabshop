import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

const OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取佣金中心首页数据。
 *
 * 返回 `summary`、`config`、`advertisement`，用于顶部金额和推广广告图。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录，成功时返回佣金首页数据。
 */
export function getCommissionIndex(params = {}, options = {}) {
  return get('user/commission/index', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取佣金中心统计卡片数据。
 *
 * 返回 `direct_count`、`order_count`、`month_estimate`。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录，成功时返回统计数据。
 */
export function getCommissionStatistics(params = {}, options = {}) {
  return get('user/commission/statistics', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取佣金收益明细分页列表。
 *
 * 返回 `{ list, total, page, limit }`，列表包含已结算佣金和冻结中佣金。
 *
 * @param {Object} params 查询参数。
 * @param {number} params.page 页码。
 * @param {number} params.limit 每页数量。
 * @param {string} [params.start_date] 开始日期，格式 YYYY-MM-DD。
 * @param {string} [params.end_date] 结束日期，格式 YYYY-MM-DD。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录，成功时返回收益明细分页数据。
 */
export function getCommissionLogs(params = {}, options = {}) {
  return get('user/commission/logs', params, {
    ...OPTIONS,
    ...options
  })
}

/**
 * 获取分销推广海报与二维码数据。
 *
 * 返回 `poster`、`promotion`、`qrcode`，用于分销推广页合成海报。
 * H5/公众号二维码由前端根据 `promotion.url` 生成；微信小程序使用后端返回的 `qrcode.image`。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录，成功时返回推广海报数据。
 */
export function getCommissionPromotionPoster(params = {}, options = {}) {
  return get('user/commission/promotion_poster', params, {
    ...OPTIONS,
    ...options
  })
}
