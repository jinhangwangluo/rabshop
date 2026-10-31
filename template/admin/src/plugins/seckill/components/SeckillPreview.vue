<template>
  <div class="seckill-preview" :style="componentStyle">
    <div class="preview-heading"><strong>{{ config.title || '限时秒杀' }}</strong><span v-if="countdown" class="preview-countdown">{{ countdown }}</span><span v-if="showMore" class="preview-more">更多 <el-icon><ArrowRight /></el-icon></span></div>
    <div v-if="loading" class="preview-state"><el-icon class="is-loading"><Loading /></el-icon><span>加载中</span></div>
    <div v-else-if="error" class="preview-state"><span>{{ error }}</span><el-button text type="primary" @click.stop="fetchProducts">重试</el-button></div>
    <div v-else-if="!products.length" class="preview-state"><el-icon><Goods /></el-icon><span>暂无秒杀商品</span></div>
    <div v-else class="preview-products" :class="{ 'preview-products--grid': config.layout !== 'list' }">
      <article v-for="product in displayProducts" :key="product.id" class="preview-product" :class="{ 'preview-product--grid': config.layout !== 'list' }">
        <div class="preview-cover" :style="imageStyle"><img v-if="product.image" :src="product.image" :alt="product.store_name"><el-icon v-else><Picture /></el-icon></div>
        <div class="preview-body"><div class="preview-name"><span class="preview-tag">秒杀</span>{{ product.store_name }}</div><div class="preview-limit">每场限购 {{ product.user_limit }} 件</div><div class="preview-prices"><strong>¥{{ money(product.seckill_price) }}</strong><del>¥{{ money(product.original_price) }}</del></div><div class="preview-footer"><span>已抢 {{ product.sold }} 件</span><span class="preview-buy" :class="{ 'preview-buy--disabled': product.disabled }">{{ product.buttonText }}</span></div></div>
      </article>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { ArrowRight, Goods, Loading, Picture } from '@element-plus/icons-vue'
import { getDiyProducts } from '../api/seckill'
import { getImageRadiusStyle } from '@/components/mobilePage/imageRadius'

defineOptions({ name: 'SeckillPreview' })
const props = defineProps({ config: { type: Object, default: () => ({}) }})
const products = ref([])
const session = ref({})
const loading = ref(false)
const error = ref('')
const now = ref(Math.floor(Date.now() / 1000))
const offset = ref(0)
const displayProducts = computed(() => products.value.map(product => {
  const state = now.value < Number(product.start_time) ? 'upcoming' : now.value >= Number(product.end_time) ? 'ended' : 'active'
  return { ...product, disabled: state !== 'active' || Number(product.remaining) <= 0, buttonText: state === 'upcoming' ? '未开始' : state === 'ended' ? '已结束' : Number(product.remaining) <= 0 ? '已抢光' : '去抢购' }
}))
const componentStyle = computed(() => props.config.bg_color ? { background: props.config.bg_color } : {})
const imageStyle = computed(() => {
  const config = props.config.style_config || props.config
  const style = getImageRadiusStyle(config)
  if (Object.prototype.hasOwnProperty.call(config, 'image_radius') && Number(config.image_radius) === 0 && config.image_radius_mode !== 'each') style.borderRadius = '0px'
  return style
})
const showMore = computed(() => props.config.show_more !== false && Number(props.config.show_more) !== 0)
const countdown = computed(() => {
  if (!session.value.id) return ''
  const end = Number(session.value.end_time)
  if (now.value >= end) return '已结束'
  const start = Number(session.value.start_time)
  const seconds = Math.max(0, Math.floor((now.value < start ? start : end) - now.value))
  return (now.value < start ? '距开场 ' : '距结束 ') + [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60].map(value => String(value).padStart(2, '0')).join(':')
})
let sequence = 0
const clock = setInterval(() => { now.value = Math.floor(Date.now() / 1000) + offset.value }, 1000)

