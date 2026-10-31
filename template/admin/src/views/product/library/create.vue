<template>
  <div class="product-list-create-page">
    <div class="sticky">
      <el-card shadow="never" class="create-header">
        <div class="create-header__inner">
          <div class="create-header__left">
            <span class="back-close" @click="goBack">
              <LegacyIcon name="el-icon-close" />
            </span>
            <span class="create-title">{{ isEdit ? '编辑商品库商品' : '添加商品库商品' }}</span>
          </div>
          <div class="create-header__right">
            <el-button class="btn-curator-reset" @click="goBack">取消</el-button>
            <el-button type="primary" class="btn-curator-primary" :loading="submitLoading" @click="submitForm">确 定</el-button>
          </div>
        </div>
      </el-card>

      <div class="steps-bar">
        <div
          v-for="step in visibleSteps"
          :key="step.index"
          class="steps-bar__item"
          :class="{ 'is-active': activeStep === step.index, 'is-done': step.index < activeStep }"
          @click="activeStep = step.index"
        >
          <span class="steps-bar__name">{{ step.label }}</span>
          <span class="steps-bar__arrow" />
        </div>
      </div>
    </div>

    <el-card shadow="never" class="create-body">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" class="create-form">
        <div v-show="activeStep === 0" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />基本信息
          </div>
          <el-form-item label="商品名称" prop="name">
            <el-input v-model="form.name" placeholder="请填写商品名称" maxlength="100" show-word-limit />
          </el-form-item>
          <el-form-item label="商品类型">
            <div class="type-cards">
              <div
                v-for="type in typeOptions"
                :key="type.value"
                class="type-card"
                :class="{ 'is-selected': Number(form.goods_type) === type.value }"
                @click="form.goods_type = type.value"
              >
                <span class="type-card__title">
                  {{ type.title }}
                  <el-tooltip v-if="type.tip" :content="type.tip" placement="top">
                    <LegacyIcon name="el-icon-question" class="type-card__q" />
                  </el-tooltip>
                </span>
                <span class="type-card__desc">{{ type.desc }}</span>
                <span v-if="Number(form.goods_type) === type.value" class="type-card__check">
                  <LegacyIcon name="el-icon-check" />
                </span>
              </div>
            </div>
            <div class="form-tip">商品库仅保留实物与虚拟两种类型</div>
          </el-form-item>
          <el-form-item label="商品分类">
            <div class="category-field">
              <el-cascader
                v-model="categoryPath"
                :options="categoryOptions"
                :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
                placeholder="请选择分类"
                clearable
                filterable
                class="category-select"
                @change="handleCategoryChange"
              />
              <span class="category-field__quick">
                <a @click="openCategoryNew">新建</a>
                <el-divider direction="vertical" />
                <a @click="refreshCategory">刷新</a>
              </span>
            </div>
          </el-form-item>
          <el-form-item label="品牌">
            <el-select v-model="form.brand_id" placeholder="请选择品牌" clearable class="form-width">
              <el-option v-for="item in brandOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="商品分组">
            <el-select v-model="form.group_id" placeholder="请选择分组" clearable class="form-width">
              <el-option v-for="item in groupOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="关键词">
            <el-input v-model="form.keyword" placeholder="关键词，多个用逗号分隔" maxlength="100" class="form-width" />
          </el-form-item>
          <el-form-item label="商品条码">
            <el-input v-model="form.bar_code" placeholder="请输入商品条码" maxlength="64" />
          </el-form-item>
          <el-form-item label="单位">
            <el-input v-model="form.unit" placeholder="如：件、套、盒" maxlength="20" />
          </el-form-item>
          <el-form-item label="状态">
            <el-radio-group v-model="form.status">
              <el-radio :label="1">启用</el-radio>
              <el-radio :label="0">禁用</el-radio>
            </el-radio-group>
          </el-form-item>
        </div>

        <div v-show="activeStep === 1" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />规格库存
          </div>
          <el-form-item label="规格类型">
            <el-tag type="info">单规格</el-tag>
            <span class="spec-stock-type__hint">商品库仅支持单规格</span>
          </el-form-item>
          <el-form-item label="规格名称">
            <el-input v-model="form.spec_name" placeholder="请输入规格名称，例如 默认" maxlength="100" show-word-limit class="form-width" @input="handleSpecNameInput" />
            <div class="form-tip">规格名称会作为单规格 SKU 标识，并用于后续上架回填</div>
          </el-form-item>
          <el-form-item label="商品属性">
            <div class="sku-block">
              <div class="sku-block__tip">商品库恒为单规格，统一在下方填写规格名称、价格和库存</div>
              <el-table :data="[skuRow]" :row-key="skuTableRowKey" border class="sku-table sku-table--matrix" max-height="260" table-layout="fixed">
                <el-table-column label="规格名称" min-width="220">
                  <template #default="scope">
                    <el-input v-model="scope.row.sku" class="sku-table__input" @input="handleSkuNameInput" />
                  </template>
                </el-table-column>
                <el-table-column label="图片" width="80" align="center">
                  <template #default="scope">
                    <div class="sku-image-picker">
                      <el-image
                        v-if="scope.row.pics"
                        :src="scope.row.pics"
                        :preview-src-list="[scope.row.pics]"
                        preview-teleported
                        fit="cover"
                        class="sku-image-picker__preview"
                      />
                      <el-button
                        v-else
                        class="sku-image-picker__button"
                        @click="handleSkuImageSelect(scope.row)"
                      >
                        <LegacyIcon name="el-icon-picture-outline" />
                      </el-button>
                      <button
                        v-if="scope.row.pics"
                        type="button"
                        class="sku-image-picker__remove"
                        title="移除图片"
                        @click.stop="scope.row.pics = ''"
                      >
                        <LegacyIcon name="el-icon-close" />
                      </button>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="价格（元）" width="110" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.price" :min="0" :precision="2" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="划线价" width="110" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.ot_price" :min="0" :precision="2" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="成本价" width="110" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.store_price" :min="0" :precision="2" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="库存" width="100" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.stock" :min="0" :precision="0" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="排序" width="90" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.sort" :min="0" :precision="0" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
              </el-table>
            </div>
          </el-form-item>
        </div>

        <div v-show="activeStep === 2" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />商品详情
          </div>
          <el-form-item label="商品图片">
            <div>
              <Upload v-model="form.pics" :limit="9" />
              <div class="form-tip">您可以拖拽调整图片顺序，第一张图将作为商品主图展示；建议尺寸 750×750，支持 jpg/png/gif/webp</div>
            </div>
          </el-form-item>
          <el-form-item label="商品视频">
            <div>
              <div class="video-upload-wrap">
                <BigFileUpload
                  v-model="form.video"
                  :allow-extensions="['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']"
                  accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv"
                  tip="支持 mp4/avi/mov/wmv/flv/mkv 格式"
                />
              </div>
              <div v-if="form.video" class="video-preview" @click="previewVideo">
                <LegacyIcon name="el-icon-video-play" />
                <span>点击预览视频</span>
              </div>
            </div>
          </el-form-item>
          <el-form-item label="商品简介">
            <el-input v-model="form.description" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="请输入商品简介" />
          </el-form-item>
          <el-form-item label="商品详情">
            <div class="editor-wrap">
              <Toolbar :editor="editor" :default-config="toolbarConfig" :mode="editorMode" class="editor-toolbar" />
              <Editor v-model="form.detail" :default-config="editorConfig" :mode="editorMode" style="height: 360px; overflow-y: hidden;" @onCreated="onEditorCreated" />
            </div>
          </el-form-item>
        </div>

        <div v-show="activeStep === 3" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />其他设置
          </div>
          <el-form-item label="服务保障">
            <el-select v-model="form.service_ids" multiple placeholder="请选择服务" clearable class="form-width">
              <el-option v-for="item in serviceOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="运费模板">
            <el-select v-model="form.freight_template_id" placeholder="请选择运费模板" clearable class="form-width">
              <el-option v-for="item in freightOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="是否共享">
            <el-radio-group v-model="form.is_share">
              <el-radio :label="1">共享（其他商户可使用）</el-radio>
              <el-radio :label="0">私有</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="排序">
            <el-input-number v-model="form.sort" :min="0" :max="9999" class="advanced-number" />
          </el-form-item>
        </div>
      </el-form>
    </el-card>

    <el-dialog
      v-model="categoryNewVisible"
      title="新建分类"
      width="440px"
      append-to-body
    >
      <el-form label-width="90px" @submit.prevent>
        <el-form-item label="上级分类">
          <el-cascader
            v-model="categoryNewPath"
            :options="categoryParentOptions"
            :props="categoryParentProps"
            placeholder="请选择上级分类（可不选）"
            clearable
            class="form-width"
            @change="handleCategoryParentChange"
          />
        </el-form-item>
        <el-form-item label="分类名称" required>
          <el-input v-model="categoryNewName" placeholder="请输入分类名称" maxlength="30" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="cfg-footer">
          <el-button @click="categoryNewVisible = false">取 消</el-button>
          <el-button type="primary" :loading="categoryNewSaving" @click="submitCategoryNew">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'
