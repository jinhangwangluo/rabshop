<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="merchant-settled-page">
    <app-safe-top title="商户入驻" background="#f5f5f5" show-back emit-back @back="handleBack" />

    <view class="merchant-settled-page__container">
      <text v-if="isReviewing" class="merchant-settled-page__stage-title">商户入驻 - 审核中</text>

      <view class="progress-panel">
        <view class="progress-steps">
          <view class="progress-steps__line"></view>
          <view class="progress-steps__line-fill" :style="{ width: progressLineFillWidth }"></view>
          <view
            v-for="item in progressSteps"
            :key="item.key"
            class="progress-steps__item"
          >
            <view
              class="progress-steps__circle"
              :class="[item.circleClass, { 'progress-steps__circle--active-ring': item.activeRing }]"
            >
              <text class="progress-steps__circle-text">{{ item.text }}</text>
            </view>
            <text class="progress-steps__label" :class="item.labelClass">{{ item.label }}</text>
          </view>
        </view>
      </view>

      <template v-if="isEditing">
        <view class="card-section">
          <view class="section-title-row">
            <text class="section-title section-title--no-margin">身份认证</text>
            <text class="section-required-tag">必填</text>
          </view>
          <text class="section-desc">请上传身份证人像面与国徽面，用于核验入驻主体身份。</text>
          <view class="upload-grid">
            <view class="upload-grid__item">
              <view class="upload-tile" @click="handleChooseImage('idFrontUrl')">
                <image v-if="form.idFrontUrl" :src="form.idFrontUrl" class="upload-tile__image" mode="aspectFill"></image>
                <template v-else>
                  <text class="upload-tile__icon">＋</text>
                  <text class="upload-tile__title">上传身份证人像面</text>
                </template>
              </view>
              <view class="upload-grid__caption-row">
                <text class="upload-grid__caption">身份证人像面</text>
                <text class="upload-grid__caption-required">必填</text>
              </view>
            </view>
            <view class="upload-grid__item">
              <view class="upload-tile" @click="handleChooseImage('idBackUrl')">
                <image v-if="form.idBackUrl" :src="form.idBackUrl" class="upload-tile__image" mode="aspectFill"></image>
                <template v-else>
                  <text class="upload-tile__icon">＋</text>
                  <text class="upload-tile__title">上传身份证国徽面</text>
                </template>
              </view>
              <view class="upload-grid__caption-row">
                <text class="upload-grid__caption">身份证国徽面</text>
                <text class="upload-grid__caption-required">必填</text>
              </view>
            </view>
          </view>
        </view>

        <view class="card-section">
          <view class="section-head">
            <text class="section-title section-title--no-margin">工商信息</text>
            <text class="section-tag">选填</text>
          </view>
          <view class="license-tile" @click="handleChooseImage('licenseUrl')">
            <image v-if="form.licenseUrl" :src="form.licenseUrl" class="license-tile__image" mode="aspectFill"></image>
            <template v-else>
              <text class="license-tile__icon">▣</text>
              <text class="license-tile__title">上传营业执照</text>
              <text class="license-tile__desc">支持 JPG、PNG 格式</text>
            </template>
          </view>
        </view>

        <view class="card-section">
          <view class="section-title-row">
            <text class="section-title section-title--no-margin">联系人详情</text>
            <text class="section-required-tag">必填</text>
          </view>
          <text class="section-desc">请填写真实联系人信息，审核进度将通过该手机号通知。</text>
          <view class="field-block field-block--first">
            <view class="field-label-row">
              <text class="field-block__label">联系人姓名</text>
              <text class="field-required">必填</text>
            </view>
            <input
              v-model.trim="form.contactName"
              class="field-control"
              placeholder="请输入姓名"
              placeholder-class="field-control__placeholder"
            />
          </view>
          <view class="field-block">
            <view class="field-label-row">
              <text class="field-block__label">电话号码</text>
              <text class="field-required">必填</text>
            </view>
            <input
              v-model.trim="form.phone"
              class="field-control"
              type="number"
              maxlength="11"
              placeholder="请输入手机号码"
              placeholder-class="field-control__placeholder"
            />
          </view>
          <view class="field-block">
            <view class="field-label-row">
              <text class="field-block__label">电子邮箱</text>
              <text class="field-required">必填</text>
            </view>
            <input
              v-model.trim="form.email"
              class="field-control"
              placeholder="请输入电子邮箱"
              placeholder-class="field-control__placeholder"
            />
          </view>
        </view>

        <view class="card-section">
          <view class="section-title-row">
            <text class="section-title section-title--no-margin">店铺地址</text>
            <text class="section-required-tag">必填</text>
          </view>
          <text class="section-desc">请确认店铺所在省市区，并补充街道、楼牌号等详细信息。</text>
          <view class="field-block field-block--first">
            <view class="field-label-row">
              <text class="field-block__label">所在地区</text>
              <text class="field-required">必填</text>
            </view>
            <view class="field-control field-control--picker" @click="handleSelectRegion">
              <text :class="['field-control__text', { 'field-control__text--placeholder': !regionDisplay }]">
                {{ regionDisplay || regionPlaceholder }}
              </text>
              <text class="field-control__arrow">›</text>
            </view>
            <bubble-address
              ref="addressPickerRef"
              v-model="selectedRegion"
              :address-data="cityAreaData"
              @change="handleRegionChange"
            />
          </view>
          <view class="field-block">
            <view class="field-label-row">
              <text class="field-block__label">详细地址</text>
              <text class="field-required">必填</text>
            </view>
            <textarea
              v-model.trim="form.detailAddress"
              class="field-control field-control--textarea"
              maxlength="120"
              placeholder="请输入街道、楼牌号等详细信息"
              placeholder-class="field-control__placeholder"
            />
          </view>
        </view>

        <view class="agreement-row" @click="handleAgreementTap">
          <view class="agreement-row__label">
            <view class="agreement-row__checkbox" :class="{ 'agreement-row__checkbox--checked': form.agreed }">
              <view v-if="form.agreed" class="agreement-row__check-dot"></view>
            </view>
            <text class="agreement-row__text">
              我已阅读并同意
              <text class="agreement-row__link" @click.stop="handleViewAgreement('service')">《商家服务协议》</text>
              与
              <text class="agreement-row__link" @click.stop="handleViewAgreement('privacy')">《隐私权政策》</text>
              ，并保证所提供的信息真实有效。
            </text>
          </view>
        </view>

        <view class="bottom-space"></view>
      </template>

      <template v-else-if="isReviewing">
        <view class="status-card status-card--reviewing">
          <view class="status-card__badge status-card__badge--reviewing">
            <text class="status-card__badge-icon">◔</text>
          </view>
          <text class="status-card__title">审核中</text>
          <text class="status-card__desc">您的入驻申请已提交，系统正在全力审核中，预计在 1-3 个工作日内完成。</text>
        </view>

        <view class="card-section card-section--summary">
          <view class="section-head section-head--summary">
            <text class="section-title section-title--no-margin">申请详情摘要</text>
            <text class="section-badge">READONLY</text>
          </view>
          <view class="summary-list">
            <view v-for="item in summaryItems" :key="item.key" class="summary-list__row">
              <text class="summary-list__label">{{ item.label }}</text>
              <template v-if="item.key === 'license'">
                <view class="summary-license">
                  <image v-if="submissionSummary.licenseUrl" :src="submissionSummary.licenseUrl" class="summary-license__image" mode="aspectFill"></image>
                  <view v-else class="summary-license__placeholder">
                    <text class="summary-license__placeholder-text">证照</text>
                  </view>
                </view>
              </template>
              <text v-else class="summary-list__value">{{ item.value }}</text>
            </view>
          </view>
        </view>

        <view class="card-section">
          <text class="section-title">审核进度</text>
          <view class="timeline-list">
            <view v-for="(item, index) in reviewTimeline" :key="item.key" class="timeline-list__item">
              <view class="timeline-list__axis" :class="{ 'timeline-list__axis--last': index === reviewTimeline.length - 1 }">
                <view class="timeline-list__dot" :class="item.dotClass">
                  <text class="timeline-list__dot-text">{{ item.icon }}</text>
                </view>
              </view>
              <view class="timeline-list__content">
                <text class="timeline-list__title">{{ item.title }}</text>
                <text class="timeline-list__meta">{{ item.desc }}</text>
              </view>
            </view>
          </view>
        </view>
      </template>

      <template v-else-if="isApproved">
        <view class="status-card status-card--approved">
          <view class="status-card__badge status-card__badge--approved">
            <text class="status-card__badge-icon status-card__badge-icon--large">✓</text>
          </view>
          <text class="status-card__title">审核通过</text>
          <text class="status-card__desc">祝贺您！您的商户入驻申请已通过审核。现在您可以开始管理您的店铺了。</text>
          <view class="privilege-card">
            <view class="privilege-card__head">
              <text class="privilege-card__icon">▤</text>
              <text class="privilege-card__title">店铺管理权限已开通</text>
            </view>
            <text class="privilege-card__desc">商品上传、交易结算、客户沟通等核心功能均已就绪。</text>
          </view>
          <button class="primary-button primary-button--inline" @click="handleEnterMerchantCenter">
            <text>进入商户中心</text>
            <text class="primary-button__arrow">→</text>
          </button>
        </view>

        <view class="tag-list">
          <view v-for="tag in approvedTags" :key="tag" class="tag-list__item">
            <text class="tag-list__icon">✓</text>
            <text class="tag-list__text">{{ tag }}</text>
          </view>
        </view>

        <view class="feature-list">
          <view v-for="item in approvedGuides" :key="item.title" class="feature-card">
            <text class="feature-card__icon">{{ item.icon }}</text>
            <text class="feature-card__title">{{ item.title }}</text>
            <text class="feature-card__desc">{{ item.desc }}</text>
          </view>
        </view>
      </template>

      <template v-else>
        <view class="status-card status-card--rejected">
          <view class="status-card__badge status-card__badge--rejected">
            <text class="status-card__badge-icon status-card__badge-icon--large">!</text>
          </view>
          <text class="status-card__title">审核未通过</text>
          <text class="status-card__desc">很抱歉，您的商户入驻申请未能通过审核。</text>
        </view>

        <view class="card-section">
          <view class="section-head section-head--left">
            <text class="section-info-icon">i</text>
            <text class="section-title section-title--no-margin">未通过原因</text>
          </view>
          <view class="reason-list">
            <view v-for="(item, index) in rejectionReasons" :key="index" class="reason-list__item">
              <text class="reason-list__index">{{ index + 1 }}.</text>
              <text class="reason-list__text">{{ item }}</text>
            </view>
          </view>
        </view>

        <button class="primary-button primary-button--spaced" @click="handleFooterAction">
          <text>重新提交信息</text>
        </button>

        <view class="footer-links">
          <view class="footer-links__item" @click="handleContactService">
            <text class="footer-links__icon">☎</text>
            <text class="footer-links__text footer-links__text--accent">联系在线客服</text>
          </view>
          <text class="footer-links__divider">|</text>
          <view class="footer-links__item" @click="handleViewRules">
            <text class="footer-links__icon">≡</text>
            <text class="footer-links__text">查看审核规范</text>
          </view>
        </view>
        <text class="footer-copyright">© 2024 商户管理中心 版权所有</text>
      </template>
    </view>

    <view v-if="showFixedFooter" class="footer-bar">
      <button class="primary-button" :loading="submitLoading" :disabled="submitLoading" @click="handleFooterAction">
        <text>{{ footerButtonText }}</text>
      </button>
    </view>
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getAddressCityArea } from '@/api/address'
import { buildAgreementUrl } from '@/utils/agreement'
import { isLoggedIn, navigateToLogin } from '@/utils/auth'

