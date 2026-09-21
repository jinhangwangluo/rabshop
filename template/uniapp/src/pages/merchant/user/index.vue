<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="merchant-user-page">
    <app-safe-top background="#ffffff" :auto-back="false" />

    <view class="merchant-header">
      <view class="merchant-header__left">
        <view class="merchant-header__action merchant-header__action--back" hover-class="is-hover" @click="handleBack">
          <text class="iconfont icon-back merchant-header__action-icon"></text>
        </view>
        <text class="merchant-header__title">商家店铺</text>
      </view>
      <view class="merchant-header__action" hover-class="is-hover" @click="handleSearch">
        <text class="iconfont icon-search merchant-header__action-icon merchant-header__action-icon--muted"></text>
      </view>
    </view>

    <scroll-view scroll-y class="merchant-scroll" refresher-enabled :refresher-triggered="refreshing" @refresherrefresh="handleRefresh">
      <view class="merchant-scroll__inner">
        <view v-if="loading && !hasMerchant" class="merchant-state">
          <six-petal-spiral
            :active="true"
            :size="92"
            text=""
            :color="themePrimaryColor"
            :stroke-width="1.4"
            :particle-count="36"
            :trail-span="0.28"
          />
          <text class="merchant-state__text">商家信息加载中...</text>
        </view>

        <view v-else-if="errorMessage" class="merchant-state merchant-state--error">
          <text class="merchant-state__title">{{ errorMessage }}</text>
          <view class="merchant-state__button" hover-class="merchant-state__button--hover" @click="loadMerchantDetail">
            <text>重新加载</text>
          </view>
        </view>

        <template v-else-if="hasMerchant">
          <view class="merchant-hero-card">
            <image v-if="heroBanner" class="merchant-hero-card__banner" :src="heroBanner" mode="aspectFill"></image>
            <view v-else class="merchant-hero-card__fallback">
              <text class="iconfont icon-shopfill merchant-hero-card__fallback-icon"></text>
            </view>
            <view class="merchant-hero-card__mask"></view>

            <view class="merchant-hero-card__content">
              <view class="merchant-hero-card__logo-shell">
                <image v-if="merchantLogo" class="merchant-hero-card__logo" :src="merchantLogo" mode="aspectFit"></image>
                <text v-else class="iconfont icon-shopfill merchant-hero-card__logo-icon"></text>
              </view>
              <view class="merchant-hero-card__info">
                <text class="merchant-hero-card__name">{{ merchantInfo.mer_name }}</text>
                <text class="merchant-hero-card__desc">{{ heroDesc }}</text>
              </view>
              <view
                class="merchant-hero-card__follow"
                :class="{ 'merchant-hero-card__follow--active': merchantInfo.is_followed }"
                hover-class="merchant-hero-card__follow--hover"
                @click.stop="handleToggleFollow"
              >
                <text>{{ followButtonText }}</text>
              </view>
            </view>
          </view>

          <view class="merchant-card merchant-card--intro">
            <text class="merchant-card__title">商家简介</text>
            <text class="merchant-card__summary">{{ merchantInfo.description || merchantInfo.mer_notice || '商家暂未填写简介' }}</text>
            <view v-if="merchantTags.length" class="merchant-tags">
              <text
                v-for="tag in merchantTags"
                :key="tag.text"
                :class="['merchant-tags__item', { 'merchant-tags__item--accent': tag.emphasis }]"
              >
                {{ tag.text }}
              </text>
            </view>
          </view>

          <view v-if="credentialList.length" class="merchant-section">
            <view class="merchant-section__heading">
              <view class="merchant-section__badge">
                <text class="merchant-section__badge-text">✦</text>
              </view>
              <text class="merchant-section__title">业务资质</text>
            </view>

            <view class="merchant-card merchant-card--list">
              <view
                v-for="(item, index) in credentialList"
                :key="item.key"
                :class="['credential-row', { 'credential-row--last': index === credentialList.length - 1 }]"
                hover-class="credential-row--hover"
                @click="handleCredentialClick(item)"
              >
                <view class="credential-row__main">
                  <text class="credential-row__label">{{ item.label }}</text>
                  <text class="credential-row__value">{{ item.value }}</text>
                </view>
                <view :class="['credential-row__meta', item.metaClass]">
                  <text v-if="item.metaType === 'arrow'" class="iconfont icon-right credential-row__meta-icon"></text>
                  <text v-else-if="item.metaType === 'success'" class="iconfont icon-roundcheckfill credential-row__meta-icon"></text>
                  <text v-else class="iconfont icon-selectionfill credential-row__meta-icon"></text>
                </view>
              </view>
            </view>
          </view>

          <view v-if="contactList.length" class="merchant-section merchant-section--plain">
            <text class="merchant-section__title merchant-section__title--plain">联系信息</text>
            <view class="contact-list">
              <view
                v-for="item in contactList"
                :key="item.key"
                class="contact-card"
                hover-class="contact-card--hover"
                @click="handleContactClick(item)"
              >
                <view :class="['contact-card__icon', item.iconClass]">
                  <text :class="['iconfont', item.icon, 'contact-card__icon-font']"></text>
                </view>
                <view class="contact-card__info">
                  <text class="contact-card__label">{{ item.label }}</text>
                  <text class="contact-card__value">{{ item.value }}</text>
                </view>
              </view>
            </view>
          </view>

          <view v-if="hasLocation" class="merchant-section merchant-section--plain">
            <text class="merchant-section__title merchant-section__title--plain">位置</text>
            <view class="merchant-card merchant-card--location" hover-class="merchant-card--hover" @click="handleOpenMap">
              <view v-if="merchantInfo.mer_street_view" class="location-map">
                <image class="location-map__image" :src="merchantInfo.mer_street_view" mode="aspectFill"></image>
              </view>
              <view v-else class="location-map location-map--empty">
                <text class="iconfont icon-locationfill location-map__empty-icon"></text>
              </view>
              <view class="location-detail">
                <view class="location-detail__icon">
                  <text class="iconfont icon-locationfill location-detail__icon-font"></text>
                </view>
                <view class="location-detail__info">
                  <text class="location-detail__name">{{ merchantInfo.mer_name }}</text>
                  <text class="location-detail__address">{{ merchantInfo.mer_address }}</text>
                </view>
              </view>
            </view>
          </view>
        </template>

        <view class="merchant-scroll__bottom-space"></view>
      </view>
    </scroll-view>

    <merchant-bottom-nav active-key="account" account-text="我的" :merchant-id="merchantId" :menu-mode="menuMode" />
    <app-floating-button />
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getMerchantDetail, getMerchantFollowStatus, toggleMerchantFollow } from '@/api/merchant'
import MerchantBottomNav from '../components/MerchantBottomNav.vue'