import Upload from '@/components/Upload/index'
import BigFileUpload from '@/components/BigFileUpload/index'
import {
  getProductLibraryRead,
  createProductLibrary,
  updateProductLibrary,
  getProductCategoryTree,
  createProductCategory,
  getBrandOptions,
  getGroupOptions,
  getServiceOptions,
  getFreightOptions
} from '@/api/product'

defineOptions({
  name: 'ProductLibraryCreate'
})

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const imgModal = proxy.$imgModal

const isEdit = computed(() => !!route.query.id)
const activeStep = ref(0)
const visibleSteps = computed(() => [
  { label: '基本信息', index: 0 },
  { label: '规格库存', index: 1 },
  { label: '商品详情', index: 2 },
  { label: '其他设置', index: 3 }
])

const formRef = ref(null)
const editor = ref(null)
const submitLoading = ref(false)
const editorMode = 'default'

const categoryOptions = ref([])
const brandOptions = ref([])
const groupOptions = ref([])
const serviceOptions = ref([])
const freightOptions = ref([])

const categoryPath = ref([])
const categoryNewVisible = ref(false)
const categoryNewName = ref('')
const categoryNewPath = ref([])
const categoryNewPid = ref(0)
const categoryNewSaving = ref(false)

const form = reactive(getDefaultForm())
const skuRow = reactive(getDefaultSkuRow())

