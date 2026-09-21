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
            <div class="title-ai-row">
              <el-input
                v-model="form.title"
                placeholder="请输入内容标题"
                class="form-width"
                maxlength="100"
                show-word-limit
              />
              <button
                v-if="needsRichContent"
                type="button"
                class="ai-writing-btn"
                :class="{ 'is-loading': aiGenerateLoading }"
                :disabled="aiGenerateLoading"
                @click="handleAiGenerate"
              >
                <LegacyIcon v-if="aiGenerateLoading" name="el-icon-loading" />
                <span>{{ aiGenerateLoading ? '生成中' : 'AI润色写作' }}</span>
              </button>
            </div>
          </el-form-item>

          <el-form-item label="内容分类" prop="article_category_id">
            <el-cascader
              v-model="form.article_category_path"
              :options="categoryTree"
              :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
              placeholder="请选择分类"
              class="form-width"
              clearable
              filterable
              @change="handleCategoryChange"
            />
          </el-form-item>

          <el-form-item label="作者" prop="author">
            <el-input v-model="form.author" placeholder="请输入作者名" class="form-width" />
          </el-form-item>

          <el-form-item label="内容标签">
            <TagSelector ref="tagSelector" v-model="form.tag_ids" :tag-type="1" />
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
            prop="content"
            :label-width="'200px'"
          >
            <template #label>
              <span class="editor-label-text">正文内容</span>
              <button
                type="button"
                class="ai-continue-btn"
                :class="{ 'is-loading': aiContinueField === 'content' }"
                :disabled="aiContinueField !== '' || aiGenerateLoading"
                @click="handleAiContinue('content')"
              >
                <LegacyIcon v-if="aiContinueField === 'content'" name="el-icon-loading" />
                <span>AI继续帮我写</span>
              </button>
            </template>
            <div class="editor-preview-layout">
              <div class="editor-preview-layout__editor">
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
              </div>
              <ArticleContentPreview
                label="正文手机预览"
                :title="form.title"
                :author="form.author"
                :content="form.content"
              />
            </div>
          </el-form-item>

          <!-- 图文/文字/小说 - 试读富文本编辑器（付费且支持试读时显示） -->
          <el-form-item
            v-if="needsRichContent && isPaid && form.is_trial === 1"
            :label-width="'200px'"
          >
            <template #label>
              <span class="editor-label-text">试读内容</span>
              <button
                type="button"
                class="ai-continue-btn"
                :class="{ 'is-loading': aiContinueField === 'trial_content' }"
                :disabled="aiContinueField !== '' || aiGenerateLoading"
                @click="handleAiContinue('trial_content')"
              >
                <LegacyIcon v-if="aiContinueField === 'trial_content'" name="el-icon-loading" />
                <span>AI继续帮我写</span>
              </button>
            </template>
            <div class="form-tip trial-content-tip">
              <LegacyIcon name="el-icon-info" />
              用户付费前可免费阅读的内容，不填则无试读
            </div>
            <div class="editor-preview-layout">
              <div class="editor-preview-layout__editor">
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
              </div>
              <ArticleContentPreview
                label="试读手机预览"
                :title="form.title"
                :author="form.author"
                :content="form.trial_content"
                empty-text="填写试读内容后将在此处显示"
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
                    :icon="Scissor"
                    :loading="trialClipping"
                    :disabled="!form.media_url"
                    @click="doReClip('audioUpload')"
                  >立即截取
                  </el-button>
                </div>
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
                  <span>请先上传音频文件，再点击"立即截取"生成试听片段</span>
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
            <el-form-item v-if="form.is_trial === 1" label="试看片段">
              <div class="trial-block">
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
                    :icon="Scissor"
                    :loading="trialClipping"
                    :disabled="!form.media_url"
                    @click="doReClip('videoUpload')"
                  >立即截取
                  </el-button>
                </div>
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
                  <span>请先上传视频文件，再点击"立即截取"生成试看片段</span>
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
            <div class="form-tip">关键词用于前台搜索使用，多个关键词用逗号分隔</div>
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

    <!-- AI润色结果弹窗 -->
    <el-dialog
      v-model="aiResultVisible"
      title="AI润色结果"
      width="860px"
      destroy-on-close
    >
      <div v-if="aiResult" class="ai-result-dialog">
        <el-alert
          title="建议标签可直接导入当前内容标签。"
          type="info"
          show-icon
          :closable="false"
          class="ai-result-alert"
        />
        <div class="ai-result-apply">
          <span class="ai-result-apply__label">应用字段</span>
          <el-checkbox v-model="aiApplyFields.description">简介</el-checkbox>
          <el-checkbox v-model="aiApplyFields.content">正文内容</el-checkbox>
          <el-checkbox v-model="aiApplyFields.trial_content">试读内容</el-checkbox>
          <el-checkbox v-model="aiApplyFields.price">售价</el-checkbox>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">简介</div>
          <div class="ai-result-text">{{ aiResult.description || '暂无简介建议' }}</div>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">正文内容</div>
          <div class="ai-result-rich" v-html="aiResult.content || '暂无正文建议'" />
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">试读内容</div>
          <div class="ai-result-rich" v-html="aiResult.trial_content || '暂无试读建议'" />
        </div>

        <div class="ai-result-section ai-result-section--inline">
          <div class="ai-result-section__title">售价建议</div>
          <div class="ai-result-price">¥{{ formatAiPrice(aiResult.price) }}</div>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">
            <span>建议标签</span>
            <el-button
              v-if="aiTagText"
              type="text"
              size="mini"
              :icon="DocumentCopy"
              @click="copyAiTags"
            >复制</el-button>
            <el-button
              v-if="aiTagSuggestions.length"
              type="text"
              size="mini"
              :icon="Download"
              :loading="aiTagImportLoading"
              @click="handleImportAiTags"
            >一键导入</el-button>
          </div>
          <div v-if="aiTagSuggestions.length" class="ai-tag-list">
            <div
              v-for="(item, index) in aiTagSuggestions"
              :key="item.name + index"
              class="ai-tag-item"
              :style="getAiTagStyle(item)"
            >
              <span class="ai-tag-item__name">{{ item.name }}</span>
              <span v-if="item.reason" class="ai-tag-item__reason">{{ item.reason }}</span>
            </div>
          </div>
          <div v-else class="ai-result-empty">暂无标签建议</div>
        </div>
      </div>
      <template #footer>
        <span>
          <el-button @click="aiResultVisible = false">取 消</el-button>
          <el-button type="primary" @click="applyAiResult">应用选中内容</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Delete, DocumentCopy, Download, Scissor } from '@element-plus/icons-vue'
