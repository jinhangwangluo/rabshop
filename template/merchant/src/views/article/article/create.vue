<template>
  <div class="content-create-container">

    <div class="sticky ivu-mb-16">
      <el-card
        shadow="never"
        class="box-card"
      >
        <div class="acea-row space-between">
          <div class="acea-row row-middle">
            <div
              class="font-sm after-line"
              @click="goBack"
            >
              <LegacyIcon name="el-icon-arrow-left" />
              <span class="pl10">返回</span>
            </div>
            <span class="ht_title ml10">{{ isEdit ? '编辑内容' : '添加内容' }}</span>
          </div>
        </div>
      </el-card>
    </div>

    <el-card shadow="never" class="create-card">
      <el-form
        ref="form"
        v-loading="loading"
        :model="form"
        :rules="rules"
        label-width="120px"
        class="create-form"
      >
        <!-- 基础信息 -->
        <div class="form-section">
          <div class="section-title">基础信息</div>

          <el-form-item label="内容类型" prop="content_type">
            <el-radio-group v-model="form.content_type" :disabled="isEdit" @change="handleTypeChange">
              <el-radio-button
                v-for="item in contentTypes"
                :key="item.value"
                :label="item.value"
              >
                {{ item.label }}
              </el-radio-button>
            </el-radio-group>
            <div v-if="isEdit" class="form-tip">内容类型创建后不可修改</div>
          </el-form-item>

          <el-form-item label="内容标题" prop="title">
            <el-input
              v-model="form.title"
              placeholder="请输入内容标题"
              class="form-width"
              maxlength="100"
              show-word-limit
            />
          </el-form-item>

          <el-form-item label="内容分类" prop="article_category_id">
            <div class="category-select-row">
              <el-cascader
                v-model="form.article_category_id"
                :options="categoryTree"
                :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
                placeholder="请选择分类"
                class="form-width"
                clearable
                filterable
                @change="handleCategoryChange"
              />
              <el-button
                type="text"
                :icon="CirclePlus"
                class="quick-add-btn"
                @click="openQuickAdd"
              >快捷添加
              </el-button>
            </div>
          </el-form-item>

          <el-form-item label="作者" prop="author">
            <el-input v-model="form.author" placeholder="请输入作者名" class="form-width" />
          </el-form-item>

          <el-form-item label="简介" prop="description">
            <el-input
              v-model="form.description"
              type="textarea"
              :rows="3"
              placeholder="请输入内容简介"
              class="form-width"
              maxlength="300"
              show-word-limit
            />
          </el-form-item>

          <el-form-item label="封面图" prop="cover">
            <Upload v-model="form.cover" :limit="1" />
            <div class="form-tip">建议尺寸：300×300px，支持 JPG、PNG 格式</div>
          </el-form-item>
        </div>

        <!-- 价格设置 -->
        <div class="form-section">
          <div class="section-title">价格设置</div>

          <el-form-item label="是否付费">
            <el-radio-group v-model="isPaid" @change="handlePaidChange">
              <el-radio :label="false">免费</el-radio>
              <el-radio :label="true">付费</el-radio>
            </el-radio-group>
          </el-form-item>

          <template v-if="isPaid">
            <el-form-item label="售价" prop="price">
              <el-input-number
                v-model="form.price"
                :min="0"
                :precision="2"
                :step="1"
                placeholder="请输入售价"
              />
              <span class="unit-text">元</span>
            </el-form-item>

            <el-form-item label="原价">
              <el-input-number
                v-model="form.original_price"
                :min="0"
                :precision="2"
                :step="1"
                placeholder="请输入原价（可选）"
              />
              <span class="unit-text">元</span>
            </el-form-item>

            <el-form-item label="支持试读">
              <el-radio-group v-model="form.is_trial">
                <el-radio :label="1">支持试读</el-radio>
                <el-radio :label="0">不支持</el-radio>
              </el-radio-group>
            </el-form-item>
          </template>
        </div>

        <!-- 内容区域 - 根据类型动态展示 -->
        <div class="form-section">
          <div class="section-title">内容详情</div>

          <!-- 图文/文字/小说 - 富文本编辑器 -->
          <el-form-item
            v-if="needsRichContent"
            label="正文内容"
            prop="content"
          >
            <div class="editor-wrap">
              <Toolbar
                style="border-bottom: 1px solid #e8e8e8;"
                :editor="editor"
                :default-config="toolbarConfig"
                :mode="editorMode"
              />
              <Editor
                v-model="form.content"
                style="height: 400px; overflow-y: hidden;"
                :default-config="editorConfig"
                :mode="editorMode"
                @onCreated="onEditorCreated"
              />
            </div>
          </el-form-item>

          <!-- 图文/文字/小说 - 试读富文本编辑器（付费且支持试读时显示） -->
          <el-form-item
            v-if="needsRichContent && isPaid && form.is_trial === 1"
            label="试读内容"
          >
            <div class="form-tip trial-content-tip">
              <LegacyIcon name="el-icon-info" />
              用户付费前可免费阅读的内容，不填则无试读
            </div>
            <div class="editor-wrap">
              <Toolbar
                style="border-bottom: 1px solid #e8e8e8;"
                :editor="trialEditor"
                :default-config="toolbarConfig"
                :mode="editorMode"
              />
              <Editor
                v-model="form.trial_content"
                style="height: 300px; overflow-y: hidden;"
                :default-config="trialEditorConfig"
                :mode="editorMode"
                @onCreated="onTrialEditorCreated"
              />
            </div>
          </el-form-item>

          <!-- 图片类型 - 多图上传 -->
          <el-form-item
            v-if="isImageType"
            label="图片列表"
            prop="images"
          >
            <Upload v-model="form.images" :limit="100" />
            <div class="form-tip">可上传多张图片，最多100张</div>
          </el-form-item>
          <el-form-item
            v-if="isImageType && form.is_trial === 1"
            label="试看图片"
            prop="images"
          >
            <Upload v-model="form.trial_images" :limit="20" />
            <div class="form-tip">可上传多张图片，最多20张</div>
          </el-form-item>

          <!-- 音频类型 -->
          <template v-if="isAudioType">
            <el-form-item label="音频文件" prop="media_url">
              <BigFileUpload
                ref="audioUpload"
                :value="form.media_url"
                :allow-extensions="['mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg']"
                :trial-seconds="form.is_trial === 1 ? trialSeconds : 0"
                accept="audio/*,.mp3,.wav,.aac,.flac,.m4a,.ogg"
                tip="支持 mp3/wav/aac/flac/m4a/ogg 格式，大文件自动分片断点续传"
                class="form-width"
                @input="form.media_url = $event"
                @duration="handleMediaDuration"
                @trial-clipping="handleTrialClipping"
                @trial-uploaded="handleTrialUploaded"
              />
              <!-- 完整音频播放器 -->
              <div v-if="form.media_url" class="media-player-wrap">
                <div class="player-label">完整音频预览</div>
                <audio :src="form.media_url" controls class="media-player" />
              </div>
            </el-form-item>
            <el-form-item label="音频时长(秒)" prop="media_duration">
              <el-input-number
                v-model="form.media_duration"
                :min="0"
                placeholder="请输入时长（秒）"
              />
              <span v-if="form.media_duration" class="unit-text">{{ formatDuration(form.media_duration) }}</span>
            </el-form-item>
            <!-- 试听片段区域 -->
            <el-form-item v-if="form.is_trial === 1" label="试听片段">
              <div class="trial-block">
                <!-- 截取秒数设置 -->
                <div class="trial-seconds-row">
                  <span class="trial-label">截取前</span>
                  <el-input-number
                    v-model="trialSeconds"
                    :min="5"
                    :max="600"
                    :step="5"

                    class="trial-num"
                  />
                  <span class="trial-label">秒作为试听片段</span>
                  <el-button
                    type="primary"
                    plain
                    :icon="Scissors"
                    :loading="trialClipping"
                    :disabled="!form.media_url"
                    @click="doReClip('audioUpload')"
                  >立即截取
                  </el-button>
                </div>
                <!-- 试听播放器 -->
                <div v-if="form.trial_media_url" class="trial-player-wrap">
                  <div class="trial-player-header">
                    <el-tag type="success" >试听片段已设置</el-tag>
                    <el-button
                      type="text"
                      size="mini"
                      :icon="Delete"
                      class="ml-8"
                      @click="form.trial_media_url = ''"
                    >清除
                    </el-button>
                  </div>
                  <audio :src="form.trial_media_url" controls class="media-player" />
                </div>
                <div v-else class="trial-empty">
                  <LegacyIcon name="el-icon-info" />
                  <span>请先上传音频文件，再点击“立即截取”生成试听片段</span>
                </div>
              </div>
            </el-form-item>
          </template>

          <!-- 视频类型 -->
          <template v-if="isVideoType">
            <el-form-item label="视频文件" prop="media_url">
              <BigFileUpload
                ref="videoUpload"
                :value="form.media_url"
                :allow-extensions="['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']"
                :trial-seconds="form.is_trial === 1 ? trialSeconds : 0"
                accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv"
                tip="支持 mp4/avi/mov/wmv/flv/mkv 格式，大文件自动分片断点续传"
                class="form-width"
                @input="form.media_url = $event"
                @duration="handleMediaDuration"
                @trial-clipping="handleTrialClipping"
                @trial-uploaded="handleTrialUploaded"
              />
              <!-- 完整视频播放器 -->
              <div v-if="form.media_url" class="media-player-wrap">
                <div class="player-label">完整视频预览</div>
                <video :src="form.media_url" controls class="media-player video-player" />
              </div>
            </el-form-item>
            <el-form-item label="视频时长(秒)" prop="media_duration">
              <el-input-number
                v-model="form.media_duration"
                :min="0"
                placeholder="请输入时长（秒）"
              />
              <span v-if="form.media_duration" class="unit-text">{{ formatDuration(form.media_duration) }}</span>
            </el-form-item>
            <!-- 试看片段区域 -->
            <el-form-item v-if="form.is_trial === 1" label="试看片段">
              <div class="trial-block">
                <!-- 截取秒数设置 -->
                <div class="trial-seconds-row">
                  <span class="trial-label">截取前</span>
                  <el-input-number
                    v-model="trialSeconds"
                    :min="5"
                    :max="600"
                    :step="5"

                    class="trial-num"
                  />
                  <span class="trial-label">秒作为试看片段</span>
                  <el-button
                    type="primary"
                    plain
                    :icon="Scissors"
                    :loading="trialClipping"
                    :disabled="!form.media_url"
                    @click="doReClip('videoUpload')"
                  >立即截取
                  </el-button>
                </div>
                <!-- 试看播放器 -->
                <div v-if="form.trial_media_url" class="trial-player-wrap">
                  <div class="trial-player-header">
                    <el-tag type="success" >试看片段已设置</el-tag>
                    <el-button
                      type="text"
                      size="mini"
                      :icon="Delete"
                      class="ml-8"
                      @click="form.trial_media_url = ''"
                    >清除
                    </el-button>
                  </div>
                  <video :src="form.trial_media_url" controls class="media-player video-player" />
                </div>
                <div v-else class="trial-empty">
                  <LegacyIcon name="el-icon-info" />
                  <span>请先上传视频文件，再点击“立即截取”生成试看片段</span>
                </div>
              </div>
            </el-form-item>
          </template>

          <!-- 小说专属字段 -->
          <el-form-item v-if="isNovelType" label="字数">
            <el-input-number v-model="form.word_count" :min="0" placeholder="字数统计" />
            <span class="unit-text">字</span>
          </el-form-item>
        </div>

        <!-- 其他设置 -->
        <div class="form-section">
          <div class="section-title">其他设置</div>

          <el-row :gutter="24">
            <el-col :span="8">
              <el-form-item label="排序" prop="sort">
                <el-input-number v-model="form.sort" :min="0" placeholder="数值越大越靠前" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="是否热门">
                <el-switch v-model="form.is_hot" :active-value="1" :inactive-value="0" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="是否轮播">
                <el-switch v-model="form.is_banner" :active-value="1" :inactive-value="0" />
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="虚拟学习人数">
            <el-input-number v-model="form.virtual_learn_count" :min="0" :precision="0" placeholder="前台展示时会叠加真实学习人数" />
            <span class="unit-text">人</span>
          </el-form-item>

          <el-form-item label="显示状态">
            <el-radio-group v-model="form.status">
              <el-radio :label="1">显示</el-radio>
              <el-radio :label="0">隐藏</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="关键词">
            <el-input
              v-model="form.keywords"
              placeholder="请输入关键词，多个用逗号分隔"
              class="form-width"
            />
          </el-form-item>
        </div>

        <!-- 提交按钮 -->
        <div class="form-footer">
          <el-button type="primary" :loading="submitLoading" @click="submitForm">
            {{ isEdit ? '保存修改' : '立即发布' }}
          </el-button>
          <el-button @click="goBack">取消</el-button>
        </div>
      </el-form>
    </el-card>

    <!-- 快捷添加分类弹窗 -->
    <el-dialog
      v-model="quickAddVisible"
      title="快捷添加分类"
      width="420px"
      destroy-on-close
    >
      <el-form label-width="90px" class="quick-add-form">
        <el-form-item label="上级分类">
          <el-select
            v-model="quickAddForm.pid"
            placeholder="不选则为一级分类"
            clearable
            style="width: 100%;"
          >
            <el-option label="无（一级分类）" :value="0" />
            <el-option
              v-for="item in quickAddParentList"
              :key="item.id || item.value"
              :label="item.label"
              :value="item.id || item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="分类名称">
          <el-input
            v-model="quickAddForm.name"
            placeholder="请输入分类名称"
            maxlength="30"
            show-word-limit
            @keyup.enter="submitQuickAdd"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="quickAddVisible = false">取 消</el-button>
          <el-button type="primary" :loading="quickAddLoading" @click="submitQuickAdd">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CirclePlus, Delete, Scissors } from '@element-plus/icons-vue'
