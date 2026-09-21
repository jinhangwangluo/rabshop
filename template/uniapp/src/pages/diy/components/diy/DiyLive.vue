<template>
  <view
    class="diy-live"
    :class="{ 'diy-live--default-bg': !hasCustomBackground, 'diy-live--custom-bg': hasCustomBackground }"
    :style="componentStyle"
  >
    <image v-if="hasCustomBackground" class="live-bg-image" :src="backgroundImage" mode="widthFix"></image>

    <view v-if="shouldShowHeader" class="live-header">
      <view v-if="!hasCustomBackground" class="live-heading">
        <text class="live-heading__title">{{ liveTitle }}</text>
        <text class="live-heading__subtitle">{{ liveSubtitle }}</text>
      </view>
      <view v-if="showMore" class="live-more" hover-class="live-more--hover" @click="handleMoreClick">
        <text>{{ config.more_text || '更多直播' }}</text>
        <uni-icons class="live-more__icon" type="right" size="13" color="#ffffff"></uni-icons>
      </view>
    </view>

    <view v-if="liveList.length === 0" class="live-empty" @click="handleRetryClick">
      <text class="live-empty__title">{{ emptyText }}</text>
    </view>

    <view v-else-if="displayStyle === 'big'" class="live-big">
      <view
        v-for="item in liveList"
        :key="item.renderKey"
        class="live-big-item"
        hover-class="live-big-item--hover"
        @click="handleClick(item)"
      >
        <view class="live-big-cover" :style="imageRadiusStyle">
          <image v-if="item.cover" class="live-cover" :src="item.cover" mode="aspectFill" />
          <view v-else class="live-cover live-cover--empty"></view>
          <view class="live-mask"></view>
          <view class="live-badges">
            <view class="live-status" :class="item.statusClass">
              <view v-if="item.live_status === 1" class="live-dot"></view>
              <text>{{ item.statusText }}</text>
            </view>
            <view v-if="showCountInfo" class="live-hot">
              <uni-icons class="live-hot__icon" type="fire-filled" size="12" color="#ffcf6d"></uni-icons>
              <text>{{ item.viewText }}</text>
            </view>
          </view>
          <view class="live-big-info">
            <text class="live-big-title">{{ item.title }}</text>
            <text class="live-big-anchor">{{ item.anchor }}</text>
          </view>
          <view class="live-big-action">
            <text class="iconfont icon-playfill live-big-action__icon"></text>
            <text>进入直播</text>
          </view>
        </view>
      </view>
    </view>

    <scroll-view v-else-if="displayStyle === 'scroll'" class="live-scroll" scroll-x :show-scrollbar="false">
      <view class="live-track">
        <view
          v-for="item in liveList"
          :key="item.renderKey"
          class="live-card live-card--scroll"
          hover-class="live-card--hover"
          @click="handleClick(item)"
        >
          <view class="live-card-cover" :style="imageRadiusStyle">
            <image v-if="item.cover" class="live-cover" :src="item.cover" mode="aspectFill" />
            <view v-else class="live-cover live-cover--empty"></view>
            <view class="live-card-badges">
              <view class="live-status live-status--card" :class="item.statusClass">
                <view v-if="item.live_status === 1" class="live-dot"></view>
                <text>{{ item.statusText }}</text>
              </view>
              <view v-if="showCountInfo" class="live-hot live-hot--card">
                <uni-icons class="live-hot__icon" type="fire-filled" size="11" color="#ffcf6d"></uni-icons>
                <text>{{ item.viewText }}</text>
              </view>
            </view>
          </view>
          <view class="live-card-body">
            <view class="live-anchor-row">
              <image v-if="item.avatar" class="live-avatar" :src="item.avatar" mode="aspectFill"></image>
              <view v-else class="live-avatar live-avatar--empty"></view>
              <text class="live-anchor-name">{{ item.anchor }}</text>
            </view>
            <view class="live-card-footer">
              <text class="live-card-title">{{ item.title }}</text>
              <view class="live-like">
                <uni-icons class="live-like__icon" type="heart" size="14" color="#ff6c9d"></uni-icons>
                <text>{{ item.likeText }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </scroll-view>

    <view v-else class="live-small-grid">
      <view
        v-for="item in liveList"
        :key="item.renderKey"
        class="live-card-cell"
        hover-class="live-card--hover"
        @click="handleClick(item)"
      >
        <view class="live-card">
          <view class="live-card-cover" :style="imageRadiusStyle">
            <image v-if="item.cover" class="live-cover" :src="item.cover" mode="aspectFill" />
            <view v-else class="live-cover live-cover--empty"></view>
            <view class="live-card-badges">
              <view class="live-status live-status--card" :class="item.statusClass">
                <view v-if="item.live_status === 1" class="live-dot"></view>
                <text>{{ item.statusText }}</text>
              </view>
              <view v-if="showCountInfo" class="live-hot live-hot--card">
                <uni-icons class="live-hot__icon" type="fire-filled" size="11" color="#ffcf6d"></uni-icons>
                <text>{{ item.viewText }}</text>
              </view>
            </view>
          </view>
          <view class="live-card-body">
            <view class="live-anchor-row">
              <image v-if="item.avatar" class="live-avatar" :src="item.avatar" mode="aspectFill"></image>
              <view v-else class="live-avatar live-avatar--empty"></view>
              <text class="live-anchor-name">{{ item.anchor }}</text>
            </view>
            <view class="live-card-footer">
              <text class="live-card-title">{{ item.title }}</text>
              <view class="live-like">
                <uni-icons class="live-like__icon" type="heart" size="14" color="#ff6c9d"></uni-icons>
                <text>{{ item.likeText }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { navigateByLink } from '@/utils/link'
import { getImageRadiusStyle } from '@/utils/diy_adapter'

defineOptions({
  name: 'DiyLive'
})

const DEFAULT_LIVE_TITLE = '热门直播'
const DEFAULT_LIVE_SUBTITLE = '精彩正在发生'
const LEGACY_DEFAULT_BACKGROUND_MARKS = [
  '57484fe0d2c06c7202607082149467845.png'
]

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  },
  data: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['retry'])

