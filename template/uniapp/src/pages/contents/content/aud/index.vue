<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="audio-detail-page">
    <view class="audio-detail-page__topbar">
      <app-safe-top
        :title="pageTitle"
        background="transparent"
        mp-extra="12rpx"
        :show-back="true"
        emit-back
        icon-color="#1a1c1c"
        @back="handleBack"
      />
    </view>

    <view v-if="loading" class="audio-state">
      <text class="audio-state__text">加载中...</text>
    </view>

    <view v-else-if="errorMessage" class="audio-state">
      <text class="audio-state__text">{{ errorMessage }}</text>
      <view class="audio-state__button" @click="fetchDetail">
        <text>重新加载</text>
      </view>
    </view>

    <scroll-view v-else scroll-y class="audio-detail-page__scroll">
      <view class="audio-detail-page__content">
        <view class="audio-hero">
          <view class="cover-stack">
            <view class="cover-stack__shadow"></view>
            <view class="cover-stack__panel">
              <image class="cover-stack__image" :src="detail.cover || DEFAULT_COVER" mode="aspectFill"></image>

              <view v-if="!isUnlocked" class="cover-lock">
                <uni-icons type="locked-filled" size="34" color="#ffffff"></uni-icons>
                <text class="cover-lock__title">付费解锁完整音频</text>
                <text class="cover-lock__price">¥ {{ detail.priceText }}</text>
                <view class="cover-lock__actions">
                  <view class="cover-lock__primary" @click="handleUnlockClick">立即解锁</view>
                  <view class="cover-lock__ghost" @click="handleTrial">免费试听</view>
                </view>
              </view>
            </view>
          </view>

          <view class="audio-meta">
            <view class="audio-meta__heading">
              <text v-if="!isUnlocked" class="audio-meta__tag">试听中</text>
              <text class="audio-meta__title">{{ detail.title }}</text>
            </view>
            <text class="audio-meta__speaker">主讲人：{{ detail.speaker }} · {{ detail.series }}</text>
          </view>

          <view class="progress-box">
            <view class="progress-box__track">
              <view class="progress-box__value" :style="progressBarStyle">
                <view class="progress-box__dot"></view>
              </view>
            </view>
            <view class="progress-box__meta">
              <view class="progress-box__left">
                <text v-if="!isUnlocked" class="progress-box__trial">试听 {{ currentTimeText }}</text>
                <text v-else class="progress-box__time">{{ currentTimeText }}</text>
                <text class="progress-box__time">/ {{ detail.durationText }}</text>
              </view>
              <text class="progress-box__time" :class="{ 'progress-box__time--muted': !isUnlocked }">
                {{ isUnlocked ? '完整音频' : '付费内容' }}
              </text>
            </view>
          </view>

          <view class="player-controls" :class="{ 'player-controls--locked': !isUnlocked }">
            <view
              class="player-controls__side"
              :class="{ 'player-controls__side--active': isSingleLoopMode }"
              @click="handleChangeLoopMode"
            >
              <text class="iconfont icon-danquxunhuan"></text>
            </view>
            <view class="player-controls__skip" @click="handleSkip(-15)">
              <text class="iconfont icon-backwardfill player-controls__skip-icon"></text>
            </view>
            <view class="player-controls__play" @click="handleTogglePlay">
              <view class="player-controls__play-core">
                <view v-if="isPlaying" class="player-controls__pause-icon">
                  <view class="player-controls__pause-bar"></view>
                  <view class="player-controls__pause-bar"></view>
                </view>
                <view v-else class="player-controls__play-triangle"></view>
              </view>
            </view>
            <view class="player-controls__skip" @click="handleSkip(15)">
              <text class="iconfont icon-play_forward_fill player-controls__skip-icon"></text>
            </view>
            <view
              class="player-controls__side"
              :class="{ 'player-controls__side--disabled': !canOpenPlaylist }"
              @click="handleOpenPlaylist"
            >
              <text class="iconfont icon-liebiao"></text>
            </view>
          </view>
        </view>

        <view class="recommend-section">
          <guess-you-like title="为你推荐" :show-more="false" :limit="4" />
        </view>
      </view>
    </scroll-view>

    <view v-if="!loading && !errorMessage" class="floating-actions">
      <view class="floating-actions__item" @click="handleAction('like')">
        <uni-icons :type="isLiked ? 'heart-filled' : 'heart'" size="18" :color="isLiked ? '#ffffff' : 'rgba(255,255,255,0.9)'" />
        <text class="floating-actions__text">{{ detail.likeText }}</text>
      </view>
      <view class="floating-actions__divider"></view>
      <view class="floating-actions__item" @click="handleOpenCommentPopup">
        <uni-icons type="chat" size="18" color="rgba(255,255,255,0.9)" />
        <text class="floating-actions__text">{{ detail.commentText }}</text>
      </view>
      <view class="floating-actions__divider"></view>
      <view class="floating-actions__item" @click="handleAction('share')">
        <uni-icons type="redo" size="18" color="rgba(255,255,255,0.9)" />
        <text class="floating-actions__text">分享</text>
      </view>
    </view>

    <view v-if="showCommentPopup" :class="['comment-popup', { 'comment-popup--active': isCommentPopupActive }]">
        <view class="comment-popup__mask" @click="handleCloseCommentPopup"></view>
        <view class="comment-sheet">
          <view class="comment-sheet__header">
            <text class="comment-sheet__title">评论 ({{ detail.commentTotalText }})</text>
            <view class="comment-sheet__close" @click="handleCloseCommentPopup">
              <uni-icons type="closeempty" size="22" color="#1a1c1c"></uni-icons>
            </view>
          </view>

          <scroll-view scroll-y class="comment-sheet__body scrollbar-hidden" :show-scrollbar="false">
            <view v-if="commentLoading" class="comment-state">
              <text class="comment-state__text">评论加载中...</text>
            </view>
            <view v-else-if="commentError" class="comment-state">
              <text class="comment-state__text">{{ commentError }}</text>
              <view class="comment-state__button" @click="fetchComments">
                <text>重新加载</text>
              </view>
            </view>
            <view v-else-if="!detail.comments.length" class="comment-state">
              <text class="comment-state__text">暂无评论</text>
            </view>
            <block v-else>
              <view v-for="comment in detail.comments" :key="comment.id" class="comment-card">
                <view class="comment-card__row">
                  <image v-if="comment.user && comment.user.avatar" class="comment-card__avatar" :src="comment.user.avatar" mode="aspectFill"></image>
                  <view v-else class="comment-card__avatar comment-card__avatar--placeholder">
                    <uni-icons type="person-filled" size="20" color="#8a9098"></uni-icons>
                  </view>
                  <view class="comment-card__main">
                    <view class="comment-card__top">
                      <text class="comment-card__name">{{ (comment.user && comment.user.nickname) || '匿名' }}</text>
                      <text class="comment-card__time">{{ comment.create_time }}</text>
                    </view>
                    <text class="comment-card__content">{{ comment.content }}</text>
                    <view class="comment-card__actions">
                      <view class="comment-card__action" @click="handleReply(comment, comment)">
                        <uni-icons type="chat" size="14" color="#7c7f85"></uni-icons>
                        <text class="comment-card__action-text">回复</text>
                      </view>
                    </view>
                  </view>
                </view>

                <view v-if="comment.children && comment.children.length" class="reply-box">
                  <view v-for="reply in comment.children" :key="reply.id" class="reply-box__item">
                    <image v-if="reply.user && reply.user.avatar" class="reply-box__avatar" :src="reply.user.avatar" mode="aspectFill"></image>
                    <view v-else class="reply-box__avatar reply-box__avatar--placeholder">
                      <uni-icons type="person-filled" size="12" color="#8a9098"></uni-icons>
                    </view>
                    <view class="reply-box__body">
                      <view class="reply-box__top">
                        <text class="reply-box__name">{{ (reply.user && reply.user.nickname) || '匿名' }}</text>
                        <text class="reply-box__time">{{ reply.create_time }}</text>
                      </view>
                      <text class="reply-box__content">
                        <text v-if="reply.reply_to_nickname">@{{ reply.reply_to_nickname }} </text>{{ reply.content }}
                      </text>
                      <view class="reply-box__actions">
                        <text class="reply-box__reply" @click="handleReply(reply, comment)">回复</text>
                      </view>
                    </view>
                  </view>
                </view>
              </view>
            </block>
          </scroll-view>

          <view class="comment-input">
            <view class="comment-input__field">
              <input
                v-model="commentText"
                class="comment-input__control"
                :placeholder="commentInputPlaceholder"
                placeholder-class="comment-input__placeholder"
                confirm-type="send"
                @confirm="handlePublishComment"
              />
              <view class="comment-input__icons">
                <text v-if="replyTarget" class="comment-input__cancel" @click="clearReplyTarget">取消</text>
              </view>
            </view>
            <view
              class="comment-input__send"
              :class="{ 'comment-input__send--disabled': commentSubmitting }"
              @click="handlePublishComment"
            >{{ commentSubmitting ? '发送中' : '发送' }}</view>
          </view>
        </view>
      </view>

    <view
      v-if="showPlaylistPopup"
      :class="['playlist-popup', { 'playlist-popup--active': isPlaylistPopupActive }]"
      @touchmove.stop.prevent="noop"
    >
      <view class="playlist-popup__mask" @click="handleClosePlaylistPopup"></view>
      <view class="playlist-sheet" @click.stop>
        <view class="playlist-sheet__handle"></view>
        <view class="playlist-sheet__header">
          <text class="playlist-sheet__title">音乐列表</text>
          <view class="playlist-sheet__tools">
            <view class="playlist-sheet__close" @click="handleClosePlaylistPopup">
              <uni-icons type="closeempty" size="20" color="#1a1c1c"></uni-icons>
            </view>
          </view>
        </view>

        <scroll-view scroll-y class="playlist-sheet__body scrollbar-hidden" :show-scrollbar="false">
          <view v-if="playlistLoading" class="playlist-state">
            <text class="playlist-state__text">音乐列表加载中...</text>
          </view>
          <view v-else-if="playlistError" class="playlist-state">
            <text class="playlist-state__text">{{ playlistError }}</text>
            <view class="playlist-state__button" @click="fetchPlaylist(true)">
              <text>重新加载</text>
            </view>
          </view>
          <view v-else-if="!displayPlaylistItems.length" class="playlist-state">
            <text class="playlist-state__text">暂无可播放音乐</text>
          </view>
          <view v-else class="playlist-list">
            <view
              v-for="item in displayPlaylistItems"
              :key="item.key"
              class="playlist-list__item"
              :class="{ 'playlist-list__item--active': item.isCurrent }"
              @click="handlePlaylistItemClick(item)"
            >
              <image class="playlist-list__cover" :src="item.cover" mode="aspectFill"></image>
              <view class="playlist-list__main">
                <text class="playlist-list__title">{{ item.title }}</text>
                <text class="playlist-list__subtitle">{{ item.subtitleText }}</text>
              </view>
              <view class="playlist-list__control" @click.stop="handlePlaylistItemClick(item)">
                <view v-if="item.isCurrent && isPlaying" class="playlist-list__pause">
                  <view class="playlist-list__pause-bar"></view>
                  <view class="playlist-list__pause-bar"></view>
                </view>
                <view v-else class="playlist-list__play"></view>
              </view>
            </view>
          </view>
        </scroll-view>

        <view v-if="miniPlaylistItem" class="playlist-mini">
          <image class="playlist-mini__cover" :src="miniPlaylistItem.cover" mode="aspectFill"></image>
          <view class="playlist-mini__main">
            <text class="playlist-mini__title">{{ miniPlaylistItem.title }}</text>
            <text class="playlist-mini__subtitle">{{ miniPlaylistItem.subtitleText }}</text>
          </view>
          <view class="playlist-mini__controls">
            <view class="playlist-mini__skip" @click="handlePlaylistStep(-1)">
              <text class="iconfont icon-backwardfill"></text>
            </view>
            <view class="playlist-mini__play" @click="handleTogglePlay">
              <view v-if="isPlaying" class="playlist-mini__pause">
                <view class="playlist-mini__pause-bar"></view>
                <view class="playlist-mini__pause-bar"></view>
              </view>
              <view v-else class="playlist-mini__play-triangle"></view>
            </view>
            <view class="playlist-mini__skip" @click="handlePlaylistStep(1)">
              <text class="iconfont icon-play_forward_fill"></text>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import { onLoad, onUnload } from '@dcloudio/uni-app'
