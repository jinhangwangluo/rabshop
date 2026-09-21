<template>
  <div class="user-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">用户管理</h1>
        <p class="page-curator-subtitle">
          管理平台用户，支持搜索筛选、资产调整、状态控制及详情查看。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        :icon="Plus"
        @click="handleAdd"
      >
        新增用户
      </el-button>
    </header>

    <div class="panel panel--search">
      <!-- 搜索区域 -->
      <el-form
        ref="searchFormRef"
        :model="searchForm"
        inline
        class="search-form user-search-form"
        @submit.prevent
      >
        <!-- 合并文本搜索：字段选择器 + 单输入框 -->
        <el-form-item>
          <el-input
            v-model="searchKeyword"
            placeholder="请输入搜索内容"
            clearable
            class="combo-input"
            @keyup.enter="handleSearch"
            @clear="handleSearch"
          >
            <template #prepend>
              <el-select

                v-model="searchField"
                class="combo-select"
              >
                <el-option label="昵称" value="nickname" />
                <el-option label="手机号" value="phone" />
              </el-select>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="注册来源">
          <el-select v-model="searchForm.register_type" placeholder="全部" clearable class="search-select">
            <el-option label="微信" value="wechat" />
            <el-option label="手机号" value="phone" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="用户状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="分销权限">
          <el-select v-model="searchForm.distribution_permission" placeholder="全部" clearable class="search-select">
            <el-option
              v-for="item in distributionPermissionOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="推广人">
          <el-select
            v-model="searchForm.spread_uid"
            filterable
            remote
            clearable
            :remote-method="searchSpreadUser"
            :loading="spreadSearchLoading"
            placeholder="输入昵称/手机号搜索"
            class="search-select-spread"
            @clear="handleSearch"
          >
            <el-option
              v-for="u in spreadUserOptions"
              :key="u.id"
              :label="u.nickname || ('UID:' + u.id)"
              :value="u.id"
            >
              <span>{{ u.nickname || '-' }}</span>
              <span class="spread-phone">{{ u.phone || '' }}</span>
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="注册时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 240px;"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 数据统计 -->
    <el-row :gutter="16" class="mb12">
      <el-col :span="6">
        <div class="stat-badge stat-badge--green">
          <span class="stat-badge__label">总用户数</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.total || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--up">全部</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--gold">
          <span class="stat-badge__label">今日新增</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.today || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--warn">今日</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--purple">
          <span class="stat-badge__label">本月新增</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.month || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--tag">本月</span>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-badge stat-badge--red">
          <span class="stat-badge__label">禁用用户</span>
          <div class="stat-badge__body">
            <span class="stat-badge__value">{{ stats.disabled || 0 }}</span>
            <span class="stat-badge__sub stat-badge__sub--down">禁用</span>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 表格区域 -->
    <div class="panel panel--table">
      <el-table v-loading="loading" :data="userList" row-key="uid" class="user-curator-table">
        <el-table-column prop="uid" label="UID" width="80" align="center" />
        <el-table-column label="头像" width="70" align="center">
          <template #default="scope">
            <el-avatar
              :src="scope.row.avatar"
              :size="38"
              class="user-table-avatar"
              :icon="UserFilled"
            />
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="昵称" min-width="100" show-overflow-tooltip />
        <el-table-column prop="phone" label="手机号" width="130" align="center">
          <template #default="scope">
            <span>{{ scope.row.phone || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="注册来源" width="100" align="center">
          <template #default="scope">
            <el-tag :type="getSourceTagType(scope.row.register_type)">
              {{ getSourceLabel(scope.row.register_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="分销权限" width="160" align="center">
          <template #default="scope">
            <div class="distribution-permission-cell">
              <span class="distribution-permission-cell__text">
                {{ getDistributionPermissionLabel(scope.row.distribution_permission) }}
              </span>
              <el-tag
                :type="scope.row.effective_distribution_enabled ? 'success' : 'info'"
                size="mini"
              >
                {{ scope.row.effective_distribution_text || (scope.row.effective_distribution_enabled ? '可分销' : '不可分销') }}
              </el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="余额" width="100" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ (scope.row.asset && scope.row.asset.balance) || '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="积分" width="90" align="center">
          <template #default="scope">{{ (scope.row.asset && scope.row.asset.points) || 0 }}</template>
        </el-table-column>
        <el-table-column label="金币" width="90" align="center">
          <template #default="scope">{{ (scope.row.asset && scope.row.asset.coins) || 0 }}</template>
        </el-table-column>
        <el-table-column prop="create_time" label="注册时间" width="160" align="center" />
        <el-table-column prop="last_time" label="最后登录" width="160" align="center">
          <template #default="scope">
            <span>{{ scope.row.last_time || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="270" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleDetail(scope.row)">详情</a>
            <el-divider direction="vertical" />
            <a @click="handleAdjustAsset(scope.row)">调整资产</a>
            <el-divider direction="vertical" />
            <a @click="handleViewSubs(scope.row)">推广人</a>
            <el-divider direction="vertical" />
            <a
              :class="scope.row.is_comment_ban === 1 ? 'text-danger' : ''"
              @click="handleCommentBan(scope.row)"
            >{{ scope.row.is_comment_ban === 1 ? '取消禁评' : '禁评' }}</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[15, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 用户详情抽屉 -->
    <UserDetailDrawer
      ref="userDetailDrawer"
      v-model:visible="detailVisible"
      :user-data="currentUser"
      :init-tab="detailInitTab"
      @user-updated="handleDetailUserUpdated"
      @status-changed="handleDetailStatusChanged"
      @close="currentUser = null"
    />

    <!-- 下级推广人抽屉 -->
    <UserSubDrawer
      v-model:visible="subDrawerVisible"
      :user-id="subDrawerUserId"
      :user-name="subDrawerUserName"
    />

    <!-- 新增用户弹窗 -->
    <el-dialog
      v-model="addDialogVisible"
      title="新增用户"
      width="700px"
      :close-on-click-modal="false"
      @close="handleAddDialogClose"
    >
      <el-form
        ref="addFormRef"
        :model="addForm"
        :rules="addRules"
        label-width="100px"
      >
        <!-- 基础信息 -->
        <div class="form-section-title">基础信息</div>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="头像">
              <Upload v-model="addForm.avatar" :limit="1" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="昵称" prop="nickname">
              <el-input v-model="addForm.nickname" placeholder="请输入昵称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="手机号" prop="phone">
              <el-input v-model="addForm.phone" placeholder="请输入手机号" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="密码" prop="password">
              <el-input v-model="addForm.password" type="password" placeholder="请输入密码" show-password />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="确认密码" prop="confirm_password">
              <el-input v-model="addForm.confirm_password" type="password" placeholder="请再次输入密码" show-password />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="性别">
              <el-radio-group v-model="addForm.sex">
                <el-radio :label="0">保密</el-radio>
                <el-radio :label="1">男</el-radio>
                <el-radio :label="2">女</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态">
              <el-radio-group v-model="addForm.status">
                <el-radio :label="1">正常</el-radio>
                <el-radio :label="0">禁用</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="备注">
          <el-input v-model="addForm.remarks" type="textarea" :rows="2" placeholder="请输入备注" />
        </el-form-item>

        <!-- 画像信息 -->
        <div class="form-section-title">画像信息</div>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="生日">
              <el-date-picker
                v-model="addForm.birthday"
                type="date"
                placeholder="选择日期"
                value-format="YYYY-MM-DD"
                style="width:100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="年龄">
              <el-input-number v-model="addForm.age" :min="0" :max="150" controls-position="right" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="学历">
              <el-select v-model="addForm.education" placeholder="请选择" clearable style="width:100%">
                <el-option label="小学" value="小学" />
                <el-option label="初中" value="初中" />
                <el-option label="高中/中专" value="高中/中专" />
                <el-option label="大专" value="大专" />
                <el-option label="本科" value="本科" />
                <el-option label="硕士" value="硕士" />
                <el-option label="博士及以上" value="博士及以上" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="职业">
              <el-input v-model="addForm.occupation" placeholder="请输入职业" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="兴趣">
              <el-input v-model="addForm.interests" placeholder="请输入兴趣" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="爱好">
              <el-input v-model="addForm.hobbies" placeholder="请输入爱好" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="地址">
          <el-input v-model="addForm.address" placeholder="请输入地址" />
        </el-form-item>

        <!-- 分销信息 -->
        <div class="form-section-title">分销信息</div>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="分销权限">
              <el-select v-model="addForm.distribution_permission" style="width:100%">
                <el-option
                  v-for="item in distributionPermissionOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="上级推广人">
              <el-select
                v-model="addForm.spread_uid"
                filterable
                remote
                clearable
                :remote-method="searchAddSpreadUser"
                :loading="addSpreadLoading"
                placeholder="输入昵称/手机号搜索"
                style="width:100%"
              >
                <el-option
                  v-for="u in addSpreadOptions"
                  :key="u.id"
                  :label="u.nickname || ('UID:' + u.id)"
                  :value="u.id"
                >
                  <span>{{ u.nickname || '-' }}</span>
                  <span class="spread-phone">{{ u.phone || '' }}</span>
                </el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="addDialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="addSubmitLoading" @click="handleAddSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 调整资产弹窗 -->
    <UserAdjustAssetDialog
      v-model:visible="adjustVisible"
      :user-id="adjustUserId"
      :user-name="adjustUserName"
      @success="handleAdjustSuccess"
    />
  </div>
</template>

<script setup>
import { nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search, UserFilled } from '@element-plus/icons-vue'
import { getUserDetail, getUserListApi, setUserStatus, createUser, searchUsers } from '@/api/user'
import { setCommentBan } from '@/api/comment'
import UserSubDrawer from './components/UserSubDrawer'
import UserDetailDrawer from './components/UserDetailDrawer'
import UserAdjustAssetDialog from './components/UserAdjustAssetDialog'

defineOptions({ name: 'UserList' })

const route = useRoute()
const searchFormRef = ref(null)
const addFormRef = ref(null)

const loading = ref(false)
const userList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const dateRange = ref([])
const stats = reactive({})
const distributionPermissionOptions = [
  { label: '跟随模式', value: -1 },
  { label: '禁止分销', value: 0 },
  { label: '指定允许', value: 1 }
]

const searchField = ref('nickname')
const searchKeyword = ref('')
const spreadSearchLoading = ref(false)
const spreadUserOptions = ref([])
const searchForm = reactive(defaultSearchForm())

const detailVisible = ref(false)
const detailInitTab = ref('orders')
const currentUser = ref(null)
const routeDetailId = ref('')

const subDrawerVisible = ref(false)
const subDrawerUserId = ref(0)
const subDrawerUserName = ref('')

const adjustVisible = ref(false)
const adjustUserId = ref(0)
const adjustUserName = ref('')

const addDialogVisible = ref(false)
const addSubmitLoading = ref(false)
const addSpreadLoading = ref(false)
const addSpreadOptions = ref([])
const addForm = reactive(defaultAddForm())
const addRules = {
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }
  ],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }, { min: 6, message: '密码至少6位', trigger: 'blur' }],
  confirm_password: [
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    {
      validator: (rule, value, callback) => {
        if (value !== addForm.password) {
          callback(new Error('两次密码不一致'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

function defaultSearchForm() {
  return {
    register_type: '',
    status: '',
    distribution_permission: '',
    spread_uid: '',
    start_time: '',
    end_time: ''
  }
}

function defaultAddForm() {
  return {
    avatar: '',
    nickname: '',
    phone: '',
    password: '',
    confirm_password: '',
    sex: 0,
    status: 1,
    remarks: '',
    birthday: '',
    age: 0,
    education: '',
    occupation: '',
    interests: '',
    hobbies: '',
    address: '',
    is_distribution: 0,
    distribution_permission: -1,
    spread_uid: ''
  }
}

function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  if (searchKeyword.value) {
    params[searchField.value] = searchKeyword.value
  }
  getUserListApi(params).then(res => {
    userList.value = res.data.list || []
    total.value = res.data.total || 0
    if (res.data.stats) {
      Object.assign(stats, res.data.stats)
    }
    tryOpenRouteDetail(userList.value)
  }).finally(() => {
    loading.value = false
  })
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    routeDetailId.value = ''
    return
  }

  if (value === routeDetailId.value) {
    return
  }

  routeDetailId.value = value
  openRouteUserDetail(value)
}

function openRouteUserDetail(id) {
  getUserDetail(id).then(res => {
    const data = res.data || {}
    currentUser.value = {
      ...data,
      id: Number(data.uid || data.id || id),
      recentOrders: Array.isArray(data.recentOrders) ? data.recentOrders : []
    }
    detailInitTab.value = 'info'
    detailVisible.value = false
    nextTick(() => {
      detailVisible.value = true
    })
  }).catch(() => {
    ElMessage.error('获取用户详情失败')
  })
}

function tryOpenRouteDetail(list) {
  if (!routeDetailId.value || detailVisible.value) {
    return
  }

  const target = Array.isArray(list)
    ? list.find(item => String(item.uid || item.id) === String(routeDetailId.value))
    : null
  if (!target) {
    return
  }

  currentUser.value = { ...target, id: target.uid, recentOrders: target.recentOrders || [] }
  detailInitTab.value = 'info'
  detailVisible.value = true
}

function handleDateChange(val) {
  if (val && val.length === 2) {
    searchForm.start_time = val[0]
    searchForm.end_time = val[1]
  } else {
    searchForm.start_time = ''
    searchForm.end_time = ''
  }
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  searchField.value = 'nickname'
  searchKeyword.value = ''
  spreadUserOptions.value = []
  Object.assign(searchForm, defaultSearchForm())
  dateRange.value = []
  page.value = 1
  getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getList()
}

function handleCurrentChange(val) {
  page.value = val
  getList()
}

function searchSpreadUser(query) {
  if (!query) return
  spreadSearchLoading.value = true
  searchUsers({ nickname: query, limit: 20 }).then(res => {
    spreadUserOptions.value = Array.isArray(res.data.list) ? res.data.list : (Array.isArray(res.data) ? res.data : [])
  }).finally(() => {
    spreadSearchLoading.value = false
  })
}

function handleStatusChange(row) {
  setUserStatus(row.uid, row.status).then(res => {
    ElMessage.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleDetail(row) {
  currentUser.value = { ...row, id: row.uid, recentOrders: [] }
  detailInitTab.value = 'info'
  detailVisible.value = true
}

function handleDetailStatusChanged({ id, status }) {
  const item = userList.value.find(u => Number(u.uid || u.id) === Number(id))
  if (item) item.status = status
}

function handleDetailUserUpdated(payload) {
  const item = userList.value.find(u => Number(u.uid || u.id) === Number(payload.id))
  if (item) {
    Object.keys(payload).forEach(key => {
      if (key !== 'id') item[key] = payload[key]
    })
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'distribution_permission')) {
    getList()
  }
}

function handleAdjustAsset(row) {
  adjustUserId.value = row.uid
  adjustUserName.value = row.nickname || ('UID:' + row.uid)
  adjustVisible.value = true
}

function handleAdjustSuccess() {
  getList()
}

function handleViewSubs(row) {
  subDrawerUserId.value = row.uid
  subDrawerUserName.value = row.nickname || `UID:${row.uid}`
  subDrawerVisible.value = true
}

function getSourceLabel(type) {
  const map = { wechat: '微信', phone: '手机号', other: '其他', h5: 'H5', mini: '小程序' }
  return map[type] || type || '-'
}

function getSourceTagType(type) {
  const map = { wechat: 'success', phone: 'primary', mini: 'warning', h5: 'info' }
  return map[type] || ''
}

function getDistributionPermissionLabel(permission) {
  const value = Number(permission === undefined || permission === null ? -1 : permission)
  const option = distributionPermissionOptions.find(item => item.value === value)
  return option ? option.label : '跟随模式'
}

function resetAddForm() {
  Object.assign(addForm, defaultAddForm())
}

function handleAdd() {
  resetAddForm()
  addSpreadOptions.value = []
  addDialogVisible.value = true
}

function handleAddDialogClose() {
  addFormRef.value?.resetFields()
}

function searchAddSpreadUser(query) {
  if (!query) return
  addSpreadLoading.value = true
  searchUsers({ nickname: query, limit: 20 }).then(res => {
    addSpreadOptions.value = Array.isArray(res.data.list) ? res.data.list : (Array.isArray(res.data) ? res.data : [])
  }).finally(() => {
    addSpreadLoading.value = false
  })
}

function handleAddSubmit() {
  addFormRef.value?.validate(valid => {
    if (!valid) return
    addSubmitLoading.value = true
    const data = { ...addForm }
    data.is_distribution = Number(data.distribution_permission) === 1 ? 1 : 0
    if (!data.spread_uid) delete data.spread_uid
    if (!data.age) delete data.age
    createUser(data).then(() => {
      ElMessage.success('新增用户成功')
      addDialogVisible.value = false
      page.value = 1
      getList()
    }).finally(() => {
      addSubmitLoading.value = false
    })
  })
}

function handleCommentBan(row) {
  const isBan = row.is_comment_ban !== 1
  const actionText = isBan ? '禁止该用户发表评论' : '取消该用户的禁评限制'
  ElMessageBox.confirm(`确定要${actionText}吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    setCommentBan(row.uid, isBan ? 1 : 0).then(() => {
      ElMessage.success(isBan ? '已设置禁评' : '已取消禁评')
      row.is_comment_ban = isBan ? 1 : 0
    })
  }).catch(() => {})
}

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

onBeforeMount(() => {
  applyRouteDetailQuery(route.query.detail_id)
  getList()
})
</script>

<style lang="scss" scoped>
/* material/stitch/DESIGN.md */
.user-curator-page {
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
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 620px;
}

.panel {
  background: var(--curator-surface-card);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 12px;

  &--search {
    padding: 20px 24px 4px;
  }

  &--table {
    padding: 16px 24px;
  }
}

.mb12 {
  margin-bottom: 12px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--curator-surface-card);
  color: var(--curator-primary);
  border: 1px solid var(--curator-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--curator-primary-container);
    border-color: var(--curator-primary-container);
  }
}

.search-form {
  margin-bottom: 8px;

  :deep(.el-form-item) {
    margin-bottom: 16px;
    margin-right: 16px;
  }
}

.search-select { width: 120px; }
.search-select-sm { width: 120px; }
.search-select-spread { width: 200px; }

.distribution-permission-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;

  &__text {
    color: #606266;
    font-size: 13px;
    line-height: 20px;
  }
}

.combo-input {
  width: 300px;

  :deep(.el-input-group__prepend) {
    padding: 0;
    background: var(--curator-surface-card);

    .el-select {
      width: 90px;
      margin: 0;

      .el-input__inner {
        border: none;
        padding-right: 24px;
      }

      .el-input__suffix {
        right: 4px;
      }
    }
  }
}

.spread-phone {
  margin-left: 10px;
  color: var(--curator-on-surface-variant);
  font-size: 12px;
}

.stat-badge {
  position: relative;
  background: var(--curator-surface-card);
  border: 1px solid var(--curator-outline);
  border-radius: 8px;
  padding: 18px 20px 16px 24px;
  min-height: 80px;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    left: 0;
    top: 12px;
    bottom: 12px;
    width: 4px;
    border-radius: 0 4px 4px 0;
  }

  &--green::before  { background: linear-gradient(180deg, #43d79a 0%, #1db87b 100%); }
  &--gold::before   { background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%); }
  &--purple::before { background: linear-gradient(180deg, #a78bfa 0%, #7c3aed 100%); }
  &--red::before    { background: linear-gradient(180deg, #fc8181 0%, #e53e3e 100%); }
}

.stat-badge__label {
  display: block;
  font-size: 13px;
  color: var(--curator-on-surface-variant);
  margin-bottom: 10px;
  line-height: 1;
}

.stat-badge__body {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.stat-badge__value {
  font-size: 26px;
  font-weight: 700;
  color: var(--curator-on-surface);
  line-height: 1;
  letter-spacing: -0.5px;
}

.stat-badge__sub {
  font-size: 13px;
  font-weight: 500;
  line-height: 1;

  &--up   { color: #1db87b; }
  &--down { color: #f56c6c; }
  &--tag  { color: #7c3aed; }
  &--warn { color: #d97706; }
}

.user-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: var(--curator-surface-low);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.user-table-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  vertical-align: middle;

  :deep(img) {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
  }
}

.price-text {
  color: #f56c6c;
  font-weight: 600;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

a {
  color: #409eff;
  cursor: pointer;
  font-size: 13px;

  &:hover {
    opacity: 0.8;
  }

  &.text-danger {
    color: #f56c6c;
  }
}

.form-section-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--curator-on-surface);
  padding: 6px 0 12px;
  border-bottom: 1px solid var(--curator-outline);
  margin-bottom: 16px;

  &:not(:first-child) {
    margin-top: 8px;
  }
}
</style>
