<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="vip-page">
    <app-safe-top title="VIP 会员中心" background="#fff" title-color="#000000" icon-color="#000000" mp-extra="8rpx" />

    <view class="vip-page__content">
      <view class="vip-card">
        <image class="vip-card__bg" src="./../static/member-card-bg.png" mode="aspectFill"></image>

        <view class="vip-card__top">
          <view class="vip-user">
            <image class="vip-user__avatar" :src="avatarUrl" mode="aspectFill"></image>
            <view class="vip-user__meta">
              <text class="vip-user__name">尊享会员</text>
              <text class="vip-user__status">{{ memberStatusText }}</text>
            </view>
          </view>

          <view class="vip-card__tag">
            <text class="vip-card__tag-text">BLACK GOLD</text>
          </view>
        </view>

        <view class="vip-card__main">
          <text class="vip-card__title">尊享黑金卡</text>
          <text class="vip-card__subtitle">THE EXCLUSIVE BLACK GOLD PRIVILEGE</text>
        </view>
      </view>

      <view v-if="canPurchase" class="section-title-row">
        <text class="section-title-row__title">选择会员套餐</text>
        <text class="section-title-row__tip">限时优惠中</text>
      </view>

      <view v-if="canPurchase" class="plan-list">
        <view
          v-for="plan in displayPackages"
          :key="plan.id"
          :class="['plan-card', { 'plan-card--active': selectedPackageId === plan.id }]"
          @click="selectPackage(plan)"
        >
          <view v-if="plan.tag" class="plan-card__tag">
            <text class="plan-card__tag-text">{{ plan.tag }}</text>
          </view>
          <text class="plan-card__name">{{ plan.name }}</text>
          <view class="plan-card__price-row">
            <text class="plan-card__currency">¥</text>
            <text class="plan-card__price">{{ plan.priceText }}</text>
          </view>
          <text v-if="plan.originalPriceText" class="plan-card__origin">¥{{ plan.originalPriceText }}</text>
        </view>
      </view>

      <view v-if="!displayPackages.length && !loading" class="plan-empty">
        <text class="plan-empty__text">{{ packageEmptyText }}</text>
      </view>

      <view class="benefit-card">
        <text class="benefit-card__title">会员尊享权益</text>
        <view v-if="memberBenefits.length" class="benefit-grid">
          <view v-for="item in memberBenefits" :key="item.key" class="benefit-item">
            <view class="benefit-item__icon">
              <image
                v-if="item.symbol"
                class="benefit-item__image"
                :src="item.symbol"
                mode="aspectFit"
              ></image>
              <view v-else class="benefit-item__image-placeholder"></view>
            </view>
            <text class="benefit-item__label">{{ item.label }}</text>
          </view>
        </view>
        <view v-else-if="!loading" class="benefit-empty">
          <text class="benefit-empty__text">暂无会员权益</text>
        </view>
      </view>
    </view>

    <view class="footer-placeholder"></view>
    <view class="vip-footer">
      <view v-if="canPurchase" class="agreement-row" @click="toggleAgreement">
        <view :class="['agreement-check', { 'agreement-check--active': agreementChecked }]">
          <text v-if="agreementChecked" class="agreement-check__mark">✓</text>
        </view>
        <text class="agreement-row__text">我已阅读并同意</text>
        <text class="agreement-row__link" @click.stop="goAgreement">《{{ agreementTitle }}》</text>
      </view>

      <view class="footer-action">
        <view class="footer-price">
          <text class="footer-price__label">总计金额</text>
          <view class="footer-price__row">
            <text class="footer-price__currency">¥</text>
            <text class="footer-price__value">{{ totalPriceText }}</text>
          </view>
        </view>

        <button class="footer-button" :class="{ 'footer-button--disabled': !canPay }" :loading="submitting" :disabled="!canPay" @click="handlePay">
          {{ payButtonText }}
        </button>
      </view>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { getCurrentUser } from '@/api/auth'
