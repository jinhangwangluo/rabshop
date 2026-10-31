<template>
  <div class="hotel-list">
    <header class="page-header">
      <div>
        <h1>酒店管理</h1>
        <p>维护平台与商户酒店资料、房型和销售状态。</p>
      </div>
      <el-button type="primary" :icon="Plus" @click="router.push({ name: 'hotelAdminCreate' })">新增酒店</el-button>
    </header>
    <section class="work-surface">
      <el-form inline :model="query" class="filter-form" @submit.prevent="fetchHotels">
        <el-form-item label="酒店名称"><el-input v-model="query.q" clearable placeholder="搜索酒店名称" @keyup.enter="fetchHotels" /></el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="fetchHotels">查询</el-button>
          <el-button :icon="Refresh" @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
      <div class="table-toolbar">
        <div><strong>酒店列表</strong><span>共 {{ count }} 家</span></div>
        <el-button text :icon="Refresh" :loading="loading" @click="fetchHotels">刷新</el-button>
      </div>
      <el-table v-loading="loading" :data="list" row-key="id" class="hotel-table" empty-text="暂无酒店，点击新增酒店开始录入">
        <el-table-column label="酒店" min-width="280"><template #default="{ row }"><div class="hotel-name"><el-image v-if="row.cover" :src="row.cover" :preview-src-list="[row.cover]" preview-teleported fit="cover" class="cover" /><div v-else class="cover cover--empty">暂无</div><div class="hotel-name__text"><strong>{{ row.name }}</strong><small>{{ row.address || '未填写地址' }}</small></div></div></template></el-table-column>
        <el-table-column prop="merchant_id" label="归属" width="110"><template #default="{ row }">{{ Number(row.merchant_id) ? `商户 ${row.merchant_id}` : '平台自营' }}</template></el-table-column>
        <el-table-column prop="price" label="起价 / 晚" width="110"><template #default="{ row }">¥{{ row.price }}</template></el-table-column>
        <el-table-column label="审核" width="120"><template #default="{ row }"><el-tag :type="row.audit_status === 1 ? 'success' : row.audit_status === 2 ? 'danger' : 'warning'">{{ row.audit_status === 1 ? '已通过' : row.audit_status === 2 ? '已驳回' : '待审核' }}</el-tag></template></el-table-column>
        <el-table-column label="状态" width="105"><template #default="{ row }"><el-switch :model-value="row.status === 1" :disabled="row.audit_status !== 1" @change="changeStatus(row, $event)" /></template></el-table-column>
        <el-table-column label="操作" width="300" fixed="right"><template #default="{ row }">
          <el-button link type="primary" @click="router.push({ name: 'hotelAdminEdit', params: { id: row.id } })">编辑</el-button>
          <el-button link type="primary" @click="router.push({ name: 'hotelAdminCalendar', params: { id: row.id } })">房态</el-button>
          <el-button v-if="row.merchant_id > 0 && row.audit_status !== 1" link type="success" @click="audit(row, 1)">通过</el-button>
          <el-button v-if="row.merchant_id > 0 && row.audit_status !== 2" link type="danger" @click="audit(row, 2)">驳回</el-button>
        </template></el-table-column>
      </el-table>
      <el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next, jumper" :total="count" @size-change="handleSizeChange" @current-change="fetchHotels" />
    </section>
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { auditHotel, getHotels, setHotelStatus } from '../../api/hotel'

defineOptions({ name: 'HotelAdminHotels' })
const router = useRouter()
const query = reactive({ q: '', page: 1, limit: 20 })
const list = ref([])
const count = ref(0)
const loading = ref(false)
async function fetchHotels() {
  loading.value = true
  try {
    const { data } = await getHotels({ ...query })
    list.value = data?.list || []
    count.value = Number(data?.count || 0)
  } catch (error) { ElMessage.error(error.message || '酒店列表加载失败') } finally { loading.value = false }
}
function resetQuery() { Object.assign(query, { q: '', page: 1, limit: 20 }); fetchHotels() }
function handleSizeChange(size) { query.limit = size; query.page = 1; fetchHotels() }
async function changeStatus(row, enabled) {
  try { await setHotelStatus(row.id, enabled ? 1 : 0); await fetchHotels() } catch (error) { ElMessage.error(error.message || '状态修改失败') }
}
async function audit(row, status) {
  try {
    await ElMessageBox.confirm(`确认${status === 1 ? '通过' : '驳回'} ${row.name}？`, '酒店审核')
    await auditHotel(row.id, status)
    ElMessage.success('审核已更新')
    await fetchHotels()
  } catch (error) { if (error !== 'cancel') ElMessage.error(error.message || '审核失败') }
}
onMounted(fetchHotels)
</script>
<style lang="scss" scoped>
.hotel-list { padding: 24px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-bottom: 20px; h1 { margin: 0; color: var(--el-text-color-primary); font-size: 22px; line-height: 1.3; } p { margin: 8px 0 0; color: var(--el-text-color-secondary); font-size: 13px; } }
.work-surface { padding: 20px; background: var(--el-bg-color); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }
.filter-form { padding-bottom: 4px; border-bottom: 1px solid var(--el-border-color-lighter); }
.table-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 18px 0 14px; strong { font-size: 16px; } span { margin-left: 10px; color: var(--el-text-color-secondary); font-size: 13px; } }
.hotel-name { display: flex; align-items: center; min-width: 0; .cover { flex: none; width: 64px; height: 52px; margin-right: 12px; border-radius: 6px; background: var(--el-fill-color-light); } .cover--empty { display: flex; align-items: center; justify-content: center; color: var(--el-text-color-secondary); font-size: 12px; } strong, small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } small { max-width: 220px; margin-top: 4px; color: var(--el-text-color-secondary); font-size: 12px; } }
.hotel-table :deep(.el-table__cell) { padding: 12px 0; }
:deep(.el-pagination) { margin-top: 20px; justify-content: flex-end; }
@media (max-width: 768px) { .hotel-list { padding: 16px; } .page-header { align-items: stretch; flex-direction: column; gap: 14px; } .page-header .el-button { align-self: flex-start; } .work-surface { padding: 14px; } }
</style>
