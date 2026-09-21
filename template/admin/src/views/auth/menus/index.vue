<template>
  <div class="menus-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">菜单管理</h1>
        <p class="page-curator-subtitle">
          配置系统菜单与权限，管理后台导航结构
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" icon="el-icon-plus" @click="menusAdd('添加菜单')">
        添加菜单
      </el-button>
    </header>
    <div class="menus-work-surface">
      <div class="search-form-wrap">
        <el-form
          ref="roleDataRef"
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
          <el-form-item label="菜单名称：">
            <el-input v-model="roleData.title" clearable placeholder="请输入菜单名称" class="form_content_width" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" @click="getData">查询</el-button>
            <el-button class="btn-curator-reset" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-table
        ref="xTable"
        class="vxeTable mt14"
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
        <el-table-column prop="unique_auth" label="图标" min-width="100">
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
        <el-table-column label="操作" min-width="100" fixed="right" align="center">
          <template v-slot="{ row }">
            <a v-if="!row.auth_type" @click="addE(row, '添加子菜单')">添加子菜单</a>
            <el-divider v-if="!row.auth_type" direction="vertical" />
            <a @click="edit(row, '编辑')">编辑</a>
            <el-divider direction="vertical" />
            <a @click="del(row, '删除菜单')">删除</a>
          </template>
        </el-table-column>
      </el-table>
    </div>
    <menus-from
      ref="menusFromRef"
      :form-val="formValidate"
      :parent-id="parentId"
      :title-from="titleFrom"
      @changeMenu="changeMenu"
      @clearFrom="clearFrom"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import {
  getMenusListApi,
  putStatusApi
} from '@/api/system'
import { useUserStore } from '@/store/modules/user'
import { useAppStore } from '@/store/modules/app'
import { storeToRefs } from 'pinia'
import menusFrom from './components/menusFrom'

defineOptions({
  name: 'Menus'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const modalSure = proxy.$modalSure
const userStore = useUserStore()
const appStore = useAppStore()
const { device } = storeToRefs(appStore)
const emit = defineEmits(['onCancel'])

const menusFromRef = ref(null)
const loading = ref(false)
const tableData = ref([])
const titleFrom = ref('')
const parentId = ref(0)

const roleData = reactive({
  status: '',
  title: ''
})

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

const isMobile = computed(() => device.value === 'mobile')
const labelWidth = computed(() => (isMobile.value ? undefined : '80px'))
const labelPosition = computed(() => (isMobile.value ? 'top' : 'right'))

function handleReset() {
  Object.assign(roleData, { status: '', title: '' })
  getData()
}

function onchangeIsShow(row) {
  putStatusApi(row.id, row.status)
    .then(res => {
      message.success(res.message)
      userStore.fetchMenus()
    })
    .catch(res => {
      message.error(res.message)
    })
}

function changeMenu() {
  titleFrom.value = ''
  parentId.value = 0
  getData()
}

function clearFrom() {}

function addE(row, title) {
  titleFrom.value = title
  parentId.value = row.id
  if (menusFromRef.value) {
    menusFromRef.value.modals = true
    menusFromRef.value.valids = false
  }
}

function del(row, tit) {
  const delfromData = {
    title: tit,
    url: `/setting/menu/${row.id}`,
    method: 'DELETE',
    ids: ''
  }

  modalSure(delfromData)
    .then(res => {
      message.success(res.message)
      getData()
      userStore.fetchMenus()
    })
    .catch(res => {
      message.error(res.message || res)
    })
}

function edit(row, title) {
  titleFrom.value = title
  menusFromRef.value && menusFromRef.value.openCreateModel(row.id)
}

function menusAdd(title) {
  if (menusFromRef.value) {
    menusFromRef.value.modals = true
    menusFromRef.value.valids = false
  }
  titleFrom.value = title
}

function getData() {
  loading.value = true
  roleData.status = roleData.status || ''
  getMenusListApi(roleData)
    .then(res => {
      tableData.value = res.data || []
    })
    .catch(res => {
      message.error(res.message)
    })
    .finally(() => {
      loading.value = false
    })
}

function cancel() {
  emit('onCancel')
}

onBeforeMount(() => {
  getData()
})
</script>

<style scoped lang="scss">
.menus-curator-page {
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

.menus-work-surface {
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

.vxeTable {
  > .vxe-table--header-wrapper {
    background: #fff !important;
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

.rule-list:hover {
  background-color: var(--prev-bg-menu-hover-ba-color);
}

.rule-list div {
  white-space: nowrap;
}

.select-rule {
  background-color: var(--prev-bg-menu-hover-ba-color);
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
  color: var(--prev-color-primary);
  font-size: 12px;
  margin-left: 6px;
}

.el-icon-arrow-down {
  font-size: 12px;
}

.el-dialog__body {
  padding: 30px 20px 0;
}
</style>
