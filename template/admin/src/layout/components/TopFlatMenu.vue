<template>
  <aside
    v-if="shouldShowMenu"
    class="top-flat-menu"
    :class="{ 'top-flat-menu--collapse': !sidebar.opened }"
  >
    <div class="top-flat-menu__header">
      <div
        v-if="sidebar.opened"
        class="top-flat-menu__title"
      >
        {{ activeRootTitle }}
      </div>
      <hamburger
        :is-active="sidebar.opened"
        class="top-flat-menu__collapse"
        @toggleClick="toggleSideBar"
      />
    </div>
    <div v-if="activeRoot && sidebar.opened" class="top-flat-menu__inner">
      <div
        v-for="group in groups"
        :key="group.id"
        class="top-flat-menu__group"
      >
        <div
          v-if="group.showTitle"
          class="top-flat-menu__group-title"
        >
          <LegacyIcon v-if="group.icon" :name="group.icon" />
          <span>{{ group.title }}</span>
        </div>
        <div class="top-flat-menu__items">
          <a
            v-for="item in group.items"
            :key="item.id"
            class="top-flat-menu__link"
            :href="item.path"
            @click.prevent="handleMenuClick(item)"
          >
            <span
              class="top-flat-menu__item"
              :class="{ 'top-flat-menu__item--active': isActive(item) }"
            >
              <LegacyIcon v-if="item.icon" :name="item.icon" />
              <span>{{ item.title }}</span>
            </span>
          </a>
        </div>
      </div>
    </div>
    <div v-else-if="activeRoot && !sidebar.opened" class="top-flat-menu__collapsed-list">
      <el-tooltip
        v-for="item in collapsedItems"
        :key="item.id"
        :content="item.title"
        :open-delay="120"
        placement="right"
        effect="dark"
      >
        <a
          class="top-flat-menu__collapsed-link"
          :class="{ 'top-flat-menu__collapsed-link--active': isActive(item) }"
          :href="item.path"
          :aria-label="item.title"
          @click.prevent="handleMenuClick(item)"
        >
          <LegacyIcon :name="resolveMenuIcon(item)" />
        </a>
      </el-tooltip>
    </div>
    <div v-else-if="sidebar.opened" class="top-flat-menu__empty">暂无菜单</div>
  </aside>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import Hamburger from '@/components/Hamburger'
import {
  buildTopFlatGroups,
  findActiveRootMenu,
  getActivePath,
  isSameMenuPath
} from '@/utils/menuTree'
import { isExternal } from '@/utils/validate'
import { useAppStore } from '@/store/modules/app'
import { useSettingsStore } from '@/store/modules/settings'
import { useUserStore } from '@/store/modules/user'

defineOptions({
  name: 'TopFlatMenu'
})

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const settingsStore = useSettingsStore()
const userStore = useUserStore()
const { sidebar } = storeToRefs(appStore)
const { menus, appMenus } = storeToRefs(userStore)

const menuList = computed(() => route.query.appId && appMenus.value.length ? appMenus.value : menus.value)
const activePath = computed(() => getActivePath(route))
const activeRoot = computed(() => findActiveRootMenu(menuList.value, activePath.value))
const activeRootTitle = computed(() => {
  if (!activeRoot.value) {
    return '菜单'
  }
  return activeRoot.value.title || (activeRoot.value.meta && activeRoot.value.meta.title) || '菜单'
})
const groups = computed(() => buildTopFlatGroups(activeRoot.value))
const collapsedItems = computed(() => groups.value.reduce((list, group) => {
  const items = Array.isArray(group.items) ? group.items : []
  return list.concat(items)
}, []))
const menuItemCount = computed(() => groups.value.reduce((total, group) => {
  return total + (Array.isArray(group.items) ? group.items.length : 0)
}, 0))
const shouldShowMenu = computed(() => menuItemCount.value > 1)

onMounted(() => {
  if (menus.value.length <= 0) {
    userStore.fetchMenus()
  }
})

function toggleSideBar() {
  appStore.toggleSideBar()
}

function isActive(item) {
  return isSameMenuPath(item.path, activePath.value)
}

function resolveMenuIcon(item) {
  return item && item.icon ? item.icon : 'el-icon-menu'
}

function resolveMenuRoute(item) {
  if (!item || !item.path || isExternal(item.path)) {
    return null
  }
  return router.resolve(item.path)
}

