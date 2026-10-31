<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16" :body-style="{ padding: 0 }">
      <div class="padding-add">
        <el-form :model="searchForm" inline label-width="80px" @submit.prevent>
          <el-form-item label="班级搜索：">
            <el-input v-model="searchForm.title" placeholder="班级名称" clearable class="form_content_width" />
          </el-form-item>
          <el-form-item label="班级状态：">
            <el-select v-model="searchForm.status" placeholder="全部" clearable class="form_content_width">
              <el-option label="全部" value="" />
              <el-option label="开班" value="1" />
              <el-option label="结班" value="2" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>

    <el-card :bordered="false" shadow="never" class="ivu-mt-16">
      <el-button type="primary" @click="add">添加班级</el-button>
      <el-table
        v-loading="loading"
        :data="classList"
        class="vxeTable mt14"
        row-key="id"
        border
      >
        <el-table-column prop="id" label="编号" width="80" align="center" />
        <el-table-column prop="title" label="班级名称" min-width="150" />
        <el-table-column prop="teacher" label="配班教师" min-width="120" />
        <el-table-column prop="upper_limit" label="班级名额" width="100" align="center" />
        <el-table-column prop="start_time" label="开班时间" width="150" align="center">
          <template #default="scope">
            {{ scope.row.start_time ? scope.row.start_time.split(' ')[0] : '' }}
          </template>
        </el-table-column>
        <el-table-column prop="end_time" label="结班时间" width="150" align="center">
          <template #default="scope">
            {{ scope.row.end_time ? scope.row.end_time.split(' ')[0] : '' }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="班级状态" width="100" align="center">
          <template #default="scope">
            {{ scope.row.status === 1 ? '开班' : scope.row.status === 2 ? '结班' : '未知' }}
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="scope">
            <a @click="edit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <el-dropdown trigger="click">
              <a class="el-dropdown-link">
                更多 <LegacyIcon name="el-icon-arrow-down" class=" el-icon--right" />
              </a>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item @click="viewStudents(scope.row.id)">查看学员</el-dropdown-item>
                  <el-dropdown-item divided @click="del(scope.row, '删除班级')">删除班级</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </el-table>
      <div class="acea-row row-right page">
        <el-pagination
          :current-page="page"
          :page-size="limit"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getClassList, getClassCreate } from '@/api/educational'

defineOptions({
  name: 'Classes'
})

const router = useRouter()
const { proxy } = getCurrentInstance()
const loading = ref(false)
const classList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  title: '',
  status: ''
})

async function fetchClassList() {
  loading.value = true
  try {
    const res = await getClassList({
      page: page.value,
      limit: limit.value,
      ...searchForm
    })
    if (res.status === 200 || res.code === 200) {
      classList.value = res.data
      total.value = res.total
    }
  } catch (e) {
    // ignore
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  page.value = 1
  void fetchClassList()
}

function add() {
  proxy.$modalForm(getClassCreate()).then(() => fetchClassList())
}

function edit(row) {
  proxy.$modalForm(getClassCreate({ id: row.id })).then(() => fetchClassList())
}

function del(row, tit) {
  proxy.$modalSure({
    title: tit,
    url: `/admin/educational/classes/delete?id=${row.id}`,
    method: 'GET'
  }).then((res) => {
    proxy.$message.success(res.message || res.message)
    void fetchClassList()
  }).catch((res) => {
    proxy.$message.error(res.message || res.message)
  })
}

function viewStudents(cid) {
  router.push(`/educational/student?cid=${cid}`)
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  void fetchClassList()
}

function handleCurrentChange(val) {
  page.value = val
  void fetchClassList()
}

onBeforeMount(() => {
  void fetchClassList()
})
</script>
