<template>
  <el-drawer
    v-model="localVisible"
    title="用户详情"
    size="860px"
    :wrapper-closable="true"
    @open="handleOpen"
    @close="handleClose"
  >
    <div class="drawer-body">
      <div v-if="localVisible" v-loading="loading">
        <!-- 基础信息头部 -->
        <div class="detail-header">
          <el-avatar :src="user.avatar" :size="60" :icon="UserFilled" />
          <div class="detail-name-wrap">
            <div class="detail-nickname">{{ user.nickname || '未设置昵称' }}</div>
            <div class="detail-id">UID：{{ user.id }}</div>
          </div>
          <el-tag :type="user.status === 1 ? 'success' : 'danger'" class="status-tag">
            {{ user.status === 1 ? '正常' : '已禁用' }}
          </el-tag>
        </div>

        <!-- 三个 tab -->
        <el-tabs v-model="activeTab" class="detail-tabs">
          <!-- 用户详情 tab（分组编辑） -->
          <el-tab-pane label="用户详情" name="info">
            <!-- 资产简要信息行 -->
            <div class="asset-summary">
              <div class="asset-summary__item">
                <span class="asset-summary__label">余额</span>
                <span class="asset-summary__value price-text">¥{{ (user.asset && user.asset.balance) || '0.00' }}</span>
              </div>
              <div class="asset-summary__item">
                <span class="asset-summary__label">积分</span>
                <span class="asset-summary__value points-text">{{ (user.asset && user.asset.points) || 0 }}</span>
              </div>
              <div class="asset-summary__item">
                <span class="asset-summary__label">金币</span>
                <span class="asset-summary__value coins-text">{{ (user.asset && user.asset.coins) || 0 }}</span>
              </div>
              <div class="asset-summary__item">
                <span class="asset-summary__label">佣金</span>
                <span class="asset-summary__value commission-text">¥{{ (user.asset && user.asset.commission) || '0.00' }}</span>
              </div>
            </div>

            <!-- 基本信息（点击编辑，失焦保存） -->
            <div class="info-section">
              <div class="info-section__header">
                <span class="info-section__title">基本信息</span>
              </div>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">昵称</span>
                  <span v-if="editField !== 'nickname'" class="info-value info-value--editable" @click="startEdit('nickname')">{{ user.nickname || '-' }}</span>
                  <el-input v-else ref="nicknameRef" v-model="basicForm.nickname" @blur="handleBasicBlur" @keyup.enter="handleBasicBlur" />
                </div>
                <div class="info-item">
                  <span class="info-label">性别</span>
                  <span v-if="editField !== 'sex'" class="info-value info-value--editable" @click="startEdit('sex')">{{ getSexLabel(user.sex) }}</span>
                  <el-radio-group v-else ref="sexRef" v-model="basicForm.sex" @change="handleBasicBlur">
                    <el-radio :label="0">未知</el-radio>
                    <el-radio :label="1">男</el-radio>
                    <el-radio :label="2">女</el-radio>
                  </el-radio-group>
                </div>
                <div class="info-item">
                  <span class="info-label">生日</span>
                  <span v-if="editField !== 'birthday'" class="info-value info-value--editable" @click="startEdit('birthday')">{{ user.birthday || '-' }}</span>
                  <el-date-picker v-else ref="birthdayRef" v-model="basicForm.birthday" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" @change="handleBasicBlur" />
                </div>
                <div class="info-item">
                  <span class="info-label">年龄</span>
                  <span v-if="editField !== 'age'" class="info-value info-value--editable" @click="startEdit('age')">{{ user.age || '-' }}</span>
                  <el-input-number v-else ref="ageRef" v-model="basicForm.age" :min="0" :max="150" controls-position="right" @blur="handleBasicBlur" />
                </div>
                <div class="info-item">
                  <span class="info-label">分销权限</span>
                  <span v-if="editField !== 'distribution_permission'" class="info-value info-value--editable distribution-status" @click="startEdit('distribution_permission')">
                    <span>{{ getDistributionPermissionLabel(user.distribution_permission) }}</span>
                    <el-tag
                      :type="user.effective_distribution_enabled ? 'success' : 'info'"
                      size="mini"
                    >
                      {{ user.effective_distribution_text || (user.effective_distribution_enabled ? '可分销' : '不可分销') }}
                    </el-tag>
                  </span>
                  <el-select
                    v-else
                    ref="distributionPermissionRef"
                    v-model="distributionForm.distribution_permission"
                    :loading="distributionSaving"

                    class="distribution-select"
                    @change="handleDistributionChange"
                  >
                    <el-option
                      v-for="item in distributionPermissionOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value"
                    />
                  </el-select>
                </div>
                <div class="info-item">
                  <span class="info-label">学历</span>
                  <span v-if="editField !== 'education'" class="info-value info-value--editable" @click="startEdit('education')">{{ user.education || '-' }}</span>
                  <el-input v-else ref="educationRef" v-model="basicForm.education" @blur="handleBasicBlur" @keyup.enter="handleBasicBlur" />
                </div>
                <div class="info-item">
                  <span class="info-label">职业</span>
                  <span v-if="editField !== 'occupation'" class="info-value info-value--editable" @click="startEdit('occupation')">{{ user.occupation || '-' }}</span>
                  <el-input v-else ref="occupationRef" v-model="basicForm.occupation" @blur="handleBasicBlur" @keyup.enter="handleBasicBlur" />
                </div>
                <div class="info-item info-item--full">
                  <span class="info-label">兴趣</span>
                  <span v-if="editField !== 'interests'" class="info-value info-value--editable" @click="startEdit('interests')">{{ user.interests || '-' }}</span>
                  <el-input v-else ref="interestsRef" v-model="basicForm.interests" @blur="handleBasicBlur" @keyup.enter="handleBasicBlur" />
                </div>
                <div class="info-item info-item--full">
                  <span class="info-label">地址</span>
                  <span v-if="editField !== 'address'" class="info-value info-value--editable" @click="startEdit('address')">{{ user.address || '-' }}</span>
                  <el-input v-else ref="addressRef" v-model="basicForm.address" @blur="handleBasicBlur" @keyup.enter="handleBasicBlur" />
                </div>
              </div>
            </div>

            <!-- 账号（手机号）（点击编辑，失焦保存） -->
            <div class="info-section">
              <div class="info-section__header">
                <span class="info-section__title">账号（手机号）</span>
              </div>
              <div class="info-grid">
                <div class="info-item info-item--full">
                  <span class="info-label">手机号</span>
                  <span v-if="editField !== 'phone'" class="info-value info-value--editable" @click="startEdit('phone')">{{ user.phone || '-' }}</span>
                  <el-form v-else ref="phoneFormRef" :model="phoneForm" :rules="phoneRules" class="inline-form">
                    <el-form-item prop="phone" class="inline-form-item">
                      <el-input ref="phoneRef" v-model="phoneForm.phone" placeholder="请输入新手机号" maxlength="11" @blur="handlePhoneBlur" @keyup.enter="handlePhoneBlur" />
                    </el-form-item>
                  </el-form>
                </div>
              </div>
            </div>

            <!-- 密码（点击修改） -->
            <div class="info-section">
              <div class="info-section__header">
                <span class="info-section__title">密码</span>
              </div>
              <div class="info-grid">
                <div class="info-item info-item--full">
                  <span class="info-label">密码</span>
                  <span v-if="editField !== 'password'" class="info-value info-value--editable" @click="startEdit('password')">••••••••</span>
                  <el-form v-else ref="passwordFormRef" :model="passwordForm" :rules="passwordRules" class="inline-form">
                    <div class="inline-form__row">
                      <el-form-item label="新密码" prop="password" class="inline-form-item">
                        <el-input ref="passwordRef" v-model="passwordForm.password" type="password" placeholder="请输入新密码" show-password />
                      </el-form-item>
                      <el-form-item label="确认" prop="confirm_password" class="inline-form-item">
                        <el-input ref="confirmPasswordRef" v-model="passwordForm.confirm_password" type="password" placeholder="请再次输入" show-password @blur="handlePasswordBlur" />
                      </el-form-item>
                    </div>
                  </el-form>
                </div>
              </div>
            </div>

            <!-- 其他信息（只读） -->
            <div class="info-section">
              <div class="info-section__header">
                <span class="info-section__title">其他信息</span>
              </div>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label">注册来源</span>
                  <span class="info-value">
                    <el-tag :type="getSourceTagType(user.register_type)">{{ getSourceLabel(user.register_type) }}</el-tag>
                  </span>
                </div>
                <div class="info-item">
                  <span class="info-label">注册时间</span>
                  <span class="info-value">{{ user.create_time || '-' }}</span>
                </div>
                <div class="info-item">
                  <span class="info-label">最后登录</span>
                  <span class="info-value">{{ user.last_time || '-' }}</span>
                </div>
                <div class="info-item info-item--full">
                  <span class="info-label">推广人</span>
                  <span class="info-value">
                    <template v-if="user.spread_uid">
                      UID：{{ user.spread_uid }}
                      <span v-if="user.spread_nickname">（{{ user.spread_nickname }}）</span>
                    </template>
                    <template v-else>-</template>
                  </span>
                </div>
              </div>
            </div>
          </el-tab-pane>

          <!-- 最近订单 tab -->
          <el-tab-pane label="最近订单" name="orders">
            <template v-if="user.recentOrders && user.recentOrders.length">
              <div v-for="order in user.recentOrders" :key="order.order_id" class="order-item">
                <div class="order-item__no">{{ order.order_id }}</div>
                <div class="order-item__info">
                  <span class="order-price">¥{{ order.total_price }}</span>
                  <el-tag size="mini" class="order-status">{{ order.status_text }}</el-tag>
                  <span class="order-time">{{ order.create_time }}</span>
                </div>
              </div>
            </template>
            <div v-else class="empty-tip">暂无订单记录</div>
          </el-tab-pane>

          <!-- 流水记录 tab -->
          <el-tab-pane label="流水记录" name="logs">
            <div class="log-filter">
              <el-select
                v-model="logSearch.type"
                placeholder="全部类型"
                clearable

                class="log-select"
                @change="handleLogSearch"
              >
                <el-option label="余额" value="balance" />
                <el-option label="积分" value="points" />
                <el-option label="金币" value="coins" />
                <el-option label="经验値" value="exp" />
                <el-option label="佣金" value="commission" />
              </el-select>
              <el-select
                v-model="logSearch.change_type"
                placeholder="变动方向"
                clearable

                class="log-select"
                @change="handleLogSearch"
              >
                <el-option label="增加" value="in" />
                <el-option label="减少" value="out" />
              </el-select>
              <el-button :icon="Refresh" @click="loadLogs">刷新</el-button>
              <el-button type="primary" :icon="Edit" @click="openAdjustDialog">调整资产</el-button>
            </div>
            <el-table v-loading="logLoading" :data="logList">
              <el-table-column label="类型" width="72" align="center">
                <template #default="scope">
                  <el-tag :type="getAssetTagType(scope.row.type)" size="mini">
                    {{ getAssetTypeLabel(scope.row.type) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column label="方向" width="64" align="center">
                <template #default="scope">
                  <span :class="scope.row.change_type === 'in' ? 'text-success' : 'text-danger'">
                    {{ scope.row.change_type === 'in' ? '增加' : '减少' }}
                  </span>
                </template>
              </el-table-column>
              <el-table-column label="变动量" width="100" align="center">
                <template #default="scope">
                  <span :class="scope.row.change_type === 'in' ? 'text-success' : 'text-danger'">
                    {{ scope.row.change_type === 'in' ? '+' : '-' }}{{ scope.row.amount }}
                  </span>
                </template>
              </el-table-column>
              <el-table-column label="变前→变后" width="150" align="center">
                <template #default="scope">
                  <span class="text-gray">{{ scope.row.before }} → {{ scope.row.after }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="remark" label="备注" min-width="120" show-overflow-tooltip />
              <el-table-column prop="create_time" label="时间" width="155" align="center" />
            </el-table>
            <div class="log-pagination">
              <el-pagination
                :current-page="logPage"
                :page-size="logLimit"
                layout="total, prev, pager, next"
                :total="logTotal"
                small
                @current-change="handleLogPageChange"
              />
            </div>
          </el-tab-pane>
        </el-tabs>
      </div>
    </div>

    <!-- 底部操作按钮 -->
    <div class="drawer-footer">
      <el-button @click="localVisible = false">关 闭</el-button>
      <el-button
        type="primary"
        :loading="statusLoading"
        class="status-toggle-btn"
        @click="handleStatusToggle"
      >
        {{ user.status === 1 ? '禁用用户' : '启用用户' }}
      </el-button>
    </div>

    <!-- 调整资产弹窗 -->
    <UserAdjustAssetDialog
      v-model:visible="adjustVisible"
      :user-id="user.id"
      :user-name="user.nickname"
      :append-to-body="true"
      @success="handleAdjustSuccess"
    />
  </el-drawer>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Edit, Refresh, UserFilled } from '@element-plus/icons-vue'
import { setUserStatus, getUserAssetLogs, updateUserBasic, updateUserPhone, updateUserPassword, setUserDistribution } from '@/api/user'
import UserAdjustAssetDialog from './UserAdjustAssetDialog'

defineOptions({ name: 'UserDetailDrawer' })

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  userData: {
    type: Object,
    default: () => ({})
  },
  initTab: {
    type: String,
    default: 'info'
  }
})

