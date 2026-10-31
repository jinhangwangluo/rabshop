<template>
  <div class="tag-selector">
    <div
      class="tag-selector__control"
      tabindex="0"
      @click="openSelectDialog"
    >
      <div v-if="selectedTags.length" class="tag-selector__selected">
        <span
          v-for="item in selectedTags"
          :key="item.id"
          class="tag-selector__chip"
          @click.stop
        >
          <TagPreview :tag="item" />
          <LegacyIcon name="el-icon-close" class="tag-selector__chip-close" @click.stop="removeTag(item.id)" />
        </span>
      </div>
      <span v-else class="tag-selector__placeholder">请选择标签</span>
      <LegacyIcon name="el-icon-arrow-down" class="tag-selector__arrow" />
    </div>
    <el-button
      v-if="showQuickAdd"
      type="text"
      @click="openQuickDialog"
    >
      <LegacyIcon name="el-icon-plus" />
      <span>快捷添加</span>
    </el-button>

    <el-dialog
      v-model="selectDialogVisible"
      title="选择标签"
      append-to-body
      width="760px"
    >
      <div class="tag-selector__picker">
        <div v-if="groupedTags.length" class="tag-selector__group-list">
          <div
            v-for="group in groupedTags"
            :key="group.id"
            class="tag-selector__group"
          >
            <div class="tag-selector__group-title">
              <span>{{ group.name }}</span>
              <span class="tag-selector__group-count">{{ group.tags.length }} 个标签</span>
            </div>
            <div v-if="group.tags.length" class="tag-selector__tag-list">
              <button
                v-for="item in group.tags"
                :key="item.id"
                type="button"
                class="tag-selector__tag-item"
                :class="{ 'is-active': isDraftSelected(item.id) }"
                @click="toggleDraftTag(item.id)"
              >
                <TagPreview :tag="item" />
                <LegacyIcon v-if="isDraftSelected(item.id)" name="el-icon-check" class="tag-selector__tag-check" />
              </button>
            </div>
            <div v-else class="tag-selector__empty">该分组暂无标签</div>
          </div>
        </div>
        <el-empty v-else description="暂无可选标签" :image-size="80" />
      </div>
      <template #footer>
        <el-button @click="selectDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmSelect">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="quickDialogVisible"
      title="快捷添加标签"
      append-to-body
      width="560px"
      @closed="resetQuickForm"
    >
      <el-form ref="quickFormRef" :model="quickForm" :rules="quickRules" label-width="96px">
        <el-form-item label="标签分组" prop="category_id">
          <el-select v-model="quickForm.category_id" class="tag-selector__dialog-control" placeholder="请选择分组">
            <el-option
              v-for="item in flatCategories"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <div class="tag-selector__group-add">
          <el-input v-model="quickCategoryName" placeholder="输入新分组名称" clearable />
          <el-button type="primary" plain @click="handleQuickAddCategory">新增分组</el-button>
        </div>
        <el-form-item label="标签名称" prop="name">
          <el-input v-model="quickForm.name" maxlength="30" show-word-limit placeholder="请输入标签名称" />
        </el-form-item>
        <el-form-item label="效果设置">
          <el-radio-group v-model="quickForm.effect_type">
            <el-radio :label="1">自定义颜色</el-radio>
            <el-radio :label="2">图片</el-radio>
          </el-radio-group>
        </el-form-item>
        <template v-if="quickForm.effect_type === 1">
          <el-form-item label="文字颜色">
            <el-color-picker v-model="quickForm.text_color" />
          </el-form-item>
          <el-form-item label="背景颜色">
            <el-color-picker v-model="quickForm.bg_color" />
          </el-form-item>
          <el-form-item label="边框颜色">
            <el-color-picker v-model="quickForm.border_color" />
          </el-form-item>
        </template>
        <el-form-item v-else label="标签图片">
          <Upload v-model="quickForm.icon" :limit="1" />
        </el-form-item>
        <el-form-item label="效果预览">
          <div class="tag-selector__effect-preview">
            <TagPreview :tag="quickPreviewData" />
            <span class="tag-selector__effect-tips">确定保存后将按此效果展示</span>
          </div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="quickForm.sort" :min="0" :step="1" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="quickForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="quickDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="quickSaving" @click="handleQuickAddTag">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import Upload from '@/components/Upload/index'
import TagPreview from '@/components/TagPreview/index'
import { getTagOptions, getTagCategoryTree, quickAddTag, quickAddTagCategory } from '@/api/tag'

const TAG_COLOR_FIELDS = ['text_color', 'bg_color', 'border_color']

defineOptions({
  name: 'TagSelector'
})

const props = defineProps({
  modelValue: {
    type: Array,
    default: undefined
  },
  value: {
    type: Array,
    default: undefined
  },
  tagType: {
    type: Number,
    default: 1
  },
  showQuickAdd: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'input', 'change', 'confirm'])