import {
  articleAdd,
  articleRead,
  articleSave,
  aiGenerateArticle,
  aiContinueArticle,
  getContentTypes,
  getArticleCategoryTree
} from '@/api/article'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'
import Upload from '@/components/Upload/index'
import BigFileUpload from '@/components/BigFileUpload/index'
import TagSelector from '@/components/TagSelector/index'
import { importAiTags } from '@/api/tag'
import ArticleContentPreview from './components/ArticleContentPreview'

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
const tagSelectorRef = ref(null)
const loading = ref(false)
const submitLoading = ref(false)
const aiGenerateLoading = ref(false)
const aiContinueField = ref('')
const aiTagImportLoading = ref(false)
const aiResultVisible = ref(false)
const aiResult = ref(null)
const aiApplyFields = reactive({
  description: true,
  content: true,
  trial_content: true,
  price: true
})
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
  source: '',
  tag_ids: []
})
const rules = {
  title: [
    { required: true, message: '请输入内容标题', trigger: 'blur' },
    { max: 100, message: '标题最多100个字符', trigger: 'blur' }
  ],
  content_type: [
    { required: true, message: '请选择内容类型', trigger: 'change' }
  ],
  article_category_id: [
    { required: true, message: '请选择内容分类', trigger: 'change' }
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
const aiTagSuggestions = computed(() => {
  if (!aiResult.value || !Array.isArray(aiResult.value.tag_suggestions)) {
    return []
  }
  return aiResult.value.tag_suggestions
})
const aiTagText = computed(() => {
  return aiTagSuggestions.value.map(item => item.name).filter(Boolean).join('，')
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
    // 建立 id->item 映射（含 pid），用于回显路径
    const buildMap = (list, map = {}) => {
      list.forEach(item => {
        map[item.value] = item
        if (item.children && item.children.length) {
          buildMap(item.children, map)
        }
      })
      return map
    }
    categoryMap.value = buildMap(tree)
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
      trial_content: data.trial_content || '',
      tag_ids: Array.isArray(data.tag_ids) ? data.tag_ids : []
    })
    isPaid.value = form.price > 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

// 内容类型切换
function handleTypeChange() {
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

// AI润色写作：只支持图文、文字、小说，返回结果先弹窗预览再应用
function handleAiGenerate() {
  if (!needsRichContent.value) {
    ElMessage.warning('AI润色写作仅支持图文、文字、小说类型')
    return
  }
  if (!String(form.title || '').trim()) {
    ElMessage.warning('请先输入内容标题')
    return
  }
  aiGenerateLoading.value = true
  aiGenerateArticle({
    title: form.title,
    content_type: form.content_type,
    author: form.author,
    keywords: form.keywords,
    description: form.description,
    content: form.content,
    trial_content: form.trial_content,
    price: form.price,
    is_trial: form.is_trial
  }).then(res => {
    aiResult.value = res.data || null
    Object.assign(aiApplyFields, {
      description: true,
      content: true,
      trial_content: true,
      price: true
    })
    aiResultVisible.value = true
    ElMessage.success(res.message || 'AI生成成功')
  }).catch(err => {
    ElMessage.error((err && err.message) || 'AI生成失败')
  }).finally(() => {
    aiGenerateLoading.value = false
  })
}

// 应用AI结果；建议标签可通过一键导入写入当前标签选择。
function applyAiResult() {
  if (!aiResult.value) return
  if (aiApplyFields.description) {
    form.description = aiResult.value.description || form.description
  }
  if (aiApplyFields.content) {
    form.content = aiResult.value.content || form.content
  }
  if (aiApplyFields.trial_content) {
    form.trial_content = aiResult.value.trial_content || form.trial_content
  }
  if (aiApplyFields.price) {
    const price = Number(aiResult.value.price || 0)
    form.price = price > 0 ? price : 0
    isPaid.value = form.price > 0
    if (isPaid.value && form.trial_content) {
      form.is_trial = 1
    } else if (!isPaid.value) {
      handlePaidChange(false)
    }
  }
  aiResultVisible.value = false
  ElMessage.success('已应用AI润色内容')
}

// AI续写：把续写结果追加到指定字段编辑器内容末尾
function handleAiContinue(field) {
  if (!needsRichContent.value) {
    ElMessage.warning('AI续写仅支持图文、文字、小说类型')
    return
  }
  if (!String(form.title || '').trim()) {
    ElMessage.warning('请先输入内容标题')
    return
  }
  if (aiContinueField.value !== '') return
  aiContinueField.value = field
  aiContinueArticle({
    title: form.title,
    content_type: form.content_type,
    field,
    content: form[field] || ''
  }).then(res => {
    const added = res && res.data && res.data.content
    if (!added) {
      ElMessage.warning((res && res.message) || 'AI未返回续写内容，请重试')
      return
    }
    form[field] = (form[field] || '') + added
    ElMessage.success((res && res.message) || '续写成功')
  }).catch(err => {
    ElMessage.error((err && err.message) || 'AI续写失败')
  }).finally(() => {
    aiContinueField.value = ''
  })
}

function handleImportAiTags() {
  if (!aiTagSuggestions.value.length) {
    ElMessage.warning('暂无可导入的建议标签')
    return
  }
  aiTagImportLoading.value = true
  importAiTags({
    tag_type: 1,
    tag_suggestions: aiTagSuggestions.value
  }).then(res => {
    const data = res.data || {}
    const ids = Array.isArray(data.tag_ids) ? data.tag_ids.map(item => Number(item)).filter(item => item > 0) : []
    const currentIds = Array.isArray(form.tag_ids) ? form.tag_ids.map(item => Number(item)) : []
    const mergedIds = currentIds.slice()
    ids.forEach(id => {
      if (mergedIds.indexOf(id) === -1) {
        mergedIds.push(id)
      }
    })
    form.tag_ids = mergedIds
    refreshTagSelector()
    ElMessage.success(formatAiTagImportMessage(data))
  }).finally(() => {
    aiTagImportLoading.value = false
  })
}

function refreshTagSelector() {
  nextTick(() => {
    if (tagSelectorRef.value && typeof tagSelectorRef.value.loadData === 'function') {
      tagSelectorRef.value.loadData()
    }
  })
}

function formatAiTagImportMessage(data) {
  const createdCount = Number(data.created_count || 0)
  const existsCount = Number(data.exists_count || 0)
  if (createdCount > 0 && existsCount > 0) {
    return `已新增${createdCount}个标签，复用${existsCount}个已有标签`
  }
  if (createdCount > 0) {
    return `已新增${createdCount}个标签`
  }
  return existsCount > 0 ? `已复用${existsCount}个已有标签` : '标签已导入'
}

function getAiTagStyle(item) {
  const style = {}
  if (item.text_color) {
    style.color = item.text_color
  }
  if (item.bg_color) {
    style.backgroundColor = item.bg_color
  }
  if (item.border_color) {
    style.borderColor = item.border_color
  }
  return style
}

// 复制AI建议标签，便于商户手动创建或检索标签
function copyAiTags() {
  const text = aiTagText.value
  if (!text) {
    ElMessage.warning('暂无可复制的建议标签')
    return
  }
  if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      ElMessage.success('建议标签已复制')
    }).catch(() => {
      copyTextFallback(text)
    })
    return
  }
  copyTextFallback(text)
}

