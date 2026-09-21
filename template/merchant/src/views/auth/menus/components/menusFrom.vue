<template>
  <div>
    <el-dialog
      v-model="modals"
      width="540px"
      :title="titleFrom"
      :close-on-click-modal="false"
      @closed="closed"
    >
      <el-form
        ref="formRef"
        :model="formValidate"
        label-width="80px"
        @submit.prevent
      >
        <el-row :gutter="24">
          <el-col v-bind="grid">
            <el-form-item label="类型：">
              <el-radio-group
                v-model="formValidate.auth_type"
                @input="changeAuthType"
              >
                <el-radio
                  v-for="(item, i) in optionsRadio"
                  :key="i"
                  :label="item.value"
                >
                  <span>{{ item.label }}</span>
                </el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col v-bind="grid">
            <el-form-item
              :label="!authType ? '菜单名称：' : '接口名称：'"
              prop="menu_name"
            >
              <div class="add">
                <el-input
                  v-model="formValidate.title"
                  :placeholder="!authType ? '请输入菜单名称' : '请输入接口名称'"
                />
              </div>
            </el-form-item>
          </el-col>
          <el-col v-bind="grid">
            <el-form-item label="父级分类：">
              <el-cascader
                v-model="formValidate.parent_id"
                :options="menuList"
                change-on-select
                collapse-tags
                filterable
                :props="{value:'id',label:'title'}"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col
            v-if="authType == 0"
            v-bind="grid"
          >
            <el-form-item
              label="页面地址："
              prop="path"
            >
              <el-input
                v-model="formValidate.path"
                placeholder="请输入页面地址"
                @change="changeUnique"
              >
                <template #prepend>
                  <span>{{ $routeProStr }}</span>
                </template>
              </el-input>
            </el-form-item>
          </el-col>
          <el-col
            v-if="authType == 1"
            v-bind="grid"
          >
            <el-form-item
              label="接口地址："
              prop="api_url"
            >
              <el-select
                v-model="formValidate.api_url"
                filterable
                allow-create
                default-first-option
                clearable
                class="wd100"
                @change="changeApiUrl()"
              >
                <el-option
                  v-for="(item, i) in ruleList"
                  :key="i"
                  :label="'['+item.method+']'+item.rule"
                  :value="'['+item.method+']'+item.rule"
                  placeholder="请输入接口地址"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col
            v-if="authType == 1"
            v-bind="grid"
          >
            <el-form-item
              label="请求方式："
              prop="method"
            >
              <el-select v-model="formValidate.method">
                <el-option
                  value="GET"
                  label="GET"
                />
                <el-option
                  value="POST"
                  label="POST"
                />
                <el-option
                  value="PUT"
                  label="PUT"
                />
                <el-option
                  value="DELETE"
                  label="DELETE"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col
            v-if="authType == 2"
            v-bind="grid"
          >
            <el-form-item
              label="配置模板："
              prop="config_template_id"
            >
              <el-select
                v-model="formValidate.config_template_id"
                filterable
                clearable
                class="wd100"
                placeholder="请选择配置模板"
                @change="changeConfigTemplate"
              >
                <el-option
                  v-for="item in configTemplateList"
                  :key="item.id"
                  :label="item.config_name"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col
            v-if="authType == 2"
            v-bind="grid"
          >
            <el-form-item label="页面地址：">
              <el-input
                v-model="formValidate.path"
                placeholder="选择配置模板后自动生成"
                readonly
              >
                <template #prepend>
                  <span>{{ $routeProStr }}</span>
                </template>
              </el-input>
            </el-form-item>
          </el-col>
          <el-col
            v-if="authType != 1"
            v-bind="grid"
          >
            <el-form-item label="图标：">
              <el-input
                v-model="formValidate.icon"
                placeholder="请选择图标，点击右面图标"
              >
                <template #append>
                  <el-button
                    :icon="Picture"
                    @click="iconClick"
                  />
                </template>
              </el-input>
            </el-form-item>
          </el-col>
          <el-col v-bind="grid">
            <el-form-item label="排序：">
              <el-input
                v-model="formValidate.sort"
                type="number"
                placeholder="请输入排序"
                number
              />
            </el-form-item>
          </el-col>
          <el-col v-bind="grid">
            <el-form-item label="隐藏菜单：">
              <el-radio-group
                v-model="formValidate.is_hidden"
                @input="changeShow"
              >
                <el-radio
                  v-for="(item, i) in radio"
                  :key="i"
                  :label="item.value"
                >
                  <span>{{ item.label }}</span>
                </el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col v-bind="grid">
            <el-form-item label="状态：">
              <el-radio-group
                v-model="formValidate.status"
                @input="changeShow"
              >
                <el-radio
                  v-for="(item, i) in isShowRadio"
                  :key="i"
                  :label="item.value"
                >
                  <span>{{ item.label }}</span>
                </el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="handleReset">取 消</el-button>
          <el-button
            type="primary"
            @click="handleSubmit"
          >提 交</el-button>
        </span>
      </template>
    </el-dialog>
    <el-dialog
      v-model="modal12"
      width="720px"
      title="图标选择"
    >
      <el-input
        ref="search"
        v-model="iconVal"
        placeholder="输入关键词搜索,注意全是英文"
        clearable
        style="width: 300px"
        @change="upIcon(iconVal)"
      />
      <div class="trees-coadd">
        <div class="scollhide">
          <div class="iconlist">
            <ul class="list-inline">
              <li
                v-for="(item, i) in iconVal ? searchData : list"
                :key="i"
                class="icons-item"
                :title="item"
              >
                <LegacyIcon
                  :name="item"
                  class="f-s-24"
                  @click="iconChange(item)"
                />
              </li>
            </ul>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { addMenusApi, getConfigTemplateOptionsApi, getMenuDetailApi, getMenusListApi, getMenusRuleApi } from '@/api/system'