const innerValue = ref([])
const draftValue = ref([])
const tagOptions = ref([])
const categories = ref([])
const flatCategories = ref([])
const selectDialogVisible = ref(false)
const quickDialogVisible = ref(false)
const quickSaving = ref(false)
const quickCategoryName = ref('')
const quickFormRef = ref(null)
const quickForm = reactive(defaultQuickForm())
const quickRules = {
  category_id: [{ required: true, message: '请选择标签分组', trigger: 'change' }],
  name: [{ required: true, message: '请输入标签名称', trigger: 'blur' }]
}

const selectedTags = computed(() => getTagsByIds(innerValue.value))
const groupedTags = computed(() => {
  const tagsByCategory = groupOptionsByCategory()
  const usedCategoryIds = {}
  const groups = []
  const walk = (items, prefix) => {
    (items || []).forEach(item => {
      const id = Number(item.id)
      const name = prefix ? prefix + ' / ' + item.name : item.name
      usedCategoryIds[id] = true
      groups.push({
        id,
        name,
        tags: tagsByCategory[id] || []
      })
      if (item.children && item.children.length) {
        walk(item.children, name)
      }
    })
  }
  walk(categories.value, '')

  Object.keys(tagsByCategory).forEach(categoryId => {
    const id = Number(categoryId)
    if (!usedCategoryIds[id]) {
      groups.push({
        id: 'other-' + id,
        name: id === 0 ? '未分组' : '其他标签',
        tags: tagsByCategory[id]
      })
    }
  })

  return groups
})
const quickPreviewData = computed(() => Object.assign({}, quickForm, {
  name: quickForm.name || '标签预览'
}))

watch(() => [props.modelValue, props.value], () => {
  innerValue.value = getPropValue().slice()
}, { immediate: true, deep: true })

watch(() => props.tagType, () => {
  void loadData()
})

void loadData()

function getPropValue() {
  if (Array.isArray(props.modelValue)) {
    return props.modelValue
  }
  return Array.isArray(props.value) ? props.value : []
}

function defaultQuickForm() {
  return {
    tag_type: props.tagType,
    category_id: '',
    name: '',
    effect_type: 1,
    text_color: '',
    bg_color: '',
    border_color: '',
    icon: '',
    sort: 0,
    status: 1
  }
}

async function loadData() {
  await Promise.all([loadOptions(), loadCategories()])
}

async function loadOptions() {
  const res = await getTagOptions({ tag_type: props.tagType })
  tagOptions.value = res.data && res.data.list ? res.data.list : []
}

async function loadCategories() {
  const res = await getTagCategoryTree({ tag_type: props.tagType })
  categories.value = res.data || []
  flatCategories.value = flattenCategories(categories.value)
}

function flattenCategories(list) {
  const result = []
  const walk = (items, prefix) => {
    const children = items || []
    children.forEach(item => {
      const name = prefix ? prefix + ' / ' + item.name : item.name
      result.push({ id: item.id, name })
      if (item.children && item.children.length) {
        walk(item.children, name)
      }
    })
  }
  walk(list || [], '')
  return result
}

function handleChange(value) {
  emit('update:modelValue', value)
  emit('input', value)
  emit('change', value)
}

function getTagsByIds(ids) {
  const tagMap = {}
  tagOptions.value.forEach(item => {
    tagMap[Number(item.id)] = item
  })
  return (ids || []).map(id => tagMap[Number(id)]).filter(item => item)
}

function groupOptionsByCategory() {
  const groups = {}
  tagOptions.value.forEach(item => {
    const categoryId = Number(item.category_id || 0)
    if (!groups[categoryId]) {
      groups[categoryId] = []
    }
    groups[categoryId].push(item)
  })
  return groups
}

function openSelectDialog() {
  draftValue.value = innerValue.value.slice()
  void loadData()
  selectDialogVisible.value = true
}

function isDraftSelected(id) {
  return draftValue.value.map(item => Number(item)).indexOf(Number(id)) !== -1
}

function toggleDraftTag(id) {
  const value = draftValue.value.slice()
  const index = value.map(item => Number(item)).indexOf(Number(id))
  if (index > -1) {
    value.splice(index, 1)
  } else {
    value.push(Number(id))
  }
  draftValue.value = value
}

function removeTag(id) {
  const value = innerValue.value.filter(item => Number(item) !== Number(id))
  innerValue.value = value
  handleChange(value)
}

function confirmSelect() {
  innerValue.value = draftValue.value.slice()
  handleChange(innerValue.value)
  selectDialogVisible.value = false
  emit('confirm', innerValue.value)
}

function openQuickDialog() {
  Object.assign(quickForm, defaultQuickForm())
  quickDialogVisible.value = true
}

function handleQuickAddCategory() {
  const name = String(quickCategoryName.value || '').trim()
  if (!name) {
    ElMessage.warning('请输入新分组名称')
    return
  }
  quickAddTagCategory({ name, tag_type: props.tagType }).then(res => {
    ElMessage.success(res.message || '添加成功')
    quickCategoryName.value = ''
    void loadCategories()
    if (res.data && res.data.id) {
      quickForm.category_id = res.data.id
    }
  })
}