const emit = defineEmits(['update:visible', 'close', 'user-updated', 'asset-changed', 'status-changed'])

const localVisible = ref(false)
const loading = ref(false)
const statusLoading = ref(false)
const user = reactive({})
const activeTab = ref('info')
const saving = ref(false)
const editField = ref('')
const distributionSaving = ref(false)
const distributionPermissionOptions = [
  { label: '跟随模式', value: -1 },
  { label: '禁止分销', value: 0 },
  { label: '指定允许', value: 1 }
]
const basicForm = reactive(createBasicForm())
const distributionForm = reactive({ distribution_permission: -1 })
const phoneForm = reactive({ phone: '' })
const passwordForm = reactive({ password: '', confirm_password: '' })
const logLoading = ref(false)
const logList = ref([])
const logTotal = ref(0)
const logPage = ref(1)
const logLimit = ref(15)
const logSearch = reactive({ type: '', change_type: '' })
const adjustVisible = ref(false)

const phoneFormRef = ref(null)
const passwordFormRef = ref(null)
const nicknameRef = ref(null)
const sexRef = ref(null)
const birthdayRef = ref(null)
const ageRef = ref(null)
const educationRef = ref(null)
const occupationRef = ref(null)
const interestsRef = ref(null)
const addressRef = ref(null)
const phoneRef = ref(null)
const passwordRef = ref(null)
const distributionPermissionRef = ref(null)

