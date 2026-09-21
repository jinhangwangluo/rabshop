import { resolveAssetUrl } from '@/utils/runtime_config'

export const DEFAULT_BANNER_HEIGHT_PX = 210

function asArray(value) {
  return Array.isArray(value) ? value : []
}

function isObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function pickValue(value, fallback = '') {
  if (value === undefined || value === null) {
    return fallback
  }

  if (isObject(value)) {
    if (value.value !== undefined) {
      return value.value
    }
    if (value.val !== undefined) {
      return value.val
    }
    if (value.tabVal !== undefined) {
      return value.tabVal
    }
  }

  return value
}

function firstColor(value, fallback = '') {
  if (typeof value === 'string') {
    return value || fallback
  }

  if (Array.isArray(value)) {
    return firstColor(value[0], fallback)
  }

  if (isObject(value)) {
    return firstColor(value.item || value.color, fallback)
  }

  return fallback
}

function trimConfigString(value) {
  if (value === undefined || value === null) {
    return ''
  }
  return String(value).trim()
}

/**
 * 统一 DIY 组件类型，兼容历史装修数据中的搜索框别名。
 * @param {string} type 原始组件类型。
 * @returns {string} 规范化后的组件类型。
 */
export function normalizeDiyComponentType(type = '') {
  const normalized = String(type || '').trim().toLowerCase().replace(/-/g, '_')
  const aliasMap = {
    c_search_box: 'search',
    search_bar: 'search',
    search_box: 'search',
    searchbox: 'search'
  }

  return aliasMap[normalized] || normalized
}

function hasConfigValue(source, key) {
  return source && Object.prototype.hasOwnProperty.call(source, key) && source[key] !== undefined && source[key] !== null
}

function pickConfigColor(source, key, fallback = '') {
  if (hasConfigValue(source, key)) {
    return trimConfigString(source[key])
  }
  return fallback
}

function toNumber(value, fallback = 0) {
  const picked = pickValue(value, fallback)

  if (typeof picked === 'number') {
    return Number.isFinite(picked) ? picked : fallback
  }

  if (typeof picked === 'string') {
    const raw = picked.trim()
    if (!raw) return fallback

    if (/^-?\d+(\.\d+)?rpx$/i.test(raw)) {
      const parsed = parseFloat(raw)
      return Number.isFinite(parsed) ? parsed / 2 : fallback
    }

    if (/^-?\d+(\.\d+)?px$/i.test(raw)) {
      const parsed = parseFloat(raw)
      return Number.isFinite(parsed) ? parsed : fallback
    }

    const parsed = Number(raw)
    if (Number.isFinite(parsed)) return parsed
  }

  return fallback
}

function normalizeImage(url = '') {
  return resolveAssetUrl(url || '')
}

function isDiyComponentVisible(component = {}) {
  if (!component || component.status === undefined || component.status === null || component.status === '') {
    return true
  }

  return !(component.status === false || Number(component.status) === 0)
}

function inferLinkType(url = '') {
  return /^(https?:)?\/\//i.test(url) ? 'url' : (url ? 'page' : '')
}

function normalizeLink(item = {}) {
  const editorLink = isObject(item.__editor_link) ? item.__editor_link : {}
  const linkUrl = String(item.link_url || item.link || item.url || editorLink.link_url || '').trim()
  const explicitType = String(item.link_type || editorLink.link_type || '').trim()
  const inferredType = inferLinkType(linkUrl)

  return {
    link_type: explicitType === 'page' && inferredType === 'url' ? 'url' : (explicitType || inferredType),
    link_url: linkUrl
  }
}

function withLink(item = {}, extra = {}) {
  return {
    ...item,
    ...extra,
    ...normalizeLink({ ...item, ...extra })
  }
}

function normalizeSearchConfig(rawConfig = {}, page = {}) {
  const editorHotKeywords = asArray(rawConfig.hotWords?.list)
    .map((item) => item?.val || item?.value || '')
    .filter(Boolean)
  const hotKeywords = asArray(rawConfig.hot_keywords).filter(Boolean)
  const logoConfig = isObject(rawConfig.logoConfig) ? rawConfig.logoConfig : {}

  return withLink(rawConfig, {
    component_tone: 'hero',
    bg_color: 'transparent',
    box_bg_color: firstColor(rawConfig.searchBoxColor, rawConfig.box_bg_color),
    text_color: firstColor(rawConfig.tipColor, firstColor(rawConfig.txtColor, rawConfig.text_color)),
    hot_keywords_color: firstColor(rawConfig.hotWordsColor, rawConfig.hot_keywords_color),
    search_style: rawConfig.search_style || 'rounded',
    show_left_icon: toBoolean(rawConfig.show_left_icon, true),
    left_icon_url: normalizeImage(logoConfig.url || rawConfig.left_icon_url || rawConfig.icon_url || rawConfig.left_icon || ''),
    logoConfig: {
      url: normalizeImage(logoConfig.url || rawConfig.left_icon_url || rawConfig.icon_url || rawConfig.left_icon || '')
    },
    placeholder: String(pickValue(rawConfig.tipConfig, rawConfig.placeholder || hotKeywords[0] || editorHotKeywords[0] || '搜索你的下一件灵感作品') || '搜索你的下一件灵感作品'),
    hot_keywords: (hotKeywords.length > 0 ? hotKeywords : editorHotKeywords).slice(0, 5),
    brand_title: rawConfig.brand_title || page.page_title || '灵感工坊',
    brand_subtitle: rawConfig.brand_subtitle || rawConfig.subtitle || '策展式发现好课、专题与灵感内容',
    badge_text: rawConfig.badge_text || rawConfig.right_text || 'SHOP',
    show_brand: rawConfig.show_brand !== false,
    show_scan: rawConfig.show_scan === true,
    show_voice: rawConfig.show_voice === true
  })
}

