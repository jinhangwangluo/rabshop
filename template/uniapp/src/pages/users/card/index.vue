<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="card-page">
    <app-safe-top title="添加银行卡" background="#ffffff" />

    <view class="card-page__content">
      <view class="section-heading">
        <view class="section-heading__bar"></view>
        <text class="section-heading__text">卡片信息</text>
      </view>

      <view class="field-card field-card--readonly">
        <text class="field-card__label">持卡人</text>
        <input
            v-model.trim="form.holderName"
            class="field-card__input"
            maxlength="19"
            placeholder="请输入持卡人姓名"
            placeholder-class="field-card__placeholder"
          />
      </view>

      <view class="field-card field-card--row">
        <view class="field-card__main">
          <text class="field-card__label">卡号</text>
          <input
            v-model.trim="form.cardNo"
            class="field-card__input"
            type="number"
            maxlength="19"
            placeholder="请输入银行卡号"
            placeholder-class="field-card__placeholder"
          />
        </view>
      </view>

      <view class="field-card field-card--tap" @click="handleChooseBank">
        <view class="field-card__main">
          <text class="field-card__label">开户银行</text>
          <text :class="['field-card__value', { 'field-card__value--placeholder': !selectedBankName }]">
            {{ selectedBankName || '选择发卡银行' }}
          </text>
        </view>
        <uni-icons type="right" size="16" color="#8d8788"></uni-icons>
      </view>

      <view v-if="form.isOtherBank" class="field-card">
        <text class="field-card__label">开户银行名称</text>
        <input
          v-model.trim="form.bankName"
          class="field-card__input"
          maxlength="40"
          placeholder="请输入开户银行名称"
          placeholder-class="field-card__placeholder"
        />
      </view>

      <view class="field-card">
        <text class="field-card__label">手机号</text>
        <view class="phone-row">
          <text class="phone-row__prefix">+86</text>
          <input
            v-model.trim="form.phone"
            class="field-card__input phone-row__input"
            type="number"
            maxlength="11"
            placeholder="银行预留手机号"
            placeholder-class="field-card__placeholder"
          />
        </view>
      </view>
    </view>

    <view class="footer-bar">
      <button class="submit-button" :loading="submitLoading" @click="handleSubmit">
        {{ submitButtonText }}
      </button>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { getCurrentUser } from '@/api/auth'
import { getBankCard, saveBankCard } from '@/api/user'
import { getUserInfo as getLocalUserInfo, isLoggedIn, navigateToLogin } from '@/utils/auth'
import { normalizePublicErrorMessage } from '@/utils/error'
import { computed, ref, watch } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

defineOptions({ name: 'UserBankCardPage' })

const submitLoading = ref(false)
const profile = ref({})
const bankOptions = ref([
  { label: '中国工商银行', shortName: '工商银行' },
  { label: '中国建设银行', shortName: '建设银行' },
  { label: '中国农业银行', shortName: '农业银行' },
  { label: '中国银行', shortName: '中国银行' },
  { label: '招商银行', shortName: '招商银行' },
  { label: '交通银行', shortName: '交通银行' },
  { label: '其他银行', shortName: '', isOther: true }
])
const form = ref({
  holderName: '',
  cardNo: '',
  bankName: '',
  phone: '',
  isOtherBank: false
})

/**
 * 当前页面展示的持卡人姓名。
 *
 * @returns {string} 优先显示用户真实姓名，其次显示昵称。
 */
const displayHolderName = computed(() => {
  return resolveHolderName()
})

/**
 * 提交按钮文案。
 *
 * @returns {string} 根据是否已有银行卡展示文案。
 */
const submitButtonText = computed(() => {
  return form.value.cardNo ? '确认并保存卡片' : '确认并添加卡片'
})

/**
 * 当前已选择的银行简称。
 *
 * @returns {string} 展示给页面的银行名称。
 */
const selectedBankName = computed(() => {
  if (form.value.isOtherBank) {
    return '其他银行'
  }
  return String(form.value.bankName || '').trim()
})

watch(displayHolderName, (value) => {
  if (!form.value.holderName && value) {
    form.value.holderName = value
  }
})

onLoad(() => {
  initProfile()
})

/**
 * 解析默认持卡人姓名。
 *
 * @returns {string} 用户实名或昵称。
 */
function resolveHolderName() {
  const profileData = profile.value || {}
  const user = profileData.user || profileData.userinfo || profileData
  const candidates = [user.real_name, user.realName, user.nickname, user.name]

  for (let index = 0; index < candidates.length; index += 1) {
    const value = String(candidates[index] || '').trim()
    if (value) {
      return value
    }
  }

  return ''
}

