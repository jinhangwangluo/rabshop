import request from '@/utils/request'
import { pluginSecureRequest, fetchPluginSecurePublicKey } from '@/utils/pluginSecureRequest'

const ADMIN_PLUGIN_PREFIX = '/api/admin/plugins'
const CATALOG_PREFIX = `${ADMIN_PLUGIN_PREFIX}/catalog`
const AUTH_PREFIX = `${ADMIN_PLUGIN_PREFIX}/auth`
const ORDER_PREFIX = `${ADMIN_PLUGIN_PREFIX}/orders`
const PUBLISH_PREFIX = `${ADMIN_PLUGIN_PREFIX}/publish`
const VERIFY_PREFIX = `${ADMIN_PLUGIN_PREFIX}/verify`
const BUILD_PREFIX = `${ADMIN_PLUGIN_PREFIX}/plugin-builds`
const DEVELOPER_PREFIX = '/plugins/developer'
const MAX_PLUGIN_PACKAGE_BYTES = 6 * 1024 * 1024
const MAX_PLUGIN_IMAGE_BYTES = 2 * 1024 * 1024

/**
 * 获取插件安全请求公钥。
 * 用于浏览器端加密引导，不需要额外登录参数。
 */
export function getPluginPublicKey() {
  return fetchPluginSecurePublicKey()
}

export const pluginPublicKey = getPluginPublicKey

/**
 * 获取插件分类树。
 * 需要登录。
 */
export function getPluginCatalogCategories(params = {}) {
  return marketRequest('GET', `${CATALOG_PREFIX}/categories`, 'catalog_categories', params)
}

/**
 * 获取插件列表。
 * 需要登录。
 */
export function getPluginCatalogList(params = {}) {
  return marketRequest('GET', `${CATALOG_PREFIX}/list`, 'catalog_list', normalizeCatalogParams(params))
}

/**
 * 获取精选插件。
 * 需要登录。
 */
export function getPluginCatalogFeatured(params = {}) {
  return marketRequest('GET', `${CATALOG_PREFIX}/featured`, 'catalog_featured', normalizeFeaturedParams(params))
}

/**
 * 获取插件详情。
 * 需要登录。
 */
export function getPluginCatalogDetail(code) {
  return marketRequest('GET', `${CATALOG_PREFIX}/detail/${normalizePluginCode(code)}`, 'catalog_detail')
}

/**
 * 获取短信点击验证场景。
 * 可匿名。
 * @param {string} phone 手机号
 * @returns {Promise<Object>} 场景令牌、点击验证图片与目标文字。
 */
export function getPluginSmsScene(phone) {
  return marketRequest('POST', `${AUTH_PREFIX}/sms-scene`, 'auth_sms_scene', {
    phone: normalizePhone(phone)
  })
}

/**
 * 校验行为验证并发送短信验证码。
 * 可匿名。
 * @param {Object} data 请求参数。
 * @param {string} data.phone 手机号。
 * @param {string} [data.scene_token] 自建点击验证场景令牌。
 * @param {number[]} [data.selected_points] 自建点击验证点击坐标。
 * @param {string} [data.ticket] 云市场验证票据。
 * @param {string} [data.randstr] 云市场验证随机串。
 * @returns {Promise<Object>} 发送结果与倒计时。
 */
export function sendPluginSmsCode(data = {}) {
  return marketRequest('POST', `${AUTH_PREFIX}/sms-send`, 'auth_sms_send', {
    phone: normalizePhone(data.phone),
    ...normalizeOptionalFields(data, ['scene_token', 'selected_points', 'ticket', 'randstr', 'biz_state'])
  })
}

/**
 * 手机验证码登录。
 * 可匿名。成功后绑定当前管理员与开放平台账号。
 * @param {Object} data 请求参数。
 * @param {string} data.phone 手机号。
 * @param {string} data.code 短信验证码。
 * @returns {Promise<Object>} token 与用户信息。
 */
