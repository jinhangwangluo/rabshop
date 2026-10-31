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
                    <LegacyIcon name="el-icon-folder-remove" class="icon" />
                    {{ data.title }}</span
                  > -->
                    <span class="file-name">
                      <img v-if="!data.pid" class="icon" src="@/assets/images/file.jpg">
                      <span class="name line1">{{ data.label }}</span>
                    </span>
                    <span>
                      <el-dropdown @command="(command) => clickMenu(data, command)">
                        <LegacyIcon name="el-icon-more" class=" el-icon--right" />
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
        <el-tabs
          v-if="shouldShowTabs"
          v-model="activeType"
          class="type-tabs"
          @tab-click="handleTypeTabClick"
        >
          <el-tab-pane
            v-for="item in typeOptions"
            :key="item.value"
            :label="item.label"
            :name="item.value"
          />
        </el-tabs>
        <div class="bnt acea-row row-middle df-jcsb">
          <div class="">
            <el-button
              v-if="canSelectAttachment"
              type="success"
              :disabled="checkPicList.length === 0"
              @click="checkPics"
            >{{ useSelectedText }}</el-button>
            <el-button type="primary" class="upload-primary-btn" @click="uploadModal">{{ uploadButtonText }}</el-button>
            <el-button
              v-if="isImageMode"
              class="ai-generate-btn"
              icon="el-icon-magic-stick"
              @click="openAiGenerate"
            >AI生成</el-button>
            <el-button
              v-if="canSelectAttachment"
              class="mr10"
              :disabled="!checkPicList.length && !ids.length"
              @click.stop="editPicList()"
            >{{ deleteButtonText }}</el-button>
            <el-cascader
              v-if="canSelectAttachment"
              v-model="pids"
              :placeholder="movePlaceholder"
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
              v-model="fileData.name"
              class="mr10"
              :placeholder="searchPlaceholder"
              style="width: 150px"
            >
              <template #suffix>
                <LegacyIcon name="el-icon-search" class=" el-input__icon" @click="getFileList" />
              </template>
            </el-input>
            <el-radio-group
              v-model="lietStyle"
              class="view-switcher"
              @change="radioChange"
            >
              <el-radio-button label="list">
                <LegacyIcon name="el-icon-menu" />
              </el-radio-button>
              <el-radio-button label="table">
                <LegacyIcon name="el-icon-tickets" />
              </el-radio-button>
            </el-radio-group>
          </div>
        </div>
        <div class="pictrueList acea-row" :class="{ 'is-modal': !isPage }">
          <div v-if="lietStyle == 'list'" style="width: 100%">
            <div v-show="isShowPic" class="imagesNo">
              <LegacyIcon :name="emptyIcon" style="color: #dbdbdb; font-size: 60px" />
              <span class="imagesNo_sp">{{ emptyText }}</span>
            </div>
            <div ref="imgListBox" class="acea-row mb10">
              <div
                v-for="(item, index) in pictrueList"
                :key="item.id || index"
                class="pictrueList_pic mb10 mt10"
                :style="{ margin: picmargin }"
                @mouseenter="enterMouse(item)"
                @mouseleave="enterMouse(item)"
              >
                <p v-if="canSelectAttachment && item.num > 0" class="number">
                  <el-badge :value="item.num" type="primary">
                    <a href="#" class="demo-badge" />
                  </el-badge>
                </p>
                <div
                  class="img"
                  :class="{ on: canSelectAttachment && item.isSelect, 'is-readonly': !canSelectAttachment }"
                  @click.stop="handleAttachmentClick(item, index, pictrueList)"
                >
                  <img v-if="isImageFile(item)" v-lazy="getPreviewPath(item)">
                  <div v-else class="file-preview">
                    <LegacyIcon :name="getFileIcon(item)" />
                    <span>{{ getFileExt(item) }}</span>
                  </div>
                </div>

                <div class="operate-item" @mouseenter="enterLeave(item)" @mouseleave="enterLeave(item)">
                  <p v-if="!item.isEdit">
                    {{ item.editName }}
                  </p>
                  <p v-if="!item.isEdit && !isImageFile(item)" class="file-meta">
                    {{ getFileTypeName(item) }} · {{ getFileTime(item) }}
                  </p>
                  <el-input v-else v-model="item.real_name" type="text" @blur="bindTxt(item)" />
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
            <el-table-column
              v-if="canSelectAttachment"
              type="selection"
              width="60"
              :reserve-selection="true"
            />
            <el-table-column :label="tableNameLabel" min-width="190">
              <template #default="scope">
                <div class="df-aic">
                  <div class="tabBox_img mr10">
                    <img v-if="isImageFile(scope.row)" v-lazy="getPreviewPath(scope.row)">
                    <div v-else class="table-file-icon">
                      <LegacyIcon :name="getFileIcon(scope.row)" />
                    </div>
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
            <el-table-column label="类型" min-width="80">
              <template #default="scope">
                <span>{{ getFileTypeName(scope.row) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="上传时间" min-width="100">
              <template #default="scope">
                <span>{{ getFileTime(scope.row) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" fixed="right" width="170">
              <template #default="scope">
                <a @click="editPicList(scope.row.id)">删除</a>
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
            class="attachment-pagination"
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
      :file-type="activeType"
      @uploadSuccess="uploadSuccess"
    />
    <AiImageGenerateDialog
      v-model:visible="aiGenerateVisible"
      :reference-file="aiReferenceFile"
      :category-id="treeId"
      @generated="handleAiGenerated"
    />
    <el-dialog
      v-model="videoPreviewVisible"
      :append-to-body="true"
      :close-on-click-modal="true"
      :close-on-press-escape="true"
      :destroy-on-close="true"
      :show-close="false"
      custom-class="attachment-video-dialog"
      width="960px"
      top="8vh"
      @close="closeVideoPreview"
    >
      <div class="attachment-video-preview">
        <el-button
          class="attachment-video-preview__close"
          type="text"
          icon="el-icon-close"
          @click="closeVideoPreview"
        />
        <video
          v-if="videoPreviewUrl"
          ref="videoPreviewPlayer"
          class="attachment-video-preview__player"
          :src="videoPreviewUrl"
          controls
          autoplay
          playsinline
        />
      </div>
    </el-dialog>
    <div v-show="false" v-viewer="{ movable: false }" class="images">
      <img v-for="src in imagePreviewList" :key="src.id" :src="getPreviewPath(src)">
    </div>
  </div>
</template>

<script setup>
import {
  computed,
  getCurrentInstance,
  onMounted,
  reactive,
  ref,
  watch
} from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  deleteFileApi,
  fileMoveCategoryApi,
  fileUpdateApi,
  getFileCategoryFormApi,
  getFileCategoryListApi,
  getFileListApi
} from '@/api/file'
import uploadImg from '@/components/uploadImg'
import AiImageGenerateDialog from './AiImageGenerateDialog.vue'

defineOptions({
  name: 'UploadPictures'
})

const FILE_TYPE_OPTIONS = [
  { value: 'image', label: '图片', fileType: 0, icon: 'el-icon-picture' },
  { value: 'video', label: '视频', fileType: 2, icon: 'el-icon-video-camera' },
  { value: 'audio', label: '音频', fileType: 1, icon: 'el-icon-headset' },
  { value: 'other', label: '其他文件', fileType: 3, icon: 'el-icon-document' }
]

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
  },
  allowedTypes: {
    type: Array,
    default: () => ['image']
  },
  showTypeTabs: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['changeCancel', 'getPic', 'getPicD'])
const route = useRoute()
const { proxy } = getCurrentInstance() || {}
const uploadRef = ref(null)
const imgListBox = ref(null)
const treeData = ref([])
const treeData2 = ref([])
const pictrueList = ref([])
const checkPicList = ref([])
const treeId = ref('')
const fileData = reactive({
  cate_id: 0,
  name: '',
  file_type: 0,
  page: 1,
  limit: props.pageLimit || 18
})
const total = ref(0)
const pids = ref(0)
const isShowPic = ref(false)
const ids = ref([])
const lietStyle = ref('list')
const loading = ref(false)
const multipleSelection = ref([])
const aiGenerateVisible = ref(false)
const aiReferenceFile = ref(null)
const videoPreviewVisible = ref(false)
const videoPreviewUrl = ref('')
const picmargin = ref('5px')
const activeType = ref('image')

const normalizedAllowedTypes = computed(() => {
  const values = props.allowedTypes && props.allowedTypes.length ? props.allowedTypes : ['image']
  const allowed = values.filter((type) => FILE_TYPE_OPTIONS.some((item) => item.value === type))
  return allowed.length ? allowed : ['image']
})

const typeOptions = computed(() => {
  return FILE_TYPE_OPTIONS.filter((item) => normalizedAllowedTypes.value.includes(item.value))
})

const currentTypeOption = computed(() => {
  return typeOptions.value.find((item) => item.value === activeType.value) || typeOptions.value[0] || FILE_TYPE_OPTIONS[0]
})

const currentTypeName = computed(() => {
  return currentTypeOption.value.label
})

const currentFileType = computed(() => {
  return currentTypeOption.value.fileType
})

const shouldShowTabs = computed(() => {
  return props.showTypeTabs && typeOptions.value.length > 1
})

const canSelectAttachment = computed(() => {
  return props.isShow !== 0
})

const isImageMode = computed(() => {
  return currentTypeOption.value.value === 'image'
})

const useSelectedText = computed(() => {
  return isImageMode.value ? '使用选中图片' : '使用选中附件'
})

const uploadButtonText = computed(() => {
  return '上传' + currentTypeName.value
})

const deleteButtonText = computed(() => {
  return '删除' + currentTypeName.value
})

const movePlaceholder = computed(() => {
  return currentTypeName.value + '移动至'
})

const searchPlaceholder = computed(() => {
  return '请输入' + currentTypeName.value + '名'
})

const emptyText = computed(() => {
  return currentTypeName.value + '库为空'
})

const emptyIcon = computed(() => {
  return currentTypeOption.value.icon
})

const tableNameLabel = computed(() => {
  return currentTypeName.value + '名称'
})

const imagePreviewList = computed(() => {
  return pictrueList.value.filter((item) => isImageFile(item))
})

watch(normalizedAllowedTypes, () => {
  if (!normalizedAllowedTypes.value.includes(activeType.value)) {
    activeType.value = normalizedAllowedTypes.value[0]
  }
  fileData.file_type = currentFileType.value
}, { immediate: true })

onMounted(() => {
  if (props.isPage && imgListBox.value) {
    const hang = parseInt((document.body.clientHeight - imgListBox.value.clientHeight - 325) / 180)
    const col = parseInt(imgListBox.value.clientWidth / 156)
    fileData.limit = col * hang
    picmargin.value = parseInt(imgListBox.value.clientWidth - col * 146) / (2 * col) + 'px'
  }
  getList()
  getFileList()
})

function handleTypeTabClick() {
  fileData.page = 1
  treeId.value = ''
  initData()
  getList()
  getFileList()
}

function radioChange() {
  initData()
}

function lookImg(item) {
  const path = getPreviewPath(item)
  if (isImageFile(item)) {
    if (proxy && proxy.$imageAmplify) {
      proxy.$imageAmplify(path)
    }
    return
  }
  if (isVideoFile(item)) {
    openVideoPreview(path)
    return
  }
  if (path) {
    window.open(path, '_blank')
  }
}

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
  if (uploadRef.value) {
    uploadRef.value.uploadModal = true
  }
}