import { getContentDetail, toggleContentLike } from '@/api/content'
import { createComment, getCommentList, replyComment } from '@/api/comment'
import { getSpecialAudioList } from '@/api/special'
import { getUserInfo } from '@/utils/auth'
import GuessYouLike from '@/components/guess-you-like/index.vue'

const DEFAULT_AVATAR = '/static/images/avatar_default.jpg'
const DEFAULT_COVER = '/static/images/news.png'
const AUDIO_CONTENT_TYPE = 5
const LOOP_MODE_SINGLE = 1
const LOOP_MODE_LABELS = ['顺序播放', '单曲循环', '列表循环']

function formatTime(seconds = 0) {
  const safeSeconds = Math.max(Number(seconds) || 0, 0)
  const minutes = Math.floor(safeSeconds / 60)
  const remainSeconds = safeSeconds % 60
  return `${String(minutes).padStart(2, '0')}:${String(remainSeconds).padStart(2, '0')}`
}

function formatCount(count = 0) {
  const num = Number(count || 0)
  if (num >= 10000) {
    return (num / 10000).toFixed(1).replace(/\.0$/, '') + '万'
  }
  return String(num)
}

function formatMoney(value = 0) {
  const amount = Number(value || 0)
  if (amount <= 0) {
    return '免费'
  }
  return amount.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
}

defineOptions({ name: 'ContentAudPage' })

const pageTitle = ref('音频详情')
const articleId = ref(0)
const entrySource = ref('')
const specialId = ref(0)
const specialAudioCount = ref(0)
const detail = ref({
  cover: DEFAULT_COVER,
  title: '',
  speaker: '',
  series: '',
  durationSeconds: 0,
  durationText: '00:00',
  priceText: '免费',
  likeText: '0',
  commentText: '0',
  commentTotalText: '0',
  comments: []
})
const loading = ref(true)
const errorMessage = ref('')
const isUnlocked = ref(false)
const isPlaying = ref(false)
const isLiked = ref(false)
const likeSubmitting = ref(false)
const currentSeconds = ref(0)
const loopModeIndex = ref(0)
const audioContext = ref(null)
const audioSrc = ref('')
const showCommentPopup = ref(false)
const isCommentPopupActive = ref(false)
const commentPopupTimer = ref(null)
const commentText = ref('')
const replyTarget = ref(null)
const commentTotal = ref(0)
const commentLoading = ref(false)
const commentError = ref('')
const commentSubmitting = ref(false)
const showPlaylistPopup = ref(false)
const isPlaylistPopupActive = ref(false)
const playlistPopupTimer = ref(null)
const playlistLoading = ref(false)
const playlistError = ref('')
const playlistLoaded = ref(false)
const playlistItems = ref([])
const playlistSwitching = ref(false)

