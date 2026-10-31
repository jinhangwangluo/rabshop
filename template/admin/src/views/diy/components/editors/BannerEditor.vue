<template>
  <div class="banner-editor">
    <div class="section-tip">建议：图片尺寸702*320px；鼠标拖拽可调整图片顺序</div>

    <el-form label-width="100px">
      <el-form-item label="显示指示器">
        <el-switch v-model="localConfig.show_indicator" />
      </el-form-item>
      <template v-if="localConfig.show_indicator">
        <el-form-item label="指示器样式">
          <el-radio-group v-model="localConfig.indicator_style">
            <el-radio label="dot">圆点</el-radio>
            <el-radio label="line">线条</el-radio>
            <el-radio label="number">数字</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="指示器位置">
          <el-radio-group v-model="localConfig.indicator_position">
            <el-radio label="left">居左</el-radio>
            <el-radio label="center">居中</el-radio>
            <el-radio label="right">居右</el-radio>
          </el-radio-group>
        </el-form-item>
      </template>
    </el-form>

    <draggable
      v-model="localConfig.items"
      item-key="id"
      handle=".drag-handle"
      class="banner-list"
      @end="emitConfig"
    >
      <template #item="{ element: item, index }">
        <div :key="item.id || index" class="banner-card">
          <span class="drag-handle"><LegacyIcon name="el-icon-rank" /></span>

          <div class="card-thumb">
            <!-- 有图片：显示预览 + 删除按钮 -->
            <template v-if="item.image">
              <img :src="item.image" alt="banner">
              <div class="thumb-delete" @click.stop="onImageChange(index, '')">
                <LegacyIcon name="el-icon-delete" />
              </div>
            </template>
            <!-- 无图片：显示上传组件 -->
            <template v-else>
              <div class="thumb-placeholder">
                <LegacyIcon name="el-icon-camera" />
              </div>
              <Upload
                :ref="'upload_' + index"
                :model-value="item.image"
                :limit="1"
                class="thumb-upload-overlay"
                @update:modelValue="val => onImageChange(index, val)"
              />
            </template>
          </div>

          <div class="card-fields">
            <div class="card-field-row">
              <span class="field-label">图片</span>
              <span class="field-hint">{{ item.image ? '点击删除图标重新上传' : '点击上传图片' }}</span>
            </div>
            <div class="card-field-row">
              <span class="field-label">标题</span>
              <el-input v-model="item.title" placeholder="选填，不超过4个字" class="field-input" />
            </div>
            <div class="card-field-row card-field-row--link">
              <span class="field-label">链接</span>
              <DiyLinkSelectField
                class="field-selector"
                :model-value="item.link_url"
                :link-id="item.link_id"
                :link-name="item.link_name"
                :link-type="item.link_type"
                :platform-type="item.platform_type"
                placeholder="请选择或输入链接地址"
                dialog-title="选择 Banner 链接"
                @update:modelValue="val => { item.link_url = val }"
                @select="row => handleSelectLink(index, row)"
                @clear="() => clearSelectedLink(index)"
              />
            </div>
          </div>

          <LegacyIcon name="el-icon-circle-close" class=" card-remove" @click="removeBanner(index)" />
        </div>
      </template>
    </draggable>

    <div class="add-btn-wrap">
      <el-button class="add-btn" @click="addBanner">
        <LegacyIcon name="el-icon-plus" /> 添加图片
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import draggable from 'vuedraggable'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'BannerEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const fromProp = ref(false)
const localConfig = reactive({
  autoplay: true,
  interval: 3000,
  show_indicator: true,
  indicator_style: 'dot',
  indicator_position: 'left',
  items: []
})

function normalizeBannerItem(item = {}, idx = 0) {
  return {
    id: item.id || idx,
    image: item.image || '',
    link_type: item.link_type || '',
    link_url: item.link_url || '',
    link_id: item.link_id || 0,
    link_name: item.link_name || '',
    platform_type: item.platform_type || '',
    title: item.title || ''
  }
}

function syncFromConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  fromProp.value = true
  if (val.styleConfig !== undefined) {
    const list = (val.swiperConfig && val.swiperConfig.list) || []
    const tabVal = val.docConfig ? val.docConfig.tabVal : 0
    const posTabVal = val.docPosition ? val.docPosition.tabVal : 0
    const posMap = { 0: 'left', 1: 'center', 2: 'right' }
    const metaItems = val.__editor_meta && Array.isArray(val.__editor_meta.items) ? val.__editor_meta.items : []
    Object.assign(localConfig, {
      autoplay: true,
      interval: 3000,
      show_indicator: tabVal !== 4,
      indicator_style: tabVal === 2 ? 'line' : tabVal === 3 ? 'number' : 'dot',
      indicator_position: posMap[posTabVal] || 'left',
      items: list.map((item, idx) => normalizeBannerItem({
        id: idx,
        image: item.img || '',
        link_type: item.link ? 'page' : '',
        link_url: item.link || '',
        title: item.info || '',
        ...(metaItems[idx] || {})
      }, idx))
    })
  } else {
    Object.assign(localConfig, {
      ...localConfig,
      ...val,
      items: Array.isArray(val.items) ? val.items.map((item, idx) => normalizeBannerItem(item, idx)) : []
    })
  }
  nextTick(() => {
    fromProp.value = false
  })
}

