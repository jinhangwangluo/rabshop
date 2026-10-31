<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="diy-placeholder">
    <DiyPage
      v-if="false"
      :components="[]"
      :global-config="{}"
    />
  </view>
</template>

<script setup>
import DiyPage from '../components/diy/DiyPage.vue'

defineOptions({
  name: 'DiyPlaceholderPage'
})
</script>

<style lang="scss" scoped>
.diy-placeholder {
  min-height: 100%;
  background: #f9f9f9;
}
</style>