/**
 * 初始化页面展示资料。
 *
 * 优先读取本地缓存用户信息，登录后再同步最新资料，用于回填持卡人姓名。
 *
 * @returns {Promise<void>} 初始化完成后刷新页面展示。
 */
async function initProfile() {
  const localProfile = getLocalUserInfo()
  if (localProfile && Object.keys(localProfile).length) {
    profile.value = localProfile
  }

  if (!isLoggedIn()) {
    navigateToLogin()
    return
  }

  try {
    const [res, bankRes] = await Promise.all([
      getCurrentUser({}, { showError: false, loginRedirect: false }),
      getBankCard({}, { showError: false, loginRedirect: false })
    ])
    const profileData = res && res.data ? res.data : {}
    if (profileData && Object.keys(profileData).length) {
      profile.value = profileData
    }

    const bankCard = resolveBankCardPayload(bankRes)
    if (bankCard) {
      applyBankCard(bankCard)
    } else if (!form.value.holderName) {
      form.value.holderName = resolveHolderName()
    }
  } catch (error) {
    if (!form.value.holderName) {
      form.value.holderName = resolveHolderName()
    }
  }
}

/**
 * 回填银行卡数据。
 *
 * @param {Object} data 银行卡接口数据。
 * @returns {void}
 */
function applyBankCard(data = {}) {
  const bankName = String(data.bank_name || data.bankName || '')
  const isKnownBank = bankOptions.value.some((item) => !item.isOther && item.shortName === bankName)
  form.value.holderName = String(data.holder_name || data.holderName || resolveHolderName())
  form.value.cardNo = String(data.card_no || data.cardNo || '')
  form.value.bankName = bankName
  form.value.isOtherBank = Boolean(bankName && !isKnownBank)
  form.value.phone = String(data.reserved_phone || data.reservedPhone || '')
}

/**
 * 从接口响应中提取银行卡数据。
 *
 * 兼容 `user/bank-card` 直接返回银行卡对象，以及用户资料接口内嵌的 `bank_card`。
 *
 * @param {Object} response 接口统一响应。
 * @returns {Object|null} 存在可回填字段时返回银行卡数据。
 */
function resolveBankCardPayload(response = {}) {
  if (!response || response.code !== 200 || !response.data) {
    return null
  }

  const data = response.data
  const user = data.user || data.userinfo || data
  const bankCard = data.bank_card || data.bankCard || user.bank_card || user.bankCard || data
  const hasPayload = Boolean(
    bankCard &&
    (
      bankCard.has_card ||
      bankCard.hasCard ||
      bankCard.card_no ||
      bankCard.cardNo ||
      bankCard.bank_name ||
      bankCard.bankName
    )
  )

  return hasPayload ? bankCard : null
}

/**
 * 返回上一页。
 *
 * @returns {void}
 */
function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.switchTab({ url: '/pages/user/index' })
    }
  })
}

/**
 * 选择发卡银行。
 *
 * @returns {void}
 */
function handleChooseBank() {
  uni.showActionSheet({
    itemList: bankOptions.value.map((item) => item.label),
    success: ({ tapIndex }) => {
      const current = bankOptions.value[Number(tapIndex)]
      if (!current) {
        return
      }
      form.value.isOtherBank = Boolean(current.isOther)
      form.value.bankName = current.isOther ? '' : current.shortName
    }
  })
}

/**
 * 校验银行卡表单。
 *
 * @returns {boolean} 校验通过返回 true。
 */
function validateForm() {
  const cardNo = String(form.value.cardNo || '').replace(/\s+/g, '')
  const phone = String(form.value.phone || '').trim()
  const holderName = String(form.value.holderName || '').trim()

  if (!holderName) {
    uni.showToast({
      title: '请输入持卡人姓名',
      icon: 'none'
    })
    return false
  }

  if (!/^\d{12,19}$/.test(cardNo)) {
    uni.showToast({
      title: '请输入正确的银行卡号',
      icon: 'none'
    })
    return false
  }

  if (!String(form.value.bankName || '').trim()) {
    uni.showToast({
      title: form.value.isOtherBank ? '请输入开户银行名称' : '请选择开户银行',
      icon: 'none'
    })
    return false
  }

  if (!/^1\d{10}$/.test(phone)) {
    uni.showToast({
      title: '请输入正确的预留手机号',
      icon: 'none'
    })
    return false
  }

  return true
}

/**
 * 提交添加银行卡表单。
 *
 * @returns {Promise<void>} 保存完成后返回上一页。
 */