function money(value) { return Number(value || 0).toFixed(2) }
async function fetchProducts() {
  const current = ++sequence
  loading.value = false
  error.value = ''
  products.value = []
  session.value = {}
  if (!Number(props.config.activity_id) || props.config.session_mode === 'fixed' && !Number(props.config.session_id)) { products.value = []; session.value = {}; return }
  loading.value = true
  error.value = ''
  try {
    const ids = props.config.selection_mode === 'manual' && Array.isArray(props.config.product_ids) ? props.config.product_ids : []
    if (props.config.selection_mode === 'manual' && !ids.length) { products.value = []; session.value = {}; return }
    const { data } = await getDiyProducts({ activity_id: Number(props.config.activity_id), install_generation: props.config.install_generation, session_id: Number(props.config.session_id || 0), limit: Number(props.config.show_count) || 4, ...(ids.length ? { product_ids: ids.join(',') } : {}) })
    if (current !== sequence) return
    products.value = data.list
    session.value = data.session || {}
    if (Number(data.server_time) > 0) { offset.value = Number(data.server_time) - Math.floor(Date.now() / 1000); now.value = Math.floor(Date.now() / 1000) + offset.value }
  } catch (failure) { if (current === sequence) { products.value = []; error.value = failure?.message || '秒杀预览加载失败' } } finally { if (current === sequence) loading.value = false }
}
watch(() => [props.config.activity_id, props.config.install_generation, props.config.session_id, props.config.session_mode, props.config.show_count, props.config.selection_mode, props.config.product_ids], fetchProducts, { immediate: true, deep: true })
onBeforeUnmount(() => { sequence += 1; clearInterval(clock) })
</script>

<style scoped lang="scss">
.seckill-preview { background: var(--surface-card, #fff); }
.preview-heading { display: flex; align-items: center; flex-wrap: wrap; padding: 9px 0; }
.preview-heading > strong { font-size: 16px; line-height: 22px; color: var(--text-title, #18181b); }
.preview-countdown { color: var(--brand-primary, #ea580c); background: var(--brand-primary-pale, #fff7ed); margin-left: 7px; font-size: 10px; line-height: 15px; border-radius: 3px; padding: 1px 4px; }
.preview-more { display: flex; align-items: center; margin-left: auto; color: var(--text-secondary, #71717a); font-size: 11px; }
.preview-state { display: flex; align-items: center; justify-content: center; min-height: 80px; color: var(--text-secondary, #71717a); font-size: 12px; text-align: center; }
.preview-state span { margin-left: 7px; }
.preview-products--grid { display: flex; flex-wrap: wrap; margin: 0 -5px; }
.preview-product { display: flex; padding: 12px 0; border-bottom: 1px solid var(--border-light, #f4f4f5); }
.preview-cover { flex-shrink: 0; width: 110px; height: 110px; border-radius: 6px; overflow: hidden; background: var(--surface-card-muted, #f3f4f6); display: flex; align-items: center; justify-content: center; }
.preview-cover img { width: 100%; height: 100%; object-fit: cover; }
.preview-body { flex: 1; min-width: 0; display: flex; flex-direction: column; margin-left: 12px; }
.preview-name { font-size: 14px; line-height: 19px; height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; font-weight: 600; color: var(--text-title, #18181b); }
.preview-tag { color: var(--brand-primary, #ea580c); background: var(--brand-primary-pale, #fff7ed); font-size: 10px; padding: 1px 4px; margin-right: 4px; border-radius: 2px; }
.preview-limit { color: var(--text-secondary, #71717a); font-size: 10px; line-height: 14px; margin-top: 3px; }
.preview-prices { display: flex; align-items: baseline; flex-wrap: wrap; margin-top: auto; }
.preview-prices strong { color: var(--brand-primary, #ea580c); font-size: 19px; line-height: 23px; }
.preview-prices del { color: var(--text-placeholder, #a1a1aa); font-size: 10px; margin-left: 6px; }
.preview-footer { display: flex; align-items: center; justify-content: space-between; color: var(--text-secondary, #71717a); font-size: 9px; margin-top: 5px; }
.preview-buy { display: flex; align-items: center; justify-content: center; width: 60px; height: 26px; border-radius: 4px; font-size: 12px; color: var(--text-inverse, #fff); background: var(--brand-primary, #ea580c); }
.preview-buy--disabled { color: var(--text-secondary, #71717a); background: var(--surface-card-muted, #f3f4f6); }
.preview-product--grid { display: block; width: 50%; padding: 0 5px; border-bottom: 0; box-sizing: border-box; }
.preview-product--grid .preview-cover { width: 100%; height: 160px; }
.preview-product--grid .preview-body { margin: 0; padding: 8px 0; }
.preview-product--grid .preview-name { font-size: 13px; }
.preview-product--grid .preview-prices strong { font-size: 17px; }
.preview-product--grid .preview-buy { width: 48px; height: 24px; font-size: 11px; }
</style>
