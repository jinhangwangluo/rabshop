<template>
  <div class="seckill-editor">
    <el-alert v-if="error" :title="error" type="error" :closable="false" show-icon />
    <el-form label-position="top" size="small">
      <el-form-item label="标题"><el-input v-model="local.title" maxlength="20" /></el-form-item>
      <el-form-item label="秒杀活动"><el-select v-model="local.activity_id" filterable remote :remote-method="fetchActivities" :loading="activityLoading" placeholder="选择已发布活动" clearable @change="handleActivityChange"><el-option v-for="activity in activities" :key="activity.id" :label="activity.title" :value="activity.id" /></el-select></el-form-item>
      <template v-if="local.activity_id">
        <el-form-item label="展示场次"><el-radio-group v-model="local.session_mode" @change="handleModeChange"><el-radio-button value="auto">自动场次</el-radio-button><el-radio-button value="fixed">指定场次</el-radio-button></el-radio-group></el-form-item>
        <template v-if="local.session_mode === 'fixed'">
          <el-form-item label="场次日期"><el-date-picker v-model="local.session_date" value-format="YYYY-MM-DD" :disabled-date="disabledDate" @change="handleDateChange" /></el-form-item>
          <el-form-item label="场次"><el-select v-model="local.session_id" :loading="sessionLoading" placeholder="选择场次"><el-option v-for="session in sessionOptions" :key="session.id" :label="session.label" :value="session.id" /></el-select></el-form-item>
        </template>
        <el-form-item label="商品来源"><el-radio-group v-model="local.selection_mode"><el-radio-button value="all">活动商品</el-radio-button><el-radio-button value="manual">自选商品</el-radio-button></el-radio-group></el-form-item>
        <el-form-item v-if="local.selection_mode === 'manual'" label="选择活动商品"><el-select v-model="local.product_ids" multiple filterable :loading="detailLoading" placeholder="选择商品"><el-option v-for="product in activityProducts" :key="product.id" :label="product.store_name" :value="product.id" /></el-select></el-form-item>
      </template>
      <el-form-item label="展示数量"><el-input-number v-model="local.show_count" :min="1" :max="50" :precision="0" /></el-form-item>
      <el-form-item label="商品排列"><el-radio-group v-model="local.layout"><el-radio-button value="list">一排一个</el-radio-button><el-radio-button value="grid">一排两个</el-radio-button></el-radio-group></el-form-item>
      <el-form-item label="更多入口"><el-switch v-model="local.show_more" /></el-form-item>
    </el-form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { getActivityDetail, getActivityList, getSessions } from '../api/seckill'

defineOptions({ name: 'SeckillEditor' })
const props = defineProps({ config: { type: Object, default: () => ({}) }})
const emit = defineEmits(['change'])
const local = reactive(normalizeConfig(props.config))
const activities = ref([])
const activityProducts = ref([])
const activityRange = ref([])
const activityLoading = ref(false)
const detailLoading = ref(false)
const sessionLoading = ref(false)
const sessions = ref([])
const error = ref('')
const sessionOptions = computed(() => sessions.value.map(session => ({ ...session, label: timeLabel(session.start_time) + '–' + timeLabel(session.end_time) })))
let activitySequence = 0
let detailSequence = 0
let sessionSequence = 0
let fromProps = false