const EMPTY_MERCHANT_INFO = {
  id: 0,
  mer_name: '',
  mer_phone: '',
  mer_address: '',
  contact_name: '',
  contact_phone: '',
  logo: '',
  mer_avatar: '',
  mer_banner: '',
  mer_street_view: '',
  mer_notice: '',
  description: '',
  mer_keyword: '',
  business_hours: {},
  business_status: 1,
  business_status_text: '',
  qualification: {},
  lat: 0,
  lng: 0,
  mer_class: 2,
  mer_class_text: '',
  follow_count: 0,
  follow_count_text: '',
  is_followed: false,
  total_sales: 0,
  total_orders: 0,
  create_time: ''
}

defineOptions({ name: 'MerchantUserIndex' })

const merchantId = ref(0)
const menuMode = ref('special')
const loading = ref(false)
const followLoading = ref(false)
const refreshing = ref(false)
const errorMessage = ref('')
const merchantInfo = ref({ ...EMPTY_MERCHANT_INFO })

const hasMerchant = computed(() => {
  return Number(merchantInfo.value.id || 0) > 0
})

const heroBanner = computed(() => {
  return String(merchantInfo.value.mer_banner || merchantInfo.value.mer_street_view || '').trim()
})

const merchantLogo = computed(() => {
  return String(merchantInfo.value.logo || merchantInfo.value.mer_avatar || '').trim()
})

const heroDesc = computed(() => {
  const parts = []
  if (merchantInfo.value.business_status_text) {
    parts.push(merchantInfo.value.business_status_text)
  }
  if (merchantInfo.value.follow_count_text) {
    parts.push(merchantInfo.value.follow_count_text)
  }
  if (merchantInfo.value.mer_class_text) {
    parts.push(merchantInfo.value.mer_class_text)
  }
  return parts.join(' · ')
})