const rules = {
  name: [
    { required: true, message: '请输入商品名称', trigger: 'blur' },
    { max: 100, message: '商品名称最多100个字符', trigger: 'blur' }
  ]
}

const typeOptions = [
  { value: 1, title: '实物', desc: '物流配送', tip: '实物商品，参与运费计算' },
  { value: 2, title: '虚拟', desc: '无需物流', tip: '虚拟商品不参与实物运费' }
]

const toolbarConfig = {
  excludeKeys: ['uploadVideo', 'insertVideo', 'group-video']
}

const editorConfig = computed(() => {
  const config = { placeholder: '请输入商品详情内容...' }
  config.MENU_CONF = {
    uploadImage: {
      customBrowseAndUpload: (insertFn) => {
        imgModal((image) => {
          const imageList = Array.isArray(image) ? image : (image ? [image] : [])
          imageList.forEach(item => {
            const url = item && (item.path || item.url || item.att_dir || item.src)
            if (url) {
              insertFn(url, item.real_name || item.name || '', url)
            }
          })
        }, 20)
      }
    }
  }
  return config
})

const categoryParentOptions = computed(() => {
  const normalize = (nodes, level = 0) => (nodes || []).map(node => ({
    ...node,
    disabled: level >= 2,
    children: normalize(node.children, level + 1)
  }))
  return normalize(categoryOptions.value)
})

