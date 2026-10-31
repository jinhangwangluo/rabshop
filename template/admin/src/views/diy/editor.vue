<template>
  <div class="diy-editor">
    <!-- 顶部工具栏 -->
    <div class="editor-toolbar">
      <div class="toolbar-left">
        <span class="back-btn" @click="handleBack">
          <LegacyIcon name="el-icon-arrow-left" /> 返回
        </span>
        <span class="toolbar-divider" />
        <span class="page-title" @click="handleEditTitle">
          当前页面：{{ localPageTitle || '未命名页面' }}
          <LegacyIcon name="el-icon-edit-outline" />
        </span>
      </div>
      <div class="toolbar-right">
        <el-button plain class="toolbar-btn" :loading="saving" @click="handleSave(false)">仅保存</el-button>
        <el-button class="toolbar-btn-primary" :loading="saving" @click="handleSave(true)">保存关闭</el-button>
      </div>
    </div>

    <div class="editor-body">
      <!-- 左侧组件库面板 -->
      <div class="left-panel">
        <div class="component-groups">
          <div
            v-for="category in componentTypes"
            :key="category.key"
            class="component-group"
          >
            <div class="group-title">
              {{ category.name }}
              <LegacyIcon name="el-icon-arrow-down" class=" group-arrow" />
            </div>
            <div class="component-grid">
              <div
                v-for="item in category.types"
                :key="item.value"
                class="component-item"
                @click="handleAddComponent(item.value)"
              >
                <div class="component-icon">
                  <LegacyIcon :name="getComponentIcon(item.value)" />
                </div>
                <span class="component-label">{{ item.label }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 中间手机预览 -->
      <div class="center-panel">
        <div class="phone-wrapper">
          <PhonePreview
            v-model="componentList"
            :global-config="globalConfig"
            :theme-config="mobileThemeConfig"
            :selectable="true"
            :selected-id="selectedComponentId"
            :mobile-page-components="MPAGE_MAP"
            @select="handleSelectComponent"
            @deselect="handleDeselectComponent"
            @toggle-visible="handleToggleComponentVisible"
            @move-up="handleMoveComponentUp"
            @move-down="handleMoveComponentDown"
            @copy="handleCopyComponent"
            @remove="handleRemoveComponent"
          />
        </div>
      </div>

      <!-- 右侧设置面板 -->
      <div class="right-panel">
        <!-- 顶部操作区 -->
        <div class="right-panel-header">
          <div class="right-panel-actions">
            <span
              class="action-btn"
              :class="{ 'action-btn-active': rightTab === 'page' }"
              @click="rightTab = 'page'; selectedComponentId = null"
            >页面设置</span>
            <span
              v-if="selectedComponent"
              class="action-btn"
              :class="{ 'action-btn-active': rightTab === 'component' }"
              @click="rightTab = 'component'"
            >组件设置</span>
          </div>
          <div class="right-panel-ops">
            <el-button type="text" size="mini" class="btn-reset" @click="handleReset">重置</el-button>
          </div>
        </div>

        <!-- 页面设置内容 -->
        <div v-if="rightTab === 'page'" class="right-panel-body">
          <div class="settings-block">
            <div class="block-title">页面信息</div>
            <el-form :model="pageForm" label-width="72px">
              <el-form-item label="页面标题">
                <el-input v-model="pageForm.page_title" placeholder="请输入页面标题" />
              </el-form-item>
              <el-form-item label="页面名称">
                <el-input v-model="pageForm.page_name" placeholder="请输入页面名称" />
              </el-form-item>
              <el-form-item label="状态">
                <el-switch v-model="pageForm.status" :active-value="1" :inactive-value="0" />
              </el-form-item>
            </el-form>
          </div>
          <div class="settings-block">
            <div class="block-title">背景与标题栏</div>
            <GlobalConfig :config="globalConfig" @change="handleGlobalConfigChange" />
          </div>
        </div>

        <!-- 组件设置内容 -->
        <div v-else-if="rightTab === 'component'" class="right-panel-body">
          <div v-if="selectedComponent">
            <div class="settings-block">
              <div class="block-title">
                {{ getComponentName(selectedComponent.component_type) }}
                <el-button type="text" size="mini" class="btn-delete fr" @click="handleRemoveComponent(selectedComponent)">
                  <LegacyIcon name="el-icon-delete" /> 删除
                </el-button>
              </div>

              <!-- 内容 / 样式 子 Tab -->
              <div class="component-sub-tabs">
                <span
                  class="sub-tab"
                  :class="{ 'sub-tab-active': componentTab === 'content' }"
                  @click="componentTab = 'content'"
                >内容</span>
                <span
                  class="sub-tab"
                  :class="{ 'sub-tab-active': componentTab === 'style' }"
                  @click="componentTab = 'style'"
                >样式</span>
              </div>

              <!-- 内容 Tab -->
              <div v-show="componentTab === 'content'">
                <component
                  :is="getEditorComponent(selectedComponent.component_type)"
                  v-if="getEditorComponent(selectedComponent.component_type)"
                  :config="selectedComponent.component_data"
                  :is-last="isSelectedLast"
                  @change="handleComponentConfigChange"
                />
                <div v-else class="no-editor-tip">
                  <p>该组件暂无可编辑配置项</p>
                  <pre>{{ JSON.stringify(selectedComponent.component_data || {}, null, 2) }}</pre>
                </div>
              </div>

              <!-- 样式 Tab -->
              <div v-show="componentTab === 'style'">
                <ComponentStyleEditor
                  :config="getStyleEditorConfig(selectedComponent)"
                  :component-type="selectedComponent.component_type"
                  @change="handleStyleConfigChange"
                />
              </div>
            </div>
          </div>
          <div v-else class="no-selection-tip">
            点击预览区组件进行设置
          </div>
        </div>
      </div>
    </div>

    <!-- 预览弹窗 -->
    <el-dialog v-model="previewVisible" title="页面预览" width="420px" :close-on-click-modal="false">
      <PhonePreview
        :model-value="componentList"
        :global-config="globalConfig"
        :theme-config="mobileThemeConfig"
        :selectable="false"
        :mobile-page-components="MPAGE_MAP"
      />
      <template #footer>
        <el-button @click="previewVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 编辑页面标题弹窗 -->
    <el-dialog v-model="titleDialogVisible" title="编辑页面标题" width="400px">
      <el-form label-width="80px">
        <el-form-item label="页面标题">
          <el-input v-model="localPageTitle" placeholder="请输入页面标题" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="titleDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleTitleConfirm">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PhonePreview from './preview/PhonePreview.vue'
import GlobalConfig from './components/GlobalConfig.vue'
import ComponentStyleEditor from './components/ComponentStyleEditor.vue'
import SearchEditor from './components/editors/SearchEditor.vue'
import BannerEditor from './components/editors/BannerEditor.vue'
import NavigationEditor from './components/editors/NavigationEditor.vue'
import NoticeEditor from './components/editors/NoticeEditor.vue'
import ImageCubeEditor from './components/editors/ImageCubeEditor.vue'
import HotZoneEditor from './components/editors/HotZoneEditor.vue'
import TitleEditor from './components/editors/TitleEditor.vue'
import LiveEditor from './components/editors/LiveEditor.vue'
import ContentEditor from './components/editors/ContentEditor.vue'
import SpecialEditor from './components/editors/SpecialEditor.vue'
import ProductEditor from './components/editors/ProductEditor.vue'
import SeckillEditor from '@/plugins/seckill/components/SeckillEditor.vue'
import SeckillPreview from '@/plugins/seckill/components/SeckillPreview.vue'
import CouponEditor from './components/editors/CouponEditor.vue'
import RichTextEditor from './components/editors/RichTextEditor.vue'
import BlankEditor from './components/editors/BlankEditor.vue'

// Admin mobilePage 组件（用于预览区渲染）
import Banner from '@/components/mobilePage/Banner.vue'
import SearchBox from '@/components/mobilePage/SearchBox.vue'
import Navigation from '@/components/mobilePage/Navigation.vue'
import Notice from '@/components/mobilePage/Notice.vue'
import ImageCube from '@/components/mobilePage/ImageCube.vue'
import Coupon from '@/components/mobilePage/Coupon.vue'
import TitleText from '@/components/mobilePage/Title.vue'
import RichText from '@/components/mobilePage/RichText.vue'
import Blank from '@/components/mobilePage/Blank.vue'
import Special from '@/components/mobilePage/Special.vue'
import Live from '@/components/mobilePage/Live.vue'
import Content from '@/components/mobilePage/Content.vue'
import Product from '@/components/mobilePage/Product.vue'
import HotZone from '@/components/mobilePage/HotZone.vue'

import {
  getDiyPageDetail,
  updateDiyPage,
  getComponentTypes,
  getThemeConfigApi,
  batchSortDiyComponent,
  saveAsTemplate,
  getComponentsByPageId,
  saveAllComponents
} from '@/api/diy'

const COMPONENT_EDITOR_MAP = {
  search: SearchEditor,
  banner: BannerEditor,
  navigation: NavigationEditor,
  notice: NoticeEditor,
  image_cube: ImageCubeEditor,
  hot_zone: HotZoneEditor,
  title: TitleEditor,
  live: LiveEditor,
  content: ContentEditor,
  special: SpecialEditor,
  product: ProductEditor,
  seckill: SeckillEditor,
  coupon: CouponEditor,
  rich_text: RichTextEditor,
  blank: BlankEditor
}

const COMPONENT_NAMES = {
  search: '搜索框',
  banner: '轮播图',
  navigation: '导航',
  notice: '公告',
  image_cube: '图片魔方',
  hot_zone: '热区',
  title: '标题',
  live: '直播',
  content: '内容',
  special: '专题',
  product: '商品',
  coupon: '优惠券',
  rich_text: '富文本',
  blank: '空白',
  bargain: '限时折扣',
  pink: '拼团',
  seckill: '秒杀',
  comb: '组合优惠',
  service: '客服',
  good_recommend: '商品推荐',
  new_list: '好物推荐',
  hot: '热门推荐',
  reviews: '评价',
  nav_bar: '导航栏'
}

const COMPONENT_ICONS = {
  search: 'el-icon-search',
  banner: 'el-icon-picture',
  navigation: 'el-icon-menu',
  notice: 'el-icon-bell',
  image_cube: 'el-icon-s-grid',
  hot_zone: 'el-icon-location',
  title: 'el-icon-document',
  live: 'el-icon-video-camera',
  content: 'el-icon-reading',
  special: 'el-icon-collection',
  product: 'el-icon-goods',
  coupon: 'el-icon-s-ticket',
  rich_text: 'el-icon-edit',
  blank: 'el-icon-minus',
  bargain: 'el-icon-price-tag',
  pink: 'el-icon-user-solid',
  seckill: 'el-icon-timer',
  comb: 'el-icon-s-goods',
  service: 'el-icon-service',
  good_recommend: 'el-icon-star-on',
  new_list: 'el-icon-goods',
  hot: 'el-icon-trophy',
  reviews: 'el-icon-chat-dot-round',
  nav_bar: 'el-icon-s-grid'
}

const DEFAULT_GLOBAL_CONFIG = {
  background: { type: 'color', color: '#f5f5f5' },
  title_bar: { show: true, title: '首页', bg_color: '#ffffff', text_color: '#333' },
  page_padding: 24
}

const DEFAULT_MOBILE_THEME_CONFIG = {
  theme_key: 'orange',
  theme_name: '活力橙',
  primary_color: '#ea580c',
  secondary_color: '#fb923c',
  gradient_color: 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'
}

const IMAGE_RADIUS_FIELDS = ['image_radius', 'image_radius_lt', 'image_radius_rt', 'image_radius_lb', 'image_radius_rb']

const DEFAULT_COMPONENT_STYLE_CONFIG = {
  image_radius_mode: 'all',
  image_radius: 0,
  image_radius_lt: 0,
  image_radius_rt: 0,
  image_radius_lb: 0,
  image_radius_rb: 0,
  bg_color_start: '#ffffff',
  bg_color_end: '#ffffff',
  bottom_bg_color: '#f5f5f5',
  padding_lr: 0,
  padding_x: 10,
  radius_mode: 'all',
  border_radius: 0
}

const FIXED_CONTENT_CONFIG = {
  layout: 'list',
  show_cover: true,
  show_desc: true,
  show_price: true
}

const LOAD_MORE_COMPONENT_TYPES = ['content', 'special', 'product']

const DEFAULT_SEARCH_CONFIG = {
  placeholder: '搜索课程、专题、直播',
  show_left_icon: true,
  logoConfig: { url: '' },
  shape: 'rounded',
  box_bg_color: '#ffffff',
  text_color: '#8a8f98',
  link_type: 'page',
  link_url: '/pages/search/index',
  link_id: 0,
  link_name: '',
  platform_type: ''
}

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function pickConfigValue(value, fallback = '') {
  if (value === undefined || value === null) {
    return fallback
  }
  if (isPlainObject(value)) {
    if (value.value !== undefined) return value.value
    if (value.val !== undefined) return value.val
    if (value.tabVal !== undefined) return value.tabVal
  }
  return value
}

function firstConfigColor(value, fallback = '') {
  if (typeof value === 'string') {
    return value || fallback
  }
  if (Array.isArray(value)) {
    return firstConfigColor(value[0], fallback)
  }
  if (isPlainObject(value)) {
    return firstConfigColor(value.item || value.color, fallback)
  }
  return fallback
}

function normalizeSwitchValue(value, defaultValue = true) {
  if (value === undefined || value === null || value === '') {
    return defaultValue
  }
  if (value === true || value === 'true' || value === 1 || value === '1') {
    return true
  }
  if (value === false || value === 'false' || value === 0 || value === '0') {
    return false
  }
  return Boolean(value)
}

function normalizeIdList(value) {
  if (typeof value === 'string') {
    value = value.split(',')
  }
  if (!Array.isArray(value)) {
    value = [value]
  }
  const ids = value.map(item => {
    const id = item && typeof item === 'object' ? item.id || item.value : item
    return Number(id || 0)
  }).filter(id => id > 0)
  return Array.from(new Set(ids))
}

function normalizeCategoryTabsValue(tabs, fallbackIds) {
  const source = Array.isArray(tabs) && tabs.length ? tabs : normalizeIdList(fallbackIds)
  return source.map(item => {
    if (item && typeof item === 'object') {
      return {
        id: Number(item.id || item.value || 0),
        name: item.name || item.label || ''
      }
    }
    return {
      id: Number(item || 0),
      name: ''
    }
  }).filter(item => item.id > 0)
}

function normalizeCategoryTabsConfig(config) {
  const categoryIds = normalizeIdList(config.category_ids || config.category_tabs || [])
  return {
    ...config,
    category_ids: categoryIds,
    category_tabs: normalizeCategoryTabsValue(config.category_tabs || [], categoryIds)
  }
}

function normalizeProductCategoryTabsValue(tabs, fallbackIds) {
  const source = Array.isArray(tabs) && tabs.length ? tabs : normalizeIdList(fallbackIds)
  return source.map(item => {
    if (item && typeof item === 'object') {
      return {
        id: Number(item.id || item.value || 0),
        name: item.name || item.label || '',
        subtitle: item.subtitle || '',
        image: item.image || ''
      }
    }
    return {
      id: Number(item || 0),
      name: '',
      subtitle: '',
      image: ''
    }
  }).filter(item => item.id > 0)
}

function normalizeManualProductItems(value) {
  const source = Array.isArray(value) ? value : (value ? [value] : [])
  const seen = new Set()
  return source.map(item => {
    const row = item && typeof item === 'object' ? item : { id: item }
    const id = Number(row.id || row.value || 0)
    return {
      id,
      name: row.name || row.title || '',
      title: row.title || row.name || '',
      cover: row.cover || row.image || '',
      image: row.image || row.cover || '',
      price: row.price || 0,
      sales: Number(row.sales || 0),
      virtual_sales: Number(row.virtual_sales || 0),
      display_sales: Number(row.display_sales || 0)
    }
  }).filter(item => {
    if (item.id <= 0 || seen.has(item.id)) {
      return false
    }
    seen.add(item.id)
    return true
  })
}

function normalizeProductConfig(config) {
  const normalizedConfig = normalizeComponentConfig(config)
  const withNav = normalizeSwitchValue(normalizedConfig.with_nav, true)
  const layout = normalizedConfig.layout === 'list' ? 'list' : 'grid'
  const tabStyle = ['subtitle', 'arc', 'pill', 'image'].includes(normalizedConfig.tab_style)
    ? normalizedConfig.tab_style
    : 'subtitle'
  const sourceType = ['manual', 'category', 'tag', 'brand'].includes(normalizedConfig.source_type)
    ? normalizedConfig.source_type
    : 'manual'
  const configuredTabs = normalizeProductCategoryTabsValue(normalizedConfig.category_tabs || [], [])
  const configuredCategoryIds = normalizeIdList(normalizedConfig.category_ids || [])
  const categoryIds = configuredCategoryIds.length ? configuredCategoryIds : normalizeIdList(configuredTabs)
  const categoryTabs = configuredTabs.length
    ? configuredTabs
    : normalizeProductCategoryTabsValue([], categoryIds)

  return {
    ...normalizedConfig,
    title: normalizedConfig.title || '好物推荐',
    layout,
    with_nav: withNav,
    tab_style: tabStyle,
    category_ids: withNav ? categoryIds : [],
    category_tabs: withNav
      ? categoryTabs
      : [],
    source_type: withNav ? 'category' : sourceType,
    data_source: withNav ? 'category_tabs' : sourceType,
    manual_ids: normalizeManualProductItems(normalizedConfig.manual_ids || []),
    category_id: Number(normalizedConfig.category_id || 0),
    tag_id: Number(normalizedConfig.tag_id || 0),
    brand_id: Number(normalizedConfig.brand_id || 0),
    show_count: Math.max(1, Math.min(50, Number(normalizedConfig.show_count || 6))),
    load_more: normalizedConfig.load_more === true || Number(normalizedConfig.load_more || 0) === 1,
    sort_by: ['default', 'sales', 'price'].includes(normalizedConfig.sort_by) ? normalizedConfig.sort_by : 'default',
    bg_color: normalizedConfig.bg_color || '#ffffff'
  }
}

function hasExplicitImageRadius(styleConfig = {}) {
  if (!styleConfig || typeof styleConfig !== 'object') {
    return false
  }

  return IMAGE_RADIUS_FIELDS.some((key) => Object.prototype.hasOwnProperty.call(styleConfig, key)) ||
    Object.prototype.hasOwnProperty.call(styleConfig, 'image_radius_mode')
}

function getLegacyBannerImageRadius(filletImg = {}) {
  if (!filletImg || typeof filletImg !== 'object') {
    return {}
  }

  const isEachMode = Number(filletImg.type) === 1
  const valList = Array.isArray(filletImg.valList) ? filletImg.valList : []

  if (isEachMode && valList.length === 4) {
    return {
      image_radius_mode: 'each',
      image_radius_lt: Number(valList[0] && valList[0].val) || 0,
      image_radius_rt: Number(valList[1] && valList[1].val) || 0,
      image_radius_lb: Number(valList[2] && valList[2].val) || 0,
      image_radius_rb: Number(valList[3] && valList[3].val) || 0
    }
  }

  return {
    image_radius_mode: 'all',
    image_radius: Number(filletImg.val) || 0
  }
}

defineOptions({
  name: 'DiyEditor'
})

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()

const pageId = ref(null)
const loading = ref(false)
const saving = ref(false)
const initialData = ref(null)
const pageForm = reactive({
  page_name: '',
  page_title: '',
  status: 1
})
const localPageTitle = ref('')
const globalConfig = reactive(JSON.parse(JSON.stringify(DEFAULT_GLOBAL_CONFIG)))
const mobileThemeConfig = reactive({ ...DEFAULT_MOBILE_THEME_CONFIG })
const componentList = ref([])
const componentTypes = ref([])
const selectedComponentId = ref(null)
const rightTab = ref('page')
const componentTab = ref('content')
const previewVisible = ref(false)
const titleDialogVisible = ref(false)

const MPAGE_MAP = {
  search: SearchBox,
  banner: Banner,
  navigation: Navigation,
  notice: Notice,
  image_cube: ImageCube,
  coupon: Coupon,
  title: TitleText,
  rich_text: RichText,
  blank: Blank,
  special: Special,
  live: Live,
  content: Content,
  product: Product,
  seckill: SeckillPreview,
  hot_zone: HotZone
}

const selectedComponent = computed(() => {
  if (!selectedComponentId.value) return null
  return componentList.value.find(c => c.id === selectedComponentId.value) || null
})

const isSelectedLast = computed(() => {
  if (!selectedComponentId.value) return false
  const idx = componentList.value.findIndex(c => c.id === selectedComponentId.value)
  return idx === componentList.value.length - 1
})

function isLoadMoreComponentType(type = '') {
  return LOAD_MORE_COMPONENT_TYPES.indexOf(type) > -1
}

function isCategoryTabsLoadMoreDisabled(type = '', data = {}) {
  if (type === 'product') {
    return false
  }
  return data.display_style === 'category_tabs' || data.style === 'category_tabs' || data.data_source === 'category_tabs'
}

function shouldEnableLoadMore(component = {}, index = 0, length = 0) {
  const type = component.component_type || ''
  const data = component.component_data && typeof component.component_data === 'object' ? component.component_data : {}
  return isLoadMoreComponentType(type) &&
    index === length - 1 &&
    !isCategoryTabsLoadMoreDisabled(type, data)
}

function normalizeComponentLoadMore(component = {}, index = 0, length = 0) {
  const type = component.component_type || ''
  if (!isLoadMoreComponentType(type)) {
    return component
  }

  const data = component.component_data && typeof component.component_data === 'object' ? component.component_data : {}
  const loadMore = shouldEnableLoadMore(component, index, length)
  if (data.load_more === loadMore) {
    return component
  }

  return {
    ...component,
    component_data: {
      ...data,
      load_more: loadMore
    }
  }
}

function replaceReactiveObject(target, source) {
  Object.keys(target).forEach((key) => {
    delete target[key]
  })
  Object.assign(target, source)
}

async function loadComponentTypes() {
  try {
    const res = await getComponentTypes()
    componentTypes.value = res.data || []
  } catch (e) {
    console.error(e)
  }
}

async function loadMobileThemeConfig() {
  try {
    const res = await getThemeConfigApi()
    const config = res.data && res.data.config ? res.data.config : {}
    applyMobileThemeConfig(config)
  } catch (e) {
    console.error(e)
    applyMobileThemeConfig(DEFAULT_MOBILE_THEME_CONFIG)
  }
}

function applyMobileThemeConfig(config = {}) {
  Object.assign(mobileThemeConfig, {
    ...DEFAULT_MOBILE_THEME_CONFIG,
    ...(config && typeof config === 'object' && !Array.isArray(config) ? config : {})
  })
}

function getDefaultGlobalConfig() {
  return JSON.parse(JSON.stringify(DEFAULT_GLOBAL_CONFIG))
}

function normalizeGlobalConfig(config) {
  const next = {
    ...getDefaultGlobalConfig(),
    ...(config && typeof config === 'object' && !Array.isArray(config) ? config : {})
  }
  next.background = {
    ...DEFAULT_GLOBAL_CONFIG.background,
    ...(next.background && typeof next.background === 'object' ? next.background : {})
  }
  if (next.background.type === 'color' && !next.background.color) {
    next.background.color = '#f5f5f5'
  }
  next.title_bar = {
    ...DEFAULT_GLOBAL_CONFIG.title_bar,
    ...(next.title_bar && typeof next.title_bar === 'object' ? next.title_bar : {})
  }
  return next
}

function normalizeComponentConfig(config) {
  const componentConfig = (config && typeof config === 'object' && !Array.isArray(config)) ? config : {}
  const rawStyleConfig = (componentConfig.style_config && typeof componentConfig.style_config === 'object') ? componentConfig.style_config : {}
  const styleConfig = {
    ...DEFAULT_COMPONENT_STYLE_CONFIG,
    ...rawStyleConfig
  }

  if (componentConfig.filletImg && !hasExplicitImageRadius(rawStyleConfig)) {
    Object.assign(styleConfig, getLegacyBannerImageRadius(componentConfig.filletImg))
  }

  return {
    ...componentConfig,
    style_config: styleConfig
  }
}

function normalizeTypedComponentConfig(type, config) {
  const normalizedConfig = normalizeComponentConfig(config)
  if (type === 'search') {
    const logoConfig = isPlainObject(normalizedConfig.logoConfig) ? normalizedConfig.logoConfig : {}
    const editorLink = isPlainObject(normalizedConfig.__editor_link) ? normalizedConfig.__editor_link : {}
    const searchStyleConfig = {
      ...normalizedConfig.style_config,
      bg_color_start: '',
      bg_color_end: '',
      bottom_bg_color: ''
    }
    return {
      placeholder: normalizedConfig.placeholder || pickConfigValue(normalizedConfig.tipConfig, DEFAULT_SEARCH_CONFIG.placeholder) || DEFAULT_SEARCH_CONFIG.placeholder,
      show_left_icon: normalizeSwitchValue(normalizedConfig.show_left_icon, DEFAULT_SEARCH_CONFIG.show_left_icon),
      logoConfig: {
        url: logoConfig.url || normalizedConfig.left_icon_url || normalizedConfig.icon_url || normalizedConfig.left_icon || ''
      },
      shape: normalizedConfig.shape === 'square' ? 'square' : 'rounded',
      box_bg_color: normalizedConfig.box_bg_color || firstConfigColor(normalizedConfig.searchBoxColor, DEFAULT_SEARCH_CONFIG.box_bg_color),
      text_color: normalizedConfig.text_color || firstConfigColor(normalizedConfig.tipColor, firstConfigColor(normalizedConfig.txtColor, DEFAULT_SEARCH_CONFIG.text_color)),
      link_type: normalizedConfig.link_type || editorLink.link_type || DEFAULT_SEARCH_CONFIG.link_type,
      link_url: normalizedConfig.link_url || editorLink.link_url || DEFAULT_SEARCH_CONFIG.link_url,
      link_id: normalizedConfig.link_id || editorLink.link_id || 0,
      link_name: normalizedConfig.link_name || editorLink.link_name || '',
      platform_type: normalizedConfig.platform_type || editorLink.platform_type || '',
      style_config: searchStyleConfig
    }
  }
  if (type === 'content') {
    return normalizeCategoryTabsConfig({
      ...normalizedConfig,
      ...FIXED_CONTENT_CONFIG
    })
  }
  if (type === 'special') {
    return normalizeCategoryTabsConfig(normalizedConfig)
  }
  if (type === 'product') {
    return normalizeProductConfig(normalizedConfig)
  }
  return normalizedConfig
}

async function loadPage() {
  loading.value = true
  try {
    const [pageRes, componentRes] = await Promise.all([
      getDiyPageDetail(pageId.value),
      getComponentsByPageId(pageId.value)
    ])
    const pageData = pageRes.data || {}

    Object.assign(pageForm, {
      page_name: pageData.page_name || '',
      page_title: pageData.page_title || '',
      status: pageData.status !== undefined ? pageData.status : 1
    })
    localPageTitle.value = pageData.page_title || ''

    replaceReactiveObject(globalConfig, normalizeGlobalConfig(pageData.global_config))

    componentList.value = syncComponentSort((componentRes.data || []).map(c => ({
      id: c.id,
      component_type: c.component_type,
      component_data: normalizeTypedComponentConfig(c.component_type, typeof c.config === 'string' ? JSON.parse(c.config) : c.config),
      sort: c.sort || 0,
      status: c.status
    })))

    initialData.value = {
      pageForm: JSON.parse(JSON.stringify(pageForm)),
      globalConfig: JSON.parse(JSON.stringify(globalConfig)),
      componentList: JSON.parse(JSON.stringify(componentList.value))
    }
  } catch (e) {
    console.error(e)
    proxy.$message.error('加载页面失败')
  } finally {
    loading.value = false
  }
}

function handleAddComponent(componentType) {
  if (componentType === 'search') {
    const existingSearch = componentList.value.find(c => c.component_type === 'search')
    if (existingSearch) {
      selectedComponentId.value = existingSearch.id
      rightTab.value = 'component'
      componentTab.value = 'content'
      proxy.$message.warning('搜索框只能添加一个')
      return
    }
  }
  const newComponent = {
    id: 'tmp_' + Date.now(),
    component_type: componentType,
    component_data: getDefaultConfig(componentType),
    sort: componentList.value.length,
    status: 1
  }
  if (componentType === 'search') {
    componentList.value.unshift(newComponent)
  } else {
    componentList.value.push(newComponent)
  }
  componentList.value = syncComponentSort(componentList.value)
  selectedComponentId.value = newComponent.id
  rightTab.value = 'component'
  componentTab.value = 'content'
}

function handleSelectComponent(component) {
  selectedComponentId.value = component.id
  rightTab.value = 'component'
  componentTab.value = 'content'
}

function handleDeselectComponent() {
  selectedComponentId.value = null
  rightTab.value = 'page'
}

async function handleRemoveComponent(component) {
  try {
    await proxy.$confirm('确定要删除该组件吗？', '提示', { type: 'warning' })
    componentList.value = syncComponentSort(componentList.value.filter(c => c.id !== component.id))
    if (selectedComponentId.value === component.id) {
      selectedComponentId.value = null
      rightTab.value = 'page'
    }
    proxy.$message.success('组件已移除，保存后生效')
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

function syncComponentSort(list) {
  const source = Array.isArray(list) ? list : []
  const length = source.length
  return source.map((component, index) => normalizeComponentLoadMore({
    ...component,
    sort: index
  }, index, length))
}

function cloneCurrentComponentList() {
  return componentList.value.slice()
}

function getComponentIndex(component) {
  if (!component) return -1
  return componentList.value.findIndex(item => item.id === component.id)
}

function handleToggleComponentVisible(component) {
  const idx = getComponentIndex(component)
  if (idx === -1) return
  const nextStatus = Number(componentList.value[idx].status) === 0 ? 1 : 0
  componentList.value[idx] = {
    ...componentList.value[idx],
    status: nextStatus
  }
  selectedComponentId.value = component.id
  rightTab.value = 'component'
  proxy.$message.success(nextStatus === 1 ? '组件已显示，保存后生效' : '组件已隐藏，保存后生效')
}

function handleMoveComponent(component, offset) {
  const idx = getComponentIndex(component)
  const nextIdx = idx + offset
  if (idx === -1 || nextIdx < 0 || nextIdx >= componentList.value.length) return

  const nextList = componentList.value.slice()
  const current = nextList.splice(idx, 1)[0]
  nextList.splice(nextIdx, 0, current)
  componentList.value = syncComponentSort(nextList)
  selectedComponentId.value = component.id
  rightTab.value = 'component'
}

function handleMoveComponentUp(component) {
  handleMoveComponent(component, -1)
}

function handleMoveComponentDown(component) {
  handleMoveComponent(component, 1)
}

function handleCopyComponent(component) {
  const idx = getComponentIndex(component)
  if (idx === -1) return
  if (component.component_type === 'search') {
    proxy.$message.warning('搜索框只能保留一个，不能复制')
    return
  }

  const copiedComponent = JSON.parse(JSON.stringify(componentList.value[idx]))
  copiedComponent.id = 'tmp_' + Date.now() + '_' + idx
  copiedComponent.sort = idx + 1

  const nextList = componentList.value.slice()
  nextList.splice(idx + 1, 0, copiedComponent)
  componentList.value = syncComponentSort(nextList)
  selectedComponentId.value = copiedComponent.id
  rightTab.value = 'component'
  componentTab.value = 'content'
  proxy.$message.success('组件已复制，保存后生效')
}

function handleComponentConfigChange(newConfig) {
  if (!selectedComponent.value) return
  const idx = componentList.value.findIndex(c => c.id === selectedComponentId.value)
  if (idx !== -1) {
    const existingData = componentList.value[idx].component_data || {}
    const nextConfig = {
      ...(newConfig || {}),
      style_config: (newConfig && newConfig.style_config) || existingData.style_config
    }
    const nextComponent = {
      ...componentList.value[idx],
      component_data: normalizeTypedComponentConfig(selectedComponent.value.component_type, nextConfig)
    }
    componentList.value[idx] = normalizeComponentLoadMore(nextComponent, idx, componentList.value.length)
  }
}

function getStyleEditorConfig(component) {
  if (!component) return {}
  const data = component.component_data || {}
  const styleConfig = (data.style_config && typeof data.style_config === 'object') ? data.style_config : {}
  if (component.component_type === 'image_cube') {
    return { ...styleConfig, gap: data.gap !== undefined ? data.gap : 4 }
  }
  return styleConfig
}

function handleStyleConfigChange(styleConfig) {
  if (!selectedComponent.value) return
  const idx = componentList.value.findIndex(c => c.id === selectedComponentId.value)
  if (idx !== -1) {
    const existingData = componentList.value[idx].component_data || {}
    let newData = { ...existingData, style_config: styleConfig }
    if (selectedComponent.value.component_type === 'image_cube' && styleConfig.gap !== undefined) {
      const { gap, ...restStyleConfig } = styleConfig
      newData = { ...existingData, gap, style_config: restStyleConfig }
    }
    componentList.value[idx] = {
      ...componentList.value[idx],
      component_data: normalizeTypedComponentConfig(selectedComponent.value.component_type, newData)
    }
  }
}

function handleGlobalConfigChange(newConfig) {
  replaceReactiveObject(globalConfig, normalizeGlobalConfig(JSON.parse(JSON.stringify(newConfig || {}))))
}

async function handleSort(newList) {
  componentList.value = newList
  const sortData = newList.map((c, index) => ({ id: c.id, sort: index }))
  try {
    await batchSortDiyComponent({ page_id: pageId.value, data: sortData })
  } catch (e) {
    console.error(e)
  }
}

async function handleSave(andClose = false) {
  saving.value = true
  try {
    const componentSnapshot = syncComponentSort(cloneCurrentComponentList())
    await updateDiyPage(pageId.value, {
      page_name: pageForm.page_name,
      page_title: pageForm.page_title || localPageTitle.value,
      status: pageForm.status,
      global_config: globalConfig
    })

    const components = componentSnapshot.map((comp, index) => ({
      component_type: comp.component_type,
      component_name: comp.component_name || '',
      config: normalizeTypedComponentConfig(comp.component_type, comp.component_data || {}),
      sort: index,
      status: comp.status !== undefined ? comp.status : 1
    }))

    const compRes = await saveAllComponents({
      page_id: pageId.value,
      components
    })

    const newIds = compRes.data || []
    const refreshedList = syncComponentSort(componentSnapshot.map((comp, index) => ({
      ...comp,
      id: (newIds[index] && newIds[index].id) ? newIds[index].id : comp.id,
      sort: index
    })))
    componentList.value = refreshedList

    proxy.$message.success('保存成功')
    if (andClose) {
      router.push('/diy/index')
    }
  } catch (e) {
    console.error(e)
    proxy.$message.error('保存失败')
  } finally {
    saving.value = false
  }
}

function handleBack() {
  router.push('/diy/index')
}

function handlePreview() {
  previewVisible.value = true
}

function handleEditTitle() {
  localPageTitle.value = pageForm.page_title || localPageTitle.value
  titleDialogVisible.value = true
}

function handleTitleConfirm() {
  pageForm.page_title = localPageTitle.value
  titleDialogVisible.value = false
}

async function handleSaveAsTemplate() {
  try {
    await proxy.$confirm('确定要另存为新页面吗？', '提示', { type: 'warning' })
    const res = await saveAsTemplate(pageId.value, {})
    proxy.$message.success('另存成功，新页面ID: ' + ((res.data && res.data.id) || ''))
    router.push('/diy/index')
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleReset() {
  if (!initialData.value) return
  try {
    await proxy.$confirm('确定要重置页面吗？所有未保存的更改将丢失。', '提示', { type: 'warning' })
    Object.assign(pageForm, initialData.value.pageForm)
    replaceReactiveObject(globalConfig, JSON.parse(JSON.stringify(initialData.value.globalConfig)))
    componentList.value = JSON.parse(JSON.stringify(initialData.value.componentList))
    selectedComponentId.value = null
    rightTab.value = 'page'
    proxy.$message.success('已重置')
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

function getEditorComponent(type) {
  return COMPONENT_EDITOR_MAP[type] || null
}

function getComponentName(type) {
  return COMPONENT_NAMES[type] || type
}

function getComponentIcon(type) {
  return COMPONENT_ICONS[type] || 'el-icon-s-grid'
}

function getDefaultConfig(type) {
  const defaults = {
    search: { ...DEFAULT_SEARCH_CONFIG },
    banner: {
      styleConfig: { tabVal: 0 },
      bottomBgColor: { color: [{ item: '' }] },
      filletImg: { type: 0, val: 6, valList: [{ val: 6 }, { val: 6 }, { val: 6 }, { val: 6 }] },
      docPosition: { tabVal: 0 },
      toneConfig: { tabVal: 0 },
      dotColor: { color: [{ item: '#f34100' }] },
      dotBgColor: { color: [{ item: '#DDDDDD' }] },
      imgConfig: { val: 0 },
      swiperConfig: { list: [{ img: '', link: '' }] },
      docConfig: { tabVal: 0 },
      paddingConfig: { val: 0, isAll: true },
      marginConfig: { val: 0, isAll: true }
    },
    navigation: { title: '快捷导航', items: [], columns: 4, show_title: true, show_text: true, style: 'grid' },
    notice: { content: '公告内容', bg_color: '#fffbe6', text_color: '#ed6a0c' },
    image_cube: { gap: 4, blocks: [] },
    hot_zone: { areas: [] },
    title: { text: '标题', style: 'style1', color: '#333', align: 'left' },
    live: { title: '直播', display_style: 'small', show_count: 4, data_source: 'auto', category_ids: [], manual_ids: [], show_status: 'all', show_more: true, show_count_info: true, bg_color: '#ffffff', background_image: '' },
    content: { title: '最新课程', content_type: 'all', display_style: 'list', layout: 'list', show_count: 3, load_more: false, data_source: 'auto', category_id: 0, category_ids: [], category_tabs: [], manual_ids: [], sort_by: 'create_time', sort_order: 'desc', show_cover: true, show_desc: true, show_price: true, bg_color: '#ffffff' },
    special: { title: '热门专题', display_style: 'scroll', show_count: 6, load_more: false, data_source: 'auto', category_id: 0, category_ids: [], category_tabs: [], manual_ids: [], show_desc: true, show_keywords: false, show_count_info: true, bg_color: '#ffffff' },
    product: { title: '好物推荐', layout: 'grid', with_nav: true, tab_style: 'subtitle', category_ids: [], category_tabs: [], source_type: 'manual', data_source: 'category_tabs', manual_ids: [], category_id: 0, tag_id: 0, brand_id: 0, show_count: 6, load_more: false, sort_by: 'default', bg_color: '#ffffff' },
    seckill: { title: '限时秒杀', activity_id: 0, install_generation: '', session_mode: 'auto', session_id: 0, session_date: '', selection_mode: 'all', product_ids: [], show_count: 4, layout: 'grid', show_more: true, bg_color: '#ffffff' },
    coupon: { title: '优惠券', display_style: 'scroll', columns: 2, show_count: 4, scope_type: 0, data_source: 'auto', manual_ids: [], show_receive_btn: true, bg_color: '#ffffff', background_image: '' },
    rich_text: { content: '' },
    blank: { height: 20 }
  }
  return normalizeTypedComponentConfig(type, defaults[type] || {})
}

async function initEditor() {
  const rawId = Array.isArray(route.query.id) ? route.query.id[0] : route.query.id
  pageId.value = Number.parseInt(String(rawId || ''), 10)
  if (!pageId.value) {
    proxy.$message.error('页面ID不存在')
    router.push('/diy/index')
    return
  }
  await Promise.all([
    loadComponentTypes(),
    loadMobileThemeConfig()
  ])
  await loadPage()
}

onBeforeMount(() => {
  void initEditor()
})
</script>

<style lang="scss" scoped>
.diy-editor {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  color: var(--dashboard-text-primary);
  background: var(--dashboard-bg);
}

/* ── 顶部工具栏 ── */
.editor-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 50px;
  padding: 0 20px;
  background: linear-gradient(to right, var(--prev-color-primary), var(--dashboard-accent-hover));
  flex-shrink: 0;
  z-index: 10;

  .toolbar-left {
    display: flex;
    align-items: center;
    color: #fff;
  }

  .back-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #fff;
    font-size: 14px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.2s;

    &:hover {
      background: rgba(255, 255, 255, 0.15);
    }
  }

  .toolbar-divider {
    width: 1px;
    height: 18px;
    background: rgba(255, 255, 255, 0.4);
    margin: 0 16px;
  }

  .page-title {
    font-size: 14px;
    color: #fff;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    opacity: 0.95;

    &:hover {
      opacity: 1;
    }

    .el-icon-edit-outline {
      font-size: 13px;
      opacity: 0.8;
    }
  }

  .toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .toolbar-btn {
    background: transparent;
    border-color: rgba(255, 255, 255, 0.6);
    color: #fff;

    &:hover {
      background: rgba(255, 255, 255, 0.15);
      border-color: #fff;
      color: #fff;
    }
  }

  .toolbar-btn-primary {
    background: #fff;
    border-color: #fff;
    color: var(--dashboard-accent-hover);
    font-weight: 500;

    &:hover {
      background: var(--prev-color-primary-light-9);
    }
  }
}

/* ── 主体区域 ── */
.editor-body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* ── 左侧组件库 ── */
.left-panel {
  width: 248px;
  background: var(--dashboard-surface);
  border-right: 1px solid var(--dashboard-border);
  overflow-y: auto;
  flex-shrink: 0;

  &::-webkit-scrollbar {
    width: 4px;
  }
  &::-webkit-scrollbar-thumb {
    background: var(--dashboard-border);
    border-radius: 2px;
  }

  .component-groups {
    padding: 8px 0;
  }

  .component-group {
    margin-bottom: 4px;
  }

  .group-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface-muted);
    border-bottom: 1px solid var(--dashboard-border-soft);

    .group-arrow {
      font-size: 12px;
      color: var(--dashboard-text-muted);
    }
  }

  .component-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    padding: 8px;
  }

  .component-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;

    &:hover {
      background: var(--prev-color-primary-light-9);
      border-color: var(--dashboard-accent-light);

      .component-icon {
        color: var(--prev-color-primary);
      }

      .component-label {
        color: var(--prev-color-primary);
      }
    }

    .component-icon {
      width: 36px;
      height: 36px;
      background: var(--prev-color-primary-light-9);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--dashboard-accent-light);
      font-size: 18px;
      margin-bottom: 6px;
      transition: color 0.2s;
    }

    .component-label {
      font-size: 11px;
      color: var(--dashboard-text-secondary);
      text-align: center;
      line-height: 1.2;
      transition: color 0.2s;
    }
  }
}