export function pluginPhoneLogin(data = {}) {
  return marketRequest('POST', `${AUTH_PREFIX}/phone-login`, 'auth_phone_login', {
    phone: normalizePhone(data.phone),
    code: normalizeOptionalString(data.code, 8)
  })
}

/**
 * 手机账号密码登录。
 * 可匿名。成功后绑定当前管理员与开放平台账号。
 * @param {Object} data 请求参数。
 * @param {string} data.account 账号（手机号）。
 * @param {string} data.password 登录密码。
 * @returns {Promise<Object>} token 与用户信息。
 */
export function pluginAccountLogin(data = {}) {
  return marketRequest('POST', `${AUTH_PREFIX}/account-login`, 'auth_account_login', {
    account: normalizeOptionalString(data.account, 32),
    password: normalizeOptionalString(data.password, 64)
  })
}

/**
 * 获取当前管理员绑定的插件市场登录态。
 * 需要登录。
 */
export function getPluginOpenAuthInfo() {
  return marketRequest('GET', `${AUTH_PREFIX}/info`, 'open_auth_info')
}

/**
 * 退出插件市场登录。
 * 需要登录。
 */
export function logoutPluginOpenAuth() {
  return marketRequest('POST', `${AUTH_PREFIX}/logout`, 'open_auth_logout')
}

/**
 * 获取订单列表。
 * 需要登录。
 */
export function getPluginOrders(params = {}) {
  return marketRequest('GET', ORDER_PREFIX, 'order_list', params)
}

/**
 * 创建订单。
 * 需要登录。
 */
export function createPluginOrder(data = {}) {
  return marketRequest('POST', `${ORDER_PREFIX}/create`, 'order_create', data)
}

/**
 * 支付订单。
 * 需要登录。
 */
export function payPluginOrder(data = {}) {
  return marketRequest('POST', `${ORDER_PREFIX}/pay`, 'order_pay', data)
}

/**
 * 查询订单状态。
 * 需要登录。
 */
export function getPluginOrderStatus(data = {}) {
  return marketRequest('GET', `${ORDER_PREFIX}/status`, 'order_status', data)
}

/**
 * 取消待支付订单。
 * 需要登录。
 */
export function cancelPluginOrder(data = {}) {
  return marketRequest('POST', `${ORDER_PREFIX}/cancel`, 'order_cancel', data)
}

/**
 * 获取我的授权列表。
 * 需要登录。
 */
export function getPluginLicenses(params = {}) {
  return marketRequest('GET', `${ORDER_PREFIX}/licenses`, 'license_list', params)
}

/**
 * 获取我的可下载列表。
 * 需要登录。
 */
export function getPluginDownloads(params = {}) {
  return marketRequest('GET', `${ORDER_PREFIX}/downloads`, 'download_list', params)
}

/**
 * 申请下载票据。
 * 需要登录。
 */
export function requestPluginDownloadTicket(data = {}) {
  return marketRequest('POST', `${ORDER_PREFIX}/download/ticket`, 'download_ticket', data)
}

/**
 * 下载插件制品。
 * 需要登录，返回 blob 响应。
 */
export function downloadPluginFile(data = {}, requestOptions = {}) {
  return marketRequest('GET', `${ADMIN_PLUGIN_PREFIX}/download`, 'download_file', data, {
    responseType: 'blob',
    ...requestOptions
  })
}

/**
 * 获取个人中心信息。
 * 需要登录。
 */
export function getPluginUserInfo(params = {}) {
  return marketRequest('GET', `${ADMIN_PLUGIN_PREFIX}/user-info`, 'user_info', params)
}

/**
 * 更新个人昵称。
 * 需要登录。
 */
export function updatePluginUserInfo(data = {}) {
  return marketRequest('POST', `${ADMIN_PLUGIN_PREFIX}/user-update`, 'user_update', data)
}

/**
 * 获取资产流水。
 * 需要登录。
 */