const merchantTags = computed(() => {
  const tags = []
  if (merchantInfo.value.business_status_text) {
    tags.push({ text: merchantInfo.value.business_status_text, emphasis: true })
  }
  if (merchantInfo.value.mer_class_text) {
    tags.push({ text: merchantInfo.value.mer_class_text, emphasis: false })
  }
  if (businessHoursText.value) {
    tags.push({ text: businessHoursText.value, emphasis: false })
  }
  const keywordTags = String(merchantInfo.value.mer_keyword || '')
    .split(/[,，\s]+/)
    .map((text) => text.trim())
    .filter((text) => text)
  keywordTags.forEach((text) => {
    tags.push({ text, emphasis: false })
  })
  return tags
})

const businessHoursText = computed(() => {
  const hours = merchantInfo.value.business_hours || {}
  if (hours.is_all_day) {
    return '全天营业'
  }
  const start = String(hours.start || '').trim()
  const end = String(hours.end || '').trim()
  return start && end ? `${start}-${end}` : ''
})

const credentialList = computed(() => {
  const qualification = merchantInfo.value.qualification || {}
  const list = [
    buildCredentialItem('business_license', '营业执照', qualification.business_license),
    buildCredentialItem('legal_person_idcard', '法人身份证', qualification.legal_person_idcard),
    buildCredentialItem('bank_account', '银行账户', qualification.bank_account)
  ].filter((item) => item.value)

  const otherCerts = Array.isArray(qualification.other_certs) ? qualification.other_certs : []
  otherCerts.forEach((cert, index) => {
    const value = typeof cert === 'object' ? String(cert.name || cert.title || cert.url || '').trim() : String(cert || '').trim()
    if (value) {
      list.push(buildCredentialItem(`other_certs_${index}`, '其他资质', value))
    }
  })

  return list
})

const contactList = computed(() => {
  const list = []
  const phone = String(merchantInfo.value.contact_phone || merchantInfo.value.mer_phone || '').trim()
  if (phone) {
    list.push({
      key: 'phone',
      label: '电话支持',
      value: phone,
      icon: 'icon-phone',
      iconClass: 'contact-card__icon--phone'
    })
  }
  if (merchantInfo.value.contact_name) {
    list.push({
      key: 'contact_name',
      label: '联系人',
      value: merchantInfo.value.contact_name,
      icon: 'icon-profile',
      iconClass: 'contact-card__icon--email'
    })
  }
  return list
})

const hasLocation = computed(() => {
  return !!String(merchantInfo.value.mer_address || '').trim()
})

const followButtonText = computed(() => {
  if (followLoading.value) {
    return '处理中'
  }
  return merchantInfo.value.is_followed ? '已关注' : '关注'
})

onLoad((options = {}) => {
  merchantId.value = Number(options.merchant_id || options.merchantId || 0)
  loadMerchantDetail()
})

/**
 * 加载商家店铺详情。
 *
 * @returns {Promise<void>}
 */
