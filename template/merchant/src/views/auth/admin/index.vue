<template>
  <div class="auth-admin-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">账号设置</h1>
        <p class="page-curator-subtitle">
          维护当前管理员的头像、姓名与登录密码，保持账号资料与安全信息最新。
        </p>
      </div>
    </header>

    <el-card :bordered="false" shadow="never" class="profile-form-surface">
      <el-form
        ref="formRef"
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
        <el-form-item label="姓名：" prop="real_name">
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
          <el-button type="primary" class="btn-curator-primary" @click="handleSubmit">提交</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { getAdminUserInfoApi, putAdminUserApi } from '@/api/system';
import Upload from '@/components/Upload';
import { useUserStore } from '@/store/modules/user';
import { onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';

defineOptions({
  name: 'SettingUser'
});

const userStore = useUserStore();

const formRef = ref(null);
const formValidate = reactive({
  avatar: '',
  real_name: '',
  account: '',
  password: '',
  new_password: '',
  conf_password: ''
});
const ruleValidate = {
  real_name: [{ required: true, message: '您的姓名不能为空', trigger: 'blur' }]
};

onMounted(() => {
  getAdminUserInfo();
});

function getAdminUserInfo() {
  getAdminUserInfoApi().then(res => {
    formValidate.avatar = res.data.avatar
    formValidate.account = res.data.account
    formValidate.real_name = res.data.real_name
  })
}

function handleSubmit() {
  formRef.value.validate((valid) => {
    if (valid) {
      putAdminUserApi(formValidate)
        .then((res) => {
          getAdminUserInfoApi().then(res => {
            userStore.setAdminInfo(res.data);
          })
          ElMessage.success(res.message);
        })
        .catch((res) => {
          ElMessage.error(res.message);
        });
    } else {
      if (formValidate.new_password !== formValidate.conf_password) {
        ElMessage.error('您输入的新密码与旧密码不一致');
      }
    }
  });
}
</script>

<style lang="scss" scoped>
.auth-admin-curator-page {
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

.profile-form-surface {
  border-radius: 12px;
  border-color: var(--ds-outline-ghost);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
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

.input {
  width: 400px;
}

.avatar {
  width: 80px;
  height: 80px;

  img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 1px solid var(--ds-outline-ghost);
  }
}
</style>
