<template>
  <common-wrapper :config="config">
    <div class="coupon-area" :style="areaStyle">
      <div v-if="style !== 'large_image' && title" class="diy-section-header">
        <span class="diy-section-title">{{ title }}</span>
        <span v-if="couponList.length > 0" class="coupon-more">
          更多 <LegacyIcon name="el-icon-arrow-right" />
        </span>
      </div>

      <div v-if="couponList.length === 0" class="diy-empty">
        <LegacyIcon name="el-icon-ticket" />
        <span>暂无优惠券数据</span>
      </div>

      <div v-else-if="style === 'large_image'" class="coupon-poster">
        <img class="coupon-poster-bg" :src="posterBg" alt="">
        <div class="coupon-poster-hero">
          <div class="coupon-poster-title-wrap">
            <div class="coupon-title-decor coupon-title-decor--left">
              <span class="coupon-title-dot-pair">
                <span class="coupon-title-light-dot coupon-title-light-dot--small" />
                <span class="coupon-title-dot coupon-title-dot--small" />
              </span>
              <span class="coupon-title-dot-pair">
                <span class="coupon-title-light-dot coupon-title-light-dot--middle" />
                <span class="coupon-title-dot coupon-title-dot--middle" />
              </span>
              <span class="coupon-title-dot-pair">
                <span class="coupon-title-light-dot coupon-title-light-dot--large" />
                <span class="coupon-title-dot coupon-title-dot--large" />
              </span>
            </div>
            <div class="coupon-poster-title">{{ title || '优惠券' }}</div>
            <div class="coupon-title-decor coupon-title-decor--right">
              <span class="coupon-title-dot-pair">
                <span class="coupon-title-dot coupon-title-dot--large" />
                <span class="coupon-title-light-dot coupon-title-light-dot--large" />
              </span>
              <span class="coupon-title-dot-pair">
                <span class="coupon-title-dot coupon-title-dot--middle" />
                <span class="coupon-title-light-dot coupon-title-light-dot--middle" />
              </span>
              <span class="coupon-title-dot-pair">
                <span class="coupon-title-dot coupon-title-dot--small" />
                <span class="coupon-title-light-dot coupon-title-light-dot--small" />
              </span>
            </div>
          </div>
          <div class="coupon-poster-subtitle">领券购物更优惠</div>
        </div>
        <div class="coupon-poster-list">
          <div
            v-for="(item, index) in posterCouponList"
            :key="item.renderKey || index"
            class="coupon-poster-card"
            :class="{ 'coupon-poster-card--muted': item.disabled }"
          >
            <img class="coupon-poster-orange" :src="orangeBg" alt="">
            <div class="coupon-poster-value">
              <div class="coupon-poster-amount">
                <span v-if="item.amountPrefix" class="coupon-poster-symbol">{{ item.amountPrefix }}</span>
                <span class="coupon-poster-number">{{ item.value }}</span>
                <span v-if="item.amountSuffix" class="coupon-poster-symbol coupon-poster-symbol--suffix">{{ item.amountSuffix }}</span>
              </div>
              <div class="coupon-poster-condition">{{ item.condition }}</div>
              <div class="coupon-poster-source">{{ item.sourceTag }}</div>
            </div>
            <div class="coupon-poster-info">
              <div class="coupon-poster-title-row">
                <span class="coupon-poster-tag">{{ item.tag }}</span>
                <span class="coupon-poster-name">{{ item.name || '优惠券' }}</span>
              </div>
              <div class="coupon-poster-time">有效期至：{{ item.time || '长期有效' }}</div>
              <span v-if="showReceiveBtn" class="coupon-poster-btn">{{ item.buttonText }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="style === 'grid'" class="coupon-grid">
        <div
          v-for="(item, index) in couponList"
          :key="item.renderKey || index"
          class="coupon-poster-card"
          :class="{ 'coupon-poster-card--muted': item.disabled }"
        >
          <img class="coupon-poster-orange" :src="orangeBg" alt="">
          <div class="coupon-poster-value">
            <div class="coupon-poster-amount">
              <span v-if="item.amountPrefix" class="coupon-poster-symbol">{{ item.amountPrefix }}</span>
              <span class="coupon-poster-number">{{ item.value }}</span>
              <span v-if="item.amountSuffix" class="coupon-poster-symbol coupon-poster-symbol--suffix">{{ item.amountSuffix }}</span>
            </div>
            <div class="coupon-poster-condition">{{ item.condition }}</div>
            <div class="coupon-poster-source">{{ item.sourceTag }}</div>
          </div>
          <div class="coupon-poster-info">
            <div class="coupon-poster-title-row">
              <span class="coupon-poster-tag">{{ item.tag }}</span>
              <span class="coupon-poster-name">{{ item.name || '优惠券' }}</span>
            </div>
            <div class="coupon-poster-time">有效期至：{{ item.time || '长期有效' }}</div>
            <span v-if="showReceiveBtn" class="coupon-poster-btn">{{ item.buttonText }}</span>
          </div>
        </div>
      </div>

      <div v-else class="coupon-scroll">
        <div
          v-for="(item, index) in couponList"
          :key="item.renderKey || index"
          class="coupon-poster-card coupon-poster-card--scroll"
          :class="{ 'coupon-poster-card--muted': item.disabled }"
        >
          <img class="coupon-poster-orange" :src="orangeBg" alt="">
          <div class="coupon-poster-value">
            <div class="coupon-poster-amount">
              <span v-if="item.amountPrefix" class="coupon-poster-symbol">{{ item.amountPrefix }}</span>
              <span class="coupon-poster-number">{{ item.value }}</span>
              <span v-if="item.amountSuffix" class="coupon-poster-symbol coupon-poster-symbol--suffix">{{ item.amountSuffix }}</span>
            </div>
            <div class="coupon-poster-condition">{{ item.condition }}</div>
            <div class="coupon-poster-source">{{ item.sourceTag }}</div>
          </div>
          <div class="coupon-poster-info">
            <div class="coupon-poster-title-row">
              <span class="coupon-poster-tag">{{ item.tag }}</span>
              <span class="coupon-poster-name">{{ item.name || '优惠券' }}</span>
            </div>
            <div class="coupon-poster-time">有效期至：{{ item.time || '长期有效' }}</div>
            <span v-if="showReceiveBtn" class="coupon-poster-btn">{{ item.buttonText }}</span>
          </div>
        </div>
      </div>
    </div>
  </common-wrapper>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import CommonWrapper from './CommonWrapper'
import { getCouponList } from '@/api/coupon'
import posterBgDefault from '@/assets/images/coupon-large-bg.png'
import orangeBgDefault from '@/assets/images/coupon-orange-bg.png'

defineOptions({
  name: 'Coupon'
})

const props = defineProps({
  config: {
    type: Object,
    default: null
  }
})

const title = ref('优惠券')
const list = ref([])
const style = ref('scroll')
const columns = ref(2)
const bgColor = ref('#ffffff')
const showReceiveBtn = ref(true)
const lastDataKey = ref(null)
const posterBg = ref(posterBgDefault)
const orangeBg = ref(orangeBgDefault)

const areaStyle = computed(() => ({
  backgroundColor: bgColor.value || 'transparent',
  backgroundImage: posterBg.value ? `url(${posterBg.value})` : '',
  backgroundSize: 'cover',
  backgroundPosition: 'center top',
  backgroundRepeat: 'no-repeat'
}))

const couponList = computed(() => list.value.map((item, index) => mapDisplayItem(item, index)))
const posterCouponList = computed(() => couponList.value)

watch(() => props.config, setConfig, { deep: true, immediate: true })

function setConfig(nVal) {
  if (!nVal) return
  title.value = nVal.title || '优惠券'
  style.value = ['grid', 'large_image'].indexOf(nVal.display_style) > -1 ? nVal.display_style : 'scroll'
  columns.value = nVal.columns || 2
  bgColor.value = nVal.bg_color || '#ffffff'
  showReceiveBtn.value = nVal.show_receive_btn !== false
  posterBg.value = nVal.background_image || posterBgDefault
  checkAndFetch(nVal)
}

function getDataKey(data) {
  const manualIds = (data.manual_ids || [])
    .map(i => (typeof i === 'object' ? i.id : i))
    .sort()
    .join(',')
  return [data.data_source, data.scope_type, data.show_count, manualIds].join('|')
}

function checkAndFetch(data) {
  if (Array.isArray(data.list) && data.list.length > 0) {
    list.value = data.list
    return
  }
  if (Array.isArray(data.items) && data.items.length > 0) {
    list.value = data.items
    return
  }

  const key = getDataKey(data)
  if (key === lastDataKey.value) return
  lastDataKey.value = key
  fetchPreviewData(data)
}

function fetchPreviewData(data) {
  if (data.data_source === 'manual') {
    const ids = data.manual_ids || []
    list.value = ids.map(item =>
      typeof item === 'object'
        ? item
        : { id: item, name: '优惠券 #' + item, price: 0, min_price: 0, time: '' }
    )
    return
  }

  const params = {
    page: 1,
    page_size: data.show_count || 4,
    status: 1
  }
  if (data.scope_type) {
    params.scope_type = data.scope_type
  }
  getCouponList(params).then(res => {
    const items = (res.data && (res.data.list || res.data.data || res.data)) || []
    list.value = Array.isArray(items) ? items : []
  }).catch(() => {
    list.value = []
  })
}

function mapDisplayItem(item, index) {
  const amount = item.reduce_price || item.discount_amount || item.price || 0
  const minPrice = Number(item.full_price || item.min_order_amount || item.min_price || 0)
  const disabled = item.status === 0 || item.disabled === true || item.received === true
  return {
    ...item,
    renderKey: `${item.id || item.coupon_id || item.name || 'coupon'}_${index}`,
    name: item.name || item.title || '',
    amountPrefix: '¥',
    amountSuffix: '',
    value: amount,
    tag: item.scope_type_text || item.tag || '通用',
    sourceTag: Number(item.merchant_id || item.merchantId || 0) > 0 ? '商户卷' : '平台卷',
    condition: minPrice > 0 ? '满' + minPrice + '元可用' : '无门槛',
    time: item.valid_text || (item.expire_day > 0 ? '领取后' + item.expire_day + '天有效' : (item.use_end_time || item.time || '')),
    buttonText: disabled ? '已领取' : '领取',
    disabled
  }
}
</script>

<style scoped lang="scss">
.coupon-area {
  --coupon-theme-primary: var(--brand-primary, var(--theme-primary, var(--prev-color-primary, #ff4500)));
  --coupon-theme-secondary: var(--brand-primary-light, var(--theme-secondary, var(--dashboard-accent-light, #ff7a2f)));
  --coupon-theme-gradient: var(--theme-gradient, linear-gradient(135deg, var(--coupon-theme-secondary) 0%, var(--coupon-theme-primary) 100%));
  --coupon-theme-pale: var(--brand-primary-pale, var(--prev-color-primary-light-9, #fff3ed));
  --coupon-theme-shadow: var(--brand-shadow, var(--dashboard-accent-shadow, rgba(255, 63, 18, 0.22)));
  width: auto;
  padding-bottom: 4px;
  overflow: hidden;
  border-radius: 12px;
}

.diy-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 9px 11px 10px;
}

.diy-section-title {
  color: #1a1c1c;
  font-size: 16px;
  font-weight: 700;
  line-height: 24px;
}

.coupon-more {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  height: 24px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  line-height: 24px;

  i {
    margin-left: 2px;
    font-size: 11px;
  }
}

.diy-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 0;
  color: #c0c4cc;
  font-size: 12px;

  i {
    margin-bottom: 8px;
    font-size: 28px;
  }
}

.coupon-poster {
  position: relative;
  overflow: hidden;
  border-radius: 12px;
  background: #ffffff;
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.coupon-poster:hover {
  box-shadow:
    0 12px 28px rgba(255, 119, 45, 0.18),
    0 2px 12px rgba(255, 183, 92, 0.12);
  transform: translateY(-1px);
}

.coupon-poster-hero {
  position: relative;
  height: 108px;
  padding-top: 24px;
  overflow: hidden;
  box-sizing: border-box;
  text-align: center;
}

.coupon-poster-bg {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 0;
  width: 100%;
  height: auto;
  min-height: 286px;
  object-fit: cover;
  object-position: top center;
}

.coupon-poster-title,
.coupon-poster-subtitle {
  position: relative;
  z-index: 2;
}

.coupon-poster-title-wrap {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.coupon-poster-title {
  max-width: 210px;
  color: #4a2111;
  font-size: 26px;
  font-weight: 800;
  line-height: 34px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-title-decor {
  display: flex;
  align-items: center;
  width: 66px;
  height: 12px;
  flex-shrink: 0;
}

.coupon-title-decor--left {
  justify-content: flex-end;
  margin-right: 9px;
}

.coupon-title-decor--right {
  justify-content: flex-start;
  margin-left: 9px;
}

.coupon-title-dot,
.coupon-title-light-dot {
  display: block;
  flex-shrink: 0;
  border-radius: 50%;
}

.coupon-title-dot {
  background: var(--coupon-theme-primary);
  box-shadow: 0 2px 5px var(--coupon-theme-shadow);
}

.coupon-title-light-dot {
  background: var(--coupon-theme-secondary);
}

.coupon-title-dot-pair {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.coupon-title-decor--left .coupon-title-dot-pair {
  margin-left: 5px;
}

.coupon-title-decor--right .coupon-title-dot-pair {
  margin-right: 5px;
}

.coupon-title-decor--left .coupon-title-light-dot {
  margin-right: 1px;
}

.coupon-title-decor--right .coupon-title-light-dot {
  margin-left: 1px;
}

.coupon-title-dot--small {
  width: 4px;
  height: 4px;
  opacity: 0.5;
}

.coupon-title-dot--middle {
  width: 6px;
  height: 6px;
  opacity: 0.72;
}

.coupon-title-dot--large {
  width: 9px;
  height: 9px;
}

.coupon-title-light-dot--small {
  width: 4px;
  height: 4px;
}

.coupon-title-light-dot--middle {
  width: 6px;
  height: 6px;
}

.coupon-title-light-dot--large {
  width: 9px;
  height: 9px;
}

.coupon-poster-subtitle {
  margin-top: 6px;
  color: #6e3a22;
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
}

.coupon-poster-list {
  position: relative;
  z-index: 1;
  margin-top: -16px;
  padding: 0 12px 14px;
}

.coupon-poster-card {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 94px;
  margin-bottom: 10px;
  overflow: hidden;
  border-radius: 11px;
  border: 1px solid rgba(255, 168, 112, 0.26);
  background: linear-gradient(135deg, #fffdf8 0%, #fff3e8 48%, #fff9f2 100%);
  box-shadow:
    0 9px 21px rgba(255, 91, 30, 0.16),
    0 2px 7px rgba(124, 45, 18, 0.06);
  box-sizing: border-box;
  -webkit-mask-image:
    radial-gradient(circle at 0 50%, transparent 7px, #000 7px),
    radial-gradient(circle at 100% 50%, transparent 7px, #000 7px);
  -webkit-mask-size: 100% 100%, 100% 100%;
  -webkit-mask-repeat: no-repeat, no-repeat;
  -webkit-mask-composite: destination-in;
  mask-image:
    radial-gradient(circle at 0 50%, transparent 7px, #000 7px),
    radial-gradient(circle at 100% 50%, transparent 7px, #000 7px);
  mask-size: 100% 100%, 100% 100%;
  mask-repeat: no-repeat, no-repeat;
  mask-composite: intersect;
}

.coupon-poster-card--muted {
  opacity: 0.62;
  filter: grayscale(1);
}

.coupon-poster-card:last-child {
  margin-bottom: 0;
}

.coupon-poster-orange {
  position: absolute;
  left: -16px;
  top: -34px;
  width: 120px;
  height: auto;
  opacity: 0.72;
  pointer-events: none;
}

.coupon-poster-value,
.coupon-poster-info {
  position: relative;
  z-index: 1;
}

.coupon-poster-value {
  width: 112px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-right: 1px dashed #ffd1ad;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.78) 0%, rgba(255, 242, 229, 0.52) 100%);
  box-sizing: border-box;
}

.coupon-poster-amount {
  display: flex;
  align-items: baseline;
  justify-content: center;
  color: var(--coupon-theme-primary);
  font-weight: 800;
  line-height: 1;
}

.coupon-poster-symbol {
  margin-right: 3px;
  font-size: 18px;
  line-height: 1;
}

.coupon-poster-symbol--suffix {
  margin-left: 3px;
  margin-right: 0;
}

.coupon-poster-number {
  font-size: 40px;
  line-height: 1;
}

.coupon-poster-condition {
  margin-top: 6px;
  color: #2d1c15;
  font-size: 12px;
  line-height: 17px;
}

.coupon-poster-source {
  height: 20px;
  margin-top: 6px;
  padding: 0 9px;
  border: 1px solid var(--coupon-theme-secondary);
  border-radius: 4px;
  background: var(--coupon-theme-pale);
  color: var(--coupon-theme-primary);
  font-size: 10px;
  line-height: 20px;
}

.coupon-poster-info {
  flex: 1;
  min-width: 0;
  padding: 18px 16px 14px 20px;
  box-sizing: border-box;
}

.coupon-poster-title-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.coupon-poster-tag {
  flex-shrink: 0;
  height: 20px;
  padding: 0 7px;
  border-radius: 5px;
  background: var(--coupon-theme-gradient);
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  line-height: 20px;
}

.coupon-poster-name {
  flex: 1;
  min-width: 0;
  margin-left: 8px;
  color: #2b160d;
  font-size: 16px;
  font-weight: 800;
  line-height: 22px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-poster-time {
  margin-top: 12px;
  color: #2f2f2f;
  font-size: 12px;
  line-height: 18px;
}

.coupon-poster-btn {
  display: inline-block;
  width: 100%;
  height: 32px;
  margin-top: 10px;
  padding: 0 18px;
  border-radius: 16px;
  background: var(--coupon-theme-gradient);
  color: #ffffff;
  font-size: 14px;
  font-weight: 800;
  line-height: 32px;
  text-align: center;
  box-shadow: 0 5px 11px var(--coupon-theme-shadow);
}

.coupon-grid {
  display: flex;
  flex-direction: column;
  padding: 0 12px 14px;
}

.coupon-large-card {
  position: relative;
  display: flex;
  min-height: 92px;
  margin-bottom: 12px;
  overflow: hidden;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04), 0 1px 4px rgba(0, 0, 0, 0.02);
}

.coupon-large-card:last-child {
  margin-bottom: 0;
}

.coupon-large-card--muted {
  opacity: 0.62;
  filter: grayscale(1);
}

.coupon-value {
  position: relative;
  width: 112px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-right: 1px dashed #cfc4c5;
  background-color: #ffffff;
  background-image: radial-gradient(rgba(0, 0, 0, 0.04) 1px, transparent 1px);
  background-size: 8px 8px;
}

.coupon-amount,
.coupon-mini-amount {
  display: flex;
  align-items: baseline;
  color: var(--coupon-theme-primary);
  font-weight: 700;
  line-height: 1;
}

.coupon-symbol {
  margin-right: 2px;
  font-size: 14px;
}

.coupon-symbol--suffix {
  margin-left: 2px;
  margin-right: 0;
}

.coupon-number {
  font-size: 36px;
}

.coupon-condition,
.coupon-mini-condition,
.coupon-time {
  margin-top: 6px;
  color: #4c4546;
  font-size: 10px;
  line-height: 14px;
}

.coupon-cut {
  position: absolute;
  right: -8px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #f5f5f5;
}

.coupon-cut--top {
  top: -8px;
}

.coupon-cut--bottom {
  bottom: -8px;
}

.coupon-info {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px;
  box-sizing: border-box;
}

.coupon-main {
  flex: 1;
  min-width: 0;
  margin-right: 10px;
}

.coupon-title-row {
  display: flex;
  align-items: center;
  min-width: 0;
}

.coupon-tag {
  display: inline-block;
  height: 18px;
  padding: 0 6px;
  border-radius: 4px;
  background: var(--coupon-theme-pale);
  color: var(--coupon-theme-primary);
  font-size: 10px;
  font-weight: 700;
  line-height: 18px;
  vertical-align: middle;
}

.coupon-name {
  flex: 1;
  min-width: 0;
  margin-left: 6px;
  color: #1a1c1c;
  font-size: 14px;
  font-weight: 700;
  line-height: 20px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

.coupon-btn,
.coupon-mini-btn {
  flex-shrink: 0;
  height: 30px;
  padding: 0 16px;
  border-radius: 15px;
  background: var(--coupon-theme-primary);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  line-height: 30px;
}

.coupon-scroll {
  display: flex;
  overflow-x: auto;
  padding: 0 12px 14px;

  &::-webkit-scrollbar {
    display: none;
  }
}

.coupon-poster-card--scroll {
  width: 320px;
  flex-shrink: 0;
  margin-right: 12px;
  margin-bottom: 0;
}

.coupon-poster-card--scroll:last-child {
  margin-right: 0;
}

.coupon-mini-card {
  position: relative;
  flex-shrink: 0;
  width: 136px;
  min-height: 150px;
  margin-right: 12px;
  padding: 18px 14px 14px;
  border-radius: 16px;
  background-color: #ffffff;
  background-image: radial-gradient(rgba(0, 0, 0, 0.04) 1px, transparent 1px);
  background-size: 8px 8px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04), 0 1px 4px rgba(0, 0, 0, 0.02);
  box-sizing: border-box;
  text-align: center;
}

.coupon-mini-card:last-child {
  margin-right: 0;
}

.coupon-mini-card--muted {
  opacity: 0.62;
  filter: grayscale(1);
}

.coupon-mini-bar {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 6px;
  background: var(--coupon-theme-primary);
}

.coupon-mini-amount {
  justify-content: center;
  margin-top: 8px;

  .coupon-number {
    font-size: 34px;
  }
}

.coupon-mini-condition {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.coupon-mini-btn {
  display: block;
  margin-top: 16px;
  padding: 0;
}
</style>
