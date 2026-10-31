<template>
  <div :class="{'has-logo':showLogo}">
    <logo
      v-if="showLogo"
      :sidebar-logo="sidebarLogo"
      :collapse="isCollapse"
    />
    <el-scrollbar wrap-class="scrollbar-wrapper">
      <el-menu
        :default-active="activeMenu"
        :collapse="isCollapse"
        :background-color="menuBackgroundColor"
        :text-color="menuTextColor"
        :unique-opened="false"
        :active-text-color="menuActiveTextColor"
        :collapse-transition="false"
        mode="vertical"
      >
        <sidebar-item
          v-for="route in activeMenus"
          :key="route.id || route.path"
          :item="route"
          :base-path="route.path"
        />
      </el-menu>
    </el-scrollbar>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import Logo from './Logo'
import SidebarItem from './SidebarItem'
import { findMatchedMenuPath, getActivePath, normalizeMenuPath } from '@/utils/menuTree'
import { useAppStore } from '@/store/modules/app'
import { useSettingsStore } from '@/store/modules/settings'
import { useUserStore } from '@/store/modules/user'

defineOptions({
  name: 'Sidebar'
})

const route = useRoute()
const appStore = useAppStore()
const settingsStore = useSettingsStore()
const userStore = useUserStore()
const { sidebar } = storeToRefs(appStore)
const { sidebarLogo } = storeToRefs(settingsStore)
const { menus, appMenus } = storeToRefs(userStore)

const appId = computed(() => route.query.appId)
const activeMenus = computed(() => appId.value && appMenus.value.length ? appMenus.value : menus.value)
const activeMenu = computed(() => {
  const activePath = getActivePath(route)
  const matchedPath = findMatchedMenuPath(activeMenus.value, activePath)

  if (matchedPath) {
    return matchedPath
  }

  if (appId.value) {
    return route.path + '?appId=' + appId.value
  }

  return normalizeMenuPath(activePath)
})
const showLogo = computed(() => !!sidebarLogo.value)
const menuBackgroundColor = 'var(--dashboard-menu-bg)'
const menuTextColor = 'var(--dashboard-menu-text)'
const menuActiveTextColor = 'var(--dashboard-accent)'
const isCollapse = computed(() => !sidebar.value.opened)

onMounted(() => {
  if (route.query.appId && !appMenus.value.length) {
    userStore.getAppMenus(route.query.appId)
  }
  if (menus.value.length <= 0) {
    userStore.fetchMenus()
  }
})
</script>