const PAGE_STATES = Object.freeze({
  EDITING: 'editing',
  REVIEWING: 'reviewing',
  APPROVED: 'approved',
  REJECTED: 'rejected'
})

const DEFAULT_REJECTION_REASONS = []

const DEFAULT_APPROVED_TAGS = ['企业认证已生效', '安全合规已备案']

const DEFAULT_APPROVED_GUIDES = [
  { title: '新手教程', desc: '快速了解店铺设置', icon: '?' },
  { title: '平台规则', desc: '查阅最新运营规范', icon: '▣' }
]

const SETTLED_PAGE_PATH = '/pages/merchant/settled/index'

defineOptions({ name: 'MerchantSettledIndex' })

const pageOptions = ref({})
const pageInitialized = ref(false)
const pageState = ref(PAGE_STATES.EDITING)
const submitLoading = ref(false)
const cityAreaLoading = ref(false)
const selectedRegion = ref({})
const cityAreaData = ref({
  provinceList: [],
  cityList: [],
  areaList: []
})
const approvedTags = DEFAULT_APPROVED_TAGS
const approvedGuides = DEFAULT_APPROVED_GUIDES
const rejectionReasons = DEFAULT_REJECTION_REASONS
const reviewTimeline = ref([
  {
    key: 'submitted',
    title: '提交成功',
    desc: '等待系统进入审核流程',
    icon: '✓',
    dotClass: 'timeline-list__dot--done'
  },
  {
    key: 'reviewing',
    title: '资料审核中',
    desc: '系统正在处理，请耐心等待',
    icon: '◔',
    dotClass: 'timeline-list__dot--active'
  }
])
const form = ref({
  idFrontUrl: '',
  idBackUrl: '',
  licenseUrl: '',
  contactName: '',
  phone: '',
  email: '',
  province: '',
  city: '',
  district: '',
  provinceId: '',
  cityId: '',
  districtId: '',
  detailAddress: '',
  agreed: false
})
const submissionSummary = ref({
  merchantName: '',
  categoryName: '',
  contactName: '',
  contactPhone: '',
  licenseUrl: '',
  regionText: '',
  detailAddress: '',
  email: ''
})

