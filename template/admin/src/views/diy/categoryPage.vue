<template>
  <div v-loading="loading" class="diy-category-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">分类页面</h1>
        <p class="page-subtitle">四种分类样式同时展示，当前仅允许选择一种样式生效。</p>
      </div>
      <el-button type="primary" :loading="saving" @click="handleSave">保存配置</el-button>
    </header>

    <section class="style-section">
      <div
        v-for="item in styleCards"
        :key="item.value"
        class="style-card"
        :class="{ 'is-active': form.style_type === item.value }"
        @click="handleSelectStyle(item.value)"
      >
        <div class="style-card__image-wrap">
          <img class="style-card__image" :src="item.image" :alt="item.label">
        </div>
        <div class="style-card__body">
          <div class="style-card__title">
            <span>{{ item.label }}</span>
            <el-tag v-if="form.style_type === item.value" type="success">已选中</el-tag>
          </div>
          <div class="style-card__desc">{{ item.description }}</div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getDiyCategoryPageConfig,
  saveDiyCategoryPageConfig
} from '@/api/diyCategoryPage'
import specialPlaceholderImage from '@/assets/images/diy-category-page/special-placeholder.png'
import productGridImage from '@/assets/images/diy-category-page/product-grid.png'
import productTabsImage from '@/assets/images/diy-category-page/product-tabs.png'
import productExpandImage from '@/assets/images/diy-category-page/product-expand.png'

defineOptions({
  name: 'DiyCategoryPage'
})

const STYLE_IMAGE_MAP = {
  product_grid: productGridImage,
  product_tabs: productTabsImage,
  product_expand: productExpandImage,
  special: specialPlaceholderImage
}

const DEFAULT_STYLE_OPTIONS = [
  { value: 'product_grid', label: '商品三级分类图', description: '使用商品三级分类图标展示分类' },
  { value: 'product_tabs', label: '商品三级分类顶部选项卡 + 左侧二级', description: '顶部一级分类、左侧二级分类、右侧三级分类' },
  { value: 'product_expand', label: '商品三级分类层级展开 + 商品展示', description: '按商品分类层级展开并展示商品' },
  { value: 'special', label: '专题分类样式', description: '使用专题分类数据展示分类内容' }
]

const loading = ref(false)
const saving = ref(false)
const styleOptions = ref(DEFAULT_STYLE_OPTIONS)
const form = reactive({
  style_type: 'product_grid',
  config: {
    category_ids: []
  }
})

const styleCards = computed(() => {
  return styleOptions.value.map((item) => ({
    ...item,
    image: STYLE_IMAGE_MAP[item.value] || specialPlaceholderImage,
    placeholder: item.value === 'product_grid'
  }))
})

function applyResponseData(data = {}) {
  form.style_type = data.style_type || 'product_grid'
  form.config.category_ids = []
  styleOptions.value = Array.isArray(data.style_options) && data.style_options.length
    ? data.style_options
    : DEFAULT_STYLE_OPTIONS
}

async function fetchConfig() {
  loading.value = true
  try {
    const res = await getDiyCategoryPageConfig()
    applyResponseData(res.data || {})
  } catch (error) {
    ElMessage.error(error.message || '获取分类页面配置失败')
  } finally {
    loading.value = false
  }
}

function handleSelectStyle(styleType) {
  if (form.style_type === styleType) {
    return
  }

  form.style_type = styleType
  form.config.category_ids = []
}

async function handleSave() {
  saving.value = true
  try {
    const res = await saveDiyCategoryPageConfig({
      style_type: form.style_type,
      config: {}
    })
    applyResponseData(res.data || {})
    ElMessage.success(res.message || '保存成功')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

onBeforeMount(fetchConfig)
</script>

<style lang="scss" scoped>
.diy-category-page {
  min-height: calc(100vh - 84px);
  padding: 24px;
  background: #f5f7fb;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.page-title {
  margin: 0;
  color: #1f2937;
  font-size: 22px;
  font-weight: 600;
}

.page-subtitle {
  margin: 8px 0 0;
  color: #6b7280;
  font-size: 13px;
}

.style-section {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 18px;
}

.style-card {
  overflow: hidden;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #ffffff;
  cursor: pointer;
  transition: border-color .2s, box-shadow .2s, transform .2s;

  &.is-active {
    border-color: #ff5a1f;
    box-shadow: 0 10px 24px rgba(255, 90, 31, .16);
    transform: translateY(-2px);
  }
}

.style-card__image-wrap {
  position: relative;
  height: 600px;
  background: #f3f4f6;
}

.style-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
  display: block;
}

.style-card__placeholder {
  position: absolute;
  left: 12px;
  right: 12px;
  bottom: 12px;
  padding: 8px 10px;
  border-radius: 6px;
  background: rgba(31, 41, 55, .78);
  color: #ffffff;
  font-size: 12px;
  text-align: center;
}

.style-card__body {
  padding: 14px;
}

.style-card__title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: #111827;
  font-size: 15px;
  font-weight: 600;
}

.style-card__desc {
  min-height: 36px;
  margin-top: 8px;
  color: #6b7280;
  font-size: 12px;
  line-height: 18px;
}

@media (max-width: 1280px) {
  .style-section {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .diy-category-page {
    padding: 16px;
  }

  .page-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .style-section {
    grid-template-columns: 1fr;
  }
}
</style>
