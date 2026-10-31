<template>
  <div class="navbar">
    <router-link
      v-if="menuStyle === 'topFlat'"
      class="navbar-brand"
      to="/"
    >
      <img :src="logo" class="navbar-brand__logo">
      <span class="navbar-brand__title">{{ title }}</span>
    </router-link>

    <hamburger
      v-if="menuStyle !== 'topFlat'"
      id="hamburger-container"
      :is-active="sidebar.opened"
      class="hamburger-container"
      @toggleClick="toggleSideBar"
    />

    <app-breadcrumb
      v-if="menuStyle !== 'topFlat'"
      id="breadcrumb-container"
      class="breadcrumb-container"
    />

    <nav
      v-if="menuStyle === 'topFlat'"
      ref="topRootNavRef"
      class="top-root-nav"
      :class="{ 'top-root-nav--dragging': isRootNavDragging }"
      @pointerdown="handleRootNavPointerDown"
      @pointermove="handleRootNavPointerMove"
      @pointerup="handleRootNavPointerEnd"
      @pointercancel="handleRootNavPointerEnd"
    >
      <button
        v-for="item in visibleRootMenus"
        :key="item.id || item.path"
        type="button"
        class="top-root-nav__item"
        :class="{ 'top-root-nav__item--active': isRootActive(item) }"
        @click="handleRootClick(item, $event)"
      >
        {{ item.title }}
      </button>
    </nav>

    <div class="right-menu">
      <template v-if="device!=='mobile'">

        <div class="layout-navbars-breadcrumb-user-icon" @click="refresh">
          <Refresh
            class="refresh"
            :class="animation ? 'element' : ''"
          />
        </div>

        <search
          id="header-search"
          class="right-menu-item"
        />

        <error-log class="errLog-container right-menu-item hover-effect" />

        <screenfull
          id="screenfull"
          class="right-menu-item hover-effect"
        />
      </template>

      <backend-message-center />

      <el-tooltip
        v-if="aiImageGeneratePinned"
        content="生成AI图片"
        placement="bottom"
      >
        <button
          type="button"
          class="ai-image-entry"
          @click="openAiImageGenerate"
        >
          <MagicStick />
        </button>
      </el-tooltip>

      <el-dropdown
        class="avatar-container right-menu-item hover-effect"
        trigger="click"
      >
        <div class="avatar-wrapper">
          <img
            v-if="adminInfo.avatar"
            :src="adminInfo.avatar"
            class="user-avatar"
          >
          <span
            v-else
            class="user-avatar user-avatar--placeholder"
          >
            <UserFilled />
          </span>
          <span class="username">{{ adminInfo.username || '管理员' }}</span>
          <CaretBottom class="navbar-avatar-arrow" />
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <router-link :to="{name:'admin_user'}">
              <el-dropdown-item>个人信息</el-dropdown-item>
            </router-link>
            <el-dropdown-item @click="openInterfaceSettings">
              样式切换
            </el-dropdown-item>
            <el-dropdown-item
              divided
              @click="logout"
            >
              <span style="display:block;">退出登录</span>
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>

    <interface-settings-dialog v-model:visible="interfaceSettingsVisible" />
    <ai-image-generate-dialog v-model:visible="aiImageGenerateVisibleValue" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { CaretBottom, MagicStick, Refresh, UserFilled } from '@element-plus/icons-vue'
import AppBreadcrumb from '@/components/Breadcrumb'
import Hamburger from '@/components/Hamburger'
import ErrorLog from '@/components/ErrorLog'
import Screenfull from '@/components/Screenfull'
import Search from '@/components/HeaderSearch'
import BackendMessageCenter from '@/components/BackendMessageCenter'
import InterfaceSettingsDialog from './InterfaceSettingsDialog'
import AiImageGenerateDialog from '@/components/UploadPicture/AiImageGenerateDialog.vue'
import settings from '@/settings'
import {
  findFirstNavigable,
  getActivePath,
  getVisibleMenus,
  hasActiveMenu
} from '@/utils/menuTree'
import logo from '@/assets/images/logo.png'
import { useAiImageGenerateStore } from '@/store/modules/aiImageGenerate'
import { useAppStore } from '@/store/modules/app'
import { useBackendMessageStore } from '@/store/modules/backendMessage'
import { useSettingsStore } from '@/store/modules/settings'
import { useTagsViewStore } from '@/store/modules/tagsView'
import { useUserStore } from '@/store/modules/user'

