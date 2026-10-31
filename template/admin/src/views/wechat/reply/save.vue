<template>
  <div>
    <el-card>
      <template #header>
        <div class="clearfix">
          <span>{{ pageTitle }}</span>
        </div>
      </template>

      <div class="reply-container">
        <!-- 手机预览区 -->
        <div class="preview-wrapper">
          <div class="mobile-header">公众号</div>
          <div class="view-body">
            <div class="time-wrapper"><span class="time">9:36</span></div>

            <!-- 文字消息预览 -->
            <div v-if="form.type === 'text'" class="view-item text-box clearfix show">
              <div class="avatar fl"><img src="@/assets/images/f.png"></div>
              <div class="box-content fl">{{ form.data.content || '文字消息内容' }}</div>
            </div>

            <!-- 图片消息预览 -->
            <div v-if="form.type === 'image'" class="view-item text-box clearfix show">
              <div class="avatar fl"><img src="@/assets/images/f.png"></div>
              <div class="box-content fl">
                <img v-if="form.data.src" :src="form.data.src" class="picbox">
                <span v-else>请上传图片</span>
              </div>
            </div>

            <!-- 图文消息预览 -->
            <div v-if="form.type === 'news' && newsData.length > 0" class="view-item news-box show">
              <div v-if="newsData.length === 1" class="vn-content">
                <div class="vn-title">{{ newsData[0].title }}</div>
                <div class="vn-time">{{ newsData[0].create_time }}</div>
                <div v-if="newsData[0].image_input" class="vn-picture" :style="{backgroundImage: 'url(' + newsData[0].image_input + ')'}" />
                <div class="vn-picture-info">{{ newsData[0].synopsis || '' }}</div>
                <div class="vn-more">阅读原文</div>
              </div>
              <div v-else class="vn-content">
                <div class="con-item-box">
                  <div v-if="newsData[0].image_input" class="vn-picture" :style="{backgroundImage: 'url(' + newsData[0].image_input + ')'}" />
                  <div class="first-title">{{ newsData[0].title }}</div>
                </div>
                <div v-for="(item, index) in secondaryNewsData" :key="index" class="con-item-list clearfix">
                  <div class="list-tit-info fl">{{ item.title }}</div>
                  <div v-if="item.image_input" class="list-pic fr" :style="{backgroundImage: 'url(' + item.image_input + ')'}" />
                </div>
              </div>
            </div>

            <!-- 语音消息预览 -->
            <div v-if="form.type === 'voice'" class="view-item text-box clearfix show">
              <div class="avatar fl"><img src="@/assets/images/f.png"></div>
              <div class="box-content fl">
                <p>语音消息</p>
                <div class="music-icon"><LegacyIcon name="el-icon-mic" /></div>
              </div>
            </div>
          </div>
        </div>

        <!-- 表单编辑区 -->
        <div class="control-wrapper">
          <el-form ref="form" :model="form" label-width="100px">
            <el-form-item v-if="!isSpecial" label="关键字">
              <div class="keyword-input-wrapper">
                <el-tag
                  v-for="(tag, index) in keywordTags"
                  :key="index"
                  closable

                  style="margin: 0 5px 5px 0;"
                  @close="removeKeywordTag(index)"
                >{{ tag }}</el-tag>
                <el-input
                  v-if="keywordInputVisible"
                  ref="keywordInputRef"
                  v-model="keywordInputValue"

                  class="keyword-input"
                  placeholder="输入后按 Enter 确认"
                  @keyup.enter="confirmKeywordInput"
                  @blur="confirmKeywordInput"
                />
                <el-button
                  v-else

                  icon="el-icon-plus"
                  @click="showKeywordInput"
                >添加关键字</el-button>
              </div>
              <div class="tips">支持多个关键字，用于触发该回复规则</div>
            </el-form-item>

            <el-form-item label="规则状态">
              <el-radio-group v-model="form.status">
                <el-radio :label="1">启用</el-radio>
                <el-radio :label="0">禁用</el-radio>
              </el-radio-group>
            </el-form-item>

            <el-form-item label="消息类型">
              <el-select v-model="form.type" @change="handleTypeChange">
                <el-option label="文字消息" value="text" />
                <el-option label="图片消息" value="image" />
                <el-option label="图文消息" value="news" />
                <el-option label="声音消息" value="voice" />
              </el-select>
            </el-form-item>

            <!-- 文字消息 -->
            <div class="control-main">
              <el-form-item v-if="form.type === 'text'" label="规则内容">
                <el-input v-model="form.data.content" type="textarea" :rows="5" placeholder="请输入内容" />
              </el-form-item>

              <!-- 图片消息 -->
              <el-form-item v-if="form.type === 'image'" label="选取图片">
                <div class="upload-box" @click="uploadImage">
                  <img v-if="form.data.src" :src="form.data.src">
                  <LegacyIcon v-else name="el-icon-plus" />
                </div>
                <div class="tips">文件最大2Mb，支持bmp/png/jpeg/jpg/gif格式</div>
              </el-form-item>

              <!-- 图文消息 -->
              <el-form-item v-if="form.type === 'news'" label="选取图文">
                <el-button type="primary" @click="openNewsDialog">选择图文消息</el-button>
                <div v-if="newsData.length" class="selected-news-preview">
                  <el-tag
                    v-for="(item, index) in newsData"
                    :key="index"
                    closable
                    style="margin: 5px 5px 0 0;"
                    @close="removeNews(index)"
                  >{{ item.title }}</el-tag>
                </div>
              </el-form-item>

              <!-- 语音消息 -->
              <el-form-item v-if="form.type === 'voice'" label="上传声音">
                <el-input v-model="form.data.src" placeholder="语音文件路径" readonly>
                  <template #append>
                    <el-upload
                      ref="voiceUpload"
                      :action="uploadFileUrl"
                      :headers="uploadHeaders"
                      :show-file-list="false"
                      :on-success="handleVoiceSuccess"
                      :on-error="handleVoiceError"
                      :before-upload="beforeVoiceUpload"
                      accept=".mp3,.wma,.wav,.amr"
                    >
                      <el-button>上传</el-button>
                    </el-upload>
                  </template>
                </el-input>
                <div class="tips">文件最大2Mb，支持mp3/wma/wav/amr格式</div>
              </el-form-item>
            </div>

            <el-form-item>
              <el-button type="primary" :loading="submitting" @click="submit">提交</el-button>
              <el-button @click="handleBack">返回</el-button>
            </el-form-item>
          </el-form>
        </div>
      </div>
    </el-card>

    <!-- 图文消息选择对话框 -->
    <el-dialog v-model="newsDialogVisible" title="选择图文消息" width="800px" append-to-body>
      <div class="news-dialog-search">
        <el-input v-model="newsSearch.title" placeholder="搜索文章标题" clearable style="width: 300px;" @keyup.enter="fetchArticleList">
          <template #append>
            <el-button icon="el-icon-search" @click="fetchArticleList" />
          </template>
        </el-input>
      </div>
      <el-table
        ref="newsTable"
        v-loading="articleLoading"
        :data="articleList"
        border
        style="margin-top: 15px;"
        @selection-change="handleNewsSelectionChange"
      >
        <el-table-column type="selection" width="55" align="center" />
        <el-table-column label="封面" width="80" align="center">
          <template #default="{row}">
            <img v-if="row.image_input" :src="row.image_input" style="width: 50px; height: 50px; object-fit: cover;">
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="200" />
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
      </el-table>
      <div class="acea-row row-right page" style="margin-top: 15px;">
        <el-pagination
          :current-page="newsSearch.page"
          :page-size="newsSearch.limit"
          layout="total, prev, pager, next"
          :total="articleTotal"
          @current-change="handleNewsPageChange"
        />
      </div>
      <template #footer>
        <div>
          <el-button @click="newsDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmNewsSelect">确定</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { saveKeyword, getKeywordInfo, saveReply, getReplyInfo } from '@/api/wechat'
