<template>
  <div class="auth-roles-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">身份管理</h1>
        <p class="page-curator-subtitle">
          管理商户后台身份角色、权限绑定与启用状态，统一账号授权范围。
        </p>
      </div>
      <el-button type="primary" class="btn-curator-primary" @click="add('添加')">
        添加身份
      </el-button>
    </header>

    <div class="auth-roles-work-surface">
      <el-card
        :bordered="false"
        shadow="never"
        class="search-card"
        :body-style="{ padding: 0 }"
      >
        <div>
          <el-form
            ref="formValidate"
            :model="formValidate"
            inline
            @submit.prevent
          >
            <el-form-item
              label="状态："
              label-for="status"
            >
              <el-select
                v-model="formValidate.status"
                placeholder="请选择"
                clearable
                class="form_content_width"
                @change="userSearchs"
              >
                <el-option
                  value="1"
                  label="显示"
                />
                <el-option
                  value="0"
                  label="不显示"
                />
              </el-select>
            </el-form-item>
            <el-form-item
              label="身份昵称："
              label-for="role_name"
            >
              <el-input
                v-model="formValidate.role_name"
                clearable
                placeholder="请输入身份昵称"
                class="form_content_width"
              />
            </el-form-item>
            <el-form-item>
              <el-button
                type="primary"
                class="btn-curator-primary"
                @click="userSearchs"
              >
                查询
              </el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-card>
      <el-card
        v-loading="spinShow"
        :bordered="false"
        shadow="never"
        class="table-card"
      >
        <el-table
          ref="table"
          v-loading="loading"
          :data="tableList"
          class="mt14 auth-roles-curator-table"
          highlight-current-row
          no-user-from-text="暂无数据"
          no-filtered-user-from-text="暂无筛选结果"
        >
          <el-table-column
            prop="id"
            label="ID"
            min-width="80"
          />
          <el-table-column
            prop="role_name"
            label="身份昵称"
            min-width="130"
          />
          <el-table-column
            label="状态"
            min-width="120"
          >
            <template #default="{ row }">
              <el-switch
                v-model="row.status"
                class="defineSwitch"
                :active-value="1"
                :inactive-value="0"
                :value="row.status"
                size="large"
                active-text="显示"
                inactive-text="隐藏"
                @change="onchangeIsShow(row)"
              />
            </template>
          </el-table-column>
          <el-table-column
            label="操作"
            fixed="right"
            width="120"
          >
            <template #default="{ row, $index }">
              <a @click="edit(row, '编辑')">编辑</a>
              <el-divider direction="vertical" />
              <a @click="del(row, '删除', $index)">删除</a>
            </template>
          </el-table-column>
        </el-table>
        <div class="acea-row row-right page">
          <el-pagination
            v-if="total"
            v-model:current-page="formValidate.page"
            class="auth-roles-curator-pagination"
            :total="total"
            :page-size="formValidate.limit"
            @current-change="currentChange"
          />
        </div>
      </el-card>
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
          <el-button class="btn-curator-reset" @click="onCancel">取 消</el-button>
          <el-button
            type="primary"
            class="btn-curator-primary"
            @click="handleSubmit"
          >提 交</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>
<script setup>