function isCurrentMenuTarget(resolvedRoute) {
  if (!resolvedRoute) {
    return false
  }
  if (resolvedRoute.fullPath === route.fullPath) {
    return true
  }

  const targetHasQuery = Object.keys(resolvedRoute.query || {}).length > 0
  return !targetHasQuery && resolvedRoute.path === route.path
}

function refreshCurrentView() {
  settingsStore.changeSetting({ key: 'isShowLink', value: false })
  setTimeout(() => {
    settingsStore.changeSetting({ key: 'isShowLink', value: true })
  }, 100)
}

function handleMenuClick(item) {
  if (!item || !item.path) {
    return
  }

  if (isExternal(item.path)) {
    window.open(item.path, '_blank', 'noopener')
    return
  }

  const resolvedRoute = resolveMenuRoute(item)
  if (!resolvedRoute) {
    return
  }

  if (isCurrentMenuTarget(resolvedRoute)) {
    refreshCurrentView()
    return
  }

  router.push(item.path).catch(() => {})
}
</script>

<style lang="scss" scoped>
.top-flat-menu {
  box-sizing: border-box;
  width: 260px;
  min-height: calc(100vh - 58px);
  padding: 0 16px 28px;
  overflow-y: auto;
  background: var(--dashboard-surface);
  border-right: 1px solid var(--dashboard-border);
  box-shadow: 6px 0 24px rgba(31, 41, 55, .03);
  transition: width .28s, padding .28s;
  border-radius: 0;
}

.top-flat-menu--collapse {
  width: 58px;
  min-width: 58px;
  max-width: 58px;
  padding-right: 0;
  padding-left: 0;
  overflow-x: hidden;
  overflow-y: auto;
}

.top-flat-menu__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 58px;
  margin-bottom: 12px;
}

.top-flat-menu__title {
  min-width: 0;
  overflow: hidden;
  font-size: 18px;
  font-weight: 700;
  color: var(--dashboard-text-primary);
  white-space: nowrap;
  text-overflow: ellipsis;
}

.top-flat-menu__collapse {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  padding: 0 !important;
  margin-left: 10px;
  color: var(--dashboard-text-secondary);
  line-height: 1;
  cursor: pointer;
  transition: background .2s, color .2s;
  border-radius: 6px;

  &:hover {
    color: var(--dashboard-accent);
    background: var(--dashboard-accent-soft);
  }
}

.top-flat-menu--collapse .top-flat-menu__header {
  justify-content: center;
  margin-bottom: 0;
}

.top-flat-menu--collapse .top-flat-menu__collapse {
  width: 58px;
  height: 58px;
  margin-left: 0;
  border-radius: 0;
}

.top-flat-menu__collapsed-list {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 8px 6px 16px;
}

.top-flat-menu__collapsed-link {
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  width: 46px;
  height: 42px;
  font-size: 18px;
  color: var(--dashboard-menu-sub-text);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 8px;
  transition: all .2s;

  &:hover,
  &--active {
    color: var(--dashboard-accent);
    background: var(--dashboard-accent-soft);
    border-color: var(--dashboard-accent-outline);
  }
}

.top-flat-menu__inner {
  display: block;
}

.top-flat-menu__group {
  padding: 0 0 12px;
  margin-bottom: 12px;
  background: var(--dashboard-surface);
  border-bottom: 1px solid var(--dashboard-border);
  box-shadow: none;

  &:last-child {
    margin-bottom: 0;
    border-bottom: 0;
  }
}

.top-flat-menu__items {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
}

.top-flat-menu__group-title {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--dashboard-text-primary);

  i {
    margin-right: 5px;
    color: var(--dashboard-accent);
  }
}

.top-flat-menu__link {
  flex: 0 0 calc((100% - 7px) / 2);
  max-width: calc((100% - 7px) / 2);
}

.top-flat-menu__item {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  width: 100%;
  height: 34px;
  padding: 0 8px;
  overflow: hidden;
  font-size: 13px;
  font-weight: 600;
  color: var(--dashboard-menu-sub-text);
  white-space: nowrap;
  text-overflow: ellipsis;
  background: var(--dashboard-surface-muted);
  border: 1px solid transparent;
  transition: all .2s;
  border-radius: 5px;

  i {
    margin-right: 5px;
  }

  &:hover,
  &--active {
    color: var(--dashboard-accent);
    background: var(--dashboard-accent-soft);
    border-color: var(--dashboard-accent-outline);
  }
}

.top-flat-menu__empty {
  padding: 28px 12px;
  color: var(--dashboard-text-muted);
  text-align: center;
  background: var(--dashboard-surface-muted);
}
</style>