import { getMemberInfo, getMemberPackages, createMemberOrder } from '@/api/member'
import { buildAgreementUrl } from '@/utils/agreement'
import { normalizePublicErrorMessage } from '@/utils/error'
import { invokePayment } from '@/utils/payment'
import { PLATFORM_SCENES, getPlatformScene, getTerminalType } from '@/utils/platform'
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

const DEFAULT_AVATAR = '/static/images/avatar_default.jpg'
const DEFAULT_MEMBER_AGREEMENT_ROUTE = buildAgreementUrl('member', '会员服务协议')

defineOptions({ name: 'UserMemberPage' })

const loading = ref(false)
const submitting = ref(false)
const isMember = ref(false)
const memberEnabled = ref(true)
const memberVisible = ref(true)
const canPurchase = ref(true)
const canRenew = ref(false)
const endTime = ref('')
const avatarUrl = ref(DEFAULT_AVATAR)
const packages = ref([])
const memberBenefits = ref([])
const memberAgreement = ref({
  type: 'member',
  title: '会员服务协议',
  route: DEFAULT_MEMBER_AGREEMENT_ROUTE
})
const selectedPackage = ref(null)
const agreementChecked = ref(false)
const scene = ref('')

const displayPackages = computed(() => {
  return packages.value.map((item, index) => normalizePackage(item, index))
})

const selectedPackageId = computed(() => {
  return selectedPackage.value ? Number(selectedPackage.value.id || 0) : 0
})

const memberStatusText = computed(() => {
  if (!isMember.value) {
    if (!memberVisible.value && !canPurchase.value) {
      return 'VIP 暂未开放'
    }

    return '未开通会员权益'
  }

  return endTime.value ? `有效期至 ${endTime.value}` : '会员权益生效中'
})

const totalPriceText = computed(() => {
  return selectedPackage.value ? formatPrice(selectedPackage.value.price) : '0'
})

const canPay = computed(() => {
  return canPurchase.value && Boolean(selectedPackage.value) && !submitting.value && displayPackages.value.length > 0
})

const payButtonText = computed(() => {
  if (submitting.value) {
    return '支付中'
  }

  if (!canPurchase.value) {
    return isMember.value ? '暂不可续费' : '暂未开放'
  }

  return isMember.value ? '立即续费' : '立即开通'
})

const packageEmptyText = computed(() => {
  if (!canPurchase.value) {
    return isMember.value ? 'VIP 续费暂未开放' : 'VIP 暂未开放'
  }

  return '暂无可购买套餐'
})

const agreementTitle = computed(() => {
  return memberAgreement.value.title || '会员服务协议'
})

onLoad(() => {
  scene.value = getPlatformScene()
  loadPageData()
})

async function loadPageData() {
  loading.value = true

  try {
    const tasks = [
      loadMemberInfo(),
      loadPackages(),
      loadUserInfo()
    ]
    await Promise.all(tasks)
  } finally {
    loading.value = false
  }
}

async function loadMemberInfo() {
  const res = await getMemberInfo({}, { showError: false })
  if (res && res.code === 200 && res.data) {
    isMember.value = Boolean(res.data.is_member)
    memberEnabled.value = res.data.member_enabled === true || Number(res.data.member_enabled || 0) === 1
    memberVisible.value = res.data.member_visible === true || Number(res.data.member_visible || 0) === 1
    canPurchase.value = res.data.can_purchase === true || Number(res.data.can_purchase || 0) === 1
    canRenew.value = res.data.can_renew === true || Number(res.data.can_renew || 0) === 1
    endTime.value = String(res.data.end_time || '')
    memberBenefits.value = Array.isArray(res.data.benefits)
      ? res.data.benefits.map((item, index) => normalizeBenefit(item, index))
      : []
    memberAgreement.value = normalizeAgreement(res.data.agreement || {})
  }
}