import icon from '@/utils/icons'
import settings from '@/settings'
import { useUserStore } from '@/store/modules/user'
import { nextTick, onMounted, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Picture } from '@element-plus/icons-vue'

defineOptions({
  name: 'MenusFrom'
})

const props = defineProps({
  formVal: {
    type: Object,
    default: null
  },
  titleFrom: {
    type: String,
    default: ''
  },
  parentId: {
    type: Number,
    default: 0
  }
})
const emit = defineEmits(['selectRule', 'changeMenu', 'clearFrom'])

const userStore = useUserStore()
const routeProStr = settings.prefix ? '/' + settings.prefix + '/' : ''

const formRef = ref(null)
const arrs = ref([])
const searchRule = ref('')
const iconVal = ref('')
const grid = {
  xl: 22,
  lg: 22,
  md: 22,
  sm: 22,
  xs: 22
}
const modals = ref(false)
const modal12 = ref(false)
const FromData = ref([])
const valids = ref(false)
const list2 = ref([])
const list = ref(icon)
const search = ref(icon)
const ruleModal = ref(false)
const ruleList = ref([])
const authType = ref(0)
const formValidate = ref({
  auth_type: 0,
  parent_id: 0,
  path: '',
  title: '',
  icon: '',
  status: 1,
  sort: 0,
  api_url: '',
  method: '',
  id: 0,
  is_hidden: 0,
  config_template_id: ''
})
const searchData = ref([])
const configTemplateList = ref([])
const isShowRadio = [
  { value: 1, label: '开启' },
  { value: 0, label: '关闭' }
]
const radio = [
  { value: 0, label: '否' },
  { value: 1, label: '是' }
]
const optionsRadio = [
  {
    label: '菜单',
    value: 0
  },
  {
    label: '接口',
    value: 1
  },
  {
    label: '配置',
    value: 2
  }
]
const menuList = ref([])

watch(() => props.parentId, (n) => {
  console.log(n)
  formValidate.value.parent_id = n
})
watch(() => props.formVal, (val) => {
  formValidate.value = val
})
watch(() => formValidate.value.header, (n) => {
  formValidate.value.is_header = n ? 1 : 0
})
watch(() => formValidate.value.auth_type, (n) => {
  if (n === undefined) {
    n = 1
  }
  authType.value = n
})
watch(modals, (n) => {
  if (n) {
    getMenuList()
  }
})

onMounted(() => {
  getMenusRule()
  getConfigTemplateList()
})

function closed() {
  formValidate.value = {
    auth_type: 0,
    parent_id: 0,
    path: '',
    title: '',
    icon: '',
    status: 1,
    sort: 0,
    api_url: '',
    method: '',
    id: 0,
    is_hidden: 0,
    config_template_id: ''
  }
}

function getMenusRule() {
  getMenusRuleApi().then(res => {
    ruleList.value = res.data
  })
}

function getConfigTemplateList() {
  getConfigTemplateOptionsApi().then(res => {
    configTemplateList.value = res.data
  })
}

function changeApiUrl() {
  formValidate.value.method = ruleList.value.find(item => {
    return '[' + item.method + ']' + item.rule === formValidate.value.api_url
  }).method.toUpperCase()
}

function openCreateModel(id) {
  getMenuDetailApi(id).then(res => {
    modals.value = true
    formValidate.value = res.data
    if (formValidate.value.auth_type === 1) {
      formValidate.value.api_url = '[' + formValidate.value.method + ']' + formValidate.value.api_url
    }
  })
}

function getMenuList(update) {
  getMenusListApi({ auth_type: 0, status: update ? 1 : '' }).then(res => {
    menuList.value = res.data
    if (update) {
      userStore.setMenus(res.data)
    }
  })
}

function modalchange() {
  arrs.value = []
  ruleModal.value = ''
  ruleModal.value = false
}

function changeUnique(val) {
  let value = routeProStr + val.target.value
  if (value.slice(0, 1) === '/') value = value.replace('/', '')
  formValidate.value.unique_auth = value.replaceAll('/', '-')
}

