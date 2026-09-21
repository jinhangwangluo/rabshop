import defaultSettings from '@/settings'
import { defineStore } from 'pinia'
import { reactive, toRefs } from 'vue'

const { showSettings, tagsView, fixedHeader, sidebarLogo } = defaultSettings

export const useSettingsStore = defineStore('settings', () => {
  const state = reactive({
    theme: '#1456f0',
    showSettings,
    tagsView,
    fixedHeader,
    sidebarLogo,
    isShowLink: true
  })

  function setIsShowLink(value) {
    state.isShowLink = value
  }

  function changeSetting({ key, value }) {
    if (Object.prototype.hasOwnProperty.call(state, key)) {
      state[key] = value
    }
  }

  return {
    ...toRefs(state),
    setIsShowLink,
    changeSetting
  }
})

export default useSettingsStore