const categoryParentProps = {
  checkStrictly: true,
  emitPath: true,
  value: 'value',
  label: 'label',
  children: 'children'
}

function getDefaultForm() {
  return {
    name: '',
    spec_name: '',
    keyword: '',
    bar_code: '',
    category_id: 0,
    brand_id: '',
    group_id: '',
    price: 0,
    ot_price: 0,
    store_price: 0,
    stock: 0,
    unit: '',
    goods_type: 1,
    cover: '',
    pics: [],
    video: '',
    description: '',
    detail: '',
    attr_values: [],
    service_ids: [],
    freight_template_id: '',
    is_share: 1,
    status: 1,
    sort: 0
  }
}

function getDefaultSkuRow(extra = {}) {
  return {
    rowKey: 'single',
    sku: '默认',
    pics: '',
    price: 0,
    ot_price: 0,
    store_price: 0,
    stock: 0,
    sort: 100,
    ...extra
  }
}

function skuTableRowKey(row) {
  return row.rowKey
}

function loadOptions() {
  getProductCategoryTree({ owner_id: 0 }).then(res => {
    categoryOptions.value = res.data || []
    restoreCategoryPath()
  }).catch(() => {})
  getBrandOptions().then(res => { brandOptions.value = res.data || [] }).catch(() => {})
  getGroupOptions().then(res => { groupOptions.value = res.data || [] }).catch(() => {})
  getServiceOptions().then(res => { serviceOptions.value = res.data || [] }).catch(() => {})
  getFreightOptions().then(res => { freightOptions.value = res.data || [] }).catch(() => {})
}

function handleCategoryChange(val) {
  form.category_id = val && val.length ? val[val.length - 1] : 0
}

function handleSpecNameInput(value) {
  skuRow.sku = String(value || '').trim() || '默认'
}

function handleSkuNameInput(value) {
  const next = String(value || '').trim() || '默认'
  skuRow.sku = next
  form.spec_name = next
}

function openCategoryNew() {
  categoryNewName.value = ''
  categoryNewPath.value = []
  categoryNewPid.value = 0
  categoryNewVisible.value = true
}

function handleCategoryParentChange(path) {
  categoryNewPid.value = Array.isArray(path) && path.length ? Number(path[path.length - 1]) || 0 : 0
}

function submitCategoryNew() {
  if (!categoryNewName.value.trim()) {
    message.warning('请输入分类名称')
    return
  }
  categoryNewSaving.value = true
  createProductCategory({
    name: categoryNewName.value.trim(),
    pid: categoryNewPid.value || 0,
    sort: 0,
    status: 1
  }).then(res => {
    message.success(res.message || '创建成功')
    categoryNewVisible.value = false
    return loadCategoryOptions()
  }).catch(() => {}).finally(() => {
    categoryNewSaving.value = false
  })
}

function refreshCategory() {
  loadCategoryOptions()
}

function loadCategoryOptions() {
  return getProductCategoryTree({ owner_id: 0 }).then(res => {
    categoryOptions.value = res.data || []
    restoreCategoryPath()
  }).catch(() => {})
}

function findCategoryPath(nodes, id, chain = []) {
  for (const node of nodes || []) {
    const next = [...chain, node.value]
    if (Number(node.value) === Number(id)) {
      return next
    }
    if (node.children && node.children.length) {
      const found = findCategoryPath(node.children, id, next)
      if (found.length) {
        return found
      }
    }
  }
  return []
}

function restoreCategoryPath() {
  if (form.category_id) {
    categoryPath.value = findCategoryPath(categoryOptions.value, form.category_id)
  }
}

function onEditorCreated(instance) {
  editor.value = Object.seal(instance)
}

function previewVideo() {
  window.open(form.video, '_blank')
}

function handleSkuImageSelect(row) {
  imgModal((image) => {
    const selected = Array.isArray(image) ? image[0] : image
    const url = selected && (selected.path || selected.url || selected.att_dir || selected.src)
    if (url) {
      row.pics = url
    }
  }, 1)
}