import { getArticleList } from '@/api/article'
import setting from '@/settings'
import { getToken } from '@/utils/auth'

defineOptions({
  name: 'WechatReplySave'
})

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const imgModal = proxy.$imgModal

const isSpecial = ref(false)
const pageTitle = ref('新建关键字回复')
const submitting = ref(false)
const keywordTags = ref([])
const keywordInputVisible = ref(false)
const keywordInputValue = ref('')
const keywordInputRef = ref(null)
const newsData = ref([])
const newsDialogVisible = ref(false)
const articleList = ref([])
const articleTotal = ref(0)
const articleLoading = ref(false)
const selectedArticles = ref([])

const dataGroup = reactive({
  text: { content: '' },
  image: { src: '' },
  voice: { src: '' },
  news: []
})

const form = reactive({
  id: undefined,
  key: '',
  status: 1,
  type: 'text',
  data: dataGroup.text
})

const newsSearch = reactive({
  page: 1,
  limit: 10,
  title: ''
})

const uploadFileUrl = setting.baseUri + '/wechat.wechat_reply/upload_file'
const uploadHeaders = {
  Authorization: 'Bearer ' + getToken()
}

const secondaryNewsData = computed(() => newsData.value.slice(1))

watch(() => route.fullPath, init, { immediate: true })

