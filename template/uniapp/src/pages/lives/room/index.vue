<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="live-room" :class="{ 'live-room--landscape': pageLandscape }">
    <view class="live-room__backdrop">
      <image v-if="coverUrl" class="live-room__backdrop-image" :src="coverUrl" mode="aspectFill" />
      <view v-else class="live-room__backdrop-image live-room__backdrop-image--fallback"></view>
      <view class="live-room__backdrop-mask"></view>
    </view>

    <!-- 直播状态分支：直播中展示主舞台，未开播/已结束展示预告页 -->
    <template v-if="liveStatus === 1">
      <!-- 直播中页面：包含顶部栏、播放器、弹幕区、浮动商品和底部操作栏 -->
      <view class="live-stage-page">
        <!-- 顶部信息栏：返回、主播资料、在线用户入口、分享 -->
        <view class="live-topbar">
          <view class="live-topbar__content">
            <view class="live-topbar__left">
              <view class="top-icon-btn" @click="goBack">
                <text class="iconfont icon-close top-icon-btn__icon"></text>
              </view>
              <view class="host-chip">
                <image v-if="anchorAvatar" class="host-chip__avatar" :src="anchorAvatar" mode="aspectFill" />
                <view v-else class="host-chip__avatar host-chip__avatar--fallback">
                  <text>{{ anchorInitial }}</text>
                </view>
                <view class="host-chip__body">
                  <text class="host-chip__name">{{ anchorName }}</text>
                  <text class="host-chip__meta">{{ likeCountText }}</text>
                </view>
                <view
                  class="host-chip__follow"
                  :class="{ 'host-chip__follow--active': isAnchorFollowed, 'host-chip__follow--loading': anchorFollowLoading }"
                  @click.stop="handleFollowAction"
                >
                  <text class="iconfont host-chip__follow-icon" :class="anchorFollowIconClass"></text>
                </view>
              </view>
            </view>
            <view class="live-topbar__right">
              <view class="viewer-chip" @click="openSheet('rank')">
                <view class="viewer-chip__stack">
                  <view
                    v-for="viewer in topViewerItems"
                    :key="viewer.key"
                    class="viewer-chip__avatar"
                    :class="viewer.avatar ? 'viewer-chip__avatar--image' : ''"
                  >
                    <image v-if="viewer.avatar" class="viewer-chip__avatar-image" :src="viewer.avatar" mode="aspectFill" />
                    <text v-if="!viewer.avatar" class="viewer-chip__label">{{ viewer.label }}</text>
                  </view>
                </view>
                <text class="viewer-chip__count">{{ topViewerMoreText }}</text>
              </view>
              <!-- #ifdef MP-WEIXIN -->
              <button class="top-icon-btn top-icon-btn--share-button" open-type="share">
                <text class="iconfont icon-fenxiang top-icon-btn__icon top-icon-btn__icon--share"></text>
              </button>
              <!-- #endif -->
              <!-- #ifndef MP-WEIXIN -->
              <view class="top-icon-btn" @click="handleShareAction">
                <text class="iconfont icon-fenxiang top-icon-btn__icon top-icon-btn__icon--share"></text>
              </view>
              <!-- #endif -->
            </view>
          </view>
        </view>

        <!-- 播放器舞台：直播流、加载态、空态、播放报错都在这一层 -->
        <view class="player-stage">
          <image v-if="coverUrl" class="player-stage__poster" :src="coverUrl" mode="aspectFill" />
          <view class="player-stage__shade"></view>

          <!-- #ifdef H5 -->
          <view v-if="!loading && h5PlayUrl" class="player-stage__video-wrap">
            <video
              ref="flvVideo"
              class="player-stage__video"
              :poster="coverUrl"
              :controls="false"
              :show-center-play-btn="false"
              :show-play-btn="false"
              :show-fullscreen-btn="false"
              :enable-progress-gesture="false"
              :show-progress="false"
              autoplay
              playsinline
              webkit-playsinline
              x5-playsinline
              x5-video-player-type="h5-page"
              controlslist="nodownload nofullscreen noremoteplayback"
              disablepictureinpicture
            ></video>
            <view class="player-stage__video-mask"></view>
          </view>
          <!-- #endif -->

          <!-- #ifndef H5 -->
          <video
            v-if="!loading && miniProgramPlayUrl"
            id="liveRoomVideo"
            class="player-stage__video"
            :src="miniProgramPlayUrl"
            :poster="coverUrl"
            autoplay
            :controls="false"
            :show-center-play-btn="false"
            :show-play-btn="false"
            :show-fullscreen-btn="false"
            :enable-progress-gesture="false"
            :show-progress="false"
            :muted="false"
            object-fit="contain"
            @click="handlePlayerStageTap"
            @error="handleMiniProgramError"
          />
          <!-- #endif -->

          <view v-if="showEmptyState" class="player-state-card">
            <text class="player-state-card__title">{{ emptyStateTitle }}</text>
            <text class="player-state-card__desc">{{ emptyStateDesc }}</text>
            <view class="player-state-card__action" @click="handleRefresh">
              <text>重新刷新</text>
            </view>
          </view>
          <view v-if="playerError" class="player-tip-bar">
            <text class="player-tip-bar__text">{{ playerError }}</text>
          </view>

          <live-like-burst ref="likeBurst" @like="handleLikeBurst" />

        </view>

        <view
          v-if="activeGiftEffect && activeGiftEffect.effectUrl"
          class="gift-effect-layer"
          :class="activeGiftEffect.isSelf ? 'gift-effect-layer--self' : ''"
        >
          <video
            id="liveGiftEffectVideo"
            ref="giftEffectVideo"
            :key="activeGiftEffect.playKey"
            class="gift-effect-layer__video"
            :src="activeGiftEffect.effectUrl"
            autoplay
            :muted="true"
            :controls="false"
            :show-center-play-btn="false"
            :show-play-btn="false"
            :show-fullscreen-btn="false"
            :enable-progress-gesture="false"
            :show-progress="false"
            object-fit="contain"
            @loadedmetadata="handleGiftEffectLoaded"
            @ended="handleGiftEffectEnded"
            @error="handleGiftEffectError"
          />
        </view>

        <view v-if="canRotateLandscape" class="live-rotate-button" @click="handleRotateFullscreen">
          <text class="iconfont icon-xuanzhuanpingmu live-rotate-button__icon"></text>
        </view>

        <!-- 浮动互动层：左侧弹幕消息流，右侧悬浮商品卡 -->
        <view class="live-floating-layer">
          <!-- 弹幕面板：历史消息与实时消息统一渲染到这里 -->
          <view class="danmu-board">
            <view v-if="activeEnterEffect" class="enter-banner">
              <view v-if="activeEnterEffect.levelIcon" class="enter-banner__mark">
                <image class="enter-banner__mark-image" :src="activeEnterEffect.levelIcon" mode="aspectFill" />
                <image v-if="activeEnterEffect.avatar" class="enter-banner__avatar enter-banner__avatar--in-mark" :src="activeEnterEffect.avatar" mode="aspectFill" />
                <view v-else class="enter-banner__avatar enter-banner__avatar--fallback enter-banner__avatar--in-mark">
                  <text>{{ activeEnterEffect.initial }}</text>
                </view>
                <text class="enter-banner__nickname enter-banner__nickname--in-mark">{{ activeEnterEffect.nickname }}</text>
              </view>
              <view v-else class="enter-banner__plain">
                <text v-if="activeEnterEffect.levelName" class="enter-banner__plain-mark">{{ activeEnterEffect.levelName }}</text>
                <image v-if="activeEnterEffect.avatar" class="enter-banner__avatar" :src="activeEnterEffect.avatar" mode="aspectFill" />
                <view v-else class="enter-banner__avatar enter-banner__avatar--fallback">
                  <text>{{ activeEnterEffect.initial }}</text>
                </view>
                <text class="enter-banner__nickname">{{ activeEnterEffect.nickname }}</text>
              </view>
            </view>
            <scroll-view scroll-y class="danmu-board__scroll" :scroll-into-view="scrollIntoView" scroll-with-animation>
              <view v-if="!comments.length && !commentsLoading" class="danmu-board__empty">
                <text>欢迎来到直播间，互动消息会显示在这里</text>
              </view>
              <template v-else>
                <view v-for="item in comments" :key="item.msgKey" class="danmu-item" :class="item.rowClass">
                  <view class="danmu-item__bubble">
                    <template v-if="item.rowType === 'text'">
                      <text class="danmu-item__name">{{ item.nickname }}：</text>
                      <text class="danmu-item__content">{{ item.content }}</text>
                    </template>
                    <template v-else>
                      <text class="danmu-item__sys">{{ item.content }}</text>
                    </template>
                  </view>
                </view>
              </template>
              <view :id="bottomAnchorId" class="danmu-board__anchor"></view>
            </scroll-view>
          </view>

          <!-- 悬浮商品卡：直播中优先展示当前讲解商品，点击进入商品弹层 -->
          <view v-if="showGoodsFloatCard" class="goods-float-card" @click="openSheet('goods')">
            <view class="goods-float-card__close" @click.stop="closeGoodsFloatCard">
              <text class="iconfont icon-close goods-float-card__close-icon"></text>
            </view>
            <view class="goods-float-card__image-wrap">
              <image v-if="currentGoodsCard.image" class="goods-float-card__image" :src="currentGoodsCard.image" mode="aspectFill" />
              <view v-else class="goods-float-card__image goods-float-card__image--fallback">
                <text>{{ currentGoodsCard.initial }}</text>
              </view>
              <view class="goods-float-card__badge">正在讲解</view>
            </view>
            <view class="goods-float-card__body">
              <text class="goods-float-card__title">{{ currentGoodsCard.name }}</text>
              <view class="goods-float-card__footer">
                <text class="goods-float-card__price">{{ currentGoodsCard.price }}</text>
                <view class="goods-float-card__cta">
                  <text class="iconfont icon-right goods-float-card__cta-icon"></text>
                </view>
              </view>
            </view>
          </view>
        </view>

        <!-- 底部操作栏：评论输入、商品入口、礼物入口 -->
        <view class="live-actionbar">
          <view class="live-actionbar__left">
            <view class="comment-box" :class="composerInputDisabled ? 'comment-box--disabled' : ''">
              <input
                v-model="commentInput"
                class="comment-box__input"
                :disabled="composerInputDisabled"
                :placeholder="commentPlaceholderText"
                placeholder-class="comment-box__placeholder"
                confirm-type="send"
                maxlength="60"
                @confirm="handleComposerAction"
              />
            </view>
          </view>
          <view class="live-actionbar__right">
            <view class="circle-action circle-action--primary" @click="openSheet('goods')">
              <text class="iconfont icon-shop circle-action__icon"></text>
            </view>
            <view class="circle-action" @click="openSheet('gift')">
              <text class="iconfont icon-liwuhuodong circle-action__icon"></text>
            </view>
          </view>
        </view>
      </view>
    </template>

    <template v-else>
      <view class="preview-page">
        <!-- 头图预告区：对应 HTML 的 Hero Teaser Section，只保留状态胶囊和标题 -->
        <view class="preview-hero">
          <view class="preview-hero__toolbar">
            <view class="top-icon-btn top-icon-btn--light" @click="goBack">
              <text class="iconfont icon-close top-icon-btn__icon"></text>
            </view>
            <!-- #ifdef MP-WEIXIN -->
            <button class="top-icon-btn top-icon-btn--light top-icon-btn--share-button" open-type="share">
              <text class="iconfont icon-fenxiang top-icon-btn__icon top-icon-btn__icon--share"></text>
            </button>
            <!-- #endif -->
            <!-- #ifndef MP-WEIXIN -->
            <view class="top-icon-btn top-icon-btn--light" @click="handleShareAction">
              <text class="iconfont icon-fenxiang top-icon-btn__icon top-icon-btn__icon--share"></text>
            </view>
            <!-- #endif -->
          </view>
          <view class="preview-hero__content">
            <view v-if="liveStatus === 2" class="preview-hero__badge">
              <text>直播已经结束</text>
            </view>
            <text class="preview-hero__title">{{ roomTitle }}</text>
          </view>
        </view>

        <view
          class="preview-main"
          v-if="previewRecommendRooms.length"
        >
          <view v-if="previewRecommendRooms.length" class="preview-section-head">
            <text class="preview-section-head__title">推荐直播间</text>
            <text class="preview-section-head__sub">共 {{ previewRecommendRooms.length }} 场</text>
          </view>

          <view v-if="previewRecommendRooms.length" class="preview-room-list">
            <view
              v-for="room in previewRecommendRooms"
              :key="room.id"
              class="preview-room-card"
              @click="handlePreviewRoomClick(room)"
            >
              <image v-if="room.cover" class="preview-room-card__image" :src="room.cover" mode="aspectFill" />
              <view v-else class="preview-room-card__image preview-room-card__image--fallback">
                <text>{{ room.initial }}</text>
              </view>
              <view class="preview-room-card__body">
                <text class="preview-room-card__title">{{ room.title }}</text>
                <text class="preview-room-card__meta">{{ room.meta }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </template>

    <!-- 全局弹层遮罩：商品、用户榜、礼物面板共用 -->
    <view
      v-if="activeSheet"
      class="sheet-mask"
      :class="sheetAnimationState === 'enter' ? 'sheet-mask--enter' : ''"
      @click="closeSheet"
      @touchmove.stop.prevent="noop"
    ></view>

    <view
      v-if="giftRechargeVisible"
      class="sheet-mask"
      :class="giftRechargeAnimationState === 'enter' ? 'sheet-mask--enter' : ''"
      @click="closeGiftRecharge"
      @touchmove.stop.prevent="noop"
    ></view>

    <live-goods-sheet
      :visible="activeSheet === 'goods'"
      :animation-state="sheetAnimationState"
      :landscape="pageLandscape"
      :goods-items="goodsItems"
      :loading="goodsLoading"
      :error="goodsError"
      @close="closeSheet"
      @goods-action="handleGoodsItemAction"
      @content-action="handleContentItemAction"
      @buy="handleLiveGoodsBuy"
      @retry="loadGoods"
    />

    <live-rank-sheet
      :visible="activeSheet === 'rank'"
      :animation-state="sheetAnimationState"
      :landscape="pageLandscape"
      :rank-users="rankUsers"
      :online-user-count-text="onlineUserCountText"
      @close="closeSheet"
    />

    <live-gift-sheet
      :visible="activeSheet === 'gift'"
      :animation-state="sheetAnimationState"
      :landscape="pageLandscape"
      :gift-items="giftItems"
      :selected-gift-id="selectedGiftId"
      :gift-balance="giftBalance"
      :count-options="giftCountOptions"
      :logged-in="loggedIn"
      :sending="giftSending"
      @rule="handleGiftRuleAction"
      @select="selectGift"
      @recharge="handleGiftRecharge"
      @count-change="handleGiftCountChange"
      @send="handleGiftSend"
    />

    <live-gift-recharge-sheet
      :visible="giftRechargeVisible"
      :animation-state="giftRechargeAnimationState"
      :landscape="pageLandscape"
      :recharge-options="giftRechargeOptions"
      :selected-option-key="giftRechargeSelectedOptionKey"
      :coin-balance="giftBalance"
      :exchange-rate="giftRechargeExchangeRate"
      :payable-amount="giftRechargePayableAmount"
      :custom-coin-amount="giftRechargeCustomCoinAmount"
      :recharge-methods="giftRechargeMethods"
      :recharge-pay-method="giftRechargePayMethod"
      :recharge-balance="giftRechargeBalance"
      :recharge-submitting="giftRechargeSubmitting"
      :recharge-loading="giftRechargeLoading"
      :can-submit-recharge="canSubmitGiftRecharge"
      @close="closeGiftRecharge"
      @amount-select="handleGiftRechargeOptionSelect"
      @custom-coin-input="handleGiftRechargeCustomCoinInput"
      @pay-method-change="handleGiftRechargePayMethodChange"
      @submit="submitGiftRecharge"
    />

  </view>
</template>

<script setup>
import {
  buyNowLiveRoomGoods,
  getLiveCoinRechargeOptions,
  getLiveGiftList,
  getLiveRoomComments,
  getLiveRoomDetail,
  getLiveRoomGoods,
  getLiveRoomUsers,
  sendLiveRoomGift,
  sendLiveRoomLike,
  toggleLiveAnchorFollow
} from '@/api/live'
import { createRechargeOrder, getRechargeDetail, getRechargeOptions } from '@/api/balance'
import { getPaymentMethods } from '@/api/payment'
import { getUserInfo, isLoggedIn, navigateToLogin } from '@/utils/auth'
import { invokePayment } from '@/utils/payment'
import { PLATFORM_SCENES, getPlatformScene } from '@/utils/platform'
import { createSocketClient } from '@/utils/socket'
import { normalizePublicErrorMessage } from '@/utils/error'
import { configWechatJsSdk } from '@/utils/wechat_js_sdk'
import { getRuntimeConfig, resolveAssetUrl as resolveConfigAssetUrl } from '@/utils/runtime_config'
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import {
  onHide,
  onLoad,
  onPullDownRefresh,
  onResize,
  onShareAppMessage,
  onShareTimeline,
  onShow,
  onUnload
} from '@dcloudio/uni-app'
// #ifdef H5
import flvjs from 'flv.js'
// #endif
import LiveGoodsSheet from './LiveGoodsSheet.vue'
import LiveRankSheet from './LiveRankSheet.vue'
import LiveGiftSheet from './LiveGiftSheet.vue'
import LiveGiftRechargeSheet from './LiveGiftRechargeSheet.vue'
import LiveLikeBurst from './LiveLikeBurst.vue'
import {
  formatGiftItem,
  formatGoodsItem,
  formatRankUser,
  getDanmuItemClassName,
  normalizeRankUserItem
} from './liveRoomFormatters'

// 互动区保留的最大消息数，避免弹幕列表无限增长。
const MAX_COMMENT_COUNT = 80
// 首次进入页面时拉取的历史评论数量。
const HISTORY_LIMIT = 30
// 底部弹层开关动画时长，需与 SCSS 中 transition 保持一致。
const SHEET_ANIMATION_DURATION = 280

// 点赞请求聚合配置：立即播动效，接口按时间和阈值批量上报。
const LIKE_FLUSH_DELAY = 3000
const LIKE_FLUSH_THRESHOLD = 20
const LIKE_REQUEST_MAX_COUNT = 50

// 礼物数量快捷选项。
const GIFT_COUNT_OPTIONS = [1, 5, 10, 20, 66]
// 直播金币充值固定展示两排三列，最后一项为自定义金币数量。
const LIVE_COIN_CUSTOM_OPTION_KEY = 'custom'
const LIVE_COIN_FIXED_OPTION_LIMIT = 5
// 播放器默认音量：进入直播间默认满音量，不做静音播放。
const DEFAULT_PLAYER_VOLUME = 1
// 入场提示是轻量弹窗，避免长时间遮挡直播互动区。
const ENTER_EFFECT_DURATION = 1800
// WebM 元数据或播放事件异常时的最大等待时间。
const GIFT_EFFECT_FALLBACK_DURATION = 5000

// 基础房间与播放状态。
const roomId = ref(0)
const roomInfo = ref({})
const roomStats = ref({})
const viewerItems = ref([])
const goodsList = ref([])
const goodsLoading = ref(false)
const goodsError = ref('')
const giftList = ref([])
const loading = ref(true)
const commentsLoading = ref(true)
const giftSending = ref(false)
const playerLoading = ref(false)
const playerError = ref('')
const flvPlayer = ref(null)
const pageLandscape = ref(false)

// 实时互动通道状态。
const socketClient = ref(null)
const socketUnsubscribes = ref([])
const socketStatus = ref('idle')
const socketRoomId = ref(0)
const socketLastError = ref('')
const loggedIn = ref(false)

// 弹幕输入与入场标识状态。
const comments = ref([])
const socketRankUsers = ref([])
const commentInput = ref('')
const commentSending = ref(false)
const enterEffectQueue = ref([])
const activeEnterEffect = ref(null)
const enterEffectTimer = ref(null)
const scrollIntoView = ref('')
const scrollAnchorVersion = ref(0)

// 底部弹层与礼物面板状态。
const activeSheet = ref('')
const sheetAnimationState = ref('')
const sheetAnimationTimer = ref(null)
const selectedGiftId = ref('')
const giftCount = ref(1)
const giftBalance = ref('')
const giftRechargeVisible = ref(false)
const giftRechargeAnimationState = ref('')
const giftRechargeAnimationTimer = ref(null)
const giftRechargeOptions = ref([])
const giftRechargeSelectedOptionKey = ref('')
const giftRechargeCoinAmount = ref('')
const giftRechargeCustomCoinAmount = ref('')
const giftRechargeExchangeRate = ref(10)
const giftRechargeMethods = ref([])
const giftRechargePayMethod = ref('')
const giftRechargeBalance = ref(0)
const giftRechargeSubmitting = ref(false)
const giftRechargeLoading = ref(false)
const giftEffectQueue = ref([])
const activeGiftEffect = ref(null)
const giftEffectTimer = ref(null)
const giftEffectHandledIds = ref({})
const scene = ref(PLATFORM_SCENES.H5)
const goodsFloatCardClosed = ref(false)
const anchorFollowLoading = ref(false)

// 点赞聚合上报状态。
const pendingLikeCount = ref(0)
const likeFlushTimer = ref(null)
const likeSending = ref(false)

// 播放器与点赞组件模板引用。
const flvVideo = ref(null)
const giftEffectVideo = ref(null)
const likeBurst = ref(null)

// 页面主状态：0 未开播，1 直播中，2 已结束。
const liveStatus = computed(() => {
  const status = Number(roomInfo.value.status || 0)
  return Number.isNaN(status) ? 0 : status
})
// 头部、播放器空态、预告页共用的基础展示信息。
const roomTitle = computed(() => {
  return roomInfo.value.title || ''
})
const coverUrl = computed(() => {
  return resolveAssetUrl(roomInfo.value.cover_url || '')
})
const shareImageUrl = computed(() => {
  return resolveAssetUrl(
    roomInfo.value.share_image_url ||
    roomInfo.value.share_image ||
    roomInfo.value.share_img ||
    roomInfo.value.poster_url ||
    roomInfo.value.cover_url ||
    roomInfo.value.banner_url ||
    ''
  )
})
const liveSharePath = computed(() => {
  const roomIdValue = roomId.value || roomInfo.value.id || 0
  return `/pages/lives/room/index?id=${encodeURIComponent(String(roomIdValue))}`
})
const liveShareLink = computed(() => {
  // #ifdef H5
  if (typeof window !== 'undefined' && window.location && window.location.href) {
    const href = String(window.location.href || '')
    const hashIndex = href.indexOf('#/')
    if (hashIndex >= 0) {
      return `${href.slice(0, hashIndex)}#${liveSharePath.value}`
    }

    try {
      const url = new URL(href)
      url.search = `?id=${encodeURIComponent(String(roomId.value || roomInfo.value.id || 0))}`
      url.hash = ''
      return url.toString()
    } catch (error) {
      const base = href.split('?')[0].split('#')[0]
      return `${base}?id=${encodeURIComponent(String(roomId.value || roomInfo.value.id || 0))}`
    }
  }
  // #endif

  const runtime = getRuntimeConfig()
  const origin = String(runtime.origin || '').replace(/\/+$/, '')
  return origin ? `${origin}${liveSharePath.value}` : liveSharePath.value
})
const liveShareData = computed(() => {
  const title = String(roomInfo.value.share_title || roomTitle.value || '直播间').trim() || '直播间'
  const anchorPrefix = anchorName.value ? `${anchorName.value}正在直播` : '直播正在进行'
  const desc = String(roomInfo.value.share_desc || roomInfo.value.description || roomInfo.value.notice || `${anchorPrefix}，快来一起观看。`).trim()
  return {
    title,
    desc,
    path: liveSharePath.value,
    link: liveShareLink.value,
    imageUrl: shareImageUrl.value
  }
})
const anchorAvatar = computed(() => {
  const anchor = roomInfo.value.anchor && typeof roomInfo.value.anchor === 'object' ? roomInfo.value.anchor : {}
  return resolveAssetUrl(anchor.avatar || '')
})
const anchorName = computed(() => {
  const anchor = roomInfo.value.anchor && typeof roomInfo.value.anchor === 'object' ? roomInfo.value.anchor : {}
  return anchor.name || ''
})
const anchorInitial = computed(() => {
  return String(anchorName.value || '官').slice(0, 1)
})
const anchorId = computed(() => {
  const anchor = roomInfo.value.anchor && typeof roomInfo.value.anchor === 'object' ? roomInfo.value.anchor : {}
  return Number(anchor.id || roomInfo.value.live_anchor_id || roomInfo.value.anchor_id || 0)
})
const isAnchorFollowed = computed(() => {
  const anchor = roomInfo.value.anchor && typeof roomInfo.value.anchor === 'object' ? roomInfo.value.anchor : {}
  return Boolean(
    anchor.is_followed ||
    roomInfo.value.is_followed ||
    Number(anchor.follow_status || roomInfo.value.follow_status || 0) === 1
  )
})
const anchorFollowIconClass = computed(() => {
  return isAnchorFollowed.value ? 'icon-check' : 'icon-add'
})
const watchCountValue = computed(() => {
  return Number(roomStats.value.online_count || 0)
})
const watchCountText = computed(() => {
  return watchCountValue.value > 0 ? `${formatCount(watchCountValue.value)} 观看` : '热度上升中'
})
const likeCountValue = computed(() => {
  return Number(roomStats.value.like_count || 0)
})
const likeCountText = computed(() => {
  return `${formatCount(likeCountValue.value)} 点赞`
})
const topViewerItems = computed(() => {
  const list = viewerItems.value.slice(0, 2).map((item) => {
    const nickname = String(item.nickname || '')
    return {
      key: item.user_id || item.avatar || nickname,
      avatar: resolveAssetUrl(item.avatar || ''),
      label: nickname.slice(0, 1) || '观'
    }
  })
  if (activeEnterEffect.value) {
    list.unshift({
      key: activeEnterEffect.value.eventId || activeEnterEffect.value.avatar || activeEnterEffect.value.nickname,
      avatar: resolveAssetUrl(activeEnterEffect.value.avatar || ''),
      label: String(activeEnterEffect.value.nickname || '热').slice(0, 1)
    })
  }
  return list.slice(0, 2)
})
const topViewerMoreText = computed(() => {
  if (watchCountValue.value >= 10) return '10+'
  return watchCountValue.value > 0 ? `${watchCountValue.value}` : '0'
})
// 商品相关：直播中浮卡和商品弹层都依赖这一组数据。
const goodsItems = computed(() => {
  return goodsList.value.map((item) => formatGoodsItem(item, resolveLiveAssetUrl.value)).filter(Boolean)
})
const currentGoodsCard = computed(() => {
  return goodsItems.value.find((item) => item.isExplaining) || null
})
const showGoodsFloatCard = computed(() => {
  return !!currentGoodsCard.value && activeSheet.value !== 'goods' && !goodsFloatCardClosed.value
})
const canRotateLandscape = computed(() => {
  return liveStatus.value === 1 && Number(roomInfo.value.allow_landscape || 0) === 1
})
const previewRecommendRooms = computed(() => {
  return []
})
const onlineUserCountText = computed(() => {
  return watchCountValue.value > 0 ? formatCount(watchCountValue.value) : '0'
})
const rankUsers = computed(() => {
  return socketRankUsers.value.length ? socketRankUsers.value : viewerItems.value.map((item) => formatRankUser(item, resolveLiveAssetUrl.value)).filter(Boolean)
})
const giftItems = computed(() => {
  return giftList.value.map((item) => formatGiftItem(item, resolveLiveAssetUrl.value)).filter(Boolean)
})
const selectedGift = computed(() => {
  return giftItems.value.find((item) => item.id === selectedGiftId.value) || null
})
const giftCountOptions = computed(() => {
  return GIFT_COUNT_OPTIONS
})
const selectedGiftRechargeOption = computed(() => {
  return giftRechargeOptions.value.find((item) => {
    return String(item.key) === String(giftRechargeSelectedOptionKey.value)
  }) || null
})
const isCustomGiftRechargeSelected = computed(() => {
  return selectedGiftRechargeOption.value && selectedGiftRechargeOption.value.isCustom
})
const giftRechargeCoinAmountValue = computed(() => {
  const amount = Number.parseInt(String(giftRechargeCoinAmount.value || ''), 10)
  return Number.isFinite(amount) && amount > 0 ? amount : 0
})
const giftRechargePayableAmount = computed(() => {
  if (giftRechargeCoinAmountValue.value <= 0) return 0
  return parseGiftRechargeAmount(giftRechargeCoinAmountValue.value / Number(giftRechargeExchangeRate.value || 10))
})
const canSubmitGiftRecharge = computed(() => {
  if (isCustomGiftRechargeSelected.value && !isPositiveInteger(giftRechargeCustomCoinAmount.value)) {
    return false
  }
  return giftRechargeCoinAmountValue.value > 0 && giftRechargePayableAmount.value > 0 && !!giftRechargePayMethod.value && !giftRechargeSubmitting.value && !giftRechargeLoading.value
})
// 播放器空态与评论输入态依赖的文案。
const emptyStateTitle = computed(() => {
  return liveStatus.value === 1 ? '当前暂无可播放直播流' : '直播内容暂不可用'
})
const emptyStateDesc = computed(() => {
  if (liveStatus.value === 1) {
    return '主播可能尚未推流，或直播流还在同步中，可稍后刷新重试。'
  }
  return '请稍后刷新页面，获取最新直播状态。'
})
const h5PlayUrl = computed(() => {
  return String(roomInfo.value.live_play_url || roomInfo.value.external_play_url || roomInfo.value.pull_flv || '').trim()
})
const miniProgramPlayUrl = computed(() => {
  return String(roomInfo.value.pull_m3u8 || roomInfo.value.live_play_url || roomInfo.value.external_play_url || '').trim()
})
const isExternalStream = computed(() => {
  return String(roomInfo.value.stream_type || '') === 'external'
})
const isH5FlvPlayUrl = computed(() => {
  const url = String(h5PlayUrl.value || '').split('?')[0]
  return /\.flv$/i.test(url)
})
const showEmptyState = computed(() => {
  let playUrl = ''
  // #ifdef H5
  playUrl = h5PlayUrl.value
  // #endif
  // #ifndef H5
  playUrl = miniProgramPlayUrl.value
  // #endif
  return !loading.value && !playUrl
})
const commentPlaceholderText = computed(() => {
  if (!loggedIn.value) return '说点什么...'
  if (liveStatus.value === 2) return '直播已结束'
  if (socketStatus.value === 'error' || socketStatus.value === 'exhausted') return '互动通道暂不可用'
  if (socketStatus.value !== 'connected') return '互动通道连接中...'
  return '说点什么...'
})
const composerInputDisabled = computed(() => {
  return !loggedIn.value || liveStatus.value !== 1 || socketStatus.value !== 'connected'
})
const bottomAnchorId = computed(() => {
  return `chat-bottom-anchor-${scrollAnchorVersion.value}`
})
const resolveLiveAssetUrl = computed(() => {
  return (url) => resolveAssetUrl(url)
})
// 页面生命周期：进入时读取路由参数并初始化页面。
onLoad((options = {}) => {
  scene.value = getPlatformScene()
  roomId.value = Number(options.id || 0)
  if (roomId.value <= 0) {
    uni.showToast({ title: '直播间 ID 无效', icon: 'none' })
    setTimeout(() => {
      goBack()
    }, 1200)
    return
  }
  initMiniProgramShareMenu()
  loggedIn.value = isLoggedIn()
  initializePage().then(() => {
    const returnOrderSn = String(options.order_sn || options.orderSn || '').trim()
    if (returnOrderSn && loggedIn.value) {
      checkGiftRechargeReturn(returnOrderSn)
    }
  })
})
onShow(() => {
  const nextLoggedIn = isLoggedIn()
  loggedIn.value = nextLoggedIn
  if (loggedIn.value) {
    if (roomId.value) {
      connectRoomSocket()
      loadGiftRechargeOptions()
    }
    configureWechatLiveShare()
    return
  }
  configureWechatLiveShare()
  destroySocket()
})
onShareAppMessage(() => {
  const share = liveShareData.value
  return {
    title: share.title,
    path: share.path,
    imageUrl: share.imageUrl
  }
})
onShareTimeline(() => {
  const share = liveShareData.value
  return {
    title: share.title,
    query: `id=${encodeURIComponent(String(roomId.value || roomInfo.value.id || 0))}`,
    imageUrl: share.imageUrl
  }
})
onPullDownRefresh(() => {
  handleRefresh()
})
onHide(() => {
  clearPlaybackTasks()
})
onResize((event = {}) => {
  const size = event.size || {}
  const width = Number(size.windowWidth || 0)
  const height = Number(size.windowHeight || 0)
  if (width && height) {
    pageLandscape.value = width > height
  }
})
onUnload(() => {
  flushPendingLikes({ force: true })
  // #ifndef H5
  resetPageOrientation()
  // #endif
  destroyFlvPlayer()
  destroySocket()
  clearPlaybackTasks()
  clearSheetAnimationTimer()
  clearGiftRechargeAnimationTimer()
  clearLikeFlushTimer()
})
onBeforeUnmount(() => {
  flushPendingLikes({ force: true })
  // #ifndef H5
  resetPageOrientation()
  // #endif
  destroyFlvPlayer()
  destroySocket()
  clearPlaybackTasks()
  clearSheetAnimationTimer()
  clearGiftRechargeAnimationTimer()
  clearLikeFlushTimer()
})
// 占位空方法：用于阻止遮罩和弹层滚动穿透。
function noop() {}

function clearSheetAnimationTimer() {
  if (!sheetAnimationTimer.value) return
  clearTimeout(sheetAnimationTimer.value)
  sheetAnimationTimer.value = null
}

function clearGiftRechargeAnimationTimer() {
  if (!giftRechargeAnimationTimer.value) return
  clearTimeout(giftRechargeAnimationTimer.value)
  giftRechargeAnimationTimer.value = null
}

function hideActiveSheetImmediately() {
  clearSheetAnimationTimer()
  sheetAnimationState.value = ''
  activeSheet.value = ''
}

function openGiftRechargePanel() {
  clearGiftRechargeAnimationTimer()
  if (!giftRechargeCoinAmount.value && selectedGiftRechargeOption.value) {
    handleGiftRechargeOptionSelect(selectedGiftRechargeOption.value)
  }
  giftRechargeVisible.value = true
  giftRechargeAnimationState.value = ''
  nextTick(() => {
    if (!giftRechargeVisible.value) return
    giftRechargeAnimationState.value = 'enter'
  })
}

function normalizeRoomDetailPayload(data = {}) {
  if (!data || typeof data !== 'object') {
    return {
      room: {},
      stats: {}
    }
  }
  const room = data.room && typeof data.room === 'object' ? data.room : data
  const stats = data.stats && typeof data.stats === 'object'
    ? data.stats
    : {
        online_count: data.online_count || data.view_count || 0,
        view_count: data.view_count || 0
      }
  return {
    room,
    stats
  }
}

function applyRoomInfo(info = {}) {
  roomInfo.value = {
    ...roomInfo.value,
    ...info
  }
}

function applyRoomStats(stats = {}) {
  roomStats.value = {
    ...roomStats.value,
    ...stats
  }
}

function parseDateValue(value) {
  const text = String(value || '').trim()
  if (!text) return null
  const parsed = new Date(text.replace(/-/g, '/'))
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function formatCount(value) {
  const count = Number(value || 0)
  if (count >= 10000) {
    return `${(count / 10000).toFixed(count >= 100000 ? 0 : 1)}w`
  }
  return `${count}`
}

// 页面启动入口：首屏 HTTP 数据并行加载，实时变化交给 Socket 事件驱动。
async function initializePage() {
  await Promise.all([
    loadRoomDetail(),
    loadRoomUsers(),
    loadGoods(),
    loadGifts(),
    loadComments()
  ])
  if (loggedIn.value) {
    connectRoomSocket()
  }
}

async function handleRefresh() {
  const tasks = [
    loadRoomDetail(),
    loadRoomUsers(),
    loadGoods(),
    loadGifts(),
    loadComments()
  ]
  if (loggedIn.value) {
    tasks.push(loadGiftRechargeOptions())
  }
  await Promise.all(tasks)
  if (loggedIn.value) {
    connectRoomSocket(true)
  } else {
    destroySocket()
  }
  uni.stopPullDownRefresh()
}

function goBack() {
  clearPlaybackTasks()
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack({
      fail: () => {
        uni.switchTab({ url: '/pages/index/index' })
      }
    })
    return
  }
  uni.switchTab({ url: '/pages/index/index' })
}

function goLogin() {
  clearPlaybackTasks()
  navigateToLogin({ redirect: `/pages/lives/room/index?id=${roomId.value}` })
}

function closeGoodsFloatCard() {
  goodsFloatCardClosed.value = true
}

function openSheet(type) {
  if (!type) return
  if (giftRechargeVisible.value) {
    closeGiftRecharge(true)
  }
  if (type === 'goods') {
    loadGoods()
  }
  if (type === 'rank') {
    loadRoomUsers()
  }
  if (type === 'gift') {
    loadGifts()
  }
  clearSheetAnimationTimer()
  activeSheet.value = type
  sheetAnimationState.value = ''
  nextTick(() => {
    if (activeSheet.value !== type) return
    sheetAnimationState.value = 'enter'
  })
}

function closeSheet() {
  if (!activeSheet.value) return
  clearSheetAnimationTimer()
  sheetAnimationState.value = ''
  sheetAnimationTimer.value = setTimeout(() => {
    activeSheet.value = ''
    sheetAnimationTimer.value = null
  }, SHEET_ANIMATION_DURATION)
}

function closeGiftRecharge(immediate = false) {
  if (!giftRechargeVisible.value) return
  clearGiftRechargeAnimationTimer()
  giftRechargeAnimationState.value = ''
  if (immediate) {
    giftRechargeVisible.value = false
    return
  }
  giftRechargeAnimationTimer.value = setTimeout(() => {
    giftRechargeVisible.value = false
    giftRechargeAnimationTimer.value = null
  }, SHEET_ANIMATION_DURATION)
}

async function handleFollowAction() {
  if (!loggedIn.value) {
    goLogin()
    return
  }
  if (anchorFollowLoading.value) {
    return
  }
  const currentAnchorId = anchorId.value
  if (!currentAnchorId) {
    uni.showToast({ title: '主播信息不存在', icon: 'none' })
    return
  }

  anchorFollowLoading.value = true
  try {
    const res = await toggleLiveAnchorFollow({ anchor_id: currentAnchorId }, { showError: false })
    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({ title: (res && res.msg) || '关注失败，请稍后重试', icon: 'none' })
      return
    }

    const isFollowed = Boolean(res.data.is_followed || Number(res.data.status || 0) === 1)
    applyAnchorFollowState(isFollowed)
    uni.showToast({ title: isFollowed ? '已关注直播间' : '已取消关注', icon: 'none' })
  } catch (error) {
    uni.showToast({ title: '关注失败，请稍后重试', icon: 'none' })
  } finally {
    anchorFollowLoading.value = false
  }
}