function initForm(data) {
  Object.assign(form, {
    name: data.name || '',
    spec_name: data.spec_name || '',
    keyword: data.keyword || '',
    bar_code: data.bar_code || '',
    category_id: data.category_id || 0,
    brand_id: data.brand_id || '',
    group_id: data.group_id || '',
    price: Number(data.price) || 0,
    ot_price: Number(data.ot_price) || 0,
    store_price: Number(data.store_price) || 0,
    stock: Number(data.stock) || 0,
    unit: data.unit || '',
    goods_type: [1, 2].includes(Number(data.goods_type)) ? Number(data.goods_type) : 1,
    cover: data.cover || '',
    pics: Array.isArray(data.pics) ? data.pics : [],
    video: data.video || '',
    description: data.description || '',
    detail: data.detail || '',
    attr_values: Array.isArray(data.attr_values) ? data.attr_values : [],
    service_ids: data.service_ids ? String(data.service_ids).split(',').filter(Boolean).map(Number) : [],
    freight_template_id: data.freight_template_id || '',
    is_share: Number(data.is_share),
    status: Number(data.status),
    sort: Number(data.sort) || 0
  })
  skuRow.sku = form.spec_name || '默认'
  skuRow.pics = form.cover || ''
  skuRow.price = form.price
  skuRow.ot_price = form.ot_price
  skuRow.store_price = form.store_price
  skuRow.stock = form.stock
  skuRow.sort = 100
  restoreCategoryPath()
}

function buildAttrValues() {
  const values = Array.isArray(form.attr_values) ? [...form.attr_values] : []
  const filtered = values.filter(item => item && item.name !== 'spec_name')
  filtered.push({
    name: 'spec_name',
    value: form.spec_name || skuRow.sku || '默认'
  })
  return filtered
}

function submitForm() {
  if (!form.spec_name && !skuRow.sku) {
    message.warning('请输入规格名称')
    activeStep.value = 1
    return
  }
  formRef.value && formRef.value.validate(valid => {
    if (!valid) {
      activeStep.value = 0
      return
    }
    submitLoading.value = true
    const payload = {
      name: form.name,
      keyword: form.keyword,
      bar_code: form.bar_code,
      category_id: form.category_id,
      brand_id: form.brand_id || 0,
      group_id: form.group_id || 0,
      spec_value_id: 0,
      price: skuRow.price,
      ot_price: skuRow.ot_price,
      store_price: skuRow.store_price,
      stock: skuRow.stock,
      unit: form.unit,
      goods_type: form.goods_type,
      cover: form.cover || skuRow.pics || '',
      pics: Array.isArray(form.pics) ? form.pics : [],
      video: form.video,
      description: form.description,
      detail: form.detail,
      attr_values: buildAttrValues(),
      service_ids: Array.isArray(form.service_ids) ? form.service_ids.join(',') : '',
      freight_template_id: form.freight_template_id || 0,
      is_share: form.is_share,
      status: form.status,
      audit_status: 1,
      sort: form.sort
    }
    const api = isEdit.value
      ? updateProductLibrary(route.query.id, payload)
      : createProductLibrary(payload)
    api.then(res => {
      message.success(res.message || '操作成功')
      goBack()
    }).catch(() => {}).finally(() => {
      submitLoading.value = false
    })
  })
}

function goBack() {
  router.push('/product/library')
}

function initPage() {
  loadOptions()
  if (isEdit.value) {
    getProductLibraryRead(route.query.id).then(res => {
      initForm(res.data || {})
    }).catch(() => {})
  } else {
    skuRow.sku = '默认'
    form.spec_name = '默认'
  }
}

onBeforeMount(initPage)
</script>

<style lang="scss" scoped>
.product-list-create-page {
  min-height: 100%;
}

.sticky {
  position: sticky;
  top: 0;
  z-index: 10;
  margin-bottom: 16px;
}

