<template>
  <div class="user-select-frame">
    <div class="user-select-frame__toolbar">
      <el-input
        v-model="keyword"
        clearable
        class="user-select-frame__search"
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
      <el-table-column label="用户" min-width="220">
        <template #default="{ row }">
          <div class="user-select-frame__user">
            <el-avatar :size="38" :src="row.avatar" icon="el-icon-user-solid" />
            <div class="user-select-frame__meta">
              <span class="user-select-frame__name">{{ row.nickname || '未设置昵称' }}</span>
              <span class="user-select-frame__sub">ID：{{ row.uid }}<template v-if="row.phone"> / {{ row.phone }}</template></span>
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

    <div class="user-select-frame__pagination">
      <el-pagination
        small
        layout="total, prev, pager, next"
        :current-page="page"
        :page-size="limit"
        :total="total"
        @current-change="handlePageChange"
      />
    </div>
  </div>
</template>

<script setup>
import { onBeforeMount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { adminUserSelectApi } from '@/api/system'

defineOptions({
  name: 'UserSelectFrame'
})

const route = useRoute()

const keyword = ref('')
const loading = ref(false)
const list = ref([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)

function getList() {
  loading.value = true
  return adminUserSelectApi({
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

function handlePageChange(currentPage) {
  page.value = currentPage
  getList()
}

function handleSelect(row) {
  if (!row || !row.uid) return
  window.form_create_helper.set(route.query.fodder, Number(row.uid))
  window.form_create_helper.close(route.query.fodder)
}

onBeforeMount(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.user-select-frame {
  min-height: 100%;
  padding: 18px;
  background: #fff;
  box-sizing: border-box;
}

.user-select-frame__toolbar {
  display: flex;
  align-items: center;
  margin-bottom: 14px;
}

.user-select-frame__search {
  width: 320px;
  margin-right: 10px;
}

.user-select-frame__user {
  display: flex;
  align-items: center;
}

.user-select-frame__meta {
  display: flex;
  flex-direction: column;
  margin-left: 10px;
  line-height: 1.4;
}

.user-select-frame__name {
  font-size: 14px;
  color: #303133;
  font-weight: 600;
}

.user-select-frame__sub {
  margin-top: 2px;
  font-size: 12px;
  color: #909399;
}

.user-select-frame__pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
</style>