async function loadMerchantDetail() {
  if (merchantId.value <= 0) {
    errorMessage.value = '商户ID不能为空'
    clearPageData()
    return
  }

  loading.value = true
  errorMessage.value = ''
  try {
    const res = await getMerchantDetail({ merchant_id: merchantId.value }, { showError: false })
    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '商家店铺加载失败'
      clearPageData()
      return
    }

    merchantInfo.value = normalizeMerchantInfo(res.data || {})
    menuMode.value = res.data.menu?.mode || 'special'
    loadFollowStatus()
  } catch (error) {
    console.error('[merchant-user] loadMerchantDetail failed', error)
    errorMessage.value = '商家店铺加载失败'
    clearPageData()
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

/**
 * 下拉刷新商家店铺详情。
 *
 * @returns {void}
 */
function handleRefresh() {
  refreshing.value = true
  loadMerchantDetail()
}

/**
 * 清空页面数据。
 *
 * @returns {void}
 */
function clearPageData() {
  merchantInfo.value = { ...EMPTY_MERCHANT_INFO }
  menuMode.value = 'special'
  followLoading.value = false
}

/**
 * 规范化接口商户详情，字段名保留接口字段名。
 *
 * @param {Object} data 接口返回商户详情。
 * @returns {Object} 页面商户详情。
 */
function normalizeMerchantInfo(data = {}) {
  const qualification = data.qualification && typeof data.qualification === 'object' ? data.qualification : {}
  const businessHours = data.business_hours && typeof data.business_hours === 'object' ? data.business_hours : {}
  return {
    ...EMPTY_MERCHANT_INFO,
    ...data,
    id: Number(data.id || 0),
    mer_name: String(data.mer_name || '').trim(),
    mer_phone: String(data.mer_phone || '').trim(),
    mer_address: String(data.mer_address || '').trim(),
    contact_name: String(data.contact_name || '').trim(),
    contact_phone: String(data.contact_phone || '').trim(),
    logo: String(data.logo || '').trim(),
    mer_avatar: String(data.mer_avatar || '').trim(),
    mer_banner: String(data.mer_banner || '').trim(),
    mer_street_view: String(data.mer_street_view || '').trim(),
    mer_notice: String(data.mer_notice || '').trim(),
    description: String(data.description || '').trim(),
    mer_keyword: String(data.mer_keyword || '').trim(),
    business_hours: businessHours,
    business_status: Number(data.business_status || 0),
    business_status_text: String(data.business_status_text || '').trim(),
    qualification,
    lat: Number(data.lat || 0),
    lng: Number(data.lng || 0),
    mer_class: Number(data.mer_class || 0),
    mer_class_text: String(data.mer_class_text || '').trim(),
    follow_count: Number(data.follow_count || 0),
    follow_count_text: String(data.follow_count_text || '').trim(),
    is_followed: data.is_followed === true || Number(data.is_followed || 0) === 1,
    total_sales: Number(data.total_sales || 0),
    total_orders: Number(data.total_orders || 0),
    create_time: String(data.create_time || '').trim()
  }
}

/**
 * 静默加载当前用户的店铺关注状态。
 *
 * @returns {Promise<void>}
 */
async function loadFollowStatus() {
  const id = Number(merchantId.value || merchantInfo.value.id || 0)
  if (id <= 0) {
    return
  }

  try {
    const res = await getMerchantFollowStatus({ merchant_id: id }, {
      showError: false,
      loginRedirect: false
    })
    if (!res || res.code !== 200 || !res.data) {
      return
    }

    applyFollowState(res.data)
  } catch (error) {
    console.error('[merchant-user] loadFollowStatus failed', error)
  }
}

/**
 * 点击关注或取消关注店铺。
 *
 * @returns {Promise<void>}
 */
async function handleToggleFollow() {
  const id = Number(merchantId.value || merchantInfo.value.id || 0)
  if (id <= 0 || followLoading.value) {
    return
  }

  followLoading.value = true
  try {
    const res = await toggleMerchantFollow({ merchant_id: id }, { showError: false })
    if (!res || res.code !== 200 || !res.data) {
      if (!res || Number(res.code || 0) !== 401) {
        uni.showToast({ title: (res && res.msg) || '操作失败', icon: 'none' })
      }
      return
    }

    applyFollowState(res.data)
    uni.showToast({
      title: merchantInfo.value.is_followed ? '已关注' : '已取消关注',
      icon: 'none'
    })
  } catch (error) {
    console.error('[merchant-user] handleToggleFollow failed', error)
    uni.showToast({ title: '操作失败，请稍后再试', icon: 'none' })
  } finally {
    followLoading.value = false
  }
}

/**
 * 应用关注状态和粉丝数。
 *
 * @param {Object} data 关注接口返回数据。
 * @returns {void}
 */
function applyFollowState(data = {}) {
  const isFollowed = data.is_followed === true || Number(data.is_followed || 0) === 1
  const followCount = Number(data.follow_count || merchantInfo.value.follow_count || 0)
  merchantInfo.value = {
    ...merchantInfo.value,
    is_followed: isFollowed,
    follow_count: followCount,
    follow_count_text: String(data.follow_count_text || '').trim() || formatCountText(followCount, '粉丝')
  }
}

/**
 * 格式化数量文案。
 *
 * @param {number} value 数量。
 * @param {string} suffix 后缀。
 * @returns {string} 格式化后的文案。
 */
function formatCountText(value = 0, suffix = '') {
  const count = Number(value || 0)
  if (count >= 10000) {
    return `${(count / 10000).toFixed(count % 10000 === 0 ? 0 : 1).replace(/\.0$/, '')}万${suffix}`
  }
  return `${count}${suffix}`
}

/**
 * 构建资质展示项。
 *
 * @param {string} key 字段 key。
 * @param {string} label 展示标签。
 * @param {string} value 展示值。
 * @returns {Object} 资质展示项。
 */
function buildCredentialItem(key, label, value) {
  return {
    key,
    label,
    value: String(value || '').trim(),
    metaType: key === 'business_license' ? 'arrow' : 'success',
    metaClass: key === 'business_license' ? 'credential-row__meta--arrow' : 'credential-row__meta--success'
  }
}

/**
 * 返回上一页；若没有历史记录则回到个人中心。
 *
 * @returns {void}
 */
function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.redirectTo({ url: buildMerchantUrl('/pages/merchant/index/index') })
    }
  })
}

