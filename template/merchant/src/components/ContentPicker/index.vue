<template>
  <el-dialog
    :title="type === 'article' ? '选择内容' : '选择专题'"
    :model-value="visible"
    width="860px"
    :close-on-click-modal="false"
    append-to-body
    @open="onOpen"
    @update:model-value="value => emit('update:visible', value)"
    @close="emit('update:visible', false)"
  >
    <div class="content-picker">
      <div class="picker-search">
        <el-input
          v-model="keyword"
          :placeholder="type === 'article' ? '搜索内容标题' : '搜索专题标题'"
          clearable
          class="picker-search-input"
          @keyup.enter="doSearch"
        >
          <template #append>
            <el-button @click="doSearch">
              <el-icon>
                <Search />
              </el-icon>
            </el-button>
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
        <el-table-column v-if="type === 'article'" label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="getContentTypeColor(row.content_type)" >
              {{ getContentTypeLabel(row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="90" align="center">
          <template #default="{ row }">
            <span v-if="Number(row.price) > 0" class="price-text">¥{{ row.price }}</span>
            <el-tag v-else  type="success">免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" align="center" fixed="right">
          <template #default="{ row }">
            <template v-if="multiple">
              <el-button
                v-if="!isSelected(row.id)"
                type="primary"
                plain
                @click="handleSelect(row)"
              >选择</el-button>
              <el-button v-else type="success" plain disabled>已选</el-button>
            </template>
            <template v-else>
              <el-button type="primary" plain @click="handleSelect(row)">选择</el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <div class="picker-pagination">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
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
import { searchContentList, getContentTypes } from '@/api/article'
import { getSpecialList } from '@/api/special'
import { ref } from 'vue'
import { Search } from '@element-plus/icons-vue'

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
  visible: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'article'
  },
  multiple: {
    type: Boolean,
    default: false
  },
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

function loadContentTypes() {
  return getContentTypes().then(res => {
    contentTypes.value = (res.data && res.data.list) || []
  })
}

function getContentTypeLabel(type) {
  const found = contentTypes.value.find(item => item.value === type)
  return found ? found.label : type
}

function getContentTypeColor(type) {
  return CONTENT_TYPE_COLOR[type] || ''
}

function onOpen() {
  keyword.value = ''
  contentType.value = ''
  page.value = 1
  loadList()
}

function loadList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: pageSize.value
  }
  if (keyword.value) {
    params.title = keyword.value
  }
  if (props.type === 'article' && contentType.value !== '') {
    params.content_type = contentType.value
  }
  const request = props.type === 'article'
    ? searchContentList(params)
    : getSpecialList({
      ...params,
      status: 1
    })
  request.then(res => {
    const data = res.data || {}
    list.value = data.list || data || []
    total.value = data.count || data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function doSearch() {
  page.value = 1
  loadList()
}

function handlePageChange(val) {
  page.value = val
  loadList()
}

function isSelected(id) {
  return props.selectedIds.includes(id)
}

function handleSelect(row) {
  emit('select', row)
  if (!props.multiple) {
    emit('update:visible', false)
  }
}

loadContentTypes()
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
    color: var(--text-price);
    font-weight: 600;
    font-size: 13px;
  }
}
</style>
