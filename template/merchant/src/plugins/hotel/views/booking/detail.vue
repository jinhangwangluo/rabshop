<template>
  <div v-loading="loading" class="booking-detail"><div class="heading"><div><h2>预订 {{ booking.order_sn }}</h2><el-tag>{{ statusLabel[booking.status] || booking.status }}</el-tag></div><el-button :icon="ArrowLeft" title="返回" @click="router.back()" /></div>
    <el-alert v-if="error" type="error" :title="error" :closable="false" show-icon />
    <template v-if="booking.id"><h3>订单与入住</h3><el-descriptions :column="2" border>
      <el-descriptions-item label="酒店">{{ booking.property_snapshot_json?.hotel_name }}</el-descriptions-item><el-descriptions-item label="房型">{{ booking.property_snapshot_json?.room_name }}</el-descriptions-item>
      <el-descriptions-item label="入住 / 退房">{{ booking.check_in }} 至 {{ booking.check_out }}</el-descriptions-item><el-descriptions-item label="间数">{{ booking.room_count }}</el-descriptions-item><el-descriptions-item label="入住人">{{ booking.guest_snapshot_json?.guests?.map(item => item.name).join('、') }}</el-descriptions-item><el-descriptions-item label="联系电话">{{ booking.guest_snapshot_json?.contact_phone }}</el-descriptions-item>
      <el-descriptions-item label="实付">¥{{ booking.pay_amount }}</el-descriptions-item><el-descriptions-item label="免费取消截止">{{ booking.cancel_deadline || '不可免费取消' }}</el-descriptions-item>
    </el-descriptions>
      <h3>逐晚成交价格</h3><el-table :data="booking.nights || []" row-key="id"><el-table-column prop="stay_date" label="房晚" /><el-table-column prop="room_count" label="间数" /><el-table-column label="每间价格"><template #default="{ row }">¥{{ row.unit_price }}</template></el-table-column><el-table-column label="房晚金额"><template #default="{ row }">¥{{ row.amount }}</template></el-table-column><el-table-column prop="state" label="房态证据" /></el-table>
      <h3>操作与售后进度</h3><el-timeline v-if="booking.actions?.length"><el-timeline-item v-for="action in booking.actions" :key="action.id" :timestamp="action.create_time" placement="top">{{ action.action }} · {{ action.from_status }} → {{ action.to_status }}<span v-if="action.remark"> · {{ action.remark }}</span></el-timeline-item></el-timeline><el-empty v-else description="暂无操作记录" :image-size="72" />
      <div v-if="['paid', 'checked_in'].includes(booking.status)" class="stay"><h3>履约登记</h3><el-input v-model="remark" type="textarea" maxlength="500" placeholder="登记备注" /><div class="actions"><el-button v-if="booking.status === 'paid'" type="primary" :loading="saving" @click="submit('checkin')">登记入住</el-button><el-button v-if="booking.status === 'checked_in'" type="primary" :loading="saving" @click="submit('checkout')">登记离店</el-button><el-button v-if="booking.status === 'paid'" :loading="saving" @click="submit('no_show')">登记未到店</el-button></div></div>
    </template>
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { getBooking, recordStay } from '../../api/hotel'

defineOptions({ name: 'HotelMerchantBookingDetail' })
const route = useRoute()
const router = useRouter()
const booking = reactive({})
const statusLabel = { reserved: '待支付', paid: '已预订', checked_in: '已入住', checkout_recorded: '已登记离店', no_show_recorded: '未到店待复核', cancel_requested: '取消待审', refund_pending: '退款处理中', refund_failed: '退款失败', refunded: '已取消退款', completed: '已完成', cancelled: '已关单' }
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const remark = ref('')
async function fetchBooking() { loading.value = true; error.value = ''; try { const { data } = await getBooking(route.params.id); Object.assign(booking, data) } catch (failure) { error.value = failure.message || '预订详情加载失败' } finally { loading.value = false } }
async function submit(action) { try { await ElMessageBox.confirm(`确认${action === 'checkin' ? '入住' : action === 'checkout' ? '离店' : '未到店'}登记？`, '履约登记'); saving.value = true; await recordStay(route.params.id, { action, remark: remark.value }); ElMessage.success('登记已提交，待平台复核'); await fetchBooking() } catch (failure) { if (failure !== 'cancel') ElMessage.error(failure.message || '登记失败') } finally { saving.value = false } }
onMounted(fetchBooking)
</script>
<style lang="scss" scoped>
.booking-detail { padding: 20px; max-width: 1100px; }.heading { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; .el-tag { margin-left: 14px; } h2 { display: inline; font-size: 20px; } }h3 { font-size: 16px; margin: 30px 0 16px; padding-bottom: 10px; border-bottom: 1px solid var(--el-border-color); }.stay { max-width: 640px; }.actions { margin-top: 16px; }
</style>
