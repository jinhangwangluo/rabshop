<template>
  <div v-if="!item.hidden">
    <template v-if="hasOneShowingChild(item.children,item)">
      <sidebar-item
        v-if="hasShowingChildren(onlyOneChild.children)"
        :is-nest="isNest"
        :item="onlyOneChild"
        :base-path="resolvePath(onlyOneChild.path)"
      />
      <app-link v-else-if="onlyOneChild.title" :to="resolvePath(onlyOneChild.path)">
        <el-menu-item :index="resolvePath(onlyOneChild.path)" :class="{'submenu-title-noDropdown':!isNest}">
          <item :icon="onlyOneChild.icon" :title="onlyOneChild.title" />
        </el-menu-item>
      </app-link>
    </template>

    <el-sub-menu v-else ref="subMenu" :index="resolvePath(item.path)" popper-append-to-body>
      <template #title>
        <item v-if="item.title" :icon="item.icon" :title="item.title" />
      </template>
      <sidebar-item
        v-for="child in item.children"
        :key="child.id"
        :is-nest="true"
        :item="child"
        :base-path="resolvePath(child.path)"
        class="nest-menu"
      />
    </el-sub-menu>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { resolveRoutePath } from '@/utils/routePath'
import Item from './Item'
import AppLink from './Link'

defineOptions({
  name: 'SidebarItem'
})

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  isNest: {
    type: Boolean,
    default: false
  },
  basePath: {
    type: String,
    default: ''
  }
})

const onlyOneChild = ref(null)

function hasShowingChildren(children = []) {
  return children.some(item => !item.hidden)
}

function hasOneShowingChild(children = [], parent) {
  const showingChildren = children.filter(item => {
    if (item.hidden) {
      return false
    }
    onlyOneChild.value = item
    return true
  })

  if (showingChildren.length === 1) {
    return true
  }

  if (showingChildren.length === 0) {
    onlyOneChild.value = { ...parent, path: '', noShowingChildren: true }
    return true
  }

  return false
}

function resolvePath(routePath) {
  return resolveRoutePath(props.basePath, routePath)
}
</script>
