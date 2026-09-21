<template>
  <div class="plugin-market-page">
    <section class="market-hero">
      <div class="market-hero__text">
        <h1 class="market-title">{{ isDeveloperMode ? '开发者插件' : '插件市场' }}</h1>
        <p v-if="!isDeveloperMode" class="market-subtitle">发现并安装能扩展系统功能的插件</p>
      </div>

      <div class="market-hero__search">
        <el-input
          v-model="keyword"
          class="market-hero__input"
          clearable
          :prefix-icon="Search"
          placeholder="搜索插件名称、功能或开发者..."
          @keyup.enter="handleSearch"
        />
        <el-button
          class="market-hero__search-button"
          type="primary"
          :icon="Search"
          @click="handleSearch"
        >
          搜索
        </el-button>
      </div>
    </section>

    <section class="market-layout" :class="{ 'is-developer': isDeveloperMode }">
      <aside v-if="!isDeveloperMode" class="market-sidebar">
        <div class="market-sidebar__section">
          <h2 class="market-sidebar__title">插件分类</h2>
          <div class="market-category-list">
            <button
              v-for="item in categoryList"
              :key="item.key"
              type="button"
              class="market-category-item"
              :class="{ 'is-active': activeCategory === item.key }"
              @click="handleCategoryChange(item.key)"
            >
              <span class="market-category-item__label">{{ item.label }}</span>
              <span class="market-category-item__count">{{ item.count }}</span>
            </button>
          </div>
        </div>

        <div class="market-developer-card">
          <div class="market-developer-card__icon">
            <el-icon>
              <Tools />
            </el-icon>
          </div>
          <div class="market-developer-card__title">{{ developerInfo.nickname }}</div>
          <p class="market-developer-card__desc">
            {{ developerInfo.login ? (developerInfo.phone || '已登录') : '登录后可查看个人中心与发布列表' }}
          </p>
          <div class="market-developer-card__metrics">
            <div
              v-for="metric in developerMetrics"
              :key="metric.label"
              class="market-developer-card__metric"
            >
              <span class="market-developer-card__metric-label">{{ metric.label }}</span>
              <span class="market-developer-card__metric-value">{{ metric.value }}</span>
            </div>
          </div>
          <a
            class="market-developer-card__button"
            href="https://www.rabshop.cn"
            target="_blank"
            rel="noopener noreferrer"
          >
            <el-icon class="market-developer-card__button-icon">
              <ArrowRight />
            </el-icon>
            <span>前往插件市场</span>
          </a>
          <el-button
            v-if="developerInfo.login"
            class="market-developer-card__logout"
            text
            type="danger"
            size="small"
            @click="handleOpenAuthLogout"
          >
            退出登录
          </el-button>
        </div>
      </aside>

      <main v-loading="loading" class="market-main">
        <PluginDetail
          v-if="currentDetailPlugin"
          :plugin="currentDetailPlugin"
          :has-license="Boolean(currentDetailPlugin.hasLicense)"
          @back="handleBackToList"
          @buy="handleDetailBuy"
          @install="handleDetailInstall"
        />

        <template v-else>
          <div class="market-topbar">
            <div class="market-tabs">
              <button
                v-for="tab in visibleTabs"
                :key="tab.key"
                type="button"
                class="market-tabs__item"
                :class="{ 'is-active': activeTab === tab.key }"
                @click="handleTabChange(tab.key)"
              >
                {{ tab.label }}
              </button>
            </div>

            <div class="market-toolbar">
              <el-tag effect="plain" type="info">共 {{ catalogTotal }} 项</el-tag>
              <el-select
                v-if="!isDeveloperMode"
                v-model="sortKey"
                class="market-sort"
                :suffix-icon="ArrowDown"
                @change="handleSortChange"
              >
                <el-option
                  v-for="option in sortOptions"
                  :key="option.value"
                  :label="option.label"
                  :value="option.value"
                />
              </el-select>
            </div>
          </div>

          <div v-if="catalogError" class="market-load-error">
            <el-alert :title="catalogError" type="error" :closable="false" show-icon />
            <el-button :icon="Refresh" :loading="loading" @click="fetchCatalog">重试</el-button>
          </div>

          <div v-else-if="pagedPlugins.length" class="market-grid">
            <article
              v-for="plugin in pagedPlugins"
              :key="plugin.code"
              class="market-card"
            >
              <div class="market-card__header">
                <div
                  class="market-card__icon"
                  :style="pluginIconStyle(plugin)"
                >
                  <el-icon>
                    <component :is="plugin.icon" />
                  </el-icon>
                </div>

                <div class="market-card__content">
                  <button
                    v-if="plugin.source === 'developer' && plugin.lifecycleStatus !== 'uninstalled'"
                    type="button"
                    class="market-card__title-button"
                    @click="handleOpenApplication(plugin)"
                  >
                    {{ plugin.name }}
                  </button>
                  <h3 v-else-if="plugin.source === 'developer'" class="market-card__title">
                    {{ plugin.name }}
                  </h3>
                  <button
                    v-else
                    type="button"
                    class="market-card__title-button"
                    @click="handleOpenDetail(plugin)"
                  >
                    {{ plugin.name }}
                  </button>
                  <div v-if="plugin.source !== 'developer'" class="market-card__rating">
                    <el-icon class="market-card__star">
                      <StarFilled />
                    </el-icon>
                    <span class="market-card__score">{{ plugin.rating.toFixed(1) }}</span>
                    <span class="market-card__reviews">({{ plugin.reviews }})</span>
                  </div>
                </div>
                <span
                  v-if="plugin.source !== 'developer'"
                  class="market-card__price"
                  :class="plugin.priceType === 'free' ? 'is-free' : 'is-paid'"
                >
                  {{ plugin.priceLabel }}
                </span>
              </div>

              <div class="market-card__flags">
                <template v-if="plugin.source === 'developer'">
                  <el-tag effect="plain" size="small" type="info">开发源码</el-tag>
                  <el-tag effect="plain" size="small">v{{ plugin.version }}</el-tag>
                  <el-tag effect="plain" size="small" :type="plugin.statusType">{{ plugin.statusLabel }}</el-tag>
                </template>
                <template v-else>
                  <el-tag v-if="plugin.isFeature" effect="plain" size="small" type="success">精选</el-tag>
                  <el-tag v-if="plugin.isOfficial" effect="plain" size="small" type="warning">官方</el-tag>
                  <el-tag effect="plain" size="small">{{ plugin.billingLabel }}</el-tag>
                </template>
              </div>

              <p v-if="plugin.source === 'developer' && plugin.blockedReason" class="market-card__blocked">
                {{ plugin.blockedReason }}
              </p>

              <p class="market-card__desc">
                {{ plugin.description }}
              </p>

              <div v-if="plugin.surfaceTags.length" class="market-card__tags">
                <el-tag
                  v-for="tag in plugin.surfaceTags"
                  :key="tag"
                  effect="plain"
                  size="small"
                >
                  {{ tag }}
                </el-tag>
              </div>

              <div class="market-card__footer">
                <div class="market-card__team">
                  <el-icon>
                    <UserFilled />
                  </el-icon>
                  <span>{{ plugin.team }}</span>
                </div>

                <div class="market-card__actions">
                  <template v-if="plugin.source === 'developer'">
                    <el-button
                      v-if="plugin.lifecycleStatus === 'active'"
                      type="primary"
                      plain
                      size="small"
                      :icon="Monitor"
                      :loading="Boolean(applicationLoading[plugin.code])"
                      @click="handleOpenApplication(plugin)"
                    >
                      进入应用
                    </el-button>
                    <el-button
                      v-if="plugin.lifecycleStatus === 'uninstalled' || plugin.canInstall"
                      class="market-card__developer-action"
                      type="primary"
                      plain
                      size="small"
                      :icon="Download"
                      :loading="Boolean(developerActionLoading[plugin.code])"
                      :disabled="!plugin.canInstall"
                      @click="handleDeveloperAction(plugin, 'install')"
                    >
                      安装
                    </el-button>
                    <template v-else>
                      <el-button
                        class="market-card__developer-action"
                        type="warning"
                        plain
                        size="small"
                        :icon="RefreshRight"
                        :loading="Boolean(developerActionLoading[plugin.code])"
                        :disabled="!plugin.canReinstall"
                        @click="handleDeveloperAction(plugin, 'reinstall')"
                      >
                        重新安装
                      </el-button>
                      <el-button
                        class="market-card__developer-action"
                        type="danger"
                        plain
                        size="small"
                        :icon="Delete"
                        :loading="Boolean(developerActionLoading[plugin.code])"
                        :disabled="!plugin.canUninstall"
                        @click="handleDeveloperAction(plugin, 'uninstall')"
                      >
                        卸载
                      </el-button>
                    </template>
                    <el-button
                      class="market-card__developer-action"
                      type="info"
                      plain
                      size="small"
                      :icon="FolderOpened"
                      :loading="Boolean(developerDownloadLoading[plugin.code])"
                      :disabled="!plugin.canDownload"
                      @click="handleDeveloperDownload(plugin)"
                    >
                      下载插件
                    </el-button>
                  </template>
                  <template v-else>
                    <el-button text type="primary" class="market-card__detail" @click="handleOpenDetail(plugin)">
                      详情
                    </el-button>
                    <el-button
                      class="market-card__install"
                      type="primary"
                      plain
                      size="small"
                      @click="handleInstall(plugin)"
                    >
                      {{ plugin.actionLabel }}
                    </el-button>
                  </template>
                </div>
              </div>
            </article>
          </div>

          <el-empty
            v-else
            class="market-empty"
            description="暂无匹配插件"
          />

          <div
            v-if="totalPages > 1"
            class="market-pagination"
          >
            <button
              type="button"
              class="market-pagination__item market-pagination__nav"
              :disabled="currentPage === 1"
              @click="handlePrevPage"
            >
              <el-icon>
                <ArrowLeft />
              </el-icon>
            </button>

            <button
              v-for="(item, index) in paginationItems"
              :key="`${item}-${index}`"
              type="button"
              class="market-pagination__item"
              :class="{
                'is-active': currentPage === item,
                'is-ellipsis': item === 'ellipsis'
              }"
              :disabled="item === 'ellipsis'"
              @click="handlePageJump(item)"
            >
              {{ item === 'ellipsis' ? '...' : item }}
            </button>

            <button
              type="button"
              class="market-pagination__item market-pagination__nav"
              :disabled="currentPage === totalPages"
              @click="handleNextPage"
            >
              <el-icon>
                <ArrowRight />
              </el-icon>
            </button>
          </div>
        </template>
      </main>
    </section>

    <el-drawer
      v-if="!isDeveloperMode"
      v-model="publishDrawerVisible"
      class="market-panel-drawer"
      size="760px"
      title="开发者中心"
      destroy-on-close
    >
      <div class="market-panel">
        <div class="market-panel__header">
          <div>
            <div class="market-panel__title">{{ developerInfo.nickname }}</div>
            <p class="market-panel__desc">
              {{ developerInfo.login ? '实时查看余额、授权和发布记录' : '登录后可查看完整开发者中心' }}
            </p>
          </div>
          <el-tag effect="plain" :type="developerInfo.login ? 'success' : 'info'">
            {{ developerInfo.login ? '已登录' : '未登录' }}
          </el-tag>
        </div>

        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="UID">{{ developerInfo.uid || '-' }}</el-descriptions-item>
          <el-descriptions-item label="余额">{{ formatMoney(developerInfo.balance) }}</el-descriptions-item>
          <el-descriptions-item label="实名">{{ developerInfo.isVerify ? '已实名' : '未实名' }}</el-descriptions-item>
          <el-descriptions-item label="账号状态">{{ developerInfo.statusLabel }}</el-descriptions-item>
        </el-descriptions>

        <div class="market-panel__section">
          <div class="market-detail__section-title">发布列表</div>
          <el-table v-if="publishDrawerRows.length" :data="publishDrawerRows" size="small" border>
            <el-table-column prop="code" label="编码" min-width="140" />
            <el-table-column prop="name" label="名称" min-width="160" />
            <el-table-column label="版本" min-width="110">
              <template #default="{ row }">
                {{ row.version || row.latestVersion || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="上架" min-width="90">
              <template #default="{ row }">
                <el-tag effect="plain" size="small">
                  {{ resolvePublishStatusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="审核" min-width="100">
              <template #default="{ row }">
                <el-tag effect="plain" size="small">
                  {{ resolveAuditStatusLabel(row.auditStatus) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="时间" min-width="160">
              <template #default="{ row }">
                {{ formatDateTime(row.updateTime || row.createTime) }}
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-else description="暂无发布记录" />
        </div>
      </div>
    </el-drawer>

    <template v-if="!isDeveloperMode">
      <PluginLogin
        v-model:visible="loginVisible"
        @success="handleOpenAuthSuccess"
      />

      <PluginPayDialog
        v-model:visible="payDialogVisible"
        :order="payOrderInfo"
        @success="handlePaySuccess"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowDown,
  ArrowLeft,
  ArrowRight,
  ChatDotRound,
  Coin,
  Delete,
  Download,
  FolderOpened,
  Lock,
  Memo,
  Monitor,
  Promotion,
  Refresh,
  RefreshRight,
  Search,
  StarFilled,
  Tools,
  TrendCharts,
  UserFilled
} from '@element-plus/icons-vue'
import {
  createPluginOrder,
  downloadDeveloperPlugin,
  downloadPluginFile,
  getPluginBalance,
  getPluginCatalogDetail,
  getPluginCatalogList,
  getPluginDeveloperList,
  getPluginDownloads,
  getPluginLicenses,
  getPluginOpenAuthInfo,
  getPluginOrders,
  getPluginPublishList,
  getPluginUserInfo,
  installDeveloperPlugin,
  logoutPluginOpenAuth,
  reinstallDeveloperPlugin,
  requestPluginDownloadTicket,
  uninstallDeveloperPlugin
} from '@/api/plugin'
import PluginDetail from './components/PluginDetail.vue'
import PluginLogin from './components/PluginLogin.vue'
import PluginPayDialog from './components/PluginPayDialog.vue'
import { useUserStore } from '@/store/modules/user'
import { getPluginState, syncPluginRuntime } from '@/plugins/registry'
import { useRouter } from 'vue-router'
import { normalizeMenuPath, resolveMenuPath } from '@/utils/menuTree'
import settings from '@/settings'

defineOptions({
  name: 'PluginMarket'
})

const pageSize = 9
// 开发插件动作与按钮文案共用同一份映射，避免确认弹窗和提示词出现分叉。
const DEVELOPER_ACTION_LABELS = { install: '安装', uninstall: '卸载', reinstall: '重新安装' }
const router = useRouter()
const applicationLoading = reactive({})
const keyword = ref('')
const appliedKeyword = ref('')
const activeCategory = ref('all')
const activeTab = ref('all')
const sortKey = ref('default')
const currentPage = ref(1)
const loading = ref(false)
const catalogError = ref('')
const developerActionLoading = reactive({})
const developerDownloadLoading = reactive({})
const catalogTotal = ref(0)
const catalogPages = ref(1)
const catalogCategories = ref([])
const marketPlugins = ref([])
const licenseList = ref([])
const downloadList = ref([])
const orderList = ref([])
const publishList = ref([])
const userInfo = ref(null)
const publishDrawerVisible = ref(false)
const detailPlugin = ref(null)
const detailCache = reactive({})
const openAuth = ref({ login: false, uid: 0, user: {}})
const openAuthLoaded = ref(false)
const loginVisible = ref(false)
const payDialogVisible = ref(false)
const payOrderInfo = ref({})
const pendingBuy = ref(null)
const balanceState = reactive({
  balance: 0,
  login: false
})
let catalogRequest = 0

const tabList = [
  { key: 'all', label: '全部' },
  { key: 'developer', label: '开发者插件' },
  { key: 'free', label: '免费' },
  { key: 'paid', label: '付费' },
  { key: 'latest', label: '最新' },
  { key: 'hot', label: '最热' }
]

const isDeveloperMode = computed(() => activeTab.value === 'developer')
const visibleTabs = computed(() => isDeveloperMode.value ? tabList.filter(tab => ['all', 'developer'].includes(tab.key)) : tabList)

const sortOptions = [
  { label: '推荐排序', value: 'default' },
  { label: '下载最多', value: 'downloads' },
  { label: '评分最高', value: 'rating' },
  { label: '最新上线', value: 'latest' }
]

const categoryList = computed(() => [
  {
    key: 'all',
    label: '全部插件',
    count: catalogCategories.value.reduce((total, item) => total + item.count, 0)
  },
  ...catalogCategories.value.map(item => ({ key: String(item.id), label: item.name, count: item.count }))
])

const licenseMap = computed(() => {
  const licenses = new Map()
  // 历史授权可能已过期或吊销，同一插件只使用最新有效授权下载。
  for (const license of licenseList.value) {
    const code = String(license.plugin_code || '')
    const expiresAt = String(license.expires_at || '').trim()
    const expiresTime = expiresAt ? new Date(expiresAt.replace(' ', 'T')).getTime() : null
    if (!code || Number(license.status) !== 1 || license.revoked_at ||
      (expiresAt && (!Number.isFinite(expiresTime) || expiresTime <= Date.now()))) {
      continue
    }
    const current = licenses.get(code)
    if (!current || Number(license.id) > Number(current.id)) {
      licenses.set(code, license)
    }
  }
  return licenses
})
const downloadMap = computed(() => new Map(downloadList.value.map(item => [String(item.plugin_code || ''), item])))

const developerInfo = computed(() => {
  const info = userInfo.value || {}
  const openUser = openAuth.value?.user || {}
  return {
    nickname: String(openUser.nickname || info.nickname || '登陆插件市场'),
    avatar: String(openUser.avatar || info.avatar || ''),
    uid: Number(openUser.uid || info.uid || 0),
    phone: String(openUser.phone || ''),
    balance: Number(info.balance ?? balanceState.balance ?? 0),
    login: Boolean(balanceState.login || openAuth.value?.login || info.uid),
    isVerify: Number(info.is_verify || 0) === 1,
    status: Number(info.status || 0),
    statusLabel: Number(openAuth.value?.login ? info.status || 1 : 0) === 1 ? '正常' : '未登录'
  }
})

const developerMetrics = computed(() => [
  { label: '授权', value: licenseList.value.length },
  { label: '可下载', value: downloadList.value.length },
  { label: '订单', value: orderList.value.length },
  { label: '发布', value: publishList.value.length }
])

const publishDrawerRows = computed(() => normalizeArrayPayload(publishList.value).map(item => ({
  id: Number(item.id || 0),
  code: String(item.code || ''),
  name: String(item.name || ''),
  version: String(item.version || item.latest_version || ''),
  latestVersion: String(item.latest_version || ''),
  status: Number(item.status || 0),
  auditStatus: Number(item.audit_status || 0),
  auditReason: String(item.audit_reason || ''),
  createTime: String(item.create_time || ''),
  updateTime: String(item.update_time || '')
})))

const catalogQuery = computed(() => ({
  category_id: activeCategory.value === 'all' ? 0 : Number(activeCategory.value),
  keyword: appliedKeyword.value,
  sort: resolveSortMode(),
  page: currentPage.value,
  limit: pageSize,
  billing_type: ['free', 'paid'].includes(activeTab.value) ? activeTab.value : '',
  is_feature: 0
}))

const decoratedPlugins = computed(() => marketPlugins.value.map(plugin => decoratePlugin(plugin)))
const currentDetailPlugin = computed(() => detailPlugin.value && detailPlugin.value.source !== 'developer' ? decoratePlugin(detailPlugin.value) : null)

const totalPages = computed(() => Math.max(1, catalogPages.value))
const pagedPlugins = computed(() => decoratedPlugins.value)
const paginationItems = computed(() => buildPaginationItems(currentPage.value, totalPages.value))

watch([activeCategory, activeTab, sortKey, appliedKeyword, currentPage], (next, previous) => {
  const filtersChanged = next.slice(0, 4).some((value, index) => value !== previous[index])
  if (filtersChanged && currentPage.value !== 1) {
    currentPage.value = 1
    return
  }
  void fetchCatalog()
})

onMounted(() => {
  void fetchCatalog()
})

onBeforeUnmount(() => {
  catalogRequest += 1
})

async function fetchCatalog() {
  const currentRequest = ++catalogRequest
  const developerMode = isDeveloperMode.value
  loading.value = true
  catalogError.value = ''
  try {
    // 两种来源共用请求序号，切换分类后晚到的旧响应不能覆盖当前列表。
    const response = developerMode
      ? await getPluginDeveloperList({ keyword: appliedKeyword.value, page: currentPage.value, limit: pageSize })
      : await getPluginCatalogList(catalogQuery.value)
    if (currentRequest !== catalogRequest) {
      return
    }
    const payload = readPayload(response)
    const page = developerMode ? normalizeDeveloperPage(payload) : normalizeCatalogPage(payload)
    catalogTotal.value = Number(page.total || page.items.length || 0)
    catalogPages.value = Math.max(1, page.pages)
    if (!developerMode) {
      catalogCategories.value = page.categories
    }
    marketPlugins.value = page.items
    if (currentPage.value > catalogPages.value) {
      // 已校验当前请求序号，此后同步调整分页不会与晚到响应交错。
      // eslint-disable-next-line require-atomic-updates
      currentPage.value = catalogPages.value
    }
  } catch (error) {
    if (currentRequest !== catalogRequest) {
      return
    }
    catalogError.value = error?.message || '插件列表加载失败'
    ElMessage.error(catalogError.value)
    marketPlugins.value = []
    catalogTotal.value = 0
    catalogPages.value = 1
  } finally {
    if (currentRequest === catalogRequest) {
      loading.value = false
    }
  }
}

async function fetchSidebarData() {
  // 侧栏依赖多个独立接口，任意一个失败都不应阻断其他信息展示。
  const tasks = await Promise.allSettled([
    getPluginBalance(),
    getPluginUserInfo(),
    getPluginLicenses(),
    getPluginDownloads(),
    getPluginOrders(),
    getPluginPublishList()
  ])

  if (tasks[0].status === 'fulfilled') {
    const data = readPayload(tasks[0].value) || {}
    balanceState.balance = Number(data.balance || 0)
    balanceState.login = Boolean(data.login)
  }

  if (tasks[1].status === 'fulfilled') {
    userInfo.value = readPayload(tasks[1].value) || null
  }

  if (tasks[2].status === 'fulfilled') {
    licenseList.value = normalizeArrayPayload(readPayload(tasks[2].value))
  }

  if (tasks[3].status === 'fulfilled') {
    downloadList.value = normalizeArrayPayload(readPayload(tasks[3].value))
  }

  if (tasks[4].status === 'fulfilled') {
    orderList.value = normalizeArrayPayload(readPayload(tasks[4].value))
  }

  if (tasks[5].status === 'fulfilled') {
    publishList.value = normalizeArrayPayload(readPayload(tasks[5].value))
  }
}

function readPayload(response) {
  return response?.data ?? response ?? {}
}

function normalizeArrayPayload(payload) {
  return Array.isArray(payload) ? payload : []
}

function normalizeCatalogPage(payload) {
  const items = normalizeArrayPayload(payload?.items)
  const total = Number(payload?.total ?? items.length)
  const limit = Number(payload?.limit || pageSize)
  return {
    items: items.map(normalizeCatalogItem),
    categories: normalizeArrayPayload(payload?.categories).map(item => ({
      id: Number(item.id || 0),
      name: String(item.name || ''),
      count: Number(item.count || 0)
    })).filter(item => item.id > 0 && item.name),
    total,
    page: Number(payload?.page || 1),
    limit,
    pages: Number(payload?.pages ?? Math.ceil(total / limit))
  }
}

/**
 * 转换已合并本地安装状态的开发插件分页响应。
 * @param {Object} payload 接口分页数据。
 * @returns {Object} 页面分页数据。
 */
function normalizeDeveloperPage(payload) {
  const items = normalizeArrayPayload(payload?.items).map(normalizeDeveloperItem)
  const total = Number(payload?.total ?? items.length)
  const limit = Number(payload?.limit || pageSize)
  return { items, total, pages: Number(payload?.pages ?? Math.ceil(total / limit)) }
}

/**
 * 开发源码使用独立契约，不映射成免费市场商品或虚构购买授权。
 * @param {Object} item 开发插件及真实生命周期状态。
 * @returns {Object} 开发插件卡片数据。
 */
function normalizeDeveloperItem(item) {
  if (item.source !== 'developer') {
    throw new Error('开发者插件数据无效')
  }
  const code = String(item.code || '')
  const lifecycleStatus = String(item.lifecycle_status || 'uninstalled')
  const surfaces = { backend: '后端', admin: '平台端', merchant: '商户端', mobile: '移动端' }
  const statuses = {
    uninstalled: '未安装', active: '已安装', disabled: '已停用', failed: '异常',
    installing: '安装中', uninstalling: '卸载中', upgrading: '升级中', disabling: '停用中'
  }
  return {
    source: 'developer', code, name: String(item.name || ''), version: String(item.version || ''),
    description: String(item.description || ''), team: String(item.publisher || ''),
    surfaceTags: normalizeArrayPayload(item.surfaces).map(surface => surfaces[surface] || surface),
    lifecycleStatus, enabled: item.enabled === true,
    canInstall: item.can_install === true, canUninstall: item.can_uninstall === true,
    canReinstall: item.can_reinstall === true, canDownload: item.can_download === true,
    sourceChanged: item.source_changed === true,
    blockedReason: String(item.blocked_reason || ''),
    statusLabel: statuses[lifecycleStatus] || '处理中',
    statusType: lifecycleStatus === 'active' ? 'success' : lifecycleStatus === 'failed' ? 'danger' : 'info',
    icon: Tools, iconColor: resolvePalette(code)
  }
}

function normalizeCatalogItem(item = {}) {
  const code = String(item.code || '')
  const billingType = String(item.billing_type || 'free')
  return {
    id: Number(item.id || 0),
    code,
    name: String(item.name || ''),
    summary: String(item.summary || ''),
    description: String(item.description || item.summary || ''),
    cover: String(item.cover || ''),
    categoryId: Number(item.category_id || 0),
    categoryName: String(item.category_name || ''),
    surfaceTags: Array.isArray(item.surface_tags) ? item.surface_tags : [],
    isFeature: Boolean(Number(item.is_feature || 0)),
    isVipPrice: Boolean(Number(item.is_vip_price || 0)),
    enterpriseFree: Boolean(Number(item.enterprise_free || 0)),
    isOfficial: Boolean(Number(item.is_official || 0)),
    rating: Number(item.rating || 0),
    reviews: Number(item.reviews || 0),
    downloads: Number(item.downloads || 0),
    latestVersion: String(item.latest_version || ''),
    billingType,
    billingLabel: resolveBillingLabel(billingType),
    priceType: billingType === 'free' ? 'free' : 'paid',
    priceLabel: String(item.price_label || '免费'),
    iconColor: String(item.icon_color || resolvePalette(code)),
    icon: resolvePluginIcon(item),
    team: String(item.team || item.developer_name || item.publisher_name || item.company_name || '官方团队'),
    skuId: Number(item.sku_id || item.default_sku_id || item.latest_sku_id || 0),
    status: Number(item.status || 0),
    auditStatus: Number(item.audit_status || 0),
    auditReason: String(item.audit_reason || ''),
    version: String(item.version || ''),
    packageName: String(item.package_name || ''),
    createTime: String(item.create_time || ''),
    updateTime: String(item.update_time || ''),
    hasLicense: licenseMap.value.has(code),
    license: licenseMap.value.get(code) || null,
    downloadItem: downloadMap.value.get(code) || null,
    hasDownload: downloadMap.value.has(code),
    actionLabel: resolveActionLabel(item, licenseMap.value.has(code)),
    actionMode: resolveActionMode(item, licenseMap.value.has(code)),
    publishedAt: String(item.published_at || item.update_time || item.create_time || ''),
    plugin: item
  }
}

function decoratePlugin(plugin) {
  if (plugin.source === 'developer') {
    return plugin
  }
  const license = getLicenseByCode(plugin.code)
  const downloadItem = downloadMap.value.get(plugin.code) || null
  return {
    ...plugin,
    hasLicense: Boolean(license),
    license,
    downloadItem,
    hasDownload: Boolean(downloadItem),
    actionLabel: getPluginState(plugin.code).lifecycle_status === 'active' ? '进入应用' : resolveActionLabel(plugin, Boolean(license)),
    actionMode: resolveActionMode(plugin, Boolean(license))
  }
}

function resolveSortMode() {
  if (activeTab.value === 'latest') {
    return 'latest'
  }
  if (activeTab.value === 'hot') {
    return 'downloads'
  }
  return sortKey.value
}

function buildPaginationItems(page, total) {
  if (total <= 1) {
    return [1]
  }
  if (total <= 5) {
    return Array.from({ length: total }, (_, index) => index + 1)
  }
  if (page <= 3) {
    return [1, 2, 3, 'ellipsis', total]
  }
  if (page >= total - 2) {
    return [1, 'ellipsis', total - 2, total - 1, total]
  }
  return [1, 'ellipsis', page - 1, page, page + 1, 'ellipsis', total]
}

function resolvePluginIcon(item) {
  const category = String(item.category_name || '').toLowerCase()
  if (category.includes('数据')) {
    return TrendCharts
  }
  if (category.includes('内容')) {
    return ChatDotRound
  }
  if (category.includes('营销')) {
    return Promotion
  }
  if (category.includes('支付')) {
    return Coin
  }
  if (category.includes('安全')) {
    return Lock
  }
  if (category.includes('工具') || category.includes('开发')) {
    return Memo
  }
  if (category.includes('用户') || category.includes('互动')) {
    return Monitor
  }
  return Tools
}

function resolvePalette(seed) {
  const palette = ['#2dbf68', '#2095ff', '#ff7a00', '#7b68ee', '#ff7399', '#14b8a6', '#f7b931', '#3b82f6']
  const text = String(seed || '')
  let hash = 0
  for (let index = 0; index < text.length; index += 1) {
    hash = (hash << 5) - hash + text.charCodeAt(index)
    hash |= 0
  }
  return palette[Math.abs(hash) % palette.length]
}

function resolveBillingLabel(billingType) {
  const value = String(billingType || '')
  if (value === 'single') {
    return '单次购买'
  }
  if (value === 'buyout') {
    return '买断'
  }
  if (value === 'subscription') {
    return '订阅'
  }
  return '免费'
}

function resolvePublishStatusLabel(status) {
  return Number(status || 0) === 1 ? '上架' : '下架'
}

function resolveAuditStatusLabel(status) {
  const value = Number(status || 0)
  if (value === 1) {
    return '通过'
  }
  if (value === 2) {
    return '驳回'
  }
  if (value === 3) {
    return '草稿'
  }
  return value === 0 ? '待审核' : '未知'
}

function pluginIconStyle(plugin) {
  return {
    backgroundColor: plugin.iconColor,
    color: '#ffffff'
  }
}

function formatMoney(value) {
  const number = Number(value || 0)
  return Number.isFinite(number) ? number.toFixed(2) : '0.00'
}

function formatDateTime(value) {
  const text = String(value || '').trim()
  return text === '' ? '-' : text.replace('T', ' ')
}

function resolveActionMode(plugin, hasLicense) {
  if (hasLicense) {
    return 'download'
  }
  if (plugin.billingType === 'free') {
    return 'claim'
  }
  return 'buy'
}

function resolveActionLabel(plugin, hasLicense) {
  if (hasLicense) {
    return '下载'
  }
  if (plugin.billingType === 'free') {
    return '领取'
  }
  if (plugin.billingType === 'subscription') {
    return '订阅'
  }
  return '购买'
}

function getLicenseByCode(code) {
  return licenseMap.value.get(String(code || '')) || null
}

function handleSearch() {
  detailPlugin.value = null
  const search = keyword.value.trim()
  const queryChanged = appliedKeyword.value !== search || currentPage.value !== 1
  appliedKeyword.value = search
  currentPage.value = 1
  if (!queryChanged) {
    void fetchCatalog()
  }
}

function handleTabChange(key) {
  detailPlugin.value = null
  if (isDeveloperMode.value !== (key === 'developer')) {
    // 来源切换时不保留另一来源的可操作卡片，避免加载期间误点市场购买。
    marketPlugins.value = []
    catalogTotal.value = 0
    catalogPages.value = 1
  }
  activeTab.value = key
  currentPage.value = 1
}

function handleCategoryChange(key) {
  detailPlugin.value = null
  activeCategory.value = key
  currentPage.value = 1
}

function handleSortChange() {
  detailPlugin.value = null
  currentPage.value = 1
}

function handlePageJump(item) {
  if (item !== 'ellipsis') {
    currentPage.value = item
  }
}

function handlePrevPage() {
  if (currentPage.value > 1) {
    currentPage.value -= 1
  }
}

function handleNextPage() {
  if (currentPage.value < totalPages.value) {
    currentPage.value += 1
  }
}

async function handleOpenDetail(plugin) {
  if (plugin.source === 'developer') {
    if (plugin.lifecycleStatus !== 'uninstalled') return handleOpenApplication(plugin)
    return
  }
  const runtime = await syncPluginRuntime(true)
  const state = runtime.plugins?.find(item => item.code === plugin.code)
  if (state?.lifecycle_status === 'active') return handleOpenApplication(plugin, state)
  // 详情直接替换内容区展示,不跳转路由;左侧分类与开发者信息保持原位。
  detailPlugin.value = plugin
  if (!openAuthLoaded.value && !openAuth.value?.login) {
    await fetchOpenAuth()
  }
  if (openAuth.value?.login) {
    try {
      await fetchLicenseData()
    } catch (error) {
      ElMessage.error(error?.message || '授权加载失败')
    }
  }
}

function handleBackToList() {
  detailPlugin.value = null
  void fetchCatalog()
}

async function handleDetailBuy({ plugin, skuId }) {
  await handlePrimaryAction(plugin, skuId)
}

async function handleDetailInstall(plugin) {
  const runtime = await syncPluginRuntime(true)
  const state = runtime.plugins?.find(item => item.code === plugin.code)
  if (state?.lifecycle_status === 'active') return handleOpenApplication(plugin, state)
  await handleDownload(plugin)
}

async function handleInstall(plugin) {
  if (plugin.lifecycleStatus === 'active') {
    return handleOpenApplication(plugin)
  }
  if (plugin.source === 'developer') {
    if (plugin.lifecycleStatus !== 'uninstalled') {
      ElMessage.warning('应用当前不可进入，请检查启用状态')
      return
    }
    return handleDeveloperAction(plugin, 'install')
  }
  const runtime = await syncPluginRuntime(true)
  const state = runtime.plugins?.find(item => item.code === plugin.code)
  if (state?.lifecycle_status === 'active') return handleOpenApplication(plugin, state)
  return handlePrimaryAction(plugin)
}

/**
 * 按导航顺序查找插件首个可操作后台菜单，跳过目录及隐藏的接口权限。
 * @param {Array} menus 服务端返回的当前导航树。
 * @param {string} code 应用编码。
 * @param {string} parentPath 父节点完整路径。
 * @returns {string} 可操作的后台页面地址；没有时为空。
 */
function firstApplicationMenu(menus, code, parentPath = '') {
  const prefix = normalizeMenuPath('/' + code + '/')
  for (const menu of menus || []) {
    if (menu.hidden || (menu.auth_type !== undefined && Number(menu.auth_type) !== 0)) continue
    const path = resolveMenuPath(parentPath, menu.path || '')
    const child = firstApplicationMenu(menu.children, code, path)
    if (child) return child
    if (path.startsWith(prefix) && router.resolve(path).matched.some(record => record.meta?.pluginCode === code)) return path
  }
  return ''
}

/**
 * 打开已安装应用：后台首菜单优先，其次前台，无入口或停用时提示。
 * @param {Object} plugin 当前应用卡片。
 * @param {Object|null} confirmedState 本次点击已从服务端确认的状态，避免重复查询。
 * @returns {Promise<void>} 跳转或提示完成。
 */
async function handleOpenApplication(plugin, confirmedState = null) {
  if (applicationLoading[plugin.code]) return
  applicationLoading[plugin.code] = true
  try {
    const runtime = confirmedState ? null : await syncPluginRuntime(true)
    // 仅有前台的插件不会出现在后台编译注册表中，以服务器完整状态为准。
    const state = confirmedState || runtime?.plugins?.find(item => item.code === plugin.code) || getPluginState(plugin.code)
    if (!state.enabled || state.lifecycle_status !== 'active') {
      ElMessage.warning('应用尚未安装、已停用或运行状态不可用')
      return
    }
    const response = await useUserStore().fetchMenus()
    const path = firstApplicationMenu(response.data, plugin.code)
    if (path) {
      await router.push(path)
    } else if (state.frontend_entry) {
      // 前台由宿主移动站点承载，不能交给后台路由的兜底页处理。
      const site = new URL(settings.baseUri, window.location.origin)
      window.location.assign(new URL(state.frontend_entry, site.origin).href)
    } else {
      ElMessage.info('该应用没有可进入的后台菜单或前台页面')
    }
  } catch (error) {
    ElMessage.error(error?.message || '应用入口加载失败，请重试')
  } finally {
    // eslint-disable-next-line require-atomic-updates
    applicationLoading[plugin.code] = false
  }
}

/**
 * 独立执行本地开发插件安装、重新安装或卸载，不参与市场账户、授权或购买流程。
 * @param {Object} plugin 当前卡片及服务端允许的操作。
 * @param {'install'|'uninstall'|'reinstall'} action 生命周期动作。
 * @returns {Promise<void>} 操作反馈与列表刷新完成。
 */
async function handleDeveloperAction(plugin, action) {
  if (plugin.source !== 'developer' || !['install', 'uninstall', 'reinstall'].includes(action) || developerActionLoading[plugin.code]) {
    return
  }
  const label = DEVELOPER_ACTION_LABELS[action]
  const allowed = action === 'install' ? plugin.canInstall : action === 'uninstall' ? plugin.canUninstall : plugin.canReinstall
  if (!allowed) {
    ElMessage.warning(plugin.blockedReason || `当前插件不能${label}`)
    return
  }
  let submitted = false
  let execution = null
  developerActionLoading[plugin.code] = true
  try {
    const confirm = buildDeveloperConfirm(plugin, action)
    await ElMessageBox.confirm(
      confirm.message,
      `${label}插件`,
      { type: 'warning', confirmButtonText: confirm.confirmText, cancelButtonText: '取消' }
    )
    submitted = true
    const result = action === 'install'
      ? await installDeveloperPlugin(plugin.code, {})
      : action === 'uninstall'
        ? await uninstallDeveloperPlugin(plugin.code, { data_policy: 'delete' })
        : await reinstallDeveloperPlugin(plugin.code, { data_policy: 'delete' })
    const task = readPayload(result)
    if (task?.status !== 'succeeded') {
      ElMessage.error(task?.error_message || `${label}任务尚未确认完成`)
      await refreshDeveloperList()
      return
    }
    execution = task.result_json?.execution || {}
    ElMessage.success(`插件${label}成功`)
    // 安装资源已落库后刷新导航缓存与运行态，不增加市场首屏请求。
    try {
      await useUserStore().fetchMenus()
      await syncPluginRuntime(true)
    } catch (error) {
      ElMessage.warning(`插件${label}已完成，但导航刷新失败，请重新登录`)
    }
    await refreshDeveloperList()
  } catch (error) {
    if (!submitted && (error === 'cancel' || error === 'close')) {
      return
    }
    ElMessage.error(error?.message || `插件${label}失败`)
    if (submitted) {
      // 请求失败也可能留下禁用或失败状态，重新读取服务器账本，不在前端猜测回滚。
      await refreshDeveloperList()
    }
  } finally {
    // 入口按编码互斥，只有当前动作能够释放该编码的加载状态。
    // eslint-disable-next-line require-atomic-updates
    developerActionLoading[plugin.code] = false
  }

  const followUp = []
  if (execution?.frontend_rebuild_required) {
    followUp.push('相关前端需要重新构建并发布。')
  }
  if (execution?.reload_required) {
    followUp.push('后端需要重启服务或重新加载工作进程。')
  }
  if (followUp.length) {
    await ElMessageBox.alert(followUp.join('\n'), `${label}后续处理`, {
      type: 'info', confirmButtonText: '知道了', showClose: false
    }).catch(() => {})
  }
}

/**
 * 下载整个开发插件源码包，包含后端、平台端、商户端、移动端与资源文件。
 * @param {Object} plugin 当前开发插件卡片及服务端允许的操作。
 * @returns {Promise<void>} 下载启动或错误提示完成。
 */
async function handleDeveloperDownload(plugin) {
  if (plugin.source !== 'developer' || developerDownloadLoading[plugin.code]) {
    return
  }
  if (!plugin.canDownload) {
    ElMessage.warning(plugin.blockedReason || '当前插件源码不可验证，无法下载')
    return
  }
  developerDownloadLoading[plugin.code] = true
  try {
    const response = await downloadDeveloperPlugin(plugin.code)
    const blob = response?.data
    if (!(blob instanceof Blob)) {
      ElMessage.error('下载响应无效')
      return
    }
    const contentType = String(response?.headers?.['content-type'] || response?.headers?.['Content-Type'] || blob.type || '').toLowerCase()
    // 二进制接口不经过统一状态包装，只能按响应类型区分文件与后台错误。
    if (Number(response?.status || 200) >= 400 || contentType.includes('json')) {
      ElMessage.error(await resolveDeveloperDownloadError(blob))
      return
    }
    const objectUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = objectUrl
    link.download = resolveDownloadFilename(response, plugin)
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(objectUrl)
    ElMessage.success('下载已开始')
  } catch (error) {
    ElMessage.error(await resolveDeveloperDownloadError(error?.response?.data, error))
  } finally {
    // eslint-disable-next-line require-atomic-updates
    developerDownloadLoading[plugin.code] = false
  }
}

/**
 * 按操作类型生成二次确认文案与按钮，本地开发卸载与重装都会清理运营数据。
 * @param {Object} plugin 当前开发插件卡片。
 * @param {'install'|'uninstall'|'reinstall'} action 生命周期动作。
 * @returns {{message: string, confirmText: string}} 确认弹窗正文与按钮文案。
 */
function buildDeveloperConfirm(plugin, action) {
  if (action === 'install') {
    return { message: `确定安装「${plugin.name}」吗？`, confirmText: '确认安装' }
  }
  // 开发来源走本地测试口径：卸载必然清理运营数据，永久订单、支付、退款及财务数据由插件迁移声明保留。
  const scope = '订单、支付、退款及财务等永久数据仍保留。'
  if (action === 'reinstall') {
    const source = plugin.sourceChanged ? '当前开发源码与安装时不一致，将用当前源码替换已安装源码。' : ''
    return {
      message: `确定重新安装「${plugin.name}」吗？插件会先卸载再安装，卸载将清理运营数据，${source}${scope}`,
      confirmText: '确认清理并重装'
    }
  }
  return {
    message: `确定卸载「${plugin.name}」吗？本地开发卸载将清理运营数据，${scope}`,
    confirmText: '确认清理并卸载'
  }
}

/**
 * 从二进制下载失败响应中提取后台错误信息，无法解析时保留兜底提示。
 * @param {Blob|null} blob 后台返回的错误响应体。
 * @param {Object} error 请求异常；二进制错误响应已解析时可不传。
 * @returns {Promise<string>} 可直接展示的错误文案。
 */
async function resolveDeveloperDownloadError(blob, error = null) {
  if (blob instanceof Blob) {
    try {
      const parsed = JSON.parse(await blob.text())
      if (parsed?.message) {
        return String(parsed.message)
      }
    } catch (parseError) {
      // 非 JSON 错误体无法解析时保留兜底文案，不把错误响应保存为插件包。
    }
    return '下载失败，请稍后重试'
  }
  return String(error?.message || '插件下载失败')
}

/**
 * 开发操作完成后刷新当前开发列表，切回市场时不额外预取本地数据。
 * @returns {Promise<void>} 刷新完成。
 */
async function refreshDeveloperList() {
  if (isDeveloperMode.value) {
    await fetchCatalog()
  }
}

async function handlePrimaryAction(plugin, skuId = 0) {
  if (plugin.source === 'developer') {
    await handleInstall(plugin)
    return
  }
  if (!openAuthLoaded.value && !openAuth.value?.login) {
    await fetchOpenAuth()
  }
  if (!openAuth.value?.login) {
    // 购买前必须先登录开放平台，登录成功后继续本次购买。
    pendingBuy.value = { plugin, skuId }
    loginVisible.value = true
    return
  }

  // 授权在操作时按需加载，页面刷新后已有购买记录也不会重复下单。
  try {
    await fetchLicenseData()
  } catch (error) {
    ElMessage.error(error?.message || '授权加载失败')
    return
  }
  const mode = resolveActionMode(plugin, Boolean(getLicenseByCode(plugin.code)))
  if (mode === 'download') {
    await handleDownload(plugin)
    return
  }

  const title = mode === 'claim'
    ? '领取插件'
    : plugin.billingType === 'subscription'
      ? '订阅插件'
      : '创建订单'
  const actionText = mode === 'claim'
    ? '领取'
    : plugin.billingType === 'subscription'
      ? '订阅'
      : '购买'
  try {
    await ElMessageBox.confirm(`确定${actionText}「${plugin.name}」吗？`, title, {
      type: 'warning',
      confirmButtonText: actionText,
      cancelButtonText: '取消'
    })
  } catch (error) {
    return
  }

  const resolvedSkuId = Number(skuId || 0) || (await resolveSkuId(plugin))
  if (!resolvedSkuId) {
    ElMessage.warning('未找到可用的价格方案')
    return
  }

  const response = await createPluginOrder({
    product_id: plugin.id,
    sku_id: resolvedSkuId
  })
  const payload = readPayload(response)
  const order = payload?.order || {}
  if (Number(order.pay_amount || 0) > 0 && Number(order.status || 0) === 0) {
    // 待支付订单打开扫码支付弹窗，未登录时先登录再回到下单。
    payOrderInfo.value = {
      ...payload,
      order_no: String(order.order_no || ''),
      pay_amount: Number(order.pay_amount || 0),
      plugin_name: String(payload?.item?.plugin_name || payload?.sku?.name || plugin.name),
      billing_label: String(payload?.sku?.billing_label || '')
    }
    payDialogVisible.value = true
    ElMessage.success('订单已创建，请完成支付')
  } else {
    ElMessage.success(`已完成${actionText}`)
  }
  await refreshSidebarData()
  await fetchCatalog()
}

async function handleDownload(plugin) {
  const license = getLicenseByCode(plugin.code)
  if (!license) {
    ElMessage.warning('暂无可下载授权')
    return
  }

  // 先申请票据，再用票据取真实二进制文件，避免前端直链泄露下载地址。
  const ticketResponse = await requestPluginDownloadTicket({
    license_id: license.id
  })
  const ticket = String(readPayload(ticketResponse)?.ticket || '').trim()
  if (!ticket) {
    ElMessage.warning('下载票据无效')
    return
  }

  const response = await downloadPluginFile({ ticket })
  const blob = response?.data
  if (!(blob instanceof Blob)) {
    ElMessage.error('下载响应无效')
    return
  }

  const contentType = String(response?.headers?.['content-type'] || response?.headers?.['Content-Type'] || blob.type || '').toLowerCase()
  if (Number(response?.status || 200) >= 400 || contentType.includes('json')) {
    let message = '下载失败，请重新申请下载票据'
    if (contentType.includes('json')) {
      try {
        const error = JSON.parse(await blob.text())
        message = String(error?.message || message)
      } catch (error) {
        // 无法解析错误响应时保留兜底提示，不保存为插件包。
      }
    }
    ElMessage.error(message)
    return
  }

  const filename = resolveDownloadFilename(response, plugin)
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
  ElMessage.success('下载已开始')
}

async function handleOpenAuthLogout() {
  await ElMessageBox.confirm('确定退出插件市场登录吗？', '退出登录', {
    type: 'warning',
    confirmButtonText: '退出',
    cancelButtonText: '取消'
  })
  await logoutPluginOpenAuth()
  ElMessage.success('已退出登录')
  await refreshOpenAuth()
  await refreshSidebarData()
}

async function fetchOpenAuth() {
  try {
    const response = await getPluginOpenAuthInfo()
    const data = readPayload(response) || {}
    openAuth.value = {
      login: Boolean(data.login),
      uid: Number(data.uid || 0),
      user: (data.user && typeof data.user === 'object') ? data.user : {}
    }
    openAuthLoaded.value = true
  } catch (error) {
    openAuth.value = { login: false, uid: 0, user: {}}
    openAuthLoaded.value = false
  }
}

async function handleOpenAuthSuccess() {
  await refreshOpenAuth()
  await refreshSidebarData()
  const pending = pendingBuy.value
  pendingBuy.value = null
  if (pending) {
    await handlePrimaryAction(pending.plugin, pending.skuId)
  }
}

async function refreshOpenAuth() {
  await fetchOpenAuth()
}

/**
 * 按需刷新市场授权，供详情和购买动作识别已有授权。
 * @returns {Promise<void>} 授权状态刷新完成。
 */
async function fetchLicenseData() {
  const response = await getPluginLicenses()
  licenseList.value = normalizeArrayPayload(readPayload(response))
}

async function handlePaySuccess() {
  payDialogVisible.value = false
  ElMessage.success('授权已下发，可前往开发者中心查看')
  await refreshSidebarData()
  await fetchCatalog()
}

async function refreshSidebarData() {
  const tasks = await Promise.allSettled([
    getPluginBalance(),
    getPluginUserInfo(),
    getPluginLicenses(),
    getPluginDownloads(),
    getPluginOrders(),
    getPluginPublishList()
  ])

  if (tasks[0].status === 'fulfilled') {
    const data = readPayload(tasks[0].value) || {}
    balanceState.balance = Number(data.balance || 0)
    balanceState.login = Boolean(data.login)
  }

  if (tasks[1].status === 'fulfilled') {
    userInfo.value = readPayload(tasks[1].value) || null
  }

  if (tasks[2].status === 'fulfilled') {
    licenseList.value = normalizeArrayPayload(readPayload(tasks[2].value))
  }

  if (tasks[3].status === 'fulfilled') {
    downloadList.value = normalizeArrayPayload(readPayload(tasks[3].value))
  }

  if (tasks[4].status === 'fulfilled') {
    orderList.value = normalizeArrayPayload(readPayload(tasks[4].value))
  }

  if (tasks[5].status === 'fulfilled') {
    publishList.value = normalizeArrayPayload(readPayload(tasks[5].value))
  }
}

async function resolveSkuId(plugin) {
  if (plugin.skuId) {
    return plugin.skuId
  }
  const detail = await resolvePluginDetail(plugin)
  if (Array.isArray(detail?.skus) && detail.skus.length > 0) {
    return Number(detail.skus[0].id || 0)
  }
  return 0
}

async function resolvePluginDetail(plugin) {
  const cacheKey = plugin.code
  const cachedDetail = detailCache[cacheKey]
  if (cachedDetail) {
    return cachedDetail
  }
  const response = await getPluginCatalogDetail(plugin.code)
  const detail = readPayload(response) || {}
  // eslint-disable-next-line require-atomic-updates
  detailCache[cacheKey] = detail
  return detail
}

function resolveDownloadFilename(response, plugin) {
  const headers = response?.headers || {}
  const disposition = String(headers['content-disposition'] || headers['Content-Disposition'] || '')
  if (disposition.includes('filename=')) {
    const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)
    if (match?.[1]) {
      return decodeURIComponent(match[1])
    }
  }
  return `${plugin.code || 'plugin'}.zip`
}

</script>

<style scoped lang="scss">
.plugin-market-page {
  min-height: 100%;
  padding: 2px 6px 16px;
  box-sizing: border-box;
  background: #f6f8fc;
  color: var(--el-text-color-primary);
}

.market-hero {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 18px;
}

.market-hero__text {
  min-width: 0;
  padding-top: 2px;
}

.market-title {
  margin: 0;
  font-size: 20px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.market-subtitle {
  margin: 6px 0 0;
  font-size: 13px;
  line-height: 1.6;
  color: #667085;
}

.market-hero__search {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
  width: min(760px, 100%);
}

.market-hero__input {
  width: 100%;
}

.market-hero__search-button {
  min-width: 78px;
  height: 34px;
  border-radius: 8px;
}

.market-layout {
  display: grid;
  grid-template-columns: 244px minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}

.market-sidebar,
.market-main {
  min-width: 0;
  border: 1px solid #e7ecf4;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
}

.market-layout.is-developer {
  grid-template-columns: minmax(0, 1fr);
}

.market-load-error {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 18px 2px 0;
  min-width: 0;

  .el-alert {
    flex: 1;
    min-width: 0;
    overflow-wrap: anywhere;
  }
}

.market-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 18px 14px 14px;
  // 全局样式对 aside 标签有主题底色与内边距,分类栏需恢复为无背景、无多余留白。
  margin: 0;
  background: #ffffff;
  line-height: 1.4;
  font-size: 14px;
}

.market-sidebar__section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.market-sidebar__title {
  margin: 0;
  font-size: 16px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.market-category-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.market-category-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 11px 12px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #344054;
  text-align: left;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.market-category-item:hover {
  background: #f3f7ff;
  color: #2563eb;
}

.market-category-item.is-active {
  background: linear-gradient(90deg, #edf4ff 0%, #ffffff 100%);
  color: #2563eb;
}

.market-category-item__label {
  font-size: 14px;
  line-height: 1.4;
  font-weight: 500;
}

.market-category-item__count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 22px;
  padding: 0 7px;
  border-radius: 999px;
  background: #eef2f7;
  color: #98a2b3;
  font-size: 12px;
  font-weight: 600;
}

.market-category-item.is-active .market-category-item__count {
  background: #dbeafe;
  color: #2563eb;
}

.market-developer-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  padding: 16px;
  border-radius: 10px;
  background: linear-gradient(180deg, #f7fbff 0%, #edf4ff 100%);
}

.market-developer-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  color: #4f80ff;
  background: rgba(79, 128, 255, 0.12);

  .el-icon {
    font-size: 21px;
  }
}

.market-developer-card__title {
  font-size: 16px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.market-developer-card__desc {
  margin: 0;
  font-size: 12px;
  line-height: 1.7;
  color: #98a2b3;
}

.market-developer-card__metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  width: 100%;
}

.market-developer-card__metric {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 11px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.7);
}

.market-developer-card__metric-label {
  font-size: 11px;
  line-height: 1.2;
  color: #98a2b3;
}

.market-developer-card__metric-value {
  font-size: 15px;
  line-height: 1.2;
  font-weight: 700;
  color: #101828;
}

.market-developer-card__logout {
  height: auto;
  padding: 0;
  font-size: 12px;
}

.market-developer-card__button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  margin-top: 6px;
  padding: 10px 14px;
  border: 0;
  border-radius: 9px;
  background: linear-gradient(90deg, #ff7a3d 0%, #f75c1c 100%);
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  box-sizing: border-box;
  transition: opacity 0.2s ease;

  &:hover {
    opacity: 0.92;
  }
}

.market-developer-card__button-icon {
  font-size: 15px;
}

.market-main {
  padding: 0 16px 18px;
}

.market-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 0 2px;
  border-bottom: 1px solid #edf2f7;
}

.market-toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  min-width: 0;
}

.market-tabs {
  display: flex;
  align-items: flex-end;
  gap: 4px;
  min-width: 0;
  flex-wrap: wrap;
}

.market-tabs__item {
  position: relative;
  margin: 0;
  padding: 15px 10px 14px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #344054;
  font-size: 14px;
  line-height: 1.2;
  font-weight: 600;
  cursor: pointer;
}

.market-tabs__item:hover {
  color: #2563eb;
}

.market-tabs__item.is-active {
  color: #2563eb;
  border-bottom-color: #2563eb;
}

.market-sort {
  width: 126px;
  flex-shrink: 0;
}

.market-sort :deep(.el-select__wrapper) {
  min-height: 34px;
  border-radius: 8px;
  box-shadow: none;
}

.market-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  padding: 18px 2px 0;
}

