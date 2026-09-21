<template>
  <div class="user-center-diy">
    <div class="editor-toolbar">
      <div class="toolbar-left">
        <span class="page-title">个人中心装修</span>
      </div>
      <div class="toolbar-right">
        <el-button plain :loading="loading" @click="loadConfig">刷新</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存配置</el-button>
      </div>
    </div>

    <div v-loading="loading" class="editor-body">
      <div class="preview-panel">
        <div class="panel-title">手机预览</div>
        <div class="panel-desc">“我的订单”“必备服务”和“广告位”支持点击设置，其他模块为固定业务预览。</div>
        <UserCenterPreview
          :service-menu="serviceMenu"
          :order-menu="orderMenu"
          :banner="banner"
          :selected-key="selectedKey"
          @select="handleSelect"
        />
      </div>

      <div class="setting-panel">
        <div class="panel-header">
          <div>
            <div class="panel-title">配置面板</div>
            <div class="panel-desc">当前：{{ selectedTitle }}</div>
          </div>
        </div>

        <div v-if="selectedKey === 'service_menu'" class="settings-block">
          <div class="block-title">必备服务菜单</div>
          <UserServiceMenuEditor :config="serviceMenu" @change="handleServiceMenuChange" />
        </div>
        <div v-else-if="selectedKey === 'order_menu'" class="settings-block">
          <div class="block-title">我的订单</div>
          <UserOrderMenuEditor :config="orderMenu" @change="handleOrderMenuChange" />
        </div>
        <div v-else-if="selectedKey === 'banner'" class="settings-block">
          <div class="block-title">个人中心广告</div>
          <UserBannerEditor :config="banner" @change="handleBannerChange" />
        </div>
        <div v-else class="empty-tip">
          <LegacyIcon name="el-icon-mouse" />
          <p>点击左侧预览中的“我的订单”“必备服务”或“广告位”进行设置</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getUserCenterDiyConfig, saveUserCenterDiyConfig } from '@/api/diy'
import UserCenterPreview from './components/UserCenterPreview.vue'
import UserServiceMenuEditor from './components/editors/UserServiceMenuEditor.vue'
import UserOrderMenuEditor from './components/editors/UserOrderMenuEditor.vue'
import UserBannerEditor from './components/editors/UserBannerEditor.vue'
import orderNoAddressImage from '@/assets/images/diy/user-center/order_no_adderss.png'
import orderNoAssessImage from '@/assets/images/diy/user-center/order_no_assess.png'
import orderNoPayImage from '@/assets/images/diy/user-center/order_no_pay.png'
import orderNoRefundImage from '@/assets/images/diy/user-center/order_no_refund.png'
import orderNoShipImage from '@/assets/images/diy/user-center/order_no_ship.png'

const DEFAULT_BACKEND_ORDER_IMAGE_BASE = '/admin/static/img/diy/user-center/'
const DEFAULT_ORDER_IMAGE_MAP = {
  'pending-payment': {
    local: orderNoPayImage,
    backend: DEFAULT_BACKEND_ORDER_IMAGE_BASE + 'order_no_pay.png',
    icon: 'icon-moreandroid'
  },
  'pending-shipment': {
    local: orderNoShipImage,
    backend: DEFAULT_BACKEND_ORDER_IMAGE_BASE + 'order_no_ship.png',
    icon: 'icon-send'
  },
  'pending-receipt': {
    local: orderNoAddressImage,
    backend: DEFAULT_BACKEND_ORDER_IMAGE_BASE + 'order_no_adderss.png',
    icon: 'icon-order'
  },
  'pending-review': {
    local: orderNoAssessImage,
    backend: DEFAULT_BACKEND_ORDER_IMAGE_BASE + 'order_no_assess.png',
    icon: 'icon-evaluate'
  },
  'after-sale': {
    local: orderNoRefundImage,
    backend: DEFAULT_BACKEND_ORDER_IMAGE_BASE + 'order_no_refund.png',
    icon: 'icon-refund'
  }
}

function createDefaultServiceMenu() {
  return {
    title: '必备服务',
    columns: 4,
    items: []
  }
}

function createDefaultOrderMenu() {
  return {
    title: '我的订单',
    items: [
      { key: 'pending-payment', name: '待付款', icon: 'icon-moreandroid', image: orderNoPayImage },
      { key: 'pending-shipment', name: '待发货', icon: 'icon-send', image: orderNoShipImage },
      { key: 'pending-receipt', name: '待收货', icon: 'icon-order', image: orderNoAddressImage },
      { key: 'pending-review', name: '待评价', icon: 'icon-evaluate', image: orderNoAssessImage },
      { key: 'after-sale', name: '售后', icon: 'icon-refund', image: orderNoRefundImage }
    ]
  }
}

function createDefaultBanner() {
  return {
    image: '',
    title: '夏季狂欢购物节',
    desc: '全场低至 5 折起',
    link_type: 'page',
    link_url: '',
    link_id: 0,
    link_name: '',
    platform_type: ''
  }
}

function normalizeImagePath(value) {
  return String(value || '').trim().split('#')[0].split('?')[0]
}

function isDefaultOrderImage(key, image) {
  const imageConfig = DEFAULT_ORDER_IMAGE_MAP[key]
  const imagePath = normalizeImagePath(image)
  if (!imageConfig || !imagePath) {
    return false
  }

  const localImagePath = normalizeImagePath(imageConfig.local)
  const backendImagePath = normalizeImagePath(imageConfig.backend)
  return imagePath === localImagePath || imagePath === backendImagePath || imagePath.endsWith(backendImagePath)
}

