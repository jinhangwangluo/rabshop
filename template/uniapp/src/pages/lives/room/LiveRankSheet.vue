<template>
  <view
    v-if="visible"
    class="sheet-panel sheet-panel--rank"
    :class="panelClass"
    @click.stop
    @touchmove.stop.prevent="noop"
  >
    <view class="sheet-panel__header">
      <text class="sheet-panel__title">直播间用户</text>
      <view class="sheet-panel__close" @click="$emit('close')">
        <text class="iconfont icon-close"></text>
      </view>
    </view>

    <scroll-view scroll-y class="sheet-panel__scroll sheet-panel__scroll--rank">
      <template v-if="rankUsers.length">
        <view v-if="rankTopThree.length" class="rank-podium">
          <view
            v-for="(item, index) in rankTopThree"
            :key="item.userId || index"
            class="rank-podium__card"
            :class="item.podiumClass"
          >
            <view class="rank-podium__badge">
              <text v-if="index === 0" class="iconfont icon-huiyuanhuangguanguanjun-xianxing rank-podium__badge-icon"></text>
              <text v-else>{{ index + 1 }}</text>
            </view>
            <image v-if="item.avatar" class="rank-podium__avatar" :src="item.avatar" mode="aspectFill" />
            <view v-else class="rank-podium__avatar rank-podium__avatar--fallback">
              <text>{{ item.initial }}</text>
            </view>
            <text class="rank-podium__name">{{ item.name }}</text>
            <text class="rank-podium__score">{{ item.score }}</text>
          </view>
        </view>

        <view class="rank-subhead">
          <text class="rank-subhead__count">全部在线用户 ({{ onlineUserCountText }})</text>
          <text class="iconfont icon-liebiao rank-subhead__icon"></text>
        </view>

        <view class="rank-user-list">
          <view v-for="(item, index) in filteredRankUsers" :key="item.userId || index" class="rank-user-card" :class="item.highlight ? 'rank-user-card--highlight' : ''">
            <view class="rank-user-card__left">
              <image v-if="item.avatar" class="rank-user-card__avatar" :src="item.avatar" mode="aspectFill" />
              <view v-else class="rank-user-card__avatar rank-user-card__avatar--fallback">
                <text>{{ item.initial }}</text>
              </view>
              <view class="rank-user-card__body">
                <view class="rank-user-card__name-row">
                  <text class="rank-user-card__name">{{ item.name }}</text>
                  <text class="rank-user-card__level">{{ item.level }}</text>
                </view>
                <text class="rank-user-card__meta">{{ item.meta }}</text>
              </view>
            </view>
          </view>
        </view>
      </template>
      <view v-else class="sheet-empty-state">
        <text class="sheet-empty-state__title">暂无在线用户数据</text>
        <text class="sheet-empty-state__desc">用户进入直播间后会展示在这里。</text>
      </view>
    </scroll-view>

    <view class="rank-searchbar">
      <text class="iconfont icon-search rank-searchbar__icon"></text>
      <input
        :value="keyword"
        class="rank-searchbar__input"
        placeholder="搜索用户名称"
        placeholder-class="rank-searchbar__placeholder"
        @input="handleKeywordInput"
      />
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
import { computed, ref } from 'vue'

defineOptions({ name: 'LiveRankSheet' })

const props = defineProps({
  visible: Boolean,
  animationState: {
    type: String,
    default: ''
  },
  landscape: Boolean,
  rankUsers: {
    type: Array,
    default: () => []
  },
  onlineUserCountText: {
    type: String,
    default: '0'
  }
})

const emit = defineEmits(['close'])

const keyword = ref('')

const panelClass = computed(() => {
  const classNames = []
  if (props.animationState === 'enter') classNames.push('sheet-panel--enter')
  if (props.landscape) classNames.push('sheet-panel--landscape')
  return classNames.join(' ')
})

const rankTopThree = computed(() => {
  return props.rankUsers
    .filter((item) => Number(item.score || 0) > 0)
    .slice(0, 3)
    .map((item, index) => ({
      ...item,
      podiumClass: `rank-podium__card--${index + 1}`
    }))
})

const filteredRankUsers = computed(() => {
  const rawKeyword = String(keyword.value || '').trim().toLowerCase()
  if (!rawKeyword) return props.rankUsers
  return props.rankUsers.filter((item) => String(item.name || '').toLowerCase().includes(rawKeyword))
})

function noop() {}

function handleKeywordInput(event = {}) {
  keyword.value = event.detail ? event.detail.value : ''
}
</script>

<style lang="scss" src="./style.scss"></style>