import { roleListApi, roleSetStatusApi, getMenusListApi, roleCreateApi, roleInfoApi } from '@/api/system';
import { modalSure } from '@/utils/index';
import { nextTick, onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';

defineOptions({
  name: 'Index'
});

const spinShow = ref(false);
const modals = ref(false);
const total = ref(0);
const loading = ref(false);
const formValidate = reactive({
  status: '',
  role_name: '',
  page: 1,
  limit: 20
});
const tableList = ref([]);
const formInline = ref({
  role_name: '',
  status: 0,
  rule: []
});
const id = ref(0);
const menusList = ref([]);
const selectIds = ref([]);
const modelTit = ref('');
const rows = ref(null);
const ruleValidate = {
  role_name: [{ required: true, message: '请输入身份昵称', trigger: 'blur' }],
  status: [{ required: true, type: 'number', message: '请选择是否开启', trigger: 'change' }]
  // checked_menus: [
  //     { required: true, validator: validateStatus, trigger: 'change' }
  // ]
};
const defaultProps = {
  children: 'children',
  label: 'title'
};
const formInlineRef = ref(null);
const treeRef = ref(null);

onMounted(() => {
  getList();
  getmenusList();
});

function currentChange(page) {
  formValidate.page = page
  getList()
}

function closed() {
  formInline.value = {
    role_name: '',
    status: 0,
    rule: []
  };
  selectIds.value = [];
}

// 添加
function add(name) {
  id.value = 0;
  modelTit.value = name;
  modals.value = true;
}

// 删除
function del(row, tit, num) {
  const delfromData = {
    title: tit,
    num: num,
    url: `system/roule/${row.id}`,
    method: 'DELETE',
    ids: ''
  };
  modalSure(delfromData)
    .then((res) => {
      ElMessage.success(res.message);
      tableList.value.splice(num, 1);
    })
    .catch((res) => {
      ElMessage.error(res.message);
    });
}

// 修改是否显示
function onchangeIsShow(row) {
  roleSetStatusApi(row.id, row.status)
    .then(async(res) => {
      ElMessage.success(res.message);
    })
    .catch((res) => {
      ElMessage.error(res.message);
    });
}

// 列表
function getList() {
  loading.value = true;
  formValidate.status = formValidate.status || '';
  roleListApi(formValidate)
    .then(res => {
      const data = res.data;
      tableList.value = data.list;
      total.value = res.data.count;
      loading.value = false;
    })
    .catch((res) => {
      loading.value = false;
      ElMessage.error(res.message);
    });
}

// 表格搜索
function userSearchs() {
  formValidate.page = 1;
  getList();
}

// 编辑
function edit(row, name) {
  modelTit.value = name;
  id.value = row.id;
  modals.value = true;
  rows.value = row;
  getIofo(row);
}

// 菜单列表
function getmenusList() {
  spinShow.value = true;
  getMenusListApi({ status: 1 })
    .then(res => {
      menusList.value = res.data;
      menusList.value.map((item, index) => {
        if (item.title === '主页') {
          // item.checked = true;
          // item.disableCheckbox = true;
          if (item.children.length) {
            item.children.map((v) => {
              // v.checked = true;
              // v.disableCheckbox = true;
            });
          }
        }
        item.expand = false;
      });
      spinShow.value = false;
    })
    .catch((res) => {
      spinShow.value = false;
      ElMessage.error(res.message);
    });
}

// 详情
function getIofo(row) {
  spinShow.value = true;
  roleInfoApi(row.id)
    .then(async(res) => {
      const data = res.data;
      formInline.value = {
        role_name: data.role_name,
        rule: data.rule,
        status: data.status,
        store_manage_ids: data.store_manage_ids
      }

      nextTick(() => {
        selectIds.value = formInline.value.rule;
        tidyRes(menusList.value);
        // treeRef.value.setCheckedKeys(selectIds.value);
      });
      spinShow.value = false;
    })
    .catch((res) => {
      spinShow.value = false;
      ElMessage.error(res.message);
    });
}

function forChildrenChecked(arr, status, pid) {
  if (arr.length) {
    const len = arr.length;
    for (var j = 0; j < len; j++) {
      var childNode = treeRef.value.getNode(arr[j].id).data;
      if (status) {
        treeRef.value.setChecked(childNode.id, true);
        childNode.checked = true;
      }
      if (!status) {
        treeRef.value.setChecked(childNode.id, false);
        childNode.checked = false;
      }
      if (childNode.children.length) {
        forChildrenChecked(childNode.children, status);
      }
    }
  }
}

function clickDeal(currentObj, treeStatus, ccc) {
  // 用于：父子节点严格互不关联时，父节点勾选变化时通知子节点同步变化，实现单向关联。
  const selected = treeStatus.checkedKeys.indexOf(currentObj.id); // -1未选中
  // 选中
  if (selected !== -1) {
    // 子节点只要被选中父节点就被选中
    selectedParent(currentObj);
    // 统一处理子节点为相同的勾选状态
    uniteChildSame(currentObj, true);
  } else {
    // 未选中 处理子节点全部未选中
    if (currentObj.children.length !== 0) {
      uniteChildSame(currentObj, false);
    }
    let selParent = false;
    const parentNode = currentObj.pid ? treeRef.value.getNode(currentObj.pid).data : undefined;
    if (parentNode && parentNode.children.length) {
      for (let i = 0; i < parentNode.children.length; i++) {
        console.log(treeStatus.checkedKeys, parentNode.children[i].id);
        if (treeStatus.checkedKeys.includes(parentNode.children[i].id)) {
          selParent = true;
        }
      }
    }
    if (!selParent && currentObj.pid) treeRef.value.setChecked(currentObj.pid, false);
  }
}

// 统一处理子节点为相同的勾选状态
function uniteChildSame(treeList, isSelected) {
  treeRef.value.setChecked(treeList.id, isSelected);
  if (treeList.children) {
    for (let i = 0; i < treeList.children.length; i++) {
      uniteChildSame(treeList.children[i], isSelected);
    }
  }
}

// 统一处理父节点为选中
function selectedParent(currentObj) {
  const currentNode = treeRef.value.getNode(currentObj);
  if (currentNode.parent.key !== undefined) {
    treeRef.value.setChecked(currentNode.parent, true);
    selectedParent(currentNode.parent);
  }
}

function tidyRes(menus) {
  const data = [];
  menus.map((menu) => {
    if (menu.title === '主页') {
      menu.checked = true;
      // menu.disabled = true;
      if (menu.children.length) {
        menu.children.map((v) => {
          v.checked = true;
        });
      }
      data.push(menu);
    } else {
      data.push(initMenu(menu));
    }
  });
  menusList.value = data;
}

function initMenu(menu) {
  const data = {};
  const checkMenus = ',' + formInline.value.checked_menus + ',';
  data.title = menu.title;
  data.id = menu.id;
  data.pid = menu.pid;
  data.children = menu.children;
  data.checked = menu.checked;

  if (menu.children && menu.children.length > 0) {
    data.children = [];
    menu.children.map((child) => {
      data.children.push(initMenu(child));
    });
  } else {
    data.checked = checkMenus.indexOf(String(',' + data.id + ',')) !== -1;
    data.expand = !data.checked;
  }
  return data;
}

// 提交
function handleSubmit() {
  formInlineRef.value.validate((valid) => {
    if (valid) {
      formInline.value.rule = [
        ...treeRef.value.getCheckedKeys(),
        ...treeRef.value.getHalfCheckedKeys()
      ];
      if (formInline.value.rule.length === 0) return ElMessage.warning('请至少选择一个权限');
      roleCreateApi(id.value, formInline.value)
        .then(async(res) => {
          ElMessage.success(res.message);
          modals.value = false;
          id.value = 0;
          getList();
          formInlineRef.value.resetFields();
          formInline.value.rule = [];
        })
        .catch((res) => {
          ElMessage.error(res.message);
        });
    } else {
      return false;
    }
  });
}

function onCancel() {
  formInlineRef.value.resetFields();
  formInline.value.rule = [];
  formInline.value.status = 0;
  formInline.value.role_name = '';
  selectIds.value = [];
  id.value = 0;
  modals.value = false;
}
</script>

<style scoped lang="scss">
.auth-roles-curator-page {
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
  max-width: 560px;
}

.auth-roles-work-surface {
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

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: var(--brand-primary-pale);
    color: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

.auth-roles-curator-table {
  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.auth-roles-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next:hover),
  :deep(.btn-prev:hover),
  :deep(.el-pager li:hover) {
    color: var(--ds-primary);
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

a {
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;

  &:hover {
    color: var(--ds-primary-hover);
  }
}
</style>