.market-card {
  display: flex;
  flex-direction: column;
  min-height: 196px;
  padding: 18px 16px 16px;
  border: 1px solid #e8edf5;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
}

.market-card__header {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.market-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  flex-shrink: 0;

  .el-icon {
    font-size: 24px;
  }
}

.market-card__content {
  min-width: 0;
  flex: 1;
}

.market-card__title,
.market-card__title-button {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  text-align: left;
  font-size: 16px;
  line-height: 1.35;
  font-weight: 700;
  color: #101828;
  cursor: pointer;
}

.market-card__title-button:hover {
  color: #2563eb;
}

.market-card__title {
  cursor: default;
  overflow-wrap: anywhere;
}

.market-card__blocked {
  margin: 12px 0 0;
  color: var(--el-color-warning-dark-2);
  font-size: 12px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.market-card__developer-action {
  min-width: 78px;
  height: 30px;
  border-radius: 6px;
}

.market-card__rating {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 8px;
  min-width: 0;
  color: #667085;
  font-size: 12px;
}

.market-card__star {
  color: #f59e0b;
}

.market-card__score {
  font-weight: 700;
  color: #101828;
}

.market-card__reviews {
  color: #98a2b3;
}

.market-card__price {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 38px;
  height: 22px;
  margin-left: auto;
  padding: 0 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}

.market-card__price.is-free {
  background: #ebfdf2;
  color: #16a34a;
}

.market-card__price.is-paid {
  background: #eef4ff;
  color: #2563eb;
}

.market-card__flags,
.market-card__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}

