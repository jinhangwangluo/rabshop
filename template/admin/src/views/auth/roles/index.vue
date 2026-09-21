<template>
  <div class="roles-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">角色管理</h1>
        <p class="page-curator-subtitle">
          管理系统角色，配置角色权限与状态
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" icon="el-icon-plus" @click="add('添加')">
        添加身份
      </el-button>
    </header>
    <div class="roles-work-surface">
      <div class="search-form-wrap">
        <el-form
          ref="formValidateRef"
          :model="formValidate"
          inline
          @submit.prevent
        >
          <el-form-item label="状态：">
            <el-select
              v-model="formValidate.status"
              placeholder="请选择"
              clearable
              class="form_content_width"
              @change="userSearchs"
            >
              <el-option value="1" label="显示" />
              <el-option value="0" label="不显示" />
            </el-select>
          </el-form-item>
          <el-form-item label="身份昵称：">
            <el-input
              v-model="formValidate.name"
              clearable
              placeholder="请输入身份昵称"
              class="form_content_width"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" @click="userSearchs">查询</el-button>
            <el-button class="btn-curator-reset" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>
      <el-table
        ref="tableRef"
        v-loading="loading"
        :data="tableList"
        highlight-current-row
      >
        <el-table-column
          prop="id"
          label="ID"
          min-width="80"
        />
        <el-table-column
          prop="name"
          label="身份昵称"
          min-width="130"
        />
        <el-table-column
          label="状态"
          min-width="120"
        >
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              class="defineSwitch"
              :active-value="1"
              :inactive-value="0"
              :value="scope.row.status"
              size="large"
              active-text="显示"
              inactive-text="隐藏"
              @change="onchangeIsShow(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column
          label="操作"
          fixed="right"
          width="120"
        >
          <template #default="scope">
            <a @click="edit(scope.row, '编辑')">编辑</a>
            <el-divider direction="vertical" />
            <a @click="del(scope.row, '删除', scope.$index)">删除</a>
          </template>
        </el-table-column>
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
    <!-- 新增编辑-->
    <el-dialog
      v-model="modals"
      :title="`${modelTit}身份`"
      :close-on-click-modal="false"
      :show-close="true"
      width="540px"
      @closed="closed"
    >
      <el-form
        ref="formInlineRef"
        :model="formInline"
        :rules="ruleValidate"
        label-width="100px"
        @submit.prevent
      >
        <el-form-item
          label="身份名称："
          label-for="role_name"
          prop="role_name"
        >
          <el-input
            v-model="formInline.role_name"
            placeholder="请输入身份昵称"
          />
        </el-form-item>
        <el-form-item
          label="是否开启："
          prop="status"
        >
          <el-radio-group v-model="formInline.status">
            <el-radio :label="1">
              开启
            </el-radio>
            <el-radio :label="0">
              关闭
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="权限：">
          <div class="trees-coadd">
            <div class="scollhide">
              <div class="iconlist">
                <el-tree
                  ref="treeRef"
                  :data="menusList"
                  node-key="id"
                  check-strictly
                  show-checkbox
                  highlight-current
                  :default-checked-keys="selectIds"
                  :props="defaultProps"
                  @check="clickDeal"
                />
              </div>
            </div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="onCancel">取 消</el-button>
          <el-button type="primary" @click="handleSubmit">提 交</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>
<script setup>
import { nextTick, onBeforeMount, reactive, ref } from 'vue'
import { getCurrentInstance } from 'vue'
import { roleListApi, roleSetStatusApi, getMenusListApi, roleCreateApi, roleInfoApi } from '@/api/system'