function normalizeBannerItems(items = [], metaItems = []) {
  return asArray(items).map((item, index) => {
    const meta = isObject(metaItems[index]) ? metaItems[index] : {}
    const merged = { ...meta, ...item }
    return withLink(item, {
      image: normalizeImage(merged.image || merged.img || merged.cover || ''),
      title: merged.title || merged.name || merged.text || '',
      subtitle: merged.subtitle || merged.desc || merged.description || '',
      tag: merged.tag || merged.label || merged.badge_text || '',
      button_text: merged.button_text || merged.cta_text || merged.btn_text || '立即查看'
    })
  }).filter((item) => item.image)
}

function normalizeBannerConfig(rawConfig = {}, rawData = []) {
  const docTabVal = Number(pickValue(rawConfig.docConfig, rawConfig.show_indicator === false ? 4 : 0))
  const docPositionVal = Number(pickValue(rawConfig.docPosition, 0))
  const metaItems = asArray(rawConfig.__editor_meta?.items)
  const items = normalizeBannerItems(rawConfig.items || rawConfig.swiperConfig?.list || rawData, metaItems)
  const positionMap = {
    0: 'left',
    1: 'center',
    2: 'right'
  }

  return {
    ...rawConfig,
    component_tone: 'hero',
    autoplay: rawConfig.autoplay !== false,
    interval: toNumber(rawConfig.interval, 3000),
    show_indicator: docTabVal !== 4,
    indicator_style: rawConfig.indicator_style || (docTabVal === 3 ? 'number' : docTabVal === 2 ? 'line' : 'dot'),
    indicator_position: rawConfig.indicator_position || positionMap[docPositionVal] || 'left',
    indicator_color: firstColor(rawConfig.dotBgColor, rawConfig.indicator_color),
    indicator_active_color: firstColor(rawConfig.dotColor, rawConfig.indicator_active_color),
    card_radius: toNumber(rawConfig.card_radius, 32),
    height: toNumber(rawConfig.height, DEFAULT_BANNER_HEIGHT_PX),
    items
  }
}

function normalizeNavigationItems(items = [], metaItems = [], iconBgColor = '') {
  return asArray(items).map((item, index) => {
    const meta = isObject(metaItems[index]) ? metaItems[index] : {}
    const merged = { ...meta, ...item }

    return withLink(item, {
      icon: normalizeImage(merged.icon || merged.image || ''),
      name: merged.name || merged.text || '',
      desc: merged.desc || merged.subtitle || '',
      badge_text: merged.badge_text || merged.tag || '',
      icon_bg_color: pickConfigColor(merged, 'icon_bg_color', iconBgColor),
      icon_color: pickConfigColor(merged, 'icon_color')
    })
  }).filter((item) => item.name || item.icon)
}

function toBoolean(value, defaultValue = true) {
  if (value === undefined || value === null || value === '') {
    return defaultValue
  }
  if (value === true || value === 'true' || value === 1 || value === '1') {
    return true
  }
  if (value === false || value === 'false' || value === 0 || value === '0') {
    return false
  }
  return Boolean(value)
}

function normalizeNavigationConfig(rawConfig = {}, rawData = []) {
  const metaItems = asArray(rawConfig.__editor_meta?.items)
  const iconBgColor = pickConfigColor(rawConfig, 'icon_bg_color')
  const items = normalizeNavigationItems(rawConfig.items || rawConfig.icons || rawData, metaItems, iconBgColor)
  const rawShowTitle = rawConfig.show_title !== undefined ? rawConfig.show_title : rawConfig.show_text

  return {
    ...rawConfig,
    component_tone: 'section',
    bg_color: pickConfigColor(rawConfig, 'bg_color'),
    text_color: pickConfigColor(rawConfig, 'text_color'),
    columns: Math.min(Math.max(toNumber(rawConfig.columns, 5), 4), 6),
    max_rows: Math.max(toNumber(rawConfig.max_rows, 2), 1),
    show_title: toBoolean(rawShowTitle, true),
    card_radius: toNumber(rawConfig.card_radius, 28),
    items
  }
}