.market-card__desc {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  min-height: 42px;
  margin: 14px 0 0;
  color: #667085;
  font-size: 13px;
  line-height: 1.7;
}

.market-card__actions {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  flex-shrink: 0;
}

.market-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: auto;
  padding-top: 14px;
}

.market-card__team {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  color: #98a2b3;
  font-size: 12px;

  .el-icon {
    color: #c0c6d4;
    font-size: 15px;
  }
}

.market-card__detail,
.market-card__install {
  min-width: 76px;
  height: 30px;
  margin: 0;
}

.market-card__install {
  border-radius: 7px;
  border-color: #4f80ff;
  color: #4f80ff;
}

.market-card__install:hover {
  border-color: #2563eb;
  color: #2563eb;
}

.market-card__install.is-plain {
  background: #ffffff;
}

.market-panel-drawer :deep(.el-drawer__body) {
  padding-top: 0;
}

.market-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.market-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.market-panel__title {
  margin: 0;
  font-size: 18px;
  line-height: 1.3;
  font-weight: 700;
  color: #101828;
}

.market-panel__desc {
  margin: 8px 0 0;
  font-size: 13px;
  line-height: 1.7;
  color: #667085;
}

.market-panel__section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.market-detail__section-title {
  font-size: 14px;
  line-height: 1.4;
  font-weight: 700;
  color: #101828;
}

