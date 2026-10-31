<template>
  <div class="bookings"><div class="heading"><h2>酒店预订</h2><el-button :icon="Refresh" title="刷新" @click="fetchBookings" /></div>
    <el-form inline @submit.prevent="fetchBookings"><el-form-item label="状态"><el-select v-model="query.status" clearable placeholder="全部状态" @change="fetchBookings"><el-option v-for="item in statuses" :key="item.value" :label="item.label" :value="item.value" /></el-select></el-form-item></el-form>
    <el-table v-loading="loading" :data="list" row-key="id" empty-text="暂无预订"><el-table-column prop="order_sn" label="订单号" min-width="180" /><el-table-column label="酒店 / 房型" min-width="210"><template #default="{ row }"><strong>{{ row.property_snapshot_json?.hotel_name }}</strong><small>{{ row.property_snapshot_json?.room_name }}</small></template></el-table-column>
      <el-table-column label="入住至退房" min-width="190"><template #default="{ row }">{{ row.check_in }} 至 {{ row.check_out }}</template></el-table-column><el-table-column prop="room_count" label="间数" width="70" /><el-table-column label="状态" width="120"><template #default="{ row }"><el-tag>{{ label(row.status) }}</el-tag></template></el-table-column>
      <el-table-column label="操作" width="115" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="router.push({ name: 'hotelMerchantBookingDetail', params: { id: row.id } })">查看详情</el-button></template></el-table-column></el-table><el-pagination v-model:current-page="query.page" :page-size="query.limit" :total="count" layout="total, prev, pager, next" @current-change="fetchBookings" />
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { getBookings } from '../../api/hotel'
defineOptions({ name: 'HotelMerchantBookings' })
const router = useRouter()
const statuses = [{ value: 'reserved', label: '待支付' }, { value: 'paid', label: '已预订' }, { value: 'checked_in', label: '已入住' }, { value: 'checkout_recorded', label: '已登记离店' }, { value: 'no_show_recorded', label: '未到店待复核' }, { value: 'cancel_requested', label: '取消待审' }, { value: 'refund_pending', label: '退款处理中' }, { value: 'refund_failed', label: '退款失败' }, { value: 'refunded', label: '已取消退款' }, { value: 'completed', label: '已完成' }, { value: 'cancelled', label: '已关单' }]
const label = value => statuses.find(item => item.value === value)?.label || value
const query = reactive({ status: '', page: 1, limit: 20 })
const list = ref([])
const count = ref(0)
const loading = ref(false)
async function fetchBookings() { loading.value = true; try { const { data } = await getBookings({ ...query }); list.value = data?.list || []; count.value = Number(data?.count || 0) } catch (error) { ElMessage.error(error.message || '预订加载失败') } finally { loading.value = false } }
onMounted(fetchBookings)
</script>
<style lang="scss" scoped>
.bookings { padding: 20px; }.heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; h2 { margin: 0; font-size: 20px; } }small { display: block; color: var(--text-secondary); }:deep(.el-pagination) { justify-content: flex-end; margin-top: 20px; }
</style>