async function loadPackages() {
  const res = await getMemberPackages({}, { showError: false })
  const list = res && res.code === 200 && Array.isArray(res.data) ? res.data : []
  packages.value = list

  const normalized = displayPackages.value
  if (!normalized.length) {
    selectedPackage.value = null
    return
  }

  const currentId = selectedPackageId.value
  selectedPackage.value = normalized.find((item) => item.id === currentId)
    || normalized.find((item) => item.tag)
    || normalized[0]
}

async function loadUserInfo() {
  const res = await getCurrentUser({}, { showError: false, loginRedirect: false })
  if (res && res.code === 200 && res.data) {
    const user = res.data.user || res.data.userinfo || {}
    avatarUrl.value = user.avatar || DEFAULT_AVATAR
  }
}

function normalizePackage(item = {}, index = 0) {
  const price = Number(item.price || 0)
  const originalPrice = Number(item.original_price || item.originalPrice || 0)
  return {
    id: Number(item.id || index + 1),
    key: String(item.key || item.package_key || `package-${index}`),
    name: String(item.name || item.title || resolvePackageName(item, index)),
    price,
    priceText: formatPrice(price),
    original_price: originalPrice,
    originalPriceText: originalPrice > price ? formatPrice(originalPrice) : '',
    duration_days: Number(item.duration_days || item.durationDays || 0),
    tag: String(item.tag || '')
  }
}

function resolvePackageName(item = {}, index = 0) {
  const days = Number(item.duration_days || item.durationDays || 0)
  if (days >= 360) return '年卡'
  if (days >= 90) return '季卡'
  if (days >= 30) return '月卡'
  return ['月卡', '年卡', '季卡'][index] || '会员卡'
}

function selectPackage(plan = {}) {
  if (!canPurchase.value) {
    return
  }
  selectedPackage.value = plan
}

function normalizeBenefit(item = {}, index = 0) {
  const key = String(item.key || item.benefit_key || `benefit-${index}`)
  return {
    key,
    label: String(item.label || item.name || item.title || ''),
    symbol: String(item.symbol || ''),
    desc: String(item.desc || item.description || '')
  }
}

function normalizeAgreement(item = {}) {
  const type = String(item.type || 'member')
  const title = String(item.title || '会员服务协议')
  return {
    type,
    title,
    route: buildAgreementUrl(type, title)
  }
}

function toggleAgreement() {
  agreementChecked.value = !agreementChecked.value
}

function goAgreement() {
  uni.navigateTo({
    url: memberAgreement.value.route || DEFAULT_MEMBER_AGREEMENT_ROUTE
  })
}

async function handlePay() {
  if (!canPurchase.value) {
    showToast(isMember.value ? 'VIP 续费暂未开放' : 'VIP 暂未开放')
    return
  }
  if (!selectedPackage.value) {
    showToast('请选择会员套餐')
    return
  }
  if (!agreementChecked.value) {
    showToast('请先阅读并同意会员服务协议')
    return
  }
  if (submitting.value) {
    return
  }

  submitting.value = true

  try {
    const res = await createMemberOrder({
      package_id: selectedPackage.value.id,
      pay_type: 'wechat',
      trade_type: resolveTradeType(),
      terminal: getTerminalType()
    })

    if (!res || res.code !== 200 || !res.data) {
      showToast((res && res.msg) || '会员订单创建失败')
      return
    }

    await invokePayment({
      payType: 'wechat',
      config: res.data.config || {},
      scene: scene.value,
      returnUrl: ''
    })

    showToast('支付已提交')
    loadMemberInfo()
  } catch (error) {
    showToast((error && error.message) || '支付未完成')
  } finally {
    submitting.value = false
  }
}

function resolveTradeType() {
  if (scene.value === PLATFORM_SCENES.MINIAPP) {
    return 'miniapp_jsapi'
  }
  if (scene.value === PLATFORM_SCENES.WECHAT_H5) {
    return 'oa_jsapi'
  }
  return 'h5'
}

