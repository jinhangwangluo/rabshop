<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16" :body-style="{ padding: 0 }">
      <div class="padding-add">
        <el-form :model="searchForm" inline label-width="80px" @submit.prevent>
          <el-form-item label="分类搜索：">
            <el-input v-model="searchForm.title" placeholder="分类名称" clearable class="form_content_width" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>

    <el-card :bordered="false" shadow="never" class="ivu-mt-16">
      <el-button type="primary" @click="add">添加分类</el-button>
      <el-table
        v-loading="loading"
        :data="categoryList"
        class="vxeTable mt14"
        row-key="id"
        border
      >
        <el-table-column prop="id" label="编号" width="80" align="center" />
        <el-table-column prop="title" label="分类名称" min-width="150" />
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="scope">
            <a @click="edit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="del(scope.row, '删除分类')">删除</a>
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
import { getTeacherCategoryList, deleteTeacherCategory, getTeacherCategoryCreate } from '@/api/educational'

defineOptions({
  name: 'TeacherCategory'
})

const { proxy } = getCurrentInstance()
const loading = ref(false)
const categoryList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  title: ''
})

async function getTeacherCategoryListData() {
  loading.value = true
  try {
    const res = await getTeacherCategoryList({
      page: page.value,
      limit: limit.value,
      ...searchForm
    })
    if (res.code === 200) {
      categoryList.value = res.data
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
  void getTeacherCategoryListData()
}

function add() {
  proxy.$modalForm(getTeacherCategoryCreate()).then(() => getTeacherCategoryListData())
}

function edit(row) {
  proxy.$modalForm(getTeacherCategoryCreate({ id: row.id })).then(() => getTeacherCategoryListData())
}

function del(row, tit) {
  proxy.$modalSure({
    title: tit,
    url: `/admin/educational/teacher_categpry/delete?id=${row.id}`,
    method: 'GET'
  }).then((res) => {
    proxy.$message.success(res.message)
    void getTeacherCategoryListData()
  }).catch((res) => {
    proxy.$message.error(res.message)
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  void getTeacherCategoryListData()
}

function handleCurrentChange(val) {
  page.value = val
  void getTeacherCategoryListData()
}

onBeforeMount(() => {
  void getTeacherCategoryListData()
})
</script>