function applyAnchorFollowState(isFollowed) {
  const followStatus = isFollowed ? 1 : 0
  const anchor = roomInfo.value.anchor && typeof roomInfo.value.anchor === 'object' ? roomInfo.value.anchor : {}
  roomInfo.value = {
    ...roomInfo.value,
    is_followed: isFollowed,
    follow_status: followStatus,
    anchor: {
      ...anchor,
      is_followed: isFollowed,
      follow_status: followStatus
    }
  }
}

function handleShareAction() {
  if (scene.value === PLATFORM_SCENES.WECHAT_H5) {
    configureWechatLiveShare()
    uni.showToast({ title: '请点击右上角菜单分享', icon: 'none' })
    return
  }

  const share = liveShareData.value
  uni.setClipboardData({
    data: share.link,
    success: () => {
      uni.showToast({ title: '直播链接已复制', icon: 'none' })
    }
  })
}

function initMiniProgramShareMenu() {
  // #ifdef MP-WEIXIN
  uni.showShareMenu({
    withShareTicket: true,
    menus: ['shareAppMessage', 'shareTimeline']
  })
  // #endif
}

async function configureWechatLiveShare() {
  if (scene.value !== PLATFORM_SCENES.WECHAT_H5) {
    return
  }

  // #ifdef H5
  const share = liveShareData.value
  try {
    const result = await configWechatJsSdk({
      jsApiList: [
        'updateAppMessageShareData',
        'updateTimelineShareData',
        'onMenuShareAppMessage',
        'onMenuShareTimeline'
      ],
      showError: false
    })
    if (!result.configured || typeof window === 'undefined') {
      return
    }

    const wx = window.wx || null
    if (!wx) {
      return
    }

    const appMessageShare = {
      title: share.title,
      desc: share.desc,
      link: share.link,
      imgUrl: share.imageUrl
    }
    const timelineShare = {
      title: share.title,
      link: share.link,
      imgUrl: share.imageUrl
    }

    if (typeof wx.updateAppMessageShareData === 'function') {
      wx.updateAppMessageShareData(appMessageShare)
    } else if (typeof wx.onMenuShareAppMessage === 'function') {
      wx.onMenuShareAppMessage(appMessageShare)
    }

    if (typeof wx.updateTimelineShareData === 'function') {
      wx.updateTimelineShareData(timelineShare)
    } else if (typeof wx.onMenuShareTimeline === 'function') {
      wx.onMenuShareTimeline(timelineShare)
    }
  } catch (error) {
    // 公众号分享配置失败不影响直播间播放和互动。
  }
  // #endif
}