export function getPluginAssetLogs(params = {}) {
  return marketRequest('GET', `${ADMIN_PLUGIN_PREFIX}/asset-logs`, 'asset_logs', params)
}

/**
 * 获取当前余额。
 * 可匿名。
 */
export function getPluginBalance() {
  return marketRequest('GET', `${ADMIN_PLUGIN_PREFIX}/balance`, 'balance')
}

/**
 * 获取发布分类树。
 * 可匿名。
 */
export function getPluginPublishCategories() {
  return marketRequest('GET', `${PUBLISH_PREFIX}/categories`, 'publish_categories')
}

/**
 * 获取我的发布列表。
 * 需要登录。
 */
export function getPluginPublishList() {
  return marketRequest('GET', `${PUBLISH_PREFIX}/my-list`, 'publish_list')
}

/**
 * 解析插件压缩包。
 * 需要登录。
 */
export async function inspectPluginPackage(data = {}) {
  return marketFileRequest('POST', `${PUBLISH_PREFIX}/inspect`, 'publish_inspect', data)
}

/**
 * 提交插件发布。
 * 需要登录。
 */
export async function publishPlugin(data = {}) {
  return marketFileRequest('POST', PUBLISH_PREFIX, 'publish', data)
}

/**
 * 重新提交审核。
 * 需要登录。
 */
export function submitPluginPublish(data = {}) {
  return marketRequest('POST', `${PUBLISH_PREFIX}/submit`, 'publish_submit', data)
}

/**
 * 上架或下架插件。
 * 需要登录。
 */
export function updatePluginPublishStatus(data = {}) {
  return marketRequest('POST', `${PUBLISH_PREFIX}/status`, 'publish_status', data)
}

/**
 * 个人实名认证。
 * 需要登录。
 */
export function verifyPluginPersonal(data = {}) {
  return marketRequest('POST', `${VERIFY_PREFIX}/personal`, 'verify_personal', data)
}

/**
 * 企业实名认证。
 * 需要登录。
 */
export function verifyPluginEnterprise(data = {}) {
  return marketRequest('POST', `${VERIFY_PREFIX}/enterprise`, 'verify_enterprise', data)
}

/**
 * 上传图片。
 * 需要登录。
 */
export async function uploadPluginImage(data = {}) {
  return marketFileRequest('POST', `${ADMIN_PLUGIN_PREFIX}/upload/image`, 'upload_image', data)
}

/**
 * 创建或更新小程序上传任务。
 * 需要登录。
 */
export function createPluginBuildMpUpload(id, data = {}) {
  return marketRequest('POST', `${BUILD_PREFIX}/${normalizePositiveInt(id)}/mp-upload`, 'build_mp_upload', data)
}

/**
 * 重试小程序上传任务。
 * 需要登录。
 */
export function retryPluginBuildMpUpload(id, data = {}) {
  return marketRequest('POST', `${BUILD_PREFIX}/${normalizePositiveInt(id)}/mp-upload/retry`, 'build_mp_upload_retry', data)
}

/**
 * 获取小程序制品下载地址。
 * 需要登录。
 */
export function getPluginBuildMpArtifactUrl(id) {
  return marketRequest('GET', `${BUILD_PREFIX}/${normalizePositiveInt(id)}/mp-artifact-url`, 'build_mp_artifact_url')
}

/**
 * 查询服务器本地开发插件及其安装状态，无须开放平台登录或购买。
 * @param {Object} params 查询条件，需要后台登录。
 * @param {string} [params.keyword] 名称或编码关键词。
 * @param {number} [params.page] 页码。
 * @param {number} [params.limit] 每页数量，最多 48 项。
 * @returns {Promise<Object>} 开发插件分页列表。
 */
export function getPluginDeveloperList(params = {}) {
  return request({
    url: DEVELOPER_PREFIX,
    method: 'get',
    params: {
      keyword: normalizeOptionalString(params.keyword, 100),
      page: normalizeOptionalInteger(params.page, 1, 1),
      limit: normalizeOptionalInteger(params.limit, 9, 1, 48)
    }
  })
}

