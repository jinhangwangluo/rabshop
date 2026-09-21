<template>
  <view v-if="visible" class="product-sku-popup" :class="{ 'product-sku-popup--cart': scene === 'cart' }" @click="handleClose">
    <view class="product-sku-popup__sheet" @click.stop>
      <scroll-view scroll-y class="product-sku-popup__content">
        <view class="product-sku-popup__header">
          <image class="product-sku-popup__thumb" :src="popupThumb" mode="aspectFill" @click.stop="openSelectedSpecPreview"></image>
          <view class="product-sku-popup__summary">
            <view class="sku-price">
              <text class="sku-price__symbol">¥</text>
              <text class="sku-price__integer">{{ priceInteger }}</text>
              <text class="sku-price__decimal">.{{ priceDecimal }}</text>
              <text class="sku-price__tag">{{ priceLabel }}</text>
              <text v-if="product.originalPriceText" class="sku-price__origin">¥{{ product.originalPriceText }}</text>
            </view>
            <text class="product-sku-popup__stock">{{ stockSummary }} | 已选 “{{ selectedText }}”</text>
          </view>
        </view>

        <view v-for="group in displayGroups" :key="group.key" class="sku-section">
          <text class="sku-section__title">{{ group.title }}</text>
          <view class="sku-options">
            <view
              v-for="item in group.options"
              :key="item.value"
              class="sku-option"
              :class="{
                'sku-option--small': group.small,
                'sku-option--image': item.image,
                'sku-option--active': item.selected,
                'sku-option--disabled': item.disabled
              }"
              @click="handleOptionClick(group, item)"
            >
              <image
                v-if="item.image"
                class="sku-option__image"
                :src="item.image"
                mode="aspectFill"
                @click.stop="handleOptionImageClick(group, item)"
              ></image>
              <text>{{ item.label }}</text>
            </view>
          </view>
        </view>

        <view v-if="showQuantity" class="quantity-row">
          <text class="quantity-row__title">数量</text>
          <view class="stepper">
            <view class="stepper__button" :class="{ 'stepper__button--disabled': quantityLocked || quantity <= minQuantity }" @click="changeQuantity(-1)">
              <text>−</text>
            </view>
            <text class="stepper__value">{{ quantity }}</text>
            <view class="stepper__button" :class="{ 'stepper__button--disabled': quantityLocked || quantity >= maxQuantity }" @click="changeQuantity(1)">
              <text>＋</text>
            </view>
          </view>
        </view>
      </scroll-view>

      <view class="product-sku-popup__footer">
        <view
          v-for="action in displayActions"
          :key="action.type"
          class="sku-submit"
          :class="[action.className, { 'sku-submit--disabled': submitLoading || submitDisabled }]"
          @click="handleSubmit(action.type)"
        >
          <text>{{ action.text }}</text>
        </view>
      </view>
    </view>

    <product-image-preview
      :visible="showSpecPreview"
      :images="specPreviewImages"
      :current="specPreviewIndex"
      counter-separator="/"
      :show-close="false"
      :close-on-tap="true"
      @close="closeSpecPreview"
      @change="changeSpecPreview"
    />
  </view>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import ProductImagePreview from '@/components/product-image-preview/index.vue'

defineOptions({
  name: 'ProductSkuPopup'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  product: {
    type: Object,
    default: () => ({})
  },
  groups: {
    type: Array,
    default: () => []
  },
  selected: {
    type: Object,
    default: () => ({})
  },
  quantity: {
    type: Number,
    default: 1
  },
  maxQuantity: {
    type: Number,
    default: 1
  },
  minQuantity: { type: Number, default: 1 },
  priceLabel: { type: String, default: '到手价' },
  stockText: { type: String, default: '' },
  submitDisabled: { type: Boolean, default: false },
  closeDisabled: { type: Boolean, default: false },
  submitLoading: {
    type: Boolean,
    default: false
  },
  showQuantity: {
    type: Boolean,
    default: true
  },
  scene: {
    type: String,
    default: ''
  },
  actions: {
    type: Array,
    default: () => [
      {
        type: 'cart',
        text: '加入购物车',
        className: 'sku-submit--cart'
      },
      {
        type: 'buy',
        text: '立即购买',
        className: 'sku-submit--buy'
      }
    ]
  }
})

const emit = defineEmits(['close', 'select', 'quantity-change', 'submit'])

const showSpecPreview = ref(false)
const specPreviewIndex = ref(0)
const stockSummary = computed(() => props.stockText || `库存 ${props.product.stock}件`)
const quantityLocked = computed(() => props.submitLoading || Number(props.maxQuantity) <= 0 || Number(props.maxQuantity) < Number(props.minQuantity))