function normalizeImageCubeBlocks(blocks = []) {
  return asArray(blocks).map((item, index) => withLink(item, {
    id: item.id || `block_${index}`,
    image: normalizeImage(item.image || item.img || item.cover || ''),
    title: item.title || item.name || item.text || '',
    subtitle: item.subtitle || item.desc || '',
    rowStart: Number(item.rowStart ?? item.row_start ?? 1),
    rowEnd: Number(item.rowEnd ?? item.row_end ?? 1),
    colStart: Number(item.colStart ?? item.col_start ?? 1),
    colEnd: Number(item.colEnd ?? item.col_end ?? 1)
  })).filter((item) => item.image)
}

function normalizeHotZoneAreas(areas = []) {
  return asArray(areas).map((item, index) => withLink(item, {
    id: item.id || `area_${index}`,
    x: toNumber(item.x, 0),
    y: toNumber(item.y, 0),
    width: toNumber(item.width, 0),
    height: toNumber(item.height, 0)
  }))
}

function normalizeHotZoneConfig(rawConfig = {}, rawData = []) {
  const areas = normalizeHotZoneAreas(rawConfig.hot_areas || rawConfig.areas || rawData)
  return {
    ...rawConfig,
    component_tone: 'hero',
    image: normalizeImage(rawConfig.image || rawConfig.img || rawConfig.cover || ''),
    hot_areas: areas
  }
}

function normalizeImageCubeConfig(rawConfig = {}, rawData = []) {
  const blocks = normalizeImageCubeBlocks(rawConfig.blocks || rawConfig.items || rawData)

  return {
    ...rawConfig,
    component_tone: 'section',
    bg_color: pickConfigColor(rawConfig, 'bg_color'),
    ['gap']: toNumber(rawConfig.gap, 12),
    columns: Math.max(toNumber(rawConfig.columns, 2), 1),
    block_radius: toNumber(rawConfig.block_radius, 24),
    blocks
  }
}

function normalizeTitleConfig(rawConfig = {}) {
  return {
    ...rawConfig,
    component_tone: 'title',
    title: rawConfig.title || rawConfig.text || '精选内容',
    subtitle: rawConfig.subtitle || rawConfig.sub_text || '',
    eyebrow: rawConfig.eyebrow || rawConfig.tag || '',
    align: rawConfig.align || 'left',
    text_color: pickConfigColor(rawConfig, 'text_color', pickConfigColor(rawConfig, 'color')),
    show_more: rawConfig.show_more === true || Boolean(rawConfig.more_text || rawConfig.more_link_url || rawConfig.link_url),
    more_text: rawConfig.more_text || '查看全部',
    more_link_type: rawConfig.more_link_type || rawConfig.link_type || '',
    more_link_url: rawConfig.more_link_url || rawConfig.link_url || ''
  }
}

function normalizeSpecialConfig(rawConfig = {}) {
  return {
    ...rawConfig,
    title: rawConfig.title || '精选专题',
    display_style: rawConfig.display_style || rawConfig.style || 'scroll',
    show_count: Math.max(toNumber(rawConfig.show_count, 6), 1),
    show_desc: rawConfig.show_desc !== false,
    show_keywords: rawConfig.show_keywords === true,
    show_count_info: rawConfig.show_count_info !== false,
    show_more: rawConfig.show_more !== false,
    load_more: rawConfig.load_more === true,
    more_text: rawConfig.more_text || '查看全部',
    more_link_type: rawConfig.more_link_type || rawConfig.link_type || '',
    more_link_url: rawConfig.more_link_url || rawConfig.link_url || ''
  }
}

function normalizeLiveConfig(rawConfig = {}) {
  const rawStyle = String(rawConfig.display_style || rawConfig.style || 'list').trim()
  const displayStyle = rawStyle === 'big' || rawStyle === 'scroll' ? rawStyle : 'small'

  return {
    ...rawConfig,
    title: rawConfig.title || '直播',
    display_style: displayStyle,
    show_count: Math.max(toNumber(rawConfig.show_count, 4), 1),
    show_count_info: rawConfig.show_count_info !== false,
    show_more: rawConfig.show_more !== false,
    vertical: rawConfig.vertical === true,
    background_image: normalizeImage(rawConfig.background_image || ''),
    more_text: rawConfig.more_text || '更多直播',
    more_link_type: rawConfig.more_link_type || rawConfig.link_type || '',
    more_link_url: rawConfig.more_link_url || rawConfig.link_url || ''
  }
}

function normalizeCouponConfig(rawConfig = {}) {
  return {
    ...rawConfig,
    title: rawConfig.title || '奖励优惠',
    display_style: rawConfig.display_style || rawConfig.style || 'scroll',
    columns: Math.max(toNumber(rawConfig.columns, 2), 1),
    background_image: normalizeImage(rawConfig.background_image || ''),
    show_receive_btn: rawConfig.show_receive_btn !== false
  }
}

