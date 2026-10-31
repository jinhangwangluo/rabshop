<template>
  <div class="sidebar-logo-container" :class="{'collapse':collapse}">
    <transition name="sidebarLogoFade">
      <router-link v-if="collapse" key="collapse" class="sidebar-logo-link" to="/">
        <img v-if="sidebarLogo" :src="logo" class="sidebar-logo">
        <h1 v-else class="sidebar-title">{{ title }} </h1>
      </router-link>
      <router-link v-else key="expand" class="sidebar-logo-link" to="/">
        <img v-if="sidebarLogo" :src="logo" class="sidebar-logo">
      </router-link>
    </transition>
  </div>
</template>

<script setup>
import logo from '@/assets/images/logo.png'

defineOptions({
  name: 'SidebarLogo'
})

defineProps({
  collapse: {
    type: Boolean,
    required: true
  },
  sidebarLogo: {
    type: Boolean,
    required: true
  }
})

const title = '乖乖兔多商户商城系统'
</script>

<style lang="scss" scoped>
.sidebarLogoFade-enter-active {
  transition: opacity 1.5s;
}

.sidebarLogoFade-enter,
.sidebarLogoFade-leave-to {
  opacity: 0;
}

.sidebar-logo-container {
  position: relative;
  width: 100%;
  height: 50px;
  line-height: 50px;
  background: var(--ds-surface-card);
  border-bottom: 1px solid var(--ds-outline-ghost);
  text-align: center;
  overflow: hidden;

  & .sidebar-logo-link {
    height: 100%;
    width: 100%;
    display: inline-flex;
    justify-content: center;
    align-items: center;

    & .sidebar-logo {
      height: 50px;
      vertical-align: middle;
      margin-right: 12px;
      border-radius: 5px;
    }

    & .sidebar-title {
      display: inline-block;
      margin: 0;
      color: var(--text-title);
      font-weight: 600;
      line-height: 50px;
      font-size: 14px;
      font-family: Avenir, Helvetica Neue, Arial, Helvetica, sans-serif;
      vertical-align: middle;
    }
  }

  &.collapse {
    .sidebar-logo {
      margin-right: 0px;
    }
  }
}
</style>
