<template>
  <el-dialog
    :title="type === 'article' ? '选择内容' : '选择专题'"
    :visible="visible"
    width="860px"
    :close-on-click-modal="false"
    append-to-body
    @open="onOpen"
    @close="$emit('update:visible', false)"
  >
    <div class="content-picker">
      <!-- 搜索栏 -->
      <div class="picker-search">
        <el-input
          v-model="keyword"
          :placeholder="type === 'article' ? '搜索内容标题' : '搜索专题标题'"
          clearable
          class="picker-search-input"
          @keyup.enter="doSearch"
        >
          <template #append>
            <el-button icon="el-icon-search" @click="doSearch" />
          </template>
        </el-input>
        <el-select
          v-if="type === 'article'"
          v-model="contentType"
          placeholder="内容类型"
          clearable
          class="picker-type-select"
          @change="doSearch"
        >
          <el-option
            v-for="item in contentTypes"
            :key="item.value"
            :label="item.label"
            :value="item.value"
          />
        </el-select>
      </div>

      <!-- 列表 -->
      <el-table
        v-loading="loading"
        :data="list"
        class="picker-table"
      >
        <el-table-column label="封面" width="70" align="center">
          <template #default="{ row }">
            <el-image v-if="row.cover" :src="row.cover" class="picker-cover" fit="cover" />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
        <el-table-column v-if="type === 'article'" label="类型" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="getContentTypeColor(row.content_type)">
              {{ getContentTypeLabel(row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column v-if="type === 'article'" label="价格" width="90" align="center">
          <template #default="{ row }">
            <span v-if="row.price > 0" class="price-text">¥{{ row.price }}</span>
            <el-tag v-else size="mini" type="success">免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column v-if="type === 'special'" label="价格" width="90" align="center">
          <template #default="{ row }">
            <span class="price-text">¥{{ row.price }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" align="center" fixed="right">
          <template #default="{ row }">
            <template v-if="multiple">
              <el-button
                v-if="!isSelected(row.id)"
                type="primary"
                size="mini"
                plain
                @click="handleSelect(row)"
              >选择</el-button>
              <el-button v-else size="mini" type="success" plain disabled>已选</el-button>
            </template>
            <template v-else>
              <el-button type="primary" size="mini" plain @click="handleSelect(row)">选择</el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="picker-pagination">
        <el-pagination
          :current-page="page"
          :page-size="pageSize"
          layout="total, prev, pager, next"
          :total="total"
          small
          @current-change="handlePageChange"
        />
      </div>
    </div>
  </el-dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { searchContentList, getContentTypes } from '@/api/article'
import { getSpecialList } from '@/api/special'

// 内容类型颜色映射（与 article/index.vue 保持一致）
const CONTENT_TYPE_COLOR = {
  1: 'primary',
  2: '',
  3: 'warning',
  4: 'success',
  5: 'danger',
  6: 'info'
}

defineOptions({
  name: 'ContentPicker'
})

const props = defineProps({
  // 弹窗显隐通过 visible/update:visible 协议控制
  visible: {
    type: Boolean,
    default: false
  },
  // 选择类型：'article' 内容 | 'special' 专题
  type: {
    type: String,
    default: 'article'
  },
  // 是否多选：true 多选（不自动关闭），false 单选（选后自动关闭）
  multiple: {
    type: Boolean,
    default: false
  },
  // 已选 ID 列表（多选时用于标记"已选"状态）
  selectedIds: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['select', 'update:visible'])
const keyword = ref('')
const contentType = ref('')
const loading = ref(false)
const list = ref([])
const page = ref(1)
const pageSize = ref(15)
const total = ref(0)
const contentTypes = ref([])

const type = computed(() => props.type)
const multiple = computed(() => props.multiple)
const selectedIds = computed(() => props.selectedIds)
const visible = computed(() => props.visible)

watch(() => props.type, (value) => {
  if (value === 'article' && !contentTypes.value.length) {
    loadContentTypes()
  }
}, {
  immediate: true
})

function loadContentTypes() {
  getContentTypes().then(res => {
    contentTypes.value = (res.data && res.data.list) || []
  })
}

function getContentTypeLabel(contentTypeValue) {
  const found = contentTypes.value.find(item => item.value === contentTypeValue)
  return found ? found.label : contentTypeValue
}

function getContentTypeColor(contentTypeValue) {
  return CONTENT_TYPE_COLOR[contentTypeValue] || ''
}

function onOpen() {
  keyword.value = ''
  contentType.value = ''
  page.value = 1
  loadList()
}

function loadList() {
  loading.value = true
  const params = { page: page.value, limit: pageSize.value }
  if (keyword.value) {
    params.title = keyword.value
  }
  const request = props.type === 'article'
    ? searchContentList({ ...params, content_type: contentType.value || undefined })
    : getSpecialList(params)
  request.then(res => {
    list.value = (res.data && (res.data.list || res.data)) || []
    total.value = (res.data && (res.data.total || res.data.count)) || 0
  }).finally(() => {
    loading.value = false
  })
}

function doSearch() {
  page.value = 1
  loadList()
}

function handlePageChange(value) {
  page.value = value
  loadList()
}

function isSelected(id) {
  return selectedIds.value.includes(id)
}

function handleSelect(row) {
  emit('select', row)
  if (!props.multiple) {
    emit('update:visible', false)
  }
}
</script>

<style lang="scss" scoped>
.content-picker {
  .picker-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;

    .picker-search-input {
      flex: 1;
    }

    .picker-type-select {
      width: 140px;
    }
  }

  .picker-table {
    width: 100%;

    .picker-cover {
      width: 54px;
      height: 38px;
      border-radius: 4px;
    }
  }

  .picker-pagination {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
  }

  .no-cover {
    color: #c0c4cc;
    font-size: 12px;
  }

  .price-text {
    color: #e6a23c;
    font-weight: 600;
    font-size: 13px;
  }
}
</style>
