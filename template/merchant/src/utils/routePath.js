import { isExternal } from '@/utils/validate'

export function resolveRoutePath(basePath = '', routePath = '') {
  if (routePath === null) {
    return ''
  }
  if (isExternal(routePath)) {
    return routePath
  }
  if (isExternal(basePath)) {
    return basePath
  }

  const base = String(basePath || '')
  const route = String(routePath || '')
  const pathname = route.startsWith('/') ? route : `${base}/${route}`
  const segments = []

  pathname.split('/').forEach(segment => {
    if (!segment || segment === '.') {
      return
    }
    if (segment === '..') {
      segments.pop()
      return
    }
    segments.push(segment)
  })

  return `/${segments.join('/')}`
}
