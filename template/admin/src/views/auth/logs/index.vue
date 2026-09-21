<template>
  <div class="logs-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">操作日志</h1>
        <p class="page-curator-subtitle">
          查看系统管理员的操作记录，追踪系统操作轨迹
        </p>
      </div>
    </header>
    <div class="logs-work-surface">
      <div class="search-form-wrap">
        <el-form
          ref="formValidate"
          inline
          @submit.prevent
        >
          <el-form-item :label="fromList.title + '：'">
            <el-date-picker
              v-model="timeVal"
              clearable
              :editable="false"
              format="yyyy/MM/dd HH:mm:ss"
              type="datetimerange"
              value-format="yyyy/MM/dd HH:mm:ss"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              :default-time="['00:00:00', '23:59:59']"
              @change="onchangeTime"
            />
          </el-form-item>
          <el-form-item label="管理员：">
            <el-select
              v-model="formValidate.user_id"
              multiple
              clearable
              class="form_content_width"
              @change="userSearchs"
            >
              <el-option
                v-for="item in dataList"
                :key="item.value"
                :value="item.value"
                :label="item.label"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="链接：">
            <el-input
              v-model="formValidate.path"
              placeholder="请输入链接"
              class="form_content_width"
              clearable
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" @click="userSearchs">搜索</el-button>
            <el-button class="btn-curator-reset" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-table
        ref="selection"
        v-loading="loading"
        :data="tabList"
        empty-text="暂无数据"
        highlight-current-row
      >
        <el-table-column
          prop="id"
          label="ID"
          width="80"
        />
        <el-table-column
          label="ID/名称"
          min-width="100"
        >
          <template #default="scope">
            <span>{{ scope.row.admin_id + ' / ' + scope.row.adminUser.account }}</span>
          </template>
        </el-table-column>
        <el-table-column
          prop="path"
          label="链接"
          min-width="100"
        />
        <el-table-column
          prop="ip"
          label="操作ip"
          min-width="100"
        />
        <el-table-column
          prop="create_time"
          label="操作时间"
          min-width="100"
        />
      </el-table>
      <div class="acea-row row-right page">
        <el-pagination
          v-if="total"
          v-model:current-page="formValidate.page"
          :total="total"
          :page-size="formValidate.limit"
          @current-change="currentChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { searchAdminApi, systemListApi } from '@/api/system'

defineOptions({
  name: 'SystemLog'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message

const fromList = {
  title: '选择时间',
  custom: true,
  fromTxt: [
    { text: '全部', val: '' },
    { text: '今天', val: 'today' },
    { text: '昨天', val: 'yesterday' },
    { text: '最近7天', val: 'lately7' },
    { text: '最近30天', val: 'lately30' },
    { text: '本月', val: 'month' },
    { text: '本年', val: 'year' }
  ]
}

const timeVal = ref([])
const loading = ref(false)
const tabList = ref([])
const total = ref(0)
const dataList = ref([])
const formValidate = reactive(getDefaultFormValidate())

function getDefaultFormValidate() {
  return {
    limit: 20,
    page: 1,
    method: '',
    created_at: '',
    path: '',
    user_id: []
  }
}

function currentChange(page) {
  formValidate.page = page
  getList()
}

function onchangeTime(value) {
  timeVal.value = value
  formValidate.created_at = timeVal.value ? timeVal.value.join('-') : ''
  formValidate.page = 1
  getList()
}

function getSearchAdmin() {
  searchAdminApi()
    .then(res => {
      dataList.value = res.data || []
    })
    .catch(res => {
      message.error(res.message)
    })
}

function getList() {
  loading.value = true
  systemListApi(formValidate)
    .then(res => {
      const data = res.data || {}
      tabList.value = data.list || []
      total.value = data.count || 0
    })
    .catch(res => {
      message.error(res.message)
    })
    .finally(() => {
      loading.value = false
    })
}

function userSearchs() {
  formValidate.page = 1
  getList()
}

function handleReset() {
  timeVal.value = []
  Object.assign(formValidate, getDefaultFormValidate())
  getList()
}

onBeforeMount(() => {
  getSearchAdmin()
  getList()
})
</script>

<style scoped lang="scss">
.logs-curator-page {
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

.logs-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-form-wrap {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--ds-outline-variant);
}

:deep(.btn-curator-primary.el-button--primary) {
  background: var(--ds-primary);
  border-color: var(--ds-primary);
  color: var(--ds-on-primary);

  &:hover, &:focus {
    background: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

:deep(.btn-curator-reset) {
  background: var(--ds-surface-container-highest);
  border-color: var(--ds-outline);
  color: var(--ds-on-surface);

  &:hover, &:focus {
    background: var(--ds-surface-container-high);
    border-color: var(--ds-outline-variant);
  }
}
</style>
