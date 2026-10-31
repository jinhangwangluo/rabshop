<template>
  <section class="seckill-admin">
    <header class="page-heading">
      <div><h1>秒杀活动</h1><span class="page-heading__count">{{ total }} 个活动</span></div>
      <div class="page-actions">
        <el-button v-if="isPlatform" :icon="Setting" @click="handleSettings">支付设置</el-button>
        <el-button type="primary" :icon="Plus" @click="handleCreate">创建活动</el-button>
      </div>
    </header>

    <div class="activity-summary">
      <span>本页售出 <strong>{{ summary.sold }}</strong> 件</span>
      <span>待支付 <strong>{{ summary.reserved }}</strong> 件</span>
      <span>订单 <strong>{{ summary.order_count }}</strong> 笔</span>
    </div>

    <el-form class="activity-filters" inline @submit.prevent="handleSearch">
      <el-form-item><el-input v-model="query.q" placeholder="活动名称" clearable :prefix-icon="Search" @keyup.enter="handleSearch" /></el-form-item>
      <el-form-item><el-select v-model="query.status" placeholder="发布状态" clearable><el-option label="已发布" :value="1" /><el-option label="已暂停" :value="0" /></el-select></el-form-item>
      <el-form-item><el-select v-model="query.audit_status" placeholder="审核状态" clearable><el-option v-for="item in auditOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
      <el-form-item><el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button><el-tooltip content="重置筛选"><el-button :icon="RefreshLeft" circle aria-label="重置筛选" @click="handleReset" /></el-tooltip></el-form-item>
    </el-form>
    <el-alert v-if="listError" :title="listError" type="error" show-icon :closable="false" class="page-error"><el-button text @click="fetchList">重试</el-button></el-alert>
    <el-table v-loading="loading" :data="list" row-key="id" class="activity-table">
      <el-table-column label="活动" min-width="240">
        <template #default="{ row }"><div class="activity-name"><el-image v-if="row.cover" :src="row.cover" fit="cover" /><div><strong>{{ row.title }}</strong><span>#{{ row.id }}<template v-if="isPlatform"> · {{ row.merchant_id ? '商户 #' + row.merchant_id : '平台自营' }}</template></span></div></div></template>
      </el-table-column>
      <el-table-column label="日期 / 每日时段" min-width="225"><template #default="{ row }"><span>{{ row.start_date }} 至 {{ row.end_date }}</span><div class="time-tags"><el-tag v-for="(slot, index) in row.slots" :key="index" size="small" type="info">{{ slot.start_time }}–{{ slot.end_time }}</el-tag></div></template></el-table-column>
      <el-table-column label="审核" width="105"><template #default="{ row }"><el-tooltip :content="row.audit_reason || auditLabel(row.audit_status)"><el-tag :type="auditType(row.audit_status)">{{ auditLabel(row.audit_status) }}</el-tag></el-tooltip></template></el-table-column>
      <el-table-column label="发布状态" width="105"><template #default="{ row }"><el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '已发布' : '已暂停' }}</el-tag></template></el-table-column>
      <el-table-column prop="sold" label="售出" width="80" /><el-table-column prop="reserved" label="待支付" width="85" /><el-table-column prop="order_count" label="订单" width="80" />
      <el-table-column label="操作" width="270" fixed="right">
        <template #default="{ row }">
          <el-button text type="primary" @click="handleEdit(row)">编辑</el-button>
          <el-button v-if="isPlatform && row.merchant_id" text type="primary" @click="handleAudit(row)">审核</el-button>
          <el-button text :loading="busyId === row.id" :disabled="row.status !== 1 && row.audit_status !== 1" @click="handleStatus(row)">{{ row.status === 1 ? '暂停' : '发布' }}</el-button>
          <el-tooltip content="删除活动"><el-button text type="danger" :icon="Delete" aria-label="删除活动" @click="handleDelete(row)" /></el-tooltip>
        </template>
      </el-table-column>
      <template #empty><el-empty description="暂无秒杀活动" :image-size="90" /></template>
    </el-table>
    <el-pagination v-model:current-page="query.page" :page-size="query.limit" :total="total" layout="total, prev, pager, next" @current-change="fetchList" />

    <el-dialog v-if="isPlatform" v-model="auditVisible" title="活动审核" width="460px">
      <el-form label-width="80px"><el-form-item label="审核结果"><el-radio-group v-model="auditForm.audit_status"><el-radio :value="1">通过</el-radio><el-radio :value="2">拒绝</el-radio></el-radio-group></el-form-item><el-form-item label="审核原因"><el-input v-model="auditForm.audit_reason" type="textarea" :rows="3" maxlength="500" /></el-form-item></el-form>
      <template #footer><el-button @click="auditVisible = false">取消</el-button><el-button type="primary" :loading="auditSaving" @click="handleSubmitAudit">确认审核</el-button></template>
    </el-dialog>
    <el-dialog v-if="isPlatform" v-model="settingsVisible" title="秒杀支付设置" width="460px">
      <el-alert v-if="settingsError" :title="settingsError" type="error" :closable="false" />
      <el-form v-loading="settingsLoading" label-width="130px"><el-form-item label="未支付保留时间"><el-input-number v-model="unpaidMinutes" :min="1" :max="1440" :precision="0" /><span class="field-unit">分钟</span></el-form-item></el-form>
      <template #footer><el-button @click="settingsVisible = false">取消</el-button><el-button type="primary" :disabled="settingsLoading || !!settingsError" :loading="settingsSaving" @click="handleSaveSettings">保存</el-button></template>
    </el-dialog>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Plus, RefreshLeft, Search, Setting } from '@element-plus/icons-vue'
