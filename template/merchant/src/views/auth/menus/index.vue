<template>
  <div class="auth-menus-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">菜单权限</h1>
        <p class="page-curator-subtitle">
          管理后台菜单、接口规则与层级展示状态，统一导航与权限入口配置。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" @click="menusAdd('添加菜单')">添加菜单</el-button>
    </header>

    <div class="auth-menus-work-surface">
      <el-card :bordered="false" shadow="never" class="search-card" :body-style="{ padding: 0 }">
        <div class="padding-add">
          <el-form
            ref="roleData"
            :model="roleData"
            :label-width="labelWidth"
            :label-position="labelPosition"
            inline
            @submit.prevent
          >
            <el-form-item label="菜单状态：">
              <el-select
                v-model="roleData.status"
                placeholder="请选择"
                clearable
                class="form_content_width"
                @change="getData"
              >
                <el-option value="1" label="开启" />
                <el-option value="0" label="关闭" />
              </el-select>
            </el-form-item>
            <el-form-item label="菜单名称：" prop="status2" label-for="status2">
              <el-input v-model="roleData.title" clearable placeholder="请输入菜单名称" class="form_content_width" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" class="btn-curator-primary" @click="getData">查询</el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-card>
      <el-card :bordered="false" shadow="never" class="table-card">
        <el-table
          ref="xTable"
          class="vxeTable menu-curator-table mt14"
          :loading="loading"
          :data="tableData"
          row-key="id"
          :tree-props="{children: 'children'}"
        >
          <el-table-column prop="title" label="菜单名称" min-width="150">
            <template v-slot="{ row }">
              {{ row.title }}
              <span v-if="row.auth_type === 0">
                <el-tag v-if="row.hidden" type="warning">隐</el-tag>
                <el-tag v-else type="success">显</el-tag>
              </span>
            </template>
          </el-table-column>
          <el-table-column prop="unique_auth" label="图标" min-width="200">
            <template v-slot="{ row }">
              <LegacyIcon v-if="row.icon" :name="row.icon" />
            </template>
          </el-table-column>
          <el-table-column label="路由" min-width="240">
            <template v-slot="{ row }">
              <span v-if="row.auth_type == 1">接口：[{{ row.methods }}]{{ row.path }}</span>
              <span v-else>菜单：{{ row.path }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="flag" label="规则状态" min-width="120">
            <template v-slot="{ row }">
              <el-switch
                v-model="row.status"
                :active-value="1"
                :inactive-value="0"
                :value="row.status"
                size="large"
                @change="onchangeIsShow(row)"
              />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="230" fixed="right">
            <template v-slot="{ row }">
              <a v-if="!row.auth_type" @click="addE(row, '添加子菜单')">添加子菜单</a>
              <el-divider v-if="!row.auth_type" direction="vertical" />
              <a @click="edit(row, '编辑')">编辑</a>
              <el-divider direction="vertical" />
              <a @click="del(row, '删除菜单')">删除</a>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>
    <menus-from
      ref="menusFromRef"
      :form-val="formValidate"
      :parent-id="parentId"
      :title-from="titleFrom"
      @getList="getList"
      @changeMenu="changeMenu"
      @clearFrom="clearFrom"
    />
  </div>
</template>

<script setup>

import {
  getMenusListApi,
  putStatusApi
} from '@/api/system'
import menusFrom from './components/menusFrom'
import { modalSure } from '@/utils/index'
import { useUserStore } from '@/store/modules/user'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'

defineOptions({
  name: 'Menus'
})

const emit = defineEmits(['onCancel'])

const userStore = useUserStore()
const menusFromRef = ref(null)

const children = ref([])
const expandedKeys = ref([])
const spinShow = ref(false)
const ruleModal = ref(false)
const searchRule = ref('')
const roleData = reactive({
  status: '',
  title: ''
})
const ruleCateList = ref([]) // 权限树
const loading = ref(false)
const tableData = ref([])
const FromData = ref(null)
const icons = ref('')
const formValidate = reactive({
  auth_type: '',
  menu_name: '',
  path: '',
  api_url: '',
  menu_path: '',
  methods: '',
  unique_auth: '',
  mark: '',
  sort: '',
  is_show: 0
})
const titleFrom = ref('')
const modalTitleSs = ref('')
const routeType = ref('0')
const arrs = ref([])
const parentId = ref(0)
const foundationList = ref([]) // 基础接口列表
const openList = ref([]) // 公开接口列表
const seletRoute = ref([]) // 选中路由
const seletRouteIds = ref([]) // 选中id
const menusId = ref(0) // 选中分类id
const nodeKey = ref(0) // 选中节点

const isMobile = ref(false)
const labelWidth = computed(() => {
  return isMobile.value ? undefined : '80px'
})
const labelPosition = computed(() => {
  return isMobile.value ? 'top' : 'right'
})

onMounted(() => {
  getData()
})

// 修改规则状态
function onchangeIsShow(row) {
  putStatusApi(row.id, row.status)
    .then(async(res) => {
      ElMessage.success(res.message)
      userStore.fetchMenus()
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function changeMenu() {
  titleFrom.value = ''
  parentId.value = 0
  getData()
}

// 请求列表
function getList() {
  getData()
}

// 清除表单数据
function clearFrom() {
}

// 添加子菜单
function addE(row, title) {
  titleFrom.value = title
  parentId.value = row.id
  menusFromRef.value.openModal()
  menusFromRef.value.resetValids()
}

// 删除
function del(row, tit) {
  const delfromData = {
    title: tit,
    url: `/setting/menu/${row.id}`,
    method: 'DELETE',
    ids: ''
  }

  modalSure(delfromData)
    .then((res) => {
      ElMessage.success(res.message)
      getData()
      userStore.fetchMenus()
    })
    .catch((res) => {
      ElMessage.error(res.message || res)
    })
}

// 编辑
function edit(row, title) {
  titleFrom.value = title
  menusFromRef.value.openCreateModel(row.id)
}

// 添加
function menusAdd(title) {
  menusFromRef.value.openModal()
  menusFromRef.value.resetValids()
  titleFrom.value = title
}

// 列表
function getData() {
  loading.value = true
  roleData.status = roleData.status || ''
  getMenusListApi(roleData)
    .then(async(res) => {
      tableData.value = res.data
      loading.value = false
    })
    .catch((res) => {
      loading.value = false
      ElMessage.error(res.message)
    })
}

// 关闭按钮
function cancel() {
  emit('onCancel')
}
</script>

<style scoped lang="scss">
.auth-menus-curator-page {
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
  max-width: 620px;
}

.auth-menus-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

.menu-curator-table {
  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.vxeTable {
  > .vxe-table--header-wrapper {
    background: transparent !important;
  }

  .icon {
    font-size: 20px;
  }
}

.rule {
  display: flex;
  flex-wrap: wrap;
  overflow-y: scroll;
  height: max-content;
  max-height: 600px;
  flex: 1;
}

.tree::-webkit-scrollbar {
  width: 2px;
  background-color: #f5f5f5;
}

.rule::-webkit-scrollbar {
  width: 10px;
  height: 10px;
  background-color: #f5f5f5;
}

.rule::-webkit-scrollbar-track {
  border-radius: 4px;
  background-color: #f5f5f5;
}

.rule::-webkit-scrollbar-thumb {
  border-radius: 4px;
  background-color: #ccc;
}

.rule-list {
  background-color: #f2f2f2;
  width: 48.5%;
  height: max-content;
  margin: 5px;
  border-radius: 3px;
  padding: 10px;
  color: #333;
  cursor: pointer;
  transition: all 0.1s;
}

.rule-list:hover,
.select-rule {
  background-color: var(--prev-bg-menu-hover-ba-color);
}

.rule-list div {
  white-space: nowrap;
}

.route-list {
  display: flex;
  margin-top: 10px;

  .tree {
    width: 200px;
    overflow-y: scroll;
    max-height: 600px;

    :deep(.el-tree-node__children .el-tree-node .el-tree-node__content) {
      padding-left: 14px !important;
    }
  }
}

.el-dropdown-link {
  cursor: pointer;
  color: var(--ds-primary);
  font-size: 12px;
  margin-left: 6px;
}

.el-icon-arrow-down {
  font-size: 12px;
}

.el-dialog__body {
  padding: 30px 20px 0;
}

a {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--ds-primary-hover);
  }
}
</style>
