<template>
  <div
    :class="classObj"
    class="app-wrapper"
  >
    <div
      v-if="showSidebar && device==='mobile'&&sidebar.opened"
      class="drawer-bg"
      @click="handleClickOutside"
    />
    <Sidebar
      v-if="showSidebar"
      class="sidebar-container"
    />
    <template v-if="menuStyle === 'topFlat'">
      <div
        :class="{'top-flat-header--fixed': fixedHeader}"
        class="top-flat-header"
      >
        <navbar />
      </div>
      <div
        :class="{
          'top-flat-body--fixed-header': fixedHeader,
          'top-flat-body--collapse': !sidebar.opened
        }"
        class="top-flat-body"
      >
        <top-flat-menu class="top-flat-body__menu" />
        <div class="top-flat-body__content">
          <app-main />
        </div>
      </div>
    </template>
    <div
      v-else
      :class="{'hasTagsView': showTagsView, 'no-header': hideHeader}"
      class="main-container"
    >
      <div
        v-if="showSidebar && !hideHeader"
        :class="{'fixed-header':fixedHeader}"
      >
        <navbar />
        <tags-view v-if="showTagsView" />
      </div>
      <app-main />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { AppMain, Navbar, Sidebar, TagsView, TopFlatMenu } from './components'
import { useAppStore } from '@/store/modules/app'
import { useSettingsStore } from '@/store/modules/settings'

defineOptions({
  name: 'Layout'
})

const route = useRoute()
const appStore = useAppStore()
const settingsStore = useSettingsStore()
const { sidebar, device } = storeToRefs(appStore)
const { tagsView: needTagsView, fixedHeader, menuStyle } = storeToRefs(settingsStore)

const showSidebar = computed(() => menuStyle.value !== 'topFlat')
const hideHeader = computed(() => route.meta && route.meta.hideHeader === true)
const showTagsView = computed(() => needTagsView.value && !hideHeader.value && menuStyle.value !== 'topFlat')
const classObj = computed(() => ({
  hideSidebar: !sidebar.value.opened,
  openSidebar: sidebar.value.opened,
  withoutAnimation: sidebar.value.withoutAnimation,
  mobile: device.value === 'mobile',
  topFlatLayout: menuStyle.value === 'topFlat',
  handAccordionLayout: showSidebar.value
}))

watch([menuStyle, device], ([style, currentDevice]) => {
  if (style !== 'topFlat' && currentDevice !== 'mobile' && !sidebar.value.opened) {
    appStore.toggleSideBar()
  }
}, { immediate: true })

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

  .top-flat-header {
    position: relative;
    z-index: 10;
    width: 100%;
    height: 58px;
    flex-shrink: 0;
  }

  .top-flat-header--fixed {
    position: fixed;
    top: 0;
    right: 0;
    left: 0;
  }

  .top-flat-body {
    display: flex;
    align-items: stretch;
    box-sizing: border-box;
    min-height: calc(100vh - 58px);
    background: var(--ds-background);
  }

  .top-flat-body--fixed-header {
    padding-top: 58px;
    min-height: 100vh;
  }

  .top-flat-body__menu {
    flex: 0 0 260px;
  }

  .top-flat-body--collapse .top-flat-body__menu {
    flex: 0 0 58px;
    flex-basis: 58px;
    max-width: 58px;
  }

  .top-flat-body__content {
    flex: 1;
    min-width: 0;
  }

  .mobile .fixed-header {
    width: 100%;
  }

  .mobile.topFlatLayout {
    .top-flat-body {
      display: block;
    }

    .top-flat-body__menu {
      width: 100%;
      min-height: auto;
    }

    .top-flat-body--collapse {
      display: flex;
      align-items: stretch;

      .top-flat-body__menu {
        flex: 0 0 58px;
        width: 58px;
        min-height: calc(100vh - 58px);
        max-width: 58px;
      }
    }
  }
</style>