async function handleQuickAddTag() {
  if (!quickFormRef.value) {
    return
  }
  try {
    await quickFormRef.value.validate()
  } catch {
    return
  }

  quickSaving.value = true
  try {
    const data = Object.assign({}, normalizeTagColorFields(quickForm), { tag_type: props.tagType })
    const res = await quickAddTag(data)
    ElMessage.success(res.message || '添加成功')
    const id = res.data && res.data.id ? res.data.id : 0
    quickDialogVisible.value = false
    void loadOptions()
    if (id) {
      const value = innerValue.value.slice()
      if (value.indexOf(id) === -1) {
        value.push(id)
      }
      innerValue.value = value
      handleChange(value)
      if (selectDialogVisible.value && draftValue.value.indexOf(id) === -1) {
        draftValue.value.push(id)
      }
    }
  } finally {
    quickSaving.value = false
  }
}

function normalizeTagColorFields(form) {
  const data = Object.assign({}, form)
  TAG_COLOR_FIELDS.forEach(field => {
    if (data[field] === null || typeof data[field] === 'undefined') {
      data[field] = ''
    }
  })
  return data
}

function resetQuickForm() {
  Object.assign(quickForm, defaultQuickForm())
  quickCategoryName.value = ''
  if (quickFormRef.value) {
    quickFormRef.value.clearValidate()
  }
}

defineExpose({
  loadData,
  openSelectDialog
})
</script>

<style lang="scss" scoped>
.tag-selector {
  display: flex;
  align-items: center;
  width: 100%;

  &__control {
    position: relative;
    display: flex;
    align-items: center;
    min-height: 40px;
    width: 100%;
    max-width: 520px;
    padding: 4px 34px 4px 10px;
    margin-right: 12px;
    border: 1px solid var(--prev-border-color-base);
    border-radius: 4px;
    background: var(--prev-bg-white);
    cursor: pointer;

    &:hover,
    &:focus {
      border-color: var(--prev-color-primary);
    }
  }

  &__selected,
  &__picked-list,
  &__tag-list {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }

  &__selected {
    min-height: 30px;
  }

  &__placeholder {
    color: var(--prev-color-text-placeholder);
  }

  &__arrow {
    position: absolute;
    top: 50%;
    right: 12px;
    color: var(--prev-color-text-placeholder);
    transform: translateY(-50%);
  }

  &__chip,
  &__tag-item {
    display: inline-flex;
    align-items: center;
    margin: 4px 8px 4px 0;
  }

  &__chip {
    padding: 2px 7px 2px 2px;
    border-radius: 16px;
    background: var(--prev-color-primary-light-9);
  }

  &__chip-close {
    margin-left: 5px;
    color: var(--prev-color-text-secondary);
    cursor: pointer;

    &:hover {
      color: var(--prev-color-primary);
    }
  }

  &__picker {
    max-height: 560px;
    overflow-y: auto;
  }

  &__picked {
    display: flex;
    align-items: flex-start;
    padding: 12px 14px;
    margin-bottom: 14px;
    border: 1px solid var(--prev-border-color-lighter);
    border-radius: 6px;
    background: var(--prev-color-primary-light-9);
  }

  &__picked-label {
    flex: 0 0 72px;
    margin-top: 7px;
    color: var(--prev-color-text-regular);
    font-weight: 600;
  }

  &__picked-list {
    flex: 1;
    min-width: 0;
  }

  &__picked-empty {
    margin-top: 7px;
    color: var(--prev-color-text-secondary);
  }

  &__group {
    padding: 14px 0;
    border-bottom: 1px solid var(--prev-border-color-lighter);

    &:last-child {
      border-bottom: 0;
    }
  }

  &__group-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--prev-color-text-black);
    font-weight: 600;
  }

  &__group-count,
  &__empty {
    color: var(--prev-color-text-secondary);
    font-size: 12px;
    font-weight: 400;
  }

  &__tag-item {
    position: relative;
    padding: 4px 10px 4px 4px;
    border: 1px solid var(--prev-border-color-light);
    border-radius: 18px;
    background: var(--prev-bg-white);
    cursor: pointer;

    &:hover {
      border-color: var(--prev-color-primary);
    }

    &.is-active {
      border-color: var(--prev-color-primary);
      background: var(--prev-color-primary-light-9);
    }
  }

  &__tag-check {
    margin-left: 6px;
    color: var(--prev-color-primary);
    font-weight: 700;
  }

  &__dialog-control {
    width: 100%;
  }

  &__group-add {
    display: flex;
    margin: -8px 0 18px 96px;

    .el-input {
      margin-right: 8px;
    }
  }

  &__effect-preview {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
  }

  &__effect-tips {
    margin-left: 12px;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
  }
}
</style>