const liveList = computed(() => {
  const source = Array.isArray(props.data) ? props.data : []
  const limit = Number(props.config.show_count || source.length || 4)
  return source.slice(0, limit).map((item, index) => mapItem(item, index))
})

const displayStyle = computed(() => {
  const style = String(props.config.display_style || 'small').trim()
  if (style === 'big') return 'big'
  if (style === 'scroll') return 'scroll'
  return 'small'
})

const showCountInfo = computed(() => {
  return props.config.show_count_info !== false
})

const showMore = computed(() => {
  return props.config.show_more !== false
})

const rawBackgroundImage = computed(() => {
  return String(props.config.background_image || '').trim()
})

const isLegacyDefaultBackground = computed(() => {
  const image = rawBackgroundImage.value
  return LEGACY_DEFAULT_BACKGROUND_MARKS.some((mark) => image.indexOf(mark) > -1)
})

const hasCustomBackground = computed(() => {
  return Boolean(rawBackgroundImage.value) && !isLegacyDefaultBackground.value
})

const shouldShowHeader = computed(() => {
  return Boolean(showMore.value || !hasCustomBackground.value)
})

const backgroundImage = computed(() => {
  return rawBackgroundImage.value
})

const liveTitle = computed(() => {
  const title = String(props.config.title || '').trim()
  if (!title || (isLegacyDefaultBackground.value && title === '直播')) {
    return DEFAULT_LIVE_TITLE
  }
  return title
})

const liveSubtitle = computed(() => {
  return String(props.config.subtitle || props.config.desc || DEFAULT_LIVE_SUBTITLE).trim() || DEFAULT_LIVE_SUBTITLE
})

const componentStyle = computed(() => {
  const style = {}
  if (props.config.bg_color) {
    style.backgroundColor = props.config.bg_color
  }
  return style
})

const imageRadiusStyle = computed(() => {
  return getImageRadiusStyle(props.config.style_config)
})

const emptyText = computed(() => {
  if (props.loading) {
    return '直播加载中...'
  }

  if (props.error) {
    return `${props.error}，点击重试`
  }

  return '暂无直播数据'
})

function mapItem(item = {}, index = 0) {
  const rawStatus = item.live_status !== undefined ? item.live_status : item.status
  const status = Number(rawStatus)
  const normalizedStatus = Number.isNaN(status) ? 1 : status
  const anchor = item.anchor && typeof item.anchor === 'object' ? item.anchor : {}
  return {
    ...item,
    renderKey: `${item.id || item.room_id || item.live_room_id || item.title || 'live'}_${index}`,
    title: item.title || '直播间',
    cover: item.cover || item.image || '',
    avatar: item.anchor_avatar || anchor.avatar || item.avatar || '',
    anchor: item.anchor_name || anchor.name || item.subtitle || '主播',
    live_status: normalizedStatus,
    statusClass: statusClass(normalizedStatus),
    statusText: getStatusText(normalizedStatus),
    shortStatusText: normalizedStatus === 1 ? 'LIVE' : (normalizedStatus === 0 ? '预告' : '回放'),
    views: Number(item.view_count || item.views || item.count || 0),
    likes: Number(item.like_count || item.likes || item.likeCount || 0),
    viewText: formatCount(item.view_count || item.views || item.count || 0),
    likeText: formatCount(item.like_count || item.likes || item.likeCount || 0)
  }
}

