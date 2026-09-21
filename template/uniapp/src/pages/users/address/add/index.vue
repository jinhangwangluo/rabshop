<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="address-detail-page">
    <app-safe-top :title="pageTitle" background="#ffffff">
    </app-safe-top>

    <!-- 主内容区 -->
    <scroll-view scroll-y class="main-scroll">
      <view class="main-content">
        <!-- 智能解析区域 -->
        <view class="smart-parse-card">
          <view class="smart-parse-card__header">
            <text class="smart-parse-card__title">智能解析</text>
            <view class="smart-parse-card__tag">AI 已开启</view>
          </view>
          <view class="smart-parse-card__textarea-wrap">
            <textarea
              class="smart-parse-card__textarea"
              v-model="form.smartAddress"
              placeholder="在此粘贴地址（例如：张三，13800138000，北京市...）"
              :maxlength="200"
              auto-height
            ></textarea>
          </view>
        </view>

        <!-- 表单字段区域 -->
        <view class="form-fields">
          <!-- 收货人 -->
          <view class="form-field">
            <text class="form-field__label">收货人</text>
            <input
              class="form-field__input"
              v-model="form.name"
              placeholder="姓名"
              type="text"
              :maxlength="20"
            />
          </view>

          <!-- 手机号码 -->
          <view class="form-field">
            <text class="form-field__label">手机号码</text>
            <input
              class="form-field__input"
              v-model="form.phone"
              placeholder="联系电话"
              type="number"
              :maxlength="11"
            />
          </view>

          <!-- 所在地区 -->
          <view class="form-field form-field--tap" @click="handleSelectRegion">
            <text class="form-field__label">所在地区</text>
            <view class="form-field__value-wrap">
              <text class="form-field__placeholder" :class="{ 'form-field__text': form.region }">{{ form.region || regionPlaceholder }}</text>
              <uni-icons type="right" size="14" color="#9ca3af"></uni-icons>
            </view>
          </view>
          <bubble-address
            ref="addressPickerRef"
            v-model="selectedRegion"
            :address-data="cityAreaData"
            @change="handleRegionChange"
          />

          <!-- 详细地址 -->
          <view class="form-field form-field--multiline">
            <text class="form-field__label">详细地址</text>
            <view class="form-field__detail-wrap">
              <textarea
                class="form-field__textarea"
                v-model="form.detail"
                placeholder="街道门牌、楼层、房间号等"
                :maxlength="100"
                auto-height
                :min-height="120"
              ></textarea>
              <view class="form-field__location" @click.stop="handleMapSelect">
                <uni-icons type="location" size="18" :color="themePrimaryColor"></uni-icons>
              </view>
            </view>
          </view>
        </view>

        <!-- 设为默认地址 -->
        <view class="default-card">
          <view class="default-card__left">
            <text class="default-card__title">设为默认地址</text>
            <text class="default-card__desc">后续下单将默认使用此地址</text>
          </view>
          <switch
            :checked="form.isDefault"
            @change="handleSwitchDefault"
            :color="themePrimaryColor"
            style="transform: scale(0.85);"
          />
        </view>

      </view>
    </scroll-view>

    <!-- 底部保存按钮 -->
    <view class="footer-bar">
      <button class="save-btn" @click="handleSave" :loading="saving" :disabled="saving || loading">
        {{ saveButtonText }}
      </button>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { createAddress, getAddressCityArea, getAddressDetail, updateAddress } from '@/api/address'
import { computed, ref, watch } from 'vue'
import { onHide, onLoad, onUnload } from '@dcloudio/uni-app'

/**
 * 添加/编辑收货地址页面。
 * 支持新增和编辑两种模式，通过路由参数 id 区分。
 */
defineOptions({ name: 'AddressDetail' })

