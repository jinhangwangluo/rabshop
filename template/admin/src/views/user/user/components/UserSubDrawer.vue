<template>
  <el-drawer
    v-model="localVisible"
    :title="`下级推广人 — ${userName}`"
    size="720px"
    :wrapper-closable="true"
    @open="handleOpen"
    @close="handleClose"
  >
    <div class="drawer-body">
      <!-- 筛选栏 -->
      <div class="drawer-filter">
        <el-input
          v-model="search.nickname"
          placeholder="搜索昵称"
          clearable

          :prefix-icon="Search"
          class="filter-input"
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        />
        <el-input
          v-model="search.phone"
          placeholder="搜索手机号"
          clearable

          class="filter-input"
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        />
        <el-button type="primary" :icon="Search" @click="handleSearch">查询</el-button>
        <el-button :icon="Refresh" @click="loadList">刷新</el-button>
      </div>

      <!-- 列表 -->
      <el-table
        v-loading="loading"
        :data="list"

        class="sub-table"
      >
        <el-table-column label="头像" width="54" align="center">
          <template #default="scope">
            <el-avatar :size="32" :src="scope.row.avatar" :icon="UserFilled" />
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="昵称" min-width="100" show-overflow-tooltip />
        <el-table-column prop="phone" label="手机号" width="130" align="center">
          <template #default="scope">{{ scope.row.phone || '-' }}</template>
        </el-table-column>
        <el-table-column label="余额" width="100" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ scope.row.asset ? scope.row.asset.balance : '0.00' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="积分" width="80" align="center">
          <template #default="scope">
            <span class="points-text">{{ scope.row.asset ? scope.row.asset.points : 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="金币" width="80" align="center">
          <template #default="scope">
            <span class="coins-text">{{ scope.row.asset ? scope.row.asset.coins : 0 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.status === 1 ? 'success' : 'danger'" size="mini">
              {{ scope.row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="spread_time" label="推广时间" width="155" align="center">
          <template #default="scope">{{ scope.row.spread_time || '-' }}</template>
        </el-table-column>
        <el-table-column prop="create_time" label="注册时间" width="155" align="center" />
      </el-table>

      <!-- 分页 -->
      <div class="drawer-pagination">
        <el-pagination
          :current-page="page"
          :page-sizes="[15, 20, 50]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next"
          :total="total"
          small
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>
  </el-drawer>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { Refresh, Search, UserFilled } from '@element-plus/icons-vue'
import { getSubordinates } from '@/api/user'

defineOptions({ name: 'UserSubDrawer' })

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  userId: {
    type: Number,
    default: 0
  },
  userName: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:visible'])

const localVisible = ref(false)
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const search = reactive({ nickname: '', phone: '' })

watch(() => props.visible, val => {
  localVisible.value = val
}, { immediate: true })

function handleOpen() {
  page.value = 1
  Object.assign(search, { nickname: '', phone: '' })
  loadList()
}

function handleClose() {
  list.value = []
  total.value = 0
  emit('update:visible', false)
}

function loadList() {
  if (!props.userId) return
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...search
  }
  getSubordinates(props.userId, params).then(res => {
    const data = res.data || {}
    list.value = Array.isArray(data.list) ? data.list : []
    total.value = data.total || 0
  }).catch(() => {
    list.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  page.value = 1
  loadList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  loadList()
}

function handleCurrentChange(val) {
  page.value = val
  loadList()
}
</script>

<style lang="scss" scoped>
.drawer-body {
  padding: 0 20px 20px;
  display: flex;
  flex-direction: column;
  height: 100%;
}
.drawer-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 0 12px;
  flex-shrink: 0;
}
.filter-input {
  width: 160px;
}
.sub-table {
  flex: 1;
}
.drawer-pagination {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}
.price-text {
  color: #f56c6c;
  font-weight: 600;
}
.points-text {
  color: #e6a23c;
  font-weight: 600;
}
.coins-text {
  color: #f56c6c;
  font-weight: 600;
}
</style>
