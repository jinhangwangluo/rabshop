<template>
  <section class="seckill-admin">
    <header class="page-curator-header">
      <h1 class="page-curator-title">秒杀活动</h1>
      <div class="page-actions">
        <el-button v-if="isPlatform" :icon="Setting" @click="handleSettings">支付设置</el-button>
        <el-button type="primary" :icon="Plus" @click="handleCreate">创建活动</el-button>
      </div>
    </header>

    <div class="category-work-surface">
      <el-form class="activity-filters" inline @submit.prevent="handleSearch">
        <el-form-item label="活动名称"><el-input v-model="query.q" class="filter-input" placeholder="请输入活动名称" clearable @keyup.enter="handleSearch" /></el-form-item>
        <el-form-item label="发布状态"><el-select v-model="query.status" class="filter-select" placeholder="全部" clearable><el-option label="已发布" :value="1" /><el-option label="已暂停" :value="0" /></el-select></el-form-item>
        <el-form-item label="审核状态"><el-select v-model="query.audit_status" class="filter-select" placeholder="全部" clearable><el-option v-for="item in auditOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item>
        <el-form-item><el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button><el-button :icon="RefreshLeft" @click="handleReset">重置</el-button></el-form-item>
      </el-form>
      <el-table v-loading="loading" :data="list" row-key="id" class="activity-table">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="70" align="center"><template #default="{ row }"><el-image v-if="row.cover" :src="row.cover" :preview-src-list="[row.cover]" preview-teleported fit="cover" class="cover-img" /><span v-else class="empty-text">-</span></template></el-table-column>
        <el-table-column prop="title" label="活动名称" min-width="200" show-overflow-tooltip />
        <el-table-column v-if="isPlatform" label="归属" min-width="105"><template #default="{ row }">{{ row.merchant_id ? '商户 #' + row.merchant_id : '平台自营' }}</template></el-table-column>
        <el-table-column label="活动日期" min-width="205" align="center"><template #default="{ row }">{{ row.start_date }} 至 {{ row.end_date }}</template></el-table-column>
        <el-table-column label="审核状态" width="105" align="center"><template #default="{ row }"><el-tooltip :content="row.audit_reason || auditLabel(row.audit_status)"><el-tag :type="auditType(row.audit_status)">{{ auditLabel(row.audit_status) }}</el-tag></el-tooltip></template></el-table-column>
        <el-table-column label="发布状态" width="105" align="center"><template #default="{ row }"><el-tag :type="Number(row.status) === 1 ? 'success' : 'info'">{{ Number(row.status) === 1 ? '已发布' : '已暂停' }}</el-tag></template></el-table-column>
        <el-table-column prop="sold" label="售出" width="80" align="center" /><el-table-column prop="reserved" label="待支付" width="85" align="center" /><el-table-column prop="order_count" label="订单" width="80" align="center" />
        <el-table-column label="操作" width="225" fixed="right" align="center">
          <template #default="{ row }">
            <div class="table-action-group">
              <span v-for="(action, index) in visibleActions(row)" :key="action.command" class="action-item">
                <el-divider v-if="index > 0" direction="vertical" />
                <a href="#" class="table-action-link" :class="{ 'danger-link': action.danger, 'disabled-link': action.disabled }" :aria-disabled="action.disabled || undefined" @click.prevent="handleAction(action.command, row)">{{ action.label }}</a>
              </span>
              <template v-if="overflowActions(row).length">
                <el-divider direction="vertical" />
                <el-dropdown trigger="click" popper-class="seckill-action-dropdown" @command="command => handleAction(command, row)">
                  <a href="#" class="table-action-link" @click.prevent>更多<el-icon class="el-icon--right"><ArrowDown /></el-icon></a>
                  <template #dropdown><el-dropdown-menu><el-dropdown-item v-for="action in overflowActions(row)" :key="action.command" :command="action.command" :disabled="action.disabled" :class="{ 'dropdown-danger': action.danger }">{{ action.label }}</el-dropdown-item></el-dropdown-menu></template>
                </el-dropdown>
              </template>
            </div>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无秒杀活动" :image-size="90" /></template>
      </el-table>
      <div class="pagination-wrap"><el-pagination v-if="total > 0" v-model:current-page="query.page" v-model:page-size="query.limit" :page-sizes="[10, 15, 20, 50]" :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchList" /></div>
    </div>

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
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, Plus, RefreshLeft, Search, Setting } from '@element-plus/icons-vue'
import * as api from '../../api/seckill'

