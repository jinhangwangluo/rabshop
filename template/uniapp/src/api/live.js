import { get, post } from './request'
import { AUTH_MODES } from '@/utils/auth'

/**
 * 直播业务接口封装。
 * 直播详情可匿名访问，但建立互动型 WebSocket 连接前需要先获取登录态对应的 ws token。
 */

/**
 * 获取直播间列表。
 *
 * 使用方式：直播列表页进入后调用本方法，按分页、关键词、分类、热门或直播状态拉取展示数据；
 * 匿名用户返回公开字段，登录用户会额外返回主播关注状态。
 *
 * @param {Object} params 查询参数，支持 `page`、`limit`、`keyword`、`status`、`category_id`、`is_hot`、`merchant_id`；`status` 仅支持 0（未开始）和 1（直播中）。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list`、`total`、`page`、`totalPage`；登录用户列表项包含 `is_followed`。
 */
export function getLiveRoomList(params = {}, options = {}) {
  return get('live/room/list', params, {
    authMode: AUTH_MODES.OPTIONAL,
    ...options
  })
}

/**
 * 获取直播分类选项。
 *
 * 使用方式：直播列表页进入后调用本方法，渲染顶部分类横栏和筛选弹窗分类项。
 *
 * @param {Object} params 查询参数，当前无需传参，保留扩展位。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 为直播分类数组，每项包含 `id`、`name`。
 */
export function getLiveCategoryOptions(params = {}, options = {}) {
  return get('live/category/options', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取直播间详情。
 *
 * 使用方式：直播详情页进入后先调用本方法，拿到标题、封面、主播信息和播放地址。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} params 附加查询参数。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含直播间详情。
 */
export function getLiveRoomDetail(roomId, params = {}, options = {}) {
  return get(`live/room/${roomId}/detail`, params, {
    authMode: AUTH_MODES.OPTIONAL,
    ...options
  })
}

/**
 * 获取直播间观看用户列表。
 *
 * 使用方式：直播间用户弹层打开或刷新时调用，返回公开观看用户字段。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} params 查询参数，支持 `page`、`limit`。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list`、`total`、`page`、`limit`。
 */
export function getLiveRoomUsers(roomId, params = {}, options = {}) {
  return get(`live/room/${roomId}/users`, params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取直播间商品列表。
 *
 * 使用方式：直播间进入、下拉刷新、商品弹层打开或商品变更 socket 事件后调用。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} params 附加查询参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.list` 返回统一商品 DTO。
 */
export function getLiveRoomGoods(roomId, params = {}, options = {}) {
  return get(`live/room/${roomId}/goods`, params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 创建直播间商品立即购买临时项。
 *
 * 使用方式：直播间商品弹层点击立即购买入口时调用；直播价来自 live_goods，不进入普通购物车。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} data 请求参数。
 * @param {number|string} data.live_goods_id 直播间商品 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时返回 `cart` 与 `preview`，需要登录。
 */
export function buyNowLiveRoomGoods(roomId, data = {}, options = {}) {
  return post(`live/room/${roomId}/goods/buy_now`, data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取可用直播礼物列表。
 *
 * 使用方式：直播间进入或礼物弹层打开时调用。
 *
 * @param {Object} params 附加查询参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.list` 返回可用礼物 DTO。
 */
export function getLiveGiftList(params = {}, options = {}) {
  return get('live/gift/list', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取直播金币快捷充值档位。
 *
 * 使用方式：直播间点击礼物面板充值时调用，按后台配置返回金币数量与应付金额。
 *
 * @param {Object} params 附加查询参数，当前无需传参。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.list` 返回充值档位，`data.exchange_rate` 为当前兑换比例。
 */
export function getLiveCoinRechargeOptions(params = {}, options = {}) {
  return get('live/coin/recharge/options', params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 获取直播间 WebSocket 登录令牌。
 *
 * 使用方式：`utils/socket.js` 在连接直播互动通道前调用本方法，获取 `ws_url` 和 `token`。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} params 附加查询参数。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `ws_url` 与 `token`。
 */
export function getLiveRoomWsToken(roomId, params = {}, options = {}) {
  return get(`live/room/${roomId}/ws_token`, params, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 获取直播间最近弹幕/评论列表。
 *
 * 使用方式：直播页进入后先拉取首屏历史消息，保证未登录用户也能看到最近互动内容。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} params 查询参数，支持 `page`、`limit`。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 中包含 `list` 与 `total`。
 */
export function getLiveRoomComments(roomId, params = {}, options = {}) {
  return get(`live/room/${roomId}/comments`, params, {
    authMode: AUTH_MODES.NONE,
    ...options
  })
}

/**
 * 赠送直播间礼物。
 *
 * 使用方式：登录用户在礼物面板选择礼物和数量后调用，后端扣减余额并广播礼物消息。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} data 请求参数。
 * @param {number|string} data.gift_id 礼物 ID。
 * @param {number|string} data.quantity 礼物数量。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回送礼结果、余额和等级信息。
 */
export function sendLiveRoomGift(roomId, data = {}, options = {}) {
  return post(`live/room/${roomId}/gift/send`, data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 批量上报直播间点赞次数。
 *
 * 使用方式：直播间点赞动效立即播放，前端按防抖/阈值聚合后调用本方法提交增量。
 *
 * @param {number|string} roomId 直播间 ID。
 * @param {Object} data 请求参数。
 * @param {number|string} data.count 本次聚合点赞数，后端会限制单次最大值。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data` 返回本次计数和直播间总点赞数；需要登录。
 */
export function sendLiveRoomLike(roomId, data = {}, options = {}) {
  return post('live/room/like', {
    room_id: roomId,
    ...data
  }, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}

/**
 * 切换当前用户对主播的关注状态。
 *
 * 使用方式：直播列表页点击主播关注按钮时调用；需要登录。
 *
 * @param {Object} data 请求参数。
 * @param {number|string} data.anchor_id 主播 ID。
 * @param {Object} options 透传给请求封装的附加选项。
 * @returns {Promise<Object>} 成功时 `data.is_followed` 返回切换后的关注状态。
 */
export function toggleLiveAnchorFollow(data = {}, options = {}) {
  return post('live/anchor/follow/toggle', data, {
    authMode: AUTH_MODES.REQUIRED,
    ...options
  })
}