function normalizeMiniProgramPageUrl(url = '') {
  const source = String(url || '').trim()
  if (!source) return ''
  if (/^(https?:)?\/\//i.test(source)) return ''
  const pageUrl = source.startsWith('/') ? source : `/${source}`
  return pageUrl.startsWith('/pages/') ? pageUrl : ''
}

function handleGoodsItemAction(item = {}) {
  if (!item.canOpen || !item.targetUrl) {
    uni.showToast({ title: '当前内容暂不可打开', icon: 'none' })
    return
  }
  // #ifdef MP-WEIXIN
  const miniProgramPageUrl = normalizeMiniProgramPageUrl(item.targetUrl)
  if (!miniProgramPageUrl) {
    uni.showToast({ title: '小程序暂不支持打开该内容', icon: 'none' })
    return
  }
  clearPlaybackTasks()
  uni.navigateTo({
    url: miniProgramPageUrl,
    fail: () => {
      uni.showToast({ title: '页面暂不可打开', icon: 'none' })
    }
  })
  // #endif
  // #ifndef MP-WEIXIN
  clearPlaybackTasks()
  uni.navigateTo({
    url: item.targetUrl,
    fail: () => {
      uni.showToast({ title: '页面暂不可打开', icon: 'none' })
    }
  })
  // #endif
}

async function handleLiveGoodsBuy(item = {}) {
  const liveGoodsId = Number(item.id || 0)
  if (!liveGoodsId) {
    uni.showToast({ title: '直播间商品不存在', icon: 'none' })
    return
  }

  try {
    const res = await buyNowLiveRoomGoods(roomId.value, {
      live_goods_id: liveGoodsId
    }, {
      showError: false
    })

    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({
        title: (res && res.msg) || '获取结算信息失败',
        icon: 'none'
      })
      return
    }

    const cart = res.data.cart && typeof res.data.cart === 'object' ? res.data.cart : {}
    const cartId = Number(cart.cart_id || cart.id || 0)
    if (!cartId) {
      uni.showToast({ title: '未获取到结算商品', icon: 'none' })
      return
    }

    clearPlaybackTasks()
    hideActiveSheetImmediately()
    uni.navigateTo({
      url: `/pages/orders/confirm/index?cart_ids=${encodeURIComponent(String(cartId))}&is_new=1`,
      fail: () => {
        uni.showToast({ title: '订单确认页暂不可打开', icon: 'none' })
      }
    })
  } catch (error) {
    uni.showToast({ title: '获取结算信息失败', icon: 'none' })
  }
}

