import { onActivated, onBeforeUnmount, onDeactivated, onMounted, ref } from 'vue'
import { debounce } from '@/utils'

export function useChartResize(getChart) {
  const sidebarElm = ref(null)
  const resizeHandler = ref(null)

  function sidebarResizeHandler(event) {
    if (event.propertyName === 'width') {
      resizeHandler.value && resizeHandler.value()
    }
  }

  function initListener() {
    resizeHandler.value = debounce(() => {
      resize()
    }, 100)
    window.addEventListener('resize', resizeHandler.value)

    sidebarElm.value = document.getElementsByClassName('sidebar-container')[0]
    sidebarElm.value && sidebarElm.value.addEventListener('transitionend', sidebarResizeHandler)
  }

  function destroyListener() {
    window.removeEventListener('resize', resizeHandler.value)
    resizeHandler.value = null
    sidebarElm.value && sidebarElm.value.removeEventListener('transitionend', sidebarResizeHandler)
  }

  function resize() {
    const chart = getChart()
    chart && chart.resize()
  }

  onMounted(initListener)
  onActivated(() => {
    if (!resizeHandler.value) {
      initListener()
    }
    resize()
  })
  onBeforeUnmount(destroyListener)
  onDeactivated(destroyListener)

  return {
    resize
  }
}

export default useChartResize
