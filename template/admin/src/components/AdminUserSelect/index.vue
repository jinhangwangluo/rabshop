<template>
  <div class="admin-user-select">
    <div class="admin-user-select__control">
      <el-input
        :value="selectedText"
        :placeholder="placeholder"
        :disabled="disabled"
        readonly
      >
        <template #append>
          <el-button

            :disabled="disabled"
            icon="el-icon-search"
            @click="handleOpen"
          >选择</el-button>
        </template>
      </el-input>
      <el-button
        v-if="hasValue && !disabled"
        type="text"
        class="admin-user-select__clear"
        @click="handleClear"
      >清除</el-button>
    </div>

    <div v-if="selectedUser" class="admin-user-select__selected">
      <el-avatar :size="32" :src="selectedUser.avatar" icon="el-icon-user-solid" />
      <div class="admin-user-select__meta">
        <span class="admin-user-select__name">{{ selectedUser.nickname || '未设置昵称' }}</span>
        <span class="admin-user-select__sub">
          ID：{{ selectedUser.uid }}<template v-if="selectedUser.phone"> / {{ selectedUser.phone }}</template>
        </span>
      </div>
    </div>

    <el-dialog
      v-model="dialogVisible"
      title="选择绑定用户"
      width="760px"
      append-to-body
      class="admin-user-select__dialog"
    >
      <div class="admin-user-select__toolbar">
        <el-input
          v-model="keyword"
          clearable
          class="admin-user-select__search"
          placeholder="请输入用户ID、昵称或手机号"
          @keyup.enter="handleSearch"
        />
        <el-button type="primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="list"
        height="420"
        row-key="uid"
        @row-dblclick="handleSelect"
      >
        <el-table-column label="用户" min-width="260">
          <template #default="{ row }">
            <div class="admin-user-select__user">
              <el-avatar :size="38" :src="row.avatar" icon="el-icon-user-solid" />
              <div class="admin-user-select__meta">
                <span class="admin-user-select__name">{{ row.nickname || '未设置昵称' }}</span>
                <span class="admin-user-select__sub">
                  ID：{{ row.uid }}<template v-if="row.phone"> / {{ row.phone }}</template>
                </span>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'" size="mini">
              {{ Number(row.status) === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="90" align="center">
          <template #default="{ row }">
            <el-button size="mini" type="primary" @click="handleSelect(row)">选择</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="admin-user-select__pagination">
        <el-pagination
          small
          layout="total, prev, pager, next"
          :current-page="page"
          :page-size="limit"
          :total="total"
          @current-change="handlePageChange"
        />
      </div>

      <template #footer>
        <span>
          <el-button @click="dialogVisible = false">取消</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { adminUserSelectApi } from '@/api/system'

defineOptions({
  name: 'AdminUserSelect'
})

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: 0
  },
  disabled: {
    type: Boolean,
    default: false
  },
  placeholder: {
    type: String,
    default: '请选择绑定用户'
  }
})

const { disabled, placeholder } = props

const emit = defineEmits(['update:modelValue', 'change'])
const dialogVisible = ref(false)
const keyword = ref('')
const loading = ref(false)
const list = ref([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)
const selectedUser = ref(null)

const hasValue = computed(() => Number(props.modelValue) > 0)

const selectedText = computed(() => {
  if (!hasValue.value) return ''
  if (selectedUser.value) {
    return `${selectedUser.value.nickname || '未设置昵称'}（ID：${selectedUser.value.uid}）`
  }
  return `用户ID：${props.modelValue}`
})

watch(() => props.modelValue, (val) => {
  handleValueChange(val)
}, {
  immediate: true
})

function handleValueChange(val) {
  const uid = Number(val)
  if (!uid) {
    selectedUser.value = null
    return
  }
  if (selectedUser.value && Number(selectedUser.value.uid) === uid) return
  loadSelectedUser(uid)
}

function loadSelectedUser(uid) {
  adminUserSelectApi({ keyword: String(uid), page: 1, limit: 10 }).then(res => {
    const data = res.data || {}
    const rows = data.list || []
    selectedUser.value = rows.find(item => Number(item.uid) === uid) || null
  })
}

function handleOpen() {
  if (props.disabled) return
  dialogVisible.value = true
  page.value = 1
  keyword.value = ''
  getList()
}

function getList() {
  loading.value = true
  adminUserSelectApi({
    keyword: keyword.value,
    page: page.value,
    limit: limit.value
  }).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  getList()
}

function handlePageChange(nextPage) {
  page.value = nextPage
  getList()
}

function handleSelect(row) {
  if (!row || !row.uid) return
  selectedUser.value = row
  emit('update:modelValue', Number(row.uid))
  emit('change', row)
  dialogVisible.value = false
}

function handleClear() {
  selectedUser.value = null
  emit('update:modelValue', 0)
  emit('change', null)
}
</script>

<style lang="scss" scoped>
.admin-user-select {
  width: 100%;
}

.admin-user-select__control {
  display: flex;
  align-items: center;
}

.admin-user-select__clear {
  margin-left: 10px;
}

.admin-user-select__selected,
.admin-user-select__user {
  display: flex;
  align-items: center;
}

.admin-user-select__selected {
  margin-top: 8px;
}

.admin-user-select__meta {
  display: flex;
  flex-direction: column;
  margin-left: 10px;
  line-height: 1.4;
}

.admin-user-select__name {
  font-size: 14px;
  color: #303133;
  font-weight: 600;
}

.admin-user-select__sub {
  margin-top: 2px;
  font-size: 12px;
  color: #909399;
}

.admin-user-select__toolbar {
  display: flex;
  align-items: center;
  margin-bottom: 14px;
}

.admin-user-select__search {
  width: 320px;
  margin-right: 10px;
}

.admin-user-select__pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