function handleContentItemAction(item = {}) {
  handleGoodsItemAction({
    ...item,
    name: item.title
  })
}

function handlePreviewRoomClick(room = {}) {
  if (!room.id) return
  clearPlaybackTasks()
  uni.redirectTo({
    url: `/pages/lives/room/index?id=${room.id}`
  })
}

function handleGiftRuleAction() {
  uni.showToast({ title: '暂无礼物规则', icon: 'none' })
}

function handleGiftRecharge() {
  if (!loggedIn.value) {
    goLogin()
    return
  }
  hideActiveSheetImmediately()
  openGiftRechargePanel()
  loadGiftRechargeOptions()
}

function handleGiftRechargeOptionSelect(option = {}) {
  giftRechargeSelectedOptionKey.value = option.key || ''
  if (option.isCustom) {
    giftRechargeCoinAmount.value = giftRechargeCustomCoinAmount.value
    return
  }
  giftRechargeCoinAmount.value = String(option.coinAmount || '')
}

function handleGiftRechargeCustomCoinInput(value = '') {
  const integerPart = String(value || '').split('.')[0]
  const digits = integerPart.replace(/[^\d]/g, '')
  const normalized = digits.replace(/^0+(?=\d)/, '')
  giftRechargeCustomCoinAmount.value = normalized
  giftRechargeSelectedOptionKey.value = LIVE_COIN_CUSTOM_OPTION_KEY
  giftRechargeCoinAmount.value = normalized
}

