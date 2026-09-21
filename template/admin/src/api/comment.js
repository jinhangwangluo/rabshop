import request from '@/utils/request'

/**
 * 获取内容评论列表
 *
 * @param {Object} params 查询参数
 * @param {string|number} params.article_id 内容ID筛选
 * @param {string} params.keyword 评论内容关键词
 * @param {string} params.nickname 用户昵称关键词
 * @param {string} params.create_time 时间范围，格式 "yyyy/MM/dd HH:mm:ss-yyyy/MM/dd HH:mm:ss"
 * @param {number} params.page 页码
 * @param {number} params.limit 每页条数
 * @returns {Promise}
 */
export function getCommentList(params) {
  return request({ url: '/content_comment', method: 'get', params })
}

/**
 * 删除评论（软删除）
 *
 * @param {number} id 评论ID
 * @returns {Promise}
 */
export function deleteComment(id) {
  return request({ url: `/content_comment/${id}`, method: 'delete' })
}

/**
 * 设置用户禁评 / 取消禁评
 *
 * @param {number} userId 用户ID
 * @param {number} isCommentBan 1=禁评，0=取消禁评
 * @returns {Promise}
 */
export function setCommentBan(userId, isCommentBan) {
  return request({
    url: '/user/set_comment_ban',
    method: 'post',
    data: { user_id: userId, is_comment_ban: isCommentBan }
  })
}