const phoneRules = {
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1\d{10}$/, message: '手机号格式不正确', trigger: 'blur' }
  ]
}
const passwordRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码至少 6 位', trigger: 'blur' }
  ],
  confirm_password: [
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        if (value !== passwordForm.password) {
          callback(new Error('两次输入的密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

watch(() => props.visible, val => {
  localVisible.value = val
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

watch(() => props.userData, val => {
  if (val) {
    setUser(val)
  }
})

watch(activeTab, val => {
  if (val === 'logs' && logList.value.length === 0) {
    loadLogs()
  }
})

function createBasicForm() {
  return {
    nickname: '',
    sex: 0,
    birthday: '',
    age: 0,
    education: '',
    occupation: '',
    interests: '',
    hobbies: '',
    address: ''
  }
}

function resetReactiveObject(target, data) {
  Object.keys(target).forEach(key => {
    delete target[key]
  })
  Object.assign(target, data)
}

function setUser(data = {}) {
  resetReactiveObject(user, { ...data, recentOrders: data.recentOrders || [] })
}

function patchUser(data) {
  Object.assign(user, data)
}

function setSaving(value) {
  saving.value = value
}

function setDistributionSaving(value) {
  distributionSaving.value = value
}

function clearEditField(savedField) {
  if (editField.value === savedField) editField.value = ''
}

function handleOpen() {
  setUser(props.userData)
  activeTab.value = props.initTab || 'info'
  logList.value = []
  logTotal.value = 0
  logPage.value = 1
  Object.assign(logSearch, { type: '', change_type: '' })
  initBasicForm()
}

function initBasicForm() {
  editField.value = ''
  Object.assign(basicForm, {
    nickname: user.nickname || '',
    sex: user.sex || 0,
    birthday: user.birthday || '',
    age: user.age || 0,
    education: user.education || '',
    occupation: user.occupation || '',
    interests: user.interests || '',
    hobbies: user.hobbies || '',
    address: user.address || ''
  })
  distributionForm.distribution_permission = normalizeDistributionPermission(user.distribution_permission)
  phoneForm.phone = user.phone || ''
  Object.assign(passwordForm, { password: '', confirm_password: '' })
}

function handleClose() {
  resetReactiveObject(user, {})
  emit('close')
}

function startEdit(field) {
  const textFields = ['nickname', 'education', 'occupation', 'interests', 'hobbies', 'address', 'birthday']
  if (textFields.includes(field)) {
    basicForm[field] = user[field] || ''
  } else if (field === 'sex') {
    basicForm.sex = user.sex || 0
  } else if (field === 'age') {
    basicForm.age = user.age || 0
  } else if (field === 'phone') {
    phoneForm.phone = user.phone || ''
    phoneFormRef.value?.clearValidate()
  } else if (field === 'password') {
    Object.assign(passwordForm, { password: '', confirm_password: '' })
    passwordFormRef.value?.clearValidate()
  } else if (field === 'distribution_permission') {
    distributionForm.distribution_permission = normalizeDistributionPermission(user.distribution_permission)
  }
  editField.value = field
  nextTick(() => {
    const refMap = {
      nickname: nicknameRef,
      sex: sexRef,
      birthday: birthdayRef,
      age: ageRef,
      education: educationRef,
      occupation: occupationRef,
      interests: interestsRef,
      address: addressRef,
      phone: phoneRef,
      password: passwordRef,
      distribution_permission: distributionPermissionRef
    }
    const targetRef = refMap[field]
    const el = targetRef && targetRef.value
    if (el && typeof el.focus === 'function') el.focus()
  })
}

async function handleBasicBlur() {
  if (saving.value || !editField.value) return
  const savedField = editField.value
  const fieldValue = basicForm[savedField]
  const userId = user.id
  setSaving(true)
  try {
    await updateUserBasic(userId, { [savedField]: fieldValue })
    patchUser({ [savedField]: fieldValue })
    emit('user-updated', { id: userId, [savedField]: fieldValue })
  } catch (e) {
    // 错误已在请求拦截器处理
  } finally {
    setSaving(false)
    clearEditField(savedField)
  }
}

async function handlePhoneBlur() {
  if (saving.value || editField.value !== 'phone') return
  const valid = await phoneFormRef.value?.validate().catch(() => false)
  if (!valid) return
  const savedField = editField.value
  const userId = user.id
  const phone = phoneForm.phone
  setSaving(true)
  try {
    await updateUserPhone(userId, { phone })
    patchUser({ phone })
    emit('user-updated', { id: userId, phone })
  } catch (e) {
    // 错误已在请求拦截器处理
  } finally {
    setSaving(false)
    clearEditField(savedField)
  }
}

async function handlePasswordBlur() {
  if (saving.value || editField.value !== 'password') return
  const valid = await passwordFormRef.value?.validate().catch(() => false)
  if (!valid) return
  const savedField = editField.value
  const userId = user.id
  const password = passwordForm.password
  const confirmPassword = passwordForm.confirm_password
  setSaving(true)
  try {
    await updateUserPassword(userId, {
      password,
      confirm_password: confirmPassword
    })
    ElMessage.success('密码修改成功')
    Object.assign(passwordForm, { password: '', confirm_password: '' })
  } catch (e) {
    // 错误已在请求拦截器处理
  } finally {
    setSaving(false)
    clearEditField(savedField)
  }
}

async function handleDistributionChange(value) {
  if (distributionSaving.value || editField.value !== 'distribution_permission') return
  const permission = normalizeDistributionPermission(value)
  const savedField = editField.value
  const userId = user.id
  setDistributionSaving(true)
  try {
    await setUserDistribution({
      user_id: userId,
      distribution_permission: permission
    })
    ElMessage.success('分销权限设置成功')
    const effectiveDistributionEnabled = resolveEffectiveDistributionEnabled(permission)
    patchUser({
      distribution_permission: permission,
      is_distribution: permission === 1 ? 1 : 0,
      effective_distribution_enabled: effectiveDistributionEnabled,
      effective_distribution_text: effectiveDistributionEnabled ? '可分销' : '不可分销'
    })
    emit('user-updated', {
      id: userId,
      distribution_permission: permission,
      is_distribution: permission === 1 ? 1 : 0,
      effective_distribution_enabled: effectiveDistributionEnabled,
      effective_distribution_text: effectiveDistributionEnabled ? '可分销' : '不可分销'
    })
  } catch (e) {
    distributionForm.distribution_permission = normalizeDistributionPermission(user.distribution_permission)
  } finally {
    setDistributionSaving(false)
    clearEditField(savedField)
  }
}

function loadLogs() {
  if (!user.id) return
  logLoading.value = true
  const params = {
    page: logPage.value,
    limit: logLimit.value,
    ...logSearch
  }
  getUserAssetLogs(user.id, params).then(res => {
    const data = res.data || {}
    logList.value = Array.isArray(data.list) ? data.list : []
    logTotal.value = data.total || 0
  }).catch(() => {
    logList.value = []
    logTotal.value = 0
  }).finally(() => {
    logLoading.value = false
  })
}

function handleLogSearch() {
  logPage.value = 1
  loadLogs()
}

function handleLogPageChange(val) {
  logPage.value = val
  loadLogs()
}

function openAdjustDialog() {
  adjustVisible.value = true
}

function handleAdjustSuccess() {
  logPage.value = 1
  loadLogs()
  emit('asset-changed', user.id)
}

function getSexLabel(sex) {
  const map = { 0: '未知', 1: '男', 2: '女' }
  return sex !== undefined && sex !== null ? (map[sex] || '-') : '-'
}

function getSourceLabel(type) {
  const map = { wechat: '微信', phone: '手机号', other: '其他', h5: 'H5', mini: '小程序' }
  return map[type] || type || '-'
}

function getSourceTagType(type) {
  const map = { wechat: 'success', phone: 'primary', mini: 'warning', h5: 'info' }
  return map[type] || ''
}

function getAssetTypeLabel(type) {
  const map = { balance: '余额', points: '积分', coins: '金币', exp: '经验', commission: '佣金' }
  return map[type] || type || '-'
}

function getAssetTagType(type) {
  const map = { balance: 'primary', points: 'warning', coins: 'danger', exp: '', commission: 'success' }
  return map[type] || ''
}

function normalizeDistributionPermission(permission) {
  const value = Number(permission === undefined || permission === null ? -1 : permission)
  return [-1, 0, 1].includes(value) ? value : -1
}

function getDistributionPermissionLabel(permission) {
  const value = normalizeDistributionPermission(permission)
  const option = distributionPermissionOptions.find(item => item.value === value)
  return option ? option.label : '跟随模式'
}

function resolveEffectiveDistributionEnabled(permission) {
  if (!user.distribution_enabled || Number(user.status) !== 1) {
    return false
  }

  if (permission === 0) {
    return false
  }

  if (user.distribution_mode === 'all') {
    return true
  }

  return permission === 1
}

function handleStatusToggle() {
  statusLoading.value = true
  setUserStatus(user.id, user.status === 1 ? 0 : 1).then(res => {
    ElMessage.success(res.message || '状态更新成功')
    user.status = user.status === 1 ? 0 : 1
    emit('status-changed', { id: user.id, status: user.status })
  }).finally(() => {
    statusLoading.value = false
  })
}
</script>

<style lang="scss" scoped>
.drawer-body {
  padding: 0 20px;
  height: calc(100% - 60px);
  overflow-y: auto;
}

.drawer-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 20px;
  border-top: 1px solid #ebeef5;
  background: #fff;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.status-toggle-btn {
  min-width: 86px;
}

.detail-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 4px 0 16px;

  .detail-name-wrap {
    flex: 1;

    .detail-nickname {
      font-size: 17px;
      font-weight: 600;
      color: #303133;
    }

    .detail-id {
      font-size: 13px;
      color: #909399;
      margin-top: 4px;
    }
  }

  .status-tag {
    flex-shrink: 0;
  }
}

.asset-summary {
  display: flex;
  gap: 16px;
  padding: 12px 16px;
  margin-bottom: 16px;
  border-radius: 8px;
  background: #fafafa;

  &__item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
  }

  &__label {
    font-size: 12px;
    color: #909399;
  }

  &__value {
    font-size: 18px;
    font-weight: 600;
  }
}

.info-section {
  margin-bottom: 16px;
  border-radius: 6px;
  padding: 12px 16px;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
  }

  &__title {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
  }
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;

  .info-item {
    display: flex;
    align-items: center;
    padding: 7px 0;

    &--full {
      grid-column: span 2;
      .inline-form__row {
        display: flex;
        gap: 12px;
        align-items: flex-start;
      }
    }
  }

  .info-label {
    width: 70px;
    flex-shrink: 0;
    font-size: 13px;
    color: #909399;
  }

  .info-value {
    font-size: 13px;
    color: #303133;

    &--editable {
      flex: 1;
      min-width: 0;
      min-height: 28px;
      display: inline-flex;
      align-items: center;
      box-sizing: border-box;
      cursor: pointer;
      border-radius: 4px;
      padding: 4px 8px;
      margin-left: -8px;
      transition: background-color 0.2s, color 0.2s;

      &:hover {
        background: #f5f6f8;
      }
    }
  }
}

.inline-form {
  flex: 1;

  .inline-form-item {
    margin-bottom: 0;
  }

  :deep(.el-form-item__error) {
    position: static;
    padding-top: 2px;
  }
}

.detail-tabs {
  margin-top: 0;

  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }
}

.log-filter {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0 12px;
  flex-wrap: wrap;
}

.log-select {
  width: 110px;
}

.distribution-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.distribution-select {
  width: 132px;
}

.log-pagination {
  margin-top: 12px;
  display: flex;
  justify-content: flex-end;
}

.order-item {
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;

  &:last-child {
    border-bottom: none;
  }

  &__no {
    font-size: 13px;
    color: #303133;
    margin-bottom: 6px;
    font-family: monospace;
  }

  &__info {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .order-price {
    color: #f56c6c;
    font-weight: 600;
    font-size: 14px;
  }

  .order-status {
    flex-shrink: 0;
  }

  .order-time {
    font-size: 12px;
    color: #909399;
    margin-left: auto;
  }
}

.price-text { color: #f56c6c; font-weight: 600; }
.points-text { color: #e6a23c; font-weight: 600; }
.coins-text { color: #f56c6c; font-weight: 600; }
.commission-text { color: #67c23a; font-weight: 600; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-gray { color: #909399; font-size: 12px; }

.empty-tip {
  text-align: center;
  color: #909399;
  font-size: 13px;
  padding: 20px 0;
}
</style>
