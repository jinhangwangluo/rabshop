<template>
  <div class="store-setting-container">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">店铺设置</h1>
        <p class="page-curator-subtitle">
          维护店铺基础资料、展示素材、位置信息与功能权限，统一商户端品牌呈现。
        </p>
      </div>
    </header>

    <el-card v-loading="loading" shadow="never" class="store-setting-surface">
      <el-tabs v-model="activeTab">
        <!-- 基本信息 -->
        <el-tab-pane label="基本信息" name="basic">
          <el-form label-width="140px" class="form-container">
            <el-form-item label="店铺名称：">
              <el-input v-model.trim="basicInfo.mer_name" placeholder="请输入店铺名称" maxlength="64" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="店铺负责人手机号：">
              <el-input v-model.trim="basicInfo.contact_phone" placeholder="请输入负责人手机号" maxlength="20" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="店铺分类：">
              <span class="form-value">{{ basicInfo.mer_category || '-' }}</span>
            </el-form-item>
            <el-form-item label="店铺类型：">
              <span class="form-value">{{ basicInfo.mer_type || '-' }}</span>
            </el-form-item>
            <el-form-item label="店铺类别：">
              <span class="form-value">{{ basicInfo.mer_class_text }}</span>
            </el-form-item>
            <el-form-item label="店铺负责人姓名：">
              <el-input v-model="basicInfo.contact_name" placeholder="请输入负责人姓名" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="店铺入驻时间：">
              <span class="form-value">{{ basicInfo.create_time }}</span>
            </el-form-item>
            <el-form-item label="是否开启店铺：">
              <el-switch
                v-model="basicInfo.status"
                :active-value="1"
                :inactive-value="0"
                active-text="开启"
                inactive-text="关闭"
              />
              <span class="form-tip">开启，店铺即可展示在移动端</span>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 店铺信息 -->
        <el-tab-pane label="店铺信息" name="store">
          <el-form ref="storeForm" :model="storeInfo" label-width="140px" class="form-container">
            <el-form-item label="店铺背景图：">
              <div class="upload-item">
                <upload v-model="storeInfo.mer_banner" :limit="1" />
                <div class="upload-tip">建议尺寸 710x200px</div>
              </div>
            </el-form-item>

            <el-form-item label="店铺头像：">
              <div class="upload-item">
                <upload v-model="storeInfo.mer_avatar" :limit="1" />
                <div class="upload-tip">建议尺寸 120x120px</div>
              </div>
            </el-form-item>

            <el-form-item label="店铺街景背景图：">
              <div class="upload-item">
                <upload v-model="storeInfo.mer_street_view" :limit="1" />
                <div class="upload-tip">建议尺寸 710x134px 或 710x460px</div>
              </div>
            </el-form-item>

            <el-form-item label="店铺资质：">
              <div class="upload-item">
                <upload v-model="qualificationImage" :limit="1" />
              </div>
            </el-form-item>

            <el-form-item label="店铺简介：">
              <el-input
                v-model="storeInfo.description"
                type="textarea"
                :rows="4"
                placeholder="请输入店铺简介"
                maxlength="500"
                show-word-limit
                style="width: 500px;"
              />
            </el-form-item>

            <el-form-item label="店铺关键字：">
              <el-input
                v-model="storeInfo.mer_keyword"
                placeholder="用户在搜索该关键字时，可搜索到本店铺"
                style="width: 500px;"
              />
            </el-form-item>

            <el-form-item label="店铺地址：">
              <div class="address-row">
                <el-input
                  v-model="storeInfo.mer_address"
                  placeholder="请输入店铺地址"
                  style="flex: 1;"
                />
                <el-button type="primary" style="margin-left: 10px;" @click="openMapPicker">查找位置</el-button>
              </div>
            </el-form-item>

            <el-form-item label="纬度（lat）：">
              <el-input v-model="storeInfo.lat" placeholder="纬度" style="width: 300px;" />
            </el-form-item>

            <el-form-item label="经度（lng）：">
              <el-input v-model="storeInfo.lng" placeholder="经度" style="width: 300px;" />
            </el-form-item>

            <el-form-item v-if="storeInfo.lat && storeInfo.lng" label="当前坐标：">
              <el-tag type="success" >已获取经纬度</el-tag>
              <span class="current-location"> 当前：{{ storeInfo.lat }}, {{ storeInfo.lng }} </span>
              <el-button type="text" :icon="Location" @click="openMapPicker">腾讯地图选点</el-button>
            </el-form-item>

            <!-- 内嵌小地图预览 -->
            <el-form-item v-if="storeInfo.lat && storeInfo.lng && mapPreviewUrl" label="位置预览：">
              <div class="map-preview">
                <img :src="mapPreviewUrl" alt="地图预览">
              </div>
            </el-form-item>

            <PluginSlot
              name="merchant.store.form.sections"
              :context="{ basicInfo, storeInfo, funcInfo }"
            />
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="移动端展示" name="mobile" lazy>
          <MobileDisplaySettings
            v-model="storeInfo.merchant_home_config"
            v-model:banners="storeInfo.merchant_home_banner"
          />
        </el-tab-pane>

        <!-- 功能信息 -->
        <el-tab-pane label="功能信息" name="func">
          <el-form label-width="160px" class="form-container">
            <el-form-item label="店铺手续费：">
              <span class="form-value">{{ funcInfo.commission_rate }}%</span>
            </el-form-item>
            <el-form-item label="开启直播间：">
              <span class="form-value">{{ funcInfo.live_audit_mode_text }}</span>
            </el-form-item>
            <el-form-item label="添加直播商品：">
              <span class="form-value">{{ funcInfo.live_product_audit_mode_text }}</span>
            </el-form-item>
            <el-form-item label="内容审核方式：">
              <span class="form-value">{{ funcInfo.content_audit_mode_text }}</span>
            </el-form-item>
            <el-form-item label="平台首页推荐店铺：">
              <span class="form-value">{{ funcInfo.is_recommend_text }}</span>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>

      <div v-if="activeTab !== 'func'" class="form-footer">
        <el-button type="primary" class="btn-curator-primary" :loading="submitLoading" @click="handleSubmit">提交</el-button>
      </div>
    </el-card>

    <!-- 腾讯地图选点弹窗 -->
    <tencent-map-picker
      v-model="mapPickerVisible"
      :lat="parseFloat(storeInfo.lat) || 0"
      :lng="parseFloat(storeInfo.lng) || 0"
      @confirm="onMapConfirm"
    />
  </div>