function copyTextFallback(text) {
  if (typeof document === 'undefined') {
    ElMessage.warning('当前环境不支持复制')
    return
  }
  const input = document.createElement('textarea')
  input.value = text
  input.style.position = 'fixed'
  input.style.left = '-9999px'
  document.body.appendChild(input)
  input.select()
  document.execCommand('copy')
  document.body.removeChild(input)
  ElMessage.success('建议标签已复制')
}

function formatAiPrice(price) {
  const value = Number(price || 0)
  return value.toFixed(2)
}

// 级联选择器变更
function handleCategoryChange(val) {
  if (val && val.length) {
    form.article_category_id = val[val.length - 1]
  } else {
    form.article_category_id = ''
  }
  nextTick(() => {
    formRef.value && formRef.value.validateField('article_category_id')
  })
}

// 提交表单
function submitForm() {
  formRef.value.validate(valid => {
    if (!valid) return
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

// 试听截取进行中状态同步
function handleTrialClipping(isClipping) {
  trialClipping.value = isClipping
}

// 试听片段上传成功
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
  router.push('/merchant/content/article/article')
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

.title-ai-row {
  display: flex;
  align-items: center;
  gap: 14px;
}

.ai-writing-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 110px;
  height: 30px;
  padding: 0 18px;
  border: 0;
  border-radius: 22px;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0;
  text-shadow: 0 1px 6px rgba(19, 28, 84, 0.35);
  background-image: url('@/assets/images/button_ai.jpg');
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
  box-shadow: 0 8px 20px var(--brand-shadow);
  cursor: pointer;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;

  i {
    margin-right: 6px;
    font-size: 15px;
  }

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px var(--brand-shadow-hover);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.72;
  }
}

.editor-label-text {
  margin-right: 8px;
  white-space: nowrap;
}

.ai-continue-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  height: 24px;
  padding: 0 10px;
  border: 0;
  border-radius: 12px;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  text-shadow: 0 1px 6px rgba(19, 28, 84, 0.35);
  background-image: url('@/assets/images/button_ai.jpg');
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
  box-shadow: 0 4px 12px var(--brand-shadow);
  cursor: pointer;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;

  i {
    margin-right: 4px;
    font-size: 12px;
  }

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px var(--brand-shadow-hover);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.72;
  }
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