const commentInputPlaceholder = computed(() => {
  if (!replyTarget.value) {
    return '写下你的精彩评论...'
  }
  return `回复 ${(replyTarget.value.user && replyTarget.value.user.nickname) || '用户'}`
})

const displaySeconds = computed(() => {
  return Math.min(currentSeconds.value, Number(detail.value.durationSeconds || 0) || 0)
})

const currentTimeText = computed(() => {
  return formatTime(displaySeconds.value)
})

const progressPercent = computed(() => {
  const total = Math.max(Number(detail.value.durationSeconds || 0), 1)
  return Math.max(Math.min((displaySeconds.value / total) * 100, 100), 0)
})

const progressBarStyle = computed(() => {
  return `width: ${progressPercent.value}%;`
})

const isFromSpecial = computed(() => {
  return entrySource.value === 'special' && specialId.value > 0
})

const canOpenPlaylist = computed(() => {
  return isFromSpecial.value && specialAudioCount.value >= 2
})

const isSingleLoopMode = computed(() => {
  return loopModeIndex.value === LOOP_MODE_SINGLE
})

const displayPlaylistItems = computed(() => {
  return playlistItems.value.map((item) => {
    const isCurrent = Number(item.articleId || 0) === Number(articleId.value || 0)
    return {
      ...item,
      isCurrent
    }
  })
})

const miniPlaylistItem = computed(() => {
  const current = displayPlaylistItems.value.find((item) => item.isCurrent)
  if (current) {
    return current
  }

  if (!detail.value.id) {
    return null
  }

  return {
    key: `current-${detail.value.id}`,
    articleId: Number(detail.value.id || 0),
    title: detail.value.title || '音频内容',
    subtitleText: `${detail.value.speaker || '平台主讲'} · ${detail.value.series || '音频内容'}`,
    cover: detail.value.cover || DEFAULT_COVER,
    isCurrent: true
  }
})

onLoad((options = {}) => {
  initPage(options)
})

onUnload(() => {
  clearPlaylistPopupTimer()
  clearCommentPopupTimer()
  destroyAudioContext()
})

/**
 * 初始化音频详情页。
 *
 * @param {Object} options 页面参数。
 * @returns {void}
 */
function initPage(options = {}) {
  const shouldOpenCommentPopup = String(options.popup || '') === 'comment'
  articleId.value = Number(options.id || 0)
  entrySource.value = String(options.source || '').trim()
  specialId.value = Number(options.special_id || options.specialId || 0)
  specialAudioCount.value = Math.max(0, Number(options.audio_count || options.audioCount || 0))
  showCommentPopup.value = false
  isCommentPopupActive.value = false
  commentText.value = ''
  replyTarget.value = null
  commentTotal.value = 0
  commentError.value = ''
  playlistError.value = ''
  playlistLoaded.value = false
  playlistItems.value = []
  showPlaylistPopup.value = false
  isPlaylistPopupActive.value = false
  playlistSwitching.value = false
  isPlaying.value = false
  isLiked.value = false
  likeSubmitting.value = false
  loopModeIndex.value = 0
  currentSeconds.value = 0
  syncPageTitle('音频详情')

  clearCommentPopupTimer()
  clearPlaylistPopupTimer()
  if (shouldOpenCommentPopup) {
    handleOpenCommentPopup()
  }
  fetchDetail()
}

/**
 * 获取音频详情并初始化播放器数据。
 *
 * @returns {Promise<void>}
 */
async function fetchDetail() {
  if (!articleId.value) {
    errorMessage.value = '内容ID不能为空'
    loading.value = false
    return
  }

  loading.value = true
  errorMessage.value = ''
  stopAudio()

  try {
    const res = await getContentDetail({ id: articleId.value }, {
      showError: false,
      loginRedirect: false
    })

    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '音频加载失败'
      return
    }

    if (Number(res.data.content_type || 0) !== AUDIO_CONTENT_TYPE) {
      errorMessage.value = '不支持的内容类型'
      return
    }

    applyDetail(res.data)
    fetchComments()
  } catch (e) {
    errorMessage.value = '音频加载失败'
  } finally {
    loading.value = false
  }
}

/**
 * 将接口字段转换为页面展示字段。
 *
 * @param {Object} data 接口返回的音频详情。
 * @returns {void}
 */
function applyDetail(data = {}) {
  const durationSeconds = Number(data.media_duration || 0)
  const priceText = formatMoney(data.price)
  const title = String(data.title || '')
  isUnlocked.value = String(data.access_state || '') === 'learning'
  isLiked.value = Boolean(data.is_liked)
  audioSrc.value = isUnlocked.value ? String(data.media_url || '') : String(data.trial_media_url || '')
  currentSeconds.value = 0
  commentTotal.value = Number(data.comment_total || 0)
  detail.value = {
    id: Number(data.id || 0),
    cover: String(data.cover || '') || DEFAULT_COVER,
    title,
    speaker: String(data.author || '') || '平台主讲',
    series: String(data.description || '') || '音频内容',
    durationSeconds,
    durationText: formatTime(durationSeconds),
    priceText,
    likeText: formatCount(data.like_count),
    commentText: formatCount(commentTotal.value),
    commentTotalText: formatCount(commentTotal.value),
    comments: [],
    is_trial: Number(data.is_trial || 0),
    trial_media_url: String(data.trial_media_url || ''),
    media_url: String(data.media_url || '')
  }
  syncPageTitle(title || '音频详情')
  prepareAudioContext()
}

/**
 * 获取评论列表。
 *
 * @returns {Promise<void>}
 */
async function fetchComments() {
  if (!articleId.value) {
    return
  }

  commentLoading.value = true
  commentError.value = ''
  try {
    const res = await getCommentList({ article_id: articleId.value }, {
      showError: false
    })

    if (!res || res.code !== 200 || !res.data) {
      commentError.value = (res && res.msg) || '评论加载失败'
      return
    }

    const list = Array.isArray(res.data.list) ? res.data.list : []
    const count = Number(res.data.count || 0)
    commentTotal.value = count
    detail.value.comments = list
    detail.value.commentText = formatCount(count)
    detail.value.commentTotalText = formatCount(count)
  } catch (e) {
    commentError.value = '评论加载失败'
  } finally {
    commentLoading.value = false
  }
}

/**
 * 同步自定义导航标题与原生页面标题。
 *
 * @param {string} title 页面标题。
 * @returns {void}
 */
function syncPageTitle(title = '') {
  const nextTitle = String(title || '').trim() || '音频详情'
  pageTitle.value = nextTitle
  uni.setNavigationBarTitle({
    title: nextTitle
  })
}

/**
 * 返回上一页。
 *
 * @returns {void}
 */