function init() {
  const path = route.path
  if (path.includes('subscribe')) {
    isSpecial.value = true
    pageTitle.value = '关注回复'
    form.key = 'subscribe'
    fetchSpecial('subscribe')
  } else if (path.includes('default')) {
    isSpecial.value = true
    pageTitle.value = '无效关键字回复'
    form.key = 'default'
    fetchSpecial('default')
  } else {
    isSpecial.value = false
    const id = route.params.id
    if (id) {
      pageTitle.value = '编辑关键字回复'
      form.id = id
      fetchKeyword(id)
    } else {
      pageTitle.value = '新建关键字回复'
      resetForm()
    }
  }
}

function resetForm() {
  Object.assign(dataGroup.text, { content: '' })
  Object.assign(dataGroup.image, { src: '' })
  Object.assign(dataGroup.voice, { src: '' })
  dataGroup.news = []
  newsData.value = []
  keywordTags.value = []
  keywordInputVisible.value = false
  keywordInputValue.value = ''
  Object.assign(form, {
    id: undefined,
    key: '',
    status: 1,
    type: 'text',
    data: dataGroup.text
  })
}

function assignFormDataByType(type) {
  form.data = type === 'news' ? {} : dataGroup[type]
}

function fetchKeyword(id) {
  getKeywordInfo(id).then(res => {
    const data = res.data || {}
    keywordTags.value = data.key ? data.key.split(',').map(k => k.trim()).filter(k => k) : []
    form.status = data.status
    form.type = data.type
    form.id = data.id
    fillDataGroup(data.type, data.data)
    assignFormDataByType(data.type)
  })
}

function fetchSpecial(key) {
  getReplyInfo(key).then(res => {
    const data = res.data
    if (data && data.id) {
      form.status = data.status
      form.type = data.type
      fillDataGroup(data.type, data.data)
      assignFormDataByType(data.type)
    }
  })
}

function fillDataGroup(type, data) {
  if (type === 'text') {
    Object.assign(dataGroup.text, { content: data.content || '' })
  } else if (type === 'image') {
    Object.assign(dataGroup.image, { src: data.src || '' })
  } else if (type === 'voice') {
    Object.assign(dataGroup.voice, { src: data.src || '' })
  } else if (type === 'news') {
    newsData.value = Array.isArray(data) ? data : []
  }
}

function handleTypeChange(val) {
  assignFormDataByType(val)
}

function showKeywordInput() {
  keywordInputVisible.value = true
  nextTick(() => {
    keywordInputRef.value && keywordInputRef.value.focus()
  })
}

