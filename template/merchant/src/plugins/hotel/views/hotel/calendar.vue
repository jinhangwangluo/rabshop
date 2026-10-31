<template>
  <div v-loading="loading" class="calendar">
    <div class="heading"><div><h2>{{ hotel.name }} · 房态</h2><span>{{ hotel.address }}</span></div><el-button :icon="ArrowLeft" title="返回" @click="router.back()" /></div>
    <el-form inline @submit.prevent="fetchCalendar"><el-form-item label="房型"><el-select v-model="roomId" placeholder="选择房型" @change="fetchCalendar"><el-option v-for="room in hotel.rooms" :key="room.id" :value="room.id" :label="room.name" /></el-select></el-form-item>
      <el-form-item label="入住"><el-date-picker v-model="start" type="date" value-format="YYYY-MM-DD" :disabled-date="past" /></el-form-item><el-form-item label="退房"><el-date-picker v-model="end" type="date" value-format="YYYY-MM-DD" :disabled-date="past" /></el-form-item><el-button :icon="Search" @click="fetchCalendar">查询</el-button></el-form>
    <el-table :data="nights" row-key="stay_date" empty-text="请选择房型和入住日期"><el-table-column prop="stay_date" label="房晚" width="135" /><el-table-column label="日价" width="115"><template #default="{ row }">¥{{ row.unit_price }}</template></el-table-column>
      <el-table-column prop="stock" label="总间数" width="95" /><el-table-column prop="reserved" label="待支付锁房" width="120" /><el-table-column prop="paid" label="已支付" width="95" /><el-table-column prop="remaining" label="剩余" width="95" />
      <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="row.closed ? 'info' : row.available ? 'success' : 'warning'">{{ row.closed ? '停售' : row.remaining ? '销售中' : '已满房' }}</el-tag></template></el-table-column><el-table-column label="操作" width="100"><template #default="{ row }"><el-button link type="primary" @click="edit(row)">调整</el-button></template></el-table-column></el-table>
    <el-dialog v-model="visible" :title="`${draft.stay_date} 房态`" width="420px" destroy-on-close><el-form label-width="98px" @submit.prevent><el-form-item label="逐日价格"><el-input-number v-model="draft.price" :min="0.01" :max="99999999" :precision="2" class="fill" /></el-form-item><el-form-item label="总间数"><el-input-number v-model="draft.stock" :min="draft.occupied" :max="10000" class="fill" /></el-form-item><el-form-item label="停售"><el-switch v-model="draft.closed" /></el-form-item></el-form><template #footer><el-button @click="visible = false">取消</el-button><el-button type="primary" :loading="saving" @click="save">保存房态</el-button></template></el-dialog>
  </div>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Search } from '@element-plus/icons-vue'
import { getHotel, getCalendar, setCalendarNight } from '../../api/hotel'

defineOptions({ name: 'HotelMerchantCalendar' })
const route = useRoute()
const router = useRouter()
const hotel = reactive({ name: '', address: '', rooms: [] })
const roomId = ref(0)
const today = new Date()
const date = offset => { const value = new Date(today); value.setDate(value.getDate() + offset); return [value.getFullYear(), String(value.getMonth() + 1).padStart(2, '0'), String(value.getDate()).padStart(2, '0')].join('-') }
const start = ref(date(0))
const end = ref(date(7))
const past = value => value.getTime() < new Date(date(0)).getTime()
const nights = ref([])
const loading = ref(false)
const saving = ref(false)
const visible = ref(false)
const draft = reactive({ stay_date: '', price: 0, stock: 0, occupied: 0, closed: false })
async function fetchCalendar() {
  if (!roomId.value || !start.value || !end.value) return
  loading.value = true
  try { const { data } = await getCalendar(route.params.id, { room_type_id: roomId.value, check_in: start.value, check_out: end.value }); nights.value = data?.nights || [] } catch (error) { nights.value = []; ElMessage.error(error.message || '房态加载失败') } finally { loading.value = false }
}
function edit(row) { Object.assign(draft, { stay_date: row.stay_date, price: row.unit_price, stock: row.stock, occupied: Number(row.reserved) + Number(row.paid), closed: !!row.closed }); visible.value = true }
async function save() { saving.value = true; try { await setCalendarNight(route.params.id, { room_type_id: roomId.value, stay_date: draft.stay_date, price: draft.price, stock: draft.stock, closed: Number(draft.closed) }); visible.value = false; ElMessage.success('房态已更新'); await fetchCalendar() } catch (error) { ElMessage.error(error.message || '保存失败') } finally { saving.value = false } }
onMounted(async() => { loading.value = true; try { const { data } = await getHotel(route.params.id); Object.assign(hotel, data); roomId.value = data.rooms?.[0]?.id || 0; await fetchCalendar() } catch (error) { ElMessage.error(error.message || '酒店加载失败') } finally { loading.value = false } })
</script>
<style lang="scss" scoped>
.calendar { padding: 20px; }.heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; h2 { margin: 0; font-size: 20px; } span { color: var(--text-secondary); } }.fill { width: 100%; }
</style>
