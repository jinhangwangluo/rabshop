<template>
  <view
    v-if="visible"
    class="sheet-panel sheet-panel--gift"
    :class="panelClass"
    @click.stop
    @touchmove.stop.prevent="noop"
  >
    <view class="sheet-panel__handle sheet-panel__handle--gift"></view>

    <view class="gift-head">
      <view class="gift-tabs">
        <view
          v-for="item in giftCategories"
          :key="item"
          class="gift-tabs__item"
          :class="activeGiftCategory === item ? 'gift-tabs__item--active' : ''"
          @click="handleCategoryChange(item)"
        >
          <text>{{ item }}</text>
        </view>
      </view>
      <view class="gift-head__info" @click="$emit('rule')">
        <text class="iconfont icon-bangzhu gift-head__info-icon"></text>
      </view>
    </view>

    <scroll-view scroll-y class="sheet-panel__scroll sheet-panel__scroll--gift">
      <view v-if="giftItems.length" class="gift-grid">
        <view
          v-for="item in filteredGiftItems"
          :key="item.id"
          class="gift-card"
          :class="item.activeClass"
          @click="handleGiftSelect(item)"
        >
          <view class="gift-card__inner">
            <view class="gift-card__icon" :style="item.iconStyle">
              <image v-if="item.icon" class="gift-card__image" :src="item.icon" mode="aspectFit" />
              <text v-else :class="item.fallbackIconClass">{{ item.fallbackIconText }}</text>
            </view>
            <text class="gift-card__name">{{ item.name }}</text>
            <view class="gift-card__price">
              <text class="iconfont icon-integral gift-card__coin"></text>
              <text>{{ item.price }}</text>
            </view>
          </view>
        </view>
      </view>
      <view v-else class="sheet-empty-state sheet-empty-state--gift">
        <text class="sheet-empty-state__title">暂无可用礼物</text>
        <text class="sheet-empty-state__desc">礼物配置接入后会展示在这里。</text>
      </view>
    </scroll-view>

    <view class="gift-submitbar">
      <view class="gift-balance">
        <view class="gift-balance__row">
          <text class="iconfont icon-jine gift-balance__icon"></text>
          <text class="gift-balance__value">金币: {{ giftBalanceText }}</text>
        </view>
        <view class="gift-balance__link" @click="$emit('recharge')">
          <text>{{ rechargeLinkText }}</text>
          <text class="iconfont icon-right gift-balance__arrow"></text>
        </view>
      </view>

      <view class="gift-submitbar__actions">
        <view class="gift-count-picker" @click="openGiftCountPicker">
          <text class="gift-count-picker__value">{{ localGiftCount }}</text>
        </view>
        <view class="gift-submitbar__button" :class="!canSendGift ? 'gift-submitbar__button--disabled' : ''" @click="handleGiftSend">
          <text>{{ giftSendButtonText }}</text>
        </view>
      </view>
    </view>

  </view>
</template>

<script>
// 小程序样式隔离选项：共享页面样式，需保留为普通 script 导出（script setup 无法声明组件选项）
export default {
  options: {
    styleIsolation: 'shared'
  }
}
</script>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'

defineOptions({ name: 'LiveGiftSheet' })

const props = defineProps({
  visible: Boolean,
  animationState: {
    type: String,
    default: ''
  },
  landscape: Boolean,
  giftItems: {
    type: Array,
    default: () => []
  },
  giftBalance: {
    type: [String, Number],
    default: ''
  },
  loggedIn: Boolean,
  sending: Boolean,
  selectedGiftId: {
    type: [String, Number],
    default: ''
  },
  countOptions: {
    type: Array,
    default: () => [1, 5, 10, 20, 66]
  }
})

const emit = defineEmits(['rule', 'recharge', 'select', 'count-change', 'send'])

const selectedCategory = ref('')
const localSelectedGiftId = ref('')
const localGiftCount = ref(1)

