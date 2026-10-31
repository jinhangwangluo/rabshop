<template>
  <common-wrapper :config="config">
    <div
      class="live-area"
      :class="{ 'live-area--default-bg': !hasCustomBackground, 'live-area--custom-bg': hasCustomBackground }"
      :style="areaStyle"
    >
      <div v-if="shouldShowHeader" class="diy-section-header">
        <span class="diy-section-more">
          {{ moreText }} <LegacyIcon name="el-icon-arrow-right" />
        </span>
      </div>

      <div v-if="liveList.length === 0" class="diy-empty">
        <LegacyIcon name="el-icon-video-camera" />
        <span>暂无直播数据</span>
      </div>

      <div v-else-if="displayStyle === 'big'" class="live-hero-list">
        <div
          v-for="(item, index) in liveList"
          :key="item.renderKey || index"
          class="live-hero-card"
          @click.prevent
        >
          <div class="live-hero-cover" :style="imageRadiusStyle">
            <img v-if="item.cover" :src="item.cover" :alt="item.title">
            <div v-else class="live-cover-placeholder">
              <LegacyIcon name="el-icon-video-camera" />
            </div>
            <div class="live-mask" />
            <div class="live-badges">
              <span class="live-status" :class="item.statusClass">
                <i v-if="Number(item.live_status) === 1" class="live-dot" />
                {{ item.statusText }}
              </span>
              <span v-if="showCountInfo" class="live-hot">
                <LegacyIcon name="el-icon-s-opportunity" /> {{ item.viewText }}
              </span>
            </div>
            <div class="live-hero-info">
              <div class="live-hero-title">{{ item.title || '直播间' }}</div>
              <div class="live-anchor">{{ item.anchor || '主播' }}</div>
            </div>
            <div class="live-hero-action">
              <i class="live-play-triangle" />
              <span>进入直播</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="displayStyle === 'scroll'" class="live-scroll">
        <div
          v-for="(item, index) in liveList"
          :key="item.renderKey || index"
          class="live-card live-card--scroll"
          @click.prevent
        >
          <div class="live-card-cover" :style="imageRadiusStyle">
            <img v-if="item.cover" :src="item.cover" :alt="item.title">
            <div v-else class="live-cover-placeholder">
              <LegacyIcon name="el-icon-video-camera" />
            </div>
            <div class="live-card-badges">
              <span class="live-status live-status--card" :class="item.statusClass">
                <i v-if="Number(item.live_status) === 1" class="live-dot" />
                {{ item.statusText }}
              </span>
              <span v-if="showCountInfo" class="live-hot live-hot--card">
                <LegacyIcon name="el-icon-s-opportunity" /> {{ item.viewText }}
              </span>
            </div>
          </div>
          <div class="live-card-body">
            <div class="live-anchor-row">
              <img v-if="item.avatar" class="live-avatar" :src="item.avatar" :alt="item.anchor">
              <span v-else class="live-avatar live-avatar--empty" />
              <span class="live-anchor-name">{{ item.anchor || '主播' }}</span>
            </div>
            <div class="live-card-footer">
              <div class="live-card-title">{{ item.title || '直播间' }}</div>
              <span class="live-like"><LegacyIcon name="el-icon-star-off" />{{ item.likeText }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="live-small-grid">
        <div
          v-for="(item, index) in liveList"
          :key="item.renderKey || index"
          class="live-card-cell"
          @click.prevent
        >
          <div class="live-card">
            <div class="live-card-cover" :style="imageRadiusStyle">
              <img v-if="item.cover" :src="item.cover" :alt="item.title">
              <div v-else class="live-cover-placeholder">
                <LegacyIcon name="el-icon-video-camera" />
              </div>
              <div class="live-card-badges">
                <span class="live-status live-status--card" :class="item.statusClass">
                  <i v-if="Number(item.live_status) === 1" class="live-dot" />
                  {{ item.statusText }}
                </span>
                <span v-if="showCountInfo" class="live-hot live-hot--card">
                  <LegacyIcon name="el-icon-s-opportunity" /> {{ item.viewText }}
                </span>
              </div>
            </div>
            <div class="live-card-body">
              <div class="live-anchor-row">
                <img v-if="item.avatar" class="live-avatar" :src="item.avatar" :alt="item.anchor">
                <span v-else class="live-avatar live-avatar--empty" />
                <span class="live-anchor-name">{{ item.anchor || '主播' }}</span>
              </div>
              <div class="live-card-footer">
                <div class="live-card-title">{{ item.title || '直播间' }}</div>
                <span class="live-like"><LegacyIcon name="el-icon-star-off" />{{ item.likeText }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'
import { getRoomList } from '@/api/live'
import { getImageRadiusStyle } from './imageRadius'
import liveBg from '@/assets/images/live-bg.webp'

defineOptions({
  name: 'Live',
  cname: '直播',
  icon: '#iconzujian-live',
  configName: 'c_live',
  type: 0
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const displayStyle = ref('small')
const showCount = ref(4)
const bgColor = ref('#ffffff')
const showCountInfo = ref(true)
const showMore = ref(true)
const moreText = ref('更多直播')
const backgroundImage = ref(liveBg)
const hasCustomBackground = ref(false)
const items = ref([])
const fetchKey = ref(null)

const liveList = computed(() => {
  return items.value
    .slice(0, Number(showCount.value || items.value.length || 4))
    .map((item, index) => mapItem(item, index))
})
const imageRadiusStyle = computed(() => getImageRadiusStyle(props.config && props.config.style_config))
const shouldShowHeader = computed(() => Boolean(showMore.value))
const areaStyle = computed(() => ({
  backgroundColor: bgColor.value || 'transparent',
  backgroundImage: `url(${backgroundImage.value})`
}))

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(data) {
  if (!data) return
  displayStyle.value = normalizeStyle(data.display_style || data.style)
  showCount.value = data.show_count || 4
  bgColor.value = data.bg_color || '#ffffff'
  showCountInfo.value = data.show_count_info !== false
  showMore.value = data.show_more !== false
  moreText.value = data.more_text || '更多直播'
  backgroundImage.value = data.background_image || liveBg
  hasCustomBackground.value = Boolean(data.background_image)

  if (Array.isArray(data.live_data) && data.live_data.length > 0) {
    items.value = data.live_data
    return
  }
  if (Array.isArray(data.list) && data.list.length > 0) {
    items.value = data.list
    return
  }
  if (Array.isArray(data.items) && data.items.length > 0) {
    items.value = data.items
    return
  }

  const nextFetchKey = JSON.stringify({
    data_source: data.data_source,
    show_count: data.show_count,
    category_ids: data.category_ids,
    show_status: data.show_status,
    manual_ids: data.manual_ids,
    background_image: data.background_image
  })
  if (nextFetchKey === fetchKey.value) return
  fetchKey.value = nextFetchKey

  fetchPreviewData(data)
}

function normalizeStyle(style) {
  if (style === 'big') return 'big'
  if (style === 'scroll') return 'scroll'
  return 'small'
}

function fetchPreviewData(data) {
  if (data.data_source === 'manual') {
    const manualIds = data.manual_ids || []
    items.value = manualIds.map(item =>
      typeof item === 'object'
        ? { ...item, cover: item.cover || item.image || '' }
        : { id: item, title: '直播间 #' + item, cover: '', live_status: 2 }
    )
    return
  }

  const params = {
    page: 1,
    limit: data.show_count || 4
  }
  if (data.category_ids && data.category_ids.length > 0) {
    params.category_id = data.category_ids[0]
  }
  if (data.show_status === 'living') {
    params.status = 1
  } else if (data.show_status === 'preview') {
    params.status = 0
  }
  getRoomList(params).then(res => {
    const list = (res.data && (res.data.list || res.data)) || []
    items.value = Array.isArray(list) ? list : []
  }).catch(() => {
    items.value = []
  })
}

function mapItem(item, index) {
  const rawStatus = item.live_status !== undefined ? item.live_status : item.status
  const status = Number(rawStatus)
  const normalizedStatus = Number.isNaN(status) ? 1 : status
  const statusText = statusLabel(normalizedStatus)
  const anchor = item.anchor && typeof item.anchor === 'object' ? item.anchor : {}
  return {
    ...item,
    renderKey: `${item.id || item.room_id || item.title || 'live'}_${index}`,
    title: item.title || item.name || '',
    cover: item.cover || item.image || '',
    avatar: item.anchor_avatar || anchor.avatar || item.avatar || '',
    anchor: item.anchor_name || anchor.name || item.subtitle || item.teacher_name || '',
    live_status: normalizedStatus,
    statusClass: statusClass(normalizedStatus),
    statusText,
    views: Number(item.view_count || item.views || item.count || 0),
    likes: Number(item.like_count || item.likes || item.likeCount || 0),
    viewText: formatCount(item.view_count || item.views || item.count || 0),
    likeText: formatCount(item.like_count || item.likes || item.likeCount || 0)
  }
}

function statusClass(status) {
  if (status === 1) return 'status-live'
  if (status === 0) return 'status-upcoming'
  return 'status-ended'
}

function statusLabel(status) {
  if (status === 1) return '正在直播'
  if (status === 0) return '即将开始'
  return '回放中'
}

function formatCount(num) {
  const count = Number(num || 0)
  if (count >= 10000) return (count / 10000).toFixed(1) + 'w'
  if (count >= 1000) return (count / 1000).toFixed(1) + 'k'
  return count
}
</script>

<style scoped lang="scss">
.live-area {
  position: relative;
  width: auto;
  padding: 10px 10px 12px;
  overflow: hidden;
  border-radius: 14px;
  background-repeat: no-repeat;
  background-position: center top;
  background-size: 100% auto;
  box-sizing: border-box;
}

.live-area--default-bg {
  padding-top: 70px;
  background-color: #f6edff;
}

.live-area--custom-bg {
  padding-top: 40px;
}

.diy-section-header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0 0 9px;
}

.live-area--default-bg .diy-section-header {
  position: absolute;
  left: 10px;
  right: 10px;
  top: 31px;
  z-index: 3;
  padding: 0;
}

.diy-section-more {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  height: 22px;
  padding: 0 9px;
  border-radius: 11px;
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
  font-size: 12px;
  line-height: 18px;
  font-weight: 600;
  box-shadow: 0 3px 7px rgba(116, 54, 0, 0.12);
  cursor: pointer;

  i {
    margin-left: 3px;
    font-size: 12px;
  }
}

.diy-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 0;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.86);
  color: #c0c4cc;
  font-size: 12px;
  box-shadow: 0 5px 14px rgba(92, 58, 156, 0.08);

  i {
    margin-bottom: 8px;
    font-size: 32px;
  }
}

.live-hero-card {
  margin-bottom: 12px;
}

.live-hero-card:last-child {
  margin-bottom: 0;
}

.live-hero-cover,
.live-card-cover {
  position: relative;
  overflow: hidden;
  background: #1f2933;

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.live-hero-cover {
  height: 215px;
  border-radius: 14px;
  box-shadow: 0 7px 17px rgba(52, 41, 115, 0.2);
}

.live-mask {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.02), rgba(0, 0, 0, 0.62));
}

