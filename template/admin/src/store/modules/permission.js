import { constantRoutes } from '@/router'
import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Use meta.role to determine if the current user has permission
 * @param roles
 * @param route
 */
function hasPermission(roles, route) {
  if (route.meta && route.meta.roles) {
    return roles.some(role => route.meta.roles.includes(role))
  } else {
    return true
  }
}

/**
 * Filter asynchronous routing tables by recursion
 * @param routes asyncRoutes
 * @param roles
 */
export function filterAsyncRoutes(routes, roles) {
  const res = []

  routes.forEach(route => {
    const tmp = { ...route }
    if (hasPermission(roles, tmp)) {
      if (tmp.children) {
        tmp.children = filterAsyncRoutes(tmp.children, roles)
      }
      res.push(tmp)
    }
  })

  return res
}

export const usePermissionStore = defineStore('permission', () => {
  const routes = ref([])
  const addRoutes = ref([])

  function setRoutes(newRoutes) {
    addRoutes.value = newRoutes
    routes.value = constantRoutes.concat(newRoutes)
  }

  function generateRoutes(roles) {
    let accessedRoutes
    if (roles.includes('admin')) {
      accessedRoutes = []
    } else {
      accessedRoutes = filterAsyncRoutes([], roles)
    }
    setRoutes(accessedRoutes)
    return Promise.resolve(accessedRoutes)
  }

  return {
    routes,
    addRoutes,
    setRoutes,
    generateRoutes
  }
})

export default usePermissionStore
