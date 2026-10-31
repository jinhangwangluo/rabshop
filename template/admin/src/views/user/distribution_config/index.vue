<template>
  <div v-loading="pageLoading" class="distribution-config-page">
    <el-card shadow="never" class="distribution-config-page__section">
      <div class="distribution-config-page__header">
        <div>
          <div class="distribution-config-page__title">分销配置</div>
          <div class="distribution-config-page__desc">控制分销开关、等级、模式和全局返佣比例</div>
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
        class="distribution-config-page__form"
      >
        <el-form-item label="分销总开关" prop="distribution_enabled">
          <el-switch
            v-model="configForm.distribution_enabled"
            active-value="1"
            inactive-value="0"
            active-text="开启"
            inactive-text="关闭"
          />
          <div class="distribution-config-page__tip">关闭后移动端不允许生成推广海报，下单不会记录返佣。</div>
        </el-form-item>

        <template v-if="isDistributionEnabled">
          <el-form-item label="分销等级" prop="distribution_level">
            <el-radio-group v-model="configForm.distribution_level">
              <el-radio-button label="1">一级</el-radio-button>
              <el-radio-button label="2">二级</el-radio-button>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="分销模式" prop="distribution_mode">
            <el-radio-group v-model="configForm.distribution_mode">
              <el-radio-button label="all">人人分销</el-radio-button>
              <el-radio-button label="specified">指定分销</el-radio-button>
            </el-radio-group>
            <div class="distribution-config-page__tip">人人分销下，用户列表仍可单独设置为禁止分销。</div>
          </el-form-item>

          <el-form-item label="一级返佣比例" prop="distribution_first_rate">
            <el-input-number
              v-model="configForm.distribution_first_rate"
              :min="0"
              :max="100"
              :step="0.01"
              :precision="2"
              controls-position="right"
            />
            <span class="distribution-config-page__unit">%</span>
          </el-form-item>

          <el-form-item label="二级返佣比例" prop="distribution_second_rate">
            <el-input-number
              v-model="configForm.distribution_second_rate"
              :disabled="String(configForm.distribution_level) === '1'"
              :min="0"
              :max="100"
              :step="0.01"
              :precision="2"
              controls-position="right"
            />
            <span class="distribution-config-page__unit">%</span>
          </el-form-item>
        </template>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import {
  configSaveApi,
  getConfigTemplateApi
} from '@/api/system'
import { getConfigTemplateFindApi } from '@/api/setting'

defineOptions({ name: 'DistributionConfig' })

const DISTRIBUTION_CONFIG_KEY = 'distribution_config'

const defaultConfigForm = {
  distribution_enabled: '0',
  distribution_level: '2',
  distribution_mode: 'specified',
  distribution_first_rate: 0,
  distribution_second_rate: 0
}

const pageLoading = ref(false)
const configSaving = ref(false)
const templateId = ref(0)
const configFormRef = ref(null)
const configForm = reactive({ ...defaultConfigForm })
const configRules = {
  distribution_level: [{ required: true, message: '请选择分销等级', trigger: 'change' }],
  distribution_mode: [{ required: true, message: '请选择分销模式', trigger: 'change' }],
  distribution_first_rate: [{ required: true, message: '请填写一级返佣比例', trigger: 'change' }],
  distribution_second_rate: [{ required: true, message: '请填写二级返佣比例', trigger: 'change' }]
}

const isDistributionEnabled = computed(() => String(configForm.distribution_enabled) === '1')

async function loadConfig() {
  pageLoading.value = true
  try {
    const res = await getConfigTemplateApi({ config_key: DISTRIBUTION_CONFIG_KEY, page: 1, limit: 1 })
    const data = res.data || {}
    const list = data.list || []
    const template = list.find(item => item.config_key === DISTRIBUTION_CONFIG_KEY)

    if (!template) {
      throw new Error('分销配置模板缺失，请先执行数据库迁移')
    }

    templateId.value = Number(template.id || 0)
    const detail = await getConfigTemplateFindApi(templateId.value)
    const configData = (detail.data && detail.data.configData) || {}
    Object.assign(configForm, normalizeConfig({ ...defaultConfigForm, ...configData }))
  } catch (e) {
    ElMessage.error(e.message || '加载分销配置失败')
  } finally {
    pageLoading.value = false
  }
}

function normalizeConfig(data) {
  const level = String(data.distribution_level || defaultConfigForm.distribution_level)
  const mode = String(data.distribution_mode || defaultConfigForm.distribution_mode)
  return {
    distribution_enabled: String(data.distribution_enabled === undefined || data.distribution_enabled === '' ? '0' : data.distribution_enabled),
    distribution_level: ['1', '2'].includes(level) ? level : '2',
    distribution_mode: ['all', 'specified'].includes(mode) ? mode : 'specified',
    distribution_first_rate: Number(data.distribution_first_rate || 0),
    distribution_second_rate: Number(data.distribution_second_rate || 0)
  }
}

function submitConfig() {
  configFormRef.value?.validate(async valid => {
    if (!valid) return
    if (!templateId.value) {
      ElMessage.error('分销配置模板缺失，请先执行数据库迁移')
      return
    }
    configSaving.value = true
    try {
      await configSaveApi({
        id: templateId.value,
        form_data: { ...configForm }
      })
      ElMessage.success('保存成功')
    } catch (e) {
      ElMessage.error(e.message || '保存失败')
    } finally {
      configSaving.value = false
    }
  })
}

onBeforeMount(loadConfig)
</script>

<style lang="scss" scoped>
.distribution-config-page {

  &__section {
    margin-bottom: 16px;
  }

  &__header {
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

  &__unit {
    margin-left: 8px;
    color: #606266;
  }
}
</style>
