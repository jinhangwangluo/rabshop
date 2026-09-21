<template>
  <div class="plugin-detail">
    <div v-if="detailLoading" class="plugin-detail__skeleton">
      <el-skeleton :rows="8" animated />
    </div>

    <template v-else>
      <!-- 面包屑 -->
      <nav class="plugin-detail__breadcrumb">
        <span class="plugin-detail__crumb">首页</span>
        <span class="plugin-detail__crumb-sep">›</span>
        <button type="button" class="plugin-detail__crumb-link" @click="emit('back')">
          插件市场
        </button>
        <span class="plugin-detail__crumb-sep">›</span>
        <span class="plugin-detail__crumb plugin-detail__crumb--current">{{ pluginName }}</span>
      </nav>

      <!-- 顶部概览:左介绍 + 右购买 -->
      <section class="plugin-detail__hero">
        <div class="plugin-detail__hero-main">
          <div class="plugin-detail__title-row">
            <div class="plugin-detail__icon" :style="iconStyle">
              <span class="plugin-detail__icon-char">{{ pluginIconChar }}</span>
            </div>
            <div class="plugin-detail__title-text">
              <div class="plugin-detail__title-line">
                <h1 class="plugin-detail__name">{{ pluginName }}</h1>
                <el-tag v-if="pluginIsFeature" effect="plain" size="small" type="primary">精选</el-tag>
                <el-tag v-if="pluginIsOfficial" effect="plain" size="small" type="warning">官方</el-tag>
              </div>
              <p class="plugin-detail__summary">{{ pluginSummary }}</p>
              <div class="plugin-detail__meta">
                <el-icon class="plugin-detail__star"><StarFilled /></el-icon>
                <span class="plugin-detail__score">{{ ratingText }}</span>
                <span class="plugin-detail__reviews">({{ reviewsText }})</span>
                <span class="plugin-detail__meta-divider">|</span>
                <span class="plugin-detail__platforms">{{ surfaceLabels }}</span>
              </div>
            </div>
          </div>

          <div v-if="sceneTags.length" class="plugin-detail__tags">
            <el-tag
              v-for="tag in sceneTags"
              :key="tag"
              class="plugin-detail__tag"
              effect="plain"
              size="small"
            >
              {{ tag }}
            </el-tag>
          </div>
        </div>

        <div class="plugin-detail__purchase">
          <div v-if="skus.length" class="plugin-detail__plans">
            <button
              v-for="sku in skus"
              :key="sku.id"
              type="button"
              class="plugin-detail__plan"
              :class="{ 'is-active': selectedSkuId === sku.id }"
              @click="selectedSkuId = sku.id"
            >
              <span class="plugin-detail__plan-label">{{ sku.billingLabel }}</span>
              <span class="plugin-detail__plan-price">{{ sku.priceLabel }}</span>
            </button>
          </div>

          <el-button
            class="plugin-detail__buy-button"
            type="primary"
            @click="handlePrimaryAction"
          >
            {{ primaryLabel }}
          </el-button>

          <el-button class="plugin-detail__favorite-button" plain @click="handleFavorite">
            <el-icon><Star /></el-icon>
            加入收藏
          </el-button>
        </div>
      </section>

      <!-- 主体:左详情 + 右信息栏 -->
      <div class="plugin-detail__body">
        <section class="plugin-detail__card plugin-detail__aside-main">
          <div class="plugin-detail__tabs">
            <button
              v-for="tab in detailTabs"
              :key="tab.key"
              type="button"
              class="plugin-detail__tab"
              :class="{ 'is-active': activeTab === tab.key }"
              @click="activeTab = tab.key"
            >
              {{ tab.label }}
            </button>
          </div>

          <div class="plugin-detail__panel">
            <!-- 插件介绍 -->
            <template v-if="activeTab === 'intro'">
              <div v-if="pluginSummary" class="plugin-detail__section">
                <h3 class="plugin-detail__section-title">插件简介</h3>
                <p class="plugin-detail__paragraph">{{ pluginSummary }}</p>
              </div>

              <div v-if="featureHighlights.length" class="plugin-detail__section">
                <h3 class="plugin-detail__section-title">核心功能</h3>
                <div class="plugin-detail__features">
                  <div
                    v-for="feature in featureHighlights"
                    :key="feature.title"
                    class="plugin-detail__feature-card"
                  >
                    <span class="plugin-detail__feature-icon" :style="featureIconStyle(feature)">
                      <el-icon><MagicStick /></el-icon>
                    </span>
                    <div class="plugin-detail__feature-text">
                      <div class="plugin-detail__feature-title">{{ feature.title }}</div>
                      <p class="plugin-detail__feature-desc">{{ feature.description }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="sceneTags.length" class="plugin-detail__section">
                <h3 class="plugin-detail__section-title">适用场景</h3>
                <div class="plugin-detail__scenes">
                  <el-tag
                    v-for="tag in sceneTags"
                    :key="tag"
                    class="plugin-detail__scene"
                    effect="plain"
                  >
                    {{ tag }}
                  </el-tag>
                </div>
              </div>

              <el-empty v-if="isEmptyIntro" description="暂无插件介绍" />
            </template>

            <!-- 更新日志 -->
            <template v-else>
              <div v-if="updateLogs.length" class="plugin-detail__section">
                <h3 class="plugin-detail__section-title">更新日志</h3>
                <div class="plugin-detail__logs">
                  <div
                    v-for="log in updateLogs"
                    :key="`${log.version}-${log.publishedAt}`"
                    class="plugin-detail__log"
                  >
                    <div class="plugin-detail__log-head">
                      <span class="plugin-detail__log-version">{{ log.version }}</span>
                      <span class="plugin-detail__log-date">{{ formatDate(log.publishedAt) }}</span>
                    </div>
                    <p class="plugin-detail__log-summary">
                      {{ log.summary || '暂无更新说明' }}
                    </p>
                  </div>
                </div>
              </div>
              <el-empty v-else description="暂无更新日志" />
            </template>
          </div>
        </section>

        <aside class="plugin-detail__side">
          <section class="plugin-detail__card">
            <h3 class="plugin-detail__side-title">插件信息</h3>
            <dl class="plugin-detail__info">
              <div
                v-for="row in infoRows"
                :key="row.label"
                class="plugin-detail__info-row"
              >
                <dt class="plugin-detail__info-label">{{ row.label }}</dt>
                <dd class="plugin-detail__info-value">{{ row.value || '-' }}</dd>
              </div>
            </dl>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { MagicStick, Star, StarFilled } from '@element-plus/icons-vue'
import { getPluginCatalogDetail } from '@/api/plugin'

defineOptions({
  name: 'PluginDetail'
})

const props = defineProps({
  plugin: {
    type: Object,
    default: () => ({})
  },
  hasLicense: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['back', 'buy', 'install'])

const detailLoading = ref(true)
const activeTab = ref('intro')
const selectedSkuId = ref(0)
const detail = ref(null)

const detailTabs = [
  { key: 'intro', label: '插件介绍' },
  { key: 'logs', label: '更新日志' }
]

const pluginName = computed(() => String(detail.value?.plugin?.name || props.plugin?.name || '插件详情'))
const pluginSummary = computed(() => String(detail.value?.plugin?.summary || props.plugin?.summary || ''))
const pluginIconChar = computed(() => {
  const char = String(detail.value?.plugin?.icon_char || props.plugin?.iconChar || '')
  if (char !== '') {
    return char
  }
  return pluginName.value.slice(0, 1).toUpperCase() || 'P'
})
const iconColor = computed(() => String(detail.value?.plugin?.icon_color || props.plugin?.iconColor || '#2563eb'))
const pluginIsFeature = computed(() => Boolean(Number(detail.value?.plugin?.is_feature ?? props.plugin?.isFeature ?? 0)))
const pluginIsOfficial = computed(() => Boolean(Number(detail.value?.plugin?.is_official ?? props.plugin?.isOfficial ?? 0)))
const ratingValue = computed(() => Number(
  detail.value?.review_summary?.rating ??
  detail.value?.plugin?.rating ??
  props.plugin?.rating ??
  0
))
const ratingText = computed(() => ratingValue.value.toFixed(1))
const reviewsText = computed(() => {
  const count = Number(
    detail.value?.review_summary?.reviews ??
    detail.value?.plugin?.reviews ??
    props.plugin?.reviews ??
    0
  )
  return count > 0 ? String(count) : '暂无'
})
const surfaceLabels = computed(() => {
  const raw = detail.value?.plugin?.surface_tags ?? props.plugin?.surfaceTags ?? []
  if (!Array.isArray(raw) || raw.length === 0) {
    return '通用插件'
  }
  return raw.map(label => resolveSurfaceLabel(label)).join(' ')
})
const sceneTags = computed(() => normalizeStringList(detail.value?.scene_tags))
const featureHighlights = computed(() => {
  const list = normalizeArray(detail.value?.feature_highlights)
    .map(item => ({
      title: String(item.title || ''),
      description: String(item.description || ''),
      color: String(item.color || '')
    }))
    .filter(item => item.title !== '')
  return list
})
const infoRows = computed(() => normalizeArray(detail.value?.info_rows)
  .map(row => ({
    label: String(row.label || ''),
    value: String(row.value || '')
  }))
  .filter(row => row.label !== ''))
const skus = computed(() => normalizeArray(detail.value?.skus)
  .map(sku => ({
    id: Number(sku.id || 0),
    billingLabel: String(sku.billing_label || ''),
    billingType: String(sku.billing_type || ''),
    priceLabel: String(sku.price_label || ''),
    price: Number(sku.price || 0),
    subscriptionDays: Number(sku.subscription_days || 0)
  }))
  .filter(sku => sku.id > 0))
const updateLogs = computed(() => normalizeArray(detail.value?.update_logs)
  .map(log => ({
    version: String(log.version || ''),
    publishedAt: String(log.published_at || log.publishedAt || ''),
    summary: String(log.summary || ''),
    totalSize: Number(log.total_size || 0),
    fileCount: Number(log.file_count || 0)
  }))
  .filter(log => log.version !== ''))
const isEmptyIntro = computed(() => pluginSummary.value === '' && featureHighlights.value.length === 0 && sceneTags.value.length === 0)

const primaryLabel = computed(() => {
  if (props.hasLicense) {
    return '立即下载'
  }
  if (props.plugin?.billingType === 'free') {
    return '立即领取'
  }
  return '立即购买'
})

const iconStyle = computed(() => ({
  backgroundColor: iconColor.value
}))

function featureIconStyle(feature) {
  return {
    color: feature.color || '#2563eb',
    backgroundColor: `${feature.color || '#2563eb'}1a`
  }
}

function normalizeArray(value) {
  return Array.isArray(value) ? value : []
}

function normalizeStringList(value) {
  return normalizeArray(value)
    .map(item => String(item || '').trim())
    .filter(item => item !== '')
}

function resolveSurfaceLabel(value) {
  const label = String(value || '')
  if (label === 'platform') {
    return '平台端'
  }
  if (label === 'merchant') {
    return '商户端'
  }
  if (label === 'mobile') {
    return '移动端'
  }
  return label
}

function formatDate(value) {
  const text = String(value || '').replace('T', ' ')
  return text === '' ? '-' : text
}

async function fetchDetail() {
  const code = String(props.plugin?.code || '').trim()
  if (code === '') {
    detailLoading.value = false
    return
  }
  detailLoading.value = true
  try {
    const response = await getPluginCatalogDetail(code)
    const payload = response?.data ?? response ?? {}
    detail.value = payload && typeof payload === 'object' ? payload : {}
    // 默认选中第一个价格方案,与远端返回顺序保持一致。
    const firstSku = skus.value[0]
    selectedSkuId.value = firstSku ? firstSku.id : 0
  } catch (error) {
    ElMessage.error(error?.message || '插件详情加载失败')
    detail.value = null
  } finally {
    detailLoading.value = false
  }
}

function handlePrimaryAction() {
  if (props.hasLicense) {
    emit('install', props.plugin)
    return
  }
  const skuId = Number(selectedSkuId.value || 0)
  if (!skuId) {
    ElMessage.warning('未找到可用的价格方案')
    return
  }
  emit('buy', { plugin: props.plugin, skuId })
}

function handleFavorite() {
  // 远端市场暂无收藏接口,先以提示兜底,避免用户以为收藏成功。
  ElMessage.info('收藏功能暂未开放')
}

onMounted(() => {
  void fetchDetail()
})
</script>

<style scoped lang="scss">
.plugin-detail {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
  padding: 16px 2px 4px;
}

.plugin-detail__skeleton {
  padding: 24px 4px;
}

.plugin-detail__breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;

  font-size: 13px;
  line-height: 1.4;
  color: #98a2b3;
}

.plugin-detail__crumb-sep {
  color: #d0d5dd;
}

.plugin-detail__crumb-link {
  padding: 0;
  border: 0;
  background: transparent;
  color: #2563eb;
  font-size: 13px;
  cursor: pointer;

  &:hover {
    color: #1d4ed8;
    text-decoration: underline;
  }
}

.plugin-detail__crumb--current {
  color: #667085;
}

.plugin-detail__hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: 16px;
  align-items: start;
}

