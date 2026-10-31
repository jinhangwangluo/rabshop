<template>
  <el-drawer
    v-model="localVisible"
    :title="`用户资产 — ${userName}`"
    size="680px"
    :wrapper-closable="true"
    @open="handleOpen"
    @close="handleClose"
  >
    <div class="drawer-body">
      <el-tabs v-model="activeTab">
        <!-- 资产信息 -->
        <el-tab-pane label="资产信息" name="asset">
          <div v-loading="assetLoading">
            <el-descriptions v-if="assetInfo" :column="2" border class="asset-desc">
              <el-descriptions-item label="余额">
                <span class="price-text">¥{{ assetInfo.balance || '0.00' }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="积分">
                <span class="points-text">{{ assetInfo.points || 0 }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="金币">
                <span class="coins-text">{{ assetInfo.coins || 0 }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="经验值">
                <span>{{ assetInfo.exp || 0 }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="佣金">
                <span class="commission-text">¥{{ assetInfo.commission || '0.00' }}</span>
              </el-descriptions-item>
              <el-descriptions-item label="更新时间" :span="2">
                {{ assetInfo.update_time || '-' }}
              </el-descriptions-item>
            </el-descriptions>
            <el-empty v-else-if="!assetLoading" description="暂无资产数据" :image-size="60" />
          </div>
          <div class="asset-actions">
            <el-button type="primary" :icon="Edit" @click="openAdjustDialog">调整资产</el-button>
          </div>
        </el-tab-pane>

        <!-- 流水记录 -->
        <el-tab-pane label="流水记录" name="log">
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
              <el-option label="经验值" value="exp" />
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
          </div>
          <el-table v-loading="logLoading" :data="logList" class="log-table">
            <el-table-column label="类型" width="80" align="center">
              <template #default="scope">
                <el-tag
                  :type="getAssetTagType(scope.row.type)"
                  size="mini"
                >{{ getAssetTypeLabel(scope.row.type) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="方向" width="70" align="center">
              <template #default="scope">
                <span :class="scope.row.change_type === 'in' ? 'text-success' : 'text-danger'">
                  {{ scope.row.change_type === 'in' ? '增加' : '减少' }}
                </span>
              </template>
            </el-table-column>
            <el-table-column label="变动数量" width="100" align="center">
              <template #default="scope">
                <span :class="scope.row.change_type === 'in' ? 'text-success' : 'text-danger'">
                  {{ scope.row.change_type === 'in' ? '+' : '-' }}{{ scope.row.amount }}
                </span>
              </template>
            </el-table-column>
            <el-table-column label="变前 / 变后" width="150" align="center">
              <template #default="scope">
                <span class="text-gray">{{ scope.row.before }}</span>
                <span class="text-gray"> → </span>
                <span>{{ scope.row.after }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="remark" label="备注" min-width="110" show-overflow-tooltip />
            <el-table-column prop="create_time" label="时间" width="150" align="center" />
          </el-table>
          <div class="drawer-pagination">
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

    <!-- 调整资产弹窗 -->
    <el-dialog
      v-model="adjustVisible"
      title="调整用户资产"
      width="480px"
      :close-on-click-modal="false"
      append-to-body
      @close="handleAdjustClose"
    >
      <el-form
        ref="adjustFormRef"
        :model="adjustForm"
        :rules="adjustRules"
        label-width="100px"
      >
        <el-form-item label="资产类型" prop="type">
          <el-radio-group v-model="adjustForm.type">
            <el-radio label="balance">余额</el-radio>
            <el-radio label="points">积分</el-radio>
            <el-radio label="coins">金币</el-radio>
            <el-radio label="exp">经验值</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="变动方向" prop="change_type">
          <el-radio-group v-model="adjustForm.change_type">
            <el-radio label="in">增加</el-radio>
            <el-radio label="out">减少</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="变动数量" prop="amount">
          <el-input-number
            v-model="adjustForm.amount"
            :min="0.01"
            :precision="adjustForm.type === 'balance' ? 2 : 0"
            controls-position="right"
            style="width: 180px;"
          />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="adjustForm.remark" placeholder="请输入调整备注" maxlength="100" />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="adjustVisible = false">取 消</el-button>
          <el-button type="primary" :loading="adjustLoading" @click="handleAdjustSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </el-drawer>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Edit, Refresh } from '@element-plus/icons-vue'
import { getAssetByUser, getUserAssetLogs, changeUserAsset } from '@/api/user'

defineOptions({ name: 'UserAssetDrawer' })

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  userId: {
    type: Number,
    default: 0
  },
  userName: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:visible'])

const adjustFormRef = ref(null)
const localVisible = ref(false)
const activeTab = ref('asset')
const assetLoading = ref(false)
const assetInfo = ref(null)
const logLoading = ref(false)
const logList = ref([])
const logTotal = ref(0)
const logPage = ref(1)
const logLimit = ref(15)
const logSearch = reactive({ type: '', change_type: '' })
const adjustVisible = ref(false)
const adjustLoading = ref(false)
const adjustForm = reactive(createDefaultAdjustForm())
const adjustRules = {
  type: [{ required: true, message: '请选择资产类型', trigger: 'change' }],
  change_type: [{ required: true, message: '请选择变动方向', trigger: 'change' }],
  amount: [{ required: true, message: '请输入变动数量', trigger: 'blur' }]
}

watch(() => props.visible, val => {
  localVisible.value = val
}, { immediate: true })

watch(localVisible, val => {
  emit('update:visible', val)
})

watch(activeTab, val => {
  if (val === 'log' && logList.value.length === 0) {
    loadLogs()
  }
})

function createDefaultAdjustForm() {
  return { type: 'balance', change_type: 'in', amount: 1, remark: '' }
}

function handleOpen() {
  activeTab.value = 'asset'
  loadAsset()
}

function handleClose() {
  assetInfo.value = null
  logList.value = []
  logTotal.value = 0
  logPage.value = 1
  Object.assign(logSearch, { type: '', change_type: '' })
  emit('update:visible', false)
}

function loadAsset() {
  if (!props.userId) return
  assetLoading.value = true
  getAssetByUser(props.userId).then(res => {
    assetInfo.value = res.data || null
  }).catch(() => {
    assetInfo.value = null
  }).finally(() => {
    assetLoading.value = false
  })
}

function loadLogs() {
  if (!props.userId) return
  logLoading.value = true
  const params = {
    page: logPage.value,
    limit: logLimit.value,
    ...logSearch
  }
  getUserAssetLogs(props.userId, params).then(res => {
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
  Object.assign(adjustForm, createDefaultAdjustForm())
  adjustVisible.value = true
}

function handleAdjustClose() {
  adjustFormRef.value?.resetFields()
}

function handleAdjustSubmit() {
  adjustFormRef.value?.validate(valid => {
    if (!valid) return
    adjustLoading.value = true
    const data = { user_id: props.userId, ...adjustForm }
    changeUserAsset(data).then(res => {
      ElMessage.success(res.message || '操作成功')
      adjustVisible.value = false
      loadAsset()
      if (activeTab.value === 'log') {
        logPage.value = 1
        loadLogs()
      }
    }).finally(() => {
      adjustLoading.value = false
    })
  })
}

function getAssetTypeLabel(type) {
  const map = { balance: '余额', points: '积分', coins: '金币', exp: '经验', commission: '佣金' }
  return map[type] || type || '-'
}

function getAssetTagType(type) {
  const map = { balance: 'primary', points: 'warning', coins: 'danger', exp: '', commission: 'success' }
  return map[type] || ''
}
</script>

<style lang="scss" scoped>
.drawer-body {
  padding: 0 20px 20px;
  height: 100%;
  display: flex;
  flex-direction: column;
}
.asset-desc {
  margin-top: 8px;
}
.asset-actions {
  margin-top: 20px;
}
.log-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0 12px;
}
.log-select {
  width: 120px;
}
.log-table {
  flex: 1;
}
.drawer-pagination {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
}
.price-text {
  color: #f56c6c;
  font-weight: 600;
}
.points-text {
  color: #e6a23c;
  font-weight: 600;
}
.coins-text {
  color: #f56c6c;
  font-weight: 600;
}
.commission-text {
  color: #67c23a;
  font-weight: 600;
}
.text-success {
  color: #67c23a;
}
.text-danger {
  color: #f56c6c;
}
.text-gray {
  color: #909399;
  font-size: 12px;
}
</style>
