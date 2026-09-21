<template>
  <view class="waterfall-grid">
    <view
      v-for="column in waterfallColumns"
      :key="column.key"
      class="waterfall-grid__column"
    >
      <view
        v-for="item in column.items"
        :key="item.cardKey"
        class="waterfall-grid__item"
        hover-class="waterfall-grid__item--hover"
        @click="handleSelect(item)"
      >
        <view class="waterfall-card">
          <view class="waterfall-card__cover-wrap">
            <image class="waterfall-card__cover" :src="item.cover" mode="aspectFill"></image>
            <view v-if="item.badge" class="waterfall-card__badge" :class="item.badgeClassName">
              <text class="waterfall-card__badge-text">{{ item.badge }}</text>
            </view>
          </view>

          <view class="waterfall-card__body">
            <text class="waterfall-card__title">{{ item.title }}</text>
            <text v-if="item.subtitle" class="waterfall-card__subtitle">{{ item.subtitle }}</text>

            <view v-if="item.displayTags.length" class="waterfall-card__tags">
              <search-tag-pill
                v-for="tag in item.displayTags"
                :key="tag.key"
                :tag="tag"
              ></search-tag-pill>
            </view>

            <view class="waterfall-card__meta">
              <view class="waterfall-card__price-wrap">
                <text v-if="item.pricePrefix" class="waterfall-card__currency">{{ item.pricePrefix }}</text>
                <text class="waterfall-card__price">{{ item.price }}</text>
                <text v-if="item.originalPrice" class="waterfall-card__origin">¥{{ item.originalPrice }}</text>
              </view>
              <text v-if="item.hotText" class="waterfall-card__hot">{{ item.hotText }}</text>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed } from 'vue'
import SearchTagPill from './SearchTagPill.vue'

defineOptions({
  name: 'SearchWaterfallGrid'
})

const props = defineProps({
  items: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['select'])

const displayItems = computed(() => {
  const source = Array.isArray(props.items) ? props.items : []
  return source.map((item, index) => {
    const resultType = String(item.result_type || item.resultType || '')
    const id = Number(item.id || 0)
    const tags = Array.isArray(item.tags) ? item.tags : []
    return {
      ...item,
      cardKey: `${resultType || 'item'}-${id || index}`,
      displayTags: tags.map((tag, tagIndex) => normalizeTag(tag, tagIndex)).filter(Boolean)
    }
  })
})

const waterfallColumns = computed(() => {
  return displayItems.value.reduce((columns, item, index) => {
    columns[index % 2].items.push(item)
    return columns
  }, [
    { key: 'left', items: [] },
    { key: 'right', items: [] }
  ])
})

function handleSelect(item) {
  emit('select', item)
}

function normalizeTag(tag, index = 0) {
  if (typeof tag === 'string' || typeof tag === 'number') {
    const text = String(tag || '').trim()
    return text ? { key: `tag-${index}-${text}`, text } : null
  }

  if (!tag || typeof tag !== 'object') {
    return null
  }

  const text = String(tag.text || tag.name || tag.label || '').trim()
  const icon = String(tag.icon || '').trim()
  if (!text && !icon) {
    return null
  }

  const id = Number(tag.id || 0)
  return {
    ...tag,
    key: String(tag.key || (id > 0 ? `tag-${id}` : `tag-${index}-${text}-${icon}`)),
    text
  }
}
</script>

<style lang="scss" scoped>
.waterfall-grid {
  display: flex;
  align-items: flex-start;
}

.waterfall-grid__column {
  flex: 1;
  min-width: 0;
}

.waterfall-grid__column + .waterfall-grid__column {
  margin-left: 20rpx;
}

.waterfall-grid__item {
  margin-bottom: 24rpx;
}

.waterfall-grid__item:last-child {
  margin-bottom: 0;
}

.waterfall-grid__item--hover {
  opacity: 0.92;
}

.waterfall-card {
  overflow: hidden;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 12rpx 36rpx rgba(0, 0, 0, 0.04);
}

.waterfall-card__cover-wrap {
  position: relative;
  width: 100%;
  height: 320rpx;
  background: #ececec;
}

.waterfall-card__cover {
  width: 100%;
  height: 100%;
}

.waterfall-card__badge {
  position: absolute;
  top: 16rpx;
  left: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 56rpx;
  height: 44rpx;
  padding: 0 14rpx;
  box-sizing: border-box;
  border-radius: 999rpx;
  background: rgba(17, 24, 39, 0.84);
}

.waterfall-card__badge.is-brand {
  background: var(--brand-primary, #ea580c);
}

.waterfall-card__badge.is-muted {
  background: #e5e7eb;
}

.waterfall-card__badge.is-muted .waterfall-card__badge-text {
  color: #4b5563;
}

.waterfall-card__badge.is-dark {
  background: rgba(17, 24, 39, 0.92);
}

.waterfall-card__badge-text {
  display: block;
  font-size: 18rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
  text-align: center;
}

.waterfall-card__body {
  padding: 20rpx 20rpx 22rpx;
}

.waterfall-card__title {
  display: -webkit-box;
  overflow: hidden;
  height: 40rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #1a1c1c;
  text-overflow: ellipsis;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 1;
}

.waterfall-card__subtitle {
  display: -webkit-box;
  overflow: hidden;
  margin-top: 8rpx;
  height: 64rpx;
  font-size: 22rpx;
  line-height: 32rpx;
  color: #6b7280;
  text-overflow: ellipsis;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.waterfall-card__tags {
  display: flex;
  flex-wrap: wrap;
  margin-top: 14rpx;
}

.waterfall-card__meta {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-top: 8rpx;
}

.waterfall-card__price-wrap {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
}

.waterfall-card__currency,
.waterfall-card__price {
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.waterfall-card__currency {
  margin-right: 4rpx;
  font-size: 22rpx;
  line-height: 30rpx;
}

.waterfall-card__price {
  font-size: 36rpx;
  line-height: 1;
}

.waterfall-card__origin {
  margin-left: 8rpx;
  font-size: 18rpx;
  line-height: 24rpx;
  color: #9ca3af;
  text-decoration: line-through;
}

.waterfall-card__hot {
  flex-shrink: 0;
  margin-left: 16rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #6b7280;
}
</style>