const loading = ref(false)
const saving = ref(false)
/** 地址表单数据 */
const form = ref({
  /** 智能解析地址文本 */
  smartAddress: '',
  /** 收货人姓名 */
  name: '',
  /** 手机号码 */
  phone: '',
  /** 所在地区 */
  region: '',
  /** 省 */
  province: '',
  /** 市 */
  city: '',
  /** 区 */
  district: '',
  /** 省ID */
  provinceId: '',
  /** 市ID */
  cityId: '',
  /** 区ID */
  districtId: '',
  /** 地图纬度 */
  latitude: '',
  /** 地图经度 */
  longitude: '',
  /** 地图选择名称 */
  mapName: '',
  /** 详细地址 */
  detail: '',
  /** 是否设为默认地址 */
  isDefault: false
})
const selectedRegion = ref({})
const cityAreaLoading = ref(false)
const cityAreaData = ref({
  provinceList: [],
  cityList: [],
  areaList: []
})
const smartParseTimer = ref(null)
/** 当前编辑的地址ID（新增时为空） */
const editId = ref(null)
const addressPickerRef = ref(null)

const pageTitle = computed(() => {
  return editId.value ? '编辑地址' : '添加地址'
})

const saveButtonText = computed(() => {
  return editId.value ? '修改地址' : '保存地址'
})

const regionPlaceholder = computed(() => {
  return cityAreaLoading.value ? '地区加载中' : '省/市/区'
})

const mapLatitude = computed(() => {
  return Number(form.value.latitude) || 39.908823
})

const mapLongitude = computed(() => {
  return Number(form.value.longitude) || 116.39747
})

/**
 * 监听智能地址内容，停止输入后自动解析。
 */
watch(
  () => form.value.smartAddress,
  (value) => {
    clearSmartParseTimer()
    const text = String(value || '').trim()
    if (!isAddressText(text)) {
      return
    }

    smartParseTimer.value = setTimeout(() => {
      runSmartParse(true)
    }, 700)
  }
)

onLoad((options = {}) => {
  loadCityAreaData()
  if (options && options.id) {
    editId.value = options.id
    loadAddressDetail(options.id)
  }
})

onHide(() => {
  clearSmartParseTimer()
})

onUnload(() => {
  clearSmartParseTimer()
})

/**
 * 加载地址详情（编辑模式）。
 * @param {string|number} id 地址ID。
 */
async function loadAddressDetail(id) {
  loading.value = true
  try {
    const res = await getAddressDetail({ id })
    fillForm(res.data || {})
  } catch (e) {
    console.error('加载地址详情失败', e)
  } finally {
    loading.value = false
  }
}

/**
 * 加载 bubble-address 所需省市区数据。
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
    if (form.value.smartAddress && !form.value.districtId) {
      applyParsedAddress(form.value.smartAddress, true)
    }
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

/**
 * 使用接口地址详情回填表单。
 *
 * @param {Object} address 地址详情。
 */
function fillForm(address = {}) {
  form.value.name = address.real_name || ''
  form.value.phone = address.phone || ''
  form.value.province = address.province || ''
  form.value.city = address.city || ''
  form.value.district = address.district || ''
  form.value.region = [form.value.province, form.value.city, form.value.district].filter(Boolean).join(' ')
  form.value.detail = address.detail || ''
  form.value.isDefault = Number(address.is_default || 0) === 1
  form.value.provinceId = String(address.province_id || '')
  form.value.cityId = String(address.city_id || '')
  form.value.districtId = String(address.district_id || address.area_id || '')
  form.value.latitude = String(address.latitude || '')
  form.value.longitude = String(address.longitude || '')
  form.value.mapName = address.map_name || ''
  selectedRegion.value = {
    province: form.value.province,
    city: form.value.city,
    district: form.value.district,
    provinceCode: form.value.provinceId,
    cityCode: form.value.cityId,
    code: form.value.districtId
  }
}

/** 更多操作 */
function handleMore() {
  // 预留更多操作入口
}

/**
 * 判断文本是否像地址文本。
 * @param {string} text 待判断文本。
 * @returns {boolean} 是否可尝试解析。
 */
function isAddressText(text) {
  return text.length >= 15 && /^1\d{10}$/.test((text.match(/1\d{10}/) || [''])[0] || '')
}

/** 清理智能解析防抖计时器。 */
function clearSmartParseTimer() {
  if (smartParseTimer.value) {
    clearTimeout(smartParseTimer.value)
    smartParseTimer.value = null
  }
}

/**
 * 执行智能解析。
 * @param {boolean} silent 是否静默解析。
 */
async function runSmartParse(silent = true) {
  if (!cityAreaData.value.provinceList.length) {
    await loadCityAreaData()
  }
  applyParsedAddress(form.value.smartAddress, silent)
}