function normalizeContentConfig(rawConfig = {}) {
  const rawStyle = String(rawConfig.display_style || rawConfig.style || 'list').trim()
  const displayStyle = ['list', 'card', 'waterfall', 'category_tabs'].indexOf(rawStyle) > -1 ? rawStyle : 'list'

  return {
    ...rawConfig,
    title: rawConfig.title || '工坊故事',
    display_style: displayStyle,
    layout: rawConfig.layout === 'list' ? 'list' : 'alternate',
    show_count: Math.max(toNumber(rawConfig.show_count, 3), 1),
    show_cover: rawConfig.show_cover !== false,
    show_desc: rawConfig.show_desc !== false,
    show_price: rawConfig.show_price !== false,
    show_more: rawConfig.show_more !== false,
    load_more: rawConfig.load_more === true,
    more_text: rawConfig.more_text || '更多内容',
    more_link_type: rawConfig.more_link_type || rawConfig.link_type || '',
    more_link_url: rawConfig.more_link_url || rawConfig.link_url || ''
  }
}

function normalizeProductConfigTabs(rawConfig = {}) {
  const configuredTabs = asArray(rawConfig.category_tabs)
  const tabs = configuredTabs.length > 0
    ? configuredTabs
    : normalizeConfigIds(rawConfig.category_ids)

  return tabs.map((item) => {
    if (isObject(item)) {
      const id = toNumber(item.id, 0)
      return {
        id,
        name: item.name || `分类 #${id}`,
        subtitle: item.subtitle || '',
        image: normalizeImage(item.image || ''),
        items: []
      }
    }

    const id = toNumber(item || 0)
    return {
      id,
      name: `分类 #${id}`,
      subtitle: '',
      image: '',
      items: []
    }
  }).filter((item) => item.id > 0)
}

function normalizeProductConfig(rawConfig = {}) {
  const rawLayout = String(rawConfig.layout || 'grid').trim()
  const layout = rawLayout === 'list' ? 'list' : 'grid'
  const withNav = toBoolean(rawConfig.with_nav, true)
  const rawTabStyle = String(rawConfig.tab_style || 'subtitle').trim()
  const tabStyle = ['subtitle', 'arc', 'pill', 'image'].indexOf(rawTabStyle) > -1 ? rawTabStyle : 'subtitle'
  const rawSourceType = String(rawConfig.source_type || 'manual').trim()
  const sourceType = ['manual', 'category', 'tag', 'brand', 'auto'].indexOf(rawSourceType) > -1 ? rawSourceType : 'manual'
  const rawSortBy = String(rawConfig.sort_by || 'default').trim()
  const sortBy = ['default', 'sales', 'price'].indexOf(rawSortBy) > -1 ? rawSortBy : 'default'
  const categoryTabs = withNav ? normalizeProductConfigTabs(rawConfig) : []

  return {
    ...rawConfig,
    title: rawConfig.title || '好物推荐',
    layout,
    with_nav: withNav,
    tab_style: tabStyle,
    category_tabs: categoryTabs,
    category_ids: categoryTabs.map((item) => item.id),
    source_type: withNav ? 'category' : sourceType,
    data_source: withNav ? 'category_tabs' : sourceType,
    manual_ids: normalizeConfigIds(rawConfig.manual_ids),
    category_id: toNumber(rawConfig.category_id, 0),
    tag_id: toNumber(rawConfig.tag_id, 0),
    brand_id: toNumber(rawConfig.brand_id, 0),
    show_count: Math.max(toNumber(rawConfig.show_count, 6), 1),
    sort_by: sortBy,
    bg_color: pickConfigColor(rawConfig, 'bg_color', '#ffffff')
  }
}

function normalizeNoticeItems(items = [], content = '') {
  const list = asArray(items).map((item) => withLink(item, {
    text: item.text || item.title || item.content || ''
  })).filter((item) => item.text)
  return list.length > 0 ? list : (content ? [{ text: content, link_type: '', link_url: '' }] : [])
}

function normalizeGenericContent(rawData = [], priceKey = 'price') {
  return asArray(rawData).map((item) => ({
    ...item,
    cover: normalizeImage(item.cover || item.image || ''),
    image: normalizeImage(item.image || item.cover || ''),
    description: item.description || item.subtitle || item.desc || '',
    view_count: toNumber(item.view_count || item.views || item.learn_count || 0),
    course_count: toNumber(item.course_count || item.content_count || 0),
    view_label: item.view_label || '',
    [priceKey]: item[priceKey] || item.money || item.pay_price || ''
  }))
}