function handleBack() {
  const pages = getCurrentPages()
  if (Array.isArray(pages) && pages.length > 1) {
    uni.navigateBack()
    return
  }
  uni.switchTab({ url: '/pages/index/index' })
}

/**
 * 切换播放状态。
 *
 * @returns {void}
 */
function handleTogglePlay() {
  if (!audioSrc.value) {
    uni.showToast({ title: isUnlocked.value ? '暂无音频内容' : '暂无试听内容', icon: 'none' })
    return
  }

  if (isPlaying.value) {
    pauseAudio()
    return
  }

  if (!audioContext.value) {
    prepareAudioContext()
  }
  audioContext.value.play()
}

/**
 * 快进或快退播放进度。
 *
 * @param {number} deltaSeconds 偏移秒数。
 * @returns {void}
 */
function handleSkip(deltaSeconds) {
  if (!audioContext.value || !audioSrc.value) {
    uni.showToast({ title: '暂无可播放音频', icon: 'none' })
    return
  }
  const durationSeconds = Number(detail.value.durationSeconds || 0)
  const nextSeconds = currentSeconds.value + Number(deltaSeconds || 0)
  const targetSeconds = Math.max(Math.min(nextSeconds, durationSeconds), 0)
  audioContext.value.seek(targetSeconds)
  currentSeconds.value = targetSeconds
}

/**
 * 切换循环模式，并立即同步到当前播放器。
 *
 * @returns {void}
 */
function handleChangeLoopMode() {
  loopModeIndex.value = (loopModeIndex.value + 1) % LOOP_MODE_LABELS.length
  syncAudioLoop()
  uni.showToast({ title: LOOP_MODE_LABELS[loopModeIndex.value], icon: 'none' })
}

/**
 * 打开专题音乐列表弹窗。
 *
 * @returns {void}
 */
function handleOpenPlaylist() {
  if (!canOpenPlaylist.value) {
    return
  }

  clearPlaylistPopupTimer()
  showPlaylistPopup.value = true
  nextTick(() => {
    isPlaylistPopupActive.value = true
  })

  if (!playlistLoaded.value && !playlistLoading.value) {
    fetchPlaylist()
  }
}

/**
 * 获取专题音频播放列表。
 *
 * @param {boolean} force 是否强制重新加载。
 * @returns {Promise<void>}
 */
async function fetchPlaylist(force = false) {
  if (!specialId.value || (!force && playlistLoaded.value)) {
    return
  }

  playlistLoading.value = true
  playlistError.value = ''
  try {
    const res = await getSpecialAudioList({
      special_id: specialId.value,
      current_id: articleId.value
    }, {
      showError: false,
      loginRedirect: false
    })

    if (!res || res.code !== 200 || !res.data) {
      playlistError.value = (res && res.msg) || '音乐列表加载失败'
      return
    }

    const list = Array.isArray(res.data.list) ? res.data.list : []
    const total = Number(res.data.total || list.length || 0)
    specialAudioCount.value = Math.max(0, total)
    playlistItems.value = list.map((item, index) => normalizePlaylistItem(item, index)).filter(Boolean)
    playlistLoaded.value = true
  } catch (e) {
    playlistError.value = '音乐列表加载失败'
  } finally {
    playlistLoading.value = false
  }
}

/**
 * 规范化专题音频列表项。
 *
 * @param {Object} item 原始接口字段。
 * @param {number} index 列表序号。
 * @returns {Object|null} 页面可渲染字段。
 */
function normalizePlaylistItem(item = {}, index = 0) {
  const articleId = Number(item.article_id || item.id || 0)
  if (!articleId) {
    return null
  }

  const author = String(item.author || '').trim() || '平台主讲'
  const subtitle = String(item.subtitle || '').trim() || '音频内容'

  return {
    key: `audio-${articleId}-${Number(item.special_content_id || index)}`,
    id: articleId,
    articleId,
    specialContentId: Number(item.special_content_id || 0),
    title: String(item.title || '').trim() || '音频内容',
    subtitleText: `${author} · ${subtitle}`,
    author,
    subtitle,
    cover: String(item.cover || '').trim() || DEFAULT_COVER,
    durationText: formatTime(Number(item.media_duration || 0)),
    sort: Number(item.sort || index)
  }
}

/**
 * 点击专题音乐列表项。
 *
 * @param {Object} item 当前列表项。
 * @returns {Promise<void>}
 */
async function handlePlaylistItemClick(item = {}) {
  const targetId = Number(item.articleId || item.id || 0)
  if (!targetId || playlistSwitching.value) {
    return
  }

  if (targetId === Number(articleId.value || 0)) {
    handleTogglePlay()
    return
  }

  await switchToPlaylistAudio(targetId)
}

/**
 * 切换到专题音乐列表中的指定音频。
 *
 * @param {number} targetId 目标内容 ID。
 * @returns {Promise<void>}
 */
async function switchToPlaylistAudio(targetId) {
  if (!targetId || playlistSwitching.value) {
    return
  }

  playlistSwitching.value = true
  stopAudio()
  try {
    const res = await getContentDetail({ id: targetId }, {
      showError: false,
      loginRedirect: false
    })

    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({ title: (res && res.msg) || '音频加载失败', icon: 'none' })
      return
    }

    if (Number(res.data.content_type || 0) !== AUDIO_CONTENT_TYPE) {
      uni.showToast({ title: '不支持的内容类型', icon: 'none' })
      return
    }

    articleId.value = targetId
    applyDetail(res.data)
    if (audioSrc.value) {
      nextTick(() => {
        if (audioContext.value) {
          audioContext.value.play()
        }
      })
    }
    fetchComments()
  } catch (e) {
    uni.showToast({ title: '音频加载失败', icon: 'none' })
  } finally {
    playlistSwitching.value = false
  }
}

/**
 * 按列表顺序切换上一首或下一首。
 *
 * @param {number} step 切换步长。
 * @returns {Promise<void>}
 */
async function handlePlaylistStep(step = 1) {
  const list = displayPlaylistItems.value
  if (!list.length || playlistSwitching.value) {
    return
  }

  const currentIndex = list.findIndex((item) => item.isCurrent)
  const startIndex = currentIndex >= 0 ? currentIndex : 0
  const nextIndex = (startIndex + Number(step || 0) + list.length) % list.length
  await switchToPlaylistAudio(Number(list[nextIndex].articleId || 0))
}

/**
 * 清理播放列表弹窗动画计时器。
 *
 * @returns {void}
 */
function clearPlaylistPopupTimer() {
  if (playlistPopupTimer.value) {
    clearTimeout(playlistPopupTimer.value)
    playlistPopupTimer.value = null
  }
}

/**
 * 关闭专题音乐列表弹窗。
 *
 * @returns {void}
 */
function handleClosePlaylistPopup() {
  if (!showPlaylistPopup.value) {
    return
  }

  clearPlaylistPopupTimer()
  isPlaylistPopupActive.value = false
  playlistPopupTimer.value = setTimeout(() => {
    showPlaylistPopup.value = false
    playlistPopupTimer.value = null
  }, 300)
}

/**
 * 空函数，用于阻止弹层滚动穿透。
 *
 * @returns {void}
 */
function noop() {
}

/**
 * 付费解锁占位。
 *
 * @returns {void}
 */
function handleUnlockClick() {
  uni.showToast({ title: '支付功能开发中', icon: 'none' })
}

