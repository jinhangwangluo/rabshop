import defaultSettings from '@/settings'
import { defineStore } from 'pinia'
import localStorage from '@/utils/store/localStorage'
import { applyInterfaceTheme } from '@/utils/interfaceTheme'
import { reactive, toRefs } from 'vue'

const { showSettings, tagsView, fixedHeader, sidebarLogo } = defaultSettings
const interfaceKey = 'rabbit-InterfaceSettings'
const defaultInterfaceSettings = {
  themeMode: 'light',
  menuStyle: 'handAccordion',
  themeColor: 'orange',
  fontSize: 'standard'
}
const supportedMenuStyles = ['handAccordion', 'topFlat']
const savedInterfaceSettings = localStorage.has(interfaceKey)
  ? (localStorage.get(interfaceKey) || {})
  : {}
function normalizeMenuStyle(menuStyle) {
  return supportedMenuStyles.includes(menuStyle) ? menuStyle : defaultInterfaceSettings.menuStyle
}

function normalizeInterfaceSettings(settings = {}) {
  return {
    ...defaultInterfaceSettings,
    ...settings,
    menuStyle: normalizeMenuStyle(settings.menuStyle)
  }
}

const interfaceSettings = normalizeInterfaceSettings(savedInterfaceSettings)

export const useSettingsStore = defineStore('settings', () => {
  const state = reactive({
    theme: '#f34100',
    showSettings: showSettings,
    tagsView: tagsView,
    fixedHeader: fixedHeader,
    sidebarLogo: sidebarLogo,
    themeMode: interfaceSettings.themeMode,
    menuStyle: interfaceSettings.menuStyle,
    themeColor: interfaceSettings.themeColor,
    fontSize: interfaceSettings.fontSize
  })

  applyInterfaceTheme(interfaceSettings)

  function saveInterfaceSettings() {
    const data = {
      themeMode: state.themeMode,
      menuStyle: normalizeMenuStyle(state.menuStyle),
      themeColor: state.themeColor,
      fontSize: state.fontSize
    }

    localStorage.set(interfaceKey, data)
    applyInterfaceTheme(data)
  }

  function setInterfaceSettings(data) {
    const nextSettings = normalizeInterfaceSettings(data)

    Object.keys(defaultInterfaceSettings).forEach(key => {
      if (Object.prototype.hasOwnProperty.call(nextSettings, key)) {
        state[key] = nextSettings[key]
      }
    })
    saveInterfaceSettings()
  }

  function changeSetting({ key, value }) {
    // eslint-disable-next-line no-prototype-builtins
    if (Object.prototype.hasOwnProperty.call(state, key)) {
      state[key] = key === 'menuStyle' ? normalizeMenuStyle(value) : value
      if (Object.prototype.hasOwnProperty.call(defaultInterfaceSettings, key)) {
        saveInterfaceSettings()
      }
    }
  }

  function saveInterfaceSettingsAction(data) {
    setInterfaceSettings(data)
  }

  return {
    ...toRefs(state),
    setInterfaceSettings,
    changeSetting,
    saveInterfaceSettings: saveInterfaceSettingsAction
  }
})

export default useSettingsStore