function normalizeProductItems(rawData = []) {
  const source = Array.isArray(rawData)
    ? rawData
    : (isObject(rawData) && Array.isArray(rawData.list) ? rawData.list : [])

  return source.map((item, index) => {
    const id = toNumber(item.id, 0)
    const title = item.title || item.name || '商品'
    const image = normalizeImage(item.image || item.cover || '')
    const priceValue = toNumber(item.price, 0)
    const originalPriceValue = toNumber(item.ot_price, 0)
    const realSales = toNumber(item.sales, 0)
    const virtualSales = toNumber(item.virtual_sales, 0)
    const displaySales = toNumber(
      item.display_sales !== undefined ? item.display_sales : (realSales + virtualSales),
      realSales + virtualSales
    )
    const priceText = String(item.price_text || '').trim() || `¥${priceValue.toFixed(2)}`
    const salesText = String(item.sales_text || '').trim() || `已售${displaySales}件`
    const memberPriceValue = toNumber(item.member_price, 0)
    const memberPriceAvailable = item.member_price_available === true ||
      Number(item.member_price_available || 0) === 1
    const memberPriceText = String(item.member_price_text || '').trim() ||
      (memberPriceAvailable && memberPriceValue > 0 ? `¥${memberPriceValue.toFixed(2)}` : '')

    return {
      ...item,
      id,
      title,
      name: item.name || title,
      cover: image,
      image,
      price: priceValue,
      ot_price: originalPriceValue,
      price_text: priceText,
      original_price_text: String(item.original_price_text || '').trim(),
      member_price: memberPriceValue,
      member_price_text: memberPriceText,
      member_price_available: memberPriceAvailable,
      member_enabled: item.member_enabled === true || Number(item.member_enabled || 0) === 1,
      member_visible: item.member_visible === true || Number(item.member_visible || 0) === 1,
      sales: realSales,
      virtual_sales: virtualSales,
      display_sales: displaySales,
      sales_text: salesText,
      tag_list: asArray(item.tag_list),
      tag_ids: normalizeConfigIds(item.tag_ids),
      renderKey: `product-${id || index}-${index}`
    }
  })
}

function isCategoryTabsData(value) {
  return isObject(value) && value.mode === 'category_tabs' && Array.isArray(value.categories)
}

function isCategoryTabsConfig(rawConfig = {}) {
  return rawConfig.display_style === 'category_tabs' || rawConfig.data_source === 'category_tabs'
}

function normalizeConfigIds(value) {
  if (typeof value === 'string') {
    value = value.split(',')
  }
  if (!Array.isArray(value)) {
    value = [value]
  }

  const ids = value.map((item) => {
    if (isObject(item)) {
      return toNumber(item.id || item.value || 0)
    }
    return toNumber(item || 0)
  }).filter((id) => id > 0)

  return Array.from(new Set(ids))
}

function normalizeCategoryConfigTabs(rawConfig = {}) {
  const configuredTabs = asArray(rawConfig.category_tabs)
  const tabs = configuredTabs.length > 0
    ? configuredTabs
    : normalizeConfigIds(rawConfig.category_ids)

  return tabs.map((item) => {
    if (isObject(item)) {
      const id = toNumber(item.id || item.value || 0)
      return {
        id,
        name: item.name || item.label || `分类 #${id}`,
        items: []
      }
    }
    const id = toNumber(item || 0)
    return {
      id,
      name: `分类 #${id}`,
      items: []
    }
  }).filter((item) => item.id > 0)
}

function normalizeCategoryTabsData(value = {}, rawConfig = {}) {
  const sourceCategories = isObject(value) ? asArray(value.categories) : []
  const categories = (sourceCategories.length > 0
    ? sourceCategories.map((category) => ({
      ...category,
      id: toNumber(category.id || category.value || 0),
      name: category.name || category.label || '分类',
      items: []
    }))
    : normalizeCategoryConfigTabs(rawConfig)
  ).filter((category) => category.id > 0)

  const rawList = Array.isArray(value)
    ? value
    : (isObject(value) ? value.list : [])
  const normalizedList = normalizeGenericContent(rawList || [])

  return {
    ...(isObject(value) ? value : {}),
    mode: 'category_tabs',
    categories,
    list: normalizedList
  }
}

function normalizeProductCategoryTabsData(value = {}, rawConfig = {}) {
  const sourceCategories = isObject(value) ? asArray(value.categories) : []
  const categories = (sourceCategories.length > 0
    ? sourceCategories.map((category) => ({
      ...category,
      id: toNumber(category.id, 0),
      name: category.name || '分类',
      subtitle: category.subtitle || '',
      image: normalizeImage(category.image || ''),
      items: []
    }))
    : normalizeProductConfigTabs(rawConfig)
  ).filter((category) => category.id > 0)

  const rawList = Array.isArray(value)
    ? value
    : (isObject(value) ? value.list : [])

  return {
    ...(isObject(value) ? value : {}),
    mode: 'category_tabs',
    categories,
    active_category_id: toNumber(
      isObject(value) ? (value.active_category_id || 0) : 0,
      0
    ),
    list: normalizeProductItems(rawList || [])
  }
}

function normalizeCouponData(rawData = []) {
  return asArray(rawData).map((item) => ({
    ...item,
    reduce_price: item.reduce_price || item.discount_amount || item.price || 0,
    full_price: item.full_price || item.min_order_amount || item.min_price || 0,
    valid_text: item.valid_text || (item.valid_days ? `领取后${item.valid_days}天有效` : '')
  }))
}

const DEFAULT_STYLE_CONFIG = {
  image_radius_mode: 'all',
  image_radius: 0,
  image_radius_lt: 0,
  image_radius_rt: 0,
  image_radius_lb: 0,
  image_radius_rb: 0,
  ['gap']: 4,
  icon_bg_color: '',
  indicator_color: '',
  bg_color_start: '',
  bg_color_end: '',
  bottom_bg_color: '',
  margin_top: 0,
  margin_bottom: 0,
  padding_lr: 0,
  padding_x: 10,
  page_margin_top: 0,
  radius_mode: 'all',
  border_radius: 0,
  border_radius_lt: 0,
  border_radius_rt: 0,
  border_radius_lb: 0,
  border_radius_rb: 0
}