/**
 * 播放试听音频。
 *
 * @returns {void}
 */
function handleTrial() {
  if (isUnlocked.value) {
    handleTogglePlay()
    return
  }
  if (!audioSrc.value) {
    uni.showToast({ title: '暂无试听内容', icon: 'none' })
    return
  }
  handleTogglePlay()
  uni.showToast({ title: '正在播放试听', icon: 'none' })
}

/**
 * 处理底部快捷操作。
 *
 * @param {string} action 操作类型。
 * @returns {void}
 */
async function handleAction(action) {
  if (action === 'like') {
    await handleToggleLike()
    return
  }
  if (action === 'share') {
    uni.showToast({ title: '分享功能开发中', icon: 'none' })
  }
}

/**
 * 切换内容点赞状态。
 *
 * @returns {Promise<void>}
 */
async function handleToggleLike() {
  if (likeSubmitting.value) {
    return
  }

  likeSubmitting.value = true
  try {
    const res = await toggleContentLike({ article_id: articleId.value }, {
      showError: false
    })

    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({ title: (res && res.msg) || '点赞失败', icon: 'none' })
      return
    }

    isLiked.value = Boolean(res.data.is_liked)
    detail.value.likeText = formatCount(res.data.like_count)
    uni.showToast({ title: isLiked.value ? '已点赞' : '已取消点赞', icon: 'none' })
  } catch (e) {
    uni.showToast({ title: '点赞失败', icon: 'none' })
  } finally {
    likeSubmitting.value = false
  }
}

/**
 * 清理评论弹窗动画计时器。
 *
 * @returns {void}
 */
function clearCommentPopupTimer() {
  if (commentPopupTimer.value) {
    clearTimeout(commentPopupTimer.value)
    commentPopupTimer.value = null
  }
}

/**
 * 打开评论弹窗。
 *
 * @returns {void}
 */
function handleOpenCommentPopup() {
  clearCommentPopupTimer()
  if (showCommentPopup.value && isCommentPopupActive.value) {
    return
  }
  showCommentPopup.value = true
  if (!detail.value.comments.length && !commentLoading.value) {
    fetchComments()
  }
  nextTick(() => {
    isCommentPopupActive.value = true
  })
}

/**
 * 关闭评论弹窗。
 *
 * @returns {void}
 */
function handleCloseCommentPopup() {
  if (!showCommentPopup.value) {
    return
  }
  clearCommentPopupTimer()
  isCommentPopupActive.value = false
  commentPopupTimer.value = setTimeout(() => {
    showCommentPopup.value = false
    commentPopupTimer.value = null
    clearReplyTarget()
  }, 300)
}

/**
 * 清空回复目标。
 *
 * @returns {void}
 */
function clearReplyTarget() {
  replyTarget.value = null
}

/**
 * 发布评论或回复。
 *
 * @returns {Promise<void>}
 */
async function handlePublishComment() {
  const content = String(commentText.value || '').trim()
  if (!content) {
    uni.showToast({ title: '请输入评论内容', icon: 'none' })
    return
  }
  if (commentSubmitting.value) {
    return
  }

  commentSubmitting.value = true
  try {
    const payload = {
      article_id: articleId.value,
      content,
      images: []
    }
    const res = replyTarget.value
      ? await replyComment({ ...payload, pid: Number(replyTarget.value.pid || 0) }, { showError: false })
      : await createComment(payload, { showError: false })

    if (!res || res.code !== 200) {
      uni.showToast({ title: (res && res.msg) || '评论提交失败', icon: 'none' })
      return
    }

    const isReply = !!replyTarget.value
    const replyRootId = Number(replyTarget.value && replyTarget.value.pid || 0)
    commentText.value = ''
    appendCreatedComment(res.data || {}, {
      isReply,
      replyRootId
    })
    clearReplyTarget()
    uni.showToast({ title: isReply ? '回复成功' : '评论成功', icon: 'success' })
  } catch (e) {
    uni.showToast({ title: '评论提交失败', icon: 'none' })
  } finally {
    commentSubmitting.value = false
  }
}

/**
 * 设置回复目标。
 *
 * @param {Object} target 被回复的评论或回复。
 * @param {Object} root 一级评论。
 * @returns {void}
 */
function handleReply(target = {}, root = {}) {
  const rootId = Number(root.id || target.root_id || target.pid || target.id || 0)
  if (!rootId) {
    uni.showToast({ title: '回复目标无效', icon: 'none' })
    return
  }
  replyTarget.value = {
    ...target,
    pid: rootId
  }
}

/**
 * 获取当前登录用户的评论展示字段。
 *
 * @returns {Object} 评论用户字段。
 */
function getCurrentCommentUser() {
  const userInfo = getUserInfo()
  return {
    uid: Number(userInfo.uid || userInfo.id || 0),
    nickname: String(userInfo.nickname || userInfo.name || ''),
    avatar: String(userInfo.avatar || '')
  }
}

/**
 * 将新发布的评论或回复追加到当前评论列表。
 *
 * @param {Object} comment 接口返回的评论字段。
 * @param {Object} options 追加选项。
 * @param {boolean} options.isReply 是否为回复。
 * @param {number} options.replyRootId 回复所属一级评论 ID。
 * @returns {void}
 */
function appendCreatedComment(comment = {}, options = {}) {
  if (!comment || !comment.id) {
    return
  }

  const nextComment = {
    ...comment,
    user: comment.user || getCurrentCommentUser(),
    children: Array.isArray(comment.children) ? comment.children : []
  }

  if (options.isReply) {
    const rootId = Number(options.replyRootId || nextComment.pid || nextComment.root_id || 0)
    detail.value.comments = detail.value.comments.map((item) => {
      if (Number(item.id || 0) !== rootId) {
        return item
      }
      const children = Array.isArray(item.children) ? item.children.slice() : []
      return {
        ...item,
        children: children.concat(nextComment)
      }
    })
    return
  }

  detail.value.comments = [nextComment].concat(detail.value.comments || [])
  commentTotal.value += 1
  detail.value.commentText = formatCount(commentTotal.value)
  detail.value.commentTotalText = formatCount(commentTotal.value)
}

/**
 * 准备音频上下文。
 *
 * @returns {void}
 */
function prepareAudioContext() {
  destroyAudioContext()
  if (!audioSrc.value || typeof uni.createInnerAudioContext !== 'function') {
    return
  }

  const context = uni.createInnerAudioContext()
  context.src = audioSrc.value
  context.obeyMuteSwitch = false
  context.loop = isSingleLoopMode.value
  context.onPlay(() => {
    isPlaying.value = true
  })
  context.onPause(() => {
    isPlaying.value = false
  })
  context.onStop(() => {
    isPlaying.value = false
  })
  context.onEnded(() => {
    // 单曲循环不能停在结束态。H5/部分 iOS 会忽略 loop，仍要在 ended 后重播。
    if (isSingleLoopMode.value) {
      replayCurrentAudio()
      return
    }
    isPlaying.value = false
    currentSeconds.value = 0
  })
  context.onTimeUpdate(() => {
    currentSeconds.value = Math.floor(Number(context.currentTime || 0))
  })
  context.onError(() => {
    isPlaying.value = false
    uni.showToast({ title: '音频播放失败', icon: 'none' })
  })
  audioContext.value = context
}

let audioReplayToken = 0
let audioReplayAt = 0