const panelClass = computed(() => {
  const classNames = []
  if (props.animationState === 'enter') classNames.push('sheet-panel--enter')
  if (props.landscape) classNames.push('sheet-panel--landscape')
  return classNames.join(' ')
})

const giftCategories = computed(() => {
  return Array.from(new Set(props.giftItems.map((item) => item.category)))
})

const activeGiftCategory = computed(() => {
  if (giftCategories.value.includes(selectedCategory.value)) return selectedCategory.value
  return giftCategories.value.length ? giftCategories.value[0] : ''
})

const filteredGiftItems = computed(() => {
  const category = activeGiftCategory.value
  return props.giftItems
    .filter((item) => !category || item.category === category)
    .map((item) => ({
      ...item,
      activeClass: item.id === localSelectedGiftId.value ? 'gift-card--active' : ''
    }))
})

const selectedGift = computed(() => {
  return props.giftItems.find((item) => item.id === localSelectedGiftId.value) || null
})

const giftBalanceText = computed(() => {
  if (props.giftBalance === '') return '--'
  return `${props.giftBalance}`
})

const giftTotalCoins = computed(() => {
  if (!selectedGift.value) return 0
  return Number(selectedGift.value.price || 0) * Number(localGiftCount.value || 1)
})

const canSendGift = computed(() => {
  return !!selectedGift.value && props.loggedIn && giftTotalCoins.value > 0 && !props.sending
})

const giftSendButtonText = computed(() => {
  if (!selectedGift.value) return '请选择'
  return '赠送'
})

const rechargeLinkText = computed(() => {
  return '立即充值'
})

watch(() => props.visible, (value) => {
  if (value) {
    syncSelectedGift()
  }
})

watch(() => props.giftItems, () => {
  syncSelectedGift()
})

watch(() => props.selectedGiftId, (value) => {
  localSelectedGiftId.value = value || ''
  syncSelectedCategory()
})

onMounted(() => {
  syncSelectedGift()
})

function noop() {}

function syncSelectedGift() {
  if (props.selectedGiftId) {
    const matchedGift = props.giftItems.find((item) => item.id === props.selectedGiftId)
    if (matchedGift) {
      localSelectedGiftId.value = props.selectedGiftId
      selectedCategory.value = matchedGift.category || selectedCategory.value
      return
    }
  }
  if (localSelectedGiftId.value && props.giftItems.some((item) => item.id === localSelectedGiftId.value)) {
    syncSelectedCategory()
    return
  }
  const firstGift = filteredGiftItems.value.length ? filteredGiftItems.value[0] : null
  localSelectedGiftId.value = firstGift ? firstGift.id : ''
  if (firstGift) {
    emit('select', firstGift)
  }
}

function syncSelectedCategory() {
  const matchedGift = props.giftItems.find((item) => item.id === localSelectedGiftId.value)
  if (matchedGift && matchedGift.category) {
    selectedCategory.value = matchedGift.category
  }
}

function handleCategoryChange(category) {
  selectedCategory.value = category
  const nextGift = props.giftItems.find((item) => item.category === category)
  localSelectedGiftId.value = nextGift ? nextGift.id : ''
  if (nextGift) {
    emit('select', nextGift)
  }
}

function handleGiftSelect(item = {}) {
  localSelectedGiftId.value = item.id || ''
  emit('select', item)
}

function openGiftCountPicker() {
  uni.showActionSheet({
    itemList: props.countOptions.map((item) => `${item} 个`),
    success: ({ tapIndex }) => {
      localGiftCount.value = props.countOptions[tapIndex] || 1
      emit('count-change', localGiftCount.value)
    }
  })
}

function handleGiftSend() {
  emit('send', {
    gift: selectedGift.value,
    quantity: localGiftCount.value,
    canSend: canSendGift.value
  })
}
</script>

<style lang="scss" src="./style.scss"></style>
