<template>
  <div class="menus-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商户菜单</h1>
        <p class="page-curator-subtitle">
          管理商户端菜单权限，支持添加、编辑、删除菜单及配置接口权限。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"

        @click="menusAdd('添加菜单')"
      >
        添加菜单
      </el-button>
    </header>

    <div class="menus-work-surface">
      <!-- 搜索区域 -->
      <el-form
        ref="roleDataRef"
        :model="roleData"
        :label-width="labelWidth"
        :label-position="labelPosition"
        inline
        class="search-form menus-search-form"
        @submit.prevent
      >
        <el-form-item label="菜单状态">
          <el-select
            v-model="roleData.status"
            placeholder="请选择"
            clearable
            class="search-select-sm"
            @change="getData"
          >
            <el-option value="1" label="开启" />
            <el-option value="0" label="关闭" />
          </el-select>
        </el-form-item>
        <el-form-item label="菜单名称">
          <el-input v-model="roleData.title" clearable placeholder="请输入菜单名称" class="search-input" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="getData">查询</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        ref="xTable"
        class="menus-curator-table"
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
  getMerchantMenusListApi,
  putMerchantMenuStatusApi
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
  auth_type: 0,
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

function onchangeIsShow(row) {
  putMerchantMenuStatusApi(row.id, row.status)
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
    url: `/merchant_menu/${row.id}`,
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
  getMerchantMenusListApi(roleData)
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

onBeforeMount(() => {
  getData()
})
</script>

<style scoped lang="scss">
/* material/stitch/DESIGN.md */
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
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.menus-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

.menus-search-form {
  margin-bottom: 8px;
}

.menus-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: rgba(242, 244, 247, 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

.icon {
  font-size: 20px;
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
.el-dialog__body{
  padding: 30px 20px 0;
}
</style>