import {
  articleAdd,
  articleRead,
  articleSave,
  getContentTypes,
  getArticleCategoryTree,
  articleCategoryQuickAdd
} from '@/api/article'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'
import Upload from '@/components/Upload/index'
import BigFileUpload from '@/components/BigFileUpload/index'

defineOptions({
  name: 'ContentCreate'
})

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance() || {}

// 内容类型常量
const TYPE_GRAPHIC = 1 // 图文
const TYPE_TEXT = 2 // 文字
const TYPE_NOVEL = 3 // 小说
const TYPE_IMAGE = 4 // 图片
const TYPE_AUDIO = 5 // 音频
const TYPE_VIDEO = 6 // 视频

const formRef = ref(null)
const loading = ref(false)
const submitLoading = ref(false)
// 分类树（级联选择器使用）
const categoryTree = ref([])
// 分类 id->item 映射（用于回显时找父级）
const categoryMap = ref({})
const contentTypes = ref([])
const isPaid = ref(false)
// 试听/试看截取秒数（默认30秒）
const trialSeconds = ref(30)
// 试听截取中状态
const trialClipping = ref(false)
// 快捷添加分类弹窗
const quickAddVisible = ref(false)
const quickAddLoading = ref(false)
const quickAddForm = reactive({
  name: '',
  pid: 0
})
const quickAddParentList = ref([])
// 富文本编辑器
const editor = ref(null)
const toolbarConfig = {}
const editorConfig = { placeholder: '请输入内容...' }
// 试读富文本编辑器
const trialEditor = ref(null)
const trialEditorConfig = { placeholder: '请输入试读内容（选填）...' }
const editorMode = 'default'
const form = reactive({
  title: '',
  article_category_id: '',
  article_category_path: [],
  author: '',
  description: '',
  cover: '',
  content_type: TYPE_GRAPHIC,
  // 图文/文字/小说
  content: '',
  trial_content: '',
  // 图片
  images: [],
  trial_images: [],
  // 音频/视频
  media_url: '',
  media_duration: 0,
  // 试听/试看片段URL
  trial_media_url: '',
  // 小说
  word_count: 0,
  virtual_learn_count: 0,
  // 价格
  price: 0,
  original_price: 0,
  is_trial: 0,
  // 其他
  sort: 0,
  is_hot: 0,
  is_banner: 0,
  status: 1,
  keywords: '',
  source: ''
})
const rules = {
  title: [
    { required: true, message: '请输入内容标题', trigger: 'blur' },
    { max: 100, message: '标题最多100个字符', trigger: 'blur' }
  ],
  content_type: [
    { required: true, message: '请选择内容类型', trigger: 'change' }
  ],
  cover: [
    { required: true, message: '请上传封面图', trigger: 'change' }
  ],
  price: [
    {
      validator: (rule, val, cb) => {
        if (isPaid.value && (!val || val <= 0)) {
          cb(new Error('付费内容请设置大于0的价格'))
        } else {
          cb()
        }
      },
      trigger: 'blur'
    }
  ]
}

