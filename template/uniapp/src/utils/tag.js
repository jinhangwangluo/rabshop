function splitTagText(value) {
  return String(value || '')
    .split(/[,，、\s]+/)
    .map((tag) => tag.trim())
    .filter(Boolean)
}

function pickTagColor(source, snakeKey, camelKey) {
  if (source[snakeKey] !== undefined && source[snakeKey] !== null) {
    return String(source[snakeKey]).trim()
  }
  if (source[camelKey] !== undefined && source[camelKey] !== null) {
    return String(source[camelKey]).trim()
  }
  return ''
}

function pickTagImage(source) {
  const keys = ['bg_image', 'bgImage', 'background_image', 'backgroundImage']
  for (let i = 0; i < keys.length; i += 1) {
    const key = keys[i]
    if (source[key] !== undefined && source[key] !== null) {
      return String(source[key]).trim()
    }
  }
  return ''
}

/**
 * 规范化单个标签对象。
 *
 * 使用场景：内容/专题列表、DIY 内容组件在渲染标签组件前，把后端标签或关键词字符串
 * 统一转换成包含名称、展示效果和颜色字段的对象。无需登录，纯前端数据处理。
 *
 * @param {Object|string} tag 后端标签对象或关键词字符串。
 * @param {number} index 标签序号，用于生成渲染 key。
 * @returns {Object|null} 可传给 tag-preview 的标签对象；无有效名称和图标时返回 null。
 */
export function normalizeDisplayTag(tag, index = 0) {
  const source = tag && typeof tag === 'object' ? tag : { name: tag }
  const name = String(source.name || source.label || source.title || source.text || '').trim()
  const icon = String(source.icon || '').trim()
  const requestedEffectType = Number(source.effect_type || source.effectType || (icon ? 2 : 1))
  const effectType = requestedEffectType === 2 && icon ? 2 : 1

  if (!name && !icon) {
    return null
  }

  return {
    ...source,
    id: Number(source.id || source.value || index + 1),
    name,
    label: String(source.label || name).trim(),
    effect_type: effectType,
    text_color: pickTagColor(source, 'text_color', 'textColor'),
    bg_color: pickTagColor(source, 'bg_color', 'bgColor'),
    border_color: pickTagColor(source, 'border_color', 'borderColor'),
    bg_image: pickTagImage(source),
    icon,
    renderKey: String(source.renderKey || source.render_key || source.id || source.value || `${name || icon}-${index}`)
  }
}

/**
 * 规范化标签列表。
 *
 * @param {Array|Object|string} value 后端 tag_list/tags/keyword_list/keywords 任一形式。
 * @param {number} limit 最多返回数量。
 * @returns {Array<Object>} 可直接渲染的标签对象列表。
 */
export function normalizeDisplayTags(value, limit = 3) {
  let list = value
  if (typeof list === 'string') {
    list = splitTagText(list)
  }
  if (!Array.isArray(list)) {
    list = list ? [list] : []
  }

  return list
    .map((tag, index) => normalizeDisplayTag(tag, index))
    .filter(Boolean)
    .slice(0, limit)
}