/**
 * 解析并应用收货地址。
 * @param {string} text 原始地址文本。
 * @param {boolean} silent 是否静默解析。
 */
function applyParsedAddress(text, silent) {
  const parsed = parseAddressText(text)
  if (!parsed.phone || !parsed.name || !parsed.detail) {
    if (!silent) uni.showToast({ title: '未识别到完整地址', icon: 'none' })
    return
  }

  form.value.name = parsed.name
  form.value.phone = parsed.phone
  form.value.detail = parsed.detail

  if (parsed.region.province && parsed.region.district) {
    applyRegionValue(parsed.region)
  } else if (!silent) {
    uni.showToast({ title: '请手动选择省市区', icon: 'none' })
  }

  if (!silent) {
    uni.showToast({ title: parsed.region.district ? '解析成功' : '请手动选择省市区', icon: 'none' })
  }
}

/**
 * 解析用户粘贴的两种地址格式。
 * @param {string} text 原始地址文本。
 * @returns {Object} 解析后的姓名、手机号、地区和详细地址。
 */
function parseAddressText(text) {
  const source = String(text || '').replace(/：/g, ':').replace(/\r/g, '\n').trim()
  const phoneMatch = source.match(/1\d{10}/)
  const phone = phoneMatch ? phoneMatch[0] : ''
  let name = ''
  let detail = ''
  let regionText = ''

  if (source.indexOf('收货人:') !== -1 || source.indexOf('收件人:') !== -1 || source.indexOf('手机号:') !== -1 || source.indexOf('手机号码:') !== -1) {
    name = matchLineValue(source, '收货人') || matchLineValue(source, '收件人')
    regionText = matchLineValue(source, '所在地区')
    detail = matchLineValue(source, '详细地址')
  } else {
    const beforePhone = phone ? source.split(phone)[0] : ''
    name = beforePhone.trim().split(/\s+/)[0] || ''
    const afterPhone = phone ? source.split(phone).slice(1).join(phone).trim() : source
    const region = matchRegionFromText(afterPhone)
    regionText = region.text
    detail = region.detail
  }

  if (!detail && regionText) {
    detail = source.replace(name, '').replace(phone, '').replace(regionText, '').trim()
  }

  const region = matchRegionFromText(regionText)
  return {
    name,
    phone,
    region: region.value,
    detail: detail.trim()
  }
}

/**
 * 从多行文本中提取指定字段值。
 * @param {string} text 原始文本。
 * @param {string} label 字段标签。
 * @returns {string} 字段值。
 */
function matchLineValue(text, label) {
  const reg = new RegExp(label + '\\s*:\\s*([^\\n]+)')
  const match = text.match(reg)
  return match ? match[1].trim() : ''
}

/**
 * 从文本中匹配省市区和剩余详细地址。
 * @param {string} text 地址文本。
 * @returns {Object} 匹配结果。
 */
function matchRegionFromText(text) {
  const sourceText = String(text || '').trim()
  const fullText = sourceText.replace(/\s+/g, '')
  const province = cityAreaData.value.provinceList.find(item => fullText.indexOf(normalizeRegionName(item.name)) !== -1)
  if (!province) return { text: '', detail: sourceText, value: {} }

  const city = findMatchedCityByText(province, fullText)
  const district = findMatchedDistrictByText(province, city, fullText)
  if (!district) return { text: '', detail: sourceText, value: {} }

  const regionText = province.name + (city ? city.name : '') + district.name
  let detail = sourceText
  ;[province.name, city ? city.name : '', district.name].forEach(item => {
    if (item) detail = removeRegionName(detail, item)
  })

  return {
    text: regionText,
    detail,
    value: {
      province: province.name,
      city: city ? city.name : province.name,
      district: district.name,
      provinceCode: province.code,
      cityCode: city ? city.code : province.code,
      code: district.code
    }
  }
}

/**
 * 规范化省市区名称，兼容省/市/区后缀差异。
 * @param {string} name 地区名称。
 * @returns {string} 规范化名称。
 */
function normalizeRegionName(name) {
  return String(name || '').replace(/省|市|区|县|自治州|地区|盟/g, '')
}

