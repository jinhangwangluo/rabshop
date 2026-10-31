<template>
  <div
    :class="classObj"
    class="app-wrapper"
  >
    <div v-if="device==='mobile'&&sidebar.opened" class="drawer-bg" @click="handleClickOutside" />
    <Sidebar class="sidebar-container" />
    <div
      :class="{'hasTagsView': needTagsView && !hideHeader, 'no-header': hideHeader}"
      class="main-container"
    >
      <div v-if="!hideHeader" :class="{'fixed-header':fixedHeader}">
        <Navbar />
        <tags-view v-if="needTagsView" />
      </div>
      <AppMain />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { AppMain, Navbar, Sidebar, TagsView } from './components'
import { useAppStore } from '@/store/modules/app'
import { useSettingsStore } from '@/store/modules/settings'

defineOptions({
  name: 'Layout'
})

const route = useRoute()
const appStore = useAppStore()
const settingsStore = useSettingsStore()
const { sidebar, device } = storeToRefs(appStore)
const { tagsView: needTagsView, fixedHeader } = storeToRefs(settingsStore)

const hideHeader = computed(() => route.meta && route.meta.hideHeader === true)
const classObj = computed(() => ({
  hideSidebar: !sidebar.value.opened,
  openSidebar: sidebar.value.opened,
  withoutAnimation: sidebar.value.withoutAnimation,
  mobile: device.value === 'mobile'
}))

const WIDTH = 992
const isMobile = () => document.body.getBoundingClientRect().width - 1 < WIDTH
const resizeHandler = () => {
  if (document.hidden) {
    return
  }
  const mobile = isMobile()
  appStore.toggleDevice(mobile ? 'mobile' : 'desktop')
  if (mobile) {
    appStore.closeSideBar({ withoutAnimation: true })
  }
}

watch(() => route.fullPath, () => {
  if (device.value === 'mobile' && sidebar.value.opened) {
    appStore.closeSideBar({ withoutAnimation: false })
  }
})

onMounted(() => {
  window.addEventListener('resize', resizeHandler)
  if (isMobile()) {
    appStore.toggleDevice('mobile')
    appStore.closeSideBar({ withoutAnimation: true })
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeHandler)
})

function handleClickOutside() {
  appStore.closeSideBar({ withoutAnimation: false })
}
</script>

<style lang="scss" scoped>
  @import "@/styles/mixin.scss";
  @import "@/styles/variables.scss";

  .app-wrapper {
    position: relative;
    height: 100%;
    width: 100%;

    @include clearfix;

    &.mobile.openSidebar {
      position: fixed;
      top: 0;
    }
  }

  .drawer-bg {
    background: #000;
    opacity: 0.3;
    width: 100%;
    top: 0;
    height: 100%;
    position: absolute;
    z-index: 999;
  }

  .fixed-header {
    position: fixed;
    top: 0;
    right: 0;
    z-index: 9;
    width: calc(100% - #{$sideBarWidth});
    transition: width 0.28s;
  }

  .hideSidebar .fixed-header {
    width: calc(100% - 54px)
  }

  .mobile .fixed-header {
    width: 100%;
  }

  // 中控台全屏页面（hideHeader 模式）：取消 app-main 内边距
  .no-header {
    .app-main {
      padding: 0;
    }
  }
</style>
