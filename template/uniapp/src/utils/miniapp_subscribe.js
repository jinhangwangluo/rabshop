import { getMiniappSubscribeMessages } from '@/api/miniapp'
import { isMiniProgram } from '@/utils/platform'

const MAX_SUBSCRIBE_TEMPLATE_COUNT = 3
let subscribeMessageCache = null
let subscribeMessageLoading = null

/**
 * 规范化订阅业务类型，和后端 relation_type 保持一致。
 *
 * @param {string} type 前端按钮传入的业务类型。
 * @returns {string} 标准订阅业务类型。
 */
export function normalizeMiniappSubscribeType(type = '') {
  const value = String(type || '').trim()
  const aliases = {
    refund: 'order',
    order_refund: 'order',
    withdraw: 'user_withdraw',
    content: 'article',
    live: 'live_room'
  }

  return aliases[value] || value
}

/**
 * 从字符串或对象中提取微信订阅模板 ID。
 *
 * @param {string|Object} item 模板 ID 字符串或后端返回的对象。
 * @returns {string} 订阅模板 ID。
 */
function getTemplateId(item) {
  if (typeof item === 'string') {
    return item.trim()
  }

  if (!item || typeof item !== 'object') {
    return ''
  }

  return String(
    item.template_id ||
    item.subscribe_message_id ||
    item.external_template_id ||
    item.id ||
    ''
  ).trim()
}

/**
 * 规范化订阅模板列表，固定最多返回 3 个去重后的模板 ID。
 *
 * @param {Array<string|Object>} items 模板 ID 或包含模板 ID 的对象数组。
 * @returns {string[]} 可传给 requestSubscribeMessage 的模板 ID 数组。
 */
export function normalizeMiniappSubscribeTemplateIds(items = []) {
  const source = Array.isArray(items) ? items : []
  const seen = {}
  const templateIds = []

  source.forEach((item) => {
    const templateId = getTemplateId(item)
    if (!templateId || seen[templateId]) {
      return
    }

    seen[templateId] = true
    templateIds.push(templateId)
  })

  return templateIds.slice(0, MAX_SUBSCRIBE_TEMPLATE_COUNT)
}

/**
 * 获取订阅分组中的模板对象数组。
 *
 * @param {Object} group 后端返回的订阅分组。
 * @returns {Array<string|Object>} 可用于订阅的模板对象或 ID。
 */
function getGroupItems(group = {}) {
  if (!group || typeof group !== 'object') {
    return []
  }

  if (Array.isArray(group.list)) {
    return group.list
  }

  return Array.isArray(group.template_ids) ? group.template_ids : []
}

/**
 * 从全量订阅配置中按类型取模板对象数组。
 *
 * @param {Object} config 后端一次性返回的订阅配置。
 * @param {string} type 前端按钮传入的业务类型或事件标识。
 * @returns {Array<string|Object>} 匹配到的订阅模板对象数组。
 */
function getSubscribeItemsByType(config = {}, type = '') {
  const rawType = String(type || '').trim()
  const normalizedType = normalizeMiniappSubscribeType(rawType)
  const source = config && typeof config === 'object' ? config : {}
  const map = source.map && typeof source.map === 'object' ? source.map : {}
  const mappedGroup = map[normalizedType] || map[rawType]

  if (mappedGroup) {
    return getGroupItems(mappedGroup)
  }

  const groups = Array.isArray(source.list)
    ? source.list.filter((item) => item && typeof item === 'object' && item.type)
    : []
  const matchedGroup = groups.find((item) => item.type === normalizedType || item.type === rawType)
  if (matchedGroup) {
    return getGroupItems(matchedGroup)
  }

  const eventItems = []
  groups.forEach((group) => {
    getGroupItems(group).forEach((item) => {
      if (item && typeof item === 'object' && item.event_key === rawType) {
        eventItems.push(item)
      }
    })
  })
  if (eventItems.length) {
    return eventItems
  }

  const singleList = Array.isArray(source.list) ? source.list : []
  return singleList.filter((item) => !!getTemplateId(item))
}

/**
 * 从全量订阅配置中按事件 key 取模板对象数组。
 *
 * @param {Object} config 后端一次性返回的订阅配置。
 * @param {string[]} eventKeys 需要订阅的消息事件 key。
 * @returns {Array<string|Object>} 匹配到的订阅模板对象数组。
 */
function getSubscribeItemsByEvents(config = {}, eventKeys = []) {
  const keyMap = {}
  eventKeys.forEach((eventKey) => {
    const key = String(eventKey || '').trim()
    if (key) {
      keyMap[key] = true
    }
  })

  const source = config && typeof config === 'object' ? config : {}
  const groups = Array.isArray(source.list)
    ? source.list.filter((item) => item && typeof item === 'object' && item.type)
    : []
  const matchedItems = []

  groups.forEach((group) => {
    getGroupItems(group).forEach((item) => {
      const itemEventKeys = item && Array.isArray(item.event_keys) ? item.event_keys : []
      const hasMatchedEvent = itemEventKeys.some((eventKey) => keyMap[eventKey])
      if (item && typeof item === 'object' && (keyMap[item.event_key] || hasMatchedEvent)) {
        matchedItems.push(item)
      }
    })
  })

  if (matchedItems.length) {
    return matchedItems
  }

  const singleList = Array.isArray(source.list) ? source.list : []
  return singleList.filter((item) => {
    if (!item || typeof item !== 'object') {
      return false
    }

    const itemEventKeys = Array.isArray(item.event_keys) ? item.event_keys : []
    return keyMap[item.event_key] || itemEventKeys.some((eventKey) => keyMap[eventKey])
  })
}