const isEdit = computed(() => {
  return !!route.query.id
})
// 是否需要富文本内容（图文/文字/小说）
const needsRichContent = computed(() => {
  return [TYPE_GRAPHIC, TYPE_TEXT, TYPE_NOVEL].includes(form.content_type)
})
const isImageType = computed(() => {
  return form.content_type === TYPE_IMAGE
})
const isAudioType = computed(() => {
  return form.content_type === TYPE_AUDIO
})
const isVideoType = computed(() => {
  return form.content_type === TYPE_VIDEO
})
const isNovelType = computed(() => {
  return form.content_type === TYPE_NOVEL
})

function onEditorCreated(editorInstance) {
  editor.value = Object.seal(editorInstance)
}

function onTrialEditorCreated(editorInstance) {
  trialEditor.value = Object.seal(editorInstance)
}

// 初始化数据
async function initData() {
  await Promise.all([
    loadContentTypes(),
    loadCategoryList()
  ])
  if (isEdit.value) {
    getDetail(route.query.id)
  } else if (route.query.cid) {
    const cid = Number(route.query.cid)
    form.article_category_id = cid
    // 如果有父级，设置级联路径
    const item = categoryMap.value[cid]
    if (item && item.pid) {
      form.article_category_path = [item.pid, cid]
    } else {
      form.article_category_path = [cid]
    }
  }
}