/**
 * 取消尚未执行的单曲重播，避免暂停或切歌后被延迟 play 拉起来。
 *
 * @returns {void}
 */
function cancelPendingAudioReplay() {
  audioReplayToken += 1
}

/**
 * 按当前模式同步原生循环开关。
 * 只在单曲循环时打开，顺序播放和列表循环仍要收到结束事件。
 *
 * @returns {void}
 */
function syncAudioLoop() {
  if (!isSingleLoopMode.value) {
    cancelPendingAudioReplay()
  }
  if (!audioContext.value) {
    return
  }
  audioContext.value.loop = isSingleLoopMode.value
}

/**
 * 从开头重播当前音频。
 * ended 后立刻 play 会停在末尾并再次结束，所以延迟 seek 后再播。
 *
 * @returns {void}
 */
function replayCurrentAudio() {
  const context = audioContext.value
  const src = String(audioSrc.value || '')
  if (!context || !src || !isSingleLoopMode.value) {
    isPlaying.value = false
    currentSeconds.value = 0
    return
  }

  const now = Date.now()
  // seek 失败时 ended 会马上再来一次，避免把重播打成死循环。
  if (now - audioReplayAt < 300) {
    isPlaying.value = false
    return
  }
  audioReplayAt = now

  const token = audioReplayToken + 1
  audioReplayToken = token
  currentSeconds.value = 0
  isPlaying.value = true
  context.loop = true

  setTimeout(() => {
    if (token !== audioReplayToken || audioContext.value !== context || !isSingleLoopMode.value) {
      return
    }
    context.seek(0)
    const played = context.play()
    if (played && typeof played.catch === 'function') {
      played.catch(() => {
        if (token === audioReplayToken) {
          isPlaying.value = false
        }
      })
    }
  }, 60)
}

/**
 * 暂停当前音频。
 *
 * @returns {void}
 */
function pauseAudio() {
  cancelPendingAudioReplay()
  if (audioContext.value) {
    audioContext.value.pause()
  }
  isPlaying.value = false
}

/**
 * 停止当前音频并归零进度。
 *
 * @returns {void}
 */
function stopAudio() {
  cancelPendingAudioReplay()
  if (audioContext.value) {
    audioContext.value.stop()
  }
  isPlaying.value = false
  currentSeconds.value = 0
}

/**
 * 销毁音频上下文，避免离页后继续播放。
 *
 * @returns {void}
 */
function destroyAudioContext() {
  cancelPendingAudioReplay()
  if (audioContext.value) {
    audioContext.value.destroy()
    audioContext.value = null
  }
  isPlaying.value = false
}
</script>

<style lang="scss" scoped>
.audio-detail-page {
  position: relative;
  min-height: 100%;
  background: #f5f5f5;
  color: #1a1c1c;
}

.audio-detail-page__topbar {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 20;
}

.audio-detail-page__scroll {
  height: 100%;
}

.audio-detail-page__content {
  min-height: 100%;
  padding: 0 32rpx;
}

.audio-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 640rpx;
  padding: 160rpx 32rpx 80rpx;
  box-sizing: border-box;
}

.audio-state__text {
  font-size: 28rpx;
  line-height: 40rpx;
  color: #7c7f85;
}

.audio-state__button {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 72rpx;
  margin-top: 24rpx;
  padding: 0 36rpx;
  border-radius: 36rpx;
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  font-size: 26rpx;
  font-weight: 700;
}

.audio-hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 120rpx;
}

.cover-stack {
  position: relative;
  width: 640rpx;
  height: 640rpx;
  /* #ifdef MP-WEIXIN */
  margin-top: 40rpx;
  /* #endif */
}

.cover-stack__shadow {
  position: absolute;
  inset: 24rpx;
  border-radius: 32rpx;
  background: rgba(0, 0, 0, 0.06);
  transform: rotate(4deg);
}

.cover-stack__panel {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 32rpx;
  background: #ffffff;
  box-shadow: 0 32rpx 72rpx rgba(17, 17, 17, 0.12);
}

.cover-stack__image {
  width: 100%;
  height: 100%;
}

.cover-lock {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40rpx;
  background: rgba(0, 0, 0, 0.58);
}

.cover-lock__title {
  margin-top: 24rpx;
  font-size: 32rpx;
  font-weight: 700;
  color: #ffffff;
}

.cover-lock__price {
  margin-top: 12rpx;
  font-size: 56rpx;
  font-weight: 700;
  line-height: 1;
  color: #ffffff;
}

.cover-lock__actions {
  width: 100%;
  margin-top: 40rpx;
}

.cover-lock__primary,
.cover-lock__ghost {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 88rpx;
  border-radius: 24rpx;
  font-size: 28rpx;
  font-weight: 700;
}

.cover-lock__primary {
  background: var(--brand-primary, #ea580c);
  color: #ffffff;
  box-shadow: 0 16rpx 32rpx rgba(255, 69, 0, 0.22);
}

.cover-lock__ghost {
  margin-top: 20rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.56);
  color: #ffffff;
}

.audio-meta {
  width: 100%;
  margin-top: 48rpx;
  text-align: center;
}

.audio-meta__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  margin: -6rpx;
}

.audio-meta__heading > text {
  margin: 6rpx;
}

.audio-meta__tag {
  padding: 4rpx 12rpx;
  border-radius: 8rpx;
  background: #e2e2e2;
  font-size: 20rpx;
  font-weight: 700;
  color: #5b5f64;
}

.audio-meta__title {
  max-width: 100%;
  font-size: 48rpx;
  font-weight: 700;
  line-height: 1.3;
  color: #000000;
}

.audio-meta__speaker {
  margin-top: 12rpx;
  font-size: 26rpx;
  line-height: 38rpx;
  color: #5b5f64;
}

.progress-box {
  width: 100%;
  margin-top: 56rpx;
}

.progress-box__track {
  position: relative;
  height: 8rpx;
  overflow: hidden;
  border-radius: 999rpx;
  background: #e2e2e2;
}

.progress-box__value {
  position: relative;
  height: 100%;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);
}

.progress-box__dot {
  position: absolute;
  top: 50%;
  right: -10rpx;
  width: 24rpx;
  height: 24rpx;
  border: 4rpx solid var(--brand-primary, #ea580c);
  border-radius: 50%;
  background: #ffffff;
  transform: translateY(-50%);
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.12);
}

.progress-box__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 16rpx;
}

.progress-box__left {
  display: flex;
  align-items: center;
}

.progress-box__left > text + text {
  margin-left: 8rpx;
}

.progress-box__trial {
  font-size: 20rpx;
  font-weight: 700;
  color: var(--brand-primary, #ea580c);
}

.progress-box__time {
  font-size: 20rpx;
  line-height: 28rpx;
  color: #7c7f85;
}

.progress-box__time--muted {
  opacity: 0.55;
}

.player-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  margin-top: 52rpx;
  padding: 0 24rpx;
}

.player-controls--locked {
  opacity: 0.42;
}

.player-controls__side,
.player-controls__skip,
.player-controls__play {
  display: flex;
  align-items: center;
  justify-content: center;
}

.player-controls__side {
  width: 72rpx;
  height: 72rpx;
  color: #000000;
}

