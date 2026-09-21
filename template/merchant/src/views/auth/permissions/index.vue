<template>
  <div class="auth-permissions-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">管理员权限</h1>
        <p class="page-curator-subtitle">
          管理商户后台账号、角色与启用状态，统一商户后台访问权限。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        :icon="Plus"
        @click="handleAdd"
      >
        添加管理员
      </el-button>
    </header>

    <div class="auth-permissions-work-surface">
      <el-card
        :bordered="false"
        shadow="never"
        class="search-card"
        :body-style="{padding:0}"
      >
        <div>
          <el-form
            ref="formValidate"
            :model="formValidate"
            label-position="right"
            inline
            @submit.prevent
          >
            <el-form-item
              label="状态："
              label-for="status1"
            >
              <el-select
                v-model="status"
                placeholder="请选择"
                clearable
                class="form_content_width"
                @change="userSearchs"
              >
                <el-option
                  value="all"
                  label="全部"
                />
                <el-option
                  value="1"
                  label="开启"
                />
                <el-option
                  value="0"
                  label="关闭"
                />
              </el-select>
            </el-form-item>
            <el-form-item
              label="搜索："
              label-for="status2"
            >
              <el-input
                v-model="formValidate.account"
                clearable
                placeholder="请输入账号"
                class="form_content_width"
              />
            </el-form-item>
            <el-form-item>
              <el-button
                type="primary"
                class="btn-curator-primary"
                @click="userSearchs"
              >
                查询
              </el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-card>
      <el-card
        :bordered="false"
        shadow="never"
        class="table-card"
      >
        <el-table
          v-loading="loading"
          :data="list"
          class="mt14 auth-permissions-curator-table"
          no-user-from-text="暂无数据"
          no-filtered-user-from-text="暂无筛选结果"
          highlight-current-row
        >
          <el-table-column
            label="姓名"
            width="120"
          >
            <template #default="{ row }">
              <span>{{ row.real_name }}</span>
            </template>
          </el-table-column>
          <el-table-column
            label="账号"
            min-width="130"
          >
            <template #default="{ row }">
              <span><el-tag v-if="row.pid === 0">超</el-tag><span class="ml10">{{ row.account }}</span></span>
            </template>
          </el-table-column>
          <el-table-column
            label="身份"
            min-width="130"
          >
            <template #default="{ row }">
              <div v-if="row.role_list && row.role_list.length !== 0">
                <el-tag
                  v-for="(item, index) in row.role_list"
                  :key="index"
                >
                  {{ item }}
                </el-tag>
              </div>
            </template>
          </el-table-column>
          <el-table-column
            label="消息提醒"
            width="90"
            align="center"
          >
            <template #default="{ row }">
              <el-tag
                :type="Number(row.message_notify_enabled) === 1 ? 'success' : 'info'"
              >
                {{ Number(row.message_notify_enabled) === 1 ? '开启' : '关闭' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column
            label="提醒手机号"
            min-width="130"
          >
            <template #default="{ row }">
              <span>{{ row.message_notify_phone || '--' }}</span>
            </template>
          </el-table-column>
          <el-table-column
            label="提醒邮箱"
            min-width="180"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              <span>{{ row.message_notify_email || '--' }}</span>
            </template>
          </el-table-column>
          <el-table-column
            label="最后一次登录时间"
            min-width="130"
          >
            <template #default="{ row }">
              <span>{{ row.last_time }}</span>
            </template>
          </el-table-column>
          <el-table-column
            label="最后一次登录ip"
            min-width="130"
          >
            <template #default="{ row }">
              <span>{{ row.last_ip }}</span>
            </template>
          </el-table-column>
          <el-table-column
            label="开启"
            min-width="70"
          >
            <template #default="{ row }">
              <el-switch
                v-model="row.status"
                class="defineSwitch"
                :active-value="1"
                :inactive-value="0"
                :value="row.status"
                size="large"
                active-text="开启"
                inactive-text="关闭"
                @change="onchangeIsShow(row)"
              />
            </template>
          </el-table-column>
          <el-table-column
            label="操作"
            fixed="right"
            width="100"
          >
            <template #default="{ row, $index }">
              <a @click="handleEdit(row)">编辑</a>
              <el-divider direction="vertical" />
              <a @click="del(row, '删除管理员', $index)">删除</a>
            </template>
          </el-table-column>
        </el-table>
        <div class="acea-row row-right page">
          <el-pagination
            v-if="total"
            v-model:current-page="formValidate.page"
            class="auth-permissions-curator-pagination"
            :total="total"
            :page-size="formValidate.limit"
            @current-change="currentChange"
          />
        </div>
      </el-card>
    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="640px"
      append-to-body
      :close-on-click-modal="false"
      @closed="resetAdminForm"
    >
      <el-form
        ref="adminFormRef"
        :model="adminForm"
        :rules="adminRules"
        label-width="110px"
        @submit.prevent
      >
        <el-form-item label="管理员姓名" prop="real_name">
          <el-input v-model="adminForm.real_name" placeholder="请输入管理员姓名" />
        </el-form-item>
        <el-form-item label="管理员账号" prop="account">
          <el-input v-model="adminForm.account" :disabled="Boolean(adminForm.id)" placeholder="请输入管理员账号" />
        </el-form-item>
        <el-form-item label="管理员密码" prop="password" :required="!adminForm.id">
          <el-input
            v-model="adminForm.password"
            type="password"
            show-password
            autocomplete="new-password"
            :placeholder="adminForm.id ? '不修改密码不需要填写' : '请输入管理员密码'"
          />
        </el-form-item>
        <el-form-item label="确认密码" prop="confirm_password" :required="!adminForm.id">
          <el-input
            v-model="adminForm.confirm_password"
            type="password"
            show-password
            autocomplete="new-password"
            placeholder="请再次输入管理员密码"
          />
        </el-form-item>
        <el-form-item label="管理员角色" prop="roles" :required="!adminForm.id">
          <el-select
            v-model="adminForm.roles"
            multiple
            clearable
            filterable
            class="admin-form__control"
            placeholder="请选择管理员角色"
            :loading="roleLoading"
          >
            <el-option
              v-for="role in roleOptions"
              :key="role.value"
              :label="role.label"
              :value="role.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="管理员头像" prop="avatar" :required="!adminForm.id">
          <Upload v-model="adminForm.avatar" :limit="1" />
        </el-form-item>
        <el-form-item label="管理员状态">
          <el-switch
            v-model="adminForm.status"
            :active-value="1"
            :inactive-value="0"
            active-text="开启"
            inactive-text="关闭"
          />
        </el-form-item>
        <el-form-item label="消息提醒">
          <el-switch
            v-model="adminForm.message_notify_enabled"
            :active-value="1"
            :inactive-value="0"
            active-text="开启"
            inactive-text="关闭"
            @change="handleNotifySwitchChange"
          />
        </el-form-item>
        <el-form-item
          label="提醒手机号"
          prop="message_notify_phone"
          :required="Number(adminForm.message_notify_enabled) === 1"
        >
          <el-input
            v-model="adminForm.message_notify_phone"
            clearable
            :disabled="Number(adminForm.message_notify_enabled) !== 1"
            maxlength="20"
            :placeholder="Number(adminForm.message_notify_enabled) === 1 ? '请输入提醒手机号' : '开启消息提醒后填写'"
          />
        </el-form-item>
        <el-form-item
          label="提醒邮箱"
          prop="message_notify_email"
          :required="Number(adminForm.message_notify_enabled) === 1"
        >
          <el-input
            v-model="adminForm.message_notify_email"
            clearable
            :disabled="Number(adminForm.message_notify_enabled) !== 1"
            maxlength="160"
            :placeholder="Number(adminForm.message_notify_enabled) === 1 ? '请输入提醒邮箱' : '开启消息提醒后填写'"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">保存</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { adminListApi, adminSaveApi, roleSelectApi, setShowApi } from '@/api/system';
import Upload from '@/components/Upload';
import { modalSure } from '@/utils/index';
import { onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';

defineOptions({
  name: 'SystemAdmin'
});

const total = ref(0);
const loading = ref(false);
const formValidate = reactive({
  status: '',
  account: '',
  page: 1, // 当前页
  limit: 20 // 每页显示条数
});
const status = ref('');
const list = ref([]);
const dialogVisible = ref(false);
const dialogTitle = ref('新增管理员');
const submitLoading = ref(false);
const roleLoading = ref(false);
const roleOptions = ref([]);
const adminFormRef = ref(null);

function getDefaultAdminForm() {
  return {
    id: 0,
    real_name: '',
    account: '',
    roles: [],
    avatar: '',
    status: 1,
    message_notify_enabled: 0,
    message_notify_phone: '',
    message_notify_email: '',
    password: '',
    confirm_password: ''
  }
}

const adminForm = ref(getDefaultAdminForm());

const validateNotifyPhone = (rule, value, callback) => {
  const phone = String(value || '').trim()
  const email = String(adminForm.value.message_notify_email || '').trim()
  if (Number(adminForm.value.message_notify_enabled) === 1 && phone === '' && email === '') {
    callback(new Error('请填写提醒手机号或提醒邮箱'))
    return
  }
  if (phone !== '' && !/^1[3-9]\d{9}$/.test(phone)) {
    callback(new Error('请输入正确的手机号'))
    return
  }
  callback()
}

const validateNotifyEmail = (rule, value, callback) => {
  const email = String(value || '').trim()
  const phone = String(adminForm.value.message_notify_phone || '').trim()
  if (Number(adminForm.value.message_notify_enabled) === 1 && phone === '' && email === '') {
    callback(new Error('请填写提醒手机号或提醒邮箱'))
    return
  }
  if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    callback(new Error('请输入正确的邮箱'))
    return
  }
  callback()
}
const validateAddRequired = (field, message) => {
  return (rule, value, callback) => {
    if (adminForm.value.id) {
      callback()
      return
    }

    const fieldValue = field ? adminForm.value[field] : value
    const isEmptyArray = Array.isArray(fieldValue) && fieldValue.length === 0
    const isEmptyString = !Array.isArray(fieldValue) && String(fieldValue || '').trim() === ''
    if (isEmptyArray || isEmptyString) {
      callback(new Error(message))
      return
    }
    callback()
  }
}

const adminRules = {
  account: [{ required: true, message: '请输入管理员账号', trigger: 'blur' }],
  password: [{ validator: validateAddRequired('password', '请输入管理员密码'), trigger: 'blur' }],
  confirm_password: [{ validator: validateAddRequired('confirm_password', '请再次输入管理员密码'), trigger: 'blur' }],
  roles: [{ validator: validateAddRequired('roles', '请选择管理员角色'), trigger: 'change' }],
  avatar: [{ validator: validateAddRequired('avatar', '请上传管理员头像'), trigger: 'change' }],
  message_notify_phone: [{ validator: validateNotifyPhone, trigger: 'blur' }],
  message_notify_email: [{ validator: validateNotifyEmail, trigger: 'blur' }]
};

onMounted(() => {
  getList();
});

function normalizeRoles(roles) {
  if (Array.isArray(roles)) {
    return roles.map(item => Number(item)).filter(item => item)
  }
  if (!roles) {
    return []
  }
  if (typeof roles === 'string') {
    try {
      const parsedRoles = JSON.parse(roles)
      return Array.isArray(parsedRoles) ? parsedRoles.map(item => Number(item)).filter(item => item) : []
    } catch (e) {
      return []
    }
  }
  return []
}

// 修改是否开启
function onchangeIsShow(row) {
  setShowApi(row.id, row.status)
    .then(async(res) => {
      ElMessage.success(res.message);
    })
    .catch((res) => {
      ElMessage.error(res.message);
    });
}

// 请求列表
function submitFail() {
  getList();
}

function currentChange(page) {
  formValidate.page = page
  getList()
}

// 列表
function getList() {
  loading.value = true;
  adminListApi(formValidate)
    .then(async(res) => {
      total.value = res.data.count;
      list.value = res.data.list;
      loading.value = false;
    })
    .catch((res) => {
      loading.value = false;
      ElMessage.error(res.message);
    });
}

function loadRoleOptions() {
  roleLoading.value = true
  roleSelectApi()
    .then(res => {
      const listData = Array.isArray(res.data) ? res.data : []
      roleOptions.value = listData.map(item => ({
        label: item.label,
        value: Number(item.value)
      }))
    })
    .catch(res => {
      roleOptions.value = []
      ElMessage.error(res.message)
    })
    .finally(() => {
      roleLoading.value = false
    })
}

function handleAdd() {
  dialogTitle.value = '新增管理员'
  adminForm.value = getDefaultAdminForm()
  dialogVisible.value = true
  loadRoleOptions()
}

function handleEdit(row) {
  dialogTitle.value = '编辑管理员'
  adminForm.value = {
    id: Number(row.id),
    real_name: row.real_name || '',
    account: row.account || '',
    roles: normalizeRoles(row.roles),
    avatar: row.avatar || '',
    status: Number(row.status) === 0 ? 0 : 1,
    message_notify_enabled: Number(row.message_notify_enabled) === 1 ? 1 : 0,
    message_notify_phone: row.message_notify_phone || '',
    message_notify_email: row.message_notify_email || '',
    password: '',
    confirm_password: ''
  }
  dialogVisible.value = true
  loadRoleOptions()
}

function handleSubmit() {
  adminFormRef.value.validate(valid => {
    if (!valid) return
    if (!adminForm.value.id && !adminForm.value.password) {
      ElMessage.error('请输入密码')
      return
    }
    if (adminForm.value.password && !adminForm.value.confirm_password) {
      ElMessage.error('请输入确认密码')
      return
    }
    if (!adminForm.value.password && adminForm.value.confirm_password) {
      ElMessage.error('请输入密码')
      return
    }
    if (adminForm.value.password && adminForm.value.password !== adminForm.value.confirm_password) {
      ElMessage.error('两次输入的密码不一致')
      return
    }

    const data = {
      real_name: adminForm.value.real_name,
      account: adminForm.value.account,
      roles: adminForm.value.roles.map(item => Number(item)),
      avatar: adminForm.value.avatar,
      status: Number(adminForm.value.status),
      message_notify_enabled: Number(adminForm.value.message_notify_enabled) === 1 ? 1 : 0,
      message_notify_phone: String(adminForm.value.message_notify_phone || '').trim(),
      message_notify_email: String(adminForm.value.message_notify_email || '').trim(),
      password: adminForm.value.password,
      confirm_password: adminForm.value.confirm_password
    }

    submitLoading.value = true
    adminSaveApi(adminForm.value.id, data)
      .then(res => {
        ElMessage.success(res.message)
        dialogVisible.value = false
        getList()
      })
      .catch(res => {
        ElMessage.error(res.message)
      })
      .finally(() => {
        submitLoading.value = false
      })
  })
}

function resetAdminForm() {
  adminForm.value = getDefaultAdminForm()
  if (adminFormRef.value) {
    adminFormRef.value.clearValidate()
  }
}

function handleNotifySwitchChange() {
  if (adminFormRef.value) {
    if (Number(adminForm.value.message_notify_enabled) !== 1) {
      adminForm.value.message_notify_phone = ''
      adminForm.value.message_notify_email = ''
      adminFormRef.value.clearValidate('message_notify_phone')
      adminFormRef.value.clearValidate('message_notify_email')
      return
    }
    adminFormRef.value.validateField('message_notify_phone')
    adminFormRef.value.validateField('message_notify_email')
  }
}

// 删除
function del(row, tit, num) {
  const delfromData = {
    title: tit,
    num: num,
    url: `/setting/admin/delete/${row.id}`,
    method: 'DELETE',
    ids: ''
  };
  modalSure(delfromData)
    .then((res) => {
      ElMessage.success(res.message);
      list.value.splice(num, 1);
    })
    .catch((res) => {
      ElMessage.error(res.message);
    });
}

// 表格搜索
function userSearchs() {
  formValidate.status = status.value === 'all' ? '' : status.value;
  formValidate.page = 1;
  list.value = [];
  getList();
}
</script>

<style scoped lang="scss">
.auth-permissions-curator-page {
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
  max-width: 560px;
}

.auth-permissions-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
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

.auth-permissions-curator-table {
  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.auth-permissions-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next:hover),
  :deep(.btn-prev:hover),
  :deep(.el-pager li:hover) {
    color: var(--ds-primary);
  }
}

.admin-form__control {
  width: 100%;
}

.mt14 {
  margin-top: 14px;
}

a {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--ds-primary-hover);
  }
}
</style>