const addressPickerRef = ref(null)

const isEditing = computed(() => {
  return pageState.value === PAGE_STATES.EDITING
})

const isReviewing = computed(() => {
  return pageState.value === PAGE_STATES.REVIEWING
})

const isApproved = computed(() => {
  return pageState.value === PAGE_STATES.APPROVED
})

const isRejected = computed(() => {
  return pageState.value === PAGE_STATES.REJECTED
})

const showFixedFooter = computed(() => {
  return isEditing.value
})

const footerButtonText = computed(() => {
  return '提交申请'
})

const regionPlaceholder = computed(() => {
  return cityAreaLoading.value ? '地区加载中' : '请选择省 / 市 / 区'
})

const regionDisplay = computed(() => {
  return [form.value.province, form.value.city, form.value.district].filter(Boolean).join(' / ')
})

const progressSteps = computed(() => {
  const buildStep = (key, text, label, status, activeRing = false) => ({
    key,
    text,
    label,
    activeRing,
    circleClass: `progress-steps__circle--${status}`,
    labelClass: `progress-steps__label--${status}`
  })

  if (isEditing.value) {
    return [
      buildStep('editing-1', '1', '填写信息', 'active'),
      buildStep('editing-2', '2', '审核中', 'pending'),
      buildStep('editing-3', '3', '已完成', 'pending')
    ]
  }

  if (isReviewing.value) {
    return [
      buildStep('review-1', '✓', '提交资料', 'done'),
      buildStep('review-2', '◉', '审核中', 'active-dark', true),
      buildStep('review-3', '3', '入驻成功', 'pending')
    ]
  }

  if (isApproved.value) {
    return [
      buildStep('approve-1', '1', '基础信息', 'pending'),
      buildStep('approve-2', '2', '资质审核', 'pending'),
      buildStep('approve-3', '✓', '已完成', 'success')
    ]
  }

  return [
    buildStep('reject-1', '✓', '基础信息', 'done'),
    buildStep('reject-2', '✓', '审核中', 'done'),
    buildStep('reject-3', '×', '审核未通过', 'danger')
  ]
})