function confirmKeywordInput() {
  const val = keywordInputValue.value.trim()
  if (val) {
    const newTags = val.split(',').map(k => k.trim()).filter(k => k)
    newTags.forEach(tag => {
      if (!keywordTags.value.includes(tag)) {
        keywordTags.value.push(tag)
      }
    })
  }
  keywordInputVisible.value = false
  keywordInputValue.value = ''
}

function removeKeywordTag(index) {
  keywordTags.value.splice(index, 1)
}

function uploadImage() {
  imgModal((image) => {
    const url = image && image.path
    if (url) {
      dataGroup.image.src = url
      form.data = dataGroup.image
    }
  }, 1)
}

function beforeVoiceUpload(file) {
  const allowTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-ms-wma', 'audio/amr']
  const isAudio = allowTypes.includes(file.type) || /\.(mp3|wma|wav|amr)$/i.test(file.name)
  const isLt2M = file.size / 1024 / 1024 < 2

  if (!isAudio) {
    message.error('只能上传mp3/wma/wav/amr格式的音频文件!')
    return false
  }
  if (!isLt2M) {
    message.error('音频文件大小不能超过2MB!')
    return false
  }
  return true
}

function handleVoiceSuccess(response) {
  if (response.status === 200) {
    dataGroup.voice.src = response.data
    form.data = dataGroup.voice
    message.success('上传成功')
  } else {
    message.error(response.msg || '上传失败')
  }
}

function handleVoiceError() {
  message.error('上传失败，请重试')
}

function openNewsDialog() {
  newsDialogVisible.value = true
  newsSearch.page = 1
  newsSearch.title = ''
  selectedArticles.value = []
  fetchArticleList()
}

function fetchArticleList() {
  articleLoading.value = true
  getArticleList({
    page: newsSearch.page,
    limit: newsSearch.limit,
    title: newsSearch.title
  }).then(res => {
    articleList.value = res.data.list || []
    articleTotal.value = res.data.total || 0
    articleLoading.value = false
  }).catch(() => {
    articleLoading.value = false
  })
}

function handleNewsPageChange(page) {
  newsSearch.page = page
  fetchArticleList()
}

function handleNewsSelectionChange(val) {
  selectedArticles.value = val
}

function confirmNewsSelect() {
  if (!selectedArticles.value.length) {
    message.warning('请至少选择一条图文消息')
    return
  }
  const existIds = newsData.value.map(item => item.id)
  selectedArticles.value.forEach(article => {
    if (!existIds.includes(article.id)) {
      newsData.value.push({
        id: article.id,
        title: article.title,
        image_input: article.image_input || '',
        synopsis: article.synopsis || '',
        url: article.url || '',
        create_time: article.create_time || ''
      })
    }
  })
  newsDialogVisible.value = false
}

function removeNews(index) {
  newsData.value.splice(index, 1)
}

function submit() {
  if (!check()) return

  submitting.value = true
  const postData = {
    key: keywordTags.value.join(','),
    status: form.status,
    type: form.type,
    data: form.type === 'news' ? newsData.value : form.data
  }

  if (isSpecial.value) {
    postData.key = form.key
    saveReply(postData).then(() => {
      message.success('设置成功')
      submitting.value = false
    }).catch(() => {
      submitting.value = false
    })
  } else {
    if (form.id) {
      postData.id = form.id
    }
    saveKeyword(postData).then(() => {
      message.success('设置成功')
      submitting.value = false
      router.push('/channel/wechat/reply/keyword')
    }).catch(() => {
      submitting.value = false
    })
  }
}

function check() {
  if (!isSpecial.value && keywordTags.value.length === 0) {
    message.error('请至少添加一个关键字')
    return false
  }
  if (form.type === 'text' && !form.data.content) {
    message.error('请输入文字消息内容')
    return false
  }
  if (form.type === 'image' && !form.data.src) {
    message.error('请上传图片')
    return false
  }
  if (form.type === 'voice' && !form.data.src) {
    message.error('请上传声音')
    return false
  }
  if (form.type === 'news' && (!newsData.value || newsData.value.length === 0)) {
    message.error('请选择图文消息')
    return false
  }
  return true
}

function handleBack() {
  router.go(-1)
}
</script>

