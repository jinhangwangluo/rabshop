<template>
  <div>
    <el-card :bordered="false" shadow="never" class="ivu-mb-16" :body-style="{ padding: 0 }">
      <div class="padding-add">
        <el-form :model="searchForm" inline label-width="80px" @submit.prevent>
          <el-form-item label="学员搜索：">
            <el-input v-model="searchForm.title" placeholder="学员名称" clearable class="form_content_width" />
          </el-form-item>
          <el-form-item label="班级名称：">
            <el-select v-model="searchForm.cid" placeholder="全部" clearable class="form_content_width">
              <el-option label="全部" value="0" />
              <el-option
                v-for="item in classList"
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
      <div class="mb20">
        <el-button type="primary" @click="add">添加学员</el-button>
        <el-button type="success" @click="handleSendTestPaper">发送试卷</el-button>
        <el-button type="warning" @click="handleSendSpecial">发送课程</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="studentList"
        class="vxeTable mt14"
        row-key="id"
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="uid" label="UID" width="80" align="center" />
        <el-table-column prop="nickname" label="昵称" min-width="120" align="center" />
        <el-table-column label="头像" width="100" align="center">
          <template #default="scope">
            <div v-viewer class="tabBox_img">
              <img v-lazy="scope.row.image">
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="sex" label="性别" width="80" align="center" />
        <el-table-column prop="name" label="名称" min-width="120" align="center" />
        <el-table-column prop="title" label="班级" min-width="200" align="center" />
        <el-table-column prop="address" label="地址" min-width="150" align="center" />
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
                  <el-dropdown-item @click="viewTestPaper(scope.row.uid)">已发试卷</el-dropdown-item>
                  <el-dropdown-item @click="viewSpecial(scope.row.uid)">已发课程</el-dropdown-item>
                  <el-dropdown-item divided @click="del(scope.row, '删除学员')">删除学员</el-dropdown-item>
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
import { getStudentList, getClassList, getStudentCreate } from '@/api/educational'

defineOptions({
  name: 'Student'
})

const router = useRouter()
const { proxy } = getCurrentInstance()
const loading = ref(false)
const studentList = ref([])
const classList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const searchForm = reactive({
  title: '',
  cid: '0'
})
const selectionData = ref([])

async function getClasses() {
  try {
    const res = await getClassList()
    if (res.status === 200 || res.code === 200) {
      classList.value = res.data
    }
  } catch (e) {
    // ignore
  }
}

async function getStudentListData() {
  loading.value = true
  try {
    const res = await getStudentList({
      page: page.value,
      limit: limit.value,
      ...searchForm
    })
    if (res.status === 200 || res.code === 200) {
      studentList.value = res.data
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
  void getStudentListData()
}

function add() {
  proxy.$modalForm(getStudentCreate()).then(() => getStudentListData())
}

function edit(row) {
  proxy.$modalForm(getStudentCreate({ id: row.id })).then(() => getStudentListData())
}

function del(row, tit) {
  proxy.$modalSure({
    title: tit,
    url: `/admin/educational/student/delete?id=${row.id}`,
    method: 'GET'
  }).then((res) => {
    proxy.$message.success(res.message)
    void getStudentListData()
  }).catch((res) => {
    proxy.$message.error(res.message)
  })
}

function viewTestPaper(uid) {
  void uid
}

function viewSpecial(uid) {
  void uid
}

function handleSendTestPaper() {
  if (selectionData.value.length === 0) {
    proxy.$message.warning('请选择要发送试卷的学员')
  }
}

function handleSendSpecial() {
  if (selectionData.value.length === 0) {
    proxy.$message.warning('请选择要发送课程的学员')
  }
}

function handleSelectionChange(val) {
  selectionData.value = val
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  void getStudentListData()
}

function handleCurrentChange(val) {
  page.value = val
  void getStudentListData()
}

onBeforeMount(() => {
  void getClasses()
  void getStudentListData()
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
.mb20 {
    margin-bottom: 20px;
}
</style>
