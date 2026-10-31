<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mt">
      <el-form
        ref="formValidateRef"
        :model="formValidate"
        :rules="ruleValidate"
        label-width="160px"
        label-position="right"
      >
        <el-form-item label="头像：">
          <Upload v-model="formValidate.avatar" :limit="1" />
        </el-form-item>
        <el-form-item label="账号：" prop="">
          <el-input v-model="formValidate.account" type="text" :disabled="true" class="input" />
        </el-form-item>
        <el-form-item label="姓名：" prop="name">
          <el-input v-model="formValidate.real_name" type="text" class="input" />
        </el-form-item>
        <el-form-item label="原始密码：">
          <el-input v-model="formValidate.password" type="password" class="input" />
        </el-form-item>
        <el-form-item label="新密码：">
          <el-input v-model="formValidate.new_password" type="password" class="input" />
        </el-form-item>
        <el-form-item label="确认新密码：">
          <el-input v-model="formValidate.conf_password" type="password" class="input" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSubmit">提交</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { getAdminUserInfoApi, putAdminUserApi } from '@/api/system'
import Upload from '@/components/Upload'
import { useUserStore } from '@/store/modules/user'

defineOptions({
  name: 'SettingUser'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const userStore = useUserStore()
const formValidateRef = ref(null)
const formValidate = reactive({
  avatar: '',
  real_name: '',
  account: '',
  password: '',
  new_password: '',
  conf_password: ''
})
const ruleValidate = {
  real_name: [{ required: true, message: '您的姓名不能为空', trigger: 'blur' }]
}

function getAdminUserInfo() {
  getAdminUserInfoApi().then(res => {
    formValidate.avatar = res.data.avatar
    formValidate.account = res.data.account
    formValidate.real_name = res.data.real_name
  })
}

function handleSubmit() {
  formValidateRef.value.validate(valid => {
    if (valid) {
      putAdminUserApi(formValidate)
        .then(res => {
          getAdminUserInfoApi().then(res => {
            userStore.setAdminInfo(res.data)
          })
          message.success(res.message)
        })
        .catch(res => {
          message.error(res.message)
        })
    } else if (formValidate.new_password !== formValidate.conf_password) {
      message.error('您输入的新密码与旧密码不一致')
    }
  })
}

onBeforeMount(() => {
  getAdminUserInfo()
})
</script>

<style lang="scss" scoped>
.input {
  width: 400px;
}
</style>