const priceText = computed(() => String(props.product.priceText || '0.00'))
const priceInteger = computed(() => priceText.value.split('.')[0])
const priceDecimal = computed(() => (priceText.value.split('.')[1] || '00').padEnd(2, '0').slice(0, 2))
const displayGroups = computed(() => {
  return props.groups.map((group) => ({
    key: String(group.key || ''),
    title: String(group.title || ''),
    small: Boolean(group.small),
    options: normalizeOptions(group.key, group.options)
  })).filter((group) => group.key && group.title && group.options.length)
})
const skuList = computed(() => {
  const skuData = props.product && typeof props.product.sku_data === 'object' ? props.product.sku_data : {}
  return Array.isArray(skuData.sku_list) ? skuData.sku_list : []
})
const selectedText = computed(() => {
  return displayGroups.value.map((group) => {
    const item = group.options.find((option) => option.selected)
    return item ? item.label : ''
  }).filter(Boolean).join(' ')
})
const imageSpecGroup = computed(() => displayGroups.value.find((group) => group.options.some((item) => item.image)) || null)
const selectedSku = computed(() => resolveSelectedSku())
const selectedImageSpec = computed(() => {
  const group = imageSpecGroup.value
  if (!group) {
    return null
  }

  return group.options.find((item) => item.selected && item.image) || null
})
const popupThumb = computed(() => {
  if (skuList.value.length > 0) {
    const skuImage = selectedSku.value && String(selectedSku.value.pics || '').trim()
    return skuImage || String(props.product.thumb || '').trim()
  }

  const specImage = selectedImageSpec.value && selectedImageSpec.value.image
  return specImage || String(props.product.thumb || '').trim()
})
const specPreviewImages = computed(() => {
  if (skuList.value.length > 0) {
    return skuList.value.map((item, index) => {
      const id = String(item.id || item.uniqid || index + 1)
      const url = String(item.pics || '').trim() || String(props.product.thumb || '').trim()
      return {
        id,
        url,
        title: String(item.sku || '').trim() || selectedText.value || '规格图片',
        priceText: formatMoney(item.price)
      }
    }).filter((item) => item.url)
  }

  const group = imageSpecGroup.value
  if (!group) {
    return []
  }

  return group.options.filter((item) => item.image).map((item) => ({
    id: item.value,
    url: item.image,
    title: item.label,
    priceText: item.priceText || priceText.value
  }))
})
const specPreviewCurrentIndex = computed(() => {
  if (skuList.value.length > 0) {
    const skuId = String(selectedSku.value && (selectedSku.value.id || selectedSku.value.uniqid) || '')
    const index = skuId ? specPreviewImages.value.findIndex((item) => item.id === skuId) : -1
    return Math.max(0, index)
  }

  const item = selectedImageSpec.value
  if (!item) {
    return 0
  }

  const index = specPreviewImages.value.findIndex((preview) => preview.id === item.value)
  return Math.max(0, index)
})
const displayActions = computed(() => {
  return props.actions.map((item) => ({
    type: String(item.type || ''),
    text: String(item.text || ''),
    className: String(item.className || 'sku-submit--buy')
  })).filter((item) => item.type && item.text)
})

watch(() => props.visible, (value) => {
  if (!value) {
    showSpecPreview.value = false
  }
})

/**
 * 规范化规格选项，提前生成选中态，避免模板里写复杂表达式。
 *
 * @param {string} groupKey 规格组 key。
 * @param {Array<Object>} options 原始规格选项。
 * @returns {Array<Object>} 可渲染规格选项。
 */
function normalizeOptions(groupKey, options = []) {
  const selectedValue = String(props.selected[groupKey] || '')
  return options.map((item) => ({
    label: String(item.label || ''),
    value: String(item.value || ''),
    image: String(item.image || '').trim(),
    priceText: String(item.priceText || '').trim(),
    selected: selectedValue === String(item.value || ''),
    disabled: item.disabled === true
  })).filter((item) => item.label && item.value)
}

/**
 * 根据当前规格选择解析命中的 SKU。
 *
 * @param {Object} selectedMap 规格选中值。
 * @returns {Object|null} 命中的 SKU。
 */
function resolveSelectedSku(selectedMap = props.selected) {
  if (skuList.value.length === 0) {
    return null
  }

  const specKey = displayGroups.value.map((group) => String(selectedMap[group.key] || '')).filter(Boolean).join('||')
  if (specKey) {
    const matched = skuList.value.find((item) => String(item.spec_key || '') === specKey)
    if (matched) {
      return matched
    }
  }

  const defaultSku = skuList.value.find((item) => Number(item.is_default || 0) === 1)
  return defaultSku || skuList.value[0] || null
}

/**
 * 根据 SKU 同步规格选择。
 *
 * @param {Object} sku SKU 数据。
 * @returns {void}
 */