.player-controls__side--active {
  color: var(--brand-primary, #ea580c);
}

.player-controls__side--disabled {
  color: #a8adb4;
  opacity: 0.32;
}

.player-controls__skip {
  width: 96rpx;
  height: 96rpx;
}

.player-controls__skip-icon {
  font-size: 54rpx;
  line-height: 1;
  color: #000000;
}

.player-controls__play {
  width: 160rpx;
  height: 160rpx;
  border-radius: 50%;
  background: #000000;
  box-shadow: 0 24rpx 48rpx rgba(0, 0, 0, 0.16);
}

.player-controls__play-core {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 82rpx;
  height: 82rpx;
  border-radius: 50%;
  background: #f6f6f6;
  box-shadow: inset 0 2rpx 8rpx rgba(255, 255, 255, 0.4), 0 6rpx 16rpx rgba(0, 0, 0, 0.12);
}

.player-controls__play-triangle {
  width: 0;
  height: 0;
  margin-left: 6rpx;
  border-top: 18rpx solid transparent;
  border-bottom: 18rpx solid transparent;
  border-left: 28rpx solid #000000;
}

.player-controls__pause-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.player-controls__pause-bar {
  width: 10rpx;
  height: 34rpx;
  border-radius: 6rpx;
  background: #000000;
}

.player-controls__pause-bar + .player-controls__pause-bar {
  margin-left: 10rpx;
}

.recommend-section {
  margin-top: 88rpx;
  margin-left: -32rpx;
  margin-right: -32rpx;
  padding: 48rpx 32rpx 120rpx;
  border-radius: 48rpx 48rpx 0 0;
  background: #ffffff;
  box-shadow: 0 -24rpx 48rpx rgba(0, 0, 0, 0.04);
}

.floating-actions {
  position: fixed;
  left: 50%;
  bottom: 24rpx;
  z-index: 35;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 28rpx;
  height: 88rpx;
  border-radius: 44rpx;
  background: rgba(0, 0, 0, 0.9);
  transform: translateX(-50%);
  box-shadow: 0 20rpx 36rpx rgba(0, 0, 0, 0.18);
}

.floating-actions__item {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 112rpx;
  padding: 0 18rpx;
  white-space: nowrap;
}

.floating-actions__item--share {
  padding-right: 10rpx;
}

.floating-actions__share-icon {
  font-size: 28rpx;
  font-weight: 700;
  line-height: 1;
  color: rgba(255, 255, 255, 0.92);
}

.floating-actions__text {
  margin-left: 8rpx;
  font-size: 20rpx;
  font-weight: 700;
  line-height: 1;
  color: rgba(255, 255, 255, 0.92);
}

.floating-actions__divider {
  width: 2rpx;
  height: 28rpx;
  background: rgba(255, 255, 255, 0.16);
}

.comment-popup {
  position: fixed;
  inset: 0;
  z-index: 40;
  overflow: hidden;
}

.comment-popup__mask {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.58);
  opacity: 0;
  transition: opacity 240ms ease;
}

.comment-sheet {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  height: 76%;
  overflow: hidden;
  border-radius: 32rpx 32rpx 0 0;
  background: #f5f5f5;
  opacity: 0.94;
  transform: translateY(108%);
  transform-origin: center bottom;
  will-change: transform, opacity;
  transition: transform 300ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease;
}

.comment-popup--active .comment-popup__mask {
  opacity: 1;
}

.comment-popup--active .comment-sheet {
  opacity: 1;
  transform: translateY(0);
}

.comment-sheet__header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 25rpx 25rpx 20rpx;
}

.comment-sheet__title {
  font-size: 32rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.comment-sheet__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
}

.comment-sheet__body {
  flex: 1;
  min-height: 0;
  height: 0;
  padding: 0 20rpx 20rpx;
  box-sizing: border-box;
}

.comment-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 360rpx;
  padding: 48rpx 24rpx;
  box-sizing: border-box;
}

.comment-state__text {
  font-size: 26rpx;
  line-height: 38rpx;
  color: #7c7f85;
}

.comment-state__button {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 20rpx;
  padding: 14rpx 32rpx;
  border-radius: 999rpx;
  background: var(--brand-primary, #ea580c);

  text {
    font-size: 24rpx;
    font-weight: 700;
    color: #ffffff;
  }
}

.comment-card {
  margin-bottom: 20rpx;
  padding: 20rpx;
  border-radius: 24rpx;
  background: #ffffff;
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.04);
}

.comment-card__row {
  display: flex;
  align-items: flex-start;
}

.comment-card__avatar {
  width: 80rpx;
  height: 80rpx;
  border-radius: 50%;
  background: #e2e2e2;
  flex-shrink: 0;
}

.comment-card__avatar--placeholder,
.reply-box__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
}

.comment-card__main {
  flex: 1;
  min-width: 0;
  margin-left: 24rpx;
}

.comment-card__top,
.reply-box__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.comment-card__name,
.reply-box__name {
  overflow: hidden;
  font-size: 28rpx;
  font-weight: 700;
  color: #1a1c1c;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.comment-card__time,
.reply-box__time {
  flex-shrink: 0;
  margin-left: 16rpx;
  font-size: 20rpx;
  color: #7c7f85;
}

.comment-card__content,
.reply-box__content {
  display: block;
  margin-top: 10rpx;
  font-size: 24rpx;
  line-height: 36rpx;
  color: #4c4546;
}

.comment-card__actions {
  display: flex;
  align-items: center;
  margin-top: 18rpx;
}

.comment-card__action {
  display: flex;
  align-items: center;
  margin-right: 28rpx;
}

.comment-card__action:last-child {
  margin-right: 0;
}

.comment-card__action-text {
  margin-left: 6rpx;
  font-size: 20rpx;
  color: #7c7f85;
}

.reply-box {
  margin-top: 10rpx;
  margin-left: 104rpx;
  padding: 20rpx;
  border-radius: 20rpx;
  background: #f3f3f3;
}

.reply-box__item {
  display: flex;
  align-items: flex-start;
}

.reply-box__item + .reply-box__item {
  margin-top: 18rpx;
}

.reply-box__avatar {
  width: 48rpx;
  height: 48rpx;
  border-radius: 50%;
  background: #e2e2e2;
  flex-shrink: 0;
}

.reply-box__body {
  flex: 1;
  min-width: 0;
  margin-left: 16rpx;
}

.reply-box__name {
  font-size: 22rpx;
}

.reply-box__time {
  font-size: 18rpx;
}

.reply-box__content {
  margin-top: 6rpx;
  font-size: 22rpx;
  line-height: 32rpx;
}

.reply-box__actions {
  display: flex;
  align-items: center;
  margin-top: 8rpx;
}

.reply-box__reply {
  font-size: 20rpx;
  font-weight: 700;
  color: #7c7f85;
}

.comment-input {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  padding: 18rpx 32rpx 28rpx;
  box-sizing: border-box;
  background: rgba(255, 255, 255, 0.98);
  border-top: 2rpx solid #ececec;
}

.comment-input__field {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
  height: 84rpx;
  padding: 0 24rpx;
  border-radius: 22rpx;
  background: #f3f3f3;
}

.comment-input__control {
  flex: 1;
  min-width: 0;
  height: 84rpx;
  font-size: 24rpx;
  color: #1a1c1c;
}

.comment-input__placeholder {
  font-size: 24rpx;
  color: #a0a4aa;
}

.comment-input__icons {
  display: flex;
  align-items: center;
}

.comment-input__cancel {
  margin-left: 16rpx;
  font-size: 22rpx;
  font-weight: 700;
  line-height: 1;
  color: #7c7f85;
}

.comment-input__send {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 112rpx;
  height: 84rpx;
  margin-left: 20rpx;
  border-radius: 22rpx;
  background: var(--brand-primary, #ea580c);
  font-size: 28rpx;
  font-weight: 700;
  color: #ffffff;
  box-shadow: 0 12rpx 24rpx rgba(255, 69, 0, 0.2);
}

.comment-input__send--disabled {
  opacity: 0.58;
}

.playlist-popup {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 50;
  overflow: hidden;
}

.playlist-popup__mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: rgba(0, 0, 0, 0.46);
  opacity: 0;
  transition: opacity 240ms ease;
}

.playlist-sheet {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  height: 812rpx;
  max-height: 78%;
  padding: 0 20rpx 14rpx;
  box-sizing: border-box;
  overflow: hidden;
  border-radius: 48rpx 48rpx 0 0;
  background: rgba(255, 255, 255, 0.96);
  opacity: 0.98;
  transform: translateY(108%);
  transform-origin: center bottom;
  will-change: transform, opacity;
  transition: transform 300ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease;
}

.playlist-popup--active .playlist-popup__mask {
  opacity: 1;
}

.playlist-popup--active .playlist-sheet {
  opacity: 1;
  transform: translateY(0);
}

.playlist-sheet__handle {
  flex-shrink: 0;
  width: 72rpx;
  height: 7rpx;
  margin: 18rpx auto 0;
  border-radius: 999rpx;
  background: #d6d6d6;
}

.playlist-sheet__header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 86rpx;
}

