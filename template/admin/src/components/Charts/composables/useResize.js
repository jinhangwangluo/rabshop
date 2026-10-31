import { onActivated, onBeforeUnmount, onDeactivated, ref } from 'vue'
import { debounce } from '@/utils'

export function useChartResize(chart) {
  const sidebarElm = ref(null)
  const resizeHandler = ref(null)

  function resize() {
    chart.value && chart.value.resize()
  }

  function sidebarResizeHandler(event) {
    if (event.propertyName === 'width' && resizeHandler.value) {
      resizeHandler.value()
    }
  }

  function initListener() {
    resizeHandler.value = debounce(resize, 100)
    window.addEventListener('resize', resizeHandler.value)

    sidebarElm.value = document.getElementsByClassName('sidebar-container')[0]
    if (sidebarElm.value) {
      sidebarElm.value.addEventListener('transitionend', sidebarResizeHandler)
    }
  }

  function destroyListener() {
    if (resizeHandler.value) {
      window.removeEventListener('resize', resizeHandler.value)
      resizeHandler.value = null
    }

    if (sidebarElm.value) {
      sidebarElm.value.removeEventListener('transitionend', sidebarResizeHandler)
      sidebarElm.value = null
    }
  }

  onActivated(() => {
    if (!resizeHandler.value) {
      initListener()
    }

    resize()
  })

  onBeforeUnmount(destroyListener)
  onDeactivated(destroyListener)

  return {
    initListener,
    resize
  }
}
