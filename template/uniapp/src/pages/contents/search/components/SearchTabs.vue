<template>
  <view class="search-tabs">
    <view class="search-tabs__scroll">
      <view class="search-tabs__list">
        <view
          v-for="item in tabs"
          :key="item.value"
          class="search-tabs__item"
          :class="{ 'is-active': item.value === activeValue }"
          @click="handleSelect(item.value)"
        >
          <text class="search-tabs__text">{{ item.label }}</text>
          <view v-if="item.value === activeValue" class="search-tabs__indicator"></view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
defineOptions({
  name: 'SearchTabs'
})

defineProps({
  tabs: {
    type: Array,
    default: () => []
  },
  activeValue: {
    type: String,
    default: 'all'
  }
})

const emit = defineEmits(['change'])

function handleSelect(value) {
  emit('change', value)
}
</script>

<style lang="scss" scoped>
.search-tabs {
  overflow: hidden;
  background: transparent;
}

.search-tabs__scroll {
  overflow: hidden;
}

.search-tabs__list {
  display: flex;
  align-items: center;
  min-width: 100%;
  padding: 0 32rpx;
}

.search-tabs__item {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 76rpx;
  margin-right: 52rpx;
}

.search-tabs__item:last-child {
  margin-right: 0;
}

.search-tabs__text {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 500;
  color: #5b5f64;
}

.search-tabs__item.is-active .search-tabs__text {
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.search-tabs__indicator {
  position: absolute;
  bottom: 10rpx;
  left: 50%;
  width: 28rpx;
  height: 6rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  transform: translateX(-50%);
}
</style>