/**
 * 从详细地址文本中移除地区名称及常见后缀。
 * @param {string} text 详细地址文本。
 * @param {string} name 地区名称。
 * @returns {string} 移除后的文本。
 */
function removeRegionName(text, name) {
  const source = String(text || '')
  const normalizedName = normalizeRegionName(name)
  return source
    .replace(name, '')
    .replace(normalizedName + '省', '')
    .replace(normalizedName + '市', '')
    .replace(normalizedName + '区', '')
    .replace(normalizedName + '县', '')
    .replace(normalizedName, '')
}

/**
 * 通过文本匹配城市。
 * @param {Object} province 省数据。
 * @param {string} text 地址文本。
 * @returns {Object|null} 市数据。
 */
function findMatchedCityByText(province, text) {
  const list = cityAreaData.value.cityList.filter(item => item.province === String(province.code).substring(0, 2))
  return list.find(item => text.indexOf(normalizeRegionName(item.name)) !== -1) || null
}

/**
 * 通过文本匹配区县。
 * @param {Object} province 省数据。
 * @param {Object|null} city 市数据。
 * @param {string} text 地址文本。
 * @returns {Object|null} 区县数据。
 */
function findMatchedDistrictByText(province, city, text) {
  const cityCode = city ? city.code : province.code
  const list = cityAreaData.value.areaList.filter(item => {
    return item.province === String(province.code).substring(0, 2) &&
      item.city === String(cityCode).substring(2, 4)
  })
  return list.find(item => text.indexOf(normalizeRegionName(item.name)) !== -1) || null
}