<style lang="scss" scoped>
.reply-container {
  display: flex;
  gap: 20px;
}

:deep(.el-card__header){
  font-size: 16px;
  font-weight: bold;
}
.preview-wrapper {
  width: 317px;
  min-height: 580px;
  border: 1px solid var(--dashboard-border);
  background: var(--dashboard-surface-muted);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.mobile-header {
  height: 50px;
  background: var(--dashboard-text-primary);
  color: var(--dashboard-surface);
  text-align: center;
  line-height: 50px;
  font-size: 16px;
}

.view-body {
  flex: 1;
  padding: 10px;
  overflow-y: auto;
}

.time-wrapper {
  text-align: center;
  margin-bottom: 10px;

  .time {
    font-size: 12px;
    color: var(--dashboard-text-muted);
    background: var(--dashboard-border);
    padding: 2px 6px;
    border-radius: 3px;
  }
}

.control-wrapper {
  flex: 1;
  background: var(--dashboard-surface-muted);
  border: 1px solid var(--dashboard-border);
  padding: 20px;
  min-height: 580px;
  color: var(--dashboard-text-primary);
}

.view-item {
  margin-bottom: 10px;
}

.avatar {
  width: 40px;
  height: 40px;
  margin-right: 10px;

  img {
    width: 100%;
    height: 100%;
    border-radius: 4px;
  }
}

.box-content {
  background: var(--dashboard-surface);
  padding: 10px;
  border: 1px solid var(--dashboard-border);
  border-radius: 5px;
  max-width: 220px;
  word-wrap: break-word;
  font-size: 14px;
  color: var(--dashboard-text-primary);
}

.picbox {
  max-width: 100%;
}

.news-box {
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 5px;
  padding: 10px;
  color: var(--dashboard-text-primary);
}

.vn-title {
  font-size: 16px;
  margin-bottom: 5px;
  font-weight: bold;
}

.vn-time {
  color: var(--dashboard-text-muted);
  font-size: 12px;
  margin-bottom: 5px;
}

.vn-picture {
  height: 150px;
  background-size: cover;
  background-position: center;
  margin-bottom: 5px;
  border-radius: 4px;
}

.vn-picture-info {
  color: var(--dashboard-text-secondary);
  font-size: 14px;
  margin-bottom: 5px;
}

.vn-more {
  border-top: 1px solid var(--dashboard-border);
  padding-top: 5px;
  font-size: 14px;
  color: var(--dashboard-accent);
}

.con-item-box {
  position: relative;
  margin-bottom: 10px;

  .vn-picture {
    height: 120px;
  }
}

.first-title {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  padding: 5px 10px;
  font-size: 14px;
}

.con-item-list {
  padding: 10px 0;
  border-top: 1px solid var(--dashboard-border);
}

.list-tit-info {
  width: 70%;
  font-size: 14px;
}

.list-pic {
  width: 20%;
  height: 40px;
  background-size: cover;
  background-position: center;
  border-radius: 4px;
}

.upload-box {
  width: 100px;
  height: 100px;
  border: 1px dashed var(--dashboard-border);
  background: var(--dashboard-surface);
  border-radius: 6px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: border-color 0.3s;

  &:hover {
    border-color: var(--dashboard-accent);
  }

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  i {
    font-size: 28px;
    color: var(--dashboard-text-muted);
  }
}

.tips {
  color: var(--dashboard-text-muted);
  font-size: 12px;
  margin-top: 5px;
}

.music-icon {
  margin-top: 5px;

  i {
    font-size: 24px;
    color: var(--dashboard-accent);
  }
}

.selected-news-preview {
  margin-top: 10px;
}

.keyword-input-wrapper {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  min-height: 32px;
  padding: 3px 0;

  .keyword-input {
    width: 160px;
  }
}

.news-dialog-search {
  display: flex;
  align-items: center;
}

.clearfix::after {
  content: "";
  display: table;
  clear: both;
}

.fl {
  float: left;
}

.fr {
  float: right;
}
</style>
