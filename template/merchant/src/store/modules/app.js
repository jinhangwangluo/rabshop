import Cookies from 'js-cookie'
import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'

export const useAppStore = defineStore('app', () => {
  const sidebar = reactive({
    opened: Cookies.get('sidebarStatus') ? !!+Cookies.get('sidebarStatus') : true,
    withoutAnimation: false
  })
  const device = ref('desktop')
  const size = ref(Cookies.get('size') || 'medium')

  function toggleSideBar() {
    sidebar.opened = !sidebar.opened
    sidebar.withoutAnimation = false
    if (sidebar.opened) {
      Cookies.set('sidebarStatus', 1)
    } else {
      Cookies.set('sidebarStatus', 0)
    }
  }

  function closeSideBar({ withoutAnimation }) {
    Cookies.set('sidebarStatus', 0)
    sidebar.opened = false
    sidebar.withoutAnimation = withoutAnimation
  }

  function toggleDevice(value) {
    device.value = value
  }

  function setSize(value) {
    size.value = value
    Cookies.set('size', value)
  }

  return {
    sidebar,
    device,
    size,
    toggleSideBar,
    closeSideBar,
    toggleDevice,
    setSize
  }
})

export default useAppStore
