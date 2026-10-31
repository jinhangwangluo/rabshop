<template>
  <view class="auth-mode-tabs">
    <view
      v-for="item in modes"
      :key="item.key"
      class="tab-item"
      :class="{ 'tab-item--active': item.key === activeKey }"
      @click="$emit('change', item.key)"
    >
      <text class="tab-item__text" :class="{ 'tab-item__text--active': item.key === activeKey }">{{ item.label }}</text>
    </view>
  </view>
</template>

<script setup>
defineOptions({ name: 'AuthModeTabs' })

defineProps({
  modes: {
    type: Array,
    default: () => []
  },
  activeKey: {
    type: String,
    default: ''
  }
})

defineEmits(['change'])
</script>

<style scoped lang="scss">
.auth-mode-tabs {
  display: flex;
  padding: 8rpx;
  border-radius: 24rpx;
  background: #eeeeee;
}

.tab-item {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 72rpx;
  border-radius: 20rpx;
}

.tab-item--active {
  background: #ffffff;
  box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.08);
}

.tab-item__text {
  font-size: 28rpx;
  font-weight: 500;
  color: #888888;
}

.tab-item__text--active {
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}
</style>