function normalizeConfig(config) {
  return { ...config, title: config.title ?? '限时秒杀', activity_id: Number(config.activity_id || 0), install_generation: String(config.install_generation || ''), session_mode: config.session_mode === 'fixed' ? 'fixed' : 'auto', session_date: config.session_date || '', session_id: Number(config.session_id || 0), selection_mode: config.selection_mode === 'manual' ? 'manual' : 'all', product_ids: Array.isArray(config.product_ids) ? config.product_ids.map(Number).filter(id => id > 0) : [], show_count: Math.max(1, Math.min(50, Number(config.show_count) || 4)), layout: config.layout === 'list' ? 'list' : 'grid', show_more: config.show_more !== false && Number(config.show_more) !== 0 }
}
function timeLabel(value) { const date = new Date(Number(value) * 1000); return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') }
async function fetchActivities(keyword = '') {
  const current = ++activitySequence
  activityLoading.value = true
  try { const { data } = await getActivityList({ q: keyword, status: 1, audit_status: 1, page: 1, limit: 50 }); if (current === activitySequence) activities.value = data.list } catch (failure) { if (current === activitySequence) error.value = failure?.message || '活动加载失败' } finally { if (current === activitySequence) activityLoading.value = false }
}
async function fetchActivity() {
  const current = ++detailSequence
  sessionSequence += 1
  sessions.value = []
  sessionLoading.value = false
  detailLoading.value = false
  error.value = ''
  activityProducts.value = []
  activityRange.value = []
  if (!local.activity_id) return
  detailLoading.value = true
  error.value = ''
  try {
    const { data } = await getActivityDetail(local.activity_id, local.install_generation)
    if (current !== detailSequence) return
    applyActivity(data)
  } catch (failure) { if (current === detailSequence) error.value = failure?.message || '活动商品加载失败' } finally { if (current === detailSequence) detailLoading.value = false }
}
function applyActivity(data) {
  activityProducts.value = data.products
  activityRange.value = [data.start_date, data.end_date]
  local.install_generation = data.install_generation
  if (!activities.value.some(activity => activity.id === data.id)) activities.value.push({ id: data.id, title: data.title })
  if (!local.session_date || local.session_date < data.start_date || local.session_date > data.end_date) local.session_date = data.start_date
  if (local.session_mode === 'fixed') fetchSessions()
}
async function fetchSessions() {
  const current = ++sessionSequence
  sessions.value = []
  sessionLoading.value = false
  error.value = ''
  if (!local.activity_id || !local.session_date) return
  sessionLoading.value = true
  error.value = ''
  try { const { data } = await getSessions({ activity_id: local.activity_id, install_generation: local.install_generation, date: local.session_date }); if (current === sessionSequence) sessions.value = data.list } catch (failure) { if (current === sessionSequence) { sessions.value = []; error.value = failure?.message || '场次加载失败' } } finally { if (current === sessionSequence) sessionLoading.value = false }
}
function handleActivityChange() { local.session_id = 0; local.session_mode = 'auto'; local.session_date = ''; local.product_ids = []; local.install_generation = ''; sessions.value = [] }
function handleModeChange() { local.session_id = 0; sessionSequence += 1; sessions.value = []; sessionLoading.value = false; if (local.session_mode === 'fixed') fetchSessions() }
function handleDateChange() { local.session_id = 0; fetchSessions() }
function disabledDate(date) {
  if (activityRange.value.length !== 2) return true
  const value = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0')
  return value < activityRange.value[0] || value > activityRange.value[1]
}
watch(() => props.config, config => {
  const normalized = normalizeConfig(config)
  if (JSON.stringify(normalized) === JSON.stringify(local)) return
  fromProps = true
  Object.assign(local, normalized)
  fromProps = false
}, { deep: true })
watch(local, () => {
  if (fromProps) return
  const config = { ...props.config, ...local, session_id: local.session_mode === 'fixed' ? Number(local.session_id) : 0, product_ids: local.selection_mode === 'manual' ? [...local.product_ids] : [] }
  if (JSON.stringify(config) !== JSON.stringify(props.config)) emit('change', config)
}, { deep: true })
watch(() => local.activity_id, fetchActivity, { immediate: true })
onMounted(() => fetchActivities())
</script>

<style scoped lang="scss">
.seckill-editor :deep(.el-select), .seckill-editor :deep(.el-date-editor) { width: 100%; }
.seckill-editor .el-alert { margin-bottom: 18px; }
.seckill-editor :deep(.el-form-item) { margin-bottom: 22px; }
</style>