.market-panel :deep(.el-descriptions__body) {
  background: #ffffff;
}

.market-empty {
  padding: 48px 0 36px;
}

.market-pagination {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  padding: 18px 2px 0;
}

.market-pagination__nav,
.market-pagination__item {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: 1px solid #e4e8f0;
  border-radius: 6px;
  background: #ffffff;
  color: #667085;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.market-pagination__nav:hover:not(:disabled),
.market-pagination__item:hover:not(.is-active) {
  border-color: #cbd5e1;
  color: #2563eb;
}

.market-pagination__nav:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.market-pagination__item.is-ellipsis {
  border-color: transparent;
  background: transparent;
  color: #98a2b3;
  box-shadow: none;
  cursor: default;
}

.market-pagination__item.is-active {
  border-color: #2563eb;
  background: #2563eb;
  color: #ffffff;
  box-shadow: 0 6px 14px rgba(37, 99, 235, 0.22);
}

@media (max-width: 1400px) {
  .market-layout {
    grid-template-columns: 228px minmax(0, 1fr);
  }

  .market-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 1100px) {
  .market-hero {
    flex-direction: column;
  }

  .market-hero__search {
    width: 100%;
  }

  .market-layout {
    grid-template-columns: 1fr;
  }

  .market-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .plugin-market-page {
    padding-inline: 0;
  }

  .market-main,
  .market-sidebar {
    border-radius: 10px;
  }

  .market-topbar {
    flex-direction: column;
    align-items: stretch;
    padding-bottom: 10px;
  }

  .market-sort {
    width: 100%;
  }

  .market-pagination {
    justify-content: center;
    flex-wrap: wrap;
  }
}
</style>