function handleGiftRechargePayMethodChange(payMethod = '') {
  giftRechargePayMethod.value = String(payMethod || '')
}

async function submitGiftRecharge() {
  if (isCustomGiftRechargeSelected.value && !isPositiveInteger(giftRechargeCustomCoinAmount.value)) {
    uni.showToast({ title: '请输入正整数金币数量', icon: 'none' })
    return
  }
  if (giftRechargeCoinAmountValue.value <= 0) {
    uni.showToast({ title: '请选择充值档位', icon: 'none' })
    return
  }
  if (!canSubmitGiftRecharge.value) return
  const payType = normalizeGiftRechargePayMethod(giftRechargePayMethod.value)
  giftRechargeSubmitting.value = true
  try {
    const payload = {
      order_type: 'live_coin',
      coin_amount: giftRechargeCoinAmountValue.value,
      pay_type: payType,
      return_url: buildGiftRechargeReturnUrl()
    }
    if (payType === 'wechat') {
      payload.trade_type = resolveGiftRechargeTradeType()
    }

    const res = await createRechargeOrder(payload, { showError: false, loginRedirect: true })
    if (!res || res.code !== 200 || !res.data) {
      uni.showToast({ title: (res && res.msg) || '金币充值发起失败', icon: 'none' })
      return
    }

    await handleGiftRechargePayResponse(res.data)
  } catch (error) {
    uni.showToast({ title: extractErrorMessage(error, '金币充值发起失败'), icon: 'none' })
  } finally {
    giftRechargeSubmitting.value = false
  }
}

function selectGift(item = {}) {
  selectedGiftId.value = item.id || ''
}

function getCurrentUserId() {
  const userInfo = getUserInfo()
  return Number(userInfo.uid || userInfo.id || userInfo.user_id || userInfo.userId || 0)
}

function queueGiftEffect(giftMessage = {}) {
  const taskId = String(giftMessage.msgId || '').trim()
  if (!taskId || giftEffectHandledIds.value[taskId]) return

  giftEffectHandledIds.value[taskId] = true
  giftEffectQueue.value.push({
    ...giftMessage,
    playKey: `gift-effect-${taskId}`
  })
  playNextGiftEffect()
}

function playNextGiftEffect() {
  if (activeGiftEffect.value) return

  let task = giftEffectQueue.value.shift()
  while (task && !task.effectUrl) {
    task = giftEffectQueue.value.shift()
  }
  if (!task) return

  activeGiftEffect.value = task
  clearGiftEffectTimer()
  giftEffectTimer.value = setTimeout(() => {
    handleGiftEffectError()
  }, GIFT_EFFECT_FALLBACK_DURATION)
  nextTick(() => {
    startGiftEffectVideo()
  })
}

function startGiftEffectVideo() {
  // #ifdef H5
  const videoRef = giftEffectVideo.value
  const videoElement = videoRef && (videoRef.$el || videoRef)
  if (!videoElement || typeof videoElement.play !== 'function') return
  videoElement.muted = true
  videoElement.playsInline = true
  try {
    const playResult = videoElement.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => {
        handleGiftEffectError()
      })
    }
  } catch (error) {
    handleGiftEffectError()
  }
  // #endif
}

function handleGiftEffectLoaded(event = {}) {
  if (!activeGiftEffect.value) return
  const detail = event && event.detail ? event.detail : {}
  const target = event && event.target ? event.target : null
  const duration = Number(detail.duration || (target && target.duration) || 0)
  const durationMs = duration > 0
    ? Math.min(GIFT_EFFECT_FALLBACK_DURATION, Math.max(1200, Math.ceil(duration * 1000) + 500))
    : GIFT_EFFECT_FALLBACK_DURATION

  clearGiftEffectTimer()
  giftEffectTimer.value = setTimeout(() => {
    finishGiftEffectTask()
  }, durationMs)
  startGiftEffectVideo()
}

function handleGiftEffectEnded() {
  finishGiftEffectTask()
}

function handleGiftEffectError() {
  const task = activeGiftEffect.value
  if (!task) return
  showGiftFallbackNotice(task)
  finishGiftEffectTask()
}

function finishGiftEffectTask() {
  if (!activeGiftEffect.value) return
  clearGiftEffectTimer()
  activeGiftEffect.value = null
  nextTick(() => {
    playNextGiftEffect()
  })
}

function showGiftFallbackNotice(task = {}) {
  const content = String(task.content || '').trim()
  if (!content) return
  uni.showToast({
    title: content,
    icon: 'none',
    duration: 1500
  })
}

function clearGiftEffectTimer() {
  if (!giftEffectTimer.value) return
  clearTimeout(giftEffectTimer.value)
  giftEffectTimer.value = null
}

function handleGiftCountChange(count) {
  giftCount.value = Number(count || 1)
}

async function handleGiftSend(payload = {}) {
  const targetGift = payload.gift || selectedGift.value
  const quantity = Number(payload.quantity || giftCount.value || 1)
  giftCount.value = quantity
  if (targetGift && targetGift.id) {
    selectedGiftId.value = targetGift.id
  }
  if (!loggedIn.value) {
    uni.showToast({ title: '请先登录后赠送', icon: 'none' })
    setTimeout(() => {
      goLogin()
    }, 600)
    return
  }
  if (!targetGift) {
    uni.showToast({ title: '请先选择礼物', icon: 'none' })
    return
  }
  if (giftSending.value) {
    return
  }
  giftSending.value = true
  try {
    const res = await sendLiveRoomGift(roomId.value, {
      gift_id: targetGift.id,
      quantity
    }, { showError: false })
    if (res.code !== 200 || !res.data) {
      uni.showToast({ title: res.msg || '赠送失败', icon: 'none' })
      return
    }
    applyGiftCoinBalance(res.data)
    handleGiftMessageSocketEvent({ data: res.data })
    loadRoomUsers()
    closeSheet()
    uni.showToast({ title: '赠送成功', icon: 'none' })
  } catch (error) {
    uni.showToast({ title: '赠送失败，请稍后重试', icon: 'none' })
  } finally {
    giftSending.value = false
  }
}

// 拉取直播间详情：兼容 data.room 包裹结构和 data 直接返回房间对象两种接口结构。
async function loadRoomDetail() {
  loading.value = true
  playerError.value = ''
  let shouldInitH5Player = false
  // #ifdef H5
  destroyFlvPlayer()
  // #endif
  try {
    const res = await getLiveRoomDetail(roomId.value, {}, { showError: false })
    if (res.code !== 200 || !res.data) {
      playerError.value = '直播间加载失败'
      return
    }
    const detailPayload = normalizeRoomDetailPayload(res.data)
    roomInfo.value = detailPayload.room
    roomStats.value = detailPayload.stats
    uni.setNavigationBarTitle({ title: roomInfo.value.title || '直播间' })
    configureWechatLiveShare()
    // #ifdef H5
    shouldInitH5Player = liveStatus.value === 1
    // #endif
  } catch (error) {
    playerError.value = '直播间加载失败'
  } finally {
    loading.value = false
    // #ifdef H5
    if (shouldInitH5Player) {
      nextTick(() => {
        initH5Player()
      })
    }
    // #endif
  }
}

async function loadRoomUsers() {
  try {
    const res = await getLiveRoomUsers(roomId.value, { page: 1, limit: 50 }, { showError: false })
    if (res.code !== 200 || !res.data) {
      viewerItems.value = []
      return
    }
    viewerItems.value = Array.isArray(res.data.list) ? res.data.list : []
    socketRankUsers.value = []
  } catch (error) {
    viewerItems.value = []
  }
}

async function loadGoods() {
  goodsLoading.value = true
  goodsError.value = ''
  try {
    const res = await getLiveRoomGoods(roomId.value, {}, { showError: false })
    if (res.code !== 200 || !res.data) {
      goodsList.value = []
      goodsError.value = (res && res.msg) || '商品加载失败'
      return
    }
    goodsList.value = Array.isArray(res.data.list) ? res.data.list : []
  } catch (error) {
    goodsList.value = []
    goodsError.value = '商品加载失败'
  } finally {
    goodsLoading.value = false
  }
}