.create-header {
  border: 0;
  border-radius: 12px 12px 0 0;
  background: var(--ds-surface-card);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

  :deep(.el-card__body) {
    padding: 14px 24px;
  }

  &__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__left {
    display: inline-flex;
    align-items: center;
    gap: 12px;
  }

  &__right {
    display: inline-flex;
    gap: 10px;
  }
}

.back-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  color: var(--ds-on-surface-variant);
  cursor: pointer;

  &:hover {
    background: var(--ds-surface-low);
  }
}

.create-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.steps-bar {
  display: flex;
  overflow: hidden;
  border-radius: 0 0 22px 22px;
  background: var(--ds-surface-card);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 16px;

  &__item {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    height: 44px;
    cursor: pointer;
    background: var(--ds-surface-low);

    &.is-active {
      background: var(--ds-primary);

      .steps-bar__name {
        color: var(--ds-on-primary);
      }

      .steps-bar__arrow {
        border-left-color: var(--ds-primary);
      }
    }

    &.is-done {
      background: rgba(0, 87, 194, 0.1);

      .steps-bar__name {
        color: var(--ds-primary);
      }

      .steps-bar__arrow {
        border-left-color: rgba(0, 87, 194, 0.1);
      }
    }
  }

  &__name {
    padding-left: 24px;
    color: var(--ds-on-surface-variant);
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__arrow {
    position: absolute;
    right: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 12px solid var(--ds-surface-low);
    border-top: 22px solid transparent;
    border-bottom: 22px solid transparent;
  }
}

.create-body {
  border: 0;
  border-radius: 12px;
  background: var(--ds-surface-card);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);
}

.create-form {
  padding: 8px 0;
}

.step-panel {
  padding: 20px 24px;

  &__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 15px;
    font-weight: 600;
    color: var(--prev-color-text-primary);
  }

  &__bar {
    width: 3px;
    height: 14px;
    border-radius: 2px;
    background: var(--prev-color-primary);
  }
}

.type-cards {
  display: flex;
  gap: 12px;
}

.type-card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  width: 120px;
  height: 72px;
  padding: 0 16px;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
  cursor: pointer;

  &.is-selected {
    border: 1px solid var(--ds-primary);
    background: rgba(0, 87, 194, 0.04);
  }

  &__title {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 14px;
    font-weight: 500;
  }

  &__desc {
    font-size: 12px;
    color: var(--ds-on-surface-variant);
  }

  &__check {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 20px;
    height: 20px;
    border-top-left-radius: 6px;
    border-bottom-right-radius: 6px;
    color: var(--ds-on-primary);
    background: var(--ds-primary);
  }
}

.category-field {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;

  .category-select {
    width: 480px;
  }

  &__quick {
    display: inline-flex;
    align-items: center;
    color: var(--ds-primary);
    font-size: 13px;
  }
}

.form-width {
  width: 100%;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
}

.sku-block {
  width: 100%;
}

.sku-block__tip,
.spec-stock-type__hint {
  margin-left: 8px;
  color: #909399;
  font-size: 12px;
}

.sku-table__input,
.sku-table__number {
  width: 100%;
}

.sku-image-picker {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;

  &__preview,
  &__button {
    width: 40px;
    height: 40px;
    border-radius: 3px;
  }

  &__preview {
    border: 1px solid #ebeef5;
  }

  &__button {
    padding: 0;
    border-style: dashed;
    color: #606266;
    font-size: 18px;
  }

  &__remove {
    position: absolute;
    top: -5px;
    right: -5px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: 1px solid #dcdfe6;
    border-radius: 50%;
    background: #fff;
    color: #606266;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;

    &:hover {
      border-color: #f56c6c;
      color: #f56c6c;
    }
  }
}

.editor-wrap {
  width: 100%;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
}

.editor-toolbar {
  border-bottom: 1px solid var(--prev-border-color-lighter);
}

.video-preview {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  color: var(--ds-primary);
  font-size: 13px;
  cursor: pointer;
}
</style>