</template>

<script setup>
import { getStoreInfoApi, updateStoreInfoApi } from '@/api/merchant'
import { getPublicConfigApi } from '@/api/common'
import Upload from '@/components/Upload'
import TencentMapPicker from '@/components/TencentMapPicker'
import MobileDisplaySettings from './components/MobileDisplaySettings.vue'
import { normalizeMobileHomeConfig } from './mobileDisplayConfig'
import { Location } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { computed, onMounted, ref } from 'vue'
import {
  createRequestId,
  emitPluginSubmitAfter,
  runPluginSubmitBefore
} from '@/plugins/submit-events'

defineOptions({
  name: 'StoreSetting'
})

const loading = ref(false)
const submitLoading = ref(false)
const activeTab = ref('basic')
const mapPickerVisible = ref(false)
const mapKey = ref('')
const basicInfo = ref({
  mer_name: '',
  contact_phone: '',
  mer_category: '',
  mer_type: '',
  mer_class: 2,
  mer_class_text: '非自营',
  contact_name: '',
  create_time: '',
  status: 1
})
const storeInfo = ref({
  mer_banner: '',
  merchant_home_banner: [],
  merchant_home_recommend_special: [],
  merchant_home_config: normalizeMobileHomeConfig(null),
  mer_avatar: '',
  mer_street_view: '',
  description: '',
  mer_keyword: '',
  mer_address: '',
  lat: 0,
  lng: 0,
  qualification: []
})
const funcInfo = ref({
  commission_rate: 0,
  live_audit_mode: 1,
  live_audit_mode_text: '平台审核',
  live_product_audit_mode: 1,
  live_product_audit_mode_text: '平台审核',
  content_audit_mode: 1,
  content_audit_mode_text: '平台审核',
  is_recommend: 0,
  is_recommend_text: '否'
})

// 资质图片（用于双向绑定）
const qualificationImage = computed({
  get() {
    const q = storeInfo.value.qualification
    if (q && q.business_license) {
      return q.business_license
    }
    if (Array.isArray(q) && q.length > 0) {
      return q[0]
    }
    return ''
  },
  set(val) {
    if (!storeInfo.value.qualification) {
      storeInfo.value.qualification = {}
    }
    storeInfo.value.qualification.business_license = val
  }
})

// 地图预览图（腾讯静态地图）
const mapPreviewUrl = computed(() => {
  if (!storeInfo.value.lat || !storeInfo.value.lng || !mapKey.value) return ''
  const lat = storeInfo.value.lat
  const lng = storeInfo.value.lng
  // 使用腾讯静态地图 API（需要带 key）
  return `https://apis.map.qq.com/ws/staticmap/v2/?center=${lat},${lng}&zoom=15&size=450x200&markers=size:large|color:red|${lat},${lng}&key=${mapKey.value}`
})

onMounted(() => {
  getStoreInfo()
  getMapKey()
})

// 获取地图 Key
function getMapKey() {
  getPublicConfigApi().then(res => {
    mapKey.value = res.data.tencent_map_key || ''
  }).catch(() => {
    mapKey.value = ''
  })
}

// 获取店铺信息
function getStoreInfo() {
  loading.value = true
  getStoreInfoApi()
    .then(res => {
      if (res.data) {
        basicInfo.value = { ...basicInfo.value, ...res.data.basicInfo }
        storeInfo.value = { ...storeInfo.value, ...res.data.storeInfo }
        storeInfo.value.merchant_home_banner = normalizeImageList(storeInfo.value.merchant_home_banner)
        storeInfo.value.merchant_home_recommend_special = normalizeIdList(storeInfo.value.merchant_home_recommend_special)
        storeInfo.value.merchant_home_config = normalizeMobileHomeConfig(
          res.data.storeInfo?.merchant_home_config,
          storeInfo.value.merchant_home_recommend_special
        )
        funcInfo.value = { ...funcInfo.value, ...res.data.funcInfo }
      }
    })
    .catch(err => {
      ElMessage.error(err.message || '获取店铺信息失败')
    })
    .finally(() => {
      loading.value = false
    })
}