function formatPrice(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) {
    return '0'
  }
  return amount % 1 === 0 ? String(Math.trunc(amount)) : amount.toFixed(1)
}

function showToast(title) {
  uni.showToast({
    title: normalizePublicErrorMessage(title, '操作失败'),
    icon: 'none'
  })
}
</script>

<style lang="scss">
page {
  background: #f9f9f9;
}
</style>

<style lang="scss" scoped>
.vip-page {
  min-height: 100%;
  background: #f9f9f9;
}

.vip-page__content {
  padding: 24rpx 32rpx 0;
}

.vip-card {
  position: relative;
  height: 406rpx;
  padding: 48rpx 44rpx;
  border-radius: 32rpx;
  overflow: hidden;
  box-shadow: 0 24rpx 44rpx rgba(0, 0, 0, 0.14);
  box-sizing: border-box;
}

.vip-card__bg {
  position: absolute;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
}

.vip-card__top {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.vip-user {
  display: flex;
  align-items: center;
  min-width: 0;
}

.vip-user__avatar {
  width: 84rpx;
  height: 84rpx;
  border-radius: 50%;
  border: 4rpx solid rgba(212, 175, 55, 0.64);
  background: #2a2a2a;
  flex-shrink: 0;
}

.vip-user__meta {
  margin-left: 24rpx;
  min-width: 0;
}

.vip-user__name {
  display: block;
  font-size: 32rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: #f1f1f1;
}

.vip-user__status {
  display: block;
  margin-top: 4rpx;
  font-size: 24rpx;
  line-height: 32rpx;
  color: #d4af37;
}

.vip-card__tag {
  height: 58rpx;
  padding: 0 32rpx;
  border-radius: 999rpx;
  border: 2rpx solid rgba(212, 175, 55, 0.38);
  background: rgba(212, 175, 55, 0.16);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.vip-card__tag-text {
  font-size: 24rpx;
  line-height: 1;
  font-weight: 700;
  color: #d4af37;
}

.vip-card__main {
  position: absolute;
  z-index: 1;
  left: 44rpx;
  right: 44rpx;
  bottom: 52rpx;
}

.vip-card__title {
  display: block;
  font-size: 54rpx;
  line-height: 66rpx;
  font-weight: 800;
  letter-spacing: 4rpx;
  color: #d4af37;
}

.vip-card__subtitle {
  display: block;
  margin-top: 18rpx;
  font-size: 24rpx;
  line-height: 32rpx;
  color: rgba(255, 255, 255, 0.58);
}

.section-title-row {
  margin-top: 46rpx;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-title-row__title {
  font-size: 34rpx;
  line-height: 44rpx;
  font-weight: 700;
  color: #000000;
}

.section-title-row__tip {
  font-size: 22rpx;
  line-height: 30rpx;
  color: #4c4546;
}

.plan-list {
  margin-top: 30rpx;
  display: flex;
  justify-content: space-between;
}

.plan-card {
  position: relative;
  width: 200rpx;
  height: 180rpx;
  padding: 32rpx 20rpx 24rpx;
  border-radius: 24rpx;
  border: 4rpx solid transparent;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
}

.plan-card--active {
  border-color: #d4af37;
}

.plan-card__tag {
  position: absolute;
  top: -24rpx;
  left: 50%;
  width: 90rpx;
  height: 36rpx;
  margin-left: -45rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  display: flex;
  align-items: center;
  justify-content: center;
}

.plan-card__tag-text {
  font-size: 20rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}

.plan-card__name {
  font-size: 30rpx;
  line-height: 40rpx;
  font-weight: 700;
  color: #111111;
}

.plan-card__price-row {
  margin-top: 14rpx;
  display: flex;
  align-items: baseline;
}

.plan-card__currency {
  font-size: 22rpx;
  line-height: 1;
  font-weight: 700;
  color: #111111;
}

.plan-card__price {
  font-size: 34rpx;
  line-height: 1;
  font-weight: 800;
  color: #111111;
}

.plan-card__origin {
  margin-top: 12rpx;
  font-size: 22rpx;
  line-height: 28rpx;
  color: #4c4546;
  text-decoration: line-through;
}

.plan-empty {
  margin-top: 30rpx;
  height: 140rpx;
  border-radius: 24rpx;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.plan-empty__text {
  font-size: 24rpx;
  line-height: 32rpx;
  color: #8a8a8a;
}

.benefit-card {
  margin-top: 46rpx;
  padding: 36rpx 28rpx 18rpx;
  border-radius: 28rpx;
  background: #ffffff;
}

.benefit-card__title {
  display: block;
  font-size: 34rpx;
  line-height: 44rpx;
  font-weight: 700;
  color: #000000;
}

.benefit-grid {
  margin-top: 36rpx;
  display: flex;
  flex-wrap: wrap;
}

.benefit-item {
  width: 25%;
  margin-bottom: 38rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-sizing: border-box;
}

.benefit-item__icon {
  width: 88rpx;
  height: 88rpx;
  border-radius: 50%;
  background: #eeeeee;
  display: flex;
  align-items: center;
  justify-content: center;
}

.benefit-item__image {
  width: 88rpx;
  height: 88rpx;
  display: block;
}

.benefit-item__image-placeholder {
  width: 24rpx;
  height: 24rpx;
  border-radius: 50%;
  background: #d4af37;
}

.benefit-item__label {
  margin-top: 18rpx;
  font-size: 24rpx;
  line-height: 32rpx;
  color: #1a1c1c;
}

.benefit-empty {
  height: 160rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.benefit-empty__text {
  font-size: 24rpx;
  line-height: 32rpx;
  color: #8a8a8a;
}

.footer-placeholder {
  height: 220rpx;
}

.vip-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 26rpx 30rpx 30rpx;
  border-top: 1rpx solid #e8e8e8;
  background: rgba(249, 249, 249, 0.96);
  box-sizing: border-box;
  z-index: 20;
}

.agreement-row {
  display: flex;
  align-items: center;
  min-height: 42rpx;
}

.agreement-check {
  width: 28rpx;
  height: 28rpx;
  margin-right: 18rpx;
  border-radius: 8rpx;
  border: 3rpx solid #cfc4c5;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.agreement-check--active {
  border-color: var(--brand-primary, #ea580c);
  background: var(--brand-primary, #ea580c);
}

.agreement-check__mark {
  font-size: 20rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}

.agreement-row__text,
.agreement-row__link {
  font-size: 22rpx;
  line-height: 32rpx;
  color: #4c4546;
}

.agreement-row__link {
  color: #1a1c1c;
}

.footer-action {
  margin-top: 28rpx;
  display: flex;
  align-items: center;
}

.footer-price {
  width: 150rpx;
  flex-shrink: 0;
}

.footer-price__label {
  display: block;
  font-size: 22rpx;
  line-height: 28rpx;
  color: #4c4546;
}

.footer-price__row {
  margin-top: 10rpx;
  display: flex;
  align-items: baseline;
  color: var(--brand-primary, #ea580c);
}

.footer-price__currency {
  font-size: 30rpx;
  line-height: 1;
  font-weight: 700;
}

.footer-price__value {
  font-size: 44rpx;
  line-height: 1;
  font-weight: 800;
}

.footer-button {
  flex: 1;
  height: 86rpx;
  margin-left: 20rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  box-shadow: 0 14rpx 28rpx rgba(255, 69, 0, 0.18);
  font-size: 34rpx;
  line-height: 86rpx;
  font-weight: 700;
  color: #ffffff;
}

.footer-button--disabled {
  opacity: 0.55;
}

/* #ifdef H5 */
.vip-footer {
  bottom: var(--window-bottom);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.vip-footer {
  padding-bottom: calc(30rpx + env(safe-area-inset-bottom));
}
/* #endif */
</style>