function getViewCount(item = {}) {
  return Number(item.view_count || item.views || item.count || 0)
}

function formatCount(value) {
  const count = Number(value || 0)
  if (count >= 10000) return `${(count / 10000).toFixed(1)}w`
  if (count >= 1000) return `${(count / 1000).toFixed(1)}k`
  return `${count}`
}

function getStatusText(status) {
  const map = {
    0: '即将开始',
    1: '直播中',
    2: '已结束'
  }
  return map[Number(status)] || '直播中'
}

function statusClass(status) {
  if (status === 1) return 'status-live'
  if (status === 0) return 'status-upcoming'
  return 'status-ended'
}

function handleClick(item = {}) {
  if (navigateByLink(item.link_url)) {
    return
  }

  const roomId = Number(item.id || item.room_id || item.live_room_id || 0)
  if (roomId > 0) {
    uni.navigateTo({ url: `/pages/lives/room/index?id=${roomId}` })
    return
  }

  uni.showToast({ title: '直播详情待接入', icon: 'none' })
}

function handleMoreClick() {
  if (navigateByLink(props.config.more_link_url, {
    copyTip: '小程序暂不支持直接打开外链，链接已复制'
  })) {
    return
  }

  uni.navigateTo({ url: '/pages/lives/live/index' })
}

function handleRetryClick() {
  if (props.error) {
    emit('retry')
  }
}
</script>

<style lang="scss" scoped>
.diy-live {
  position: relative;
  width: auto;
  padding: 20rpx 20rpx 24rpx;
  box-sizing: border-box;
  overflow: hidden;
  border-radius: 28rpx;
}

.diy-live--default-bg {
  padding-top: 140rpx;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.58) 72%, rgba(255, 255, 255, 0.82) 100%),
    var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
}

.diy-live--custom-bg {
  padding-top: 140rpx;
}

.live-bg-image {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 0;
  display: block;
  width: 100%;
}

.live-header {
  position: absolute;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  right: 0;
  top: 60rpx;
}

.diy-live--default-bg .live-header {
  position: absolute;
  left: 20rpx;
  right: 20rpx;
  top: 34rpx;
  z-index: 3;
  padding: 0;
  align-items: flex-start;
  justify-content: space-between;
}

.live-heading {
  flex: 1;
  min-width: 0;
  margin-right: 20rpx;
}

.live-heading__title {
  display: block;
  color: var(--text-inverse, #ffffff);
  font-size: 40rpx;
  line-height: 50rpx;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.12);
}

.live-heading__subtitle {
  display: block;
  margin-top: 6rpx;
  color: var(--text-inverse-soft, rgba(255, 255, 255, 0.8));
  font-size: 24rpx;
  line-height: 34rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-more {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  margin-top: 12rpx;
  height: 44rpx;
  padding: 0 18rpx;
  border-radius: 22rpx;
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
  font-size: 24rpx;
  line-height: 44rpx;
  font-weight: 600;
  box-shadow: 0 6rpx 14rpx rgba(116, 54, 0, 0.12);
}

.diy-live--custom-bg .live-more {
  margin-top: 0;
}

.live-more--hover {
  opacity: 0.9;
}

.live-more__icon {
  margin-left: 4rpx;
}

.live-empty {
  position: relative;
  z-index: 1;
  padding: 56rpx 0;
  border-radius: 24rpx;
  background: rgba(255, 255, 255, 0.86);
  text-align: center;
  box-shadow: 0 10rpx 28rpx rgba(92, 58, 156, 0.08);
}

.live-empty__title {
  color: #999999;
  font-size: 24rpx;
}

.live-small-grid {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  margin-left: -10rpx;
  margin-right: -10rpx;
  margin-bottom: -20rpx;
}

.live-card {
  overflow: hidden;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 10rpx 28rpx rgba(92, 58, 156, 0.12);
  box-sizing: border-box;
}

.live-card-cell {
  width: 50%;
  padding: 0 10rpx 20rpx;
  box-sizing: border-box;
}

.live-card--hover,
.live-big-item--hover {
  opacity: 0.9;
}

.live-card-cover,
.live-big-cover {
  position: relative;
  overflow: hidden;
  background: #1f2933;
}

.live-card-cover {
  height: 196rpx;
}

.live-cover {
  width: 100%;
  height: 100%;
}

.live-cover--empty {
  background: #2f3131;
}

.live-mask {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.02), rgba(0, 0, 0, 0.56));
}