function normalizeStyleConfig(rawStyleConfig) {
  if (!rawStyleConfig || typeof rawStyleConfig !== 'object') {
    return { ...DEFAULT_STYLE_CONFIG }
  }

  return {
    image_radius_mode: rawStyleConfig.image_radius_mode || 'all',
    image_radius: toNumber(rawStyleConfig.image_radius, 0),
    image_radius_lt: toNumber(rawStyleConfig.image_radius_lt, 0),
    image_radius_rt: toNumber(rawStyleConfig.image_radius_rt, 0),
    image_radius_lb: toNumber(rawStyleConfig.image_radius_lb, 0),
    image_radius_rb: toNumber(rawStyleConfig.image_radius_rb, 0),
    ['gap']: toNumber(rawStyleConfig.gap, 4),
    icon_bg_color: String(rawStyleConfig.icon_bg_color || '').trim(),
    indicator_color: String(rawStyleConfig.indicator_color || '').trim(),
    bg_color_start: pickConfigColor(rawStyleConfig, 'bg_color_start', DEFAULT_STYLE_CONFIG.bg_color_start),
    bg_color_end: pickConfigColor(rawStyleConfig, 'bg_color_end', DEFAULT_STYLE_CONFIG.bg_color_end),
    bottom_bg_color: pickConfigColor(rawStyleConfig, 'bottom_bg_color', DEFAULT_STYLE_CONFIG.bottom_bg_color),
    margin_top: toNumber(rawStyleConfig.margin_top, 0),
    margin_bottom: toNumber(rawStyleConfig.margin_bottom, 0),
    padding_lr: toNumber(rawStyleConfig.padding_lr, DEFAULT_STYLE_CONFIG.padding_lr),
    padding_x: toNumber(rawStyleConfig.padding_x, DEFAULT_STYLE_CONFIG.padding_x),
    page_margin_top: toNumber(rawStyleConfig.page_margin_top, 0),
    radius_mode: rawStyleConfig.radius_mode || 'all',
    border_radius: toNumber(rawStyleConfig.border_radius, 0),
    border_radius_lt: toNumber(rawStyleConfig.border_radius_lt, 0),
    border_radius_rt: toNumber(rawStyleConfig.border_radius_rt, 0),
    border_radius_lb: toNumber(rawStyleConfig.border_radius_lb, 0),
    border_radius_rb: toNumber(rawStyleConfig.border_radius_rb, 0)
  }
}

function hasExplicitImageRadius(styleConfig = {}) {
  if (!styleConfig || typeof styleConfig !== 'object') {
    return false
  }

  return ['image_radius', 'image_radius_lt', 'image_radius_rt', 'image_radius_lb', 'image_radius_rb']
    .some((key) => Object.prototype.hasOwnProperty.call(styleConfig, key)) ||
    Object.prototype.hasOwnProperty.call(styleConfig, 'image_radius_mode')
}

function getLegacyBannerImageRadiusStyle(rawConfig = {}) {
  const filletImg = isObject(rawConfig.filletImg) ? rawConfig.filletImg : {}
  const isEachMode = Number(filletImg.type) === 1
  const valList = asArray(filletImg.valList)

  if (isEachMode && valList.length === 4) {
    return {
      image_radius_mode: 'each',
      image_radius_lt: toNumber(valList[0]?.val, 0),
      image_radius_rt: toNumber(valList[1]?.val, 0),
      image_radius_lb: toNumber(valList[2]?.val, 0),
      image_radius_rb: toNumber(valList[3]?.val, 0)
    }
  }

  return {
    image_radius_mode: 'all',
    image_radius: toNumber(filletImg.val, 0)
  }
}

function normalizeComponentConfig(type, rawConfig = {}, rawData = [], page = {}) {
  let cfg
  if (type === 'search') cfg = normalizeSearchConfig(rawConfig, page)
  else if (type === 'banner') cfg = normalizeBannerConfig(rawConfig, rawData)
  else if (type === 'navigation') cfg = normalizeNavigationConfig(rawConfig, rawData)
  else if (type === 'notice') cfg = { ...rawConfig, items: normalizeNoticeItems(rawConfig.items || rawData, rawConfig.content || '') }
  else if (type === 'image_cube') cfg = normalizeImageCubeConfig(rawConfig, rawData)
  else if (type === 'hot_zone') cfg = normalizeHotZoneConfig(rawConfig, rawData)
  else if (type === 'title') cfg = normalizeTitleConfig(rawConfig)
  else if (type === 'special') cfg = normalizeSpecialConfig(rawConfig)
  else if (type === 'live') cfg = normalizeLiveConfig(rawConfig)
  else if (type === 'coupon') cfg = normalizeCouponConfig(rawConfig)
  else if (type === 'content') cfg = normalizeContentConfig(rawConfig)
  else if (type === 'product') cfg = normalizeProductConfig(rawConfig)
  else cfg = { ...rawConfig }

  // 统一挂载 style_config（保留原始 style_config，向下兼容无字段情况）
  cfg.style_config = normalizeStyleConfig(rawConfig.style_config)
  if (type === 'search') {
    cfg.style_config = {
      ...cfg.style_config,
      bg_color_start: '',
      bg_color_end: '',
      bottom_bg_color: ''
    }
  }
  if (type === 'banner' && !hasExplicitImageRadius(rawConfig.style_config) && isObject(rawConfig.filletImg)) {
    cfg.style_config = {
      ...cfg.style_config,
      ...getLegacyBannerImageRadiusStyle(rawConfig)
    }
  }

  return cfg
}