.live-badges,
.live-card-badges {
  position: absolute;
  left: 7px;
  right: 7px;
  top: 7px;
  z-index: 2;
  display: flex;
  align-items: center;
}

.live-status {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 7px;
  border-radius: 10px;
  background: rgba(91, 95, 100, 0.9);
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  line-height: 20px;
  white-space: nowrap;
}

.live-status.status-live {
  background: #ff4c7d;
}

.live-status.status-upcoming {
  background: rgba(245, 158, 11, 0.92);
}

.live-status--card {
  height: 17px;
  padding: 0 6px;
  border-radius: 9px;
  font-size: 9px;
  line-height: 17px;
}

.live-dot {
  width: 6px;
  height: 6px;
  margin-right: 6px;
  border-radius: 50%;
  background: #ffffff;
}

.live-hot {
  display: inline-flex;
  align-items: center;
  height: 20px;
  margin-left: 5px;
  padding: 0 7px;
  border-radius: 10px;
  background: rgba(28, 27, 39, 0.58);
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  line-height: 20px;
  white-space: nowrap;
}

.live-hot--card {
  height: 17px;
  padding: 0 6px;
  border-radius: 9px;
  font-size: 9px;
  line-height: 17px;
}

.live-hot i {
  margin-right: 2px;
  color: #ffcf6d;
}