async function handleSubmit() {
  if (submitLoading.value) {
    return
  }

  if (!validateForm()) {
    return
  }

  submitLoading.value = true
  try {
    const res = await saveBankCard({
      holder_name: form.value.holderName,
      card_no: String(form.value.cardNo || '').replace(/\s+/g, ''),
      bank_name: String(form.value.bankName || '').trim(),
      reserved_phone: form.value.phone
    })
    if (!res || res.code !== 200) {
      uni.showToast({
        title: (res && res.msg) || '保存失败',
        icon: 'none'
      })
      return
    }
    submitLoading.value = false
    uni.showToast({
      title: '银行卡已添加',
      icon: 'success'
    })

    setTimeout(() => {
      handleBack()
    }, 450)
  } catch (error) {
    uni.showToast({
      title: normalizePublicErrorMessage(error && (error.msg || error.message || error.errMsg), '保存失败'),
      icon: 'none'
    })
  } finally {
    submitLoading.value = false
  }
}
</script>

<style lang="scss">
page {
  background: #ececec;
}
</style>

<style lang="scss" scoped>
.card-page {
  min-height: 100%;
  background: #ececec;
  color: var(--text-title, #1a1c1c);
}

.card-page__content {
  position: relative;
  padding: 56rpx 30rpx 220rpx;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0) 38%, rgba(255, 255, 255, 0.16) 100%);
    pointer-events: none;
  }
}

.section-heading {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  margin-bottom: 34rpx;
  padding-left: 10rpx;

  &__bar {
    width: 8rpx;
    height: 40rpx;
    border-radius: 999rpx;
    background: var(--brand-primary, #ea580c);
  }

  &__text {
    margin-left: 16rpx;
    font-size: 32rpx;
    line-height: 44rpx;
    font-weight: 700;
    color: #4c4546;
  }
}

.field-card {
  position: relative;
  z-index: 1;
  margin-bottom: 28rpx;
  padding: 24rpx 26rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 12rpx 28rpx rgba(17, 24, 39, 0.05);

  &--readonly {
    padding-top: 22rpx;
    padding-bottom: 22rpx;
  }

  &--row,
  &--tap {
    display: flex;
    align-items: center;
  }

  &--tap {
    justify-content: space-between;
  }

  &__main {
    flex: 1;
    min-width: 0;
  }

  &__label {
    display: block;
    font-size: 20rpx;
    line-height: 28rpx;
    color: #7c7576;
    letter-spacing: 1rpx;
  }

  &__value {
    display: block;
    margin-top: 10rpx;
    font-size: 36rpx;
    line-height: 48rpx;
    font-weight: 700;
    color: #5b5f64;

    &--strong {
      font-size: 40rpx;
      line-height: 56rpx;
      color: #1a1c1c;
    }

    &--placeholder {
      color: #5f6368;
    }
  }

  &__input {
    width: 100%;
    height: 56rpx;
    margin-top: 10rpx;
    font-size: 36rpx;
    line-height: 48rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__placeholder {
    color: #5f6368;
    font-size: 36rpx;
    font-weight: 700;
  }

  &__action {
    width: 64rpx;
    height: 64rpx;
    margin-left: 18rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
}

.phone-row {
  display: flex;
  align-items: center;
  margin-top: 10rpx;

  &__prefix {
    margin-right: 12rpx;
    font-size: 36rpx;
    line-height: 48rpx;
    font-weight: 700;
    color: #1a1c1c;
    flex-shrink: 0;
  }

  &__input {
    margin-top: 0;
  }
}

.camera-icon {
  position: relative;
  width: 36rpx;
  height: 24rpx;
  border: 4rpx solid var(--brand-primary, #ea580c);
  border-radius: 8rpx;
  box-sizing: border-box;

  &::before {
    content: '';
    position: absolute;
    top: -8rpx;
    left: 8rpx;
    width: 12rpx;
    height: 8rpx;
    border-top-left-radius: 4rpx;
    border-top-right-radius: 4rpx;
    background: var(--brand-primary, #ea580c);
  }

  &__lens {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 10rpx;
    height: 10rpx;
    border: 3rpx solid var(--brand-primary, #ea580c);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    box-sizing: border-box;
  }
}

.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 18rpx 30rpx 24rpx;
  background: rgba(236, 236, 236, 0.96);
  border-top: 1rpx solid rgba(214, 214, 214, 0.85);
}

/* #ifdef MP-WEIXIN */
.footer-bar {
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
}
/* #endif */

/* #ifdef H5 */
.footer-bar {
  bottom: var(--window-bottom, 0);
}
/* #endif */

.submit-button {
  width: 100%;
  height: 92rpx;
  border: none;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 32rpx;
  line-height: 44rpx;
  font-weight: 700;
  box-shadow: 0 12rpx 24rpx rgba(0, 0, 0, 0.14);

  &::after {
    border: none;
  }
}
</style>