/**
 * 安装固定目录中的开发插件，需要后台超级管理员权限，不参与市场购买。
 * @param {string} pluginCode 插件编码。
 * @param {Object} data 操作参数。
 * @param {string} [data.idempotency_key] 重试可复用的幂等键，缺省生成新的本地操作键。
 * @returns {Promise<Object>} 真实安装任务及后续生效要求。
 */
export function installDeveloperPlugin(pluginCode, data = {}) {
  return developerLifecycleRequest(pluginCode, 'install', data)
}

/**
 * 卸载开发插件，需要后台超级管理员权限。
 * @param {string} pluginCode 插件编码。
 * @param {Object} data 操作参数。
 * @param {'retain'|'delete'} [data.data_policy] 保留兼容的运营数据策略字段；开发来源在服务端固定按清理运营数据执行。
 * @param {string} [data.idempotency_key] 重试可复用的幂等键，缺省生成新的本地操作键。
 * @returns {Promise<Object>} 真实卸载任务及后续生效要求。
 */
export function uninstallDeveloperPlugin(pluginCode, data = {}) {
  return developerLifecycleRequest(pluginCode, 'uninstall', data)
}

/**
 * 用当前源码重新安装开发插件，先卸载再安装，需要后台超级管理员权限。
 * @param {string} pluginCode 插件编码。
 * @param {Object} data 操作参数。
 * @param {'retain'|'delete'} [data.data_policy] 保留兼容的运营数据策略字段；开发来源在服务端固定按清理运营数据执行。
 * @param {string} [data.idempotency_key] 重试可复用的幂等键，缺省生成新的本地操作键。
 * @returns {Promise<Object>} 安装任务结果及配套的卸载任务信息。
 */
export function reinstallDeveloperPlugin(pluginCode, data = {}) {
  return developerLifecycleRequest(pluginCode, 'reinstall', data)
}

/**
 * 下载整个开发插件源码包，包含后端、平台端、商户端、移动端与资源文件。
 * @param {string} pluginCode 插件编码。
 * @returns {Promise<Object>} zip 文件二进制响应，需要后台超级管理员权限。
 */
export function downloadDeveloperPlugin(pluginCode) {
  const code = normalizePluginCode(pluginCode)
  if (!/^[a-z][a-z0-9_]{1,63}$/.test(code)) {
    throw new Error('插件编码无效')
  }
  return request({ url: `${DEVELOPER_PREFIX}/${code}/download`, method: 'get', responseType: 'blob' })
}

/**
 * 兼容旧生命周期接口：安装插件。
 */
export function installPlugin(pluginCode, data = {}) {
  return lifecycleRequest(pluginCode, 'install', data)
}

/**
 * 兼容旧生命周期接口：升级插件。
 */
export function upgradePlugin(pluginCode, data = {}) {
  return lifecycleRequest(pluginCode, 'upgrade', data)
}

/**
 * 兼容旧生命周期接口：禁用插件。
 */
export function disablePlugin(pluginCode, data = {}) {
  return lifecycleRequest(pluginCode, 'disable', data)
}

/**
 * 兼容旧生命周期接口：卸载插件。
 */
export function uninstallPlugin(pluginCode, data = {}) {
  return lifecycleRequest(pluginCode, 'uninstall', data)
}

/**
 * 兼容旧生命周期接口：回滚插件。
 */
export function rollbackPlugin(pluginCode, data = {}) {
  return lifecycleRequest(pluginCode, 'rollback', data)
}

function marketRequest(logicalMethod, logicalPath, method, body = {}, requestOptions = {}, idempotencyKey = undefined) {
  const requestBody = normalizePlainBody(body)
  // 网关分发方法进入加密业务体，不能由业务参数覆盖。
  requestBody.method = normalizeGatewayMethod(method)
  return pluginSecureRequest({
    logicalMethod: normalizeMethod(logicalMethod),
    logicalPath: normalizeLogicalPath(logicalPath),
    body: requestBody,
    requestOptions,
    idempotencyKey
  })
}

