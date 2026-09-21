import { get } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 获取移动端公开主题配置。
 *
 * 使用场景：App 启动或回到前台时调用，读取后台一键换色保存的 CSS 变量。
 * 匿名可访问，不触发登录跳转。
 *
 * @param {Object} params 查询参数，通常留空。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.colors` 为 CSS 变量和值的映射。
 */
export function getThemeConfig(params = {}, options = {}) {
	return get('theme/config', params, {
		authMode: AUTH_MODES.NONE,
		loginRedirect: false,
		...options
	})
}
