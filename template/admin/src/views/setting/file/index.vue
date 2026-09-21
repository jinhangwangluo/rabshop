<template>
  <div class="setting-file-page">
    <UploadPicture
      :is-page="true"
      :is-show="0"
      :show-type-tabs="true"
      :allowed-types="['image', 'video', 'audio', 'other']"
      :is-iframe="false"
    />
  </div>
</template>

<script setup>
import UploadPicture from '@/components/UploadPicture/index.vue'

defineOptions({
  name: 'SettingFile'
})
</script>

<style lang="scss" scoped>
.setting-file-page {
  height: calc(100vh - 110px);
  padding: 20px;
  background: var(--dashboard-surface);
}
</style>