// 打开地图选点弹窗
function openMapPicker() {
  mapPickerVisible.value = true
}

// 地图选点回调
function onMapConfirm({ lat, lng, address }) {
  storeInfo.value.lat = lat
  storeInfo.value.lng = lng
  if (address) {
    storeInfo.value.mer_address = address
  }
}

// 提交
async function handleSubmit() {
  submitLoading.value = true
  const homeConfig = normalizeMobileHomeConfig(storeInfo.value.merchant_home_config)
  const specialSection = homeConfig.sections.find(item => item.type === 'special')
  const data = {
    // 基本信息
    mer_name: basicInfo.value.mer_name,
    contact_phone: basicInfo.value.contact_phone,
    contact_name: basicInfo.value.contact_name,
    status: basicInfo.value.status,
    // 店铺信息
    ...storeInfo.value,
    merchant_home_banner: normalizeImageList(storeInfo.value.merchant_home_banner),
    merchant_home_config: homeConfig,
    merchant_home_recommend_special: specialSection.source === 'manual' ? specialSection.ids : []
  }

  const requestId = createRequestId()

  try {
    const before = await runPluginSubmitBefore('merchant.store.form.submit.before', {
      request_id: requestId,
      payload: data,
      store_info: { ...storeInfo.value },
      basic_info: { ...basicInfo.value }
    })
    if (!before.allowed) {
      throw new Error(before.message || '插件校验失败，请稍后重试')
    }

    await updateStoreInfoApi(before.payload)
    await emitPluginSubmitAfter('merchant.store.form.submit.after', {
      request_id: requestId,
      payload: { merchant_id: Number(basicInfo.value.id || basicInfo.value.merchant_id || 0) || undefined }
    })
    ElMessage.success('保存成功')
    getStoreInfo()
  } catch (err) {
    ElMessage.error(err.message || '保存失败')
  } finally {
    submitLoading.value = false
  }
}

function normalizeImageList(list) {
  if (!Array.isArray(list)) return []
  return list.map(item => String(item || '').trim()).filter(Boolean).slice(0, 8)
}

function normalizeIdList(list) {
  if (!Array.isArray(list)) return []
  const ids = list.map(item => Number(item || 0)).filter(id => id > 0)
  return Array.from(new Set(ids))
}
</script>

<style lang="scss" scoped>
.store-setting-container {
  min-height: 100%;
  box-sizing: border-box;

  .page-curator-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 20px;
  }

  .page-curator-title {
    margin: 0 0 8px;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--ds-on-surface);
    letter-spacing: 0.02em;
  }

  .page-curator-subtitle {
    margin: 0;
    font-size: 0.875rem;
    color: var(--ds-on-surface-variant);
    line-height: 1.5;
    max-width: 620px;
  }

  .store-setting-surface {
    border-radius: 12px;
    border-color: var(--ds-outline-ghost);
    box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

    :deep(.el-tabs__item) {
      color: var(--ds-on-surface-variant);

      &.is-active {
        color: var(--ds-primary);
        font-weight: 600;
      }
    }

    :deep(.el-tabs__active-bar) {
      background: var(--ds-primary);
    }
  }

  :deep(.btn-curator-primary.el-button--primary) {
    border: none;
    border-radius: 6px;
    color: var(--ds-on-primary);
    font-weight: 500;
    padding-left: 18px;
    padding-right: 18px;
    box-shadow: 0 2px 8px var(--brand-shadow);
  }

  .form-container {
    max-width: 800px;
    padding: 20px 0;
  }

  .form-value {
    color: var(--ds-on-surface);
  }

  .form-tip {
    margin-left: 15px;
    color: var(--text-secondary);
    font-size: 12px;
  }

  .upload-item {
    display: flex;
    align-items: flex-end;
  }

  .upload-tip {
    margin-left: 15px;
    color: var(--text-secondary);
    font-size: 12px;
  }

  .location-info {
    margin-top: 10px;

    .current-location {
      margin-left: 10px;
      color: var(--text-secondary);
      font-size: 12px;
    }
  }

  .address-row {
    display: flex;
    align-items: center;
    width: 500px;
  }

  .current-location {
    margin: 0 8px;
    color: var(--text-secondary);
    font-size: 13px;
  }

  .map-preview {
    border: 1px solid var(--ds-outline-ghost);
    border-radius: 8px;
    overflow: hidden;
    display: inline-block;

    img {
      display: block;
      width: 500px;
      height: 300px;
      object-fit: cover;
    }
  }

  .form-footer {
    padding: 20px 0;
    border-top: 1px solid var(--ds-outline-ghost);
    text-align: left;
    margin-left: 140px;
  }
}
</style>
