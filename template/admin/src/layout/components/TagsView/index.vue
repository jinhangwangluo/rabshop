<template>
  <div id="tags-view-container" ref="containerRef" class="tags-view-container">
    <ScrollPane ref="scrollPaneRef" class="tags-view-wrapper" @scroll="handleScroll">
      <router-link
        v-for="tag in visitedViews"
        :key="tag.path"
        v-slot="{ navigate }"
        :to="{ path: tag.path, query: tag.query, fullPath: tag.fullPath }"
        custom
        class="tags-view-item"
      >
        <span
          :ref="el => setTagRef(el, tag)"
          :class="isActive(tag) ? 'active' : ''"
          class="tags-view-item"
          @click="navigate"
          @click.middle="!isAffix(tag) ? closeSelectedTag(tag) : ''"
          @contextmenu.prevent="openMenu(tag, $event)"
        >
          {{ tag.title }}
          <LegacyIcon
            v-if="!isAffix(tag)"
            name="el-icon-close"
            @click.prevent.stop="closeSelectedTag(tag)"
          />
        </span>
      </router-link>
    </ScrollPane>
    <ul v-show="visible" :style="{left:left+'px',top:top+'px'}" class="contextmenu">
      <li @click="refreshSelectedTag(selectedTag)">Refresh</li>
      <li v-if="!isAffix(selectedTag)" @click="closeSelectedTag(selectedTag)">Close</li>
      <li @click="closeOthersTags">Close Others</li>
      <li @click="closeAllTags(selectedTag)">Close All</li>
    </ul>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import ScrollPane from './ScrollPane'
import { resolveRoutePath } from '@/utils/routePath'
import { usePermissionStore } from '@/store/modules/permission'
import { useTagsViewStore } from '@/store/modules/tagsView'

defineOptions({
  name: 'TagsView'
})

const route = useRoute()
const router = useRouter()
const tagsViewStore = useTagsViewStore()
const permissionStore = usePermissionStore()
const { visitedViews } = storeToRefs(tagsViewStore)
const { routes } = storeToRefs(permissionStore)
const scrollPaneRef = ref(null)
const containerRef = ref(null)
const tagRefs = ref([])
const visible = ref(false)
const top = ref(0)
const left = ref(0)
const selectedTag = ref({})
const affixTags = ref([])

watch(() => route.fullPath, () => {
  addTags()
  moveToCurrentTag()
})

watch(visible, (value) => {
  if (value) {
    document.body.addEventListener('click', closeMenu)
  } else {
    document.body.removeEventListener('click', closeMenu)
  }
})

onMounted(() => {
  initTags()
  addTags()
})

onBeforeUnmount(() => {
  document.body.removeEventListener('click', closeMenu)
})

function setTagRef(element, tag) {
  if (element) {
    element.__tagView = tag
    if (!tagRefs.value.includes(element)) {
      tagRefs.value.push(element)
    }
  }
}

function isActive(tag) {
  return tag.path === route.path
}

function isAffix(tag) {
  return tag.meta && tag.meta.affix
}

function filterAffixTags(routeList, basePath = '/') {
  let tags = []
  routeList.forEach(item => {
    if (item.meta && item.meta.affix) {
      const tagPath = resolveRoutePath(basePath, item.path)
      tags.push({
        fullPath: tagPath,
        path: tagPath,
        name: item.name,
        meta: { ...item.meta }
      })
    }
    if (item.children) {
      const tempTags = filterAffixTags(item.children, item.path)
      if (tempTags.length >= 1) {
        tags = [...tags, ...tempTags]
      }
    }
  })
  return tags
}

function initTags() {
  affixTags.value = filterAffixTags(routes.value)
  affixTags.value.forEach(tag => {
    if (tag.name) {
      tagsViewStore.addVisitedView(tag)
    }
  })
}

function addTags() {
  if (route.name) {
    tagsViewStore.addView(route)
  }
}

function moveToCurrentTag() {
  nextTick(() => {
    const currentTag = tagRefs.value.find(tag => tag.__tagView && tag.__tagView.path === route.path)
    if (!currentTag) {
      return
    }
    scrollPaneRef.value.moveToTarget(currentTag, tagRefs.value)
    if (currentTag.__tagView.fullPath !== route.fullPath) {
      tagsViewStore.updateVisitedView(route)
    }
  })
}