async function marketFileRequest(logicalMethod, logicalPath, method, data = {}, requestOptions = {}) {
  const imageUpload = method === 'upload_image'
  const maxBytes = imageUpload ? MAX_PLUGIN_IMAGE_BYTES : MAX_PLUGIN_PACKAGE_BYTES
  const sizeMessage = imageUpload ? '图片大小不能超过 2MiB' : '插件包大小不能超过 6MiB'
  const body = await normalizeFileBody(data, maxBytes, sizeMessage)
  return marketRequest(logicalMethod, logicalPath, method, body, requestOptions)
}

function lifecycleRequest(pluginCode, action, data) {
  const code = normalizePluginCode(pluginCode)
  const lifecycleAction = String(action || '').trim()
  if (!['install', 'upgrade', 'disable', 'uninstall', 'rollback'].includes(lifecycleAction)) {
    throw new Error('插件生命周期动作无效')
  }

  return marketRequest(
    'POST',
    `${ADMIN_PLUGIN_PREFIX}/${code}/${lifecycleAction}`,
    `plugin_${lifecycleAction}`,
    data,
    {},
    data?.idempotency_key
  )
}

/**
 * 只透传开发插件入口允许的操作参数，禁止提交源码路径或制品证明。
 * @param {string} pluginCode 插件编码。
 * @param {'install'|'uninstall'|'reinstall'} action 生命周期动作。
 * @param {Object} data 操作参数。
 * @returns {Promise<Object>} 本地后台接口响应。
 */
function developerLifecycleRequest(pluginCode, action, data) {
  const code = normalizePluginCode(pluginCode)
  if (!/^[a-z][a-z0-9_]{1,63}$/.test(code)) {
    throw new Error('插件编码无效')
  }
  const lifecycleAction = String(action || '').trim()
  if (!['install', 'uninstall', 'reinstall'].includes(lifecycleAction)) {
    throw new Error('开发插件生命周期动作无效')
  }
  const input = normalizePlainBody(data)
  const body = { idempotency_key: resolveDeveloperIdempotencyKey(input.idempotency_key) }
  // 卸载与重装的卸载环节都会执行真实清理，必须提交确认过的数据保留策略。
  if (lifecycleAction !== 'install') {
    body.data_policy = normalizeEnum(input.data_policy, ['retain', 'delete'], 'retain')
  }
  return request({ url: `${DEVELOPER_PREFIX}/${code}/${lifecycleAction}`, method: 'post', data: body })
}

/**
 * 本地操作也需要幂等，但不依赖安全上下文或加密公钥。
 * @param {string|undefined} value 重试时显式复用的操作键。
 * @returns {string} 符合生命周期契约的非空操作键。
 */
function resolveDeveloperIdempotencyKey(value) {
  if (value === undefined) {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') return crypto.randomUUID()
    // 此键用于去重，不作为身份凭证；兼容没有 Web Crypto 的本地 HTTP 环境。
    return `developer-${Date.now()}-${Math.random().toString(36).slice(2)}`
  }
  if (typeof value !== 'string' || !/^[A-Za-z0-9._:-]{1,128}$/.test(value)) {
    throw new Error('幂等键格式无效')
  }
  return value
}

function normalizeCatalogParams(params = {}) {
  const result = normalizePlainBody(params)
  if (result.category_id !== undefined) {
    result.category_id = normalizeOptionalInteger(result.category_id, 0, 0)
  }
  if (result.keyword !== undefined) {
    result.keyword = normalizeOptionalString(result.keyword, 100)
  }
  if (result.sort !== undefined) {
    result.sort = normalizeEnum(result.sort, ['default', 'rating', 'downloads', 'latest'], 'default')
  }
  if (result.page !== undefined) {
    result.page = normalizeOptionalInteger(result.page, 1, 1)
  }
  if (result.limit !== undefined) {
    result.limit = normalizeOptionalInteger(result.limit, 12, 1, 48)
  }
  if (result.is_feature !== undefined) {
    result.is_feature = normalizeOptionalInteger(result.is_feature, 0, 0, 1)
  }
  if (result.billing_type !== undefined) {
    result.billing_type = normalizeEnum(result.billing_type, ['free', 'paid', 'buyout', 'subscription'], '')
  }
  return result
}