function normalizeComponentData(type, rawData = [], rawConfig = {}) {
  // 插件聚合包装保留场次和服务端时间，不转换成普通商品集合。
  if (type === 'seckill' && isObject(rawData) && Array.isArray(rawData.list)) {
    return { ...rawData, list: rawData.list.map(item => ({ ...item })) }
  }
  if (type === 'product' && (isCategoryTabsData(rawData) || rawConfig.with_nav === true || rawConfig.data_source === 'category_tabs')) {
    return normalizeProductCategoryTabsData(rawData, rawConfig)
  }

  if (type === 'product' && isObject(rawData) && Array.isArray(rawData.list)) {
    return {
      ...rawData,
      list: normalizeProductItems(rawData.list)
    }
  }

  if ((type === 'content' || type === 'special') && (isCategoryTabsData(rawData) || isCategoryTabsConfig(rawConfig))) {
    return normalizeCategoryTabsData(rawData, rawConfig)
  }

  if ((type === 'content' || type === 'special') && isObject(rawData) && Array.isArray(rawData.list)) {
    return {
      ...rawData,
      list: normalizeGenericContent(rawData.list)
    }
  }

  const dataList = asArray(rawData)

  if (type === 'banner') return dataList.length > 0 ? normalizeBannerItems(dataList) : rawConfig.items || []
  if (type === 'navigation') return dataList.length > 0 ? normalizeNavigationItems(dataList) : rawConfig.items || []
  if (type === 'notice') return normalizeNoticeItems(dataList.length > 0 ? dataList : rawConfig.items, rawConfig.content || '')
  if (type === 'image_cube') return dataList.length > 0 ? normalizeImageCubeBlocks(dataList) : rawConfig.blocks || []
  if (type === 'hot_zone') return dataList.length > 0 ? normalizeHotZoneAreas(dataList) : rawConfig.hot_areas || []
  if (type === 'live') {
    const source = dataList.length > 0 ? dataList : (rawConfig.live_data || rawConfig.items || rawConfig.list || [])
    return normalizeGenericContent(source)
  }
  if (type === 'content') {
    const source = dataList.length > 0 ? dataList : (rawConfig.content_data || rawConfig.items || rawConfig.list || [])
    return normalizeGenericContent(source)
  }
  if (type === 'special') {
    const source = dataList.length > 0 ? dataList : (rawConfig.special_data || rawConfig.items || rawConfig.list || [])
    return normalizeGenericContent(source)
  }
  if (type === 'coupon') return normalizeCouponData(dataList)
  if (type === 'product') {
    return normalizeProductItems(dataList)
  }
  return dataList
}

function normalizeGlobalConfig(globalConfig = {}, page = {}) {
  const background = isObject(globalConfig.background) ? globalConfig.background : {}
  const gradient = isObject(background.gradient) ? background.gradient : {
    angle: 180,
    colors: ['#EFE5DC', '#F6F6F6', '#FFFFFF']
  }
  const titleBar = isObject(globalConfig.title_bar) ? globalConfig.title_bar : {}

  return {
    background: {
      type: background.type || 'gradient',
      color: background.color || '#F6F6F6',
      image: normalizeImage(background.image || ''),
      image_mode: background.image_mode || 'cover',
      gradient
    },
    title_bar: isObject(globalConfig.title_bar) ? {
      show: globalConfig.title_bar.show !== false,
      title: globalConfig.title_bar.title || page.page_title || '首页',
      bg_color: pickConfigColor(titleBar, 'bg_color'),
      text_color: pickConfigColor(titleBar, 'text_color')
    } : {
      show: true,
      title: page.page_title || '首页',
      bg_color: '#F6F1EB',
      text_color: '#000000'
    },
    page_padding: toNumber(globalConfig.page_padding, 24),
    section_gap: toNumber(globalConfig.section_gap, 0),
    shell_overlay: globalConfig.shell_overlay !== false
  }
}

/**
 * 将字符串中所有 px 单位转换为 rpx（1px → 2rpx）
 * 用于处理后端返回数据中直接带 px 单位的字符串
 * @param {string|number} value
 * @returns {string}
 */