function applySkuSelection(sku) {
  if (props.submitLoading || sku?.disabled === true) return
  const specKey = String(sku && sku.spec_key || '')
  if (!specKey) {
    return
  }

  const valueIds = specKey.split('||')
  displayGroups.value.forEach((group, index) => {
    const valueId = String(valueIds[index] || '')
    if (group.key && valueId) {
      emit('select', { type: group.key, value: valueId })
    }
  })
}

/**
 * 格式化金额为两位小数。
 *
 * @param {number|string} value 金额。
 * @returns {string} 金额文本。
 */
function formatMoney(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) {
    return '0.00'
  }

  return amount.toFixed(2)
}

/**
 * 关闭规格弹窗。
 *
 * @returns {void}
 */
function handleClose() {
  if (props.closeDisabled) return
  showSpecPreview.value = false
  emit('close')
}

/**
 * 抛出规格选择事件。
 *
 * @param {string} type 规格类型。
 * @param {string} value 规格值。
 * @returns {void}
 */
function handleSelect(type, value) {
  if (props.submitLoading) return
  const option = displayGroups.value.find(group => group.key === type)?.options.find(item => item.value === String(value))
  if (option?.disabled) return
  emit('select', { type, value })
}

/**
 * 处理规格项点击，只切换当前规格。
 *
 * @param {Object} group 规格组。
 * @param {Object} item 规格项。
 * @returns {void}
 */
function handleOptionClick(group, item) {
  if (item.disabled) {
    return
  }

  handleSelect(group.key, item.value)
}

/**
 * 处理规格图片点击，切换当前规格并打开图片预览。
 *
 * @param {Object} group 规格组。
 * @param {Object} item 规格项。
 * @returns {void}
 */
function handleOptionImageClick(group, item) {
  if (item.disabled) {
    return
  }

  handleSelect(group.key, item.value)
  if (skuList.value.length > 0) {
    const nextSelected = {
      ...props.selected,
      [group.key]: item.value
    }
    const nextSku = resolveSelectedSku(nextSelected)
    const nextSkuId = String(nextSku && (nextSku.id || nextSku.uniqid) || '')
    const index = nextSkuId ? specPreviewImages.value.findIndex((preview) => preview.id === nextSkuId) : -1
    specPreviewIndex.value = Math.max(0, index)
    showSpecPreview.value = true
    return
  }

  if (!item.image) {
    return
  }

  const index = specPreviewImages.value.findIndex((preview) => preview.id === item.value)
  specPreviewIndex.value = Math.max(0, index)
  showSpecPreview.value = true
}

/**
 * 打开当前已选规格图全屏预览。
 *
 * @returns {void}
 */
function openSelectedSpecPreview() {
  if (props.submitLoading || !specPreviewImages.value.length) {
    return
  }

  specPreviewIndex.value = specPreviewCurrentIndex.value
  showSpecPreview.value = true
}

/**
 * 关闭规格图全屏预览。
 *
 * @returns {void}
 */
function closeSpecPreview() {
  showSpecPreview.value = false
}

/**
 * 规格图滑动时同步选中的规格项。
 *
 * @param {number} index 当前规格图索引。
 * @returns {void}
 */
function changeSpecPreview(index) {
  const nextIndex = Math.max(0, Number(index || 0))
  const nextItem = specPreviewImages.value[nextIndex]
  specPreviewIndex.value = nextIndex
  if (!nextItem) {
    return
  }

  if (skuList.value.length > 0) {
    const nextSku = skuList.value.find((item) => String(item.id || item.uniqid || '') === String(nextItem.id || ''))
    if (nextSku) {
      applySkuSelection(nextSku)
    }
    return
  }

  const group = imageSpecGroup.value
  if (group) {
    handleSelect(group.key, nextItem.id)
  }
}

/**
 * 调整购买数量。
 *
 * @param {number} step 调整步长。
 * @returns {void}
 */
function changeQuantity(step) {
  if (quantityLocked.value) return
  const minValue = Math.max(0, Number(props.minQuantity) || 0)
  const maxValue = Math.max(minValue, Number(props.maxQuantity) || 0)
  const nextValue = Math.min(maxValue, Math.max(minValue, Number(props.quantity || 0) + Number(step || 0)))
  emit('quantity-change', nextValue)
}

/**
 * 抛出提交动作。
 *
 * @param {string} action 提交动作：cart/buy。
 * @returns {void}
 */
function handleSubmit(action) {
  if (props.submitLoading || props.submitDisabled) {
    return
  }

  emit('submit', action)
}
</script>

<style lang="scss" scoped>
.product-sku-popup {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.58);
  animation: sku-popup-mask-in 180ms ease-out both;
}

.product-sku-popup__sheet {
  width: 100%;
  min-height: 828rpx;
  max-height: 90%;
  padding: 34rpx 30rpx 26rpx;
  box-sizing: border-box;
  border-top-left-radius: 32rpx;
  border-top-right-radius: 32rpx;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  animation: sku-popup-sheet-in 240ms cubic-bezier(0.22, 0.61, 0.36, 1) both;
}