watch(() => props.config, syncFromConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, () => {
  if (fromProp.value) return
  emitConfig()
}, {
  deep: true
})

function addBanner() {
  localConfig.items.push(normalizeBannerItem({
    id: Date.now(),
    image: '',
    link_type: '',
    link_url: '',
    title: ''
  }))
}

function removeBanner(index) {
  localConfig.items.splice(index, 1)
}

function onImageChange(index, val) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    image: val
  }
  emitConfig()
}

function handleSelectLink(index, row) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    link_type: row.link_type || localConfig.items[index].link_type,
    link_url: row.link_url || '',
    link_id: row.id || 0,
    link_name: row.link_name || '',
    platform_type: row.platform_type || ''
  }
}

function clearSelectedLink(index) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    link_id: 0,
    link_name: '',
    platform_type: ''
  }
}

function emitConfig() {
  const val = localConfig
  const docStyleMap = { dot: 0, line: 2, number: 3 }
  const posMap = { left: 0, center: 1, right: 2 }
  const config = {
    styleConfig: { tabVal: 0 },
    styleTypeConfig: { tabVal: 0 },
    bottomBgColor: { color: [{ item: '' }] },
    filletImg: { type: 0, val: 6, valList: [{ val: 6 }, { val: 6 }, { val: 6 }, { val: 6 }] },
    docPosition: { tabVal: posMap[val.indicator_position] !== undefined ? posMap[val.indicator_position] : 0 },
    toneConfig: { tabVal: 1 },
    dotColor: { color: [{ item: '' }] },
    dotBgColor: { color: [{ item: '#DDDDDD' }] },
    imgConfig: { val: 0 },
    swiperConfig: {
      list: val.items.map(item => ({
        img: item.image || '',
        link: item.link_url || '',
        info: item.title || ''
      }))
    },
    docConfig: { tabVal: val.show_indicator ? (docStyleMap[val.indicator_style] || 0) : 4 },
    paddingConfig: { val: 0, isAll: true, valList: [{ val: 0 }, { val: 0 }, { val: 0 }, { val: 0 }] },
    marginConfig: { val: 0, isAll: true, valList: [{ val: 0 }, { val: 0 }, { val: 0 }, { val: 0 }] },
    __editor_meta: {
      items: val.items.map(item => ({
        link_type: item.link_type || '',
        link_url: item.link_url || '',
        link_id: item.link_id || 0,
        link_name: item.link_name || '',
        platform_type: item.platform_type || ''
      }))
    }
  }
  emit('change', config)
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.banner-editor {
  padding: 4px 0;

  .section-tip {
    font-size: 12px;
    color: #e6a23c;
    margin-bottom: 12px;
    line-height: 1.5;
  }

  .banner-list {
    margin-top: 12px;
  }

  .banner-card {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 28px 10px 10px;
    margin-bottom: 8px;
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);

    .drag-handle {
      color: #c0c4cc;
      cursor: move;
      font-size: 16px;
      flex-shrink: 0;
      padding-top: 22px;

      &:hover {
        color: #909399;
      }
    }

    .card-thumb {
      position: relative;
      width: 72px;
      height: 72px;
      flex-shrink: 0;
      border-radius: 4px;
      overflow: hidden;
      background: #f5f7fa;
      border: 1px dashed #dcdfe6;
      cursor: pointer;

      &:hover {
        border-color: var(--prev-color-primary);
      }

      img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }

      .thumb-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #c0c4cc;
        font-size: 24px;
      }

      .thumb-upload-overlay {
        position: absolute;
        inset: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;

        :deep(.el-upload) {
          width: 100%;
          height: 100%;
          display: block;
        }

        :deep(.el-upload-dragger),
        :deep(.el-upload--text) {
          width: 100%;
          height: 100%;
          display: block;
        }
      }

      .thumb-delete {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.45);
        color: #fff;
        font-size: 18px;
        opacity: 0;
        transition: opacity 0.2s;
        cursor: pointer;
      }

      &:hover .thumb-delete {
        opacity: 1;
      }
    }

    .card-fields {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 5px;
      min-width: 0;
    }

    .card-field-row {
      display: flex;
      align-items: center;
      gap: 6px;

      .field-label {
        font-size: 12px;
        color: #909399;
        width: 28px;
        flex-shrink: 0;
      }

      .field-hint {
        font-size: 11px;
        color: #c0c4cc;
      }

      .field-input,
      .field-selector {
        flex: 1;
        min-width: 0;
      }

      .field-link-icon {
        cursor: pointer;
        line-height: 32px;
        color: #909399;
      }
    }

    .card-field-row--link {
      align-items: flex-start;
    }

    .card-remove {
      position: absolute;
      top: 6px;
      right: 6px;
      font-size: 17px;
      color: #c0c4cc;
      cursor: pointer;
      transition: color 0.2s;
      z-index: 1;

      &:hover {
        color: #f56c6c;
      }
    }
  }

  .add-btn-wrap {
    margin-top: 4px;
  }

  .add-btn {
    width: 100%;
    border-style: dashed;
    color: var(--prev-color-primary);
    border-color: var(--dashboard-accent-light);
    background: var(--prev-color-primary-light-9);
    font-size: 13px;

    &:hover {
      background: var(--prev-color-primary-light-9);
      border-color: var(--prev-color-primary);
    }
  }
}
</style>