function normalizeFeaturedParams(params = {}) {
  const result = normalizePlainBody(params)
  if (result.category_id !== undefined) {
    result.category_id = normalizeOptionalInteger(result.category_id, 0, 0)
  }
  if (result.limit !== undefined) {
    result.limit = normalizeOptionalInteger(result.limit, 12, 1, 24)
  }
  return result
}

function normalizePlainBody(value) {
  if (value === null || value === undefined) {
    return {}
  }
  if (Array.isArray(value) || typeof value !== 'object') {
    throw new Error('请求参数必须为对象')
  }
  return { ...value }
}

async function normalizeFileBody(data, maxBytes, sizeMessage) {
  const body = isFileLike(data) ? { file: data } : normalizePlainBody(data)
  // 兼容原生 File、Blob 以及 el-upload 回填对象，统一压成同一种文件描述符。
  const file = resolveFileSource(body.file || body.upload_file || body.image)
  if (file) {
    if (file.size > maxBytes) {
      throw new Error(sizeMessage)
    }
    body.file = await encodeFileDescriptor(file)
  }
  if (!body.file) {
    throw new Error('请先选择文件')
  }
  body.file = normalizeEncodedFileDescriptor(body.file, maxBytes, sizeMessage)
  return body
}

function resolveFileSource(file) {
  if (!file) {
    return null
  }
  // el-upload 可能把原始文件挂在 raw / originFileObj / file 上，这里逐层兜底。
  if (isFileLike(file)) {
    return file
  }
  if (isFileLike(file.raw)) {
    return file.raw
  }
  if (isFileLike(file.originFileObj)) {
    return file.originFileObj
  }
  if (isFileLike(file.file)) {
    return file.file
  }
  return null
}

function isFileLike(value) {
  return (
    typeof File !== 'undefined' && value instanceof File
  ) || (
    typeof Blob !== 'undefined' && value instanceof Blob
  )
}

async function encodeFileDescriptor(file) {
  const blob = file instanceof Blob ? file : new Blob([file], { type: file.type || 'application/octet-stream' })
  // FileReader 返回 Data URL，发送前统一收敛为后端严格解码所需的纯 Base64。
  const content = extractBase64Content(await readBlobAsDataUrl(blob))
  const name = normalizeOptionalString(file.name || 'upload.bin', 255) || 'upload.bin'
  const type = normalizeOptionalString(file.type || blob.type || 'application/octet-stream', 100) || 'application/octet-stream'
  const size = Number(file.size || blob.size || 0)

  return {
    name,
    type,
    mime_type: type,
    size: Number.isFinite(size) && size >= 0 ? size : 0,
    content
  }
}

/**
 * 统一原生文件和已编码描述符，校验真实字节数后发送纯 Base64。
 * @param {Object} file 文件描述符。
 * @param {number} maxBytes 当前文件接口允许的最大字节数。
 * @param {string} sizeMessage 超出接口大小限制时的提示。
 * @returns {Object} 可 JSON 序列化的文件描述符。
 */
