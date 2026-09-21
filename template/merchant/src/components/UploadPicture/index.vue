<template>
  <div class="Modal">
    <div class="colLeft">
      <div class="Nav">
        <div class="trees-coadd">
          <div v-if="isPage" class="tree_tit" @click="addSort">
            <LegacyIcon name="el-icon-circle-plus" />
            添加分类
          </div>
          <div class="scollhide">
            <div :class="isPage ? 'tree' : 'isTree'">
              <el-tree
                :data="treeData"
                node-key="id"
                default-expand-all
                highlight-current
                :expand-on-click-node="false"
                :current-node-key="treeId"
                @node-click="appendBtn"
              >
                <template #default="{ data }">
                  <span class="custom-tree-node">
                    <!-- <span class="file-name">
                      <i class="icon el-icon-folder-remove"></i>
                      {{ data.title }}</span
                    > -->
                    <span class="file-name">
                      <img v-if="!data.pid" class="icon" src="@/assets/images/file.jpg">
                      <span class="name line1">{{ data.label }}</span>
                    </span>
                    <span>
                      <el-dropdown @command="(command) => clickMenu(data, command)">
                        <LegacyIcon name="el-icon-more" class="el-icon--right" />
                        <template #dropdown>
                          <el-dropdown-menu>
                            <el-dropdown-item command="1">新增分类</el-dropdown-item>
                            <el-dropdown-item v-if="data.id" command="2">编辑分类</el-dropdown-item>
                            <el-dropdown-item v-if="data.id" command="3">删除</el-dropdown-item>
                          </el-dropdown-menu>
                        </template>
                      </el-dropdown>
                    </span>
                  </span>
                </template>
              </el-tree>
            </div>
          </div>
        </div>
      </div>
      <div class="conter">
        <div class="bnt acea-row row-middle df-jcsb">
          <div class="">
            <el-button
              v-if="isShow !== 0"
              type="success"
              :disabled="checkPicList.length === 0"
              @click="checkPics"
            >使用选中图片</el-button>
            <el-button type="primary" @click="uploadModal">上传图片</el-button>
            <el-button
              class="ai-generate-btn"
              :icon="MagicStick"
              @click="openAiGenerate"
            >AI生成</el-button>
            <el-button
              class="mr10"
              :disabled="!checkPicList.length && !ids.length"
              @click.stop="editPicList()"
            >删除图片</el-button>
            <el-cascader
              v-model="pids"
              placeholder="图片移动至"
              style="width: 150px"
              class="treeSel"
              :options="treeData2"
              :props="{ checkStrictly: true, emitPath: false, label: 'label', value: 'id' }"
              clearable

              @change="handleMoveCategoryChange"
            />
          </div>
          <div v-if="isPage">
            <el-input
              v-model="fileData.real_name"
              class="mr10"
              placeholder="请输入图片名"

              style="width: 150px"
            >
              <template #suffix>
                <LegacyIcon name="el-icon-search" class="el-input__icon" @click="getFileList" />
              </template>
            </el-input>
            <el-radio-group v-model="lietStyle"  @change="radioChange">
              <el-radio-button label="list">
                <LegacyIcon name="el-icon-menu" />
              </el-radio-button>
              <el-radio-button label="table">
                <!-- <i class="el-icon-files"></i> -->
                <span class="iconfont iconliebiao" />
              </el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="pictrueList acea-row" :class="{ 'is-modal': !isPage }">
          <div v-if="lietStyle == 'list'" style="width: 100%">
            <div v-show="isShowPic" class="imagesNo">
              <LegacyIcon name="el-icon-picture" style="color: #dbdbdb; font-size: 60px" />
              <span class="imagesNo_sp">图片库为空</span>
            </div>
            <div ref="imgListBox" class="acea-row mb10">
              <div
                v-for="(item, index) in pictrueList"
                :key="index"
                class="pictrueList_pic mb10 mt10"
                :style="{ margin: picmargin }"
                @mouseenter="enterMouse(item)"
                @mouseleave="enterMouse(item)"
              >
                <p v-if="item.num > 0" class="number">
                  <el-badge :value="item.num" type="primary">
                    <a href="#" class="demo-badge" />
                  </el-badge>
                </p>
                <div class="img" :class="item.isSelect ? 'on' : ''">
                  <img v-lazy="item.path" @click.stop="changImage(item, index, pictrueList)">
                </div>

                <div class="operate-item" @mouseenter="enterLeave(item)" @mouseleave="enterLeave(item)">
                  <p v-if="!item.isEdit">
                    {{ item.editName }}
                  </p>
                  <el-input v-else v-model="item.real_name"  type="text" @blur="bindTxt(item)" />
                  <div class="operate-height">
                    <span
                      v-if="item.isShowEdit"
                      class="operate mr10"
                      @click="editPicList(item.id)"
                    >删除</span>
                    <span
                      v-if="item.isShowEdit"
                      class="operate mr10"
                      @click="item.isEdit = !item.isEdit"
                    >改名</span>
                    <span v-if="item.isShowEdit" class="operate" @click="lookImg(item)">查看</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <el-table
            v-if="lietStyle == 'table'"
            ref="table"
            v-loading="loading"
            :data="pictrueList"
            highlight-row
            :row-key="getRowKey"
            no-data-text="暂无数据"
            no-filtered-data-text="暂无筛选结果"
            @selection-change="handleSelectRow"
          >
            <el-table-column type="selection" width="60" :reserve-selection="true" />
            <el-table-column label="图片名称" min-width="190">
              <template #default="scope">
                <div class="df-aic">
                  <div v-viewer class="tabBox_img mr10">
                    <img v-lazy="scope.row.att_dir">
                  </div>
                  <span v-if="!scope.row.isEdit" class="line2 real-name">{{ scope.row.real_name }}</span>
                  <el-input
                    v-else
                    v-model="scope.row.real_name"

                    type="text"
                    style="width: 90%"
                    @blur="bindTxt(scope.row)"
                  />
                </div>
              </template>
            </el-table-column>
            <el-table-column label="上传时间" min-width="100">
              <template #default="scope">
                <span>{{ scope.row.time }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" fixed="right" width="170">
              <template #default="scope">
                <a @click="editPicList(scope.row)">删除</a>
                <el-divider direction="vertical" />
                <a @click="scope.row.isEdit = !scope.row.isEdit">{{
                  scope.row.isEdit ? '确定' : '重命名'
                }}</a>
                <el-divider direction="vertical" />
                <a @click="lookImg(scope.row)">查看</a>
              </template>
            </el-table-column>
          </el-table>
        </div>
        <div class="footer acea-row row-right">
          <el-pagination
            v-model:current-page="fileData.page"
            :page-size="fileData.limit"
            layout="total, prev, pager, next"
            :total="total"
            @current-change="pageChange"
          />
        </div>
      </div>
    </div>
    <uploadImg
      ref="upload"
      :is-page="isPage"
      :is-iframe="isIframe"
      :category-id="treeId"
      :category-list="treeData"
      @uploadSuccess="uploadSuccess"
    />
    <AiImageGenerateDialog
      v-model:visible="aiGenerateVisible"
      :reference-file="aiReferenceFile"
      :category-id="treeId"
      @generated="handleAiGenerated"
    />
    <div v-show="false" v-viewer="{ movable: false }" class="images">
      <img v-for="src in pictrueList" :key="src.id" :src="src.att_dir">
    </div>
  </div>
</template>

<script setup>
import {
  getFileCategoryListApi,
  getFileCategoryFormApi,
  getFileListApi,
  fileUpdateApi,
  fileMoveCategoryApi
} from '@/api/file'
import Setting from '@/settings';
import { getToken as getAuthToken } from '@/utils/auth'
import { ElMessage } from 'element-plus'
import { MagicStick } from '@element-plus/icons-vue'
import { imageAmplify, modalSure } from '@/utils/index'
import modalForm from '@/utils/modalForm'
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import uploadImg from '@/components/uploadImg'
import AiImageGenerateDialog from './AiImageGenerateDialog.vue'

defineOptions({
  name: 'UploadPictures'
})

const props = defineProps({
  isChoice: {
    type: String,
    default: ''
  },
  isPage: {
    type: Boolean,
    default: false
  },
  isIframe: {
    type: Boolean,
    default: true
  },
  gridBtn: {
    type: Object,
    default: null
  },
  gridPic: {
    type: Object,
    default: null
  },
  isShow: {
    type: Number,
    default: 1
  },
  pageLimit: {
    type: Number,
    default: 0
  },
  maxLength: {
    type: Number,
    default: 1
  }
})

const emit = defineEmits(['getPic', 'getPicD', 'changeCancel'])

let route = null
try {
  route = useRoute()
} catch (e) {
  // iframe 等无路由上下文场景
}

const spinShow = ref(false)
const fileUrl = Setting.baseUri + '/setting/file/upload'
const modalPic = ref(false)
const treeData = ref([])
const treeData2 = ref([])
const pictrueList = ref([])
const uploadData = reactive({})
const checkPicList = ref([])
const uploadName = reactive({
  name: '',
  all: 1
})
const formValidate = reactive({ id: 0 })
const FromData = ref(null)
const treeId = ref('')
const isJudge = ref(false)
const buttonProps = reactive({
  type: 'default',
  size: 'small'
})
const fileData = reactive({
  cate_id: 0,
  real_name: '',
  page: 1,
  limit: props.pageLimit || 18
})
const total = ref(0)
const pids = ref(0)
const list = ref([])
const modalTitleSs = ref('')
const isShowPic = ref(false)
const header = reactive({})
const ids = ref([])
const lietStyle = ref('list')
const imageUrl = ref('')
const loading = ref(false)
const multipleSelection = ref([])
const aiGenerateVisible = ref(false)
const aiReferenceFile = ref(null)
const picmargin = ref('5px')

const imgListBox = ref(null)
const table = ref(null)
const upload = ref(null)

onMounted(() => {
  if (props.isPage) {
    const hang = parseInt((document.body.clientHeight - imgListBox.value.clientHeight - 325) / 180)
    const col = parseInt(imgListBox.value.clientWidth / 156)
    fileData.limit = col * hang
    picmargin.value = parseInt(imgListBox.value.clientWidth - col * 146) / (2 * col) + 'px'
  }
  getToken()
  getList()
  getFileList()
})

function radioChange() {
  initData()
}

function lookImg(item) {
  imageAmplify(item.path)
}

function onChangeName() {}

function addSort() {
  append({ id: treeId.value || 0 })
}

function clickMenu(data, name) {
  if (name === '1') {
    append(data)
  } else if (name === '2') {
    editPic(data)
  } else if (name === '3') {
    remove(data, '分类')
  }
}

function uploadSuccess() {
  fileData.page = 1
  initData()
  getFileList()
}

function uploadModal() {
  upload.value && upload.value.openUpload()
}

function openAiGenerate() {
  if (checkPicList.value.length === 1) {
    aiReferenceFile.value = checkPicList.value[0]
  } else {
    aiReferenceFile.value = null
    if (checkPicList.value.length > 1) {
      ElMessage.info('AI参考图仅支持单张，当前将不使用参考图')
    }
  }
  aiGenerateVisible.value = true
}

function handleAiGenerated() {
  fileData.page = 1
  getList()
  getFileList()
}

function enterMouse(item) {
  item.realName = !item.realName
}

function enterLeave(item) {
  item.isShowEdit = !item.isShowEdit
}

function getToken() {
  header['Authorization'] = 'Bearer ' + getAuthToken()
}

function handleMoveCategoryChange() {
  if (pids.value === '' || pids.value === null || pids.value === undefined) {
    return
  }
  getMove()
}

function searchImg() {}

function getMove() {
  if (!ids.value.length) {
    ElMessage.warning('请先选择图片')
    pids.value = 0
    return
  }
  if (pids.value === '' || pids.value === null || pids.value === undefined) {
    return
  }
  const data = {
    pid: pids.value,
    images: ids.value
  }
  fileMoveCategoryApi(data)
    .then(async(res) => {
      ElMessage.success(res.message)
      getFileList()
      pids.value = 0
      checkPicList.value = []
      ids.value = []
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function delImg(id) {
  const idParams = {
    ids: id
  }
  const delfromData = {
    title: '删除选中图片',
    url: '/setting/file/delete',
    method: 'DELETE',
    ids: idParams
  }
  modalSure(delfromData)
    .then((res) => {
      ElMessage.success(res.message)
      getFileList()
      checkPicList.value = []
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function editPicList(id) {
  const idParams = {
    ids: id || ids.value.toString()
  }
  const delfromData = {
    title: '删除选中图片',
    url: '/setting/file/delete',
    method: 'DELETE',
    ids: idParams
  }
  modalSure(delfromData)
    .then((res) => {
      ElMessage.success(res.message)
      getFileList()
      initData()
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function initData() {
  checkPicList.value = []
  ids.value = []
  multipleSelection.value = []
}

function appendBtn(data) {
  treeId.value = data.id
  fileData.page = 1
  getFileList()
}

function append(data) {
  treeId.value = data.id
  getFrom()
}

function remove(data, tit) {
  const delfromData = {
    title: '删除 [ ' + data.label + ' ] ' + '分类',
    url: `/setting/file/category/${data.id}`,
    method: 'DELETE',
    ids: ''
  }
  modalSure(delfromData)
    .then((res) => {
      ElMessage.success(res.message)
      getList()
      checkPicList.value = []
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function editPic(data) {
  modalForm(getFileCategoryFormApi({ id: data.id })).then(() => getList())
}

function changePage() {
  getList('search')
}

function getList(type) {
  const data = {
    label: '全部图片',
    id: '',
    pid: 0
  }
  getFileCategoryListApi(uploadName)
    .then(async(res) => {
      if (type !== 'search') {
        treeData2.value = JSON.parse(JSON.stringify([...res.data]))
      }
      res.data.unshift(data)
      treeData.value = res.data
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function loadData(item, callback) {
  getFileCategoryListApi({
    pid: item.id
  })
    .then(async(res) => {
      const data = res.data
      callback(data)
    })
    .catch((res) => {})
}

function addFlag(treedata) {
  treedata.map((item) => {
    item.flag = false
    item.flag2 = false
    item.children && addFlag(item.children)
  })
}

function add() {
  treeId.value = 0
  getFrom()
}

function getFileList() {
  fileData.cate_id = treeId.value
  getFileListApi(fileData)
    .then(async(res) => {
      res.data.list.forEach((el) => {
        el.isSelect = false
        el.isEdit = false
        el.isShowEdit = false
        el.realName = false
        el.num = 0
        editName(el)
      })
      pictrueList.value = res.data.list

      if (pictrueList.value.length) {
        isShowPic.value = false
      } else {
        isShowPic.value = true
      }
      total.value = res.data.total
      nextTick(() => {
        // 确保dom加载完毕
        // showSelectData()
      })
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function showSelectData() {
  if (multipleSelection.value.length > 0) {
    pictrueList.value.forEach((row) => {
      multipleSelection.value.forEach((item) => {
        if (row.id === item.id) {
          // table.value.toggleRowSelection(item, true)
        }
      })
    })
  }
}

function getRowKey(row) {
  return row.id
}

function unique(arr) {
  const result = arr.reduce((acc, curr) => {
    const x = acc.find((item) => item.id === curr.id)
    if (!x) {
      return acc.concat([curr])
    } else {
      return acc
    }
  }, [])
  return result
}

function handleSelectRow(selection) {
  const arr = unique(selection)
  const uniqueArr = []
  const ids = []
  for (let i = 0; i < arr.length; i++) {
    const item = arr[i]
    if (!ids.includes(item.id)) {
      uniqueArr.push(item)
      ids.push(item.id)
    }
  }
  ids.value = ids
  multipleSelection.value = uniqueArr
}

function pageChange(page) {
  fileData.page = page
  pictrueList.value = []
  getFileList()
  checkPicList.value = []
}

function getFrom() {
  modalForm(getFileCategoryFormApi({ id: 0, pid: treeId.value })).then((res) => {
    getList()
  })
}

function beforeUpload(file) {
  if (!/image\/\w+/.test(file.type)) {
    ElMessage.error('请上传以jpg、jpeg、png等结尾的图片文件')
    return false
  }
  uploadData.pid = treeId.value
  const promise = new Promise((resolve) => {
    nextTick(function() {
      resolve(true)
    })
  })
  return promise
}

function handleSuccess(res, file, fileList) {
  if (res.status === 200) {
    ElMessage.success(res.message)
    fileData.page = 1
    getFileList()
  } else {
    ElMessage.error(res.message)
  }
}

function cancel() {
  emit('changeCancel')
}

function changImage(item, index, row) {
  let activeIndex = 0
  if (!item.isSelect) {
    item.isSelect = true
    checkPicList.value.push(item)
  } else {
    item.isSelect = false
    checkPicList.value.map((el, index) => {
      if (el.id === item.id) {
        activeIndex = index
      }
    })
    checkPicList.value.splice(activeIndex, 1)
  }

  ids.value = []
  checkPicList.value.map((item, i) => {
    ids.value.push(item.id)
  })
  pictrueList.value.map((el, i) => {
    if (el.isSelect) {
      checkPicList.value.filter((el2, j) => {
        if (el.id === el2.id) {
          el.num = j + 1
        }
      })
    } else {
      el.num = 0
    }
  })
}

function checkPics() {
  if (props.isChoice === '单选') {
    if (checkPicList.value.length > 1) return ElMessage.warning('最多只能选一张图片')
    emit('getPic', checkPicList.value[0])
  } else {
    let maxLength = route && route.query ? route.query.maxLength : undefined
    if (maxLength === undefined) {
      maxLength = props.maxLength
    }
    if (checkPicList.value.length > Number(maxLength)) { return ElMessage.warning('最多只能选' + maxLength + '张图片') }
    emit('getPicD', checkPicList.value)
    emit('getPic', checkPicList.value)
  }
}

function editName(item) {
  const it = item.real_name.split('.')
  const it1 = it[1] === undefined ? [] : it[1]
  const len = it[0].length + it1.length
  item.editName = len < 10 ? item.real_name : item.real_name.substr(0, 4) + '...' + item.real_name.substr(-5, 5)
}

function bindTxt(item) {
  if (item.real_name === '') {
    ElMessage.error('请填写内容')
  }
  fileUpdateApi(item.id, {
    real_name: item.real_name
  })
    .then((res) => {
      editName(item)
      item.isEdit = false
      ElMessage.success(res.message)
    })
    .catch((error) => {
      ElMessage.error(error.message)
    })
}
</script>

<style scoped lang="scss">
.nameStyle {
  position: absolute;
  white-space: nowrap;
  z-index: 9;
  background: #eee;
  height: 20px;
  line-height: 20px;
  color: #555;
  border: 1px solid #ebebeb;
  padding: 0 5px;
  left: 56px;
  bottom: -18px;
}

.iconbianji1 {
  font-size: 13px;
}

.selectTreeClass {
  background: #d5e8fc;
}
.tree_tit {
  padding-top: 7px;
}

.Modal .colLeft .conter .bnt .ai-generate-btn {
  color: var(--text-inverse);
  border: 0;
  background: var(--brand-primary);
  box-shadow: 0 6px 14px var(--brand-shadow);

  &:hover,
  &:focus {
    color: var(--text-inverse);
    border: 0;
    background: var(--brand-primary-dark);
    box-shadow: 0 8px 18px var(--brand-shadow-hover);
  }

  &:active {
    color: var(--text-inverse);
    background: var(--brand-primary-dark);
  }
}

.treeBox {
  width: 100%;
  height: 100%;
  max-width: 180px;
}
.is-modal .pictrueList_pic {
  width: 100px;
  margin: 10px 5px !important;
  .img {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    background-color: rgb(248, 248, 248);
    padding: 2px;
    img {
      max-width: 96px;
      max-height: 96px;
      // object-fit: cover;
    }
    .operate-height {
      bottom: -8px;
    }
  }
}
.pictrueList_pic {
  position: relative;
  width: 146px;
  cursor: pointer;
  // margin-right: 20px !important;
  .img {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 146px;
    height: 146px;
    background-color: rgb(248, 248, 248);
    padding: 3px;
    img {
      max-width: 140px;
      max-height: 140px;
      // object-fit: cover;
    }
  }

  p {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    height: 20px;
    font-size: 12px;
    color: #515a6d;
    text-align: center;
  }

  .number {
    height: 33px;
  }

  .number {
    position: absolute;
    right: 0;
    top: 0;
  }
  :deep(.el-badge__content.is-fixed) {
    top: 13px;
    right: 25px;
  }
}
.Nav {
  width: 100%;
  border-right: 1px solid #eee;
  min-width: 220px;
  max-width: max-content;
}
.trees-coadd {
  width: 100%;
  border-radius: 4px;
  overflow: hidden;
  position: relative;

  .scollhide {
    overflow-x: hidden;
    overflow-y: scroll;
    padding: 0px 0 10px 0;
    box-sizing: border-box;

    .isTree {
      min-height: 374px;
      max-height: 550px;
      :deep(.file-name) {
        display: flex;
        align-items: center;
        .name {
          max-width: 7em;
        }
        .icon {
          width: 12px;
          height: 12px;
          margin-right: 8px;
        }
      }
      :deep(.el-tree-node) {
        margin-right: 16px;
      }
      :deep(.el-tree-node__children .el-tree-node) {
        margin-right: 0;
      }
      :deep(.el-tree-node__content) {
        width: 100%;
        height: 36px;
      }
      :deep(.custom-tree-node) {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-right: 20px;
        font-size: 13px;
        font-weight: 400;
        color: rgba(0, 0, 0, 0.6);
        line-height: 13px;
      }
      :deep(.is-current) {
        background: #f1f9ff !important;
        color: var(--prev-color-primary) !important;
      }
      :deep(.is-current .custom-tree-node) {
        color: var(--prev-color-primary) !important;
      }
    }
  }

  .scollhide::-webkit-scrollbar {
    display: none;
  }
}

.treeSel :deep(.ivu-select-dropdown-list) {
  padding: 0 5px !important;
  box-sizing: border-box;
  width: 200px;
}
.imagesNo {
  display: flex;
  justify-content: center;
  flex-direction: column;
  align-items: center;
  margin: 65px 0;

  .imagesNo_sp {
    font-size: 13px;
    color: #dbdbdb;
    line-height: 3;
  }
}

.Modal {
  width: 100%;
  height: 100%;
  background: #fff !important;
}
.fill-window {
  height: 100vh;
}
.colLeft {
  padding-right: 0 !important;
  height: 100%;
  display: flex;
  flex-wrap: nowrap;
}

.conter {
  width: 100%;
  height: 100%;
  margin-left: 20px !important;
  .iconliebiao {
    font-size: 12px;
  }
}

.conter .bnt {
  width: 100%;
  padding: 0 13px 20px 0px;
  box-sizing: border-box;
}

.conter .pictrueList {
  // width: 100%;
  overflow-x: hidden;
  overflow-y: auto;
  min-height: 463px;
}
.conter .pictrueList.is-modal {
  max-height: 480px;
}
.right-col {
  // flex: 1;
}
.conter .pictrueList img {
  max-width: 100%;
}
.conter .pictrueList .img.on {
  border: 2px solid var(--prev-color-primary);
}

.conter .footer {
  padding: 0 20px 10px 20px;
}
.tabBox_img {
  display: flex;
  align-items: center;
}
.real-name {
  flex: 1;
}
.df-aic {
  display: flex;
  align-items: center;
}
.demo-badge {
  width: 42px;
  height: 42px;
  background: transparent;
  border-radius: 6px;
  display: inline-block;
}

.bnt :deep(.ivu-tree-children) {
  padding: 5px 0;
}

.card-tree {
  background: #fff;
  height: 72px;
  box-sizing: border-box;
  overflow-x: scroll; /* 设置溢出滚动 */
  white-space: nowrap;
  overflow-y: hidden;
  /* 隐藏滚动条 */
  border-radius: 4px;
  scrollbar-width: none; /* firefox */
  -ms-overflow-style: none; /* IE 10+ */
}
.card-tree::-webkit-scrollbar {
  display: none; /* Chrome Safari */
}
.tabs {
  background: #fff;
  padding-top: 10px;
  border-radius: 5px 5px 0 0;
}
.operate-item {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  margin: 5px 0;
}
.operate-height {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 16px;
  position: absolute;
  bottom: -10px;
}
.operate {
  color: var(--prev-color-primary);
  font-size: 12px;
  white-space: nowrap;
}

.box{
  .pagination-container{
    padding: 0;
    margin-top: 0;
  }
}
</style>