.playlist-sheet__title {
  font-size: 34rpx;
  line-height: 46rpx;
  font-weight: 700;
  color: #111111;
}

.playlist-sheet__tools {
  display: flex;
  align-items: center;
}

.playlist-sheet__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: rgba(229, 231, 235, 0.86);
}

.playlist-sheet__body {
  flex: 1;
  min-height: 0;
  height: 0;
  border-radius: 18rpx;
}

.playlist-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 360rpx;
  padding: 48rpx 24rpx;
  box-sizing: border-box;
}

.playlist-state__text {
  font-size: 26rpx;
  line-height: 38rpx;
  color: #7c7f85;
  text-align: center;
}

.playlist-state__button {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 64rpx;
  margin-top: 22rpx;
  padding: 0 32rpx;
  border-radius: 999rpx;
  background: #111111;
}

.playlist-state__button text {
  font-size: 24rpx;
  font-weight: 700;
  color: #ffffff;
}

.playlist-list {
  border-top: 1rpx solid rgba(0, 0, 0, 0.05);
}

.playlist-list__item {
  display: flex;
  align-items: center;
  height: 128rpx;
  border-bottom: 1rpx solid rgba(0, 0, 0, 0.06);
}

.playlist-list__item--active .playlist-list__title {
  color: #000000;
}

.playlist-list__cover {
  flex-shrink: 0;
  width: 72rpx;
  height: 72rpx;
  margin-left: 16rpx;
  border-radius: 16rpx;
  background: #e5e7eb;
}

.playlist-list__main {
  flex: 1;
  min-width: 0;
  margin-left: 24rpx;
}

.playlist-list__title,
.playlist-list__subtitle,
.playlist-mini__title,
.playlist-mini__subtitle {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.playlist-list__title {
  font-size: 28rpx;
  line-height: 40rpx;
  font-weight: 600;
  color: #171717;
}

.playlist-list__subtitle {
  margin-top: 4rpx;
  font-size: 24rpx;
  line-height: 34rpx;
  color: #6f757c;
}

.playlist-list__control {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52rpx;
  height: 52rpx;
  margin: 0 16rpx 0 18rpx;
  border: 3rpx solid #111111;
  border-radius: 50%;
  box-sizing: border-box;
}

.playlist-list__play {
  width: 0;
  height: 0;
  margin-left: 4rpx;
  border-top: 12rpx solid transparent;
  border-bottom: 12rpx solid transparent;
  border-left: 18rpx solid #111111;
}

.playlist-list__pause,
.playlist-mini__pause {
  display: flex;
  align-items: center;
  justify-content: center;
}

.playlist-list__pause-bar {
  width: 6rpx;
  height: 22rpx;
  border-radius: 6rpx;
  background: #111111;
}

.playlist-list__pause-bar + .playlist-list__pause-bar {
  margin-left: 6rpx;
}

.playlist-mini {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  height: 82rpx;
  margin-top: 12rpx;
  padding: 0 18rpx;
  border: 1rpx solid rgba(0, 122, 255, 0.24);
  border-radius: 20rpx;
  background: rgba(255, 255, 255, 0.9);
  box-sizing: border-box;
}

.playlist-mini__cover {
  flex-shrink: 0;
  width: 58rpx;
  height: 58rpx;
  border-radius: 12rpx;
  background: #e5e7eb;
}

.playlist-mini__main {
  flex: 1;
  min-width: 0;
  margin-left: 16rpx;
  height: 62rpx;
  overflow: hidden;
}

.playlist-mini__title {
  font-size: 23rpx;
  line-height: 32rpx;
  font-weight: 700;
  color: #111111;
}

.playlist-mini__subtitle {
  margin-top: 2rpx;
  height: 28rpx;
  font-size: 20rpx;
  line-height: 28rpx;
  color: #6f757c;
}

.playlist-mini__controls {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  margin-left: 12rpx;
}

.playlist-mini__skip {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44rpx;
  height: 52rpx;
}

.playlist-mini__skip .iconfont {
  font-size: 32rpx;
  line-height: 1;
  color: #111111;
}

.playlist-mini__play {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  margin: 0 10rpx;
  border-radius: 50%;
  background: #111111;
  box-shadow: 0 10rpx 24rpx rgba(0, 0, 0, 0.16);
}

.playlist-mini__play-triangle {
  width: 0;
  height: 0;
  margin-left: 4rpx;
  border-top: 12rpx solid transparent;
  border-bottom: 12rpx solid transparent;
  border-left: 19rpx solid #ffffff;
}

.playlist-mini__pause-bar {
  width: 7rpx;
  height: 24rpx;
  border-radius: 6rpx;
  background: #ffffff;
}

.playlist-mini__pause-bar + .playlist-mini__pause-bar {
  margin-left: 7rpx;
}

::v-deep .app-safe-top__back {
  left: 28rpx;
  width: 64rpx;
  height: 64rpx;
  border-radius: 36rpx;
  background: rgba(255, 255, 255, 0.76);
  box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.08);
}

/* #ifdef H5 */
.floating-actions {
  bottom: calc(var(--window-bottom) + 24rpx);
}

.comment-input {
  padding-bottom: calc(var(--window-bottom) + 28rpx);
}

.playlist-sheet {
  padding-bottom: calc(var(--window-bottom) + 16rpx);
}
/* #endif */

/* #ifdef MP-WEIXIN */
.floating-actions {
  bottom: calc(env(safe-area-inset-bottom) + 24rpx);
}

.comment-input {
  padding-bottom: calc(env(safe-area-inset-bottom) + 28rpx);
}

.playlist-sheet {
  padding-bottom: calc(env(safe-area-inset-bottom) + 16rpx);
}
/* #endif */
</style>