/** 选择所在地区 */
function handleSelectRegion() {
  if (cityAreaLoading.value) {
    uni.showToast({ title: '地区数据加载中', icon: 'none' })
    return
  }

  if (!cityAreaData.value.provinceList.length) {
    loadCityAreaData()
    uni.showToast({ title: '正在重新加载地区数据', icon: 'none' })
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
 * 应用省市区选择值，并保存各级行政区ID。
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
  form.value.region = [form.value.province, form.value.city, form.value.district].filter(Boolean).join(' ')
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
  if (provinceCode && form.value.province === city) return provinceCode

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

/**
 * 根据地图返回地址匹配本地省市区数据。
 *
 * @param {Object} location 地图选择结果。
 * @returns {Object} 匹配后的省市区。
 */
function matchRegionByLocation(location = {}) {
  const address = String(location.address || '')
  const name = String(location.name || '')
  const fullAddress = address + name
  const province = cityAreaData.value.provinceList.find(item => fullAddress.indexOf(item.name) !== -1)
  if (!province) return {}

  const city = findMatchedCity(province, fullAddress)
  const district = findMatchedDistrict(province, city, fullAddress)
  if (!district) return {}

  return {
    province: province.name,
    city: city ? city.name : province.name,
    district: district.name,
    provinceCode: province.code,
    cityCode: city ? city.code : province.code,
    code: district.code
  }
}

/**
 * 匹配地图地址中的城市。
 *
 * @param {Object} province 省数据。
 * @param {string} fullAddress 地图返回完整地址。
 * @returns {Object|null} 市数据。
 */
function findMatchedCity(province, fullAddress) {
  const list = cityAreaData.value.cityList.filter(item => item.province === String(province.code).substring(0, 2))
  if (!list.length) return null
  return list.find(item => fullAddress.indexOf(item.name) !== -1) || null
}

/**
 * 匹配地图地址中的区县。
 *
 * @param {Object} province 省数据。
 * @param {Object|null} city 市数据。
 * @param {string} fullAddress 地图返回完整地址。
 * @returns {Object|null} 区数据。
 */
function findMatchedDistrict(province, city, fullAddress) {
  const cityCode = city ? city.code : province.code
  const list = cityAreaData.value.areaList.filter(item => {
    return item.province === String(province.code).substring(0, 2) &&
      item.city === String(cityCode).substring(2, 4)
  })
  return list.find(item => fullAddress.indexOf(item.name) !== -1) || null
}

/**
 * 应用地图选择结果。
 *
 * @param {Object} location 地图选择结果。
 */
function applyMapLocation(location = {}) {
  const address = location.address || ''
  const name = location.name || ''
  const region = matchRegionByLocation(location)
  if (region.province && region.district) {
    applyRegionValue(region)
  }

  form.value.latitude = String(location.latitude || '')
  form.value.longitude = String(location.longitude || '')
  form.value.mapName = name
  form.value.detail = buildMapDetail(address, name, region)
}

/**
 * 生成去除省市区前缀后的详细地址。
 *
 * @param {string} address 地图地址。
 * @param {string} name 地图位置名称。
 * @param {Object} region 匹配到的省市区。
 * @returns {string} 详细地址。
 */
function buildMapDetail(address, name, region = {}) {
  let detail = address || name || ''
  ;[region.province, region.city, region.district].forEach(item => {
    if (item) {
      detail = detail.replace(item, '')
    }
  })

  if (name && detail.indexOf(name) === -1) {
    detail = detail ? detail + name : name
  }

  return detail || name || address || ''
}

/**
 * 切换默认地址开关。
 * @param {Object} e switch 组件 change 事件对象。
 */
function handleSwitchDefault(e) {
  form.value.isDefault = e.detail.value
}

/** 地图选点 */
async function handleMapSelect() {
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

  uni.chooseLocation({
    latitude: mapLatitude.value,
    longitude: mapLongitude.value,
    success: (res) => {
      applyMapLocation(res)
      if (!form.value.districtId) {
        uni.showToast({ title: '请确认所在地区', icon: 'none' })
      }
    },
    fail: (err) => {
      if (err && String(err.errMsg || '').indexOf('cancel') !== -1) {
        return
      }
      uni.showToast({ title: '地图选择失败', icon: 'none' })
    }
  })
}

/** 保存地址 */
async function handleSave() {
  // 表单校验
  if (!validateForm()) return

  saving.value = true
  try {
    const params = buildSubmitParams()
    let res

    if (editId.value) {
      res = await updateAddress({ id: editId.value, ...params })
    } else {
      res = await createAddress(params)
    }

    if (!res || res.code !== 200) {
      uni.showToast({ title: (res && res.msg) || '保存失败', icon: 'none' })
      return
    }

    uni.showToast({ title: '保存成功', icon: 'success' })

    setTimeout(() => {
      uni.navigateBack()
    }, 1500)
  } catch (e) {
    console.error('保存地址失败', e)
    uni.showToast({ title: '保存失败', icon: 'none' })
  } finally {
    saving.value = false
  }
}

/**
 * 构造提交参数。
 *
 * @returns {Object} 后端地址字段。
 */
function buildSubmitParams() {
  const userAddress = [form.value.province, form.value.city, form.value.district, form.value.detail]
    .filter(Boolean)
    .join(' ')

  return {
    real_name: form.value.name,
    phone: form.value.phone,
    province: form.value.province,
    city: form.value.city,
    district: form.value.district,
    province_id: form.value.provinceId,
    city_id: form.value.cityId,
    district_id: form.value.districtId,
    area_id: form.value.districtId,
    latitude: form.value.latitude,
    longitude: form.value.longitude,
    map_name: form.value.mapName,
    detail: form.value.detail,
    user_address: userAddress,
    is_default: form.value.isDefault ? 1 : 0
  }
}

/**
 * 表单数据校验。
 * @returns {boolean} 校验是否通过。
 */
function validateForm() {
  if (!form.value.name || !form.value.name.trim()) {
    uni.showToast({ title: '请输入收货人姓名', icon: 'none' })
    return false
  }
  if (!form.value.phone) {
    uni.showToast({ title: '请输入手机号码', icon: 'none' })
    return false
  }
  if (!/^1\d{10}$/.test(form.value.phone)) {
    uni.showToast({ title: '手机号码格式不正确', icon: 'none' })
    return false
  }
  if (!form.value.districtId) {
    applyRegionValue(selectedRegion.value)
  }
  if (!form.value.districtId) {
    uni.showToast({ title: '请选择有效地区', icon: 'none' })
    return false
  }
  if (!form.value.detail || !form.value.detail.trim()) {
    uni.showToast({ title: '请输入详细地址', icon: 'none' })
    return false
  }
  // if (!form.value.latitude || !form.value.longitude) {
  //   uni.showToast({ title: '请选择定位位置', icon: 'none' })
  //   return false
  // }
  return true
}
</script>

<style scoped lang="scss">
/* ========== 页面容器 ========== */
.address-detail-page {
  display: flex;
  flex-direction: column;
  min-height: 100%;
  background-color: #f9f9f9;
}

.page-top-action {
  width: 64rpx;
  min-height: 64rpx;
  display: flex;
  align-items: center;
  justify-content: center;

  &:active {
    opacity: 0.6;
  }
}

/* ========== 滚动区域 ========== */
.main-scroll {
  flex: 1;
  min-height: 0;
}

.main-content {
  padding: 24rpx 26rpx 180rpx;
}

/* ========== 智能解析卡片 ========== */
.smart-parse-card {
  background-color: #ffffff;
  border-radius: 16rpx;
  padding: 28rpx 30rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22rpx;
  }

  &__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #1a1c1c;
  }

  &__tag {
    font-size: 20rpx;
    font-weight: 700;
    color: #5f6368;
    background-color: #f3f3f3;
    padding: 4rpx 14rpx;
    border-radius: 999rpx;
    letter-spacing: 2rpx;
  }

  &__textarea-wrap {
    position: relative;
    background-color: #efefef;
    border-radius: 12rpx;
  }

  &__textarea {
    width: 100%;
    min-height: 160rpx;
    background-color: transparent;
    border-radius: 12rpx;
    padding: 20rpx 22rpx;
    font-size: 28rpx;
    color: #1a1c1c;
    line-height: 1.6;
    box-sizing: border-box;
  }

}

