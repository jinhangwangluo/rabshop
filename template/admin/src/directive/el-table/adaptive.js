/**
 * How to use
 * <el-table height="100px" v-el-height-adaptive-table="{bottomOffset: 30}">...</el-table>
 * el-table height is must be set
 * bottomOffset: 30(default)   // The height of the table from the bottom of the page.
 */

const getTableInstance = (el, vnode) => {
  if (vnode && vnode.component && vnode.component.proxy) {
    return vnode.component.proxy
  }

  const parentComponent = el.__vueParentComponent
  return parentComponent && parentComponent.proxy
}

const doResize = (el, binding, vnode) => {
  const $table = getTableInstance(el, vnode)
  if (!$table) return

  const { value } = binding
  const tableHeight = $table.height || ($table.$props && $table.$props.height)

  if (!tableHeight) {
    throw new Error(`el-$table must set the height. Such as height='100px'`)
  }
  const bottomOffset = (value && value.bottomOffset) || 30

  if (!$table.layout || typeof $table.layout.setHeight !== 'function') return

  const height = window.innerHeight - el.getBoundingClientRect().top - bottomOffset
  $table.layout.setHeight(height)
  if (typeof $table.doLayout === 'function') {
    $table.doLayout()
  }
}

export default {
  beforeMount(el, binding, vnode) {
    const resizeListener = () => {
      doResize(el, binding, vnode)
    }
    el.__elTableResizeListener = resizeListener
    window.addEventListener('resize', resizeListener)

    if (typeof ResizeObserver !== 'undefined') {
      const observer = new ResizeObserver(resizeListener)
      observer.observe(document.body)
      el.__elTableResizeObserver = observer
    }
  },
  mounted(el, binding, vnode) {
    doResize(el, binding, vnode)
  },
  updated(el, binding, vnode) {
    doResize(el, binding, vnode)
  },
  unmounted(el) {
    if (el.__elTableResizeListener) {
      window.removeEventListener('resize', el.__elTableResizeListener)
    }
    if (el.__elTableResizeObserver) {
      el.__elTableResizeObserver.disconnect()
    }
    delete el.__elTableResizeListener
    delete el.__elTableResizeObserver
  }
}
