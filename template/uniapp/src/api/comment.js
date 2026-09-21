import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取内容评论列表（一级 + 二级子评论）。
 *
 * @param {Object} params 查询参数。
 * @param {number|string} params.article_id 内容 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} data.list 为评论列表，data.count 为一级评论总数。
 */
export function getCommentList(params = {}, options = {}) {
  return get('comment/list', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 发布一级评论。
 *
 * @param {Object} data 请求参数。
 * @param {number} data.article_id 内容 ID。
 * @param {string} data.content 评论内容。
 * @param {Array<string>} data.images 图片 URL 数组。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>}
 */
export function createComment(data = {}, options = {}) {
  return post('comment/create', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 回复内容评论。
 *
 * @param {Object} data 请求参数。
 * @param {number} data.article_id 内容 ID。
 * @param {number} data.pid 被回复的一级评论 ID。
 * @param {string} data.content 回复内容。
 * @param {Array<string>} data.images 图片 URL 数组。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录，成功时返回新建回复。
 */
export function replyComment(data = {}, options = {}) {
  return post('comment/reply', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 删除当前用户自己的评论。
 *
 * @param {Object} data 请求参数。
 * @param {number} data.comment_id 评论 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 需要登录，成功时返回统一响应对象。
 */
export function deleteComment(data = {}, options = {}) {
  return post('comment/delete', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