defineOptions({
  name: 'Index'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const modalSure = proxy.$modalSure

const formValidateRef = ref(null)
const tableRef = ref(null)
const formInlineRef = ref(null)
const treeRef = ref(null)
const modals = ref(false)
const total = ref(0)
const loading = ref(false)
const tableList = ref([])
const id = ref(0)
const menusList = ref([])
const selectIds = ref([])
const modelTit = ref('')

const formValidate = reactive({
  status: '',
  name: '',
  page: 1,
  limit: 20
})

const formInline = reactive({
  role_name: '',
  status: 0,
  rule: []
})

const ruleValidate = {
  role_name: [{ required: true, message: '请输入身份昵称', trigger: 'blur' }],
  status: [{ required: true, type: 'number', message: '请选择是否开启', trigger: 'change' }]
}

const defaultProps = {
  children: 'children',
  label: 'title'
}

function getDefaultFormInline() {
  return {
    role_name: '',
    status: 0,
    rule: []
  }
}

function currentChange(page) {
  formValidate.page = page
  getList()
}

function closed() {
  Object.assign(formInline, getDefaultFormInline())
  selectIds.value = []
}

function add(name) {
  id.value = 0
  modelTit.value = name
  modals.value = true
}

function del(row, tit, num) {
  const delfromData = {
    title: tit,
    num,
    url: `system/roule/${row.id}`,
    method: 'DELETE',
    ids: ''
  }
  modalSure(delfromData)
    .then(res => {
      message.success(res.message)
      tableList.value.splice(num, 1)
    })
    .catch(res => {
      message.error(res.message)
    })
}

function onchangeIsShow(row) {
  roleSetStatusApi(row.id, row.status)
    .then(res => {
      message.success(res.message)
    })
    .catch(res => {
      message.error(res.message)
    })
}

function getList() {
  loading.value = true
  formValidate.status = formValidate.status || ''
  roleListApi(formValidate)
    .then(res => {
      const data = res.data || {}
      tableList.value = data.list || []
      total.value = data.count || 0
    })
    .catch(res => {
      message.error(res.message)
    })
    .finally(() => {
      loading.value = false
    })
}

function handleReset() {
  Object.assign(formValidate, {
    status: '',
    name: '',
    page: 1,
    limit: 20
  })
  userSearchs()
}

function userSearchs() {
  formValidate.page = 1
  getList()
}

function edit(row, name) {
  modelTit.value = name
  id.value = row.id
  modals.value = true
  getIofo(row)
}

function getmenusList() {
  getMenusListApi({ status: 1 })
    .then(res => {
      menusList.value = (res.data || []).map(item => ({
        ...item,
        expand: false
      }))
    })
    .catch(res => {
      message.error(res.message)
    })
}

function getIofo(row) {
  roleInfoApi(row.id)
    .then(res => {
      const data = res.data || {}
      Object.assign(formInline, getDefaultFormInline(), {
        role_name: data.role_name || '',
        rule: Array.isArray(data.rule) ? data.rule : [],
        status: Number(data.status) || 0,
        store_manage_ids: data.store_manage_ids
      })
      nextTick(() => {
        selectIds.value = formInline.rule.slice()
        tidyRes(menusList.value)
        treeRef.value && treeRef.value.setCheckedKeys(selectIds.value)
      })
    })
    .catch(res => {
      message.error(res.message)
    })
}

function clickDeal(currentObj, treeStatus) {
  const selected = treeStatus.checkedKeys.indexOf(currentObj.id)
  if (selected !== -1) {
    selectedParent(currentObj)
    uniteChildSame(currentObj, true)
    return
  }
  if (currentObj.children && currentObj.children.length) {
    uniteChildSame(currentObj, false)
  }
  let selectedParentChild = false
  const parentNode = currentObj.pid && treeRef.value
    ? treeRef.value.getNode(currentObj.pid).data
    : undefined
  if (parentNode && parentNode.children && parentNode.children.length) {
    selectedParentChild = parentNode.children.some(child => treeStatus.checkedKeys.includes(child.id))
  }
  if (!selectedParentChild && currentObj.pid && treeRef.value) {
    treeRef.value.setChecked(currentObj.pid, false)
  }
}

function uniteChildSame(treeList, isSelected) {
  if (!treeRef.value) return
  treeRef.value.setChecked(treeList.id, isSelected)
  if (treeList.children) {
    treeList.children.forEach(child => uniteChildSame(child, isSelected))
  }
}

function selectedParent(currentObj) {
  if (!treeRef.value) return
  const currentNode = treeRef.value.getNode(currentObj)
  if (currentNode && currentNode.parent && currentNode.parent.key !== undefined) {
    treeRef.value.setChecked(currentNode.parent.key, true)
    selectedParent(currentNode.parent.data)
  }
}

function tidyRes(menus) {
  menusList.value = (menus || []).map(menu => {
    if (menu.title === '主页') {
      return {
        ...menu,
        checked: true,
        children: (menu.children || []).map(child => ({
          ...child,
          checked: true
        }))
      }
    }
    return initMenu(menu)
  })
}

function initMenu(menu) {
  const rawCheckedMenus = formInline.checked_menus || formInline.rule || []
  const checkedMenus = Array.isArray(rawCheckedMenus)
    ? rawCheckedMenus.map(item => String(item))
    : String(rawCheckedMenus).split(',').map(item => item.trim())
  const data = {
    title: menu.title,
    id: menu.id,
    pid: menu.pid,
    children: menu.children,
    checked: menu.checked
  }
  if (menu.children && menu.children.length > 0) {
    data.children = menu.children.map(child => initMenu(child))
  } else {
    data.checked = checkedMenus.includes(String(data.id))
    data.expand = !data.checked
  }
  return data
}

function handleSubmit() {
  if (!formInlineRef.value || !treeRef.value) return
  formInlineRef.value.validate(valid => {
    if (!valid) return
    formInline.rule = [
      ...treeRef.value.getCheckedKeys(),
      ...treeRef.value.getHalfCheckedKeys()
    ]
    if (formInline.rule.length === 0) {
      message.warning('请至少选择一个权限')
      return
    }
    roleCreateApi(id.value, formInline)
      .then(res => {
        message.success(res.message)
        modals.value = false
        id.value = 0
        getList()
        formInlineRef.value.resetFields()
        formInline.rule = []
      })
      .catch(res => {
        message.error(res.message)
      })
  })
}

function onCancel() {
  formInlineRef.value && formInlineRef.value.resetFields()
  Object.assign(formInline, getDefaultFormInline())
  selectIds.value = []
  id.value = 0
  modals.value = false
}

onBeforeMount(() => {
  getList()
  getmenusList()
})
</script>

<style scoped lang="scss">
.roles-curator-page {
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

.roles-work-surface {
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

.trees-coadd {
  width: 100%;
  height: 385px;
}

.trees-coadd .scollhide {
  width: 100%;
  height: 100%;
  overflow-x: hidden;
  overflow-y: scroll;
}

.scollhide::-webkit-scrollbar {
  display: none;
}
</style>
