<template>
  <div class="user-banner-editor">
    <div class="section-tip">建议：广告图片尺寸702*240px；用于个人中心必备服务下方运营广告</div>

    <el-form label-width="82px">
      <el-form-item label="广告图片">
        <div class="banner-upload">
          <template v-if="localConfig.image">
            <img :src="localConfig.image" alt="banner">
            <div class="banner-delete" @click="handleImageChange('')"><LegacyIcon name="el-icon-delete" /></div>
          </template>
          <template v-else>
            <div class="banner-placeholder"><LegacyIcon name="el-icon-camera" /><span>上传广告图</span></div>
            <Upload :model-value="localConfig.image" :limit="1" class="banner-upload-overlay" @update:modelValue="handleImageChange" />
          </template>
        </div>
      </el-form-item>
      <el-form-item label="广告标题">
        <el-input v-model="localConfig.title" placeholder="请输入广告标题" />
      </el-form-item>
      <el-form-item label="广告描述">
        <el-input v-model="localConfig.desc" placeholder="请输入广告描述" />
      </el-form-item>
      <el-form-item label="跳转链接">
        <DiyLinkSelectField
          :model-value="localConfig.link_url"
          :link-id="localConfig.link_id"
          :link-name="localConfig.link_name"
          :link-type="localConfig.link_type"
          :platform-type="localConfig.platform_type"
          placeholder="请选择或输入链接地址"
          dialog-title="选择广告链接"
          @update:modelValue="val => { localConfig.link_url = val }"
          @select="handleSelectLink"
          @clear="clearSelectedLink"
        />
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'UserBannerEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const syncingFromProp = ref(false)
const localConfig = reactive(normalizeConfig({}))

function normalizeConfig(config = {}) {
  return {
    image: config.image || '',
    title: config.title || '',
    desc: config.desc || '',
    link_type: config.link_type || 'page',
    link_url: config.link_url || config.url || '',
    link_id: config.link_id || 0,
    link_name: config.link_name || '',
    platform_type: config.platform_type || ''
  }
}

function applyConfig(val) {
  syncingFromProp.value = true
  Object.assign(localConfig, normalizeConfig(val))
  nextTick(() => {
    syncingFromProp.value = false
  })
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, () => {
  if (!syncingFromProp.value) emitConfig()
}, {
  deep: true
})

function handleImageChange(val) {
  localConfig.image = val
}

function handleSelectLink(row) {
  Object.assign(localConfig, {
    ...localConfig,
    link_type: row.link_type || localConfig.link_type,
    link_url: row.link_url || '',
    link_id: row.id || 0,
    link_name: row.link_name || '',
    platform_type: row.platform_type || ''
  })
}

function clearSelectedLink() {
  Object.assign(localConfig, {
    ...localConfig,
    link_id: 0,
    link_name: '',
    platform_type: ''
  })
}

function emitConfig() {
  emit('change', { ...localConfig })
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.user-banner-editor {
  padding: 4px 0;

  .section-tip { margin-bottom: 14px; color: #e6a23c; font-size: 12px; line-height: 1.5; }
  .banner-upload { position: relative; width: 100%; height: 128px; overflow: hidden; border: 1px dashed var(--dashboard-border); border-radius: 10px; background: var(--dashboard-surface-muted); cursor: pointer; }
  .banner-upload:hover { border-color: var(--prev-color-primary); }
  .banner-upload img { display: block; width: 100%; height: 100%; object-fit: cover; }
  .banner-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; color: var(--dashboard-text-muted); font-size: 13px; }
  .banner-placeholder i { margin-bottom: 8px; font-size: 24px; }
  .banner-upload-overlay { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; }
  .banner-delete { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; color: #fff; background: rgba(0, 0, 0, 0.45); font-size: 22px; }
  .banner-upload:hover .banner-delete { display: flex; }
}
</style>
