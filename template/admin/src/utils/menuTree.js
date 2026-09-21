import settings from '@/settings'
import { resolveRoutePath } from '@/utils/routePath'
import { isExternal } from '@/utils/validate'

const adminPrefix = settings.prefix ? `/${settings.prefix}` : ''

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
  if (!adminPrefix || rawPath === adminPrefix || rawPath.indexOf(`${adminPrefix}/`) === 0) {
    return rawPath
  }

  return resolveRoutePath(adminPrefix, rawPath.indexOf('/') === 0 ? rawPath.slice(1) : rawPath)
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

export function hasActiveMenu(item, activePath, parentPath = '') {
  if (!item || item.hidden) {
    return false
  }

  const currentPath = parentPath ? resolveMenuPath(parentPath, item.path) : (item.path || '')
  if (isSameMenuPath(currentPath, activePath) || isSameMenuPath(item.path, activePath)) {
    return true
  }

  return getVisibleMenus(item.children || []).some(child => {
    return hasActiveMenu(child, activePath, currentPath)
  })
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

export function flattenChildren(children = [], parentPath = '') {
  const list = []

  getVisibleMenus(children).forEach(child => {
    const itemPath = resolveMenuPath(parentPath, child.path)
    if (child.title) {
      list.push({
        id: child.id || itemPath,
        title: child.title,
        icon: child.icon || '',
        path: itemPath,
        raw: child
      })
    }

    list.push(...flattenChildren(child.children || [], itemPath))
  })

  return list
}

export function findFirstNavigable(item, parentPath = '') {
  if (!item || item.hidden) {
    return ''
  }

  const currentPath = parentPath ? resolveMenuPath(parentPath, item.path) : (item.path || '')
  const children = getVisibleMenus(item.children || [])
  if (!children.length && currentPath) {
    return currentPath
  }

  for (let index = 0; index < children.length; index++) {
    const childPath = findFirstNavigable(children[index], currentPath)
    if (childPath) {
      return childPath
    }
  }

  return currentPath
}

export function buildTopFlatGroups(rootMenu) {
  if (!rootMenu) {
    return []
  }

  const rootPath = rootMenu.path || ''
  const children = getVisibleMenus(rootMenu.children || [])

  if (!children.length) {
    return [{
      id: rootMenu.id || rootPath,
      title: rootMenu.title || '菜单',
      showTitle: false,
      items: rootMenu.title ? [{
        id: rootMenu.id || rootPath,
        title: rootMenu.title,
        icon: rootMenu.icon || '',
        path: rootPath,
        raw: rootMenu
      }] : []
    }]
  }

  const hasNestedChildren = children.some(child => getVisibleMenus(child.children || []).length > 0)
  if (!hasNestedChildren) {
    return [{
      id: rootMenu.id || rootPath,
      title: rootMenu.title || '菜单',
      icon: rootMenu.icon || '',
      showTitle: false,
      items: children.map(child => {
        const childPath = resolveMenuPath(rootPath, child.path)
        return {
          id: child.id || childPath,
          title: child.title,
          icon: child.icon || '',
          path: childPath,
          raw: child
        }
      }).filter(item => item.title)
    }].filter(group => group.items.length)
  }

  const groups = []
  let flatGroup = null
  const flushFlatGroup = () => {
    if (flatGroup && flatGroup.items.length) {
      groups.push(flatGroup)
    }
    flatGroup = null
  }

  children.forEach(child => {
    const childPath = resolveMenuPath(rootPath, child.path)
    const nestedChildren = getVisibleMenus(child.children || [])

    if (!nestedChildren.length) {
      if (!flatGroup) {
        flatGroup = {
          id: `${rootMenu.id || rootPath}-flat`,
          title: rootMenu.title || '菜单',
          icon: rootMenu.icon || '',
          showTitle: false,
          items: []
        }
      }

      if (child.title) {
        flatGroup.items.push({
          id: child.id || childPath,
          title: child.title,
          icon: child.icon || '',
          path: childPath,
          raw: child
        })
      }
      return
    }

    flushFlatGroup()

    const nestedItems = flattenChildren(nestedChildren, childPath)
    if (nestedItems.length) {
      groups.push({
        id: child.id || childPath,
        title: child.title || '菜单',
        icon: child.icon || '',
        path: childPath,
        showTitle: true,
        items: nestedItems
      })
    }
  })

  flushFlatGroup()

  return groups.filter(group => group.items.length)
}

export function findActiveRootMenu(menus = [], activePath = '') {
  const visibleMenus = getVisibleMenus(menus)
  return visibleMenus.find(item => hasActiveMenu(item, activePath)) || visibleMenus[0] || null
}
