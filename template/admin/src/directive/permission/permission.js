import { useUserStore } from '@/store/modules/user'

function getRoles(binding) {
  const pinia = binding.instance && binding.instance.$pinia
  const userStore = useUserStore(pinia)
  const adminInfo = userStore.adminInfo || {}
  return Array.isArray(adminInfo.roles) ? adminInfo.roles : []
}

function checkPermission(el, binding) {
  const { value } = binding
  const roles = getRoles(binding)

  if (value && value instanceof Array) {
    if (value.length > 0) {
      const permissionRoles = value

      const hasPermission = roles.some(role => {
        return permissionRoles.includes(role)
      })

      if (!hasPermission) {
        el.parentNode && el.parentNode.removeChild(el)
      }
    }
  } else {
    throw new Error(`need roles! Like v-permission="['admin','editor']"`)
  }
}

export default {
  mounted(el, binding) {
    checkPermission(el, binding)
  },
  updated(el, binding) {
    checkPermission(el, binding)
  }
}
