<template>
  <div class="user-order-menu-editor">
    <div class="section-tip">默认使用移动端订单图片，点击图标区域可选择图片替换；标题可直接编辑。</div>

    <el-form label-width="90px">
      <el-form-item label="模块标题">
        <el-input v-model="localConfig.title" placeholder="我的订单" />
      </el-form-item>
    </el-form>

    <div class="menu-list">
      <div v-for="(item, index) in localConfig.items" :key="item.key || index" class="menu-card">
        <div class="card-thumb" @click="handleReplaceImage(index)">
          <template v-if="item.image">
            <img :src="item.image" alt="order">
            <div class="thumb-replace">
              <LegacyIcon name="el-icon-refresh" />
              <span>替换</span>
            </div>
          </template>
          <template v-else-if="item.icon">
            <div class="thumb-preview thumb-preview--icon">
              <LegacyIcon :name="resolvePreviewIconClass(item.icon)" />
            </div>
            <div class="thumb-replace">
              <LegacyIcon name="el-icon-refresh" />
              <span>替换</span>
            </div>
          </template>
          <template v-else>
            <div class="thumb-placeholder">
              <LegacyIcon name="el-icon-picture-outline" />
            </div>
            <div class="thumb-replace">
              <LegacyIcon name="el-icon-refresh" />
              <span>替换</span>
            </div>
          </template>
        </div>

        <div class="card-fields">
          <div class="card-field-row">
            <span class="field-label">标题</span>
            <el-input v-model="item.name" placeholder="请输入标题" class="field-input" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import orderNoAddressImage from '@/assets/images/diy/user-center/order_no_adderss.png'
import orderNoAssessImage from '@/assets/images/diy/user-center/order_no_assess.png'
import orderNoPayImage from '@/assets/images/diy/user-center/order_no_pay.png'
import orderNoRefundImage from '@/assets/images/diy/user-center/order_no_refund.png'
import orderNoShipImage from '@/assets/images/diy/user-center/order_no_ship.png'

const DEFAULT_ORDER_ITEMS = [
  { key: 'pending-payment', name: '待付款', icon: 'icon-moreandroid', image: orderNoPayImage },
  { key: 'pending-shipment', name: '待发货', icon: 'icon-send', image: orderNoShipImage },
  { key: 'pending-receipt', name: '待收货', icon: 'icon-order', image: orderNoAddressImage },
  { key: 'pending-review', name: '待评价', icon: 'icon-evaluate', image: orderNoAssessImage },
  { key: 'after-sale', name: '售后', icon: 'icon-refund', image: orderNoRefundImage }
]

function isIconClass(value) {
  const text = String(value || '').trim()
  if (!text) {
    return false
  }

  return /^(el-icon-|icon[-_])[A-Za-z0-9_-]+$/.test(text)
}

defineOptions({
  name: 'UserOrderMenuEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const { proxy } = getCurrentInstance()
const syncingFromProp = ref(false)
const lastEmit = ref('')
const localConfig = reactive({
  title: '我的订单',
  items: DEFAULT_ORDER_ITEMS.map(item => ({ ...item }))
})

function normalizeConfig(config = {}) {
  const next = config && typeof config === 'object' ? config : {}
  const items = Array.isArray(next.items) ? next.items : []
  const itemsByKey = {}

  items.forEach((item) => {
    if (item && item.key) {
      itemsByKey[item.key] = item
    }
  })

  return {
    title: next.title || '我的订单',
    items: DEFAULT_ORDER_ITEMS.map((defaultItem, index) => {
      const current = itemsByKey[defaultItem.key] || items[index] || {}
      const rawIcon = String(current.icon || '').trim()
      const rawImage = String(current.image || '').trim()
      const icon = rawIcon || (isIconClass(rawImage) ? rawImage : '')
      const image = isIconClass(rawImage) ? defaultItem.image : (rawImage || defaultItem.image)
      const displayIcon = icon || (!image ? defaultItem.icon : '')

      return {
        key: defaultItem.key,
        name: current.name || current.text || current.title || defaultItem.name,
        icon: displayIcon,
        image
      }
    })
  }
}

function resolvePreviewIconClass(icon) {
  const value = String(icon || '').trim()
  if (!value) {
    return ''
  }

  return value.indexOf('el-icon-') === 0 ? value : ['iconfont', value]
}

function handleReplaceImage(index) {
  proxy.$imgModal((image) => {
    if (!image) {
      return
    }

    const selected = Array.isArray(image) ? image[0] : image
    const imagePath = selected.path || selected.att_dir || selected.satt_dir || ''
    if (imagePath) {
      onImageChange(index, imagePath)
    }
  }, 1)
}

function onImageChange(index, val) {
  localConfig.items[index] = {
    ...localConfig.items[index],
    image: val,
    icon: val ? '' : localConfig.items[index].icon
  }
}

function emitConfig() {
  const cfg = {
    title: localConfig.title,
    items: localConfig.items.map(item => ({
      key: item.key,
      name: item.name,
      icon: item.icon,
      image: item.image
    }))
  }
  lastEmit.value = JSON.stringify(cfg)
  emit('change', cfg)
}

function getConfig() {
  return normalizeConfig(localConfig)
}

function applyConfig(val) {
  const next = normalizeConfig(val)
  if (lastEmit.value && JSON.stringify(next) === lastEmit.value) return
  syncingFromProp.value = true
  Object.assign(localConfig, next)
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

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.user-order-menu-editor {
  padding: 4px 0;

  .section-tip {
    margin-bottom: 14px;
    color: #e6a23c;
    font-size: 12px;
    line-height: 1.5;
  }

  .menu-list {
    margin-top: 12px;
  }

  .menu-card {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 14px;
    background: var(--dashboard-surface-muted);
    border: 1px solid var(--dashboard-border);
    border-radius: 6px;
    box-shadow: none;
    overflow: hidden;
  }

  .card-thumb {
    position: relative;
    width: 52px;
    height: 52px;
    margin-right: 12px;
    flex-shrink: 0;
    border-radius: 4px;
    overflow: hidden;
    background: var(--dashboard-surface);
    border: 1px dashed var(--dashboard-border);
    cursor: pointer;
  }

  .thumb-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: var(--dashboard-accent);
    background: linear-gradient(180deg, rgba(200, 161, 90, 0.08), rgba(200, 161, 90, 0.02));
    font-size: 22px;
  }

  .card-thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .thumb-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: var(--dashboard-text-muted);
    font-size: 20px;
  }

  .thumb-replace {
    position: absolute;
    inset: 0;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: rgba(0, 0, 0, 0.45);
    z-index: 2;
    font-size: 12px;
    line-height: 1.2;
  }

  .thumb-replace i {
    margin-bottom: 4px;
    font-size: 16px;
  }

  .card-thumb:hover .thumb-replace {
    display: flex;
  }

  .card-fields {
    flex: 1;
    min-width: 0;
  }

  .card-field-row {
    display: flex;
    align-items: center;
  }

  .field-label {
    width: 54px;
    color: var(--dashboard-text-secondary);
    font-size: 12px;
    flex-shrink: 0;
  }

  .field-input {
    flex: 1;
    min-width: 0;
  }
}
</style>
