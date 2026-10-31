<template>
  <div class="product-spec-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">规格管理</h1>
        <p class="page-curator-subtitle">
          管理商品规格模板，一个模板可包含多个规格维度及规格值，供商品库绑定和商品上架组合使用。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="openDialog()"
      >
        添加规格
      </el-button>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-form :model="searchForm" inline class="category-search-form" @submit.prevent>
        <el-form-item label="模板名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入模板名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="list"
        row-key="id"
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="name" label="模板名称" min-width="160" show-overflow-tooltip />
        <el-table-column label="规格维度及值" min-width="420">
          <template #default="scope">
            <template v-if="scope.row.specs && scope.row.specs.length">
              <div
                v-for="(spec, specIndex) in scope.row.specs"
                :key="`${scope.row.id}-spec-${specIndex}`"
                class="dimension-group"
              >
                <span class="dimension-name">{{ spec.name }}</span>
                <template v-if="spec.values && spec.values.length">
                  <el-tag
                    v-for="(item, valueIndex) in spec.values.slice(0, 4)"
                    :key="`${scope.row.id}-spec-${specIndex}-value-${valueIndex}`"

                    class="value-tag"
                  >
                    {{ item.value }}
                  </el-tag>
                  <span v-if="spec.values.length > 4" class="value-more">+{{ spec.values.length - 4 }}</span>
                </template>
              </div>
            </template>
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="90" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="140" fixed="right" align="center">
          <template #default="scope">
            <a @click="openDialog(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-if="total > 0"
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 商品规格模板弹窗：模板标题独立于下方规格维度 -->
    <el-dialog
      v-model="dialogVisible"
      title="规格模板"
      width="760px"
      :before-close="handleClose"
      destroy-on-close
    >
      <div class="spec-dialog">
        <div class="template-name-field">
          <span class="template-name-field__label">规格模板名称</span>
          <el-input
            v-model="templateName"
            placeholder="请输入规格模板名称"
            maxlength="50"
            class="template-name-field__input"
          />
        </div>
        <draggable
          v-model="cards"
          item-key="rowKey"
          handle=".card-handle"
          class="spec-card-list"
        >
          <template #item="{ element: card, index: ci }">
            <div class="spec-card-wrap">
              <span v-if="cards.length > 1" class="card-handle">
                <img :src="dragIcon" alt="" class="drag-icon-img">
              </span>
              <div class="spec-card" :class="{ 'is-active': ci === 0 }">
                <div class="spec-card__head">
                  <div class="spec-card__name-field">
                    <el-input
                      v-model="card.name"
                      placeholder="规格维度名称"
                      maxlength="30"
                      class="spec-card__name"
                    />
                  </div>
                  <span class="spec-card__count">{{ card.values.filter(item => item.value.trim()).length }}/30</span>
                  <span class="spec-card__del" @click="removeCard(ci)">
                    <LegacyIcon name="el-icon-close" />
                  </span>
                </div>
                <div class="spec-card__body">
                  <draggable
                    v-model="card.values"
                    item-key="rowKey"
                    handle=".value-drag-handle"
                    class="value-row-list"
                  >
                    <template #item="{ element, index: vi }">
                      <div class="value-row">
                        <span class="value-drag-handle">
                          <img :src="dragIcon" alt="" class="drag-icon-img">
                        </span>
                        <div class="value-row__field">
                          <el-input
                            v-model="element.value"
                            maxlength="30"
                            placeholder="规格值"
                            class="value-row__input"
                            @blur="handleValueBlur(card, vi)"
                          />
                          <span class="value-row__del" @click="removeValueItem(card, element)">
                            <LegacyIcon name="el-icon-close" />
                          </span>
                        </div>
                      </div>
                    </template>
                  </draggable>
                </div>
              </div>
            </div>
          </template>
        </draggable>
        <div class="spec-dialog__actions">
          <el-button @click="addCard()">添加新规格维度</el-button>
        </div>
      </div>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="handleClose">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitForm">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import draggable from 'vuedraggable'
import dragIcon from '@/assets/images/drag-icon.png'
import {
  getSpecList,
  createSpec,
  updateSpec,
  deleteSpec,
  setSpecStatus
} from '@/api/product'