export function pxToRpx(value) {
  if (typeof value === 'number') {
    return `${value * 2}rpx`
  }
  if (typeof value !== 'string') return value
  // 将所有 数字px（不含rpx）替换为 数字*2 rpx
  return value.replace(/(\d+(\.\d+)?)px\b/g, (_, num) => `${Math.round(Number(num) * 2)}rpx`)
}

function shouldUsePxForInlineStyle() {
  // #ifdef H5
  return true
  // #endif
  return false
}

function formatPxUnit(value, fallbackPx = 0) {
  if (value === undefined || value === null || value === '') {
    return `${fallbackPx}px`
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return `${value}px`
  }

  if (typeof value === 'string') {
    const raw = value.trim()
    if (!raw) return `${fallbackPx}px`
    if (/^-?\d+(\.\d+)?rpx$/i.test(raw)) {
      return raw.replace(/(-?\d+(\.\d+)?)rpx\b/ig, (_, num) => `${Number(num) / 2}px`)
    }
    if (/^-?\d+(\.\d+)?px$/i.test(raw)) {
      return raw
    }
    if (/^-?\d+(\.\d+)?$/.test(raw)) {
      return `${Number(raw)}px`
    }
  }

  return `${fallbackPx}px`
}

function formatRpxUnit(value, fallbackPx = 0) {
  if (value === undefined || value === null || value === '') {
    return `${fallbackPx * 2}rpx`
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return `${Math.round(value * 2)}rpx`
  }

  if (typeof value === 'string') {
    const raw = value.trim()
    if (!raw) return `${fallbackPx * 2}rpx`
    if (/^-?\d+(\.\d+)?rpx$/i.test(raw)) return raw
    if (/^-?\d+(\.\d+)?px$/i.test(raw)) {
      return raw.replace(/(-?\d+(\.\d+)?)px\b/ig, (_, num) => `${Math.round(Number(num) * 2)}rpx`)
    }
    if (/^-?\d+(\.\d+)?$/.test(raw)) {
      return `${Math.round(Number(raw) * 2)}rpx`
    }
  }

  return `${fallbackPx * 2}rpx`
}

/**
 * 将数值 / px / rpx 字符串统一转换为当前端可直接用于行内 style 的尺寸。
 * - H5：输出 px，避免运行时行内 rpx 被 UniApp 转成 rem
 * - 小程序/App：输出 rpx，保持跨端自适应
 */
export function toRpxUnit(value, fallbackPx = 0) {
  return shouldUsePxForInlineStyle() ? formatPxUnit(value, fallbackPx) : formatRpxUnit(value, fallbackPx)
}

export function getImageRadiusStyle(styleConfig = {}) {
  const cfg = {
    ...DEFAULT_STYLE_CONFIG,
    ...(styleConfig && typeof styleConfig === 'object' ? styleConfig : {})
  }
  const style = {}

  if (cfg.image_radius_mode === 'each') {
    const lt = Number(cfg.image_radius_lt || 0)
    const rt = Number(cfg.image_radius_rt || 0)
    const rb = Number(cfg.image_radius_rb || 0)
    const lb = Number(cfg.image_radius_lb || 0)
    if (lt || rt || rb || lb) {
      style.borderRadius = `${toRpxUnit(lt)} ${toRpxUnit(rt)} ${toRpxUnit(rb)} ${toRpxUnit(lb)}`
      style.overflow = 'hidden'
    }
    return style
  }

  const radius = Number(cfg.image_radius || 0)
  if (radius > 0) {
    style.borderRadius = toRpxUnit(radius)
    style.overflow = 'hidden'
  }

  return style
}

/**
 * 将 HTML 字符串中内联 style 里的 px 转换为 rpx
 * 用于 DiyRichText 等需要渲染后端 HTML 内容的场景
 * @param {string} html
 * @returns {string}
 */
export function convertHtmlPxToRpx(html) {
  if (!html || typeof html !== 'string') return html
  return html.replace(/(\d+(\.\d+)?)px/g, (_, num) => `${Math.round(Number(num) * 2)}rpx`)
}

export function normalizeDiyComponent(component = {}, page = {}) {
  const componentType = normalizeDiyComponentType(component.component_type || component.type || '')
  const rawConfig = isObject(component.config) ? component.config : {}
  const rawData = component.data
  const config = normalizeComponentConfig(componentType, rawConfig, asArray(rawData), page)
  const data = normalizeComponentData(componentType, rawData, config)

  return {
    ...component,
    component_type: componentType,
    type: componentType,
    component_name: component.component_name || component.name || '',
    name: component.name || component.component_name || '',
    config,
    data
  }
}

export function normalizeDiyPagePayload(payload = {}) {
  const page = isObject(payload.page) ? payload.page : {}
  const normalizedPage = {
    ...page,
    page_title: page.page_title || page.page_name || '首页'
  }
  const globalConfig = normalizeGlobalConfig(page.global_config || {}, normalizedPage)
  const components = asArray(payload.components)
    .filter((component) => isDiyComponentVisible(component))
    .map((component) => normalizeDiyComponent(component, normalizedPage))

  return {
    page: {
      ...normalizedPage,
      global_config: globalConfig
    },
    components,
    globalConfig
  }
}