const progressLineFillWidth = computed(() => {
  return isReviewing.value ? '50%' : '0'
})

const summaryItems = computed(() => {
  return [
    { key: 'merchantName', label: '商户名称', value: submissionSummary.value.merchantName },
    { key: 'categoryName', label: '主营类目', value: submissionSummary.value.categoryName },
    { key: 'contactName', label: '联系人', value: submissionSummary.value.contactName },
    { key: 'contactPhone', label: '联系电话', value: submissionSummary.value.contactPhone },
    { key: 'license', label: '营业执照', value: '' }
  ]
})

onLoad((options = {}) => {
  pageOptions.value = options || {}
  bootstrapPage()
})

onShow(() => {
  bootstrapPage()
})

function bootstrapPage() {
  if (pageInitialized.value || !ensureLoggedIn()) {
    return
  }

  pageInitialized.value = true
  initializeByState(pageOptions.value)
  if (isEditing.value) {
    loadCityAreaData()
  }
}

function ensureLoggedIn() {
  if (isLoggedIn()) {
    return true
  }

  navigateToLogin({
    redirect: buildSettledPageUrl()
  })
  return false
}

function buildSettledPageUrl() {
  const query = Object.keys(pageOptions.value || {})
    .filter(key => {
      const value = pageOptions.value[key]
      return value !== undefined && value !== null && value !== ''
    })
    .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(String(pageOptions.value[key]))}`)
    .join('&')

  return query ? `${SETTLED_PAGE_PATH}?${query}` : SETTLED_PAGE_PATH
}

function initializeByState(options = {}) {
  const state = String(options.status || options.state || '').toLowerCase()
  const nextStateMap = {
    review: PAGE_STATES.REVIEWING,
    reviewing: PAGE_STATES.REVIEWING,
    pending: PAGE_STATES.REVIEWING,
    approved: PAGE_STATES.APPROVED,
    success: PAGE_STATES.APPROVED,
    rejected: PAGE_STATES.REJECTED,
    failed: PAGE_STATES.REJECTED,
    editing: PAGE_STATES.EDITING,
    form: PAGE_STATES.EDITING
  }

  pageState.value = nextStateMap[state] || PAGE_STATES.EDITING
}

function handleBack() {
  uni.navigateBack({
    fail: () => {
      uni.switchTab({ url: '/pages/user/index' })
    }
  })
}

function handleChooseImage(fieldKey) {
  uni.chooseImage({
    count: 1,
    sizeType: ['compressed'],
    sourceType: ['album', 'camera'],
    success: (result) => {
      const tempFilePaths = Array.isArray(result.tempFilePaths) ? result.tempFilePaths : []
      if (!tempFilePaths.length) {
        return
      }
      form.value[fieldKey] = tempFilePaths[0]
    }
  })
}

/**
 * 加载地址选择组件所需省市区数据。
 */
async function loadCityAreaData() {
  cityAreaLoading.value = true
  try {
    const res = await getAddressCityArea({}, { showError: false })
    if (res.code !== 200) {
      uni.showToast({ title: res.msg || '地区数据加载失败', icon: 'none' })
      return
    }

    cityAreaData.value = normalizeCityAreaData(res.data || {})
  } catch (e) {
    console.error('加载地区数据失败', e)
    uni.showToast({ title: '地区数据加载失败', icon: 'none' })
  } finally {
    cityAreaLoading.value = false
  }
}

/**
 * 规范化地区接口数据。
 *
 * @param {Object} data 接口返回数据。
 * @returns {Object} bubble-address 组件数据。
 */
function normalizeCityAreaData(data = {}) {
  return {
    provinceList: Array.isArray(data.provinceList) ? data.provinceList : [],
    cityList: Array.isArray(data.cityList) ? data.cityList : [],
    areaList: Array.isArray(data.areaList) ? data.areaList : []
  }
}

/** 打开所在地区选择器。 */
async function handleSelectRegion() {
  if (cityAreaLoading.value) {
    uni.showToast({ title: '地区数据加载中', icon: 'none' })
    return
  }

  if (!cityAreaData.value.provinceList.length) {
    await loadCityAreaData()
  }

  if (!cityAreaData.value.provinceList.length) {
    uni.showToast({ title: '地区数据加载失败', icon: 'none' })
    return
  }

  addressPickerRef.value && addressPickerRef.value.showPicker()
}

/**
 * 接收地址选择组件变更。
 *
 * @param {Object} event bubble-address change 事件。
 */
function handleRegionChange(event = {}) {
  const value = event.value || event || {}
  applyRegionValue(value)
}

/**
 * 应用省市区选择值。
 *
 * @param {Object} value 省市区选择结果。
 */
function applyRegionValue(value = {}) {
  selectedRegion.value = value
  form.value.province = value.province || ''
  form.value.city = value.city || ''
  form.value.district = value.district || ''
  form.value.provinceId = String(value.provinceCode || value.province_id || findProvinceCode(form.value.province) || '')
  form.value.cityId = String(value.cityCode || value.city_id || findCityCode(form.value.provinceId, form.value.city) || '')
  form.value.districtId = String(value.code || value.districtCode || value.district_id || findDistrictCode(form.value.provinceId, form.value.cityId, form.value.district) || '')
}

/**
 * 通过省名称查找省ID。
 *
 * @param {string} province 省名称。
 * @returns {string} 省ID。
 */
function findProvinceCode(province) {
  const item = cityAreaData.value.provinceList.find(row => row.name === province)
  return item ? item.code : ''
}

/**
 * 通过省ID和市名称查找市ID。
 *
 * @param {string} provinceCode 省ID。
 * @param {string} city 市名称。
 * @returns {string} 市ID。
 */
function findCityCode(provinceCode, city) {
  if (!provinceCode || !city) return ''
  if (form.value.province === city) return provinceCode

  const item = cityAreaData.value.cityList.find(row => row.name === city && row.province === String(provinceCode).substring(0, 2))
  return item ? item.code : ''
}

/**
 * 通过省市ID和区名称查找区ID。
 *
 * @param {string} provinceCode 省ID。
 * @param {string} cityCode 市ID。
 * @param {string} district 区名称。
 * @returns {string} 区ID。
 */
function findDistrictCode(provinceCode, cityCode, district) {
  if (!provinceCode || !district) return ''
  const targetCityCode = cityCode || provinceCode
  const item = cityAreaData.value.areaList.find(row => {
    return row.name === district &&
      row.province === String(provinceCode).substring(0, 2) &&
      row.city === String(targetCityCode).substring(2, 4)
  })
  return item ? item.code : ''
}

function handleAgreementTap() {
  form.value.agreed = !form.value.agreed
}

function handleViewAgreement(type) {
  uni.navigateTo({
    url: buildAgreementUrl(type)
  })
}

function isValidPhone(phone = '') {
  return /^1\d{10}$/.test(String(phone || '').trim())
}

function formatPhone(phone = '') {
  const normalizedPhone = String(phone || '').trim()
  if (!/^1\d{10}$/.test(normalizedPhone)) {
    return normalizedPhone
  }
  return `${normalizedPhone.slice(0, 3)} **** ${normalizedPhone.slice(-4)}`
}

function isValidEmail(email = '') {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim())
}

async function validateForm() {
  if (!form.value.idFrontUrl || !form.value.idBackUrl) {
    uni.showToast({ title: '请上传身份证正反面', icon: 'none' })
    return false
  }

  if (!String(form.value.contactName || '').trim()) {
    uni.showToast({ title: '请输入联系人姓名', icon: 'none' })
    return false
  }

  if (!isValidPhone(form.value.phone)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return false
  }

  if (!isValidEmail(form.value.email)) {
    uni.showToast({ title: '请输入正确的邮箱地址', icon: 'none' })
    return false
  }

  if (!regionDisplay.value) {
    uni.showToast({ title: '请选择所在地区', icon: 'none' })
    return false
  }

  if (!String(form.value.detailAddress || '').trim()) {
    uni.showToast({ title: '请输入详细地址', icon: 'none' })
    return false
  }

  if (!(await ensureAgreementAccepted())) {
    return false
  }

  return true
}

function buildSubmissionSummary() {
  const contactName = String(form.value.contactName || '').trim()
  const phone = String(form.value.phone || '').trim()
  return {
    merchantName: contactName ? `${contactName}的商户申请` : '待命名商户',
    categoryName: form.value.licenseUrl ? '企业主体 / 综合服务' : '个人主体 / 综合服务',
    contactName,
    contactPhone: formatPhone(phone),
    licenseUrl: form.value.licenseUrl,
    regionText: regionDisplay.value,
    detailAddress: String(form.value.detailAddress || '').trim(),
    email: String(form.value.email || '').trim()
  }
}

function getCurrentTimeLabel() {
  const date = new Date()
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hour = String(date.getHours()).padStart(2, '0')
  const minute = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day} ${hour}:${minute}`
}

