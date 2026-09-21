<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16" :body-style="{ padding: 0 }">
      <div class="padding-add">
        <el-form :model="searchForm" inline label-width="80px" @submit.prevent>
          <el-form-item label="教师搜索：">
            <el-input v-model="searchForm.title" placeholder="教师名称" clearable class="form_content_width" />
          </el-form-item>
          <el-form-item label="教师分类：">
            <el-select v-model="searchForm.cid" placeholder="全部" clearable class="form_content_width">
              <el-option label="全部" value="0" />
              <el-option
                v-for="item in categoryList"
                :key="item.id"
                :label="item.title"
                :value="item.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="handleSearch">查询</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>

    <el-card :bordered="false" shadow="never" class="ivu-mt-16">
      <el-button type="primary" @click="add">添加教师</el-button>
      <el-table
        v-loading="loading"
        :data="teacherList"
        class="vxeTable mt14"
        row-key="id"
        border
      >
        <el-table-column prop="id" label="编号" width="80" align="center" />
        <el-table-column prop="name" label="教师名称" min-width="130" />
        <el-table-column prop="category" label="教师分类" min-width="120" align="center" />
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="scope">
            <a @click="edit(scope.row)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="del(scope.row, '删除教师')">删除</a>
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
import { getTeacherList, getTeacherCategory, getTeacherCreate } from '@/api/educational'

defineOptions({
  name: 'Teacher'
})

const { proxy } = getCurrentInstance()
const loading = ref(false)
const teacherList = ref([])
const categoryList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  title: '',
  cid: '0'
})

async function getTeacherCategoryData() {
  try {
    const res = await getTeacherCategory()
    if (res.status === 200 || res.code === 200) {
      categoryList.value = res.data
    }
  } catch (e) {
    // ignore
  }
}

async function getTeacherListData() {
  loading.value = true
  try {
    const res = await getTeacherList({
      page: page.value,
      limit: limit.value,
      ...searchForm
    })
    if (res.status === 200 || res.code === 200) {
      teacherList.value = res.data
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
  void getTeacherListData()
}

function add() {
  proxy.$modalForm(getTeacherCreate()).then(() => getTeacherListData())
}

function edit(row) {
  proxy.$modalForm(getTeacherCreate({ id: row.id })).then(() => getTeacherListData())
}

function del(row, tit) {
  proxy.$modalSure({
    title: tit,
    url: `/admin/educational/teacher/delete?id=${row.id}`,
    method: 'GET'
  }).then((res) => {
    proxy.$message.success(res.message)
    void getTeacherListData()
  }).catch((res) => {
    proxy.$message.error(res.message)
  })
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  void getTeacherListData()
}

function handleCurrentChange(val) {
  page.value = val
  void getTeacherListData()
}

onBeforeMount(() => {
  void getTeacherCategoryData()
  void getTeacherListData()
})
</script>

<style scoped lang="stylus">
.tabBox_img {
  width: 36px;
  height: 36px;
  border-radius: 4px;
  cursor: pointer;

  img {
    width: 100%;
    height: 100%;
  }
}
</style>