/* ========== 表单字段区域 ========== */
.form-fields {
  margin-top: 16rpx;
}

.form-field {
  display: flex;
  align-items: center;
  background-color: #ffffff;
  border-radius: 12rpx;
  padding: 0 28rpx;
  height: 104rpx;
  margin-bottom: 12rpx;

  &--tap {
    &:active {
      background-color: #e5e5e5;
    }
  }

  &--multiline {
    align-items: flex-start;
    height: auto;
    min-height: 160rpx;
    padding-top: 26rpx;
    padding-bottom: 26rpx;
  }

  &__label {
    width: 144rpx;
    flex-shrink: 0;
    font-size: 28rpx;
    font-weight: 500;
    color: #4c4546;
  }

  &__input {
    flex: 1;
    background: transparent;
    border: none;
    font-size: 28rpx;
    color: #1a1c1c;
    line-height: 1.4;
    padding: 0;
  }

  &__detail-wrap {
    flex: 1;
    display: flex;
    align-items: flex-start;
  }

  &__textarea {
    flex: 1;
    background: transparent;
    border: none;
    font-size: 28rpx;
    color: #1a1c1c;
    line-height: 1.6;
    padding: 0;
    min-height: 108rpx;
  }

  &__location {
    width: 40rpx;
    height: 40rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 12rpx;
    flex-shrink: 0;

    &:active {
      opacity: 0.7;
    }
  }

  &__value-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__placeholder {
    flex: 1;
    font-size: 28rpx;
    color: #9ca3af;
    margin-right: 8rpx;
    text-align: left;
  }

  &__text {
    color: #1a1c1c;
  }
}

/* ========== 设为默认地址卡片 ========== */
.default-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background-color: #ffffff;
  border-radius: 16rpx;
  padding: 28rpx 30rpx;
  margin-top: 16rpx;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.04);

  &__left {
    display: flex;
    flex-direction: column;
  }

  &__title {
    font-size: 28rpx;
    font-weight: 700;
    color: #1a1c1c;
    line-height: 1.4;
  }

  &__desc {
    font-size: 24rpx;
    color: #9ca3af;
    margin-top: 6rpx;
  }
}
uni-textarea{
  width: auto;
}
/* ========== 底部按钮 ========== */
.footer-bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 20;
  padding: 24rpx 32rpx;
  background-color: rgba(255, 255, 255, 0.92);
  border-top: 1rpx solid rgba(0, 0, 0, 0.06);
}

.save-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 96rpx;
  background-color: var(--brand-primary, #ea580c);
  color: #ffffff;
  border-radius: 999rpx;
  font-size: 32rpx;
  font-weight: 700;
  border: none;
  letter-spacing: 4rpx;

  &::after {
    border: none;
  }

  &:active {
    transform: scale(0.98);
    opacity: 0.9;
  }
}
</style>
