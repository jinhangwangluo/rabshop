import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

const ACTIVITY_REQUEST_OPTIONS = {
  authMode: AUTH_MODES.REQUIRED
}

/**
 * 获取每日签到页数据。
 *
 * 使用场景：每日签到页进入、页面显示和领奖后刷新签到进度与任务状态。
 *
 * @param {Object} params 查询参数，当前可为空。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回签到进度、积分余额和任务列表。
 */
export function getSigninIndex(params = {}, options = {}) {
  return get('activity/signin/index', params, {
    ...ACTIVITY_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 执行今日签到。
 *
 * 使用场景：用户点击每日签到按钮时调用，按后台配置发放积分、优惠券或叠加奖励。
 *
 * @param {Object} data 请求参数，当前可为空。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回最新签到页数据和本次签到结果。
 */
export function signInToday(data = {}, options = {}) {
  return post('activity/signin/sign', data, {
    ...ACTIVITY_REQUEST_OPTIONS,
    ...options
  })
}

/**
 * 领取签到任务积分。
 *
 * 使用场景：用户完成每日任务后点击领取积分。
 *
 * @param {Object} data 请求参数。
 * @param {string} data.task_key 任务标识。
 * @param {Object} options 透传给请求封装的附加选项，需要登录。
 * @returns {Promise<Object>} 成功时返回最新签到页数据和本次任务领奖结果。
 */
export function claimSigninTask(data = {}, options = {}) {
  return post('activity/signin/task/claim', data, {
    ...ACTIVITY_REQUEST_OPTIONS,
    ...options
  })
}
