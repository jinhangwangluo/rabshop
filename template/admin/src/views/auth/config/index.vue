<template>
  <div>
    <el-card
      :bordered="false"
      shadow="never"
      class="ivu-mb-16"
      :body-style="{padding:0}"
    >
      <div class="padding-add">
        <el-form
          ref="form"
          :model="form"
          class="config"
        >
          <el-tabs v-model="activeName" @tab-click="handleClick">
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
            <el-card class="box-card">
              <template #header>
                <div class="clearfix">
                  <span>饿了么应用</span>
                </div>
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
            <el-card class="box-card mt10">
              <template #header>
                <div class="clearfix">
                  <span>美团应用</span>
                </div>
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
            <el-card class="box-card mt10 mb10">
              <template #header>
                <div class="clearfix">
                  <span>小程序应用</span>
                </div>
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
              @click="onSubmit"
            >
              保存
            </el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, ref } from 'vue'
import { systemConfigSaveApi, systemGetConfigApi } from '@/api/system'

defineOptions({
  name: 'Index'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message

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

function getConfig() {
  systemGetConfigApi(configKeys).then(res => {
    form.value = res.data || {}
  })
}

function handleClick() {}

function onSubmit() {
  loading.value = true
  systemConfigSaveApi(form.value).then(res => {
    message.success(res.message)
  }).finally(() => {
    loading.value = false
  })
}

onBeforeMount(() => {
  getConfig()
})
</script>

<style scoped>
.avatar-uploader .el-upload {
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}

.avatar-uploader .el-upload:hover {
  border-color: #409EFF;
}

.avatar-uploader .avatar-uploader-icon {
  font-size: 28px;
  color: #8c939d;
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
  padding: 10px;
}

.el-table {
  width: unset !important;
}

.add-temp {
  margin-top: 10px;
  margin-left: 60px !important;
}

</style>