/**
 * 构建携带商户ID的页面地址。
 *
 * @param {string} url 原始地址。
 * @returns {string} 页面地址。
 */
function buildMerchantUrl(url = '') {
  const id = Number(merchantId.value || 0)
  const targetUrl = String(url || '').trim()
  if (!targetUrl || id <= 0) {
    return targetUrl
  }

  return targetUrl.indexOf('?') > -1
    ? `${targetUrl}&merchant_id=${id}`
    : `${targetUrl}?merchant_id=${id}`
}

/**
 * 处理头部搜索按钮点击。
 *
 * @returns {void}
 */
function handleSearch() {
  uni.navigateTo({
    url: buildMerchantUrl('/pages/contents/search/index')
  })
}

/**
 * 处理资质条目点击。
 *
 * @param {Object} item 当前资质项。
 * @returns {void}
 */
function handleCredentialClick(item = {}) {
  if (item.value) {
    uni.showToast({ title: item.label + '已展示', icon: 'none' })
  }
}

/**
 * 处理联系方式点击。
 *
 * @param {Object} item 当前联系方式。
 * @returns {void}
 */
function handleContactClick(item = {}) {
  if (item.key === 'phone') {
    uni.makePhoneCall({
      phoneNumber: String(item.value || '').replace(/[^\d+]/g, ''),
      fail: () => {
        uni.showToast({ title: '拨号失败，请稍后再试', icon: 'none' })
      }
    })
    return
  }

  uni.showToast({
    title: String(item.value || '').trim() || '暂无联系信息',
    icon: 'none'
  })
}

/**
 * 打开地图位置详情。
 *
 * @returns {void}
 */
function handleOpenMap() {
  const latitude = Number(merchantInfo.value.lat || 0)
  const longitude = Number(merchantInfo.value.lng || 0)
  if (!latitude || !longitude) {
    uni.showToast({ title: '暂无坐标信息', icon: 'none' })
    return
  }

  uni.openLocation({
    latitude,
    longitude,
    name: merchantInfo.value.mer_name,
    address: merchantInfo.value.mer_address,
    fail: () => {
      uni.showToast({ title: '地图打开失败', icon: 'none' })
    }
  })
}
</script>

<style lang="scss">
page {
  height: 100%;
  background: #f3f3f3;
}
</style>

<style lang="scss" scoped>
.merchant-user-page {
  height: 100%;
  min-height: 100%;
  background: #f3f3f3;
  display: flex;
  flex-direction: column;
}

.merchant-header {
  height: 96rpx;
  padding: 0 24rpx 0 16rpx;
  border-bottom: 1rpx solid #ddd8d5;
  background: rgba(255, 255, 255, 0.96);
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
}

.merchant-header__left {
  min-width: 0;
  display: flex;
  align-items: center;
  flex: 1;
}

