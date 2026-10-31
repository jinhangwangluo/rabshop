<template>
  <el-breadcrumb class="app-breadcrumb" separator="/">
    <transition-group name="breadcrumb">
      <el-breadcrumb-item v-for="(item,index) in levelList" :key="item.path">
        <span v-if="item.redirect==='noRedirect'||index==levelList.length-1" class="no-redirect">{{ item.meta.title }}</span>
        <a v-else @click.prevent="handleLink(item)">{{ item.meta.title }}</a>
      </el-breadcrumb-item>
    </transition-group>
  </el-breadcrumb>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import pathToRegexp from 'path-to-regexp'
import settings from '@/settings'

defineOptions({
  name: 'AppBreadcrumb'
})

const route = useRoute()
const router = useRouter()
const levelList = ref([])

watch(() => route.fullPath, () => {
  if (!route.path.startsWith('/redirect/')) {
    getBreadcrumb()
  }
}, { immediate: true })

function isDashboard(currentRoute) {
  const name = currentRoute && currentRoute.name
  return !!name && name.trim().toLocaleLowerCase() === 'dashboard'
}

function getBreadcrumb() {
  let matched = route.matched.filter(item => item.meta && item.meta.title)
  const first = matched[0]

  if (!isDashboard(first)) {
    matched = [{
      path: settings.prefix ? '/' + settings.prefix + '/home' : '/home',
      meta: { title: '主页' }
    }].concat(matched)
  }

  levelList.value = matched.filter(item => item.meta && item.meta.title && item.meta.breadcrumb !== false)
}

function pathCompile(currentPath) {
  const toPath = pathToRegexp.compile(currentPath)
  return toPath(route.params)
}

function handleLink(item) {
  if (item.redirect) {
    router.push(item.redirect)
    return
  }
  router.push(pathCompile(item.path))
}
</script>

<style lang="scss" scoped>
.app-breadcrumb.el-breadcrumb {
  display: inline-block;
  font-size: 14px;
  line-height: 50px;
  margin-left: 8px;

  .no-redirect {
    color: var(--text-secondary);
    cursor: text;
  }
}
</style>
