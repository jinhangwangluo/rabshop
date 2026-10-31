/**
 * 获取有数据的首页排行分组，空分组不占用首页展示区域。
 *
 * @param {Array<Object>} sections 后端返回的排行分组。
 * @returns {Array<Object>} 保留原顺序及内容的可见分组。
 */
export function getVisibleRankingSections(sections = []) {
  if (!Array.isArray(sections)) return []

  return sections.filter(section => section && Array.isArray(section.list) && section.list.length > 0)
}
