import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取关于我们详情。
 *
 * @param {Object} options 请求附加选项。
 * @returns {Promise<Object>} 成功时 data 返回 id、content、update_time；无需登录。
 */
export function getAboutDetail(options = {}) {
	return get('about/detail', {}, {
		authMode: AUTH_MODES.NONE,
		...options
	})
}