function scrollPageToTop() {
  uni.pageScrollTo({
    scrollTop: 0,
    duration: 200
  })
}

async function handleFooterAction() {
  if (submitLoading.value) {
    return
  }

  if (isRejected.value) {
    pageState.value = PAGE_STATES.EDITING
    loadCityAreaData()
    scrollPageToTop()
    return
  }

  if (!(await validateForm())) {
    return
  }

  submitLoading.value = true
  submissionSummary.value = buildSubmissionSummary()
  reviewTimeline.value = [
    {
      key: 'submitted',
      title: '提交成功',
      desc: getCurrentTimeLabel(),
      icon: '✓',
      dotClass: 'timeline-list__dot--done'
    },
    {
      key: 'reviewing',
      title: '资料审核中',
      desc: '系统正在处理，请耐心等待',
      icon: '◔',
      dotClass: 'timeline-list__dot--active'
    }
  ]
  pageState.value = PAGE_STATES.REVIEWING
  submitLoading.value = false
  scrollPageToTop()
}

function handleEnterMerchantCenter() {
  uni.showToast({ title: '商户中心待接入', icon: 'none' })
}

function handleContactService() {
  uni.showToast({ title: '在线客服待接入', icon: 'none' })
}

function handleViewRules() {
  uni.showToast({ title: '审核规范待接入', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.merchant-settled-page {
  min-height: 100vh;
  background: #f5f5f5;
}

.merchant-settled-page__container {
  padding: 0 28rpx 48rpx;
}

.merchant-settled-page__stage-title {
  display: block;
  margin: 12rpx 4rpx 36rpx;
  font-size: 52rpx;
  line-height: 64rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.progress-panel {
  margin-bottom: 28rpx;
  padding: 12rpx 8rpx 0;
}

.progress-steps {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.progress-steps__line,
.progress-steps__line-fill {
  position: absolute;
  top: 28rpx;
  left: 72rpx;
  height: 4rpx;
  border-radius: 999rpx;
}

.progress-steps__line {
  right: 72rpx;
  background: #dddddd;
}

.progress-steps__line-fill {
  background: #1d2433;
}

.progress-steps__item {
  position: relative;
  z-index: 1;
  width: 180rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.progress-steps__circle {
  width: 56rpx;
  height: 56rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #d7d7d7;
  border: 2rpx solid transparent;
}

.progress-steps__circle--pending {
  background: #ebebeb;
  border-color: #d7d7d7;
}

.progress-steps__circle--active,
.progress-steps__circle--success,
.progress-steps__circle--danger {
  background: var(--brand-primary, #ea580c);
  box-shadow: 0 10rpx 20rpx rgba(243, 90, 0, 0.16);
}

.progress-steps__circle--done,
.progress-steps__circle--active-dark {
  background: #1d2433;
}

.progress-steps__circle--active-ring {
  box-shadow: 0 0 0 8rpx rgba(29, 36, 51, 0.08);
}

.progress-steps__circle-text {
  font-size: 24rpx;
  line-height: 24rpx;
  font-weight: 700;
  color: #ffffff;
}

.progress-steps__circle--pending .progress-steps__circle-text {
  color: #7b7b7b;
}

.progress-steps__label {
  margin-top: 16rpx;
  font-size: 22rpx;
  line-height: 30rpx;
  color: var(--text-secondary, #8b9097);
}

.progress-steps__label--active,
.progress-steps__label--success,
.progress-steps__label--danger {
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.progress-steps__label--done,
.progress-steps__label--active-dark {
  color: #2f3647;
  font-weight: 700;
}

.card-section,
.status-card {
  margin-bottom: 24rpx;
  border-radius: 28rpx;
  background: #ffffff;
  box-shadow: 0 14rpx 36rpx rgba(15, 23, 42, 0.06);
}

.card-section {
  padding: 28rpx;
}

.card-section--summary {
  overflow: hidden;
  padding-bottom: 8rpx;
}

.section-title {
  display: block;
  margin-bottom: 24rpx;
  font-size: 40rpx;
  line-height: 56rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.section-title--no-margin {
  margin-bottom: 0;
}

.section-title-row {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
}

.section-required-tag {
  margin-left: 14rpx;
  padding: 5rpx 12rpx;
  border-radius: 8rpx;
  background: var(--brand-primary-pale, #fff7ed);
  font-size: 20rpx;
  line-height: 22rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.section-desc {
  display: block;
  margin-bottom: 24rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #8b9097);
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
}

.section-head--left {
  justify-content: flex-start;
}

.section-head--summary {
  margin: -28rpx -28rpx 0;
  padding: 24rpx 28rpx;
  background: #f3f3f3;
}

.section-tag,
.section-badge {
  padding: 8rpx 18rpx;
  border-radius: 999rpx;
  font-size: 20rpx;
  line-height: 20rpx;
  font-weight: 700;
}

.section-tag {
  background: #f2f2f2;
  color: var(--text-secondary, #8b9097);
}

.section-badge {
  background: #eef0f4;
  color: #6d7584;
}

.section-info-icon {
  width: 36rpx;
  height: 36rpx;
  margin-right: 12rpx;
  border-radius: 999rpx;
  border: 2rpx solid #c7ccd6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22rpx;
  line-height: 22rpx;
  color: #8b9097;
}

.upload-grid {
  display: flex;
  justify-content: space-between;
}

.upload-grid__item {
  width: 48%;
}

.upload-grid__caption {
  font-size: 20rpx;
  line-height: 28rpx;
  color: var(--text-placeholder, #a0a0a0);
}

.upload-grid__caption-row {
  margin-top: 12rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.upload-grid__caption-required {
  margin-left: 8rpx;
  padding: 3rpx 8rpx;
  border-radius: 8rpx;
  background: var(--brand-primary-pale, #fff7ed);
  font-size: 18rpx;
  line-height: 20rpx;
  color: var(--brand-primary, #ea580c);
}

.upload-tile,
.license-tile {
  border: 2rpx dashed #ddd4d3;
  border-radius: 24rpx;
  background: #fafafa;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.upload-tile {
  height: 204rpx;
}

.license-tile {
  height: 224rpx;
}

.upload-tile__image,
.license-tile__image,
.summary-license__image {
  width: 100%;
  height: 100%;
}

.upload-tile__icon,
.license-tile__icon {
  font-size: 44rpx;
  line-height: 44rpx;
  color: #a1a4ad;
}

.upload-tile__title,
.license-tile__title {
  margin-top: 12rpx;
  font-size: 22rpx;
  line-height: 30rpx;
  color: #7b7f87;
}

.license-tile__desc {
  margin-top: 10rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: var(--text-placeholder, #a0a0a0);
}

.field-block {
  margin-top: 20rpx;
}

.field-block--first {
  margin-top: 0;
}

.field-label-row {
  display: flex;
  align-items: center;
  margin-bottom: 12rpx;
}

.field-block__label {
  font-size: 22rpx;
  line-height: 30rpx;
  font-weight: 700;
  color: #666666;
}

.field-required {
  margin-left: 10rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: var(--brand-primary, #ea580c);
}

.field-control {
  min-height: 88rpx;
  padding: 0 24rpx;
  border-radius: 20rpx;
  background: #f7f7f7;
  box-sizing: border-box;
  font-size: 30rpx;
  color: var(--text-title, #1a1c1c);
}

.field-control--picker {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.field-control--textarea {
  height: 112rpx;
  padding: 22rpx 24rpx;
}

.field-control__text {
  font-size: 30rpx;
  line-height: 42rpx;
  color: var(--text-title, #1a1c1c);
}

.field-control__text--placeholder,
.field-control__placeholder {
  color: var(--text-placeholder, #a0a0a0);
}

.field-control__arrow {
  font-size: 28rpx;
  line-height: 28rpx;
  color: #83879a;
}

.agreement-row {
  display: flex;
  margin: 16rpx 4rpx 0;
  padding: 4rpx 0;
}

.agreement-row__label {
  flex: 1;
  display: flex;
  align-items: flex-start;
}

.agreement-row__checkbox {
  width: 36rpx;
  height: 36rpx;
  margin-top: 2rpx;
  margin-right: 12rpx;
  border: 2rpx solid #d3d6de;
  border-radius: 999rpx;
  background: #ffffff;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.agreement-row__checkbox--checked {
  border-color: var(--brand-primary, #ea580c);
  background: var(--brand-primary, #ea580c);
}

.agreement-row__check-dot {
  width: 14rpx;
  height: 14rpx;
  border-radius: 999rpx;
  background: #ffffff;
}

.agreement-row__text {
  flex: 1;
  font-size: 20rpx;
  line-height: 30rpx;
  color: var(--text-secondary, #8b9097);
}

.agreement-row__link {
  color: var(--brand-primary, #ea580c);
  font-weight: 700;
}

.status-card {
  padding: 44rpx 36rpx;
  text-align: center;
}

.status-card__badge {
  width: 128rpx;
  height: 128rpx;
  margin: 0 auto 28rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.status-card__badge--reviewing,
.status-card__badge--rejected {
  background: var(--brand-primary-pale, #fff7ed);
}

.status-card__badge--approved {
  background: var(--brand-primary, #ea580c);
}

.status-card__badge-icon {
  font-size: 48rpx;
  line-height: 48rpx;
  color: var(--brand-primary, #ea580c);
}

.status-card__badge-icon--large {
  font-size: 54rpx;
  line-height: 54rpx;
}

.status-card__badge--approved .status-card__badge-icon {
  color: #ffffff;
}

.status-card__title {
  display: block;
  font-size: 58rpx;
  line-height: 74rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.status-card__desc {
  display: block;
  margin-top: 18rpx;
  font-size: 30rpx;
  line-height: 46rpx;
  color: #666666;
}

.privilege-card {
  margin-top: 36rpx;
  padding: 28rpx;
  border-radius: 24rpx;
  background: #f6f6f6;
  text-align: left;
}

.privilege-card__head {
  display: flex;
  align-items: center;
}

.privilege-card__icon {
  margin-right: 14rpx;
  font-size: 32rpx;
  line-height: 32rpx;
  color: var(--brand-primary, #ea580c);
}

.privilege-card__title {
  font-size: 30rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.privilege-card__desc {
  display: block;
  margin-top: 14rpx;
  padding-left: 44rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #8b9097);
}

.summary-list__row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 24rpx 0;
}

.summary-list__label {
  width: 200rpx;
  font-size: 22rpx;
  line-height: 30rpx;
  color: var(--text-secondary, #8b9097);
}

.summary-list__value {
  flex: 1;
  font-size: 22rpx;
  line-height: 34rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
  text-align: right;
}

.summary-license {
  width: 124rpx;
  height: 92rpx;
  border-radius: 12rpx;
  overflow: hidden;
  background: #eef1f4;
}

.summary-license__placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.summary-license__placeholder-text {
  font-size: 22rpx;
  line-height: 22rpx;
  color: #8b9097;
}

.timeline-list__item {
  display: flex;
  align-items: stretch;
}

.timeline-list__axis {
  width: 40rpx;
  display: flex;
  justify-content: center;
  position: relative;
}

.timeline-list__axis::after {
  content: '';
  position: absolute;
  top: 28rpx;
  bottom: -8rpx;
  left: 19rpx;
  width: 2rpx;
  background: #d8dbe3;
}

.timeline-list__axis--last::after {
  display: none;
}

.timeline-list__dot {
  width: 40rpx;
  height: 40rpx;
  border-radius: 999rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #d8dbe3;
}

.timeline-list__dot--done,
.timeline-list__dot--active,
.timeline-list__dot--danger {
  background: #1d2433;
}

.timeline-list__dot--active {
  background: var(--brand-primary-pale, #fff7ed);
}

.timeline-list__dot--danger {
  background: var(--brand-primary-pale, #fff7ed);
}

.timeline-list__dot-text {
  font-size: 22rpx;
  line-height: 22rpx;
  color: #ffffff;
}

.timeline-list__dot--active .timeline-list__dot-text,
.timeline-list__dot--danger .timeline-list__dot-text {
  color: var(--brand-primary, #ea580c);
}

.timeline-list__content {
  flex: 1;
  padding: 0 0 36rpx 20rpx;
}

.timeline-list__title {
  display: block;
  font-size: 22rpx;
  line-height: 32rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.timeline-list__meta {
  display: block;
  margin-top: 8rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: var(--text-secondary, #8b9097);
}

.reason-list__item {
  display: flex;
  align-items: flex-start;
  padding: 28rpx 24rpx;
  border-radius: 22rpx;
  background: #f8f8f8;
}

.reason-list__item + .reason-list__item {
  margin-top: 20rpx;
}

.reason-list__index {
  margin-right: 16rpx;
  font-size: 34rpx;
  line-height: 38rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.reason-list__text {
  flex: 1;
  font-size: 28rpx;
  line-height: 42rpx;
  color: var(--text-title, #1a1c1c);
}

.tag-list,
.feature-list {
  display: flex;
  flex-wrap: wrap;
  margin: -8rpx;
}

.tag-list {
  justify-content: center;
  margin-bottom: 28rpx;
}

.tag-list__item {
  margin: 8rpx;
  padding: 16rpx 26rpx;
  border-radius: 999rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 22rpx rgba(15, 23, 42, 0.05);
  display: flex;
  align-items: center;
}

.tag-list__icon {
  margin-right: 10rpx;
  font-size: 20rpx;
  line-height: 20rpx;
  color: var(--brand-primary, #ea580c);
}

.tag-list__text {
  font-size: 20rpx;
  line-height: 28rpx;
  color: var(--text-secondary, #8b9097);
}

.feature-card {
  width: 48%;
  margin: 8rpx 1%;
  padding: 28rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 14rpx 30rpx rgba(15, 23, 42, 0.05);
  box-sizing: border-box;
}

.feature-card__icon {
  display: block;
  margin-bottom: 20rpx;
  font-size: 36rpx;
  line-height: 36rpx;
  color: var(--brand-primary, #ea580c);
}

.feature-card__title {
  display: block;
  font-size: 36rpx;
  line-height: 46rpx;
  font-weight: 700;
  color: var(--text-title, #1a1c1c);
}

.feature-card__desc {
  display: block;
  margin-top: 10rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: var(--text-secondary, #8b9097);
}

.bottom-space {
  height: 240rpx;
}

.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  min-height: 160rpx;
  padding: 24rpx 28rpx;
  background: #ffffff;
  box-shadow: 0 -12rpx 28rpx rgba(15, 23, 42, 0.05);
  box-sizing: border-box;
}

/* #ifdef MP-WEIXIN */
.footer-bar {
  padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
}
/* #endif */

/* #ifdef H5 */
.footer-bar {
  bottom: var(--window-bottom, 0);
  padding-bottom: 24rpx;
}
/* #endif */

.primary-button {
  width: 100%;
  height: 96rpx;
  line-height: 96rpx;
  border: none;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
  color: var(--text-inverse, #ffffff);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 34rpx;
  font-weight: 700;
  box-shadow: 0 18rpx 32rpx rgba(243, 90, 0, 0.24);
}

.primary-button--inline {
  margin-top: 36rpx;
}

.primary-button--spaced {
  margin: 12rpx 0 40rpx;
}

.primary-button::after {
  border: none;
}

.primary-button__arrow {
  margin-left: 12rpx;
  font-size: 24rpx;
  line-height: 24rpx;
}

.footer-links {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 8rpx;
}

.footer-links__item {
  display: flex;
  align-items: center;
}

.footer-links__icon {
  margin-right: 10rpx;
  font-size: 22rpx;
  line-height: 22rpx;
  color: var(--brand-primary, #ea580c);
}

.footer-links__divider {
  margin: 0 20rpx;
  font-size: 24rpx;
  line-height: 24rpx;
  color: #c9ccd3;
}

.footer-links__text {
  font-size: 24rpx;
  line-height: 32rpx;
  color: var(--text-secondary, #8b9097);
}

.footer-links__text--accent {
  color: var(--brand-primary, #ea580c);
}

.footer-copyright {
  display: block;
  margin-top: 28rpx;
  text-align: center;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #b5b9c2;
}
</style>