defineOptions({
  name: 'MerchantProductSpec'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const loading = ref(false)
const list = ref([])
const total = ref(0)
const dialogVisible = ref(false)
const submitLoading = ref(false)

const templateName = ref('')
const templateId = ref(0)
const templateSort = ref(0)
const templateStatus = ref(1)

// 编辑器卡片：{ rowKey, specId(0=新维度), name, values: [{ rowKey, id(0=新值), value, sort }] }
let rowKeySeed = 1
const cards = ref([])

const searchForm = reactive({
  name: '',
  status: '',
  page: 1,
  limit: 20
})

function getDefaultCard(extra = {}) {
  return {
    rowKey: rowKeySeed++,
    specId: 0,
    name: '',
    sort: 0,
    status: 1,
    values: [],
    ...extra
  }
}

function getDefaultValueItem(extra = {}) {
  return {
    rowKey: rowKeySeed++,
    id: 0,
    value: '',
    sort: 0,
    status: 1,
    ...extra
  }
}

function getStatusValue(value) {
  const status = Number(value)
  return Number.isFinite(status) ? status : 1
}

function ensureCardHasValueInput(card) {
  if (!card.values.length) {
    card.values.push(getDefaultValueItem())
    return
  }

  const lastItem = card.values[card.values.length - 1]
  if (lastItem && lastItem.value.trim()) {
    card.values.push(getDefaultValueItem())
  }
}

function getList() {
  loading.value = true
  getSpecList(searchForm).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, { name: '', status: '', page: 1, limit: 20 })
  getList()
}

function handleSizeChange(size) {
  searchForm.limit = size
  searchForm.page = 1
  getList()
}

function handlePageChange(page) {
  searchForm.page = page
  getList()
}

function openDialog(row) {
  if (row && row.id) {
    templateId.value = Number(row.id)
    templateName.value = row.name || ''
    templateSort.value = Number(row.sort) || 0
    templateStatus.value = getStatusValue(row.status)
    cards.value = (row.specs || []).map(spec => getDefaultCard({
      specId: Number(spec.id) || 0,
      name: spec.name || '',
      sort: Number(spec.sort) || 0,
      status: getStatusValue(spec.status),
      values: (spec.values || []).map(item => getDefaultValueItem({
        id: Number(item.id) || 0,
        value: item.value,
        sort: Number(item.sort) || 0,
        status: getStatusValue(item.status)
      }))
    }))
    if (!cards.value.length) {
      cards.value = [getDefaultCard()]
    }
    cards.value.forEach(ensureCardHasValueInput)
  } else {
    templateId.value = 0
    templateName.value = ''
    templateSort.value = 0
    templateStatus.value = 1
    cards.value = [getDefaultCard()]
    ensureCardHasValueInput(cards.value[0])
  }
  dialogVisible.value = true
}

function handleClose() {
  dialogVisible.value = false
  templateId.value = 0
  templateName.value = ''
  templateSort.value = 0
  templateStatus.value = 1
  cards.value = []
}

function addCard() {
  const newCard = getDefaultCard()
  ensureCardHasValueInput(newCard)
  cards.value.push(newCard)
}

function removeCard(index) {
  const target = cards.value[index]
  if (!target) return
  confirm(`确定要删除规格维度「${target.name || '未命名'}」及其规格值吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    cards.value.splice(index, 1)
  }).catch(() => {})
}

function handleValueBlur(card, index) {
  if (!card) return
  const item = card.values[index]
  if (!item) return

  const currentValue = item.value.trim()
  if (index === card.values.length - 1 && currentValue) {
    ensureCardHasValueInput(card)
  }
}

function removeValueItem(card, element) {
  card.values = card.values.filter(item => item.rowKey !== element.rowKey)
  ensureCardHasValueInput(card)
}

function submitForm() {
  if (!templateName.value.trim()) {
    message.warning('请输入规格模板名称')
    return
  }
  if (!cards.value.length) {
    message.warning('请至少保留一个规格维度')
    return
  }
  for (const target of cards.value) {
    if (!target.name.trim()) {
      message.warning('请输入规格维度名称')
      return
    }
    const filledValues = target.values.filter(item => item.value.trim())
    if (!filledValues.length) {
      message.warning(`规格维度「${target.name}」请至少填写一个规格值`)
      return
    }
  }

  const specs = cards.value.map((target, index) => ({
    id: target.specId || 0,
    name: target.name.trim(),
    sort: 100 - index,
    status: getStatusValue(target.status),
    values: target.values
      .filter(item => item.value.trim())
      .map((item, valueIndex) => ({
        id: item.id || 0,
        value: item.value.trim(),
        sort: 100 - valueIndex,
        status: getStatusValue(item.status)
      }))
  }))
  const payload = {
    name: templateName.value.trim(),
    sort: templateSort.value,
    status: templateStatus.value,
    specs
  }

  submitLoading.value = true
  const request = templateId.value
    ? updateSpec(templateId.value, payload)
    : createSpec(payload)
  request.then(res => {
    message.success(res.message || '保存成功')
    handleClose()
    getList()
  }).catch(() => {}).finally(() => {
    submitLoading.value = false
  })
}

function handleDelete(id) {
  confirm('确定要删除该规格吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteSpec(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setSpecStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.product-spec-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.category-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
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

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--ds-primary-container);
    border-color: var(--ds-primary-container);
  }
}

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

.value-tag {
  margin-right: 6px;
}

.dimension-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  margin: 2px 0;
}

.dimension-name {
  min-width: 42px;
  margin-right: 4px;
  color: var(--ds-on-surface);
  font-weight: 500;
}

.value-more {
  color: #909399;
  font-size: 12px;
}

.danger-link {
  color: #f56c6c;

  &:hover {
    color: #f56c6c;
    opacity: 0.8;
  }
}

.empty-text {
  color: #909399;
  font-size: 12px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

/* 商品规格多卡编辑器 */
.spec-dialog {
  padding: 0;

  &__actions {
    display: flex;
    margin-left: 0;
    margin-top: 2px;
  }
}

.template-name-field {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;

  &__label {
    width: 96px;
    flex-shrink: 0;
    color: var(--ds-on-surface);
    font-size: 14px;
    text-align: right;
  }

  &__input {
    flex: 1;
  }
}

.spec-card-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  padding-left: 110px;
}

.spec-card-wrap {
  display: flex;
  align-items: stretch;
  gap: 12px;
  padding: 18px 18px 18px 12px;
  border: 1px solid #eef1f6;
  border-radius: 10px;
  background: #fafbfc;
}

.card-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  flex-shrink: 0;
  color: var(--ds-on-surface-variant);
  cursor: grab;
  padding-top: 2px;

  &:active {
    cursor: grabbing;
  }
}

.spec-card {
  position: relative;
  flex: 1;
  min-width: 0;
  padding: 0;
  border-radius: 0;
  background: transparent;
  transition: background 0.2s ease;

  &.is-active {
    background: transparent;
  }

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-right: 48px;
    margin-bottom: 12px;
  }

  &__name-field {
    flex: 1;
    min-width: 0;
  }

  &__name {
    width: 100%;
  }

  &__count {
    min-width: 42px;
    margin-left: auto;
    color: var(--ds-on-surface-variant);
    font-size: 12px;
    text-align: right;
  }

  &__del {
    position: absolute;
    top: 0;
    right: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 4px;
    background: #3a3f47;
    color: #fff;
    cursor: pointer;
    transition: opacity 0.2s ease, background-color 0.2s ease;

    &:hover {
      background: #23262d;
    }
  }

  &__body {
    padding-left: 2px;
  }
}

.value-row-list {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 10px;
  min-height: 8px;
}

.value-row {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;

  &__field {
    position: relative;
    padding-right: 18px;

    &:hover .value-row__del,
    &:focus-within .value-row__del {
      opacity: 1;
      pointer-events: auto;
    }
  }

  &__input {
    width: 168px;
  }

  &__del {
    position: absolute;
    top: -6px;
    right: 12px;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #3a3f47;
    color: #fff;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    opacity: 0;
    pointer-events: none;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
    transition: opacity 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;

    &:hover {
      background: #23262d;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    }
  }
}

.value-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 24px;
  flex-shrink: 0;
  color: var(--ds-on-surface-variant);
  cursor: grab;

  &:active {
    cursor: grabbing;
  }
}

.drag-icon-img {
  display: block;
  width: 12px;
  height: 12px;
  object-fit: contain;
  user-select: none;
  pointer-events: none;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.spec-dialog__actions{
  margin-top: 8px;
  padding-left: 110px;
}
</style>