async function loadGifts() {
  try {
    const res = await getLiveGiftList({}, { showError: false })
    if (res.code !== 200 || !res.data) {
      giftList.value = []
      return
    }
    giftList.value = Array.isArray(res.data.list) ? res.data.list : []
    if (!selectedGiftId.value && giftItems.value.length) {
      selectedGiftId.value = giftItems.value[0].id
    }
  } catch (error) {
    giftList.value = []
  }
}

async function loadGiftRechargeOptions() {
  if (!loggedIn.value) return
  giftRechargeLoading.value = true
  try {
    const [coinOptionsRes, paymentRes, rechargeRes] = await Promise.all([
      getLiveCoinRechargeOptions({}, { showError: false }),
      getPaymentMethods({}, { showError: false, loginRedirect: true }),
      getRechargeOptions({}, { showError: false, loginRedirect: false }).catch(() => null)
    ])
    const coinOptionsData = coinOptionsRes && coinOptionsRes.code === 200 && coinOptionsRes.data ? coinOptionsRes.data : {}
    const paymentData = paymentRes && paymentRes.code === 200 && paymentRes.data ? paymentRes.data : {}
    const rechargeData = rechargeRes && rechargeRes.code === 200 && rechargeRes.data ? rechargeRes.data : {}
    giftRechargeBalance.value = Number(paymentData.balance || 0)
    applyGiftCoinBalance(rechargeData)
    const exchangeRateValue = coinOptionsData.exchange_rate || 10
    giftRechargeExchangeRate.value = Math.max(1, Number(exchangeRateValue))
    const rechargeSourceList = Array.isArray(coinOptionsData.list) ? coinOptionsData.list : []
    giftRechargeOptions.value = normalizeGiftRechargeOptions(rechargeSourceList)
    if (!selectedGiftRechargeOption.value && giftRechargeOptions.value.length) {
      handleGiftRechargeOptionSelect(giftRechargeOptions.value[0])
    }
    giftRechargeMethods.value = normalizeGiftRechargeMethods(paymentData.methods || [])
    if (!giftRechargePayMethod.value && giftRechargeMethods.value.length) {
      giftRechargePayMethod.value = mapGiftRechargePayMethodValue(giftRechargeMethods.value[0].pay_type || '')
    }
  } finally {
    giftRechargeLoading.value = false
  }
}

function normalizeGiftRechargeOptions(list = []) {
  const fixedOptions = (Array.isArray(list) ? list : []).map((item, index) => {
    const coinAmount = Number.parseInt(String(item.coin_amount || item.off_amt || item.amount || item.value || 0), 10)
    const payAmount = parseGiftRechargeAmount(item.pay_amount || item.price || item.amount || 0)
    if (!Number.isFinite(coinAmount) || coinAmount <= 0 || !Number.isFinite(payAmount) || payAmount <= 0) {
      return null
    }
    const optionKey = String(item.id || item.combination_data_id || `${coinAmount}-${payAmount}-${index}`)
    return {
      key: optionKey,
      id: Number(item.id || item.combination_data_id || 0),
      coinAmount,
      payAmount,
      coinAmountText: String(coinAmount),
      payAmountText: formatGiftRechargeMoney(payAmount),
      tag: item.tag || item.label || item.title || ''
    }
  }).filter(Boolean).slice(0, LIVE_COIN_FIXED_OPTION_LIMIT)

  fixedOptions.push({
    key: LIVE_COIN_CUSTOM_OPTION_KEY,
    id: 0,
    isCustom: true,
    coinAmount: 0,
    payAmount: 0,
    coinAmountText: '',
    payAmountText: ''
  })

  return fixedOptions
}

function parseGiftRechargeAmount(value = 0) {
  const normalized = String(value === undefined || value === null ? '' : value).replace(/,/g, '').replace('¥', '').trim()
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? Math.max(parsed, 0) : 0
}

function isPositiveInteger(value = '') {
  return /^[1-9]\d*$/.test(String(value || ''))
}

function formatGiftRechargeMoney(value = 0) {
  const amount = parseGiftRechargeAmount(value)
  if (Math.round(amount) === amount) {
    return `${amount}`
  }
  return amount.toFixed(2)
}

function normalizeGiftRechargeMethods(list = []) {
  const allowedTypes = ['yue', 'wechat', 'alipay']
  const source = Array.isArray(list) ? list : []
  const normalized = source
    .map((item) => {
      const payType = normalizeGiftRechargePayMethod(item.pay_type || item.key || '')
      if (!allowedTypes.includes(payType)) {
        return null
      }
      return {
        ...item,
        pay_type: payType,
        title: item.title || item.name || getGiftRechargePayMethodTitle(payType)
      }
    })
    .filter(Boolean)

  if (!normalized.some((item) => item.pay_type === 'yue')) {
    normalized.unshift({
      pay_type: 'yue',
      title: '余额支付',
      subtitle: '使用账户余额支付'
    })
  }

  return normalized.filter((item, index, array) => {
    return array.findIndex((method) => method.pay_type === item.pay_type) === index
  })
}

async function handleGiftRechargePayResponse(payload = {}) {
  const orderSn = String(payload.order_sn || '')
  const statusCode = Number(payload.status || 0)
  const statusText = String(payload.status_text || '')
  if (statusCode === 1 || statusText === '已支付') {
    await handleGiftRechargeSuccess()
    return
  }

  const paymentConfig = payload.config && typeof payload.config === 'object' ? payload.config : null
  if (!paymentConfig) {
    uni.showToast({ title: statusText || '支付参数获取失败', icon: 'none' })
    return
  }

  await invokePayment({
    payType: payload.pay_type || giftRechargePayMethod.value,
    config: paymentConfig,
    scene: scene.value,
    returnUrl: buildGiftRechargeReturnUrl(orderSn)
  })

  const paid = await pollGiftRechargeStatus(orderSn)
  if (paid) {
    await handleGiftRechargeSuccess()
    return
  }

  uni.showToast({ title: '支付已提交，请稍后查看金币', icon: 'none' })
}

function normalizeGiftRechargePayMethod(payMethod = '') {
  const type = String(payMethod || '').toLowerCase()
  if (type === 'balance' || type === 'yue') return 'yue'
  if (type === 'wechat' || type === 'alipay') return type
  return type
}

function mapGiftRechargePayMethodValue(payMethod = '') {
  return normalizeGiftRechargePayMethod(payMethod) === 'yue'
    ? 'balance'
    : normalizeGiftRechargePayMethod(payMethod)
}

function getGiftRechargePayMethodTitle(payMethod = '') {
  if (payMethod === 'yue') return '余额支付'
  if (payMethod === 'wechat') return '微信支付'
  if (payMethod === 'alipay') return '支付宝'
  return '支付'
}

async function pollGiftRechargeStatus(orderSn = '') {
  if (!orderSn) return false
  for (let index = 0; index < 3; index += 1) {
    await wait(800)
    const res = await getRechargeDetail({ order_sn: orderSn }, { showError: false, loginRedirect: false })
    if (res && res.code === 200 && res.data && Number(res.data.status || 0) === 1) {
      return true
    }
  }
  return false
}

async function handleGiftRechargeSuccess() {
  uni.showToast({ title: '金币充值成功', icon: 'success' })
  giftRechargeCoinAmount.value = ''
  giftRechargeCustomCoinAmount.value = ''
  giftRechargeSelectedOptionKey.value = ''
  giftRechargeVisible.value = false
  await loadGiftRechargeOptions()
}

async function checkGiftRechargeReturn(orderSn = '') {
  const paid = await pollGiftRechargeStatus(orderSn)
  if (paid) {
    await handleGiftRechargeSuccess()
  }
}

function applyGiftCoinBalance(data = {}) {
  if (data.coin_balance !== undefined) {
    giftBalance.value = Number(data.coin_balance || 0)
    return
  }
  if (data.coins !== undefined) {
    giftBalance.value = Number(data.coins || 0)
    return
  }
  if (data.balance !== undefined) {
    giftBalance.value = Number(data.balance || 0)
  }
}

function resolveGiftRechargeTradeType() {
  if (scene.value === PLATFORM_SCENES.MINIAPP) {
    return 'miniapp'
  }
  if (scene.value === PLATFORM_SCENES.H5) {
    return 'h5'
  }
  return 'official_account'
}

function buildGiftRechargeReturnUrl(orderSn = '') {
  // #ifdef H5
  if (typeof window === 'undefined' || !window.location) {
    return ''
  }
  const baseUrl = `${window.location.origin}${window.location.pathname}`
  const currentPages = getCurrentPages()
  const currentPage = currentPages.length ? currentPages[currentPages.length - 1] : null
  const route = currentPage && currentPage.route ? `/${currentPage.route}` : '/pages/lives/room/index'
  const params = [`id=${encodeURIComponent(roomId.value)}`]
  if (orderSn) {
    params.push(`order_sn=${encodeURIComponent(orderSn)}`)
  }
  return `${baseUrl}#${route}?${params.join('&')}`
  // #endif

  // #ifndef H5
  return ''
  // #endif
}

function wait(ms = 300) {
  return new Promise((resolve) => {
    setTimeout(() => resolve(), ms)
  })
}

function extractErrorMessage(error, fallback = '操作失败') {
  if (!error) {
    return fallback
  }
  if (typeof error === 'string') {
    return error || fallback
  }
  return normalizePublicErrorMessage(error.msg || error.message || error.errMsg, fallback)
}

// 拉取历史评论：用于初始化左侧弹幕区。
async function loadComments() {
  commentsLoading.value = true
  try {
    const res = await getLiveRoomComments(roomId.value, { page: 1, limit: HISTORY_LIMIT }, { showError: false })
    if (res.code !== 200 || !res.data) {
      comments.value = []
      return
    }
    const list = Array.isArray(res.data.list) ? res.data.list : []
    comments.value = list.map((item) => normalizeHistoryComment(item)).filter(Boolean).slice(-MAX_COMMENT_COUNT)
    scrollChatToBottom()
  } catch (error) {
    comments.value = []
  } finally {
    commentsLoading.value = false
  }
}

function normalizeHistoryComment(item) {
  if (!item) return null
  const msgId = item.msg_id || `history-${item.id || item.timestamp || Date.now()}`
  return {
    msgKey: `chat-${msgId}`,
    rowType: 'text',
    rowClass: getDanmuItemClassName({ rowType: 'text' }),
    nickname: item.nickname || '',
    avatar: resolveAssetUrl(item.avatar || ''),
    content: item.content || '',
    timestamp: parseTimestamp(item.send_time, item.timestamp)
  }
}

function getSocketClient() {
  if (socketClient.value) return socketClient.value
  socketClient.value = createSocketClient({
    heartbeatInterval: 30000,
    reconnectInterval: 5000,
    maxReconnectAttempts: 5
  })
  bindSocketEvents(socketClient.value)
  return socketClient.value
}

