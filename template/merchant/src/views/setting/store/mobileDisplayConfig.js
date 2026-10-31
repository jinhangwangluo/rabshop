export const DISPLAY_TYPES = [
  { type: 'special', label: '专题', limit: 8 },
  { type: 'content', label: '内容', limit: 10 },
  { type: 'product', label: '商品', limit: 10 }
]

/**
 * 规范化配置中的 ID，保留原顺序并去除重复项。
 * @param {Array<number|string>} value 配置 ID 列表
 * @param {number} max 最大 ID 数量
 * @returns {number[]} 有效 ID 列表
 */
export function normalizeDisplayIds(value, max = 100) {
  if (!Array.isArray(value)) return []
  const ids = value.map(Number).filter(id => Number.isSafeInteger(id) && id > 0)
  return [...new Set(ids)].slice(0, max)
}

/**
 * 将商户首页配置统一为保存契约，旧专题推荐只在没有新配置时转为自选。
 * @param {Object|null} value 接口返回的首页配置
 * @param {number[]} legacySpecialIds 旧版专题推荐 ID
 * @returns {Object} 三个首页板块、分类页与底部菜单配置
 */
export function normalizeMobileHomeConfig(value, legacySpecialIds = []) {
  const config = value && typeof value === 'object' && !Array.isArray(value) ? value : {}
  const incoming = Array.isArray(config.sections) ? config.sections : []
  const oldIds = normalizeDisplayIds(legacySpecialIds)
  const seen = new Set()
  const sections = []

  for (const item of incoming) {
    if (!item || seen.has(item.type)) continue
    const definition = DISPLAY_TYPES.find(entry => entry.type === item.type)
    if (!definition) continue
    sections.push(normalizeSection(item, definition))
    seen.add(item.type)
  }

  for (const definition of DISPLAY_TYPES) {
    if (seen.has(definition.type)) continue
    const useLegacy = incoming.length === 0 && definition.type === 'special' && oldIds.length > 0
    sections.push(normalizeSection({
      source: useLegacy ? 'manual' : 'all',
      ids: useLegacy ? oldIds : []
    }, definition))
  }

  const category = config.category && typeof config.category === 'object' ? config.category : {}
  const menu = config.menu && typeof config.menu === 'object' && !Array.isArray(config.menu) ? config.menu : {}
  return {
    sections,
    category: {
      mode: category.mode === 'product' ? 'product' : 'special',
      special_category_ids: normalizeDisplayIds(category.special_category_ids),
      product_category_ids: normalizeDisplayIds(category.product_category_ids)
    },
    menu: {
      mode: menu.mode === 'product' ? 'product' : 'special'
    }
  }
}

function normalizeSection(item, definition) {
  const limit = Number(item.limit)
  return {
    type: definition.type,
    enabled: item.enabled === undefined ? true : ![false, 0, '0'].includes(item.enabled),
    source: ['all', 'category', 'manual'].includes(item.source) ? item.source : 'all',
    limit: Number.isFinite(limit) ? Math.min(50, Math.max(1, Math.trunc(limit))) : definition.limit,
    category_ids: normalizeDisplayIds(item.category_ids),
    ids: normalizeDisplayIds(item.ids)
  }
}
