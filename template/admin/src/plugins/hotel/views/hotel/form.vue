<template>
  <div v-loading="loading" class="hotel-form">
    <header class="page-header"><div><h1>{{ editing ? '编辑酒店' : '新增酒店' }}</h1><p>完善酒店基础资料、房型库存和预订规则。</p></div><el-button :icon="ArrowLeft" @click="router.back()">返回</el-button></header>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="118px" class="form-surface" @submit.prevent>
      <h3>酒店信息</h3>
      <el-row :gutter="20"><el-col :md="12" :xs="24"><el-form-item label="酒店名称" prop="name"><el-input v-model="form.name" maxlength="100" show-word-limit /></el-form-item></el-col>
        <el-col :md="12" :xs="24"><el-form-item label="联系电话" prop="contact_phone"><el-input v-model="form.contact_phone" maxlength="32" /></el-form-item></el-col></el-row>
      <el-form-item label="封面" prop="cover"><Upload v-model="form.cover" :limit="1" /></el-form-item>
      <el-form-item label="酒店图片"><Upload v-model="form.pics" :limit="12" /></el-form-item>
      <el-form-item label="简介"><el-input v-model="form.description" type="textarea" :rows="2" maxlength="255" /></el-form-item>
      <el-row :gutter="20"><el-col :md="12" :xs="24"><el-form-item label="所在地区" prop="area"><el-cascader v-model="form.area" :options="areas" :props="{ checkStrictly: false }" clearable class="fill" /></el-form-item></el-col>
        <el-col :md="12" :xs="24"><el-form-item label="详细地址" prop="address"><el-input v-model="form.address" maxlength="255" /></el-form-item></el-col></el-row>
      <el-row :gutter="20"><el-col :md="12" :xs="24"><el-form-item label="经度"><el-input-number v-model="form.longitude" :min="-180" :max="180" :precision="7" controls-position="right" class="fill" /></el-form-item></el-col>
        <el-col :md="12" :xs="24"><el-form-item label="纬度"><el-input-number v-model="form.latitude" :min="-90" :max="90" :precision="7" controls-position="right" class="fill" /></el-form-item></el-col></el-row>
      <el-row :gutter="20"><el-col :md="12" :xs="24"><el-form-item label="入住时间" prop="check_in_time"><el-time-select v-model="form.check_in_time" start="00:00" step="00:30" end="23:30" class="fill" /></el-form-item></el-col>
        <el-col :md="12" :xs="24"><el-form-item label="退房时间" prop="check_out_time"><el-time-select v-model="form.check_out_time" start="00:00" step="00:30" end="23:30" class="fill" /></el-form-item></el-col></el-row>
      <el-form-item label="设施"><el-input-tag v-model="form.facilities" clearable placeholder="输入设施后回车" /></el-form-item>
      <div class="section-heading"><h3>房型与预订规则</h3><el-button :icon="Plus" @click="addRoom">新增房型</el-button></div>
      <div v-for="(room, index) in form.rooms" :key="room.key" class="room-block">
        <div class="room-heading"><strong>房型 {{ index + 1 }}</strong><el-button :icon="Delete" text type="danger" title="移除房型" @click="removeRoom(index)" /></div>
        <el-row :gutter="20"><el-col :md="12" :xs="24"><el-form-item label="房型编码" :prop="`rooms.${index}.room_code`" :rules="required"><el-input v-model="room.room_code" maxlength="64" /></el-form-item></el-col>
          <el-col :md="12" :xs="24"><el-form-item label="房型名称" :prop="`rooms.${index}.name`" :rules="required"><el-input v-model="room.name" maxlength="100" /></el-form-item></el-col></el-row>
        <el-form-item label="房型图片"><Upload v-model="room.photos" :limit="12" /></el-form-item>
        <el-row :gutter="20"><el-col :md="8" :xs="24"><el-form-item label="床型" :prop="`rooms.${index}.bed_info`" :rules="required"><el-input v-model="room.bed_info" maxlength="80" /></el-form-item></el-col>
          <el-col :md="8" :xs="24"><el-form-item label="面积 (m²)" :prop="`rooms.${index}.area_sqm`" :rules="areaRules"><el-input-number v-model="room.area_sqm" :min="0.01" :max="9999" :precision="2" class="fill" /></el-form-item></el-col>
          <el-col :md="8" :xs="24"><el-form-item label="可住人数"><el-input-number v-model="room.max_guests" :min="1" :max="20" class="fill" /></el-form-item></el-col></el-row>
        <el-row :gutter="20"><el-col :md="8" :xs="24"><el-form-item label="基础价 / 晚"><el-input-number v-model="room.base_price" :min="0.01" :max="99999999" :precision="2" class="fill" /></el-form-item></el-col>
          <el-col :md="8" :xs="24"><el-form-item label="基础间数"><el-input-number v-model="room.base_stock" :min="0" :max="10000" class="fill" /></el-form-item></el-col>
          <el-col :md="8" :xs="24"><el-form-item label="免费取消提前"><el-input-number v-model="room.cancel_before_hours" :min="0" :max="8760" class="fill" /><small class="hint">小时；0 表示不可免费取消</small></el-form-item></el-col></el-row>
        <el-row :gutter="20"><el-col :md="8" :xs="24"><el-form-item label="早餐"><el-switch v-model="room.breakfast" /></el-form-item></el-col>
          <el-col :md="8" :xs="24"><el-form-item label="房型在售"><el-switch v-model="room.status" /></el-form-item></el-col></el-row>
      </div>
      <el-empty v-if="!form.rooms.length" description="请新增至少一个房型" />
      <div class="actions"><el-button @click="router.back()">取消</el-button><el-button type="primary" :loading="saving" :icon="Check" @click="save">保存酒店</el-button></div>
    </el-form>
  </div>
