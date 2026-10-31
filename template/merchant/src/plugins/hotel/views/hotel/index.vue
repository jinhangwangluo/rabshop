<template>
  <div class="hotel-list">
    <div class="toolbar"><h2>酒店管理</h2><el-button type="primary" :icon="Plus" @click="router.push({ name: 'hotelMerchantCreate' })">新增酒店</el-button></div>
    <el-form inline :model="query" @submit.prevent="fetchHotels"><el-form-item label="酒店名称"><el-input v-model="query.q" clearable placeholder="搜索酒店" @keyup.enter="fetchHotels" /></el-form-item><el-button :icon="Search" @click="fetchHotels">查询</el-button></el-form>
    <el-table v-loading="loading" :data="list" row-key="id" empty-text="暂无酒店，点击新增酒店开始录入">
      <el-table-column label="酒店" min-width="260"><template #default="{ row }"><div class="hotel-name"><el-image :src="row.cover" fit="cover" class="cover" /><div><strong>{{ row.name }}</strong><small>{{ row.address }}</small></div></div></template></el-table-column>
      <el-table-column prop="price" label="起价 / 晚" width="120"><template #default="{ row }">¥{{ row.price }}</template></el-table-column>
      <el-table-column label="审核" width="125"><template #default="{ row }"><el-tag :type="row.audit_status === 1 ? 'success' : row.audit_status === 2 ? 'danger' : 'warning'">{{ row.audit_status === 1 ? '已通过' : row.audit_status === 2 ? '已驳回' : '待审核' }}</el-tag></template></el-table-column>
      <el-table-column label="状态" width="100"><template #default="{ row }"><el-switch :model-value="row.status === 1" :disabled="row.audit_status !== 1" @change="changeStatus(row, $event)" /></template></el-table-column>
      <el-table-column label="操作" width="170"><template #default="{ row }"><el-button link type="primary" @click="router.push({ name: 'hotelMerchantEdit', params: { id: row.id } })">编辑</el-button><el-button link type="primary" @click="router.push({ name: 'hotelMerchantCalendar', params: { id: row.id } })">房态</el-button></template></el-table-column>
    </el-table>
    <el-pagination v-model:current-page="query.page" layout="total, prev, pager, next" :page-size="query.limit" :total="count" @current-change="fetchHotels" />
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, Search } from '@element-plus/icons-vue'
import { getHotels, setHotelStatus } from '../../api/hotel'

defineOptions({ name: 'HotelMerchantHotels' })
const router = useRouter()
const query = reactive({ q: '', page: 1, limit: 20 })
const list = ref([])
const count = ref(0)
const loading = ref(false)
async function fetchHotels() {
  loading.value = true
  try { const { data } = await getHotels({ ...query }); list.value = data?.list || []; count.value = Number(data?.count || 0) } catch (error) { ElMessage.error(error.message || '酒店列表加载失败') } finally { loading.value = false }
}
async function changeStatus(row, enabled) {
  try { await setHotelStatus(row.id, enabled ? 1 : 0); await fetchHotels() } catch (error) { ElMessage.error(error.message || '状态修改失败') }
}
onMounted(fetchHotels)
</script>
<style lang="scss" scoped>
.hotel-list { padding: 20px; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; h2 { margin: 0; font-size: 20px; } }
.hotel-name { display: flex; align-items: center; min-width: 0; .cover { flex: none; width: 56px; height: 48px; margin-right: 12px; border-radius: 4px; } strong, small { display: block; } small { color: var(--text-secondary); } }
:deep(.el-pagination) { margin-top: 20px; justify-content: flex-end; }
</style>