.ai-result-dialog {
  color: var(--text-primary, #303133);
}

.ai-result-alert {
  margin-bottom: 14px;
}

.ai-result-apply {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  padding: 12px 14px;
  margin-bottom: 14px;
  border: 1px solid var(--prev-border-color-lighter, #ebeef5);
  border-radius: 6px;
  background: var(--prev-bg-color, #f5f7fa);

  &__label {
    color: var(--text-primary, #303133);
    font-weight: 600;
  }
}

.ai-result-section {
  margin-bottom: 14px;
  padding: 14px;
  border: 1px solid var(--prev-border-color-lighter, #ebeef5);
  border-radius: 6px;
  background: var(--prev-bg-white, #ffffff);

  &--inline {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--text-primary, #303133);
    font-weight: 600;
  }
}

.ai-result-text {
  color: var(--text-regular, #606266);
  line-height: 1.7;
}

.ai-result-rich {
  max-height: 260px;
  overflow-y: auto;
  padding: 12px;
  color: var(--text-regular, #606266);
  line-height: 1.75;
  border-radius: 4px;
  background: var(--prev-bg-color, #f5f7fa);

  :deep p {
    margin: 0 0 8px;
  }

  :deep h2,
  :deep h3,
  :deep h4 {
    margin: 10px 0 8px;
    color: var(--text-primary, #303133);
  }

  :deep ul,
  :deep ol {
    padding-left: 20px;
  }
}

.ai-result-price {
  color: var(--text-price, #0080FF);
  font-size: 18px;
  font-weight: 700;
}

.ai-tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.ai-tag-item {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 6px 10px;
  border: 1px solid var(--ds-outline-ghost);
  border-radius: 16px;
  background: var(--brand-primary-pale);

  &__name {
    flex-shrink: 0;
    color: inherit;
    font-weight: 600;
  }

  &__reason {
    margin-left: 8px;
    color: var(--text-secondary, #909399);
    font-size: 12px;
  }
}

.ai-result-empty {
  color: var(--text-secondary, #909399);
  font-size: 13px;
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

.editor-preview-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 286px;
  align-items: start;
  gap: 24px;
  width: 100%;

  &__editor {
    min-width: 0;
  }
}

@media (max-width: 1180px) {
  .editor-preview-layout {
    grid-template-columns: minmax(0, 1fr);
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
