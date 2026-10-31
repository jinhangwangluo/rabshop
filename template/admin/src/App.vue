<template>
  <div id="app">
    <router-view />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { applyInterfaceTheme, bindSystemThemeListener } from '@/utils/interfaceTheme'
import { useSettingsStore } from '@/store/modules/settings'
import { pageSlotBridge } from '@/utils/pageSlots'

defineOptions({
  name: 'App'
})

const settingsStore = useSettingsStore()
const { themeMode, menuStyle, themeColor, fontSize } = storeToRefs(settingsStore)
const interfaceSettings = computed(() => ({
  themeMode: themeMode.value,
  menuStyle: menuStyle.value,
  themeColor: themeColor.value,
  fontSize: fontSize.value
}))

watch(interfaceSettings, (value) => {
  applyInterfaceTheme(value)
}, {
  deep: true,
  immediate: true
})

let unbindSystemTheme = null

onMounted(() => {
  pageSlotBridge.start()
  unbindSystemTheme = bindSystemThemeListener(() => {
    if (themeMode.value === 'system') {
      applyInterfaceTheme(interfaceSettings.value)
    }
  })
})

onBeforeUnmount(() => {
  pageSlotBridge.dispose()
  if (unbindSystemTheme) {
    unbindSystemTheme()
  }
})
</script>
