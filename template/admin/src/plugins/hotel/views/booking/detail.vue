<template>
  <div v-loading="loading" class="booking-detail">
    <header class="page-header"><div><div class="page-title"><h1>预订 {{ booking.order_sn }}</h1><el-tag :type="statusType(booking.status)">{{ statusLabel[booking.status] || booking.status }}</el-tag></div><p>酒店订单详情与售后处理记录</p></div><el-button :icon="ArrowLeft" @click="router.back()">返回</el-button></header>
    <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" />
    <template v-if="booking.id"><h3>订单与入住</h3><el-descriptions :column="2" border>
      <el-descriptions-item label="酒店">{{ booking.property_snapshot_json?.hotel_name }}</el-descriptions-item><el-descriptions-item label="房型">{{ booking.property_snapshot_json?.room_name }}</el-descriptions-item>
      <el-descriptions-item label="入住 / 退房">{{ booking.check_in }} 至 {{ booking.check_out }}</el-descriptions-item><el-descriptions-item label="间数">{{ booking.room_count }}</el-descriptions-item>
      <el-descriptions-item label="入住人">{{ booking.guest_snapshot_json?.guests?.map(item => item.name).join('、') }}</el-descriptions-item><el-descriptions-item label="联系电话">{{ booking.guest_snapshot_json?.contact_phone }}</el-descriptions-item>
      <el-descriptions-item label="实付">¥{{ booking.pay_amount }}</el-descriptions-item><el-descriptions-item label="已退">¥{{ booking.refunded_amount }}</el-descriptions-item>
      <el-descriptions-item label="免费取消截止">{{ booking.cancel_deadline || '不可免费取消' }}</el-descriptions-item><el-descriptions-item label="取消规则">提前 {{ booking.policy_snapshot_json?.cancel_before_hours }} 小时</el-descriptions-item>
    </el-descriptions>
      <h3>逐晚成交价格</h3><el-table :data="booking.nights || []" row-key="id"><el-table-column prop="stay_date" label="房晚" /><el-table-column prop="room_count" label="间数" /><el-table-column label="每间价格"><template #default="{ row }">¥{{ row.unit_price }}</template></el-table-column><el-table-column label="房晚金额"><template #default="{ row }">¥{{ row.amount }}</template></el-table-column><el-table-column prop="state" label="房态证据" /></el-table>
      <h3>操作与售后进度</h3><el-timeline v-if="booking.actions?.length"><el-timeline-item v-for="action in booking.actions" :key="action.id" :timestamp="action.create_time" placement="top">{{ action.action }} · {{ action.from_status }} → {{ action.to_status }}<span v-if="action.remark"> · {{ action.remark }}</span></el-timeline-item></el-timeline><el-empty v-else description="暂无操作记录" :image-size="72" />
      <div v-if="['cancel_requested', 'refund_pending', 'refund_failed'].includes(booking.status)" class="review">
        <h3>取消审核</h3><el-form label-width="100px" @submit.prevent><el-form-item label="退款金额"><el-input-number v-model="refundAmount" :min="0.01" :max="Number(booking.pay_amount)" :precision="2" /></el-form-item><el-form-item label="审核意见"><el-input v-model="remark" type="textarea" maxlength="500" /></el-form-item>
          <el-form-item><el-button type="primary" :loading="saving" @click="cancelReview(true)">{{ booking.status === 'refund_failed' ? '重试渠道退款' : '通过并发起退款' }}</el-button><el-button :loading="saving" @click="cancelReview(false)">驳回</el-button></el-form-item></el-form>
      </div>
      <div v-if="['checkout_recorded', 'no_show_recorded'].includes(booking.status)" class="review"><h3>履约复核</h3><el-input v-model="remark" type="textarea" maxlength="500" placeholder="复核意见" /><div class="review-actions"><el-button type="primary" :loading="saving" @click="stayReview(true)">通过并完成订单</el-button><el-button :loading="saving" @click="stayReview(false)">退回登记</el-button></div></div>
    </template>
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { getBooking, reviewCancel, reviewStay } from '../../api/hotel'

defineOptions({ name: 'HotelAdminBookingDetail' })
const route = useRoute()
const router = useRouter()
const booking = reactive({})
const statusLabel = { reserved: '待支付', paid: '已预订', checked_in: '已入住', checkout_recorded: '已登记离店', no_show_recorded: '未到店待复核', cancel_requested: '取消待审', refund_pending: '退款处理中', refund_failed: '退款失败', refunded: '已取消退款', completed: '已完成', cancelled: '已关单' }
const statusType = value => ({ paid: 'success', checked_in: 'success', completed: 'success', cancel_requested: 'warning', refund_pending: 'warning', refund_failed: 'danger', cancelled: 'info', refunded: 'info' }[value] || 'warning')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const remark = ref('')
const refundAmount = ref(0)
async function fetchBooking() {
  loading.value = true; error.value = ''
  try { const { data } = await getBooking(route.params.id); Object.assign(booking, data); refundAmount.value = Number(data.pay_amount) } catch (failure) { error.value = failure.message || '预订详情加载失败' } finally { loading.value = false }
}
async function cancelReview(approved) {
  if (approved && (!refundAmount.value || Number(refundAmount.value) > Number(booking.pay_amount))) return ElMessage.warning('请输入有效退款金额')
  try { await ElMessageBox.confirm(approved ? '确认将按审核金额发起渠道退款？' : '确认驳回此次取消申请？', '酒店取消审核'); saving.value = true; await reviewCancel(route.params.id, { approved: Number(approved), refund_amount: approved ? refundAmount.value : 0, remark: remark.value }); ElMessage.success('审核已提交'); await fetchBooking() } catch (failure) { if (failure !== 'cancel') ElMessage.error(failure.message || '审核失败') } finally { saving.value = false }
}
async function stayReview(approved) {
  try { await ElMessageBox.confirm(approved ? '确认履约证据并完成宿主订单？' : '确认退回此次登记？', '履约复核'); saving.value = true; await reviewStay(route.params.id, { approved: Number(approved), remark: remark.value }); ElMessage.success('复核已更新'); await fetchBooking() } catch (failure) { if (failure !== 'cancel') ElMessage.error(failure.message || '复核失败') } finally { saving.value = false }
}
onMounted(fetchBooking)
</script>
<style lang="scss" scoped>
.booking-detail { max-width: 1180px; padding: 24px; }.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }.page-title { display: flex; align-items: center; gap: 12px; }h1 { margin: 0; font-size: 22px; }.page-header p { margin: 8px 0 0; color: var(--el-text-color-secondary); font-size: 13px; }h3 { font-size: 16px; margin: 30px 0 16px; border-bottom: 1px solid var(--el-border-color); padding-bottom: 10px; }.review { max-width: 640px; padding: 20px; background: var(--el-fill-color-extra-light); border: 1px solid var(--el-border-color-lighter); border-radius: 6px; }.review-actions { margin-top: 16px; } @media (max-width: 768px) { .booking-detail { padding: 16px; } .page-header { align-items: stretch; flex-direction: column; gap: 14px; } .page-header .el-button { align-self: flex-start; } }
</style>