defineOptions({ name: 'SeckillActivityPage' })
const router = useRouter()
const isPlatform = true
const auditOptions = [{ value: 0, label: '待审核' }, { value: 1, label: '已通过' }, { value: 2, label: '已拒绝' }]
const list = ref([])
const total = ref(0)
const loading = ref(false)
const installGeneration = ref('')
const busyId = ref(0)
const query = reactive({ q: '', status: '', audit_status: '', page: 1, limit: 15 })
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
function activityActions(row) {
  const actions = [{ command: 'edit', label: '编辑' }]
  if (isPlatform && Number(row.merchant_id) > 0) actions.push({ command: 'audit', label: '审核', disabled: auditSaving.value })
  actions.push({ command: 'status', label: Number(row.status) === 1 ? '暂停' : '发布', disabled: !!busyId.value || (Number(row.status) !== 1 && Number(row.audit_status) !== 1) })
  actions.push({ command: 'delete', label: '删除', danger: true })
  return actions
}
function visibleActions(row) { return activityActions(row).slice(0, 3) }
function overflowActions(row) { return activityActions(row).slice(3) }
function handleAction(command, row) {
  const action = activityActions(row).find(item => item.command === command)
  if (!action || action.disabled) return
  if (command === 'edit') return handleEdit(row)
  if (command === 'audit') return handleAudit(row)
  if (command === 'status') return handleStatus(row)
  if (command === 'delete') return handleDelete(row)
}

async function fetchList() {
  const sequence = ++listSequence
  loading.value = true
  try {
    const { data } = await api.getActivityList({ ...query })
    if (sequence !== listSequence) return
    list.value = Array.isArray(data.list) ? data.list : []
    total.value = Number(data.count)
    installGeneration.value = String(data.install_generation || '')
  } catch (error) {
    if (sequence === listSequence) { list.value = []; total.value = 0; installGeneration.value = ''; ElMessage.error(errorText(error, '获取活动列表失败')) }
  } finally { if (sequence === listSequence) loading.value = false }
}
function handleSearch() { query.page = 1; fetchList() }
function handleReset() { Object.assign(query, { q: '', status: '', audit_status: '', page: 1 }); fetchList() }
function handleSizeChange() { query.page = 1; fetchList() }
function handleCreate() {
  if (!installGeneration.value || loading.value) { ElMessage.warning('请先加载当前活动列表'); return }
  router.push({ name: 'seckillActivityCreate' })
}
function handleEdit(row) { router.push({ name: 'seckillActivityEdit', params: { id: row.id }, query: { install_generation: row.install_generation }}) }
function handleStatus(row) {
  if (busyId.value || (Number(row.status) !== 1 && Number(row.audit_status) !== 1)) return
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
.seckill-admin { min-height: 100%; box-sizing: border-box; min-width: 0; }
.page-curator-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 20px; }
.page-curator-title { margin: 0; font-size: 1.5rem; font-weight: 600; color: var(--ds-on-surface); letter-spacing: 0; }
.page-actions { display: flex; align-items: center; gap: 12px; }
.category-work-surface { background: var(--ds-surface-card); border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04); }
.activity-filters { margin-bottom: 8px; }
.filter-input { width: 160px; }
.filter-select { width: 110px; }
.activity-table { margin-top: 8px; }
.activity-table :deep(.el-table__header th) { color: var(--ds-on-surface-variant); font-weight: 500; background: rgba(var(--ds-surface-low-rgb), 0.5); }
.activity-table :deep(.el-table__row:hover > td) { background: var(--ds-surface-low) !important; }
.cover-img { width: 40px; height: 40px; border-radius: 6px; }
.empty-text { color: var(--ds-on-surface-variant); font-size: 12px; }
.table-action-group { display: flex; align-items: center; justify-content: center; white-space: nowrap; }
.action-item { display: inline-flex; align-items: center; }
.table-action-link { color: var(--ds-primary); cursor: pointer; }
.table-action-link:hover { color: var(--ds-primary-container); }
.table-action-link:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.danger-link, .danger-link:hover { color: var(--el-color-danger); }
.disabled-link { color: var(--ds-on-surface-variant); cursor: not-allowed; opacity: 0.6; }
.disabled-link:hover { color: var(--ds-on-surface-variant); }
.table-action-group :deep(.el-divider--vertical) { margin: 0 8px; }
.table-action-group :deep(.el-icon--right) { margin-left: 3px; vertical-align: middle; }
:global(.seckill-action-dropdown .el-dropdown-menu__item.dropdown-danger) { color: var(--el-color-danger); }
.pagination-wrap { padding: 16px 0 0; display: flex; justify-content: flex-end; }
.field-unit { color: var(--ds-on-surface-variant); margin-left: 10px; }
@media (max-width: 700px) {
  .page-curator-header { align-items: flex-start; flex-wrap: wrap; }
  .page-actions { flex-wrap: wrap; }
  .category-work-surface { padding: 16px; }
  .pagination-wrap { overflow-x: auto; justify-content: flex-start; }
}
</style>
