<template>
  <div :class="{'show':show}" class="header-search">
    <Search class="search-icon" @click.stop="click" />
    <el-select
      ref="headerSearchSelect"
      v-model="search"
      :remote-method="querySearch"
      filterable
      default-first-option
      remote
      placeholder="Search"
      class="header-search-select"
      @change="change"
    >
      <el-option v-for="item in options" :key="item.path" :value="item" :label="item.title.join(' > ')" />
    </el-select>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { Search } from '@element-plus/icons-vue'
// fuse is a lightweight fuzzy-search module
// make search results more in line with expectations
import Fuse from 'fuse.js'
import { resolveRoutePath } from '@/utils/routePath'
import { usePermissionStore } from '@/store/modules/permission'

defineOptions({
  name: 'HeaderSearch'
})

const router = useRouter()
const permissionStore = usePermissionStore()
const { routes } = storeToRefs(permissionStore)
const headerSearchSelect = ref(null)
const search = ref('')
const options = ref([])
const searchPool = ref([])
const show = ref(false)
let fuse

watch(routes, (value) => {
  searchPool.value = generateRoutes(value)
  initFuse(searchPool.value)
}, { deep: true })

watch(show, (value) => {
  if (value) {
    document.body.addEventListener('click', close)
  } else {
    document.body.removeEventListener('click', close)
  }
})

onMounted(() => {
  searchPool.value = generateRoutes(routes.value)
  initFuse(searchPool.value)
})

onBeforeUnmount(() => {
  document.body.removeEventListener('click', close)
})

function click() {
  show.value = !show.value
  if (show.value && headerSearchSelect.value) {
    headerSearchSelect.value.focus()
  }
}

function close() {
  if (headerSearchSelect.value) {
    headerSearchSelect.value.blur()
  }
  options.value = []
  show.value = false
}

function change(value) {
  router.push(value.path)
  search.value = ''
  options.value = []
  nextTick(() => {
    show.value = false
  })
}

function initFuse(list) {
  fuse = new Fuse(list, {
    shouldSort: true,
    threshold: 0.4,
    location: 0,
    distance: 100,
    maxPatternLength: 32,
    minMatchCharLength: 1,
    keys: [{
      name: 'title',
      weight: 0.7
    }, {
      name: 'path',
      weight: 0.3
    }]
  })
}

function generateRoutes(routeList, basePath = '/', prefixTitle = []) {
  let result = []

  for (const routeItem of routeList) {
    if (routeItem.hidden) {
      continue
    }

    const data = {
      path: resolveRoutePath(basePath, routeItem.path),
      title: [...prefixTitle]
    }

    if (routeItem.meta && routeItem.meta.title) {
      data.title = [...data.title, routeItem.meta.title]

      if (routeItem.redirect !== 'noRedirect') {
        result.push(data)
      }
    }

    if (routeItem.children) {
      const tempRoutes = generateRoutes(routeItem.children, data.path, data.title)
      if (tempRoutes.length >= 1) {
        result = [...result, ...tempRoutes]
      }
    }
  }
  return result
}

function querySearch(query) {
  if (query !== '' && fuse) {
    options.value = fuse.search(query)
  } else {
    options.value = []
  }
}
</script>

<style lang="scss" scoped>
.header-search {
  font-size: 0 !important;

  .search-icon {
    width: 18px;
    height: 18px;
    cursor: pointer;
    color: currentColor;
    vertical-align: middle;
    flex-shrink: 0;
  }

  .header-search-select {
    font-size: 18px;
    transition: width 0.2s;
    width: 0;
    overflow: hidden;
    background: transparent;
    border-radius: 0;
    display: inline-block;
    vertical-align: middle;

    :deep(.el-input__inner) {
      border-radius: 0;
      border: 0;
      padding-left: 0;
      padding-right: 0;
      box-shadow: none !important;
      border-bottom: 1px solid #d9d9d9;
      vertical-align: middle;
    }
  }

  &.show {
    .header-search-select {
      width: 210px;
      margin-left: 10px;
    }
  }
}
</style>
