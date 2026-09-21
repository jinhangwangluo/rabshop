<template>
  <section class="app-main">
    <router-view v-slot="{ Component }">
      <transition name="fade-transform" mode="out-in">
        <keep-alive v-if="isShowLink" :include="cachedViews">
          <component :is="Component" :key="key" />
        </keep-alive>
        <component :is="Component" v-else :key="key" />
      </transition>
    </router-view>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { useTagsViewStore } from '@/store/modules/tagsView'
import { useSettingsStore } from '@/store/modules/settings'

defineOptions({
  name: 'AppMain'
})

const route = useRoute()
const tagsViewStore = useTagsViewStore()
const settingsStore = useSettingsStore()
const { cachedViews } = storeToRefs(tagsViewStore)
const { isShowLink } = storeToRefs(settingsStore)
const key = computed(() => route.path)
</script>

<style lang="scss" scoped>
.app-main {
  /* 50= navbar  50  */
  min-height: calc(100vh - 50px);
  width: 100%;
  position: relative;
  //overflow: hidden;
  padding: 16px;
  background: var(--ds-background);
}

.fixed-header+.app-main {
  padding-top: 58px;
}

.hasTagsView {
  .app-main {
    /* 84 = navbar + tags-view = 50 + 34 */
    min-height: calc(100vh - 84px);
  }

  .fixed-header+.app-main {
    padding-top: 92px;
  }
}
</style>

<style lang="scss">
// fix css style bug in open el-dialog
.el-popup-parent--hidden {
  .fixed-header {
    padding-right: 15px;
  }
}
</style>