</template>
<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, Check, Delete, Plus } from '@element-plus/icons-vue'
import Upload from '@/components/Upload/index.vue'
import { getCityTree } from '@/api/product'
import { getHotel, saveHotel } from '../../api/hotel'

defineOptions({ name: 'HotelAdminForm' })
const route = useRoute()
const router = useRouter()
const editing = computed(() => !!route.params.id)
const formRef = ref(null)
const loading = ref(false)
const saving = ref(false)
const areas = ref([])
let nextKey = 0
const roomDefaults = () => ({ key: ++nextKey, id: 0, room_code: '', name: '', photos: [], bed_info: '', area_sqm: 0, max_guests: 2, breakfast: false, base_price: 0.01, base_stock: 1, cancel_before_hours: 0, status: true })
const form = reactive({ name: '', description: '', cover: '', pics: [], contact_phone: '', area: [], address: '', longitude: null, latitude: null, check_in_time: '14:00', check_out_time: '12:00', facilities: [], rooms: [roomDefaults()] })
const required = [{ required: true, message: '请填写此项', trigger: 'blur' }]
const areaRules = [{ required: true, message: '请输入面积', trigger: 'change' }, { type: 'number', min: 0.01, message: '面积必须大于 0', trigger: 'change' }]
const rules = { name: required, cover: required, contact_phone: required, address: required, area: required, check_in_time: required, check_out_time: required }
function addRoom() { if (form.rooms.length < 30) form.rooms.push(roomDefaults()) }
function removeRoom(index) { form.rooms.splice(index, 1) }
async function load() {
  loading.value = true
  try {
    const tree = await getCityTree()
    areas.value = tree.data || []
    if (editing.value) {
      const { data } = await getHotel(route.params.id)
      Object.assign(form, { name: data.name, description: data.description || '', cover: data.cover, pics: data.pics || [], contact_phone: data.contact_phone, area: [], address: data.address, longitude: data.longitude, latitude: data.latitude, check_in_time: String(data.check_in_time).slice(0, 5), check_out_time: String(data.check_out_time).slice(0, 5), facilities: data.facilities || [], rooms: (data.rooms || []).map(room => ({ ...roomDefaults(), ...room, photos: room.photos || [], breakfast: !!room.breakfast, status: !!room.status })) })
      // 地区树的完整路径通过节点 ID 恢复，不能把区县 ID 直接当作级联选择值。
      const findPath = (nodes, target, path = []) => { for (const node of nodes) { const current = [...path, node.value]; if (Number(node.value) === Number(target)) return current; const child = findPath(node.children || [], target, current); if (child) return child } return null }
      form.area = findPath(areas.value, data.district_id) || []
    }
  } catch (error) { ElMessage.error(error.message || '酒店资料加载失败') } finally { loading.value = false }
}
async function save() {
  if (!form.rooms.length) return ElMessage.warning('至少录入一个房型')
  if (new Set(form.rooms.map(room => room.room_code.trim())).size !== form.rooms.length) return ElMessage.warning('房型编码不能重复')
  try {
    await formRef.value.validate()
    saving.value = true
    const area = form.area || []
    const data = { name: form.name, description: form.description, cover: form.cover, pics: form.pics, city_id: Number(area[area.length - 2] || 0), district_id: Number(area[area.length - 1] || 0), address: form.address, longitude: form.longitude, latitude: form.latitude, contact_phone: form.contact_phone, check_in_time: form.check_in_time, check_out_time: form.check_out_time, facilities: form.facilities, rooms: form.rooms.map(({ key, ...room }) => ({ ...room, breakfast: Number(room.breakfast), status: Number(room.status) })) }
    if (!data.city_id || !data.district_id) return ElMessage.warning('请选择市和区县')
    await saveHotel(data, route.params.id)
    ElMessage.success('酒店已保存')
    router.push({ name: 'hotelAdminHotels' })
  } catch (error) { if (error !== false) ElMessage.error(error.message || '保存失败') } finally { saving.value = false }
}
onMounted(load)
</script>
<style lang="scss" scoped>
.hotel-form { max-width: 1180px; margin: 0 auto; padding: 24px; }
.page-header, .section-heading, .room-heading, .actions { display: flex; align-items: center; justify-content: space-between; }
.page-header { margin-bottom: 20px; h1 { margin: 0; font-size: 22px; } p { margin: 8px 0 0; color: var(--el-text-color-secondary); font-size: 13px; } }
.form-surface { padding: 24px; background: var(--el-bg-color); border: 1px solid var(--el-border-color-lighter); border-radius: 8px; }
h3 { font-size: 16px; margin: 8px 0 20px; padding-bottom: 10px; border-bottom: 1px solid var(--el-border-color); }
.section-heading h3 { flex: 1; margin-right: 20px; }
.room-block { margin-top: 18px; padding: 18px 20px 4px; background: var(--el-fill-color-extra-light); border: 1px solid var(--el-border-color-lighter); border-radius: 6px; }
.room-heading { padding-left: 12px; margin-bottom: 14px; }
.fill { width: 100%; } .hint { display: block; color: var(--el-text-color-secondary); font-size: 12px; }
.actions { justify-content: flex-end; margin: 25px 0; > * + * { margin-left: 10px; } }
@media (max-width: 768px) { .hotel-form { padding: 16px; } .form-surface { padding: 16px; } .page-header { align-items: stretch; flex-direction: column; gap: 14px; } .page-header .el-button { align-self: flex-start; } }
</style>