/* ── 中间预览区 ── */
.center-panel {
  flex: 1;
  overflow-y: auto;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 20px 16px;
  background: var(--dashboard-bg);

  .phone-wrapper {
    position: relative;
  }
}

/* ── 右侧设置面板 ── */
.right-panel {
  width: 420px;
  background: var(--dashboard-surface);
  border-left: 1px solid var(--dashboard-border);
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;

  .right-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    height: 46px;
    border-bottom: 1px solid var(--dashboard-border);
    flex-shrink: 0;
    background: var(--dashboard-surface);

    .right-panel-actions {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .action-btn {
      padding: 5px 12px;
      font-size: 13px;
      color: var(--dashboard-text-secondary);
      cursor: pointer;
      border-radius: 4px;
      transition: all 0.2s;
      font-weight: 500;
      border: 1px solid transparent;

      &:hover {
        color: var(--prev-color-primary);
      }

      &.action-btn-active {
        color: var(--prev-color-primary);
      }
    }

    .right-panel-ops {
      display: flex;
      align-items: center;
      gap: 2px;

      .btn-reset {
        color: #e6a23c;
      }
    }
  }

  .right-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 20px;

    &::-webkit-scrollbar {
      width: 4px;
    }
    &::-webkit-scrollbar-thumb {
      background: var(--dashboard-border);
      border-radius: 2px;
    }
  }

  .settings-block {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--dashboard-border-soft);

    &:last-of-type {
      border-bottom: none;
    }
  }

  .block-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;

    .fr {
      color: #f56c6c;
      font-size: 12px;
    }

    .btn-delete {
      color: #f56c6c;
      font-size: 12px;
    }
  }

  .no-selection-tip {
    text-align: center;
    color: var(--dashboard-text-muted);
    font-size: 13px;
    padding: 60px 0;
  }

  .component-sub-tabs {
    display: flex;
    gap: 0;
    margin-bottom: 14px;
    border: 1px solid var(--dashboard-border);
    border-radius: 20px;
    overflow: hidden;
    width: fit-content;

    .sub-tab {
      padding: 5px 18px;
      font-size: 13px;
      color: var(--dashboard-text-secondary);
      cursor: pointer;
      transition: all 0.2s;
      user-select: none;
      background: var(--dashboard-surface);

      &:hover {
        color: var(--prev-color-primary);
      }

      &.sub-tab-active {
        background: var(--prev-color-primary);
        color: #fff;
        font-weight: 500;
      }
    }
  }

  .no-editor-tip {
    pre {
      font-size: 11px;
      background: var(--dashboard-surface-muted);
      padding: 8px;
      border-radius: 4px;
      overflow: auto;
      max-height: 200px;
      color: var(--dashboard-text-secondary);
    }
  }

  :deep(.banner-card),
  :deep(.nav-card),
  :deep(.block-card),
  :deep(.menu-card),
  :deep(.more-link-card),
  :deep(.area-item) {
    background: var(--dashboard-surface-muted);
    border-color: var(--dashboard-border);
    box-shadow: none;
  }

  :deep(.selected-list) {
    background: var(--dashboard-surface-muted);
    border-color: var(--dashboard-border);
  }

  :deep(.selected-item) {
    color: var(--dashboard-text-secondary);
    background: transparent;

    &:hover {
      background: var(--dashboard-border-soft);
    }
  }

  :deep(.card-thumb),
  :deep(.banner-upload),
  :deep(.empty-tip) {
    color: var(--dashboard-text-muted);
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }

  :deep(.card-header),
  :deep(.area-item-header) {
    color: var(--dashboard-text-muted);
    background: var(--dashboard-surface);
    border-bottom-color: var(--dashboard-border);
  }

  :deep(.field-label),
  :deep(.field-tip),
  :deep(.tip-text),
  :deep(.tips),
  :deep(.draw-tip),
  :deep(.size-label),
  :deep(.size-unit),
  :deep(.field-link-icon),
  :deep(.thumb-placeholder),
  :deep(.banner-placeholder),
  :deep(.drag-handle),
  :deep(.nav-drag-handle),
  :deep(.menu-drag-handle),
  :deep(.card-remove),
  :deep(.area-del) {
    color: var(--dashboard-text-muted);
  }

  :deep(.selected-title),
  :deep(.selected-name),
  :deep(.list-title),
  :deep(.area-name) {
    color: var(--dashboard-text-primary);
  }
}
</style>