.live-hero-info {
  position: absolute;
  left: 12px;
  right: 105px;
  bottom: 13px;
  z-index: 2;
}

.live-hero-title {
  color: #ffffff;
  font-size: 20px;
  font-weight: 800;
  line-height: 28px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-anchor {
  margin-top: 6px;
  color: rgba(255, 255, 255, 0.88);
  font-size: 12px;
  font-weight: 600;
  line-height: 16px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-hero-action {
  position: absolute;
  right: 12px;
  bottom: 15px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 84px;
  height: 32px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.94);
  color: #2a2434;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 5px 12px rgba(37, 20, 74, 0.12);
}

.live-play-triangle {
  width: 0;
  height: 0;
  margin-right: 4px;
  border-top: 5px solid transparent;
  border-bottom: 5px solid transparent;
  border-left: 7px solid #ff4c7d;
}

.live-small-grid {
  display: flex;
  flex-wrap: wrap;
  margin-left: -5px;
  margin-right: -5px;
  margin-bottom: -10px;
}

.live-card-cell {
  width: 50%;
  padding: 0 5px 10px;
  box-sizing: border-box;
}

.live-card {
  overflow: hidden;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 5px 14px rgba(92, 58, 156, 0.12);
  box-sizing: border-box;
}

.live-card-cover {
  height: 98px;
}

.live-card-body {
  padding: 8px 9px 9px;
  box-sizing: border-box;
}

.live-anchor-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.live-avatar {
  flex-shrink: 0;
  width: 23px;
  height: 23px;
  border: 1px solid #ffffff;
  border-radius: 50%;
  background: #efe7ff;
  object-fit: cover;
  box-sizing: border-box;
}

.live-avatar--empty {
  display: inline-block;
  background: linear-gradient(135deg, #ffe2ee, #e9defc);
}

.live-anchor-name {
  flex: 1;
  min-width: 0;
  margin-left: 5px;
  color: #6f6678;
  font-size: 11px;
  line-height: 16px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-card-footer {
  display: flex;
  align-items: center;
  min-width: 0;
  margin-top: 4px;
}

.live-card-title {
  flex: 1;
  min-width: 0;
  color: #222222;
  font-size: 13px;
  font-weight: 600;
  line-height: 16px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-like {
  flex-shrink: 0;
  margin-left: 6px;
  color: #8f8b96;
  font-size: 11px;
  line-height: 16px;
  white-space: nowrap;
}

.live-like i {
  margin-right: 2px;
  color: #ff6c9d;
}

.live-scroll {
  display: flex;
  overflow-x: auto;
  padding-bottom: 1px;

  &::-webkit-scrollbar {
    display: none;
  }
}

.live-card--scroll {
  flex-shrink: 0;
  width: 165px;
  margin-right: 10px;
}

.live-card--scroll:last-child {
  margin-right: 0;
}

.live-cover-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #626b76;
  background: #2f3131;
}
</style>