import * as api from '../../api/seckill'

defineOptions({ name: 'SeckillMerchantActivityPage' })
const router = useRouter()
const isPlatform = false
const auditOptions = [{ value: 0, label: '待审核' }, { value: 1, label: '已通过' }, { value: 2, label: '已拒绝' }]
const list = ref([])
const total = ref(0)
const loading = ref(false)
const installGeneration = ref('')
const listError = ref('')
const busyId = ref(0)
const query = reactive({ q: '', status: '', audit_status: '', page: 1, limit: 15 })
const summary = computed(() => list.value.reduce((result, row) => ({ sold: result.sold + Number(row.sold || 0), reserved: result.reserved + Number(row.reserved || 0), order_count: result.order_count + Number(row.order_count || 0) }), { sold: 0, reserved: 0, order_count: 0 }))
const auditVisible = ref(false)
const auditSaving = ref(false)
const auditForm = reactive({ id: 0, install_generation: '', audit_status: 1, audit_reason: '' })
const settingsVisible = ref(false)
const settingsLoading = ref(false)
const settingsSaving = ref(false)
const settingsError = ref('')
const unpaidMinutes = ref(5)
const settingsGeneration = ref('')
let listSequence = 0

function errorText(error, fallback) { return error?.message || error?.msg || fallback }
function auditLabel(value) { return auditOptions.find(item => item.value === Number(value))?.label || '未知' }
function auditType(value) { return Number(value) === 1 ? 'success' : Number(value) === 2 ? 'danger' : 'warning' }