function normalizeEncodedFileDescriptor(file, maxBytes, sizeMessage) {
  if (!file || Array.isArray(file) || typeof file !== 'object') {
    throw new Error('文件参数无效')
  }
  const name = normalizeOptionalString(file.name, 255)
  if (!name || /[\\/\x00-\x1F\x7F]/.test(name)) {
    throw new Error('文件名称无效')
  }
  const content = extractBase64Content(file.content ?? file.base64)
  if (content.length > Math.ceil(maxBytes / 3) * 4) {
    throw new Error(sizeMessage)
  }
  let decoded
  try {
    decoded = atob(content)
  } catch (error) {
    throw new Error('文件编码无效')
  }
  if (decoded.length > maxBytes) {
    throw new Error(sizeMessage)
  }
  if (file.size !== undefined && Number(file.size) !== decoded.length) {
    throw new Error('文件大小与内容不一致')
  }
  const type = normalizeOptionalString(file.type || file.mime_type || 'application/octet-stream', 100)
  return {
    name,
    type,
    mime_type: type,
    size: decoded.length,
    content: btoa(decoded)
  }
}

/**
 * 提取 Data URL 或已编码内容中的纯 Base64，拒绝其他编码格式。
 * @param {string} value 文件编码内容。
 * @returns {string} 不带 Data URL 头的 Base64 字符串。
 */
function extractBase64Content(value) {
  let content = String(value ?? '').trim()
  if (content.startsWith('data:')) {
    const descriptor = content.match(/^data:[^,]*;base64,([A-Za-z0-9+/]*={0,2})$/)
    if (!descriptor) {
      throw new Error('文件编码无效')
    }
    content = descriptor[1]
  }
  if (!content || !/^[A-Za-z0-9+/]+={0,2}$/.test(content)) {
    throw new Error('文件编码无效')
  }
  return content
}

function readBlobAsDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(new Error('文件读取失败'))
    reader.readAsDataURL(blob)
  })
}

function normalizeMethod(method) {
  const value = String(method || '').trim().toUpperCase()
  if (!['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].includes(value)) {
    throw new Error('请求方法无效')
  }
  return value
}

function normalizeGatewayMethod(method) {
  const value = String(method || '').trim()
  if (!/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/.test(value) || value.length > 64) {
    throw new Error('插件网关方法无效')
  }
  return value
}

function normalizeLogicalPath(path) {
  const value = String(path || '').trim()
  if (
    value === '' ||
    value.length > 190 ||
    value[0] !== '/' ||
    value.includes('?') ||
    value.includes('..') ||
    /[\x00-\x1F\x7F]/.test(value)
  ) {
    throw new Error('请求路径无效')
  }
  return value
}

function normalizePluginCode(pluginCode) {
  const code = String(pluginCode || '').trim()
  if (!/^[a-z][a-z0-9_-]{0,63}$/.test(code)) {
    throw new Error('插件编码无效')
  }
  return code
}

function normalizePositiveInt(value) {
  const number = Number.parseInt(String(value || '').trim(), 10)
  if (!Number.isInteger(number) || number <= 0) {
    throw new Error('编号无效')
  }
  return number
}

function normalizeOptionalInteger(value, defaultValue, min, max = Number.POSITIVE_INFINITY) {
  if (value === '' || value === null || value === undefined) {
    return defaultValue
  }
  const number = Number.parseInt(String(value), 10)
  if (!Number.isInteger(number) || number < min || number > max) {
    throw new Error('整数参数无效')
  }
  return number
}

function normalizeOptionalString(value, maxLength) {
  const text = String(value || '').trim()
  if (text.length > maxLength) {
    throw new Error('参数长度超出限制')
  }
  return text
}

function normalizePhone(value) {
  const phone = normalizeOptionalString(value, 20)
  if (!/^1[3-9]\d{9}$/.test(phone)) {
    throw new Error('请输入正确的手机号')
  }
  return phone
}

function normalizeOptionalFields(data, keys) {
  const result = {}
  keys.forEach(key => {
    const value = data[key]
    if (value === undefined || value === null || value === '') {
      return
    }
    if (key === 'selected_points' && !Array.isArray(value)) {
      return
    }
    result[key] = value
  })
  return result
}

function normalizeEnum(value, allowed, defaultValue) {
  const text = String(value || '').trim()
  if (text === '') {
    return defaultValue
  }
  if (!allowed.includes(text)) {
    throw new Error('参数值无效')
  }
  return text
}