.plugin-detail__hero-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
  padding: 22px 24px;
  border: 1px solid #e7ecf4;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
}

.plugin-detail__title-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  min-width: 0;
}

.plugin-detail__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 16px;
  flex-shrink: 0;
}

.plugin-detail__icon-char {
  color: #ffffff;
  font-size: 30px;
  font-weight: 700;
  line-height: 1;
}

.plugin-detail__title-text {
  min-width: 0;
  flex: 1;
}

.plugin-detail__title-line {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.plugin-detail__name {
  margin: 0;
  font-size: 24px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.plugin-detail__summary {
  margin: 10px 0 0;
  font-size: 14px;
  line-height: 1.7;
  color: #667085;
}

.plugin-detail__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 12px;
  font-size: 13px;
  color: #667085;
}

.plugin-detail__star {
  color: #f59e0b;
}

.plugin-detail__score {
  font-size: 15px;
  font-weight: 700;
  color: #101828;
}

.plugin-detail__reviews {
  color: #98a2b3;
}

.plugin-detail__meta-divider {
  margin: 0 6px;
  color: #e4e7ec;
}

.plugin-detail__platforms {
  color: #667085;
}

.plugin-detail__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.plugin-detail__tag {
  border-radius: 6px;
}

.plugin-detail__purchase {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px;
  border: 1px solid #e7ecf4;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
}

.plugin-detail__plans {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.plugin-detail__plan {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 8px;
  border: 1px solid #e4e7ec;
  border-radius: 8px;
  background: #ffffff;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease;

  &:hover {
    border-color: #93c5fd;
  }

  &.is-active {
    border-color: #2563eb;
    background: #eff6ff;
  }
}

.plugin-detail__plan-label {
  font-size: 12px;
  line-height: 1.2;
  color: #667085;

  .plugin-detail__plan.is-active & {
    color: #2563eb;
  }
}

.plugin-detail__plan-price {
  font-size: 16px;
  line-height: 1.2;
  font-weight: 700;
  color: #101828;

  .plugin-detail__plan.is-active & {
    color: #2563eb;
  }
}

.plugin-detail__buy-button {
  width: 100%;
  height: 44px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
}

.plugin-detail__favorite-button {
  width: 100%;
  height: 44px;
  border-radius: 8px;
  font-size: 14px;
}

.plugin-detail__body {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}

.plugin-detail__card {
  min-width: 0;
  border: 1px solid #e7ecf4;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
}

.plugin-detail__side {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
  // 全局样式对 aside 标签有主题底色与内边距,详情侧栏需恢复为无背景、无多余留白。
  padding: 0;
  margin: 0;
  background: transparent;
  line-height: 1.4;
  font-size: 14px;
  font-family: inherit;
}

.plugin-detail__tabs {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  padding: 0 20px;
  border-bottom: 1px solid #edf2f7;
}

.plugin-detail__tab {
  position: relative;
  margin: 0;
  padding: 15px 12px 14px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #344054;
  font-size: 14px;
  line-height: 1.2;
  font-weight: 600;
  cursor: pointer;

  &:hover {
    color: #2563eb;
  }

  &.is-active {
    color: #2563eb;
    border-bottom-color: #2563eb;
  }
}

.plugin-detail__panel {
  padding: 20px 22px 24px;
}

.plugin-detail__section {
  display: flex;
  flex-direction: column;
  gap: 14px;

  & + .plugin-detail__section {
    margin-top: 26px;
  }
}

.plugin-detail__section-title {
  margin: 0;
  font-size: 16px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.plugin-detail__paragraph {
  margin: 0;
  font-size: 14px;
  line-height: 1.8;
  color: #667085;
}

.plugin-detail__features {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.plugin-detail__feature-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #eef2f7;
  border-radius: 10px;
  background: #fbfdff;
}

.plugin-detail__feature-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  flex-shrink: 0;

  .el-icon {
    font-size: 17px;
  }
}

.plugin-detail__feature-text {
  min-width: 0;
}

.plugin-detail__feature-title {
  font-size: 14px;
  line-height: 1.4;
  font-weight: 700;
  color: #101828;
}

.plugin-detail__feature-desc {
  margin: 6px 0 0;
  font-size: 13px;
  line-height: 1.7;
  color: #667085;
}

.plugin-detail__scenes {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.plugin-detail__logs {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.plugin-detail__log {
  padding: 14px 16px;
  border: 1px solid #eef2f7;
  border-radius: 10px;
  background: #ffffff;
}

.plugin-detail__log-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}

.plugin-detail__log-version {
  font-size: 14px;
  font-weight: 700;
  color: #101828;
}

.plugin-detail__log-date {
  font-size: 12px;
  color: #98a2b3;
}

.plugin-detail__log-summary {
  margin: 8px 0 0;
  font-size: 13px;
  line-height: 1.7;
  color: #667085;
}

.plugin-detail__side-title {
  margin: 0;
  padding: 18px 20px 14px;
  font-size: 15px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
  border-bottom: 1px solid #edf2f7;
}

.plugin-detail__info {
  margin: 0;
  padding: 8px 20px 14px;
}

.plugin-detail__info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-width: 0;
  padding: 11px 0;
  border-bottom: 1px solid #f4f6fa;

  &:last-child {
    border-bottom: 0;
  }
}

.plugin-detail__info-label {
  flex-shrink: 0;
  font-size: 13px;
  color: #98a2b3;
}

.plugin-detail__info-value {
  margin: 0;
  min-width: 0;
  font-size: 13px;
  text-align: right;
  color: #101828;
  word-break: break-all;
}

@media (max-width: 1400px) {
  .plugin-detail__hero {
    grid-template-columns: minmax(0, 1fr) 340px;
  }
}

@media (max-width: 1100px) {
  .plugin-detail__hero,
  .plugin-detail__body {
    grid-template-columns: 1fr;
  }

  .plugin-detail__features {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .plugin-detail__plans {
    grid-template-columns: 1fr;
  }
}
</style>