/**
 * 清空小程序订阅消息前端缓存。
 *
 * @returns {void}
 */
export function clearMiniappSubscribeMessagesCache() {
  subscribeMessageCache = null
  subscribeMessageLoading = null
}

/**
 * 获取并缓存全部小程序订阅消息配置。
 *
 * @param {Object} options 附加配置。
 * @param {boolean} [options.forceRefresh=false] 是否强制刷新缓存。
 * @param {boolean} [options.showError=false] 是否展示接口错误提示。
 * @param {boolean} [options.loginRedirect=false] 未登录时是否自动跳登录。
 * @param {Object} [options.requestOptions] 透传给请求封装的附加参数。
 * @returns {Promise<{data:Object|null,response:Object|null,cached:boolean}>} 缓存读取结果。
 */
export async function getCachedMiniappSubscribeMessages(options = {}) {
  if (!isMiniProgram()) {
    return {
      data: null,
      response: null,
      cached: true,
      reason: 'not_miniapp'
    }
  }

  if (subscribeMessageCache && options.forceRefresh !== true) {
    return {
      data: subscribeMessageCache,
      response: null,
      cached: true
    }
  }

  if (subscribeMessageLoading && options.forceRefresh !== true) {
    return subscribeMessageLoading
  }

  subscribeMessageLoading = getMiniappSubscribeMessages({}, {
    showError: options.showError === true,
    loginRedirect: options.loginRedirect === true,
    ...(options.requestOptions || {})
  }).then((response) => {
    if (response && response.code === 200 && response.data) {
      subscribeMessageCache = response.data
      return {
        data: subscribeMessageCache,
        response,
        cached: false
      }
    }

    return {
      data: null,
      response: response || null,
      cached: false
    }
  }).finally(() => {
    subscribeMessageLoading = null
  })

  return subscribeMessageLoading
}

/**
 * 构造统一成功返回。订阅取消、拒绝或非小程序端都不会 reject，方便按钮流程继续向下走。
 *
 * @param {Object} payload 返回附加数据。
 * @returns {Object} 统一订阅结果。
 */
function buildSuccessResult(payload = {}) {
  return {
    success: true,
    subscribed: false,
    template_ids: [],
    accepted_template_ids: [],
    rejected_template_ids: [],
    reason: '',
    raw: null,
    ...payload
  }
}

/**
 * 解析微信订阅回调中的 accept/reject 状态。
 *
 * @param {string[]} templateIds 请求的模板 ID。
 * @param {Object} response 微信订阅回调原始结果。
 * @returns {{accepted:string[],rejected:string[]}} 订阅结果。
 */
function parseSubscribeResponse(templateIds = [], response = {}) {
  const accepted = []
  const rejected = []

  templateIds.forEach((templateId) => {
    const status = String(response && response[templateId] ? response[templateId] : '').toLowerCase()
    if (status === 'accept') {
      accepted.push(templateId)
      return
    }

    if (status) {
      rejected.push(templateId)
    }
  })

  return {
    accepted,
    rejected
  }
}

/**
 * 调用微信小程序订阅消息能力。
 *
 * @param {string[]} templateIds 订阅模板 ID 数组。
 * @returns {Promise<Object>} 不论微信回调 success/fail，均 resolve 统一成功对象。
 */
function requestWxSubscribeMessage(templateIds = []) {
  // #ifdef MP-WEIXIN
  if (typeof uni === 'undefined' || typeof uni.requestSubscribeMessage !== 'function') {
    return Promise.resolve(buildSuccessResult({
      template_ids: templateIds,
      reason: 'api_unavailable'
    }))
  }

  return new Promise((resolve) => {
    uni.requestSubscribeMessage({
      tmplIds: templateIds,
      success: (response) => {
        const parsed = parseSubscribeResponse(templateIds, response || {})
        resolve(buildSuccessResult({
          subscribed: parsed.accepted.length > 0,
          template_ids: templateIds,
          accepted_template_ids: parsed.accepted,
          rejected_template_ids: parsed.rejected,
          reason: parsed.accepted.length > 0 ? 'accepted' : 'completed',
          raw: response || null
        }))
      },
      fail: (error) => {
        resolve(buildSuccessResult({
          template_ids: templateIds,
          reason: 'request_failed',
          raw: error || null
        }))
      }
    })
  })
  // #endif

  return Promise.resolve(buildSuccessResult({
    template_ids: templateIds,
    reason: 'not_miniapp'
  }))
}