function changeAuthType(val) {
  authType.value = val
  if (val === 2) {
    formValidate.value.path = ''
    formValidate.value.config_template_id = ''
  }
}

function changeConfigTemplate(id) {
  if (id) {
    formValidate.value.path = `/setting/config/form/${id}`
  } else {
    formValidate.value.path = ''
  }
}

function changeShow(val) {
  formValidate.value.is_show = val
}

function selectRule(data) {
  emit('selectRule', data)
  nextTick(() => {
    ruleModal.value = false
  })
}

// 搜索
function upIcon(n) {
  searchData.value = list.value.filter((item) => item.indexOf(iconVal.value) > -1)
}

// 搜索规则
function searchRules() {
  if (searchRule.value.trim()) {
    arrs.value = []
    for (var i = 0; i < ruleList.value.length; i++) {
      if (ruleList.value[i].real_name.indexOf(searchRule.value) !== -1) {
        arrs.value.push(i)
      }
    }
  } else {
    arrs.value = []
  }
}

function init() {
  searchRule.value = ''
  arrs.value = []
}

function iconClick() {
  modal12.value = true
}

function iconChange(n) {
  formValidate.value.icon = n
  modal12.value = false
}

// 提交
function handleSubmit() {
  // 判断是否选择父级分类
  if (!formValidate.value.title) {
    return ElMessage.warning('请填写菜单/接口名称')
  }
  if (!formValidate.value.path && !authType.value) {
    return ElMessage.warning('请填写页面/按钮地址')
  }
  if (!formValidate.value.api_url && authType.value === 1) {
    return ElMessage.warning('请填写接口地址')
  }
  if (authType.value === 2 && !formValidate.value.config_template_id) {
    return ElMessage.warning('请选择配置模板')
  }
  valids.value = true
  // 找到]结尾的地方接去掉,保留]后的内容
  if (formValidate.value.auth_type === 1) {
    formValidate.value.api_url = formValidate.value.api_url.substring(formValidate.value.api_url.indexOf(']') + 1)
  }
  addMenusApi(formValidate.value.id, formValidate.value)
    .then(async(res) => {
      ElMessage.success(res.message)
      modals.value = false
      emit('changeMenu')
      formValidate.value = {
        auth_type: 0,
        parent_id: 0,
        path: '',
        title: '',
        icon: '',
        status: 1,
        sort: 0,
        api_url: '',
        method: '',
        id: 0,
        is_hidden: 0,
        config_template_id: ''
      }
      userStore.fetchMenus()
      getMenusRule()
    })
    .catch((res) => {
      valids.value = false
      ElMessage.error(res.message)
    })
}

function handleReset() {
  modals.value = false
  formRef.value.resetFields()
  closed()
  emit('clearFrom')
}

const openModal = () => {
  modals.value = true
}

const resetValids = () => {
  valids.value = false
}

defineExpose({ openCreateModel, openModal, resetValids })
</script>

<style scoped>
.trees-coadd {
  width: 100%;
  height: 500px;
  border-radius: 4px;
  overflow: hidden;
}

.scollhide {
  width: 100%;
  height: 100%;
  overflow: auto;
  margin-left: 18px;
  padding: 10px 0 10px 0;
  box-sizing: border-box;
}

.content {
  font-size: 12px;
}

.time {
  font-size: 12px;
  color: #2d8cf0;
}

.icons-item {
  float: left;
  margin: 6px 6px 6px 0;
  width: 53px;
  text-align: center;
  list-style: none;
  cursor: pointer;
  height: 50px;
  color: #5c6b77;
  transition: all 0.2s ease;
  position: relative;
  padding-top: 10px;
}

.icons-item .f-s-24 {
  font-size: 24px;
}

.search-rule {
  display: flex;
  align-items: center;
  padding: 10px;
  background-color: #f2f2f2;
}

.rule {
  display: flex;
  flex-wrap: wrap;
  max-height: 700px;
  overflow: scroll;
}

/*定义滚动条高宽及背景 高宽分别对应横竖滚动条的尺寸*/
.rule::-webkit-scrollbar {
  width: 10px;
  height: 10px;
  background-color: #f5f5f5;
}

/*定义滚动条轨道 内阴影+圆角*/
.rule::-webkit-scrollbar-track {
  border-radius: 4px;
  background-color: #f5f5f5;
}

/*定义滑块 内阴影+圆角*/
.rule::-webkit-scrollbar-thumb {
  border-radius: 4px;
  background-color: #555;
}

.rule-list {
  background-color: #f8f5f5;
  width: 32%;
  margin: 5px;
  border-radius: 3px;
  padding: 10px;
  color: #333;
  cursor: pointer;
  transition: all 0.1s;
}

.rule-list:hover {
  background-color: #c5d1dd;
}

.rule-list div {
  white-space: nowrap;
}

.select-rule {
  background-color: #c5d1dd;
}

.add {
  display: flex;
  align-items: center;
}

.df {
  display: flex;
  justify-content: center;
}

.wd100 {
  width: 100%;
}
</style>