// 绑定实时事件：后端握手认证成功后会自动加入直播间房间，无需前端再发送 join_room。
function bindSocketEvents(client) {
  socketUnsubscribes.value = [
    client.on('status', (payload) => {
      socketStatus.value = payload && payload.status ? payload.status : socketStatus.value
    }),
    client.on('error', (payload) => {
      handleSocketError(payload)
    }),
    client.on('message', (payload) => {
      if (!payload) return
      if (payload.type === 'error') {
        handleSocketError(payload)
      }
    }),
    client.onEvent('live_chat', (payload) => {
      handleLiveChatSocketEvent(payload)
    }),
    client.onEvent('live_user_enter', (payload) => {
      handleLiveUserEnterSocketEvent(payload)
    }),
    client.onEvent('live_user_leave', (payload) => {
      handleLiveUserLeaveSocketEvent(payload)
    }),
    client.onEvent('online_count_update', (payload) => {
      handleOnlineCountUpdateSocketEvent(payload)
    }),
    client.onEvent('gift_message', (payload) => {
      handleGiftMessageSocketEvent(payload)
    }),
    client.onEvent('live_like', (payload) => {
      handleLiveLikeSocketEvent(payload)
    }),
    client.onEvent('console_reply', (payload) => {
      handleConsoleReplySocketEvent(payload)
    }),
    client.onEvent('console_action', (payload) => {
      handleConsoleActionSocketEvent(payload)
    }),
    client.onEvent('goods_change', (payload) => {
      handleGoodsChangeSocketEvent(payload)
    }),
    client.onEvent('room_rank_update', (payload) => {
      handleRoomRankUpdateSocketEvent(payload)
    }),
    client.onEvent('level_upgrade', (payload) => {
      handleLevelUpgradeSocketEvent(payload)
    }),
    client.onEvent('state_sync', (payload) => {
      handleStateSyncSocketEvent(payload)
    }),
    client.onEvent('system_broadcast', (payload) => {
      handleSystemBroadcastSocketEvent(payload)
    })
  ]
}

// 建立直播互动连接：登录用户进入直播间后即连接，后端会维护进出房和在线状态。
async function connectRoomSocket(force = false, options = {}) {
  if (!loggedIn.value || !roomId.value) return
  const isSameRoom = Number(socketRoomId.value) === Number(roomId.value)
  if (!force && isSameRoom && (socketStatus.value === 'connecting' || socketStatus.value === 'connected')) return
  if (force && socketClient.value) {
    destroySocket()
  }
  if (!isSameRoom && socketClient.value) {
    destroySocket()
  }
  socketStatus.value = 'connecting'
  const client = getSocketClient()
  const result = await client.connectLiveRoom(roomId.value)
  if (result.code !== 200) {
    socketStatus.value = 'error'
    socketLastError.value = normalizeSocketErrorMessage(result.msg)
    if (options.showError) {
      uni.showToast({ title: socketLastError.value || '互动通道连接失败，请稍后重试', icon: 'none' })
    }
    return
  }
  socketRoomId.value = roomId.value
  socketLastError.value = ''
}

function destroySocket() {
  socketUnsubscribes.value.forEach((off) => {
    if (typeof off === 'function') off()
  })
  socketUnsubscribes.value = []
  if (socketClient.value) {
    socketClient.value.destroy()
    socketClient.value = null
  }
  socketRoomId.value = 0
  socketStatus.value = 'idle'
  socketLastError.value = ''
}

// 评论输入框确认发送：负责登录校验、连接校验以及真正触发发送。
async function handleComposerAction() {
  if (!loggedIn.value) {
    goLogin()
    return
  }
  if (liveStatus.value !== 1) return
  if (socketStatus.value !== 'connected') {
    await connectRoomSocket(true, { showError: true })
    return
  }
  sendComment()
}

async function sendComment() {
  const content = String(commentInput.value || '').trim()
  if (!content || !socketClient.value || commentSending.value) return
  commentSending.value = true
  const sendSuccess = await socketClient.value.sendChatMessage(content, { room_id: roomId.value })
  if (sendSuccess) {
    commentInput.value = ''
  } else {
    uni.showToast({ title: '发送失败，请稍后重试', icon: 'none' })
  }
  commentSending.value = false
}

function handleSocketError(payload = {}) {
  const message = normalizeSocketErrorMessage(payload && (payload.message || payload.msg || payload.errMsg))
  if (!message) return
  socketLastError.value = message
  socketStatus.value = 'error'
}

function normalizeSocketErrorMessage(message = '') {
  const text = String(message || '').trim()
  if (!text) return ''
  if (text === '未完成的操作') return ''
  if (/operation\s+not\s+complete/i.test(text)) return ''
  if (/socket.*closed/i.test(text)) return ''
  return normalizePublicErrorMessage(text, '')
}

function getSocketPayloadData(payload = {}) {
  return payload && payload.data && typeof payload.data === 'object' ? payload.data : {}
}

function handleLiveChatSocketEvent(payload = {}) {
  const msg = normalizeLiveChatPayload(payload)
  if (msg) pushComment(msg)
}

function handleLiveUserEnterSocketEvent(payload = {}) {
  const enterData = normalizeEnterPayload(payload)
  if (!enterData) return
  queueEnterEffect(enterData)
  const data = getSocketPayloadData(payload)
  applySocketOnlineCount(data)
  loadRoomUsers()
}

function handleLiveUserLeaveSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  applySocketOnlineCount(data)
  loadRoomUsers()
}

function handleOnlineCountUpdateSocketEvent(payload = {}) {
  applySocketOnlineCount(getSocketPayloadData(payload))
}

function handleGiftMessageSocketEvent(payload = {}) {
  const msg = normalizeGiftMessagePayload(payload)
  if (msg) {
    pushComment(msg)
    queueGiftEffect(msg)
  }
}

function handleLiveLikeSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  const likeCount = Number(data.like_count || 0)
  if (likeCount < 0) return
  applyRoomStats({
    like_count: likeCount
  })
}

function handleConsoleReplySocketEvent(payload = {}) {
  const msg = normalizeLiveChatPayload(payload)
  if (msg) pushComment(msg)
}

function handleConsoleActionSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  const action = String(data.action || '').trim()
  const actionData = data.action_data && typeof data.action_data === 'object' ? data.action_data : {}

  if (action === 'start_live' || action === 'end_live') {
    applyRoomInfo(extractRoomInfoFromSocket(actionData))
    loadRoomDetail()
    return
  }

  if (action === 'mute' || action === 'unmute' || action === 'kick') {
    applyRoomUserAction(action, actionData)
  }

  if (action === 'goods_shelf' || action === 'goods_explaining') {
    loadGoods()
  }
}

function handleGoodsChangeSocketEvent(payload = {}) {
  loadGoods()
}

function handleRoomRankUpdateSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  const list = Array.isArray(data.rank_list) ? data.rank_list : []
  const normalizedRankUsers = list.map((item) => normalizeRankUserItem(item, resolveLiveAssetUrl.value)).filter(Boolean)
  const rankUserIds = normalizedRankUsers.map((item) => Number(item.userId || 0)).filter(Boolean)
  const baseUsers = viewerItems.value
    .map((item) => formatRankUser(item, resolveLiveAssetUrl.value))
    .filter((item) => item && !rankUserIds.includes(Number(item.userId || 0)))
  socketRankUsers.value = [...normalizedRankUsers, ...baseUsers]
}

function handleLevelUpgradeSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  const nickname = data.nickname || '用户'
  const levelName = data.level_name || data.new_level || ''
  pushComment({
    msgKey: `level-${data.user_id || 0}-${data.timestamp || Date.now()}`,
    rowType: 'enter',
    rowClass: getDanmuItemClassName({ rowType: 'enter' }),
    content: levelName ? `${nickname} 升级为 ${levelName}` : `${nickname} 等级提升`,
    timestamp: Number(data.timestamp || Math.floor(Date.now() / 1000))
  })
  if (Array.isArray(socketRankUsers.value) && socketRankUsers.value.length) {
    socketRankUsers.value = socketRankUsers.value.map((item) => {
      if (Number(item.userId || 0) !== Number(data.user_id || 0)) return item
      return {
        ...item,
        level: data.level_name || item.level
      }
    })
  }
}

function handleStateSyncSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  const roomInfoData = data.room_info && typeof data.room_info === 'object' ? data.room_info : {}
  if (Object.keys(roomInfoData).length) {
    applyRoomInfo(extractRoomInfoFromSocket(roomInfoData))
  }
}

function handleSystemBroadcastSocketEvent(payload = {}) {
  const data = getSocketPayloadData(payload)
  const content = String(data.content || '').trim()
  if (!content) return
  pushComment({
    msgKey: `system-${payload.timestamp || Date.now()}`,
    rowType: 'enter',
    rowClass: getDanmuItemClassName({ rowType: 'enter' }),
    content,
    timestamp: Number(payload.timestamp || Math.floor(Date.now() / 1000))
  })
}

function applySocketOnlineCount(data = {}) {
  if (data.online_count === undefined) {
    return
  }
  applyRoomStats({
    online_count: Math.max(0, Number(data.online_count || 0))
  })
}

function extractRoomInfoFromSocket(data = {}) {
  const info = {}
  if (data.status !== undefined) info.status = Number(data.status)
  if (data.title !== undefined) info.title = data.title
  if (data.live_play_url !== undefined) info.live_play_url = data.live_play_url
  if (data.pull_flv !== undefined) info.pull_flv = data.pull_flv
  if (data.pull_m3u8 !== undefined) info.pull_m3u8 = data.pull_m3u8
  if (data.stream_info && typeof data.stream_info === 'object') {
    Object.assign(info, extractRoomInfoFromSocket(data.stream_info))
  }
  return info
}

function applyRoomUserAction(action, actionData = {}) {
  const userId = Number(actionData.user_id || 0)
  if (!userId) return
  if (action === 'kick') {
    socketRankUsers.value = rankUsers.value.filter((item) => Number(item.userId || 0) !== userId)
    loadRoomUsers()
    return
  }
  const isMuted = action === 'mute'
  socketRankUsers.value = rankUsers.value.map((item) => {
    if (Number(item.userId || 0) !== userId) return item
    return {
      ...item,
      meta: isMuted ? '已禁言' : item.meta
    }
  })
}

function normalizeLiveChatPayload(payload) {
  const data = payload && payload.data ? payload.data : null
  if (!data) return null
  if (!data.msg_id) return null
  return {
    msgKey: `chat-${data.msg_id}`,
    rowType: 'text',
    rowClass: getDanmuItemClassName({ rowType: 'text' }),
    nickname: data.nickname || '',
    avatar: resolveAssetUrl(data.avatar || ''),
    content: data.content || '',
    timestamp: parseTimestamp(data.send_time, data.timestamp)
  }
}

function normalizeEnterPayload(payload) {
  const data = payload && payload.data ? payload.data : null
  if (!data) return null
  if (!data.event_id) return null
  return {
    eventId: data.event_id,
    nickname: data.nickname || '新朋友',
    initial: String(data.nickname || '新').slice(0, 1),
    avatar: resolveAssetUrl(data.avatar || ''),
    levelName: data.name || data.level_name || '',
    levelIcon: resolveAssetUrl(data.icon || data.level_icon || ''),
    timestamp: Number(data.timestamp || Math.floor(Date.now() / 1000))
  }
}

