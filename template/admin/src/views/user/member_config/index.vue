<template>
  <div v-loading="pageLoading" class="member-config-page">
    <el-card shadow="never" class="member-config-page__section">
      <div class="member-config-page__header">
        <div>
          <div class="member-config-page__title">VIP会员配置</div>
          <div class="member-config-page__desc">控制会员购买、会员权益、积分广告和移动端展示</div>
        </div>
        <el-button
          :loading="configSaving"
          type="primary"
          :icon="Check"
          @click="submitConfig"
        >
          保存配置
        </el-button>
      </div>

      <el-form
        ref="configFormRef"
        :model="configForm"
        :rules="configRules"
        label-width="130px"
        class="member-config-page__form"
      >
        <el-form-item label="VIP会员开关" prop="member_enabled">
          <el-switch
            v-model="configForm.member_enabled"
            active-value="1"
            inactive-value="0"
            active-text="开启"
            inactive-text="关闭"
          />
          <div class="member-config-page__tip">关闭后移动端不展示会员套餐和权益，不允许购买会员，也不计算会员折扣和积分倍数。</div>
        </el-form-item>

        <template v-if="isMemberEnabled">
          <el-divider content-position="left">基础权益配置</el-divider>
          <el-form-item label="会员积分倍数" prop="member_points_multiplier">
            <el-input-number
              v-model="configForm.member_points_multiplier"
              :min="1"
              :max="99"
              :step="0.1"
              :precision="1"
              controls-position="right"
            />
          </el-form-item>
          <el-form-item label="会员折扣率" prop="member_discount_rate">
            <el-input-number
              v-model="configForm.member_discount_rate"
              :min="0.01"
              :max="1"
              :step="0.01"
              :precision="2"
              controls-position="right"
            />
            <div class="member-config-page__tip">0.80 表示 8 折，1.00 表示原价。</div>
          </el-form-item>

          <el-divider content-position="left">积分广告配置</el-divider>
          <el-form-item label="广告开关" prop="member_points_banner_enabled">
            <el-switch
              v-model="configForm.member_points_banner_enabled"
              active-value="1"
              inactive-value="0"
              active-text="开启"
              inactive-text="关闭"
            />
          </el-form-item>
          <el-form-item label="广告标题" prop="member_points_banner_title">
            <el-input v-model="configForm.member_points_banner_title" maxlength="60" placeholder="请输入广告标题" clearable />
          </el-form-item>
          <el-form-item label="广告简介" prop="member_points_banner_desc">
            <el-input
              v-model="configForm.member_points_banner_desc"
              type="textarea"
              :rows="3"
              maxlength="120"
              placeholder="请输入广告简介"
              clearable
            />
          </el-form-item>
          <el-form-item label="广告图片" prop="member_points_banner_image">
            <Upload v-model="configForm.member_points_banner_image" :limit="1" />
          </el-form-item>
        </template>
      </el-form>
    </el-card>

    <template v-if="isMemberEnabled">
      <el-card shadow="never" class="member-config-page__section">
        <template #header>
          <div class="member-config-page__card-header">
            <span>会员套餐</span>
            <el-button
              type="primary"
              size="mini"
              :icon="Plus"
              :disabled="!packageConfig.id"
              @click="openPackageDialog()"
            >
              添加套餐
            </el-button>
          </div>
        </template>
        <el-table v-loading="packageLoading" :data="packageList">
          <el-table-column prop="data_key" label="标识" min-width="110" />
          <el-table-column prop="data_name" label="套餐名称" min-width="120" />
          <el-table-column prop="data_price" label="支付金额" width="110" />
          <el-table-column prop="data_original_price" label="原价" width="110" />
          <el-table-column prop="data_duration_days" label="有效天数" width="110" />
          <el-table-column prop="data_tag" label="角标" min-width="100" />
          <el-table-column prop="sort" label="排序" width="80" />
          <el-table-column prop="status" label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="mini">
                {{ Number(row.status) === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="130" fixed="right">
            <template #default="{ row }">
              <a href="javascript:;" class="member-config-page__action" @click="openPackageDialog(row)">编辑</a>
              <a href="javascript:;" class="member-config-page__action member-config-page__action--danger" @click="deleteData(row, 'package')">删除</a>
            </template>
          </el-table-column>
        </el-table>
      </el-card>

      <el-card shadow="never" class="member-config-page__section">
        <template #header>
          <div class="member-config-page__card-header">
            <span>会员权益</span>
            <el-button
              type="primary"
              size="mini"
              :icon="Plus"
              :disabled="!benefitConfig.id"
              @click="openBenefitDialog()"
            >
              添加权益
            </el-button>
          </div>
        </template>
        <el-table v-loading="benefitLoading" :data="benefitList">
          <el-table-column prop="data_key" label="标识" min-width="110" />
          <el-table-column prop="data_label" label="权益名称" min-width="120" />
          <el-table-column label="图标" width="90">
            <template #default="{ row }">
              <el-image
                v-if="row.data_symbol"
                :src="row.data_symbol"
                :preview-src-list="[row.data_symbol]"
                class="member-config-page__icon-preview"
                fit="cover"
              />
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column prop="data_desc" label="权益说明" min-width="180" />
          <el-table-column prop="sort" label="排序" width="80" />
          <el-table-column prop="status" label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="mini">
                {{ Number(row.status) === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="130" fixed="right">
            <template #default="{ row }">
              <a href="javascript:;" class="member-config-page__action" @click="openBenefitDialog(row)">编辑</a>
              <a href="javascript:;" class="member-config-page__action member-config-page__action--danger" @click="deleteData(row, 'benefit')">删除</a>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </template>

    <el-dialog
      v-model="packageVisible"
      :title="packageForm.id ? '编辑套餐' : '添加套餐'"
      width="620px"
      :close-on-click-modal="false"
    >
      <el-form ref="packageFormRef" :model="packageForm" :rules="packageRules" label-width="100px">
        <el-form-item label="套餐标识" prop="key">
          <el-input v-model="packageForm.key" placeholder="例如 month" clearable />
        </el-form-item>
        <el-form-item label="套餐名称" prop="name">
          <el-input v-model="packageForm.name" placeholder="请输入套餐名称" clearable />
        </el-form-item>
        <el-form-item label="支付金额" prop="price">
          <el-input-number v-model="packageForm.price" :min="0" :max="99999" :step="0.01" :precision="2" controls-position="right" />
        </el-form-item>
        <el-form-item label="原价" prop="original_price">
          <el-input-number v-model="packageForm.original_price" :min="0" :max="99999" :step="0.01" :precision="2" controls-position="right" />
        </el-form-item>
        <el-form-item label="有效天数" prop="duration_days">
          <el-input-number v-model="packageForm.duration_days" :min="1" :max="99999" :step="1" controls-position="right" />
        </el-form-item>
        <el-form-item label="角标">
          <el-input v-model="packageForm.tag" placeholder="例如 最划算" clearable />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="packageForm.sort" :min="0" :step="1" controls-position="right" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="packageForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="packageVisible = false">取消</el-button>
          <el-button :loading="packageSaving" type="primary" @click="submitPackage">保存</el-button>
        </div>
      </template>
    </el-dialog>

    <el-dialog
      v-model="benefitVisible"
      :title="benefitForm.id ? '编辑权益' : '添加权益'"
      width="620px"
      :close-on-click-modal="false"
    >
      <el-form ref="benefitFormRef" :model="benefitForm" :rules="benefitRules" label-width="100px">
        <el-form-item label="权益标识" prop="key">
          <el-input v-model="benefitForm.key" placeholder="例如 points" clearable />
        </el-form-item>
        <el-form-item label="权益名称" prop="label">
          <el-input v-model="benefitForm.label" placeholder="请输入权益名称" clearable />
        </el-form-item>
        <el-form-item label="权益图标" prop="symbol">
          <Upload v-model="benefitForm.symbol" :limit="1" />
        </el-form-item>
        <el-form-item label="权益说明" prop="desc">
          <el-input v-model="benefitForm.desc" type="textarea" :rows="3" placeholder="请输入权益说明" clearable />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="benefitForm.sort" :min="0" :step="1" controls-position="right" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="benefitForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="benefitVisible = false">取消</el-button>
          <el-button :loading="benefitSaving" type="primary" @click="submitBenefit">保存</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeMount, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Plus } from '@element-plus/icons-vue'
import {
  configSaveApi,
  getConfigTemplateApi
} from '@/api/system'
import { getConfigTemplateFindApi } from '@/api/setting'
import {
  getCombinationDataConfigList,
  getCombinationDataList,
  saveCombinationData,
  updateCombinationData,
  deleteCombinationData
} from '@/api/combinationData'
import Upload from '@/components/Upload'

defineOptions({ name: 'MemberConfig' })

const MEMBER_CONFIG_KEY = 'member_config'
const PACKAGE_CONFIG_KEY = 'member_package'
const BENEFIT_CONFIG_KEY = 'member_benefit'

const defaultConfigForm = {
  member_enabled: '1',
  member_points_multiplier: 2,
  member_discount_rate: 1,
  member_points_banner_enabled: '1',
  member_points_banner_title: '黑卡会员多倍积分',
  member_points_banner_desc: '立即升级，专享购物多倍积分',
  member_points_banner_image: '/static/images/coin.png'
}

function createPackageForm() {
  return { id: 0, key: '', name: '', price: 0, original_price: 0, duration_days: 30, tag: '', sort: 0, status: 1 }
}

function createBenefitForm() {
  return { id: 0, key: '', label: '', symbol: '', desc: '', sort: 0, status: 1 }
}
const pageLoading = ref(false)
const configSaving = ref(false)
const memberTemplateId = ref(0)
const configFormRef = ref(null)
const packageFormRef = ref(null)
const benefitFormRef = ref(null)
const configForm = reactive({ ...defaultConfigForm })
const packageConfig = ref({})
const benefitConfig = ref({})
const packageList = ref([])
const benefitList = ref([])
const packageLoading = ref(false)
const benefitLoading = ref(false)
const packageVisible = ref(false)
const benefitVisible = ref(false)
const packageSaving = ref(false)
const benefitSaving = ref(false)
const packageForm = reactive(createPackageForm())
const benefitForm = reactive(createBenefitForm())
const configRules = {
  member_points_multiplier: [{ required: true, message: '请填写会员积分倍数', trigger: 'change' }],
  member_discount_rate: [{ required: true, message: '请填写会员折扣率', trigger: 'change' }],
  member_points_banner_title: [{ required: true, message: '请填写广告标题', trigger: 'blur' }],
  member_points_banner_desc: [{ required: true, message: '请填写广告简介', trigger: 'blur' }],
  member_points_banner_image: [{ required: true, message: '请填写广告图片', trigger: 'blur' }]
}
const packageRules = {
  key: [{ required: true, message: '请填写套餐标识', trigger: 'blur' }],
  name: [{ required: true, message: '请填写套餐名称', trigger: 'blur' }],
  price: [{ required: true, message: '请填写支付金额', trigger: 'change' }],
  duration_days: [{ required: true, message: '请填写有效天数', trigger: 'change' }]
}
const benefitRules = {
  key: [{ required: true, message: '请填写权益标识', trigger: 'blur' }],
  label: [{ required: true, message: '请填写权益名称', trigger: 'blur' }],
  symbol: [{ required: true, message: '请填写权益图标', trigger: 'blur' }],
  desc: [{ required: true, message: '请填写权益说明', trigger: 'blur' }]
}

const isMemberEnabled = computed(() => String(configForm.member_enabled) === '1')

async function initPage() {
  pageLoading.value = true
  try {
    await loadConfig()
    await loadCombinationConfigs()
    if (isMemberEnabled.value) {
      await Promise.all([loadPackages(), loadBenefits()])
    }
  } catch (e) {
    ElMessage.error(e.message || '加载会员配置失败')
  } finally {
    pageLoading.value = false
  }
}

async function loadConfig() {
  const res = await getConfigTemplateApi({ config_key: MEMBER_CONFIG_KEY, page: 1, limit: 1 })
  const data = res.data || {}
  const list = data.list || []
  const template = list.find(item => item.config_key === MEMBER_CONFIG_KEY)

  if (!template) {
    throw new Error('会员配置模板缺失，请先执行数据库迁移')
  }

  memberTemplateId.value = Number(template.id || 0)
  const detail = await getConfigTemplateFindApi(memberTemplateId.value)
  const configData = (detail.data && detail.data.configData) || {}
  Object.assign(configForm, normalizeConfig({ ...defaultConfigForm, ...configData }))
}

function normalizeConfig(data) {
  return {
    member_enabled: String(data.member_enabled === undefined || data.member_enabled === '' ? '1' : data.member_enabled),
    member_points_multiplier: Number(data.member_points_multiplier || 2),
    member_discount_rate: Number(data.member_discount_rate || 1),
    member_points_banner_enabled: String(data.member_points_banner_enabled === undefined || data.member_points_banner_enabled === '' ? '1' : data.member_points_banner_enabled),
    member_points_banner_title: String(data.member_points_banner_title || defaultConfigForm.member_points_banner_title),
    member_points_banner_desc: String(data.member_points_banner_desc || defaultConfigForm.member_points_banner_desc),
    member_points_banner_image: String(data.member_points_banner_image || defaultConfigForm.member_points_banner_image)
  }
}

async function loadCombinationConfigs() {
  const res = await getCombinationDataConfigList({ keyword: 'member_', status: 1, page: 1, limit: 20 })
  const list = (res.data && res.data.list) || []
  packageConfig.value = list.find(item => item.config_key === PACKAGE_CONFIG_KEY) || {}
  benefitConfig.value = list.find(item => item.config_key === BENEFIT_CONFIG_KEY) || {}
  if (!packageConfig.value.id || !benefitConfig.value.id) {
    ElMessage.warning('会员套餐或权益组合数据配置缺失，请先执行数据库迁移')
  }
}

async function loadPackages() {
  if (!packageConfig.value.id) return
  packageLoading.value = true
  try {
    const res = await getCombinationDataList({ config_id: packageConfig.value.id, page: 1, limit: 100 })
    packageList.value = (res.data && res.data.list) || []
  } finally {
    packageLoading.value = false
  }
}

async function loadBenefits() {
  if (!benefitConfig.value.id) return
  benefitLoading.value = true
  try {
    const res = await getCombinationDataList({ config_id: benefitConfig.value.id, page: 1, limit: 100 })
    benefitList.value = (res.data && res.data.list) || []
  } finally {
    benefitLoading.value = false
  }
}

function submitConfig() {
  configFormRef.value?.validate(async valid => {
    if (!valid) return
    if (!memberTemplateId.value) {
      ElMessage.error('会员配置模板缺失，请先执行数据库迁移')
      return
    }
    configSaving.value = true
    try {
      await configSaveApi({
        id: memberTemplateId.value,
        form_data: { ...configForm }
      })
      ElMessage.success('保存成功')
      if (isMemberEnabled.value && (!packageList.value.length || !benefitList.value.length)) {
        await Promise.all([loadPackages(), loadBenefits()])
      }
    } catch (e) {
      ElMessage.error(e.message || '保存失败')
    } finally {
      configSaving.value = false
    }
  })
}

function openPackageDialog(row) {
  const formData = row && row.form_data ? row.form_data : {}
  Object.assign(packageForm, row
    ? { ...createPackageForm(), ...formData, id: row.id, sort: Number(row.sort || 0), status: Number(row.status === undefined ? 1 : row.status) }
    : createPackageForm())
  packageVisible.value = true
  nextTick(() => {
    packageFormRef.value?.clearValidate()
  })
}

function submitPackage() {
  packageFormRef.value?.validate(async valid => {
    if (!valid) return
    if (!packageConfig.value.id) {
      ElMessage.error('会员套餐配置缺失，请先执行数据库迁移')
      return
    }
    packageSaving.value = true
    try {
      const payload = {
        category_id: packageConfig.value.category_id || 0,
        config_id: packageConfig.value.id,
        sort: Number(packageForm.sort || 0),
        status: Number(packageForm.status),
        form_data: {
          key: packageForm.key,
          name: packageForm.name,
          price: Number(packageForm.price || 0),
          original_price: Number(packageForm.original_price || 0),
          duration_days: Number(packageForm.duration_days || 0),
          tag: packageForm.tag || ''
        }
      }
      if (packageForm.id) {
        await updateCombinationData(packageForm.id, payload)
      } else {
        await saveCombinationData(payload)
      }
      packageVisible.value = false
      await loadPackages()
      ElMessage.success('保存成功')
    } catch (e) {
      ElMessage.error(e.message || '保存失败')
    } finally {
      packageSaving.value = false
    }
  })
}

function openBenefitDialog(row) {
  const formData = row && row.form_data ? row.form_data : {}
  Object.assign(benefitForm, row
    ? { ...createBenefitForm(), ...formData, id: row.id, sort: Number(row.sort || 0), status: Number(row.status === undefined ? 1 : row.status) }
    : createBenefitForm())
  benefitVisible.value = true
  nextTick(() => {
    benefitFormRef.value?.clearValidate()
  })
}

function submitBenefit() {
  benefitFormRef.value?.validate(async valid => {
    if (!valid) return
    if (!benefitConfig.value.id) {
      ElMessage.error('会员权益配置缺失，请先执行数据库迁移')
      return
    }
    benefitSaving.value = true
    try {
      const payload = {
        category_id: benefitConfig.value.category_id || 0,
        config_id: benefitConfig.value.id,
        sort: Number(benefitForm.sort || 0),
        status: Number(benefitForm.status),
        form_data: {
          key: benefitForm.key,
          label: benefitForm.label,
          symbol: benefitForm.symbol,
          desc: benefitForm.desc
        }
      }
      if (benefitForm.id) {
        await updateCombinationData(benefitForm.id, payload)
      } else {
        await saveCombinationData(payload)
      }
      benefitVisible.value = false
      await loadBenefits()
      ElMessage.success('保存成功')
    } catch (e) {
      ElMessage.error(e.message || '保存失败')
    } finally {
      benefitSaving.value = false
    }
  })
}

function deleteData(row, type) {
  ElMessageBox.confirm('确定删除该数据吗？', '提示').then(async() => {
    await deleteCombinationData(row.id)
    if (type === 'package') {
      await loadPackages()
    } else {
      await loadBenefits()
    }
    ElMessage.success('删除成功')
  }).catch(() => {})
}

onBeforeMount(initPage)
</script>

<style lang="scss" scoped>
.member-config-page {

  &__section {
    margin-bottom: 16px;
  }

  &__header,
  &__card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__title {
    font-size: 18px;
    font-weight: 600;
    color: #303133;
  }

  &__desc,
  &__tip {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.6;
    color: #909399;
  }

  &__form {
    margin-top: 18px;
    max-width: 760px;
  }

  &__icon-preview {
    width: 34px;
    height: 34px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
  }

  &__action {
    margin-right: 10px;
    color: var(--ds-primary);
    text-decoration: none;
    cursor: pointer;

    &:last-child {
      margin-right: 0;
    }

    &--danger {
      color: #f56c6c;
    }
  }
}

:deep(.el-input-number) {
  width: 220px;
}

:deep(.el-dialog .el-input-number) {
  width: 100%;
}
</style>
