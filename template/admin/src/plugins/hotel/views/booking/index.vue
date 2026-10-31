<template>
  <div class="bookings"><header class="page-header"><div><h1>酒店预订</h1><p>查看入住安排、支付状态和售后处理进度。</p></div><el-button text :icon="Refresh" :loading="loading" @click="fetchBookings">刷新</el-button></header>
    <section class="work-surface"><el-form inline :model="query" class="filter-form" @submit.prevent="fetchBookings"><el-form-item label="状态"><el-select v-model="query.status" clearable placeholder="全部状态" @change="fetchBookings"><el-option v-for="item in statuses" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item><el-form-item><el-button type="primary" @click="fetchBookings">查询</el-button><el-button @click="resetQuery">重置</el-button></el-form-item></el-form>
      <div class="table-toolbar"><strong>预订记录</strong><span>共 {{ count }} 条</span></div>
      <el-table v-loading="loading" :data="list" row-key="id" class="booking-table" empty-text="暂无预订">
        <el-table-column prop="order_sn" label="订单号" min-width="180" /><el-table-column label="酒店 / 房型" min-width="210"><template #default="{ row }"><strong>{{ row.property_snapshot_json?.hotel_name }}</strong><small>{{ row.property_snapshot_json?.room_name }}</small></template></el-table-column>
        <el-table-column label="入住至退房" min-width="190"><template #default="{ row }">{{ row.check_in }} 至 {{ row.check_out }}</template></el-table-column><el-table-column prop="room_count" label="间数" width="70" /><el-table-column label="状态" width="120"><template #default="{ row }"><el-tag :type="statusType(row.status)">{{ label(row.status) }}</el-tag></template></el-table-column>
        <el-table-column label="操作" width="115" fixed="right"><template #default="{ row }"><el-button type="primary" link @click="router.push({ name: 'hotelAdminBookingDetail', params: { id: row.id } })">查看详情</el-button></template></el-table-column>
      </el-table><el-pagination v-model:current-page="query.page" v-model:page-size="query.limit" :page-sizes="[10, 20, 50]" :total="count" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange" @current-change="fetchBookings" /></section>
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { getBookings } from '../../api/hotel'
defineOptions({ name: 'HotelAdminBookings' })
const router = useRouter()
const statuses = [{ value: 'reserved', label: '待支付' }, { value: 'paid', label: '已预订' }, { value: 'checked_in', label: '已入住' }, { value: 'checkout_recorded', label: '已登记离店' }, { value: 'no_show_recorded', label: '未到店待复核' }, { value: 'cancel_requested', label: '取消待审' }, { value: 'refund_pending', label: '退款处理中' }, { value: 'refund_failed', label: '退款失败' }, { value: 'refunded', label: '已取消退款' }, { value: 'completed', label: '已完成' }, { value: 'cancelled', label: '已关单' }]
const label = value => statuses.find(item => item.value === value)?.label || value
const statusType = value => ({ paid: 'success', checked_in: 'success', completed: 'success', cancel_requested: 'warning', refund_pending: 'warning', refund_failed: 'danger', cancelled: 'info', refunded: 'info' }[value] || 'warning')
const query = reactive({ status: '', page: 1, limit: 20 })
const list = ref([])
const count = ref(0)
const loading = ref(false)
async function fetchBookings() { loading.value = true; try { const { data } = await getBookings({ ...query }); list.value = data?.list || []; count.value = Number(data?.count || 0) } catch (error) { ElMessage.error(error.message || '预订加载失败') } finally { loading.value = false } }
function resetQuery() { Object.assign(query, { status: '', page: 1, limit: 20 }); fetchBookings() }
function handleSizeChange(size) { query.limit = size; query.page = 1; fetchBookings() }
onMounted(fetchBookings)
</script>
<style lang="scss" scoped>
.bookings { padding: 24px; }.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; h1 { margin: 0; font-size: 22px; } p { margin: 8px 0 0; color: var(--el-text-color-secondary); font-size: 13px; } }.work-surface { padding: 20px; background: var(--el-bg-color); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }.filter-form { padding-bottom: 4px; border-bottom: 1px solid var(--el-border-color-lighter); }.table-toolbar { display: flex; align-items: center; gap: 10px; padding: 18px 0 14px; strong { font-size: 16px; } span { color: var(--el-text-color-secondary); font-size: 13px; } }.booking-table :deep(.el-table__cell) { padding: 12px 0; }small { display: block; color: var(--el-text-color-secondary); }:deep(.el-pagination) { justify-content: flex-end; margin-top: 20px; } @media (max-width: 768px) { .bookings { padding: 16px; } .work-surface { padding: 14px; } }
</style>