async function refreshSelectedTag(view) {
  await tagsViewStore.delCachedView(view)
  await nextTick()
  router.replace({ path: '/redirect' + view.fullPath })
}

async function closeSelectedTag(view) {
  const { visitedViews: nextVisitedViews } = await tagsViewStore.delView(view)
  if (isActive(view)) {
    toLastView(nextVisitedViews, view)
  }
}

async function closeOthersTags() {
  await router.push(selectedTag.value)
  await tagsViewStore.delOthersViews(selectedTag.value)
  moveToCurrentTag()
}

async function closeAllTags(view) {
  const { visitedViews: nextVisitedViews } = await tagsViewStore.delAllViews()
  if (!affixTags.value.some(tag => tag.path === view.path)) {
    toLastView(nextVisitedViews, view)
  }
}

function toLastView(nextVisitedViews, view) {
  const latestView = nextVisitedViews.slice(-1)[0]
  if (latestView) {
    router.push(latestView.fullPath)
  } else if (view.name === 'Dashboard') {
    router.replace({ path: '/redirect' + view.fullPath })
  } else {
    router.push('/')
  }
}

function openMenu(tag, event) {
  const menuMinWidth = 105
  const container = containerRef.value
  if (!container) {
    return
  }
  const offsetLeft = container.getBoundingClientRect().left
  const maxLeft = container.offsetWidth - menuMinWidth
  const nextLeft = event.clientX - offsetLeft + 15
  left.value = nextLeft > maxLeft ? maxLeft : nextLeft
  top.value = event.clientY
  visible.value = true
  selectedTag.value = tag
}

function closeMenu() {
  visible.value = false
}

function handleScroll() {
  closeMenu()
}
</script>

<style lang="scss" scoped>
.tags-view-container {
  //height: 34px;
  width: 100%;
  //background: #fff;
  background: var(--ds-background);
  padding-top: 5px;
  //border-bottom: 1px solid #d8dce5;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, .12), 0 0 3px 0 rgba(0, 0, 0, .04);
  .tags-view-wrapper {
    .tags-view-item {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      position: relative;
      cursor: pointer;
      box-sizing: border-box;
      height: 26px;
      line-height: 1;
      vertical-align: middle;
      border: none;
      box-shadow: inset 0 0 0 1px var(--ds-outline-ghost);
      color: var(--ds-on-surface-variant);
      background: var(--ds-surface-card);
      padding: 0 8px;
      font-size: 12px;
      margin-left: 5px;
      margin-top: 4px;
      border-radius: 5px;
      &:first-of-type {
        margin-left: 15px;
      }
      &:last-of-type {
        margin-right: 15px;
      }
      &.active {
        background-color: var(--ds-primary);
        color: var(--ds-on-primary);
        box-shadow: inset 0 0 0 1px var(--ds-primary);
        &::before {
          content: '';
          background: var(--ds-on-primary);
          display: inline-block;
          width: 8px;
          height: 8px;
          border-radius: 50%;
          flex: 0 0 8px;
          position: relative;
          margin-right: 4px;
        }
      }
    }
  }
  .contextmenu {
    margin: 0;
    background: var(--ds-surface-card);
    z-index: 3000;
    position: absolute;
    list-style-type: none;
    padding: 5px 0;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 400;
    color: var(--ds-on-surface);
    box-shadow: 2px 2px 3px 0 rgba(0, 0, 0, .3);
    li {
      margin: 0;
      padding: 7px 16px;
      cursor: pointer;
      &:hover {
        background: var(--ds-surface-low);
      }
    }
  }
}
</style>

<style lang="scss">
//reset element css of el-icon-close
.tags-view-wrapper {
  .tags-view-item {
    .el-icon-close {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 16px;
      height: 16px;
      flex: 0 0 16px;
      margin-left: 4px;
      vertical-align: middle;
      line-height: 1;
      border-radius: 50%;
      text-align: center;
      transition: all .3s cubic-bezier(.645, .045, .355, 1);
      transform-origin: 100% 50%;
      &:before {
        display: none;
      }

      > svg {
        width: 12px;
        height: 12px;
      }
      &:hover {
        background-color: var(--ds-outline-ghost);
        color: var(--ds-on-primary);
      }
    }
  }
}
</style>
