import routes from './routes'

export const manifest = { schema_version: '1.0', code: 'hotel', name: '酒店预订', version: '0.1.0', surfaces: ['backend', 'admin', 'merchant', 'mobile'], events: { consumes: [] }}
/** @returns {Object} 插件商户路由，由构建期注册器接入。 */
export function register() { return { routes } }
export { routes }
export default { manifest, register, routes }