async function fetchList() {
  const sequence = ++listSequence
  loading.value = true
  listError.value = ''
  try {
    const { data } = await api.getActivityList({ ...query })
    if (sequence !== listSequence) return
    list.value = Array.isArray(data.list) ? data.list : []
    total.value = Number(data.count)
    installGeneration.value = String(data.install_generation || '')
  } catch (error) {
    if (sequence === listSequence) { list.value = []; total.value = 0; installGeneration.value = ''; listError.value = errorText(error, '活动加载失败') }
  } finally { if (sequence === listSequence) loading.value = false }
}
function handleSearch() { query.page = 1; fetchList() }
function handleReset() { Object.assign(query, { q: '', status: '', audit_status: '', page: 1 }); fetchList() }
function handleCreate() {
  if (!installGeneration.value || loading.value) { ElMessage.warning('请先加载当前活动列表'); return }
  router.push({ name: 'seckillMerchantActivityCreate' })
}
function handleEdit(row) { router.push({ name: 'seckillMerchantActivityEdit', params: { id: row.id }, query: { install_generation: row.install_generation }}) }
function handleStatus(row) {
  if (busyId.value) return
  busyId.value = row.id
  return api.setActivityStatus(row.id, Number(row.status) === 1 ? 0 : 1, row.install_generation).then(fetchList).catch(error => {
    ElMessage.error(errorText(error, '状态更新失败'))
  }).finally(() => { busyId.value = 0 })
}
async function handleDelete(row) {
  try { await ElMessageBox.confirm('确认删除“' + row.title + '”？已开始或有订单的活动不能删除。', '删除活动', { type: 'warning' }) } catch { return }
  try { await api.deleteActivity(row.id, row.install_generation); ElMessage.success('活动已删除'); await fetchList() } catch (error) { ElMessage.error(errorText(error, '删除失败')) }
}
function handleAudit(row) { if (auditSaving.value) return; Object.assign(auditForm, { id: row.id, install_generation: row.install_generation, audit_status: 1, audit_reason: row.audit_reason || '' }); auditVisible.value = true }
function handleSubmitAudit() {
  if (auditSaving.value) return
  if (auditForm.audit_status === 2 && !auditForm.audit_reason.trim()) { ElMessage.warning('请填写拒绝原因'); return }
  auditSaving.value = true
  return api.auditActivity(auditForm.id, { install_generation: auditForm.install_generation, audit_status: auditForm.audit_status, audit_reason: auditForm.audit_reason.trim() }).then(async() => {
    auditVisible.value = false
    await fetchList()
  }).catch(error => { ElMessage.error(errorText(error, '审核失败')) }).finally(() => { auditSaving.value = false })
}
async function handleSettings() {
  settingsVisible.value = true
  settingsLoading.value = true
  settingsError.value = ''
  settingsGeneration.value = ''
  try { const { data } = await api.getSettings(); unpaidMinutes.value = Number(data.unpaid_cancel_minutes); settingsGeneration.value = String(data.install_generation || '') } catch (error) { settingsError.value = errorText(error, '设置加载失败') } finally { settingsLoading.value = false }
}
function handleSaveSettings() {
  if (settingsSaving.value || settingsLoading.value || settingsError.value || !settingsGeneration.value) return
  settingsSaving.value = true
  return api.saveSettings({ unpaid_cancel_minutes: unpaidMinutes.value, install_generation: settingsGeneration.value }).then(() => {
    settingsVisible.value = false
    ElMessage.success('设置已保存')
  }).catch(error => { ElMessage.error(errorText(error, '设置保存失败')) }).finally(() => { settingsSaving.value = false })
}
onMounted(fetchList)
</script>

<style scoped lang="scss">
.seckill-admin { padding: 24px; background: var(--surface-card, #fff); color: var(--text-primary, #303133); min-width: 0; }
.page-heading, .page-heading > div, .page-actions, .activity-name { display: flex; align-items: center; }
.page-heading { justify-content: space-between; }
.page-heading h1 { margin: 0; font-size: 22px; line-height: 32px; }
.page-heading__count { color: var(--text-secondary, #909399); margin-left: 16px; font-size: 13px; }
.activity-summary { display: flex; flex-wrap: wrap; padding: 20px 0; margin: 16px 0 22px; border-top: 1px solid var(--border-light, #ebeef5); border-bottom: 1px solid var(--border-light, #ebeef5); }
.activity-summary span { padding-right: 36px; color: var(--text-secondary, #909399); font-size: 13px; }
.activity-summary strong { color: var(--text-primary, #303133); font-size: 20px; margin: 0 8px; }
.activity-filters :deep(.el-select) { width: 140px; }
.activity-name { min-width: 0; }
.activity-name .el-image { flex-shrink: 0; width: 48px; height: 48px; margin-right: 12px; border-radius: 4px; }
.activity-name strong { display: block; line-height: 22px; font-size: 14px; overflow-wrap: anywhere; }
.activity-name span { display: block; font-size: 12px; color: var(--text-secondary, #909399); }
.time-tags { display: flex; flex-wrap: wrap; padding-top: 6px; }
.time-tags .el-tag { margin: 0 6px 4px 0; }
.page-error { margin-bottom: 16px; }
.el-pagination { justify-content: flex-end; margin-top: 20px; }
.field-unit { color: var(--text-secondary, #909399); margin-left: 10px; }
@media (max-width: 700px) { .seckill-admin { padding: 16px; } .page-heading { align-items: flex-start; } .page-heading > div { flex-wrap: wrap; } .page-heading__count { width: 100%; margin: 0; } .activity-summary span { padding-right: 16px; } }
</style>