.live-badges,
.live-card-badges {
  position: absolute;
  left: 14rpx;
  right: 14rpx;
  top: 14rpx;
  z-index: 2;
  display: flex;
  align-items: center;
}

.live-status {
  display: flex;
  align-items: center;
  height: 36rpx;
  padding: 0 12rpx;
  border-radius: 18rpx;
  background: rgba(156, 163, 175, 0.9);
  color: #ffffff;
  font-size: 18rpx;
  line-height: 36rpx;
  font-weight: 600;
  white-space: nowrap;
}

.live-status.status-live {
  background: #ff4c7d;
}

.live-status.status-upcoming {
  background: rgba(245, 158, 11, 0.92);
}

.live-status--card {
  height: 34rpx;
  padding: 0 11rpx;
  border-radius: 17rpx;
  font-size: 18rpx;
  line-height: 34rpx;
}

.live-dot {
  width: 8rpx;
  height: 8rpx;
  margin-right: 8rpx;
  border-radius: 50%;
  background: #ffffff;
}

.live-hot {
  display: flex;
  align-items: center;
  margin-left: 10rpx;
  height: 36rpx;
  padding: 0 12rpx;
  border-radius: 18rpx;
  background: rgba(28, 27, 39, 0.58);
  color: #ffffff;
  font-size: 18rpx;
  line-height: 36rpx;
  font-weight: 600;
  white-space: nowrap;
}

.live-hot--card {
  height: 34rpx;
  border-radius: 17rpx;
  font-size: 18rpx;
  line-height: 34rpx;
}

.live-hot__icon {
  display: flex;
  align-items: center;
  margin-right: 4rpx;
}

.live-card-body {
  padding: 16rpx 18rpx 18rpx;
  box-sizing: border-box;
}

.live-anchor-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.live-avatar {
  flex-shrink: 0;
  width: 46rpx;
  height: 46rpx;
  border: 2rpx solid #ffffff;
  border-radius: 50%;
  background: #efe7ff;
  box-sizing: border-box;
}

.live-avatar--empty {
  background: linear-gradient(135deg, #ffe2ee, #e9defc);
}

.live-anchor-name {
  flex: 1;
  min-width: 0;
  margin-left: 10rpx;
  color: #6f6678;
  font-size: 22rpx;
  line-height: 32rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-card-footer {
  display: flex;
  align-items: center;
  min-width: 0;
  margin-top: 8rpx;
}

.live-card-title {
  flex: 1;
  min-width: 0;
  color: #222222;
  font-size: 26rpx;
  line-height: 36rpx;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-like {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  margin-left: 12rpx;
  color: #8f8b96;
  font-size: 22rpx;
  line-height: 32rpx;
  white-space: nowrap;
}

.live-like__icon {
  display: flex;
  align-items: center;
  margin-right: 4rpx;
}

.live-big {
  position: relative;
  z-index: 1;
  padding: 0;
}

.live-big-item {
  margin-bottom: 20rpx;
}

.live-big-item:last-child {
  margin-bottom: 0;
}

.live-big-cover {
  height: 430rpx;
  border-radius: 28rpx;
  box-shadow: 0 14rpx 34rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
}

.live-big-info {
  position: absolute;
  left: 24rpx;
  right: 210rpx;
  bottom: 26rpx;
  z-index: 2;
}

.live-big-title {
  display: block;
  color: #ffffff;
  font-size: 34rpx;
  line-height: 46rpx;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-big-anchor {
  display: block;
  margin-top: 6rpx;
  color: rgba(255, 255, 255, 0.86);
  font-size: 24rpx;
  line-height: 32rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-big-action {
  position: absolute;
  right: 24rpx;
  bottom: 30rpx;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 168rpx;
  height: 64rpx;
  border-radius: 32rpx;
  background: var(--theme-gradient, linear-gradient(135deg, #ea580c 0%, #fb923c 100%));
  color: var(--text-inverse, #ffffff);
  font-size: 24rpx;
  line-height: 64rpx;
  font-weight: 700;
  box-shadow: 0 10rpx 24rpx var(--brand-shadow, rgba(234, 88, 12, 0.18));
}

.live-big-action__icon {
  color: currentColor;
  font-size: 26rpx;
  line-height: 1;
  margin-right: 8rpx;
}

.live-scroll {
  position: relative;
  z-index: 1;
  width: 100%;
}

.live-track {
  display: flex;
  padding-bottom: 2rpx;
}

.live-card--scroll {
  flex-shrink: 0;
  width: 330rpx;
  margin-right: 20rpx;
}

.live-card--scroll:last-child {
  margin-right: 0;
}

/* #ifdef MP-WEIXIN */
.live-card,
.live-card-cell {
  box-sizing: border-box;
}
/* #endif */
</style>