defineOptions({
  name: 'Navbar'
})

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const settingsStore = useSettingsStore()
const userStore = useUserStore()
const backendMessageStore = useBackendMessageStore()
const aiImageGenerateStore = useAiImageGenerateStore()
const tagsViewStore = useTagsViewStore()
const { sidebar, device } = storeToRefs(appStore)
const { adminInfo, menus, appMenus } = storeToRefs(userStore)
const { menuStyle } = storeToRefs(settingsStore)
const { pinned: aiImageGeneratePinned, visible: aiImageGenerateVisible } = storeToRefs(aiImageGenerateStore)

const animation = ref(false)
const interfaceSettingsVisible = ref(false)
const topRootNavRef = ref(null)
const title = settings.title || '后台管理系统'
const menuList = computed(() => menus.value)
const visibleRootMenus = computed(() => getVisibleMenus(menuList.value))
const activePath = computed(() => getActivePath(route))
const rootNavState = reactive({
  isDown: false,
  startX: 0,
  startScrollLeft: 0,
  hasDragged: false
})
const isRootNavDragging = computed(() => rootNavState.isDown)
const aiImageGenerateVisibleValue = computed({
  get: () => aiImageGenerateVisible.value,
  set: value => aiImageGenerateStore.setVisible(value)
})

onMounted(() => {
  if (menus.value.length <= 0) {
    userStore.fetchMenus()
  }
  backendMessageStore.init()
})

onBeforeUnmount(() => {
  backendMessageStore.disconnect()
})

function toggleSideBar() {
  appStore.toggleSideBar()
}

async function logout() {
  await userStore.logout()
  router.push(`/login?redirect=${route.fullPath}`)
}

function openInterfaceSettings() {
  interfaceSettingsVisible.value = true
}

function isRootActive(item) {
  return hasActiveMenu(item, activePath.value)
}

function handleRootClick(item, event) {
  if (rootNavState.hasDragged) {
    rootNavState.hasDragged = false
    event?.preventDefault?.()
    event?.stopPropagation?.()
    return
  }

  const target = findFirstNavigable(item)
  if (target && target !== route.path) {
    router.push(target)
  }
}

function openAiImageGenerate() {
  aiImageGenerateStore.open()
}

function refresh() {
  animation.value = true
  // 通过 tagsView 刷新当前视图：重建页面组件但不做整棵布局树的分支切换
  tagsViewStore.refreshView(route)
  setTimeout(() => {
    animation.value = false
  }, 1000)
}

function handleRootNavPointerDown(event) {
  if (event.button !== undefined && event.button !== 0) {
    return
  }

  const rootNav = topRootNavRef.value
  if (!rootNav) {
    return
  }

  rootNavState.isDown = true
  rootNavState.hasDragged = false
  rootNavState.startX = event.clientX
  rootNavState.startScrollLeft = rootNav.scrollLeft
}

function handleRootNavPointerMove(event) {
  if (!rootNavState.isDown) {
    return
  }

  const rootNav = topRootNavRef.value
  if (!rootNav) {
    return
  }

  const deltaX = event.clientX - rootNavState.startX
  if (Math.abs(deltaX) > 8) {
    rootNavState.hasDragged = true
    rootNav.scrollLeft = rootNavState.startScrollLeft - deltaX
  }
}

function handleRootNavPointerEnd() {
  if (!rootNavState.isDown) {
    return
  }

  rootNavState.isDown = false
}
</script>