@keyframes sku-popup-mask-in {
  0% {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}

@keyframes sku-popup-sheet-in {
  0% {
    transform: translateY(100%);
  }

  100% {
    transform: translateY(0);
  }
}

/* #ifndef H5 */
.product-sku-popup__sheet {
  padding-bottom: 40rpx;
}
/* #endif */

.product-sku-popup--cart .product-sku-popup__sheet {
  min-height: 900rpx;
  margin-bottom: 112rpx;
}

.product-sku-popup__content {
  flex: 1;
  min-height: 0;
}

.product-sku-popup__header {
  display: flex;
  align-items: flex-start;
  position: relative;
}

.product-sku-popup__thumb {
  width: 112rpx;
  height: 112rpx;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 10rpx;
}

.product-sku-popup__summary {
  flex: 1;
  min-width: 0;
  margin-left: 26rpx;
}

.sku-price {
  display: flex;
  align-items: flex-end;
}

.sku-price__symbol,
.sku-price__integer,
.sku-price__decimal {
  color: var(--brand-primary, #ea580c);
  font-weight: 800;
}

.sku-price__symbol {
  margin-bottom: 5rpx;
  font-size: 30rpx;
  line-height: 34rpx;
}

.sku-price__integer {
  margin-left: 8rpx;
  font-size: 50rpx;
  line-height: 56rpx;
}

.sku-price__decimal {
  margin-bottom: 4rpx;
  font-size: 30rpx;
  line-height: 34rpx;
}

.sku-price__tag {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 36rpx;
  margin-bottom: 5rpx;
  margin-left: 18rpx;
  padding: 0 18rpx;
  border-radius: 999rpx;
  background: var(--brand-primary-pale, #fff7ed);
  color: var(--text-primary, #111111);
  font-size: 24rpx;
  line-height: 36rpx;
  font-weight: 700;
}

.sku-price__origin {
  margin-bottom: 5rpx;
  margin-left: 16rpx;
  color: #3f3f46;
  font-size: 24rpx;
  line-height: 34rpx;
  text-decoration: line-through;
}

.product-sku-popup__stock {
  display: block;
  margin-top: 16rpx;
  color: #696969;
  font-size: 26rpx;
  line-height: 38rpx;
}

.sku-section {
  margin-top: 48rpx;
}

.sku-section__title,
.quantity-row__title {
  display: block;
  color: #111111;
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
}

.sku-options {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  margin-top: 26rpx;
}

.sku-option {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 136rpx;
  height: 58rpx;
  margin-right: 18rpx;
  margin-bottom: 18rpx;
  padding: 0 24rpx;
  border: 1px solid transparent;
  border-radius: 18rpx;
  background: #f5f5f5;
  color: #111111;
  font-size: 24rpx;
  line-height: 34rpx;
}

.sku-option--image {
  justify-content: flex-start;
  min-width: 198rpx;
  height: 72rpx;
  padding: 0 18rpx 0 8rpx;
}

.sku-option__image {
  width: 56rpx;
  height: 56rpx;
  margin-right: 14rpx;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 12rpx;
}

.sku-option--small {
  min-width: 96rpx;
}

.sku-option--active {
  border-color: var(--brand-primary, #ea580c);
  background: #ffffff;
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.sku-option--disabled {
  color: #b8b8b8;
  background: #f2f2f2;
  opacity: 0.62;
}

.quantity-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 44rpx;
}

.stepper {
  display: flex;
  align-items: center;
  width: 204rpx;
  height: 58rpx;
  border: 1px solid var(--border-default, #e4e4e7);
  border-radius: 6rpx;
}

.stepper__button,
.stepper__value {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 68rpx;
  height: 58rpx;
  color: #111111;
  font-size: 30rpx;
  line-height: 40rpx;
}

.stepper__button--disabled {
  color: #c8c8c8;
}

.stepper__value {
  border-right: 1px solid var(--border-default, #e4e4e7);
  border-left: 1px solid var(--border-default, #e4e4e7);
}

.product-sku-popup__footer {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  margin-top: 28rpx;
  padding-top: 20rpx;
  border-top: 1rpx solid var(--border-light, #f0f0f0);
}

.sku-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  height: 74rpx;
  border-radius: 999rpx;
  color: #ffffff;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
}

.sku-submit--cart {
  background: linear-gradient(90deg, var(--brand-primary-light, #fb923c) 0%, var(--brand-primary, #ea580c) 100%);
}

.sku-submit--buy {
  margin-left: 24rpx;
  background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
}

.sku-submit--confirm {
  background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
}

.sku-submit--disabled {
  opacity: 0.64;
}
</style>
