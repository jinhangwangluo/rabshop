/**
 * 根据弹幕消息类型生成稳定样式类名。
 *
 * @param {Object} item 消息对象。
 * @returns {string} 弹幕行样式类名。
 */
export function getDanmuItemClassName(item = {}) {
  const rowType = String(item.rowType || '').trim()
  return rowType === 'enter' ? 'danmu-item--enter' : ''
}

/**
 * 格式化直播间价格文案，兼容数字和带货币符号的接口返回。
 *
 * @param {string|number} value 接口返回价格。
 * @param {string} fallback 空值兜底文案。
 * @returns {string} 展示价格。
 */
export function normalizePriceText(value, fallback = '') {
  const text = String(value === null || value === undefined ? '' : value).trim()
  if (!text) return fallback
  const priceValue = Number(text)
  if (!Number.isNaN(priceValue) && priceValue <= 0) return '免费'
  return /^[¥￥]/.test(text) ? text.replace('￥', '¥') : `¥${text}`
}

/**
 * 格式化直播间商品类型文案。
 *
 * @param {Object} item 商品接口对象。
 * @returns {string} 商品类型文案。
 */
export function formatGoodsSubtitle(item = {}) {
  const typeMap = {
    article: '图文内容',
    special: '专题内容',
    product: '实物商品'
  }
  return typeMap[item.goods_type] || String(item.goods_type || '')
}

/**
 * 将直播间商品接口对象转换为弹窗和浮动卡片使用的结构。
 *
 * @param {Object} item 商品接口对象。
 * @param {Function} resolveAssetUrl 资源地址转换函数。
 * @returns {Object|null} 商品展示对象。
 */
export function formatGoodsItem(item = {}, resolveAssetUrl = (url) => url) {
  const name = String(item.goods_title || '').trim()
  if (!name) return null
  const goodsType = String(item.goods_type || '')
  const displaySales = Math.max(0, Number(item.display_sales || 0))
  const salesText = String(item.sales_text || '').trim() || (goodsType === 'product' ? `已售${displaySales}件` : '')

  return {
    id: item.id,
    liveRoomId: item.live_room_id,
    goodsId: item.goods_id,
    goodsType,
    isExplaining: Number(item.is_explaining || 0) === 1,
    targetType: item.target_type,
    targetId: item.target_id,
    targetUrl: item.target_url,
    canOpen: Boolean(item.can_open),
    name,
    initial: String(name).slice(0, 1),
    subtitle: formatGoodsSubtitle(item),
    priceValue: Number(item.goods_price || 0),
    oldPriceValue: Number(item.goods_original_price || 0),
    price: normalizePriceText(item.goods_price || ''),
    oldPrice: normalizePriceText(item.goods_original_price || '', ''),
    tag: item.goods_tag || (item.link_no ? `#${item.link_no}` : ''),
    salesText,
    displaySubtitle: String(item.goods_subtitle || ''),
    image: resolveAssetUrl(item.goods_cover || ''),
    actionIconClass: item.can_open
      ? (goodsType === 'product' ? 'iconfont icon-daohanggouwuche' : 'iconfont icon-right')
      : 'iconfont icon-close'
  }
}

/**
 * 格式化贡献值，用户榜和 Socket 榜单共用。
 *
 * @param {string|number} value 贡献值。
 * @returns {string} 两位小数贡献值。
 */
export function normalizeContributionValue(value) {
  const numericValue = Number(value || 0)
  if (!Number.isFinite(numericValue) || numericValue <= 0) {
    return '0.00'
  }
  return numericValue.toFixed(2)
}

/**
 * 判断用户是否有可进入贡献前三名的贡献值。
 *
 * @param {Object} item 用户榜展示对象。
 * @returns {boolean} 是否有贡献值。
 */
export function hasRankContribution(item = {}) {
  return Number(item.score || 0) > 0
}

/**
 * 将在线用户接口对象转换为用户榜展示结构。
 *
 * @param {Object} item 在线用户接口对象。
 * @param {Function} resolveAssetUrl 资源地址转换函数。
 * @returns {Object|null} 用户榜展示对象。
 */
export function formatRankUser(item = {}, resolveAssetUrl = (url) => url) {
  const name = String(item.nickname || '').trim()
  if (!name) return null

  return {
    userId: item.user_id,
    name,
    initial: String(name).slice(0, 1),
    score: normalizeContributionValue(item.contribution),
    level: item.level_name || '',
    meta: Number(item.is_online || 0) === 1 ? '在线' : '离线',
    avatar: resolveAssetUrl(item.avatar || ''),
    highlight: false
  }
}

/**
 * 将 Socket 榜单用户转换为用户榜展示结构。
 *
 * @param {Object} item Socket 榜单用户对象。
 * @param {Function} resolveAssetUrl 资源地址转换函数。
 * @returns {Object|null} 用户榜展示对象。
 */
export function normalizeRankUserItem(item = {}, resolveAssetUrl = (url) => url) {
  const name = String(item.nickname || '').trim()
  if (!name) return null

  return {
    userId: item.user_id || 0,
    name,
    initial: String(name).slice(0, 1),
    score: normalizeContributionValue(item.contribution),
    level: item.level_name || (item.level !== undefined ? `Lv.${item.level}` : ''),
    meta: '',
    avatar: resolveAssetUrl(item.avatar || ''),
    highlight: false
  }
}

/**
 * 将礼物接口对象转换为礼物弹窗展示结构。
 *
 * @param {Object} item 礼物接口对象。
 * @param {Function} resolveAssetUrl 资源地址转换函数。
 * @returns {Object|null} 礼物展示对象。
 */
export function formatGiftItem(item = {}, resolveAssetUrl = (url) => url) {
  const name = String(item.name || '').trim()
  if (!name) return null

  const iconColor = item.icon_color || '#ff8a35'
  const iconBackground = item.icon_background || 'rgba(255, 138, 53, 0.16)'

  return {
    id: item.id,
    category: item.category_name || item.category || '默认',
    name,
    initial: String(name).slice(0, 1),
    icon: resolveAssetUrl(item.icon || ''),
    effectUrl: resolveAssetUrl(item.effect_url || ''),
    price: Number(item.price || 0),
    score: Number(item.score || 0),
    iconStyle: `color:${iconColor};background:${iconBackground};`,
    fallbackIconClass: 'iconfont icon-liwuhuodong',
    fallbackIconText: ''
  }
}