<style lang="scss" scoped>
.navbar {
  display: flex;
  align-items: center;
  height: 58px;
  overflow: hidden;
  position: relative;
  background: var(--dashboard-surface);
  border-bottom: 1px solid var(--dashboard-border);
  box-shadow: 0 6px 18px rgba(31, 41, 55, .05);

  .hamburger-container {
    line-height: 54px;
    height: 100%;
    width: 54px;
    float: left;
    cursor: pointer;
    transition: background .3s;
    -webkit-tap-highlight-color:transparent;

    &:hover {
      background: var(--dashboard-accent-soft)
    }
  }

  .navbar-brand {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
    width: 236px;
    height: 100%;
    padding: 0 22px 0 28px;
    color: var(--dashboard-text-primary);
  }

  .navbar-brand__logo {
    width: 34px;
    height: 34px;
    margin-right: 10px;
    object-fit: contain;
  }

  .navbar-brand__title {
    overflow: hidden;
    font-size: 18px;
    font-weight: 700;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  .breadcrumb-container {
    flex: 1;
    min-width: 0;
    float: left;
    line-height: 58px;
  }

  .top-root-nav {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    height: 100%;
    padding: 0 14px;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    cursor: grab;
    touch-action: pan-x;
    user-select: none;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .top-root-nav--dragging {
    cursor: grabbing;
  }

  .top-root-nav__item {
    position: relative;
    flex-shrink: 0;
    height: 100%;
    padding: 0 18px;
    font-size: 15px;
    font-weight: 600;
    color: var(--dashboard-text-secondary);
    cursor: pointer;
    background: transparent;
    border: 0;
    transition: color .2s;

    &::after {
      content: '';
      position: absolute;
      right: 18px;
      bottom: 0;
      left: 18px;
      height: 3px;
      background: transparent;
      border-radius: 3px 3px 0 0;
      transition: background .2s;
    }

    &:hover,
    &--active {
      color: var(--dashboard-accent);

      &::after {
        background: var(--dashboard-accent);
      }
    }
  }

  .username{
    max-width: 96px;
    overflow: hidden;
    font-size: 12px;
    white-space: nowrap;
    text-overflow: ellipsis;
  }

  .layout-navbars-breadcrumb-user-icon{
    text-align: center;
    align-items: center;
    display: flex;
    padding: 0 8px;
  }

  /* 定义旋转动画的关键帧 */
  @keyframes rotate {
    from {
      transform: rotate(0deg);
    }
    to {
      transform: rotate(360deg);
    }
  }

  /* 应用旋转动画到元素 */
  .element {
    animation: rotate 2s linear infinite;
  }

  .layout-navbars-breadcrumb-user-icon .refresh{
    cursor: pointer;
    width: 20px;
    height: 20px;
    color: var(--dashboard-text-secondary);
    font-weight: 800;
  }

  .errLog-container {
    display: inline-block;
    vertical-align: top;
  }

  .right-menu {
    display: flex;
    flex-shrink: 0;
    margin-left: auto;
    height: 100%;
    line-height: 58px;
    align-items: center;

    &:focus {
      outline: none;
    }

    .right-menu-item {
      display: inline-block;
      padding: 0 8px;
      height: 100%;
      font-size: 18px;
      color: var(--dashboard-text-secondary);
      vertical-align: text-bottom;

      &.hover-effect {
        cursor: pointer;
        transition: background .3s;

        &:hover {
          background: var(--dashboard-accent-soft)
        }
      }
    }

    .ai-image-entry {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      width: 34px;
      height: 34px;
      margin: 0 8px 0 0;
      padding: 0;
      font-size: 20px;
      color: var(--dashboard-text-secondary);
      cursor: pointer;
      background: transparent;
      border: 0;
      border-radius: 10px;
      transition: background .2s, color .2s;

      &:hover,
      &:focus {
        color: var(--dashboard-accent);
        background: var(--dashboard-accent-soft);
        outline: none;
      }

      :deep(svg) {
        width: 20px;
        height: 20px;
      }
    }

    .avatar-container {
      margin-right: 30px;

      .avatar-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding-right: 18px;
        position: relative;

        .user-avatar {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
          width: 40px;
          height: 40px;
          border-radius: 10px;
          object-fit: cover;
          cursor: pointer;
        }

        .user-avatar--placeholder {
          color: var(--ds-primary, #409eff);
          background: var(--ds-surface-container-highest, #ecf5ff);
          box-shadow: inset 0 0 0 1px var(--ds-outline, #dcdfe6);

          :deep(svg) {
            width: 18px;
            height: 18px;
          }
        }

        .navbar-avatar-arrow {
          cursor: pointer;
          position: absolute;
          top: 50%;
          right: 0;
          width: 12px;
          height: 12px;
          transform: translateY(-50%);
        }
      }
    }
  }
}
</style>
