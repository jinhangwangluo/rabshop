import settings from '@/settings'
import { resolveRoutePath } from '@/utils/routePath'
import { isExternal } from '@/utils/validate'

const routePrefix = settings.prefix ? `/${settings.prefix}` : ''

export function getVisibleMenus(menus = []) {
  return menus.filter(item => item && !item.hidden)
}

export function resolveMenuPath(basePath, routePath) {
  if (isExternal(routePath)) {
    return routePath
  }
  if (isExternal(basePath)) {
    return basePath
  }

  return resolveRoutePath(basePath || '', routePath)
}

function stripQueryAndHash(menuPath) {
  if (!menuPath) {
    return ''
  }

  return String(menuPath).split(/[?#]/)[0]
}

export function normalizeMenuPath(menuPath) {
  if (!menuPath || isExternal(menuPath)) {
    return menuPath || ''
  }

  const rawPath = String(menuPath)
  if (!routePrefix || rawPath === routePrefix || rawPath.indexOf(`${routePrefix}/`) === 0) {
    return rawPath
  }

  return resolveRoutePath(routePrefix, rawPath.indexOf('/') === 0 ? rawPath.slice(1) : rawPath)
}

function getComparablePaths(menuPath) {
  const normalizedPath = normalizeMenuPath(menuPath)
  const strippedPath = stripQueryAndHash(normalizedPath)
  const rawStrippedPath = stripQueryAndHash(menuPath)
  return [menuPath, normalizedPath, strippedPath, rawStrippedPath].filter(Boolean)
}

export function isSameMenuPath(leftPath, rightPath) {
  const leftList = getComparablePaths(leftPath)
  const rightList = getComparablePaths(rightPath)

  return leftList.some(left => rightList.includes(left))
}

export function getActivePath(route) {
  if (!route) {
    return ''
  }
  if (route.meta && route.meta.activeMenu) {
    return route.meta.activeMenu
  }

  return route.path
}

export function findMatchedMenuPath(menus = [], activePath = '', parentPath = '') {
  const visibleMenus = getVisibleMenus(menus)

  for (let index = 0; index < visibleMenus.length; index++) {
    const item = visibleMenus[index]
    const currentPath = parentPath ? resolveMenuPath(parentPath, item.path) : (item.path || '')
    if (isSameMenuPath(currentPath, activePath) || isSameMenuPath(item.path, activePath)) {
      return currentPath
    }

    const childPath = findMatchedMenuPath(item.children || [], activePath, currentPath)
    if (childPath) {
      return childPath
    }
  }

  return ''
}
