<template>
  <view class="diy-notice" :style="noticeStyle">
    <image 
      v-if="showIcon && config.icon" 
      class="notice-icon" 
      :src="config.icon" 
      mode="aspectFit"
    />
    <text v-else-if="showIcon" class="iconfont icongonggao"></text>
    <swiper 
      v-if="noticeList.length > 1"
      class="notice-swiper" 
      vertical 
      circular 
      :autoplay="config.scroll_type !== 'static'"
      :interval="3000"
    >
      <swiper-item 
        v-for="(item, index) in noticeList" 
        :key="index"
        @click="handleClick(item)"
      >
        <text class="notice-text" :style="noticeTextStyle">{{ item.text }}</text>
      </swiper-item>
    </swiper>
    <text 
      v-else-if="noticeList.length === 1"
      class="notice-text single" 
      :style="noticeTextStyle"
      @click="handleClick(noticeList[0])"
    >{{ noticeList[0].text }}</text>
  </view>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'DiyNotice'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  }
})

const noticeList = computed(() => {
  return props.data.length > 0 ? props.data : (props.config.items || [])
})

const showIcon = computed(() => {
  return props.config.show_icon !== false
})

const noticeStyle = computed(() => {
  const style = {}
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  return style
})

const noticeTextStyle = computed(() => {
  return props.config.text_color ? `color: ${props.config.text_color};` : ''
})

function handleClick(item) {
  if (item.link_type === 'page' && item.link_url) {
    uni.navigateTo({ url: item.link_url })
  } else if (item.link_type === 'url' && item.link_url) {
    uni.navigateTo({
      url: `/pages/webview/webview?url=${encodeURIComponent(item.link_url)}`
    })
  }
}
</script>

<style lang="scss" scoped>
.diy-notice {
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 20rpx 0;
  
  .notice-icon {
    width: 40rpx;
    height: 40rpx;
    margin-right: 16rpx;
  }
  
  .iconfont {
    font-size: 40rpx;
    margin-right: 16rpx;
    color: #ed6a0c;
  }
  
  .notice-swiper {
    flex: 1;
    min-width: 0;
    height: 40rpx;
    
    .notice-text {
      font-size: 26rpx;
      line-height: 40rpx;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }
  
  .notice-text {
    flex: 1;
    min-width: 0;
    font-size: 26rpx;
    line-height: 40rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    
    &.single {
      display: block;
    }
  }
}
</style>
