<template>
  <div class="finance-withdraw-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">提现申请</h1>
        <p class="page-curator-subtitle">
          查看提现审核进度，银行卡、微信、支付宝提现统一在弹窗中提交。
        </p>
      </div>
      <div class="page-curator-actions">
        <el-button class="btn-curator-reset" :icon="Tickets" @click="goRecords">
          资金流水
        </el-button>
        <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="openWithdrawDialog">
          申请提现
        </el-button>
      </div>
    </header>

    <section v-loading="overviewLoading" class="withdraw-summary">
      <article v-for="item in summaryCards" :key="item.key" class="withdraw-summary__item">
        <span class="withdraw-summary__label">{{ item.label }}</span>
        <strong>¥{{ formatMoney(item.value) }}</strong>
        <span class="withdraw-summary__hint">{{ item.hint }}</span>
      </article>
    </section>

    <div class="withdraw-work-surface">
      <el-form
        :model="historyFilter"
        inline
        class="withdraw-search-form"
        @submit.prevent
      >
        <el-form-item label="提现方式">
          <el-select v-model="historyFilter.withdraw_type" clearable placeholder="全部方式" class="search-select-sm">
            <el-option label="银行卡" value="bank" />
            <el-option label="微信" value="wechat" />
            <el-option label="支付宝" value="alipay" />
          </el-select>
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="historyFilter.status" clearable placeholder="全部状态" class="search-select-sm">
            <el-option label="待审核" :value="0" />
            <el-option label="已通过" :value="1" />
            <el-option label="已拒绝" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="申请时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"

            class="date-picker"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" :icon="Search" @click="handleHistorySearch">
            查询
          </el-button>
          <el-button class="btn-curator-reset" :icon="Refresh" @click="handleHistoryReset">
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <el-table
        v-loading="historyLoading"
        :data="historyList"
        row-key="id"
        empty-text="暂无提现记录"
        class="withdraw-table"
      >
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column label="提现金额" width="130" align="right">
          <template #default="{ row }">
            <span class="price-text">¥{{ formatMoney(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="提现方式" width="110" align="center">
          <template #default="{ row }">
            <el-tag  type="info">
              {{ row.withdraw_type_text || getWithdrawTypeName(row.withdraw_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="收款信息" min-width="230" show-overflow-tooltip>
          <template #default="{ row }">
            <div class="account-cell">
              <el-image
                v-if="row.payment_qrcode"
                :src="row.payment_qrcode"
                :preview-src-list="[row.payment_qrcode]"
                class="qrcode-thumb"
              />
              <div class="account-cell__text">
                <strong>{{ row.account_name || '-' }}</strong>
                <span>{{ row.account_summary || '-' }}</span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="审核状态" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusTag(row.status)" >
              {{ row.status_text || getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="审核备注" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.remark || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="申请时间" width="165" align="center" />
        <el-table-column label="审核时间" width="165" align="center">
          <template #default="{ row }">
            {{ row.audit_time || '-' }}
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="historyPage"
          v-model:page-size="historyLimit"
          :page-sizes="[10, 15, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="historyTotal"
          @size-change="handleHistorySizeChange"
          @current-change="handleHistoryCurrentChange"
        />
      </div>
    </div>

    <el-dialog
      v-model="dialogVisible"
      title="申请提现"
      width="560px"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
      <el-form
        ref="withdrawFormRef"
        :model="withdrawForm"
        :rules="rules"
        label-width="92px"
        class="withdraw-form"
      >
        <el-form-item label="可提现">
          <span class="available-amount">¥{{ formatMoney(overview.balance) }}</span>
        </el-form-item>
        <el-form-item label="提现金额" prop="amount">
          <el-input-number
            v-model="withdrawForm.amount"
            :min="0"
            :precision="2"
            :step="100"
            controls-position="right"
            class="form-control"
          />
        </el-form-item>
        <el-form-item label="提现方式" prop="withdraw_type">
          <el-radio-group v-model="withdrawForm.withdraw_type"  @change="handleWithdrawTypeChange">
            <el-radio-button label="bank">银行卡</el-radio-button>
            <el-radio-button label="wechat">微信</el-radio-button>
            <el-radio-button label="alipay">支付宝</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="收款姓名" prop="account_name">
          <el-input v-model.trim="withdrawForm.account_name" clearable placeholder="请输入收款人姓名" />
        </el-form-item>
        <template v-if="withdrawForm.withdraw_type === 'bank'">
          <el-form-item label="银行名称" prop="bank_name">
            <el-input v-model.trim="withdrawForm.bank_name" clearable placeholder="请输入银行名称" />
          </el-form-item>
          <el-form-item label="银行卡号" prop="bank_account">
            <el-input v-model.trim="withdrawForm.bank_account" clearable placeholder="请输入银行卡号" />
          </el-form-item>
        </template>
        <el-form-item v-else label="收款码" prop="payment_qrcode">
          <div class="qrcode-upload">
            <Upload v-model="withdrawForm.payment_qrcode" :limit="1" />
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">提交申请</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import Upload from '@/components/Upload/index'
import {
  getMerchantFinanceOverview,
  getMerchantWithdrawRecords,
  submitMerchantWithdraw
} from '@/api/finance'
import { Plus, Refresh, Search, Tickets } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

defineOptions({
  name: 'MerchantFinanceWithdraw'
})

const router = useRouter()

const createWithdrawForm = () => ({
  withdraw_type: 'bank',
  amount: undefined,
  bank_name: '',
  bank_account: '',
  account_name: '',
  payment_qrcode: ''
})

const createHistoryFilter = () => ({
  withdraw_type: '',
  status: '',
  start_time: '',
  end_time: ''
})

const overviewLoading = ref(false)
const submitLoading = ref(false)
const historyLoading = ref(false)
const dialogVisible = ref(false)
const dateRange = ref([])
const withdrawForm = ref(createWithdrawForm())
const historyFilter = ref(createHistoryFilter())
const historyList = ref([])
const historyTotal = ref(0)
const historyPage = ref(1)
const historyLimit = ref(10)
const overview = ref({
  balance: 0,
  frozen_amount: 0,
  total_income: 0
})
const withdrawFormRef = ref(null)

const validateAmount = (rule, value, callback) => {
  const amount = Number(value)
  const balance = Number(overview.value.balance || 0)
  if (!Number.isFinite(amount) || amount <= 0) {
    callback(new Error('请输入大于0的提现金额'))
    return
  }
  if (amount > balance) {
    callback(new Error('提现金额不能超过可提现余额'))
    return
  }
  callback()
}
const validateBankName = (rule, value, callback) => {
  if (withdrawForm.value.withdraw_type === 'bank' && !String(value || '').trim()) {
    callback(new Error('请输入银行名称'))
    return
  }
  callback()
}
const validateBankAccount = (rule, value, callback) => {
  if (withdrawForm.value.withdraw_type === 'bank' && !String(value || '').trim()) {
    callback(new Error('请输入银行卡号'))
    return
  }
  callback()
}
const validateQrcode = (rule, value, callback) => {
  if (withdrawForm.value.withdraw_type !== 'bank' && !String(value || '').trim()) {
    callback(new Error('请上传收款码'))
    return
  }
  callback()
}

const rules = {
  amount: [{ validator: validateAmount, trigger: 'blur' }],
  withdraw_type: [{ required: true, message: '请选择提现方式', trigger: 'change' }],
  account_name: [{ required: true, message: '请输入收款人姓名', trigger: 'blur' }],
  bank_name: [{ validator: validateBankName, trigger: 'blur' }],
  bank_account: [{ validator: validateBankAccount, trigger: 'blur' }],
  payment_qrcode: [{ validator: validateQrcode, trigger: 'change' }]
}

const summaryCards = computed(() => {
  return [
    { key: 'balance', label: '可提现余额', value: overview.value.balance, hint: '可提交提现申请' },
    { key: 'frozen_amount', label: '冻结金额', value: overview.value.frozen_amount, hint: '待释放资金' },
    { key: 'total_income', label: '累计收入', value: overview.value.total_income, hint: '全部入账收入' }
  ]
})

onMounted(() => {
  fetchOverview()
  fetchHistory()
})

function fetchOverview() {
  overviewLoading.value = true
  getMerchantFinanceOverview().then(res => {
    overview.value = Object.assign({}, overview.value, res.data || {})
  }).finally(() => {
    overviewLoading.value = false
  })
}

function fetchHistory() {
  historyLoading.value = true
  getMerchantWithdrawRecords({
    page: historyPage.value,
    limit: historyLimit.value,
    ...historyFilter.value
  }).then(res => {
    const data = res && res.data ? res.data : {}
    historyList.value = Array.isArray(data.list) ? data.list : []
    historyTotal.value = Number(data.total || 0)
  }).finally(() => {
    historyLoading.value = false
  })
}

function openWithdrawDialog() {
  withdrawForm.value = createWithdrawForm()
  dialogVisible.value = true
  nextTick(() => {
    if (withdrawFormRef.value) {
      withdrawFormRef.value.clearValidate()
    }
  })
}

function handleDialogClose() {
  withdrawForm.value = createWithdrawForm()
  if (withdrawFormRef.value) {
    withdrawFormRef.value.clearValidate()
  }
}

function handleWithdrawTypeChange(type) {
  if (type === 'bank') {
    withdrawForm.value.payment_qrcode = ''
  } else {
    withdrawForm.value.bank_name = ''
    withdrawForm.value.bank_account = ''
  }
  nextTick(() => {
    if (withdrawFormRef.value) {
      withdrawFormRef.value.clearValidate(['bank_name', 'bank_account', 'payment_qrcode'])
    }
  })
}

function handleSubmit() {
  withdrawFormRef.value.validate(valid => {
    if (!valid) {
      return
    }

    ElMessageBox.confirm('确定提交该提现申请吗？', '提示', {
      type: 'warning'
    }).then(() => {
      submitLoading.value = true
      submitMerchantWithdraw(buildSubmitPayload()).then(res => {
        ElMessage.success(res.message || '提现申请已提交')
        dialogVisible.value = false
        historyPage.value = 1
        fetchOverview()
        fetchHistory()
      }).finally(() => {
        submitLoading.value = false
      })
    }).catch(() => {})
  })
}

function buildSubmitPayload() {
  const type = withdrawForm.value.withdraw_type
  const payload = {
    withdraw_type: type,
    amount: withdrawForm.value.amount,
    account_name: withdrawForm.value.account_name
  }

  if (type === 'bank') {
    payload.bank_name = withdrawForm.value.bank_name
    payload.bank_account = withdrawForm.value.bank_account
  } else {
    payload.payment_qrcode = withdrawForm.value.payment_qrcode
  }

  return payload
}

function handleDateChange(value) {
  if (Array.isArray(value) && value.length === 2) {
    historyFilter.value.start_time = value[0]
    historyFilter.value.end_time = value[1]
  } else {
    historyFilter.value.start_time = ''
    historyFilter.value.end_time = ''
  }
}

function handleHistorySearch() {
  historyPage.value = 1
  fetchHistory()
}

function handleHistoryReset() {
  historyFilter.value = createHistoryFilter()
  dateRange.value = []
  historyPage.value = 1
  fetchHistory()
}

function handleHistorySizeChange(value) {
  historyLimit.value = value
  historyPage.value = 1
  fetchHistory()
}

function handleHistoryCurrentChange(value) {
  historyPage.value = value
  fetchHistory()
}

function goRecords() {
  router.push({ name: 'MerchantFinanceRecords' })
}

function getWithdrawTypeName(type) {
  const map = { bank: '银行卡', wechat: '微信', alipay: '支付宝' }
  return map[type] || '银行卡'
}

function getStatusLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已拒绝' }
  return map[Number(status)] || '待审核'
}

function getStatusTag(status) {
  const map = { 0: 'warning', 1: 'success', 2: 'danger' }
  return map[Number(status)] || 'info'
}

function formatMoney(value) {
  const number = Number(value)
  const safeNumber = Number.isFinite(number) ? number : 0
  return safeNumber.toLocaleString('zh-CN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}
</script>

<style lang="scss" scoped>
.finance-withdraw-page {
  min-height: calc(100vh - 84px);
  box-sizing: border-box;
  color: var(--text-primary);
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
  color: var(--text-title);
  font-size: 24px;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: 0;
}

.page-curator-subtitle {
  max-width: 560px;
  margin: 0;
  color: var(--text-secondary);
  font-size: 14px;
  line-height: 1.5;
}

.page-curator-actions {
  display: flex;
  flex-shrink: 0;
  gap: 10px;
}

.withdraw-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 14px;
}

.withdraw-summary__item {
  min-height: 92px;
  padding: 18px 20px;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 8px 20px var(--brand-shadow-card);

  strong {
    display: block;
    margin: 10px 0 6px;
    color: var(--text-title);
    font-size: 24px;
    line-height: 1.2;
  }
}

.withdraw-summary__label,
.withdraw-summary__hint {
  color: var(--text-secondary);
  font-size: 13px;
  line-height: 1.4;
}

.withdraw-work-surface {
  padding: 18px 20px;
  border: 1px solid var(--layout-outline);
  border-radius: 8px;
  background: var(--layout-surface-card);
  box-shadow: 0 8px 20px var(--brand-shadow-card);
}

.withdraw-search-form {
  margin-bottom: 8px;

  :deep(.el-form-item) {
    margin-right: 16px;
    margin-bottom: 16px;
  }
}

.search-select-sm {
  width: 128px;
}

.date-picker {
  width: 240px;
}

.withdraw-table {
  :deep(.el-table__header th) {
    background: var(--layout-surface-low);
    color: var(--text-secondary);
    font-weight: 600;
  }
}

.price-text {
  color: var(--text-danger);
  font-weight: 700;
}

.account-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.account-cell__text {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
  gap: 3px;

  strong {
    color: var(--text-primary);
    font-size: 13px;
    line-height: 1.3;
  }

  span {
    overflow: hidden;
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.qrcode-thumb {
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border: 1px solid var(--layout-outline);
  border-radius: 6px;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding-top: 16px;
}

.withdraw-form {
  :deep(.el-form-item) {
    margin-bottom: 20px;
  }
}

.available-amount {
  color: var(--text-success);
  font-size: 18px;
  font-weight: 700;
}

.form-control {
  width: 100%;
}

.qrcode-upload {
  display: flex;
  justify-content: center;
  min-height: 86px;
  padding: 6px 0 2px;

  :deep(.avatar-uploader .el-upload) {
    width: 86px;
    height: 86px;
    border-radius: 8px;
  }

  :deep(.avatar-uploader .avatar) {
    width: 86px;
    height: 86px;
    object-fit: cover;
  }
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--text-inverse);
  font-weight: 500;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--layout-surface-card);
  color: var(--brand-primary);
  border: 1px solid var(--brand-primary);
}

@media (max-width: 900px) {
  .page-curator-header {
    flex-direction: column;
  }

  .page-curator-actions {
    width: 100%;
  }

  .withdraw-summary {
    grid-template-columns: 1fr;
  }
}
</style>
