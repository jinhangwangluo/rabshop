<template>
  <div class="auth-config-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">系统配置</h1>
        <p class="page-curator-subtitle">
          统一维护网站信息、上传参数与渠道应用配置，确保商户端基础能力可控可追踪。
        </p>
      </div>
    </header>

    <div class="auth-config-work-surface">
      <el-card
        :bordered="false"
        shadow="never"
        class="config-form-surface"
        :body-style="{padding:0}"
      >
        <div class="padding-add">
          <el-form
            ref="form"
            :model="form"
            class="config"
          >
            <el-tabs v-model="activeName" class="config-tabs" @tab-click="handleClick">
              <el-tab-pane label="网站配置" name="0" />
              <el-tab-pane label="上传配置" name="1" />
              <el-tab-pane label="渠道管理" name="2" />
            </el-tabs>

            <template v-if="activeName === '0'">
              <el-row>
                <el-col :span="12">
                  <el-form-item label="网站名称">
                    <el-input v-model="form.site_name" placeholder="请输入网站名称" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="网站域名">
                    <el-input v-model="form.web_host" placeholder="请输入网站域名" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="网站备案号">
                    <el-input v-model="form.site_record_number" placeholder="请输入网站备案号" />
                  </el-form-item>
                </el-col>
              </el-row>
            </template>
            <template v-if="activeName === '1'">
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云 ACCESS">
                    <el-input v-model="form.cos_access_key" placeholder="请输入腾讯云 ACCESS" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云 SECRET">
                    <el-input v-model="form.cos_secret_key" placeholder="请输入腾讯云 SECRET" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云 APPID">
                    <el-input v-model="form.cos_appid" placeholder="请输入腾讯云 APPID" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云OSS桶名">
                    <el-input v-model="form.cos_name" placeholder="请输入腾讯云OSS桶名" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云OSS所属地域">
                    <el-input v-model="form.cos_region" placeholder="请输入腾讯云OSS所属地域" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云OSS域名">
                    <el-input v-model="form.cos_url" placeholder="请输入腾讯云OSS域名" />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row>
                <el-col :span="12">
                  <el-form-item label="腾讯云OSS CDN 加速域名">
                    <el-input v-model="form.cos_cdn" placeholder="请输入腾讯云OSS CDN 加速域名" />
                  </el-form-item>
                </el-col>
              </el-row>
            </template>

            <template v-if="activeName === '2'">
              <el-card class="channel-config-card">
                <template #header>
                  <span>饿了么应用</span>
                </template>
                <el-row>
                  <el-col :span="12">
                    <el-form-item label="应用appid">
                      <el-input v-model="form.eleme_appid" placeholder="请输入应用appid" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row>
                  <el-col :span="12">
                    <el-form-item label="应用Secret">
                      <el-input v-model="form.eleme_secret" placeholder="请输入应用Secret" />
                    </el-form-item>
                  </el-col>
                </el-row>
              </el-card>
              <el-card class="channel-config-card mt10">
                <template #header>
                  <span>美团应用</span>
                </template>
                <el-row>
                  <el-col :span="12">
                    <el-form-item label="应用appid">
                      <el-input v-model="form.meituan_appid" placeholder="请输入应用appid" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row>
                  <el-col :span="12">
                    <el-form-item label="应用Secret">
                      <el-input v-model="form.meituan_secret" placeholder="请输入应用Secret" />
                    </el-form-item>
                  </el-col>
                </el-row>
              </el-card>
              <el-card class="channel-config-card mt10 mb10">
                <template #header>
                  <span>小程序应用</span>
                </template>
                <el-row>
                  <el-col :span="12">
                    <el-form-item label="应用appid">
                      <el-input v-model="form.program_appid" placeholder="请输入应用appid" />
                    </el-form-item>
                  </el-col>
                </el-row>
                <el-row>
                  <el-col :span="12">
                    <el-form-item label="应用Secret">
                      <el-input v-model="form.program_secret" placeholder="请输入应用Secret" />
                    </el-form-item>
                  </el-col>
                </el-row>
              </el-card>
            </template>

            <el-form-item>
              <el-button
                :loading="loading"
                type="primary"
                class="btn-curator-primary"
                @click="onSubmit"
              >
                保存
              </el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { getToken } from '@/utils/auth';
import setting from '@/settings'
import { systemConfigSaveApi, systemGetConfigApi } from '@/api/system'
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'

defineOptions({
  name: 'Index'
})

const activeName = ref('0')
const form = ref({})
const loading = ref(false)
const configKeys = [
  'site_name',
  'web_host',
  'site_record_number',
  'cos_access_key',
  'cos_secret_key',
  'cos_appid',
  'cos_name',
  'cos_region',
  'cos_url',
  'cos_cdn',
  'eleme_appid',
  'eleme_secret',
  'meituan_appid',
  'meituan_secret',
  'program_appid',
  'program_secret'
]
const uri = setting.baseUri + '/setting/file/upload'
const header = {
  'X-Requested-With': 'XMLHttpRequest',
  'Authorization': 'Bearer ' + getToken()
}

onMounted(() => {
  getConfig()
})

function getConfig() {
  systemGetConfigApi(configKeys).then(res => {
    form.value = res.data
  })
}

function handleAvatarSuccess(data) {
  if (data.status === 200) {
    form.value.ad_images = data.data.filePath
  } else {
    ElMessage.error(data.msg)
  }
}

function beforeAvatarUpload(file) {
  const isJPG = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'].indexOf(file.type) !== -1;
  const isLt2M = file.size / 1024 / 1024 < 2;

  if (!isJPG) {
    ElMessage.error('上传头像图片只能是 JPG、PNG、GIF 格式!');
  }
  if (!isLt2M) {
    ElMessage.error('上传头像图片大小不能超过 2MB!');
  }
  return isJPG && isLt2M;
}

function handleClick(name) {

}

function onSubmit() {
  loading.value = true
  systemConfigSaveApi(form.value).then(res => {
    ElMessage.success(res.message)
  }).finally(() => {
    loading.value = false
  })
}
</script>

<style scoped lang="scss">
.auth-config-curator-page {
  min-height: 100%;
  box-sizing: border-box;
}

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

.auth-config-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.config-form-surface {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.config-tabs {
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

.channel-config-card {
  margin-top: 8px;
  border-radius: 10px;
  border-color: var(--ds-outline-ghost);
  box-shadow: none;

  :deep(.el-card__header) {
    padding: 14px 20px;
    color: var(--ds-on-surface);
    font-weight: 600;
    background: rgba(var(--ds-surface-low-rgb), 0.45);
    border-bottom-color: var(--ds-outline-ghost);
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

.avatar-uploader .el-upload {
  border: 1px dashed var(--ds-outline);
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.avatar-uploader .el-upload:hover {
  border-color: var(--ds-primary);
}

.avatar-uploader .avatar-uploader-icon {
  font-size: 28px;
  color: var(--text-secondary);
  width: 178px;
  height: 178px;
  line-height: 178px;
  text-align: center;
}

.avatar-uploader .avatar {
  width: 178px;
  height: 178px;
  display: block;
}

.avatar-uploader .el-upload__input {
  display: none !important;
}

.config {
  padding: 4px 0;
}

.el-table {
  width: unset !important;
}

.add-temp {
  margin-top: 10px;
  margin-left: 60px !important;
}
</style>