/**
 * 请求微信小程序订阅消息。
 *
 * 调用方可直接传后端返回的对象数组，也可传模板 ID 字符串数组；方法会自动去重并截取最多 3 个。
 * 订阅成功、拒绝、取消、非小程序端或微信 API 调用失败都会 resolve，不会阻塞原按钮流程。
 *
 * @param {Array<string|Object>} items 模板 ID 或包含 id/template_id 的对象数组。
 * @returns {Promise<Object>} 统一订阅结果，success 恒为 true。
 */
export async function requestMiniappSubscribeMessages(items = []) {
  const templateIds = normalizeMiniappSubscribeTemplateIds(items)
  if (!templateIds.length) {
    return buildSuccessResult({
      reason: 'empty_templates'
    })
  }

  if (!isMiniProgram()) {
    return buildSuccessResult({
      template_ids: templateIds,
      reason: 'not_miniapp'
    })
  }

  return requestWxSubscribeMessage(templateIds)
}

/**
 * 按消息事件 key 数组从缓存中取模板，并请求微信小程序订阅消息。
 *
 * 使用场景：退款申请订阅 refund_success/refund_failed，提现申请订阅 withdraw_approved/withdraw_failed。
 * 首次调用会一次性拉取全部订阅配置并缓存；后续按钮点击不会重复请求接口。
 *
 * @param {string[]} eventKeys 需要订阅的消息事件 key 数组。
 * @param {Object} options 附加配置。
 * @param {boolean} [options.forceRefresh=false] 是否强制刷新订阅配置缓存。
 * @param {boolean} [options.showError=false] 是否展示接口错误提示。
 * @param {boolean} [options.loginRedirect=false] 未登录时是否自动跳登录。
 * @param {Object} [options.requestOptions] 透传给请求封装的附加参数。
 * @returns {Promise<Object>} 统一订阅结果，success 恒为 true。
 */
export async function requestMiniappSubscribeMessagesByEvents(eventKeys = [], options = {}) {
  const keys = Array.isArray(eventKeys)
    ? eventKeys.map((item) => String(item || '').trim()).filter(Boolean)
    : []
  if (!keys.length) {
    return buildSuccessResult({
      reason: 'missing_events'
    })
  }

  if (!isMiniProgram()) {
    return buildSuccessResult({
      event_keys: keys,
      reason: 'not_miniapp'
    })
  }

  const cacheResult = await getCachedMiniappSubscribeMessages(options)

  if (!cacheResult.data) {
    return buildSuccessResult({
      event_keys: keys,
      reason: 'template_request_failed',
      raw: cacheResult.response || null
    })
  }

  const list = getSubscribeItemsByEvents(cacheResult.data, keys)
  const result = await requestMiniappSubscribeMessages(list)

  return {
    ...result,
    event_keys: keys,
    cached: cacheResult.cached,
    source: cacheResult.data
  }
}

/**
 * 按业务类型从缓存中取模板，并请求微信小程序订阅消息。
 *
 * 使用场景：页面按钮点击时调用，例如 `await requestMiniappSubscribeMessagesByType('order')`。
 * 首次调用会一次性拉取全部订阅配置并缓存；后续按钮点击不会重复请求接口。
 * 本方法默认不弹接口错误、不自动跳登录；订阅成功与失败都通过 resolve 返回。
 *
 * @param {string} type 订阅业务类型，支持事件标识或 relation_type，如 order/user_withdraw/article。
 * @param {Object} options 附加配置。
 * @param {boolean} [options.forceRefresh=false] 是否强制刷新订阅配置缓存。
 * @param {boolean} [options.showError=false] 是否展示接口错误提示。
 * @param {boolean} [options.loginRedirect=false] 未登录时是否自动跳登录。
 * @param {Object} [options.requestOptions] 透传给请求封装的附加参数。
 * @returns {Promise<Object>} 统一订阅结果，success 恒为 true。
 */
export async function requestMiniappSubscribeMessagesByType(type = '', options = {}) {
  const normalizedType = normalizeMiniappSubscribeType(type)
  if (!normalizedType) {
    return buildSuccessResult({
      reason: 'missing_type'
    })
  }

  if (!isMiniProgram()) {
    return buildSuccessResult({
      type: normalizedType,
      reason: 'not_miniapp'
    })
  }

  const cacheResult = await getCachedMiniappSubscribeMessages(options)

  if (!cacheResult.data) {
    return buildSuccessResult({
      type: normalizedType,
      reason: 'template_request_failed',
      raw: cacheResult.response || null
    })
  }

  const list = getSubscribeItemsByType(cacheResult.data, normalizedType)
  const result = await requestMiniappSubscribeMessages(list)

  return {
    ...result,
    type: normalizedType,
    cached: cacheResult.cached,
    source: cacheResult.data
  }
}

export default {
  normalizeMiniappSubscribeType,
  normalizeMiniappSubscribeTemplateIds,
  clearMiniappSubscribeMessagesCache,
  getCachedMiniappSubscribeMessages,
  requestMiniappSubscribeMessages,
  requestMiniappSubscribeMessagesByEvents,
  requestMiniappSubscribeMessagesByType
}