function normalizeGiftMessagePayload(payload = {}) {
  const data = getSocketPayloadData(payload)
  if (!data || !data.id || !data.gift_name) return null
  const nickname = String(data.nickname || '').trim()
  const giftName = String(data.gift_name || '').trim()
  const quantity = Number(data.quantity || 1)
  const userId = Number(data.user_id || 0)
  const msgId = String(data.id)
  return {
    msgId,
    msgKey: `gift-${msgId}`,
    rowType: 'enter',
    rowClass: getDanmuItemClassName({ rowType: 'enter' }),
    content: `${nickname || '用户'} 送出 ${giftName} x${quantity}`,
    effectUrl: resolveAssetUrl(data.gift_effect_url || data.effect_url || ''),
    isSelf: userId > 0 && userId === getCurrentUserId(),
    timestamp: Number(data.timestamp || Math.floor(Date.now() / 1000))
  }
}

function pushComment(message) {
  if (!message || !message.msgKey) return
  if (comments.value.some((item) => item.msgKey === message.msgKey)) return
  comments.value = [...comments.value, message].slice(-MAX_COMMENT_COUNT)
  scrollChatToBottom()
}

function scrollChatToBottom() {
  scrollIntoView.value = ''
  scrollAnchorVersion.value += 1
  nextTick(() => {
    scrollIntoView.value = bottomAnchorId.value
  })
}

function queueEnterEffect(enterData) {
  enterEffectQueue.value.push(enterData)
  playNextEnterEffect()
}

function playNextEnterEffect() {
  if (activeEnterEffect.value || !enterEffectQueue.value.length) return
  activeEnterEffect.value = enterEffectQueue.value.shift()
  clearEnterEffectTimer()
  enterEffectTimer.value = setTimeout(() => {
    activeEnterEffect.value = null
    playNextEnterEffect()
  }, ENTER_EFFECT_DURATION)
}

function clearEnterEffectTimer() {
  if (!enterEffectTimer.value) return
  clearTimeout(enterEffectTimer.value)
  enterEffectTimer.value = null
}

function clearPlaybackTasks() {
  clearGiftEffectTimer()
  giftEffectQueue.value = []
  activeGiftEffect.value = null
  giftEffectHandledIds.value = {}
  clearEnterEffectTimer()
  enterEffectQueue.value = []
  activeEnterEffect.value = null
}

function parseTimestamp(sendTime, timestamp) {
  if (timestamp) return Number(timestamp)
  const raw = String(sendTime || '').trim()
  if (!raw) return Math.floor(Date.now() / 1000)
  const parsed = Date.parse(raw.replace(/-/g, '/'))
  if (Number.isNaN(parsed)) return Math.floor(Date.now() / 1000)
  return Math.floor(parsed / 1000)
}

function resolveAssetUrl(url) {
  const source = String(url || '').trim()
  if (!source) return ''
  return resolveConfigAssetUrl(source)
}

function handleMiniProgramError() {
  playerError.value = '原生播放器加载失败，请检查直播流地址是否可用'
}

function handlePlayerStageTap(event = {}) {
  if (likeBurst.value && typeof likeBurst.value.trigger === 'function') {
    likeBurst.value.trigger(event)
  }
}

function handleLikeBurst() {
  if (liveStatus.value !== 1) return
  applyRoomStats({
    like_count: likeCountValue.value + 1
  })
  if (!loggedIn.value || !roomId.value) return
  pendingLikeCount.value += 1
  if (likeSending.value) return
  if (pendingLikeCount.value >= LIKE_FLUSH_THRESHOLD) {
    flushPendingLikes()
    return
  }
  scheduleLikeFlush()
}

function scheduleLikeFlush() {
  clearLikeFlushTimer()
  likeFlushTimer.value = setTimeout(() => {
    likeFlushTimer.value = null
    flushPendingLikes()
  }, LIKE_FLUSH_DELAY)
}

function clearLikeFlushTimer() {
  if (!likeFlushTimer.value) return
  clearTimeout(likeFlushTimer.value)
  likeFlushTimer.value = null
}

async function flushPendingLikes(options = {}) {
  if (likeSending.value) return
  if (!loggedIn.value || !roomId.value || pendingLikeCount.value <= 0) return
  if (!options.force && liveStatus.value !== 1) return
  clearLikeFlushTimer()
  const count = Math.min(pendingLikeCount.value, LIKE_REQUEST_MAX_COUNT)
  pendingLikeCount.value -= count
  likeSending.value = true
  try {
    const res = await sendLiveRoomLike(roomId.value, { count }, { showError: false })
    if (res.code === 200 && res.data) {
      const likeCount = Number(res.data.like_count || 0)
      if (likeCount >= 0) {
        applyRoomStats({ like_count: likeCount })
      }
    }
  } catch (error) {} finally {
    likeSending.value = false
    if (pendingLikeCount.value > 0 && loggedIn.value && liveStatus.value === 1) {
      scheduleLikeFlush()
    }
  }
}

function handleRotateFullscreen() {
  if (!canRotateLandscape.value) return
  // #ifdef H5
  requestH5Fullscreen()
  // #endif
  // #ifndef H5
  requestPageLandscape()
  // #endif
}

// #ifndef H5
function requestPageLandscape() {
  if (typeof uni.setPageOrientation !== 'function') {
    uni.showToast({ title: '当前环境不支持页面横屏', icon: 'none' })
    return
  }
  const nextOrientation = pageLandscape.value ? 'portrait' : 'landscape'
  uni.setPageOrientation({
    orientation: nextOrientation,
    success: () => {
      pageLandscape.value = nextOrientation === 'landscape'
    },
    fail: () => {
      uni.showToast({ title: '页面横屏切换失败', icon: 'none' })
    }
  })
}

function resetPageOrientation() {
  if (typeof uni.setPageOrientation !== 'function') return
  uni.setPageOrientation({
    orientation: 'portrait',
    complete: () => {
      pageLandscape.value = false
    }
  })
}
// #endif
// #ifdef H5
function requestH5Fullscreen() {
  const videoElement = getH5VideoElement()
  const target = videoElement || (typeof document !== 'undefined' ? document.documentElement : null)
  if (!target) {
    uni.showToast({ title: '当前环境不支持全屏播放', icon: 'none' })
    return
  }
  const requestFullscreen = target.requestFullscreen || target.webkitRequestFullscreen || target.mozRequestFullScreen || target.msRequestFullscreen
  if (typeof requestFullscreen !== 'function') {
    uni.showToast({ title: '当前环境不支持全屏播放', icon: 'none' })
    return
  }
  const result = requestFullscreen.call(target)
  if (result && typeof result.catch === 'function') {
    result.catch(() => {
      uni.showToast({ title: '全屏播放启动失败', icon: 'none' })
    })
  }
  if (typeof screen !== 'undefined' && screen.orientation && typeof screen.orientation.lock === 'function') {
    screen.orientation.lock('landscape').catch(() => {})
  }
}

function getH5VideoElement() {
  const videoRef = flvVideo.value
  if (!videoRef) return null
  if (typeof HTMLVideoElement !== 'undefined' && videoRef instanceof HTMLVideoElement) return videoRef
  if (videoRef.$el) {
    if (typeof HTMLVideoElement !== 'undefined' && videoRef.$el instanceof HTMLVideoElement) return videoRef.$el
    if (typeof videoRef.$el.querySelector === 'function') {
      const nestedVideo = videoRef.$el.querySelector('video')
      if (nestedVideo) return nestedVideo
    }
  }
  return null
}

function hideH5VideoControls(videoElement) {
  if (!videoElement) return
  videoElement.controls = false
  videoElement.removeAttribute('controls')
  videoElement.setAttribute('controlslist', 'nodownload nofullscreen noremoteplayback')
  videoElement.setAttribute('playsinline', 'true')
  videoElement.setAttribute('webkit-playsinline', 'true')
  videoElement.setAttribute('x5-playsinline', 'true')
  if ('disablePictureInPicture' in videoElement) {
    videoElement.disablePictureInPicture = true
  }
}

function ensureH5VideoSound(videoElement) {
  if (!videoElement) return
  try {
    videoElement.muted = false
    videoElement.defaultMuted = false
    videoElement.removeAttribute('muted')
    if (typeof videoElement.volume === 'number') {
      videoElement.volume = DEFAULT_PLAYER_VOLUME
    }
  } catch (error) {}
}

// H5 播放器初始化：把接口返回的 FLV 地址挂到 flv.js 播放器上。
function initH5Player() {
  destroyFlvPlayer()
  if (!h5PlayUrl.value || liveStatus.value !== 1) return
  const videoElement = getH5VideoElement()
  if (!videoElement || typeof videoElement.addEventListener !== 'function') {
    playerError.value = '播放器初始化失败，请刷新后重试'
    return
  }
  hideH5VideoControls(videoElement)
  ensureH5VideoSound(videoElement)
  if (isExternalStream.value && !isH5FlvPlayUrl.value) {
    videoElement.src = h5PlayUrl.value
    ensureH5VideoSound(videoElement)
    videoElement.load()
    const playResult = videoElement.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => {})
    }
    playerLoading.value = false
    playerError.value = ''
    return
  }
  if (!flvjs || !flvjs.isSupported()) {
    playerError.value = '当前浏览器不支持 FLV 播放'
    return
  }
  playerLoading.value = true
  playerError.value = ''
  const player = flvjs.createPlayer(
    {
      type: 'flv',
      url: h5PlayUrl.value,
      isLive: true,
      cors: true
    },
    {
      enableWorker: false,
      lazyLoadMaxDuration: 180,
      seekType: 'range'
    }
  )
  player.attachMediaElement(videoElement)
  hideH5VideoControls(videoElement)
  ensureH5VideoSound(videoElement)
  player.on(flvjs.Events.ERROR, (errorType, errorDetail) => {
    playerError.value = ''
    playerLoading.value = false
  })
  player.on(flvjs.Events.MEDIA_INFO, () => {
    playerLoading.value = false
  })
  player.load()
  ensureH5VideoSound(videoElement)
  player.play()
  hideH5VideoControls(videoElement)
  ensureH5VideoSound(videoElement)
  flvPlayer.value = player
}

function destroyFlvPlayer() {
  if (!flvPlayer.value) {
    playerLoading.value = false
    return
  }
  try {
    flvPlayer.value.pause()
  } catch (error) {}
  try {
    flvPlayer.value.unload()
  } catch (error) {}
  try {
    flvPlayer.value.detachMediaElement()
  } catch (error) {}
  try {
    flvPlayer.value.destroy()
  } catch (error) {}
  flvPlayer.value = null
  playerLoading.value = false
}
// #endif
</script>

<style lang="scss" src="./style.scss"></style>