// 加载内容类型
function loadContentTypes() {
  return getContentTypes().then(res => {
    contentTypes.value = res.data.list
  })
}

// 加载分类树
function loadCategoryList() {
  return getArticleCategoryTree().then(res => {
    const tree = res.data || []
    categoryTree.value = tree
    // 一级分类列表（快捷添加时使用）
    quickAddParentList.value = tree
  })
}

// 获取详情
function getDetail(id) {
  loading.value = true
  articleRead(id).then(res => {
    const data = res.data
    const catId = data.article_category_id ? Number(data.article_category_id) : ''
    // 构建级联路径
    let catPath = []
    if (catId) {
      const catItem = categoryMap.value[catId]
      if (catItem && catItem.pid) {
        catPath = [catItem.pid, catId]
      } else if (catId) {
        catPath = [catId]
      }
    }
    console.log('catPath', catPath)
    Object.assign(form, {
      ...form,
      ...data,
      article_category_id: catId,
      article_category_path: catPath,
      content_type: Number(data.content_type) || TYPE_GRAPHIC,
      status: Number(data.status),
      is_hot: Number(data.is_hot),
      is_banner: Number(data.is_banner),
      is_trial: Number(data.is_trial),
      sort: Number(data.sort) || 0,
      price: parseFloat(data.price) || 0,
      original_price: parseFloat(data.original_price) || 0,
      word_count: Number(data.word_count) || 0,
      virtual_learn_count: Number(data.virtual_learn_count) || 0,
      media_duration: Number(data.media_duration) || 0,
      trial_media_url: data.trial_media_url || '',
      images: Array.isArray(data.images) ? data.images : [],
      trial_images: Array.isArray(data.trial_images) ? data.trial_images : [],
      content: data.content || '',
      trial_content: data.trial_content || ''
    })
    isPaid.value = form.price > 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

// 内容类型切换
function handleTypeChange() {
  // 切换类型时清空对应内容字段
  form.content = ''
  form.trial_content = ''
  form.images = []
  form.trial_images = []
  form.media_url = ''
  form.media_duration = 0
  form.trial_media_url = ''
}

// 付费状态切换
function handlePaidChange(val) {
  if (!val) {
    form.price = 0
    form.original_price = 0
    form.is_trial = 0
  }
}

// 级联选择器变更
function handleCategoryChange(val) {
  // val 是数组，取最后一个元素作为实际 id
  form.article_category_id = val
}

// 打开快捷添加分类弹窗
function openQuickAdd() {
  Object.assign(quickAddForm, { name: '', pid: 0 })
  quickAddVisible.value = true
}

// 提交快捷添加分类
function submitQuickAdd() {
  const name = quickAddForm.name.trim()
  if (!name) {
    ElMessage.warning('请输入分类名称')
    return
  }
  quickAddLoading.value = true
  articleCategoryQuickAdd({
    name: name,
    pid: quickAddForm.pid || 0
  }).then(res => {
    ElMessage.success('添加成功')
    quickAddVisible.value = false
    // 重新加载分类树
    loadCategoryList().then(() => {
      // 自动选中新添加的分类
      const newItem = res.data
      if (newItem && newItem.id) {
        const pid = newItem.pid || 0
        if (pid > 0) {
          form.article_category_path = [pid, newItem.id]
        } else {
          form.article_category_path = [newItem.id]
        }
        form.article_category_id = newItem.id
      }
    })
    quickAddLoading.value = false
  }).catch(() => {
    quickAddLoading.value = false
  })
}

// 提交表单
function submitForm() {
  formRef.value.validate(valid => {
    if (!valid) return
    // 媒体类型校验
    if ((isAudioType.value || isVideoType.value) && !form.media_url) {
      ElMessage.error('请上传媒体文件')
      return
    }
    if (isImageType.value && (!form.images || form.images.length === 0)) {
      ElMessage.error('请上传至少一张图片')
      return
    }
    if (isImageType.value && form.is_trial && (!form.trial_images || form.trial_images.length === 0)) {
      ElMessage.error('请上传至少一张图片')
      return
    }
    submitLoading.value = true
    const submitData = { ...form }
    if (!isPaid.value) {
      submitData.price = 0
      submitData.original_price = 0
      submitData.is_trial = 0
    }
    const api = isEdit.value
      ? articleSave(route.query.id, submitData)
      : articleAdd(submitData)
    api.then(res => {
      ElMessage.success(res.message || '操作成功')
      goBack()
      submitLoading.value = false
    }).catch(res => {
      submitLoading.value = false
      ElMessage.error(res.message || '操作失败')
    })
  })
}

// 手动触发重新截取试听/试看片段
async function doReClip(refName) {
  const uploader = proxy.$refs[refName]
  if (!uploader) return
  await uploader.reGenerateTrialClip()
}

// 试听截取进行中状态同步（BigFileUpload 内部触发）
function handleTrialClipping(isClipping) {
  trialClipping.value = isClipping
}

// 试听片段上传成功（BigFileUpload 内部分片上传完成后触发）
function handleTrialUploaded(url) {
  form.trial_media_url = url
  ElMessage.success('试听片段已生成并上传成功')
}

// 媒体时长自动回填
function handleMediaDuration(seconds) {
  if (seconds > 0) {
    form.media_duration = seconds
    ElMessage.success('已自动识别时长：' + formatDuration(seconds))
  }
}

// 格式化时长显示
function formatDuration(sec) {
  if (!sec) return ''
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  if (h > 0) return `${h}时${m}分${s}秒`
  if (m > 0) return `${m}分${s}秒`
  return `${s}秒`
}

// 返回
function goBack() {
  router.push('/article/article')
}

onMounted(() => {
  initData()
})

onBeforeUnmount(() => {
  const currentEditor = editor.value
  if (currentEditor) currentEditor.destroy()
  const currentTrialEditor = trialEditor.value
  if (currentTrialEditor) currentTrialEditor.destroy()
})
</script>

<style lang="scss" scoped>
.content-create-container {
  min-height: calc(100vh - 100px);
}

.create-card {
  border-radius: 8px;
  margin: 0 auto;

  :deep .el-card__header {
    padding: 16px 24px;
    border-bottom: 1px solid #ebeef5;
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .header-title {
    font-size: 16px;
    font-weight: 600;
    color: #303133;

    i {
      margin-right: 8px;
      color: #409eff;
    }
  }
}

.create-form {
  padding: 8px 0;
}

.form-section {
  margin-bottom: 24px;
  padding: 20px 24px;
  background: #fafafa;
  border-radius: 6px;

  &:last-of-type {
    margin-bottom: 0;
  }

  .section-title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 20px;
    padding-left: 10px;
    border-left: 3px solid #409eff;
  }
}

.form-width {
  width: 480px;
}

.category-select-row {
  display: flex;
  align-items: center;
  gap: 10px;

  .form-width {
    flex-shrink: 0;
  }
}

.quick-add-btn {
  font-size: 13px;
  padding: 0;
  white-space: nowrap;
}

.quick-add-form {
  padding: 0 8px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 6px;
  line-height: 1.5;
}

.trial-content-tip {
  margin-top: 0;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 4px;

  i {
    color: #409eff;
  }
}

.unit-text {
  margin-left: 8px;
  font-size: 14px;
  color: #606266;
}

// ===== 完整文件播放器 =====
.media-player-wrap {
  margin-top: 10px;
  max-width: 480px;

  .player-label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 6px;
  }
}

.media-player {
  width: 100%;
  outline: none;
  border-radius: 4px;
}

.video-player {
  max-height: 240px;
  background: #000;
}

// ===== 试听/试看片段区域 =====
.trial-block {
  max-width: 560px;
}

.trial-seconds-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;

  .trial-label {
    font-size: 13px;
    color: #606266;
    white-space: nowrap;
  }

  .trial-num {
    width: 110px;
  }
}

.trial-player-wrap {
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  padding: 12px 14px;
  background: #f9fafb;

  .trial-player-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
  }
}

.trial-empty {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #909399;
  padding: 10px 0;

  i {
    color: #409eff;
  }
}

.ml-8 {
  margin-left: 8px;
}

.editor-wrap {
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  overflow: hidden;

  :deep .w-e-toolbar {
    border-bottom: 1px solid #e8e8e8 !important;
  }

  :deep .w-e-text-container {
    background-color: #fff;
  }
}

.form-footer {
  padding: 24px 24px 8px;
  display: flex;
  align-items: center;
  gap: 12px;

  :deep .el-button {
    padding: 10px 30px;
    font-size: 14px;
  }
}

:deep .el-radio-button__orig-radio:checked + .el-radio-button__inner {
  background-color: #409eff;
  border-color: #409eff;
}

:deep .el-form-item__label {
  font-weight: 500;
  color: #606266;
}

:deep .el-input-number {
  .el-input__inner {
    text-align: left;
  }
}
</style>
