<template>
  <div class="diy-page-list">
    <div class="page-layout">
      <el-card shadow="never" class="preview-panel">
        <div class="panel-header">
          <div>
            <div class="panel-title">默认首页预览</div>
            <div class="panel-desc">左侧展示当前默认 DIY 页面在手机端的浏览效果</div>
          </div>
          <el-tag v-if="previewPage && previewPage.id" type="success">默认首页</el-tag>
        </div>

        <div v-loading="previewLoading" class="preview-content">
          <template v-if="previewPage && previewPage.id">
            <div class="preview-meta">
              <div class="preview-meta__main">
                <div class="preview-name">{{ previewPage.page_name || '未命名页面' }}</div>
                <div class="preview-title">{{ previewPage.page_title || '首页' }}</div>
              </div>
              <el-button size="mini" type="primary" @click="handleDecorate(previewPage)">去装修</el-button>
            </div>

            <div class="preview-phone-shell">
              <PhonePreview
                :model-value="previewComponents"
                :global-config="previewGlobalConfig"
                :selectable="false"
                :sortable="false"
                :mobile-page-components="MPAGE_MAP"
                :body-height="720"
                empty-text="当前默认页面暂无组件"
              />
            </div>
          </template>

          <div v-else class="empty-preview">
            <el-empty description="暂无默认 DIY 页面，请先在右侧列表设置默认页" :image-size="110" />
          </div>
        </div>
      </el-card>

      <el-card shadow="never" class="list-panel">
        <div class="card-header">
          <div class="header-text">
            <span class="title">DIY页面列表</span>
            <span class="desc">右侧维护页面列表，左侧同步展示当前默认首页效果</span>
          </div>
          <div class="card-header__actions">
            <el-button plain @click="handleGoLinks">链接管理</el-button>
            <el-button plain @click="handleGoUserCenter">个人中心装修</el-button>
            <el-button type="primary" icon="el-icon-plus" @click="handleAddPage">新建页面</el-button>
          </div>
        </div>

        <el-table
          v-loading="loading"
          :data="pageList"
          :header-cell-style="{ background: 'var(--dashboard-surface-muted)', color: 'var(--dashboard-text-secondary)', fontWeight: '500' }"
        >
          <el-table-column label="页面名称" min-width="180">
            <template #default="{ row }">
              <div class="page-name-cell">
                <span class="page-name">{{ row.page_name || '未命名页面' }}</span>
                <el-tag v-if="row.is_default" size="mini" type="success">默认首页</el-tag>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="page_title" label="页面标题" min-width="120" />
          <el-table-column prop="page_type" label="页面类型" width="100" align="center">
            <template #default="{ row }">
              <el-tag size="mini" type="info">{{ getPageTypeName(row.page_type) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-switch
                v-model="row.status"
                :active-value="1"
                :inactive-value="0"
                @change="handleStatusChange(row)"
              />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="280" align="center">
            <template #default="{ row }">
              <div class="operation-actions">
                <a href="javascript:;" class="action-link" @click="handleEditPage(row)">编辑</a>
                <a href="javascript:;" class="action-link" @click="handleDecorate(row)">装修</a>
                <a
                  href="javascript:;"
                  class="action-link"
                  :class="{ 'action-link--disabled': row.is_default === 1 }"
                  @click="handleSetDefault(row)"
                >设为默认</a>
                <a href="javascript:;" class="action-link action-link--danger" @click="handleDeletePage(row)">删除</a>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>

    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="500px"
      :close-on-click-modal="false"
      @closed="handleDialogClosed"
    >
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
        <el-form-item label="页面名称" prop="page_name">
          <el-input v-model="form.page_name" placeholder="请输入页面名称" />
        </el-form-item>
        <el-form-item label="页面标题" prop="page_title">
          <el-input v-model="form.page_title" placeholder="请输入页面标题" />
        </el-form-item>
        <el-form-item label="页面类型">
          <el-select v-model="form.page_type" disabled>
            <el-option label="首页" value="home" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import PhonePreview from './preview/PhonePreview.vue'
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
import HotZone from '@/components/mobilePage/HotZone.vue'
import Product from '@/components/mobilePage/Product.vue'
import {
  getDiyPageList,
  getDiyPageDetail,
  createDiyPage,
  updateDiyPage,
  deleteDiyPage,
  setDefaultPage,
  toggleDiyPageStatus,
  getComponentsByPageId
} from '@/api/diy'

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
  hot_zone: HotZone
}

function createDefaultGlobalConfig() {
  return {
    background: { type: 'color', color: '#f5f5f5' },
    title_bar: { show: true, title: '首页', bg_color: '#ffffff', text_color: '#333' },
    page_padding: 24
  }
}

const IMAGE_RADIUS_FIELDS = ['image_radius', 'image_radius_lt', 'image_radius_rt', 'image_radius_lb', 'image_radius_rb']

function createDefaultComponentStyleConfig() {
  return {
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
  name: 'DiyPageList'
})

const router = useRouter()
const { proxy } = getCurrentInstance()
const formRef = ref(null)
const loading = ref(false)
const previewLoading = ref(false)
const pageList = ref([])
const previewPage = ref(null)
const previewGlobalConfig = ref(createDefaultGlobalConfig())
const previewComponents = ref([])
const dialogVisible = ref(false)
const dialogTitle = ref('新建页面')
const submitting = ref(false)
const isEditing = ref(false)
const form = reactive({
  id: null,
  page_name: '',
  page_title: '',
  page_type: 'home',
  status: 1
})
const formRules = {
  page_name: [{ required: true, message: '请输入页面名称', trigger: 'blur' }]
}

function resetForm() {
  Object.assign(form, {
    id: null,
    page_name: '',
    page_title: '',
    page_type: 'home',
    status: 1
  })
}

async function loadPageList() {
  loading.value = true
  try {
    const res = await getDiyPageList({ page_type: 'home' })
    const list = res.data.list || res.data || []
    pageList.value = Array.isArray(list) ? list : []
    await loadDefaultPreview()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadDefaultPreview() {
  const defaultPage = pageList.value.find(item => Number(item.is_default) === 1)
  if (!defaultPage) {
    resetPreview()
    return
  }
  await loadPreviewPage(defaultPage.id)
}

async function loadPreviewPage(pageId) {
  previewLoading.value = true
  try {
    const [pageRes, componentRes] = await Promise.all([
      getDiyPageDetail(pageId),
      getComponentsByPageId(pageId)
    ])
    const pageData = pageRes.data || {}
    previewPage.value = {
      ...pageData,
      id: pageData.id || pageId
    }
    previewGlobalConfig.value = normalizeGlobalConfig(pageData.global_config)
    previewComponents.value = normalizeComponents(componentRes.data || [])
  } catch (e) {
    console.error(e)
    resetPreview()
  } finally {
    previewLoading.value = false
  }
}

function resetPreview() {
  previewPage.value = null
  previewGlobalConfig.value = createDefaultGlobalConfig()
  previewComponents.value = []
}

function normalizeGlobalConfig(globalConfig) {
  const defaultConfig = createDefaultGlobalConfig()
  if (Array.isArray(globalConfig)) {
    return defaultConfig
  }
  const config = globalConfig || {}
  const result = {
    ...defaultConfig,
    ...config,
    background: {
      ...defaultConfig.background,
      ...(config.background && typeof config.background === 'object' ? config.background : {})
    },
    title_bar: {
      ...defaultConfig.title_bar,
      ...(config.title_bar || {})
    }
  }
  if (result.background.type === 'color' && !result.background.color) {
    result.background.color = '#f5f5f5'
  }
  return result
}

function normalizeComponents(list) {
  return (list || []).map(item => {
    const config = typeof item.config === 'string' ? JSON.parse(item.config) : item.config
    const componentData = (config && typeof config === 'object' && !Array.isArray(config)) ? config : {}
    const rawStyleConfig = (componentData.style_config && typeof componentData.style_config === 'object') ? componentData.style_config : {}
    const styleConfig = {
      ...createDefaultComponentStyleConfig(),
      ...rawStyleConfig
    }
    if (componentData.filletImg && !hasExplicitImageRadius(rawStyleConfig)) {
      Object.assign(styleConfig, getLegacyBannerImageRadius(componentData.filletImg))
    }
    return {
      id: item.id,
      component_type: item.component_type,
      component_data: {
        ...componentData,
        style_config: styleConfig
      },
      sort: item.sort || 0,
      status: item.status
    }
  })
}

function getPageTypeName(type) {
  const map = { home: '首页' }
  return map[type] || type
}

function handleAddPage() {
  isEditing.value = false
  dialogTitle.value = '新建页面'
  resetForm()
  dialogVisible.value = true
}

function handleEditPage(row) {
  isEditing.value = true
  dialogTitle.value = '编辑页面'
  Object.assign(form, { ...row })
  dialogVisible.value = true
}

function handleDecorate(row) {
  const query = { id: row.id }
  router.push({ name: 'DiyEditor', query })
}

async function handleSubmit() {
  await formRef.value.validate()
  submitting.value = true
  try {
    if (isEditing.value && form.id) {
      await updateDiyPage(form.id, form)
    } else {
      await createDiyPage(form)
    }
    dialogVisible.value = false
    await loadPageList()
    proxy.$message.success('保存成功')
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}

function handleDialogClosed() {
  formRef.value && formRef.value.resetFields()
}

async function handleDeletePage(row) {
  try {
    await proxy.$confirm('确定要删除该页面吗？删除后不可恢复', '提示', { type: 'warning' })
    await deleteDiyPage(row.id)
    proxy.$message.success('删除成功')
    await loadPageList()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

async function handleStatusChange(row) {
  const previousStatus = row.status
  try {
    await toggleDiyPageStatus(row.id)
    proxy.$message.success('状态更新成功')
  } catch (e) {
    Object.assign(row, { status: previousStatus })
  }
}

async function handleSetDefault(row) {
  if (Number(row.is_default) === 1) {
    return
  }

  try {
    await proxy.$confirm('确定将该页面设为默认首页？', '提示', { type: 'warning' })
    await setDefaultPage(row.id)
    proxy.$message.success('设置成功')
    await loadPageList()
  } catch (e) {
    if (e !== 'cancel') console.error(e)
  }
}

function handleGoLinks() {
  router.push('/diy/links')
}

function handleGoUserCenter() {
  router.push('/diy/user-center')
}

onBeforeMount(() => {
  void loadPageList()
})
</script>

<style lang="scss" scoped>

.diy-page-list {
  color: var(--dashboard-text-primary);
}

.page-layout {
  display: grid;
  grid-template-columns: 436px minmax(0, 1fr);
  gap: 20px;
  align-items: flex-start;
}

.preview-panel,
.list-panel {
  border-radius: 16px;
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);
  border-color: var(--dashboard-border);
}

.preview-panel {
  position: sticky;
  top: 20px;
}

.panel-header,
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.panel-header {
  margin-bottom: 16px;
}

.panel-title,
.card-header .title {
  font-size: 18px;
  font-weight: 600;
  color: var(--dashboard-text-primary);
  line-height: 1.2;
}

.panel-desc,
.card-header .desc {
  margin-top: 6px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
  line-height: 1.5;
}

.preview-content {
  min-height: 820px;
}

.preview-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
  background: var(--dashboard-surface-muted);
  border: 1px solid var(--dashboard-border);
  border-radius: 14px;
}

.preview-meta__main {
  min-width: 0;
}

.preview-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--dashboard-text-primary);
  line-height: 1.4;
}

.preview-title {
  margin-top: 4px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
  line-height: 1.4;
}

.preview-phone-shell {
  display: flex;
  justify-content: center;
}

.empty-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 820px;
}

.header-text {
  min-width: 0;
}

.card-header {
  margin-bottom: 16px;
}

.page-name-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.page-name {
  font-size: 14px;
  color: var(--dashboard-text-primary);
  font-weight: 500;
}

.operation-actions {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-wrap: nowrap;
  white-space: nowrap;
  gap: 12px;
}

.action-link {
  line-height: 1;
  font-size: 12px;
  color: var(--dashboard-accent);
  text-decoration: none;
  cursor: pointer;
  transition: color 0.16s ease, opacity 0.16s ease;
}

.action-link:hover {
  color: var(--dashboard-accent-light);
}

.action-link--danger {
  color: var(--dashboard-danger);
}

.action-link--danger:hover {
  opacity: 0.78;
}

.action-link--disabled {
  color: var(--dashboard-text-muted);
  opacity: 0.58;
  cursor: not-allowed;
  pointer-events: none;
}

:deep(.preview-phone-shell .phone-preview) {
  width: 100%;
}

:deep(.preview-phone-shell .phone-frame) {
  width: 375px;
  max-width: 100%;
}

@media screen and (max-width: 1440px) {
  .page-layout {
    grid-template-columns: 400px minmax(0, 1fr);
  }
}

@media screen and (max-width: 1200px) {
  .page-layout {
    grid-template-columns: 1fr;
  }

  .preview-panel {
    position: static;
  }

  .preview-content,
  .empty-preview {
    min-height: auto;
  }
}
</style>