.merchant-header__title {
  overflow: hidden;
  font-size: 36rpx;
  line-height: 56rpx;
  font-weight: 900;
  color: var(--text-title, #1a1c1c);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.merchant-header__action {
  width: 72rpx;
  height: 72rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.merchant-header__action--back {
  margin-right: 8rpx;
}

.merchant-header__action-icon {
  font-size: 34rpx;
  line-height: 34rpx;
  color: var(--brand-primary, #ea580c);
}

.merchant-header__action-icon--muted {
  color: #7d7f86;
}

.is-hover {
  background: rgba(0, 0, 0, 0.04);
}

.merchant-scroll {
  flex: 1;
  min-height: 0;
}

.merchant-scroll__inner {
  padding: 16rpx 28rpx 0;
  box-sizing: border-box;
}

.merchant-state {
  min-height: 520rpx;
  padding: 96rpx 32rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.merchant-state__text {
  margin-top: 18rpx;
  font-size: 24rpx;
  color: var(--text-secondary, #8b9097);
}

.merchant-state__title {
  font-size: 28rpx;
  line-height: 40rpx;
  color: var(--text-secondary, #6b7280);
}

.merchant-state__button {
  margin-top: 20rpx;
  min-width: 160rpx;
  height: 60rpx;
  padding: 0 20rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.merchant-state__button--hover {
  opacity: 0.88;
}

.merchant-state__button text {
  font-size: 24rpx;
  line-height: 1;
  color: #ffffff;
}

.merchant-hero-card,
.merchant-card {
  border-radius: 22rpx;
  overflow: hidden;
  background: #ffffff;
  box-sizing: border-box;
}

.merchant-hero-card {
  position: relative;
  height: 356rpx;
  background: #d9d9d9;
}

.merchant-hero-card__fallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(243, 90, 0, 0.72), rgba(255, 128, 0, 0.72));
}

.merchant-hero-card__fallback-icon {
  font-size: 88rpx;
  line-height: 88rpx;
  color: rgba(255, 255, 255, 0.92);
}

.merchant-hero-card__banner,
.location-map__image {
  width: 100%;
  height: 100%;
  display: block;
}

.merchant-hero-card__mask {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.04) 0%, rgba(0, 0, 0, 0.62) 100%);
}

.merchant-hero-card__content {
  position: absolute;
  left: 28rpx;
  right: 28rpx;
  bottom: 24rpx;
  display: flex;
  align-items: flex-end;
}

.merchant-hero-card__logo-shell {
  width: 96rpx;
  height: 96rpx;
  padding: 10rpx;
  border-radius: 18rpx;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 8rpx 20rpx rgba(15, 23, 42, 0.16);
  box-sizing: border-box;
  flex-shrink: 0;
}

.merchant-hero-card__logo {
  width: 100%;
  height: 100%;
  border-radius: 12rpx;
  display: block;
}

.merchant-hero-card__info {
  flex: 1;
  min-width: 0;
  margin-left: 18rpx;
}

.merchant-hero-card__follow {
  width: 132rpx;
  height: 58rpx;
  margin-left: 18rpx;
  border: 1rpx solid rgba(255, 255, 255, 0.88);
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.96);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  flex-shrink: 0;
}

.merchant-hero-card__follow--active {
  background: rgba(255, 255, 255, 0.18);
}

.merchant-hero-card__follow--hover {
  opacity: 0.84;
}

.merchant-hero-card__follow text {
  font-size: 24rpx;
  line-height: 32rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.merchant-hero-card__follow--active text {
  color: #ffffff;
}

.merchant-hero-card__name {
  display: block;
  font-size: 40rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #ffffff;
  text-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.18);
}

.merchant-hero-card__desc {
  display: block;
  margin-top: 6rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.88);
}

.merchant-card--intro,
.merchant-card--list,
.merchant-card--location,
.contact-card {
  box-shadow: 0 12rpx 28rpx rgba(15, 23, 42, 0.04);
}

.merchant-card--intro {
  margin-top: 22rpx;
  padding: 24rpx 24rpx 20rpx;
}

.merchant-card__title,
.merchant-section__title {
  display: block;
  font-size: 32rpx;
  line-height: 48rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.merchant-card__summary {
  display: block;
  margin-top: 14rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 400;
  color: var(--text-regular, #4c4546);
}

.merchant-tags {
  margin: 18rpx -8rpx -8rpx 0;
}

.merchant-tags__item {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 40rpx;
  margin: 0 8rpx 8rpx 0;
  padding: 0 16rpx;
  border-radius: 999rpx;
  background: #ece7e4;
  font-size: 18rpx;
  line-height: 18rpx;
  color: #6e6764;
}

.merchant-tags__item--accent {
  background: var(--brand-primary, #ea580c);
  color: var(--text-inverse, #ffffff);
}

.merchant-section {
  margin-top: 32rpx;
}

.merchant-section--plain {
  margin-top: 34rpx;
}

.merchant-section__heading {
  display: flex;
  align-items: center;
  margin-bottom: 16rpx;
}

.merchant-section__badge {
  width: 38rpx;
  height: 38rpx;
  margin-right: 10rpx;
  border-radius: 12rpx;
  background: rgba(243, 90, 0, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
}

.merchant-section__badge-text {
  font-size: 20rpx;
  line-height: 20rpx;
  color: var(--brand-primary, #ea580c);
}

.merchant-section__title {
  flex: 1;
}

.merchant-section__title--plain {
  margin-bottom: 16rpx;
}

.merchant-card--list {
  padding: 0 24rpx;
}

.credential-row {
  min-height: 112rpx;
  padding: 20rpx 0;
  border-bottom: 1rpx solid #ece7e4;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
}

.credential-row--last {
  border-bottom: none;
}

.credential-row--hover {
  opacity: 0.82;
}

.credential-row__main {
  flex: 1;
  min-width: 0;
}

.credential-row__label {
  display: block;
  font-size: 20rpx;
  line-height: 28rpx;
  font-weight: 500;
  color: var(--text-secondary, #8b9097);
}

.credential-row__value {
  display: block;
  margin-top: 10rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.credential-row__meta {
  width: 40rpx;
  height: 40rpx;
  margin-left: 20rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.credential-row__meta--arrow {
  background: transparent;
}

.credential-row__meta--success {
  background: #26b36a;
}

.credential-row__meta--shield {
  background: #b3b4bc;
}

.credential-row__meta-icon {
  font-size: 24rpx;
  line-height: 24rpx;
  color: #9fa1a7;
}

.credential-row__meta--success .credential-row__meta-icon,
.credential-row__meta--shield .credential-row__meta-icon {
  color: #ffffff;
}

.contact-card {
  margin-bottom: 16rpx;
  padding: 22rpx 24rpx;
  border-radius: 22rpx;
  background: #ffffff;
  display: flex;
  align-items: center;
}

.contact-card:last-child {
  margin-bottom: 0;
}

.contact-card--hover {
  opacity: 0.86;
}

.contact-card__icon {
  width: 72rpx;
  height: 72rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.contact-card__icon--phone,
.contact-card__icon--email {
  background: rgba(243, 90, 0, 0.08);
}

.contact-card__icon-font {
  font-size: 34rpx;
  line-height: 34rpx;
  color: var(--brand-primary, #ea580c);
}

.contact-card__info {
  flex: 1;
  min-width: 0;
  margin-left: 18rpx;
}

.contact-card__label {
  display: block;
  font-size: 20rpx;
  line-height: 28rpx;
  font-weight: 500;
  color: var(--text-secondary, #8b9097);
}

.contact-card__value {
  display: block;
  margin-top: 8rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.merchant-card--location {
  overflow: hidden;
}

.location-map {
  height: 304rpx;
  background: #cfddd9;
}

.location-map--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(243, 90, 0, 0.12), rgba(243, 90, 0, 0.04));
}

.location-map__empty-icon {
  font-size: 76rpx;
  line-height: 76rpx;
  color: rgba(243, 90, 0, 0.45);
}

.location-detail {
  padding: 22rpx 24rpx 24rpx;
  display: flex;
  align-items: flex-start;
}

.location-detail__icon {
  width: 44rpx;
  height: 44rpx;
  margin-right: 14rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.location-detail__icon-font {
  font-size: 30rpx;
  line-height: 30rpx;
  color: #9f9fa5;
}

.location-detail__info {
  flex: 1;
  min-width: 0;
}

.location-detail__name {
  display: block;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.location-detail__address {
  display: block;
  margin-top: 6rpx;
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 400;
  color: var(--text-regular, #4c4546);
  white-space: pre-line;
}

.merchant-scroll__bottom-space {
  height: 144rpx;
}

</style>