function openAiGenerate() {
  if (!isImageMode.value) {
    ElMessage.warning('AI生成仅支持图片')
    return
  }
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

function openVideoPreview(path) {
  if (!path) {
    ElMessage.warning('视频地址不存在')
    return
  }
  videoPreviewUrl.value = path
  videoPreviewVisible.value = true
}

function closeVideoPreview() {
  videoPreviewVisible.value = false
  videoPreviewUrl.value = ''
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

function handleMoveCategoryChange() {
  if (pids.value === '' || pids.value === null || pids.value === undefined) {
    return
  }
  getMove()
}

function getMove() {
  if (!ids.value.length) {
    ElMessage.warning('请先选择' + currentTypeName.value)
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
    .then((res) => {
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

function editPicList(id) {
  const deleteIds = resolveDeleteIds(id)
  if (!deleteIds.length) {
    ElMessage.warning('请先选择' + currentTypeName.value)
    return
  }
  ElMessageBox.confirm('确定要删除选中' + currentTypeName.value + '吗？', '提示', {
    type: 'warning',
    confirmButtonText: '确定',
    cancelButtonText: '取消'
  })
    .then(() => deleteFileApi(deleteIds))
    .then((res) => {
      ElMessage.success(res.message)
      getFileList()
      initData()
    })
    .catch((res) => {
      if (res && res.message) {
        ElMessage.error(res.message)
      }
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

function remove(data) {
  if (!proxy || !proxy.$modalSure) {
    return
  }
  const delfromData = {
    title: '删除 [ ' + data.label + ' ] ' + '分类',
    url: `/setting/file/category/${data.id}`,
    method: 'DELETE',
    ids: ''
  }
  proxy.$modalSure(delfromData)
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
  if (!proxy || !proxy.$modalForm) {
    return
  }
  proxy.$modalForm(getFileCategoryFormApi({ id: data.id })).then(() => getList())
}

function getList(type) {
  const data = {
    label: '全部' + currentTypeName.value,
    id: '',
    pid: 0
  }
  getFileCategoryListApi({ name: '', all: 1 })
    .then((res) => {
      if (type !== 'search') {
        treeData2.value = JSON.parse(JSON.stringify([...(res.data || [])]))
      }
      const list = res.data || []
      list.unshift(data)
      treeData.value = list
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
}

function getFileList() {
  fileData.cate_id = treeId.value
  fileData.file_type = currentFileType.value
  getFileListApi(fileData)
    .then((res) => {
      const list = res.data.list || []
      list.forEach((el) => {
        el.isSelect = false
        el.isEdit = false
        el.isShowEdit = false
        el.realName = false
        el.num = 0
        editName(el)
      })
      pictrueList.value = list
      isShowPic.value = !pictrueList.value.length
      total.value = res.data.total
    })
    .catch((res) => {
      ElMessage.error(res.message)
    })
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
  if (!canSelectAttachment.value) {
    return
  }
  const arr = unique(selection)
  const uniqueArr = []
  const nextIds = []
  for (let i = 0; i < arr.length; i++) {
    const item = arr[i]
    if (!nextIds.includes(item.id)) {
      uniqueArr.push(item)
      nextIds.push(item.id)
    }
  }
  ids.value = nextIds
  multipleSelection.value = uniqueArr
}

function pageChange(page) {
  fileData.page = page
  pictrueList.value = []
  getFileList()
  checkPicList.value = []
}

function getFrom() {
  if (!proxy || !proxy.$modalForm) {
    return
  }
  proxy.$modalForm(getFileCategoryFormApi({ pid: treeId.value })).then(() => {
    getList()
  })
}

function handleAttachmentClick(item) {
  if (!canSelectAttachment.value) {
    return
  }
  changImage(item)
}

function changImage(item) {
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
  checkPicList.value.map((item) => {
    ids.value.push(item.id)
  })
  pictrueList.value.map((el) => {
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
    if (checkPicList.value.length > 1) {
      ElMessage.warning('最多只能选一个' + currentTypeName.value)
      return
    }
    emit('getPic', checkPicList.value[0])
  } else {
    let maxLength = route ? route.query.maxLength : undefined
    if (maxLength === undefined) {
      maxLength = props.maxLength
    }
    if (checkPicList.value.length > Number(maxLength)) {
      ElMessage.warning('最多只能选' + maxLength + '个' + currentTypeName.value)
      return
    }
    emit('getPicD', checkPicList.value)
    emit('getPic', checkPicList.value)
  }
}

function resolveDeleteIds(id) {
  if (id && typeof id === 'object') {
    return id.id ? [id.id] : []
  }
  if (id) {
    return [id]
  }
  return ids.value.slice()
}

function isImageFile(item) {
  return Number(item.file_type || 0) === 0
}

function isVideoFile(item) {
  return Number(item.file_type || 0) === 2
}

function getPreviewPath(item) {
  return item.path || item.filePath || item.file_url || item.att_dir || ''
}

function getFileExt(item) {
  const name = item.real_name || item.name || getPreviewPath(item)
  const ext = String(name).split('.').pop() || ''
  return ext ? ext.toUpperCase() : currentTypeName.value
}

function getFileIcon(item) {
  const fileType = Number(item.file_type)
  if (fileType === 1) return 'el-icon-headset'
  if (fileType === 2) return 'el-icon-video-camera'
  return 'el-icon-document'
}

function getFileTypeName(item) {
  return item.file_type_name || currentTypeName.value
}

function getFileTime(item) {
  return item.time || item.create_time || item.update_time || '-'
}

function editName(item) {
  const realName = item.real_name || item.name || ''
  const it = realName.split('.')
  const it1 = it[1] === undefined ? [] : it[1]
  const len = it[0].length + it1.length
  item.editName = len < 10 ? realName : realName.substr(0, 4) + '...' + realName.substr(-5, 5)
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
  background: var(--dashboard-surface-muted);
  height: 20px;
  line-height: 20px;
  color: var(--dashboard-text-secondary);
  border: 1px solid var(--dashboard-border);
  padding: 0 5px;
  left: 56px;
  bottom: -18px;
}

.iconbianji1 {
  font-size: 13px;
}

.selectTreeClass {
  background: var(--dashboard-accent-outline);
}
.tree_tit {
  padding-top: 7px;
}

.type-tabs {
  margin: 0 13px 14px 0;

  :deep(.el-tabs__header) {
    margin: 0;
  }
}

.Modal .colLeft .conter .bnt {
  .upload-primary-btn.el-button--primary {
    color: var(--ds-on-primary, var(--dashboard-on-accent, #fff)) !important;
    border-color: var(--ds-primary, var(--dashboard-accent)) !important;
    background: var(--ds-primary, var(--dashboard-accent)) !important;

    &:hover,
    &:focus {
      color: var(--ds-on-primary, var(--dashboard-on-accent, #fff)) !important;
      border-color: var(--ds-primary-hover, var(--dashboard-accent-hover)) !important;
      background: var(--ds-primary-hover, var(--dashboard-accent-hover)) !important;
    }
  }

  .ai-generate-btn {
    color: var(--ds-on-primary, var(--dashboard-on-accent, #fff));
    border: 0;
    background: linear-gradient(135deg, var(--ds-primary-container, var(--dashboard-accent-light)) 0%, var(--ds-primary, var(--dashboard-accent)) 100%);
    box-shadow: 0 6px 14px var(--dashboard-accent-shadow);

    &:hover,
    &:focus {
      color: var(--ds-on-primary, var(--dashboard-on-accent, #fff));
      border: 0;
      background: linear-gradient(135deg, var(--ds-primary-container, var(--dashboard-accent-light)) 0%, var(--ds-primary-hover, var(--dashboard-accent-hover)) 100%);
      box-shadow: 0 8px 18px var(--dashboard-accent-shadow-hover);
    }

    &:active {
      color: var(--ds-on-primary, var(--dashboard-on-accent, #fff));
      background: var(--ds-primary-hover, var(--dashboard-accent-hover));
    }
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
    background-color: var(--dashboard-surface-muted);
    padding: 2px;
    img {
      max-width: 96px;
      max-height: 96px;
      // object-fit: cover;
    }
    .file-preview {
      width: 96px;
      height: 96px;
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
    background-color: var(--dashboard-surface-muted);
    padding: 3px;
    img {
      max-width: 140px;
      max-height: 140px;
      // object-fit: cover;
    }
    .file-preview {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: 140px;
      height: 140px;
      color: var(--dashboard-text-secondary);

      i {
        font-size: 42px;
        color: var(--prev-color-primary);
      }

      span {
        max-width: 110px;
        margin-top: 10px;
        overflow: hidden;
        color: var(--dashboard-text-muted);
        font-size: 12px;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }
  }

  .img.is-readonly {
    cursor: default;
  }

  p {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    height: 20px;
    font-size: 12px;
    color: var(--dashboard-text-secondary);
    text-align: center;
  }

  .file-meta {
    width: 140px;
    height: 18px;
    margin-top: -2px;
    color: var(--dashboard-text-muted);
    font-size: 11px;
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
  border-right: 1px solid var(--dashboard-border);
  min-width: 220px;
  max-width: max-content;
  background: var(--dashboard-surface);
}
.trees-coadd {
  width: 100%;
  border-radius: 4px;
  overflow: hidden;
  position: relative;
  background: var(--dashboard-surface);

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
        color: var(--dashboard-text-secondary);
        background: var(--dashboard-surface);

        &:hover {
          background: var(--dashboard-surface-muted);
        }
      }
      :deep(.custom-tree-node) {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-right: 20px;
        font-size: 13px;
        font-weight: 400;
        color: var(--dashboard-text-secondary);
        line-height: 13px;
      }
      :deep(.el-tree) {
        color: var(--dashboard-text-secondary);
        background: var(--dashboard-surface);
      }
      :deep(.el-tree-node:focus > .el-tree-node__content) {
        background: var(--dashboard-surface-muted);
      }
      :deep(.is-current) {
        background: var(--dashboard-accent-outline) !important;
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
    color: var(--dashboard-text-muted);
    line-height: 3;
  }
}

.Modal {
  width: 100%;
  height: 100%;
  background: var(--dashboard-surface) !important;
  color: var(--dashboard-text-primary);

  :deep(.el-button--default),
  :deep(.el-button.is-disabled),
  :deep(.el-button.is-disabled:hover),
  :deep(.el-button.is-disabled:focus) {
    color: var(--dashboard-text-muted);
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }

  :deep(.el-radio-button__inner) {
    color: var(--dashboard-text-secondary);
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }

  :deep(.el-radio-button__orig-radio:checked + .el-radio-button__inner) {
    color: var(--ds-on-primary, var(--dashboard-on-accent)) !important;
    background: var(--ds-primary, var(--dashboard-accent)) !important;
    border-color: var(--ds-primary, var(--dashboard-accent)) !important;
  }

  :deep(.el-cascader .el-input__inner),
  :deep(.el-input__inner) {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }

  :deep(.attachment-pagination),
  :deep(.attachment-pagination .el-pagination__total) {
    color: var(--dashboard-text-secondary);
    background: transparent;
  }

  :deep(.attachment-pagination button),
  :deep(.attachment-pagination .el-pager li) {
    min-width: 32px;
    height: 32px;
    margin: 0 3px;
    padding: 0;
    border-radius: 4px;
    color: var(--dashboard-text-secondary);
    background: transparent;
    line-height: 32px;
  }

  :deep(.attachment-pagination button:hover),
  :deep(.attachment-pagination button:focus),
  :deep(.attachment-pagination .el-pager li:hover) {
    color: var(--dashboard-accent);
  }

  :deep(.attachment-pagination .el-pager li.active),
  :deep(.attachment-pagination .el-pager li.active:hover) {
    color: var(--dashboard-on-accent, #fff) !important;
    background: var(--dashboard-accent) !important;
    font-weight: 600;
  }

  :deep(.attachment-video-dialog) {
    overflow: hidden;
    background: #000;

    .el-dialog__header {
      display: none;
    }

    .el-dialog__body {
      position: relative;
      height: 540px;
      padding: 0;
      overflow: hidden;
    }
  }

  :deep(.el-table),
  :deep(.el-table tr),
  :deep(.el-table td),
  :deep(.el-table th) {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border-color: var(--dashboard-border);
  }
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
  background: var(--dashboard-surface);
}

.view-switcher {
  display: inline-flex;
  vertical-align: middle;

  :deep(.el-radio-button__orig-radio:checked + .el-radio-button__inner) {
    color: var(--ds-on-primary, var(--dashboard-on-accent)) !important;
    background: var(--ds-primary, var(--dashboard-accent)) !important;
    border-color: var(--ds-primary, var(--dashboard-accent)) !important;
  }

  :deep(.el-radio-button__inner) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 32px;
    padding: 0;
    line-height: 32px;
    box-sizing: border-box;
  }

  :deep(i) {
    font-size: 16px;
    line-height: 1;
  }
}

.attachment-video-preview {
  position: relative;
  width: 100%;
  height: 100%;
  background: #000;
}

.attachment-video-preview__close {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
  color: #fff;
}

.attachment-video-preview__player {
  display: block;
  width: 100%;
  height: 100%;
  background: #000;
  object-fit: contain;
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
  background: var(--dashboard-surface);
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
  background: var(--dashboard-surface);
}
.tabBox_img {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  overflow: hidden;
  border-radius: 4px;
  background: var(--dashboard-surface-muted);

  img {
    max-width: 44px;
    max-height: 44px;
  }
}
.table-file-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  color: var(--prev-color-primary);
  font-size: 24px;
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
  background: var(--dashboard-surface);
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
  background: var(--dashboard-surface);
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
