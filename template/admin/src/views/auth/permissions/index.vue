<template>
  <div class="permissions-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">管理员权限</h1>
        <p class="page-curator-subtitle">
          管理系统管理员账号，分配角色与操作权限
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        添加管理员
      </el-button>
    </header>
    <div class="permissions-work-surface">
      <div class="search-form-wrap">
        <el-form
          ref="searchFormRef"
          :model="formValidate"
          label-width="80px"
          label-position="right"
          inline
          @submit.prevent
        >
          <el-form-item label="状态：">
            <el-select
              v-model="status"
              placeholder="请选择"
              clearable
              class="form_content_width"
              @change="userSearchs"
            >
              <el-option value="all" label="全部" />
              <el-option value="1" label="开启" />
              <el-option value="0" label="关闭" />
            </el-select>
          </el-form-item>
          <el-form-item label="搜索：">
            <el-input
              v-model="formValidate.account"
              clearable
              placeholder="请输入姓名或者账号"
              class="form_content_width"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" @click="userSearchs">查询</el-button>
            <el-button class="btn-curator-reset" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-table
        v-loading="loading"
        :data="list"
        class="mt14"
        no-user-from-text="暂无数据"
        no-filtered-user-from-text="暂无筛选结果"
        highlight-current-row
      >
        <el-table-column
          label="姓名"
          width="120"
        >
          <template #default="scope">
            <span>{{ scope.row.real_name }}</span>
          </template>
        </el-table-column>
        <el-table-column
          label="账号"
          min-width="130"
        >
          <template #default="scope">
            <span><el-tag v-if="scope.row.pid === 0">超</el-tag><span class="ml10">{{ scope.row.account }}</span></span>
          </template>
        </el-table-column>
        <el-table-column
          label="身份"
          min-width="130"
        >
          <template #default="scope">
            <div v-if="scope.row.role_list && scope.row.role_list.length !== 0">
              <el-tag
                v-for="(item, index) in scope.row.role_list"
                :key="index"
              >
                {{ item }}
              </el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column
          label="绑定用户"
          min-width="180"
        >
          <template #default="scope">
            <div v-if="scope.row.bind_user" class="bind-user-cell">
              <el-avatar :size="28" :src="scope.row.bind_user.avatar" icon="el-icon-user-solid" />
              <span class="bind-user-cell__name">{{ scope.row.bind_user.nickname || scope.row.bind_user.uid }}</span>
            </div>
            <span v-else class="bind-user-cell__empty">未绑定</span>
          </template>
        </el-table-column>
        <el-table-column
          label="消息提醒"
          width="90"
          align="center"
        >
          <template #default="scope">
            <el-tag
              :type="Number(scope.row.message_notify_enabled) === 1 ? 'success' : 'info'"
            >
              {{ Number(scope.row.message_notify_enabled) === 1 ? '开启' : '关闭' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          label="提醒手机号"
          min-width="130"
        >
          <template #default="scope">
            <span>{{ scope.row.message_notify_phone || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column
          label="提醒邮箱"
          min-width="180"
          show-overflow-tooltip
        >
          <template #default="scope">
            <span>{{ scope.row.message_notify_email || '--' }}</span>
          </template>
        </el-table-column>
        <el-table-column
          label="最后一次登录时间"
          min-width="130"
        >
          <template #default="scope">
            <span>{{ scope.row.last_time }}</span>
          </template>
        </el-table-column>
        <el-table-column
          label="最后一次登录ip"
          min-width="130"
        >
          <template #default="scope">
            <span>{{ scope.row.last_ip }}</span>
          </template>
        </el-table-column>
        <el-table-column
          label="开启"
          min-width="70"
        >
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              class="defineSwitch"
              :active-value="1"
              :inactive-value="0"
              :value="scope.row.status"
              size="large"
              active-text="开启"
              inactive-text="关闭"
              @change="onchangeIsShow(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column
          label="操作"
          fixed="right"
          width="100"
        >
          <template #default="scope">
            <a @click="handleEdit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="del(scope.row, '删除管理员', scope.$index)">删除</a>
          </template>
        </el-table-column>
      </el-table>
      <div class="acea-row row-right page">
        <el-pagination
          v-if="total"
          v-model:current-page="formValidate.page"
          :total="total"
          :page-size="formValidate.limit"
          @current-change="currentChange"
        />
      </div>
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
        <el-form-item label="管理员角色" prop="roles">
          <el-select
            v-model="adminForm.roles"
            multiple
            clearable
            filterable
            class="admin-form__control"
            placeholder="请选择管理员角色"
          >
            <el-option
              v-for="role in roleOptions"
              :key="role.value"
              :label="role.label"
              :value="role.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="管理员头像" prop="avatar">
          <Upload v-model="adminForm.avatar" :limit="1" />
        </el-form-item>
        <el-form-item label="绑定用户">
          <AdminUserSelect v-model="adminForm.user_id" />
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
        <el-form-item label="提醒手机号" prop="message_notify_phone">
          <el-input
            v-model="adminForm.message_notify_phone"
            clearable
            :disabled="Number(adminForm.message_notify_enabled) !== 1"
            maxlength="20"
            :placeholder="Number(adminForm.message_notify_enabled) === 1 ? '请输入提醒手机号' : '开启消息提醒后填写'"
          />
        </el-form-item>
        <el-form-item label="提醒邮箱" prop="message_notify_email">
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
        <span>
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">保存</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { adminListApi, adminSaveApi, roleListApi, setShowApi } from '@/api/system'
import Upload from '@/components/Upload'
import AdminUserSelect from '@/components/AdminUserSelect'

defineOptions({
  name: 'SystemAdmin'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const modalSure = proxy.$modalSure
const searchFormRef = ref(null)
const adminFormRef = ref(null)
const total = ref(0)
const loading = ref(false)
const list = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('新增管理员')
const submitLoading = ref(false)
const roleOptions = ref([])
const status = ref('')

const formValidate = reactive({
  status: '',
  account: '',
  page: 1,
  limit: 20
})

const adminForm = reactive(getDefaultAdminForm())

function getDefaultAdminForm() {
  return {
    id: 0,
    real_name: '',
    account: '',
    roles: [],
    avatar: '',
    user_id: 0,
    status: 1,
    message_notify_enabled: 0,
    message_notify_phone: '',
    message_notify_email: '',
    password: '',
    confirm_password: ''
  }
}

const adminRules = computed(() => ({
  account: [{ required: true, message: '请输入管理员账号', trigger: 'blur' }],
  password: [{ validator: validateAddRequired('password', '请输入管理员密码'), trigger: 'blur' }],
  confirm_password: [{ validator: validateAddRequired('confirm_password', '请再次输入管理员密码'), trigger: 'blur' }],
  roles: [{ validator: validateAddRequired('roles', '请选择管理员角色'), trigger: 'change' }],
  avatar: [{ validator: validateAddRequired('avatar', '请上传管理员头像'), trigger: 'change' }],
  message_notify_phone: [{ validator: validateNotifyPhone, trigger: 'blur' }],
  message_notify_email: [{ validator: validateNotifyEmail, trigger: 'blur' }]
}))

function validateNotifyPhone(rule, value, callback) {
  const phone = String(value || '').trim()
  const email = String(adminForm.message_notify_email || '').trim()
  if (Number(adminForm.message_notify_enabled) === 1 && phone === '' && email === '') {
    callback(new Error('请填写提醒手机号或提醒邮箱'))
    return
  }
  if (phone !== '' && !/^1[3-9]\d{9}$/.test(phone)) {
    callback(new Error('请输入正确的手机号'))
    return
  }
  callback()
}

function validateNotifyEmail(rule, value, callback) {
  const email = String(value || '').trim()
  const phone = String(adminForm.message_notify_phone || '').trim()
  if (Number(adminForm.message_notify_enabled) === 1 && phone === '' && email === '') {
    callback(new Error('请填写提醒手机号或提醒邮箱'))
    return
  }
  if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    callback(new Error('请输入正确的邮箱'))
    return
  }
  callback()
}

function validateAddRequired(field, messageText) {
  return (rule, value, callback) => {
    if (adminForm.id) {
      callback()
      return
    }

    const fieldValue = field ? adminForm[field] : value
    const isEmptyArray = Array.isArray(fieldValue) && fieldValue.length === 0
    const isEmptyString = !Array.isArray(fieldValue) && String(fieldValue || '').trim() === ''
    if (isEmptyArray || isEmptyString) {
      callback(new Error(messageText))
      return
    }
    callback()
  }
}

function getList() {
  loading.value = true
  formValidate.roles = formValidate.roles || ''
  adminListApi(formValidate)
    .then(res => {
      total.value = res.data.count || 0
      list.value = res.data.list || []
    })
    .catch(res => {
      message.error(res.message)
    })
    .finally(() => {
      loading.value = false
    })
}

function loadRoleOptions() {
  roleListApi({ page: 1, limit: 100, status: 1 })
    .then(res => {
      const roleList = res.data && res.data.list ? res.data.list : []
      roleOptions.value = roleList.map(item => ({
        label: item.name,
        value: Number(item.id)
      }))
    })
    .catch(res => {
      roleOptions.value = []
      message.error(res.message)
    })
}

function handleAdd() {
  dialogTitle.value = '新增管理员'
  Object.assign(adminForm, getDefaultAdminForm())
  dialogVisible.value = true
  loadRoleOptions()
}

function handleEdit(row) {
  dialogTitle.value = '编辑管理员'
  Object.assign(adminForm, {
    id: Number(row.id),
    real_name: row.real_name || '',
    account: row.account || '',
    roles: Array.isArray(row.roles) ? row.roles.map(item => Number(item)) : [],
    avatar: row.avatar || '',
    user_id: Number(row.user_id || 0),
    status: Number(row.status) === 0 ? 0 : 1,
    message_notify_enabled: Number(row.message_notify_enabled) === 1 ? 1 : 0,
    message_notify_phone: row.message_notify_phone || '',
    message_notify_email: row.message_notify_email || '',
    password: '',
    confirm_password: ''
  })
  dialogVisible.value = true
  loadRoleOptions()
}

function handleSubmit() {
  if (!adminFormRef.value) return
  adminFormRef.value.validate(valid => {
    if (!valid) return
    if (adminForm.password && !adminForm.confirm_password) {
      message.error('请输入确认密码')
      return
    }
    if (!adminForm.password && adminForm.confirm_password) {
      message.error('请输入密码')
      return
    }
    if (adminForm.password !== adminForm.confirm_password) {
      message.error('两次输入的密码不一致')
      return
    }

    const data = {
      real_name: adminForm.real_name,
      account: adminForm.account,
      roles: adminForm.roles.map(item => Number(item)),
      avatar: adminForm.avatar,
      user_id: Number(adminForm.user_id || 0),
      status: Number(adminForm.status),
      message_notify_enabled: Number(adminForm.message_notify_enabled) === 1 ? 1 : 0,
      message_notify_phone: String(adminForm.message_notify_phone || '').trim(),
      message_notify_email: String(adminForm.message_notify_email || '').trim(),
      password: adminForm.password,
      confirm_password: adminForm.confirm_password
    }

    submitLoading.value = true
    adminSaveApi(adminForm.id, data)
      .then(res => {
        message.success(res.message)
        dialogVisible.value = false
        getList()
      })
      .catch(res => {
        message.error(res.message)
      })
      .finally(() => {
        submitLoading.value = false
      })
  })
}

function resetAdminForm() {
  Object.assign(adminForm, getDefaultAdminForm())
  adminFormRef.value && adminFormRef.value.clearValidate()
}

function handleNotifySwitchChange() {
  if (!adminFormRef.value) return
  if (Number(adminForm.message_notify_enabled) !== 1) {
    adminForm.message_notify_phone = ''
    adminForm.message_notify_email = ''
    adminFormRef.value.clearValidate('message_notify_phone')
    adminFormRef.value.clearValidate('message_notify_email')
    return
  }
  adminFormRef.value.validateField('message_notify_phone')
  adminFormRef.value.validateField('message_notify_email')
}

function onchangeIsShow(row) {
  setShowApi(row.id, row.status)
    .then(res => {
      message.success(res.message)
    })
    .catch(res => {
      message.error(res.message)
    })
}

function del(row, tit, num) {
  const delfromData = {
    title: tit,
    num,
    url: `/system/admin/${row.id}`,
    method: 'DELETE',
    ids: ''
  }
  modalSure(delfromData)
    .then(res => {
      message.success(res.message)
      list.value.splice(num, 1)
    })
    .catch(res => {
      message.error(res.message)
    })
}

function currentChange(page) {
  formValidate.page = page
  getList()
}

function userSearchs() {
  formValidate.status = status.value === 'all' ? '' : status.value
  formValidate.page = 1
  list.value = []
  getList()
}

function handleReset() {
  status.value = ''
  Object.assign(formValidate, {
    status: '',
    account: '',
    page: 1,
    limit: 20
  })
  getList()
}

onBeforeMount(() => {
  getList()
})
</script>

<style scoped lang="scss">
.permissions-curator-page {
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
  max-width: 520px;
}

.permissions-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.bind-user-cell {
  display: flex;
  align-items: center;
}

.bind-user-cell__name {
  margin-left: 8px;
  color: var(--ds-on-surface);
}

.bind-user-cell__empty {
  color: var(--ds-on-surface-variant);
}

.search-form-wrap {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--ds-outline-variant);
}

:deep(.btn-curator-primary.el-button--primary) {
  background: var(--ds-primary);
  border-color: var(--ds-primary);
  color: var(--ds-on-primary);

  &:hover, &:focus {
    background: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

:deep(.btn-curator-reset) {
  background: var(--ds-surface-container-highest);
  border-color: var(--ds-outline);
  color: var(--ds-on-surface);

  &:hover, &:focus {
    background: var(--ds-surface-container-high);
    border-color: var(--ds-outline-variant);
  }
}
</style>