defineOptions({
  name: 'UserCenterDiy'
})

const router = useRouter()
const { proxy } = getCurrentInstance()
const loading = ref(false)
const saving = ref(false)
const selectedKey = ref('service_menu')
const pageForm = reactive({
  page_name: '个人中心默认装修',
  page_title: '个人中心',
  status: 1,
  global_config: {}
})
const serviceMenu = reactive(createDefaultServiceMenu())
const orderMenu = reactive(createDefaultOrderMenu())
const banner = reactive(createDefaultBanner())
const selectedTitle = computed(() => {
  const map = { service_menu: '必备服务菜单', order_menu: '我的订单', banner: '个人中心广告' }
  return map[selectedKey.value] || '未选择'
})

function resetPageForm() {
  Object.assign(pageForm, {
    page_name: '个人中心默认装修',
    page_title: '个人中心',
    status: 1,
    global_config: {}
  })
}

function resetServiceMenu(config = {}) {
  Object.assign(serviceMenu, {
    ...createDefaultServiceMenu(),
    ...(config || {})
  })
}

function resetOrderMenu(config = {}) {
  Object.assign(orderMenu, {
    ...createDefaultOrderMenu(),
    ...(config || {})
  })
}

function resetBanner(config = {}) {
  Object.assign(banner, {
    ...createDefaultBanner(),
    ...(config || {})
  })
}

async function loadConfig() {
  loading.value = true
  try {
    const res = await getUserCenterDiyConfig()
    const data = res.data || {}
    const page = data.page || {}
    Object.assign(pageForm, {
      page_name: page.page_name || '个人中心默认装修',
      page_title: page.page_title || '个人中心',
      status: page.status !== undefined ? page.status : 1,
      global_config: page.global_config || {}
    })
    resetServiceMenu(data.service_menu || {})
    resetOrderMenu(data.order_menu || {})
    resetBanner(data.banner || {})
  } catch (error) {
    console.error(error)
    proxy.$message.error('加载个人中心装修配置失败')
  } finally {
    loading.value = false
  }
}

function handleSelect(key) {
  selectedKey.value = key
}

function handleServiceMenuChange(config) {
  resetServiceMenu(config || {})
}

function handleOrderMenuChange(config) {
  resetOrderMenu(config || {})
}

function handleBannerChange(config) {
  resetBanner(config || {})
}

async function handleSave() {
  saving.value = true
  try {
    await saveUserCenterDiyConfig(buildSavePayload())
    proxy.$message.success('保存成功')
    await loadConfig()
  } catch (error) {
    console.error(error)
  } finally {
    saving.value = false
  }
}

function buildSavePayload() {
  return {
    ...pageForm,
    service_menu: serviceMenu,
    order_menu: buildOrderMenuPayload(orderMenu),
    banner
  }
}

function buildOrderMenuPayload(orderMenuValue = {}) {
  const items = Array.isArray(orderMenuValue.items) ? orderMenuValue.items : []

  return {
    title: orderMenuValue.title || '我的订单',
    items: items.map((item) => {
      const nextItem = {
        key: item.key,
        name: item.name,
        icon: item.icon,
        image: item.image
      }

      if (isDefaultOrderImage(nextItem.key, nextItem.image)) {
        delete nextItem.image
        if (nextItem.icon === DEFAULT_ORDER_IMAGE_MAP[nextItem.key].icon) {
          nextItem.icon = ''
        }
      }

      return nextItem
    })
  }
}

function handleBack() {
  router.back()
}

onBeforeMount(() => {
  void loadConfig()
})
</script>

<style lang="scss" scoped>
.user-center-diy {
  min-height: 100vh;
  background: var(--dashboard-bg);
  color: var(--dashboard-text-primary);
}

.editor-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  padding: 0 24px;
  background: var(--dashboard-surface);
  border-bottom: 1px solid var(--dashboard-border);
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
}

.toolbar-left, .toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back-btn {
  color: var(--dashboard-text-secondary);
  cursor: pointer;
  font-size: 14px;
}

.back-btn:hover {
  color: var(--dashboard-accent);
}

.toolbar-divider {
  width: 1px;
  height: 18px;
  background: var(--dashboard-border);
}

.page-title {
  color: var(--dashboard-text-primary);
  font-size: 18px;
  font-weight: 600;
}

.editor-body {
  display: grid;
  grid-template-columns: 460px minmax(0, 1fr);
  gap: 20px;
  padding: 20px 0 0;
}

.preview-panel, .setting-panel {
  min-height: calc(100vh - 96px);
  padding: 18px;
  border-radius: 18px;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  box-shadow: 0 12px 30px rgba(31, 41, 55, 0.08);
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.panel-title {
  color: var(--dashboard-text-primary);
  font-size: 18px;
  font-weight: 600;
}

.panel-desc {
  margin-top: 6px;
  color: var(--dashboard-text-muted);
  font-size: 12px;
  line-height: 1.5;
}

.settings-block {
  padding: 4px 0;
  width: 50%;
}

.block-title {
  margin-bottom: 16px;
  color: var(--dashboard-text-primary);
  font-size: 15px;
  font-weight: 600;
}

.empty-tip {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 420px;
  color: var(--dashboard-text-muted);
  border: 1px dashed var(--dashboard-border);
  border-radius: 16px;
  background: var(--dashboard-surface-muted);
}

.empty-tip i {
  margin-bottom: 12px;
  font-size: 34px;
  color: var(--dashboard-accent);
}
</style>
