<template>
  <div ref="consoleWrap" class="console-wrap">
    <!-- ===== 顶部深色信息栏 ===== -->
    <div v-loading="infoLoading" class="console-header">
      <div class="header-left" @click="goBack">
        <LegacyIcon name="el-icon-arrow-left" />
        <span>返回</span>
      </div>

      <el-avatar
        v-if="roomInfo.cover"
        :src="roomInfo.cover"
        shape="square"
        :size="40"
        class="room-avatar"
      />
      <div v-else class="room-avatar-placeholder"><LegacyIcon name="el-icon-video-camera-solid" /></div>

      <div class="header-title-block">
        <span class="header-room-name">{{ roomInfo.title || '直播间' }}</span>
        <div class="header-anchor">主播：{{ roomInfo.anchor && roomInfo.anchor.name || '—' }}</div>
      </div>

      <el-tag v-if="roomInfo.status === 1" type="success" effect="dark"  class="status-tag live-tag">
        <span class="live-dot" />
        LIVE
      </el-tag>
      <el-tag v-else-if="roomInfo.status === 0" type="info"  class="status-tag">未开始</el-tag>
      <el-tag v-else type="danger"  class="status-tag">已结束</el-tag>

      <!-- 数据快报 -->
      <div class="header-stats">
        <div class="hstat-item">
          <span class="hstat-num">{{ stats.total_count || 0 }}</span>
          <span class="hstat-label">观看</span>
        </div>
        <div class="hstat-item">
          <span class="hstat-num">{{ stats.online_count || 0 }}</span>
          <span class="hstat-label">在线</span>
        </div>
        <div class="hstat-item">
          <span class="hstat-num">{{ stats.comment_count || 0 }}</span>
          <span class="hstat-label">评论</span>
        </div>
        <div class="hstat-item">
          <span class="hstat-num price-num">¥{{ stats.reward_amount || '0.00' }}</span>
          <span class="hstat-label">打赏</span>
        </div>
      </div>

      <div class="header-actions">
        <el-button
          :class="hasPlayableStream ? 'stream-btn-active' : 'stream-btn-idle'"
          :icon="hasPlayableStream ? Connection : VideoPlay"
          @click="streamDialogVisible = true"
        >
          {{ isExternalStream ? '直播链接' : (streamInfo.stream_name ? '推流地址' : '生成推流') }}
        </el-button>
        <el-button class="drawer-btn" :icon="Present" @click="rewardDrawerVisible = true">打赏</el-button>
        <el-button class="drawer-btn" :icon="User" @click="userDrawerVisible = true">
          用户<span v-if="usersTotal > 0" class="drawer-badge">{{ usersTotal }}</span>
        </el-button>
        <el-button
          v-if="roomInfo.status === 0"
          type="success"
          :icon="VideoPlay"
          :loading="statusLoading"
          @click="handleSwitchStatus(1)"
        >开播</el-button>
        <el-button
          v-if="roomInfo.status === 1"
          type="danger"
          :icon="SwitchButton"
          :loading="statusLoading"
          @click="handleSwitchStatus(2)"
        >结束直播</el-button>
        <el-button
          v-if="roomInfo.status === 2 && !isExternalStream"
          type="primary"
          :icon="VideoPlay"
          :loading="streamLoading"
          @click="handleCreateStream(false)"
        >重新开播</el-button>
        <el-button :icon="Refresh" class="refresh-btn" @click="loadAll" />
      </div>
    </div>

    <!-- ===== 三栏主体 ===== -->
    <div class="console-main">

      <!-- ===== 左栏：直播监看 ===== -->
      <div class="console-left">
        <div class="player-wrap" @mouseenter="playerCtrlVisible = true" @mouseleave="playerCtrlVisible = false">
          <!-- 顶部徽章：在线人数 + 开播时间 -->
          <div class="player-overlay-badges">
            <div class="badge-online">
              <LegacyIcon name="el-icon-user" />
              <span>{{ stats.online_count || 0 }}</span>
            </div>
            <div v-if="roomInfo.start_time" class="badge-time" :title="'开播时间 ' + roomInfo.start_time">
              <LegacyIcon name="el-icon-time" />
              <span v-if="roomInfo.status === 1">{{ liveElapsedText }}</span>
              <span v-else>{{ roomInfo.start_time }}</span>
            </div>
          </div>

          <!-- 顶部错误提示 -->
          <div v-if="playerError" class="player-error-bar">
            <LegacyIcon name="el-icon-warning-outline" />
            {{ playerError }}
          </div>

          <!-- 无直播时的占位封面 -->
          <div v-if="!playerReady && !playerLoading" class="player-offline-mask">
            <div class="offline-mask-inner">
              <div v-if="roomInfo.cover" class="offline-cover" :style="{ backgroundImage: `url(${roomInfo.cover})` }" />
              <div class="offline-icon">
                <LegacyIcon name="el-icon-video-camera-solid" />
              </div>
              <p class="offline-tip">{{ roomInfo.status === 1 ? '正在连接直播流...' : '暂未开播' }}</p>
            </div>
          </div>

          <!-- 连接中蒙层 -->
          <div v-if="playerLoading" class="player-loading-mask">
            <LegacyIcon name="el-icon-loading" />
            <span>连接中...</span>
          </div>

          <video
            ref="flvVideo"
            class="flv-video"
            autoplay
            muted
          />

          <!-- 自定义底部控制栏（参考 B站直播风格） -->
          <transition name="ctrl-fade">
            <div v-show="playerCtrlVisible || !playerReady" class="player-ctrl-bar">
              <div class="ctrl-left">
                <!-- 直播时不显示播放/暂停，只显示 LIVE 标识 -->
                <template v-if="playerReady">
                  <div class="live-badge-ctrl">
                    <span class="live-dot-ctrl" />
                    LIVE
                  </div>
                </template>
                <template v-else>
                  <!-- 未直播时只显示刷新/重试按钮 -->
                  <button class="ctrl-btn" :disabled="playerLoading" @click="initFlvPlayer">
                    <LegacyIcon :name="playerLoading ? 'el-icon-loading' : 'el-icon-refresh'" />
                  </button>
                </template>
              </div>
              <div class="ctrl-right">
                <!-- 音量控制 -->
                <div class="volume-wrap" @mouseenter="volumeBarVisible = true" @mouseleave="volumeBarVisible = false">
                  <button class="ctrl-btn" @click="toggleMute">
                    <LegacyIcon :name="playerMuted ? 'el-icon-turn-off' : 'el-icon-bell'" />
                  </button>
                  <transition name="vol-slide">
                    <div v-show="volumeBarVisible" class="volume-slider-wrap">
                      <el-slider
                        v-model="playerVolume"
                        vertical
                        height="80px"
                        :min="0"
                        :max="100"
                        :show-tooltip="false"
                        @input="onVolumeChange"
                      />
                    </div>
                  </transition>
                </div>
                <!-- 全屏 -->
                <button class="ctrl-btn" @click="toggleFullscreen">
                  <LegacyIcon :name="isFullscreen ? 'el-icon-close' : 'el-icon-full-screen'" />
                </button>
              </div>
            </div>
          </transition>

          <!-- 状态角标（左下，仅悬浮时隐藏，不遮控制栏时显示） -->
          <div v-if="!playerCtrlVisible" class="player-status-corner">
            <span v-if="playerReady" class="pstatus online"><LegacyIcon name="el-icon-circle-check" /> 直播流正常</span>
            <span v-else-if="playerLoading" class="pstatus loading"><LegacyIcon name="el-icon-loading" /> 连接中...</span>
            <span v-else class="pstatus offline"><LegacyIcon name="el-icon-warning" /> 未连接</span>
          </div>
        </div>
      </div>

      <!-- ===== 右侧：商品 + 评论 ===== -->
      <div class="console-center">
        <!-- 商品卡片（上段 flex:1） -->
        <div class="live-card goods-panel">
          <div class="live-card__header">
            <span class="live-card__title"><LegacyIcon name="el-icon-shopping-cart-2" /> 挂载商品</span>
            <div class="live-card__extra">
              <span class="goods-shelf-badge">
                <LegacyIcon name="el-icon-circle-check" />
                上架 <em>{{ goodsList.filter(g => g.status === 1).length }}</em> 件
              </span>
              <el-button
                v-if="!goodsSortMode"
                size="mini"
                :icon="Sort"
                @click="goodsSortMode = true"
              >排序</el-button>
              <template v-else>
                <el-button
                  size="mini"
                  type="success"
                  :icon="Check"
                  :loading="goodsSortLoading"
                  @click="saveSortOrder"
                >保存排序</el-button>
                <el-button
                  size="mini"
                  :icon="Close"
                  @click="cancelSortMode"
                >取消</el-button>
              </template>
              <el-button
                v-if="!goodsSortMode"
                size="mini"
                class="live-icon-btn"
                :icon="Refresh"
                :loading="goodsLoading"
                @click="loadGoods"
              />
              <el-button
                v-if="!goodsSortMode"
                size="mini"
                type="primary"
                :icon="Plus"
                @click="handleAddGoods"
              >添加</el-button>
            </div>
          </div>

          <div v-loading="goodsLoading" class="goods-list">
            <div v-if="!goodsLoading && !goodsList.length" class="live-empty">
              <LegacyIcon name="el-icon-shopping-bag-1" />
              <p>暂无挂载商品</p>
            </div>
            <draggable
              v-model="goodsList"
              :disabled="!goodsSortMode"
              handle=".goods-drag-handle"
              animation="200"
              ghost-class="goods-drag-ghost"
            >
              <div
                v-for="item in goodsList"
                :key="item.id"
                class="goods-item"
                :class="{ 'goods-item--off': item.status === 0, 'goods-item--sort': goodsSortMode }"
              >
                <div v-if="goodsSortMode" class="goods-drag-handle">
                  <LegacyIcon name="el-icon-rank" />
                </div>
                <div class="goods-item__shelf-line" :class="{ active: item.status === 1 }" />
                <div class="goods-item__body">
                  <div class="goods-item__top">
                    <el-tag
                      :type="item.goods_type === 'special' ? 'warning' : 'primary'"
                      size="mini"
                      effect="dark"
                      class="goods-type-tag"
                    >
                      {{ item.goods_type === 'special' ? '专题' : '内容' }}
                    </el-tag>
                    <span class="goods-title" :title="item.goods_title">{{ item.goods_title }}</span>
                  </div>
                  <div class="goods-item__bottom">
                    <span class="goods-price">¥{{ item.goods_price }}</span>
                    <div v-if="!goodsSortMode" class="goods-item__actions">
                      <el-switch
                        v-model="item.status"
                        :active-value="1"
                        :inactive-value="0"
                        class="goods-switch"
                        @change="handleGoodsStatusChange(item)"
                      />
                      <el-button size="mini" type="text" class="live-text-btn" @click="handleEditGoods(item.id)">编辑</el-button>
                      <el-button size="mini" type="text" class="live-danger-btn" @click="handleDeleteGoods(item.id)">移除</el-button>
                    </div>
                    <div v-else class="goods-sort-hint">
                      <span class="sort-num">排序：{{ item.sort }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </draggable>
          </div>

          <div class="pagination-wrap">
            <el-pagination
              v-model:current-page="goodsPage"
              :page-size="goodsLimit"
              layout="total, prev, pager, next"
              :total="goodsTotal"
              @current-change="loadGoods"
            />
          </div>
        </div>

        <!-- 评论气泡（下段固定高度） -->
        <div class="live-card comment-panel">
          <div class="live-card__header">
            <span class="live-card__title"><LegacyIcon name="el-icon-chat-dot-round" /> 实时评论</span>
            <div class="live-card__extra">
              <span class="panel-total"><LegacyIcon name="el-icon-chat-dot-round" />{{ commentsTotal }} 条</span>
              <el-button size="mini" class="live-icon-btn" :icon="Refresh" @click="loadComments" />
            </div>
          </div>
          <div v-loading="commentsLoading" class="comment-stream">
            <div v-if="!commentsLoading && !commentsList.length" class="live-empty">
              <LegacyIcon name="el-icon-chat-line-square" />
              <p>暂无评论</p>
            </div>
            <div
              v-for="(item, idx) in commentsList"
              :key="item.id"
              class="comment-bubble"
            >
              <div class="bubble-accent" :style="{ background: getBubbleColor(idx) }" />
              <div class="bubble-body">
                <div class="bubble-header">
                  <span class="bubble-name">{{ item.nickname || item.user_id }}</span>
                  <span class="bubble-time">{{ item.create_time }}</span>
                  <el-button
                    size="mini"
                    type="text"
                    class="live-danger-btn bubble-del"
                    @click="handleDeleteComment(item.id)"
                  ><LegacyIcon name="el-icon-delete" /></el-button>
                </div>
                <div class="bubble-content">{{ item.content }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== 打赏抽屉 ===== -->
    <el-drawer
      v-model="rewardDrawerVisible"
      title="打赏记录"
      direction="rtl"
      size="480px"
      custom-class="live-drawer"
    >
      <div class="drawer-body">
        <div class="drawer-toolbar">
          <span class="panel-total"><LegacyIcon name="el-icon-present" /> {{ rewardTotal }} 条记录</span>
          <el-button size="mini" class="live-icon-btn" :icon="Refresh" @click="loadReward" />
        </div>
        <div v-loading="rewardLoading" class="reward-stream">
          <div v-if="!rewardLoading && !rewardList.length" class="live-empty">
            <LegacyIcon name="el-icon-present" />
            <p>暂无打赏记录</p>
          </div>
          <div v-for="item in rewardList" :key="item.id" class="reward-item">
            <div class="reward-gift">
              <el-image v-if="item.gift_icon" :src="item.gift_icon" class="gift-img" fit="contain" />
              <i v-else class="el-icon-present gift-icon-default" />
            </div>
            <div class="reward-info">
              <span class="reward-name">{{ item.nickname || item.user_id }}</span>
              <span class="reward-gift-name">{{ item.gift_name }}</span>
            </div>
            <div class="reward-amount">¥{{ item.exchange_amount }}</div>
          </div>
        </div>
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="rewardPage"
            :page-size="rewardLimit"
            layout="total, prev, pager, next"
            :total="rewardTotal"
            small
            @current-change="loadReward"
          />
        </div>
      </div>
    </el-drawer>

    <!-- ===== 用户抽屉 ===== -->
    <el-drawer
      v-model="userDrawerVisible"
      title="在线用户"
      direction="rtl"
      size="480px"
      custom-class="live-drawer"
    >
      <div class="drawer-body">
        <div class="drawer-toolbar">
          <span class="panel-total">
            <span class="online-dot" />
            在线 {{ usersTotal }} 人
          </span>
          <div class="drawer-toolbar__right">
            <el-tag v-if="wsConnected" type="success" size="mini" effect="dark">已连接</el-tag>
            <el-tag v-else type="info" size="mini">未连接</el-tag>
            <el-button size="mini" class="live-icon-btn" :icon="Refresh" @click="handleRefreshUsers" />
            <el-button
              v-if="!wsConnected"
              size="mini"
              type="danger"
              :icon="Connection"
              :loading="wsReconnecting"
              @click="handleWsReconnect"
            >重连</el-button>
          </div>
        </div>
        <div v-loading="usersLoading" class="user-grid">
          <div v-if="!usersLoading && !usersList.length" class="live-empty">
            <LegacyIcon name="el-icon-user" />
            <p>暂无在线用户</p>
          </div>
          <div v-for="user in usersList" :key="user.id" class="user-card">
            <div class="user-card__avatar">
              <el-avatar :size="40" :src="user.avatar" :icon="UserFilled" />
            </div>
            <div class="user-card__name">{{ user.nickname || user.id }}</div>
            <div class="user-card__actions">
              <el-button size="mini" type="text" class="live-text-btn" @click="handleMuteUser(user)">禁言</el-button>
              <el-button size="mini" type="text" class="live-danger-btn" @click="handleKickUser(user)">踢出</el-button>
            </div>
          </div>
        </div>
        <div class="pagination-wrap">
          <el-pagination
            v-model:current-page="usersPage"
            :page-size="usersLimit"
            layout="total, prev, pager, next"
            :total="usersTotal"
            small
            @current-change="loadUsers"
          />
        </div>
      </div>
    </el-drawer>

    <!-- ===== 推流地址弹窗 ===== -->
    <el-dialog
      v-model="streamDialogVisible"
      title="推流地址管理"
      width="680px"
      custom-class="stream-dialog"
      :close-on-click-modal="false"
    >
      <div class="stream-dialog-content">
        <div class="stream-toolbar">
          <el-button
            v-if="!isExternalStream"
            type="primary"
            :icon="VideoPlay"
            :loading="streamLoading"
            @click="handleCreateStream(false)"
          >生成推流地址</el-button>
          <el-button
            v-if="!isExternalStream"
            type="warning"
            :icon="Refresh"
            :loading="streamLoading"
            @click="handleCreateStream(true)"
          >强制刷新</el-button>
          <el-button
            v-if="streamInfo.stream_name && !isExternalStream"
            type="danger"
            :icon="SwitchButton"
            :loading="closeStreamLoading"
            @click="handleCloseStream"
          >关闭推流</el-button>
          <el-tag
            v-if="streamInfo.cloud_provider && !isExternalStream"
            :type="streamInfo.cloud_provider === 'aliyun' ? 'primary' : 'warning'"

            style="margin-left:8px"
          >
            {{ streamInfo.cloud_provider === 'aliyun' ? '阿里云' : '七牛云' }}
          </el-tag>
        </div>

        <div v-if="streamLoading" class="stream-loading-tip">
          <LegacyIcon name="el-icon-loading" /> 正在生成推流地址...
        </div>
        <template v-else-if="isExternalStream">
          <div class="stream-section">
            <div class="stream-section-title"><el-tag type="warning" size="mini">外链直播</el-tag></div>
            <div class="stream-row">
              <span class="stream-label">直播链接</span>
              <el-input :value="livePlayUrl" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(livePlayUrl)">复制</el-button>
            </div>
          </div>
        </template>
        <template v-else-if="streamInfo.stream_name">
          <div class="stream-name-row">
            <span class="stream-name-label">流名称：</span>
            <span class="stream-name-val">{{ streamInfo.stream_name }}</span>
          </div>
          <!-- 阿里云 -->
          <div v-if="streamInfo.ali_push_base || streamInfo.ali_pull_url" class="stream-section">
            <div class="stream-section-title"><el-tag type="primary" size="mini">阿里云</el-tag></div>
            <template v-if="streamInfo.ali_push_base">
              <div class="stream-row">
                <span class="stream-label">推流地址</span>
                <el-input :value="streamInfo.ali_push_base" readonly class="stream-input"  />
                <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.ali_push_base)">复制</el-button>
              </div>
              <div class="stream-row">
                <span class="stream-label">推流 Key</span>
                <el-input :value="streamInfo.ali_push_key" readonly class="stream-input"  />
                <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.ali_push_key)">复制</el-button>
              </div>
            </template>
            <div v-if="streamInfo.ali_pull_url" class="stream-row">
              <span class="stream-label">播流地址</span>
              <el-input :value="streamInfo.ali_pull_url" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.ali_pull_url)">复制</el-button>
            </div>
          </div>
          <!-- 七牛云 -->
          <div v-if="streamInfo.qiniu_push_base || streamInfo.qiniu_pull_url" class="stream-section">
            <div class="stream-section-title"><el-tag type="warning" size="mini">七牛云</el-tag></div>
            <template v-if="streamInfo.qiniu_push_base">
              <div class="stream-row">
                <span class="stream-label">推流地址</span>
                <el-input :value="streamInfo.qiniu_push_base" readonly class="stream-input"  />
                <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_push_base)">复制</el-button>
              </div>
              <div class="stream-row">
                <span class="stream-label">推流 Key</span>
                <el-input :value="streamInfo.qiniu_push_key" readonly class="stream-input"  />
                <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_push_key)">复制</el-button>
              </div>
            </template>
            <div v-if="streamInfo.qiniu_pull_url" class="stream-row">
              <span class="stream-label">播流地址</span>
              <el-input :value="streamInfo.qiniu_pull_url" readonly class="stream-input"  />
              <el-button size="mini" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_pull_url)">复制</el-button>
            </div>
          </div>
        </template>
        <el-empty v-else description="暂无推流地址，请点击「生成推流地址」" :image-size="80" />
      </div>
    </el-dialog>

    <!-- ===== 添加/编辑商品弹窗 ===== -->
    <LiveGoodsFormDialog
      v-model="goodsDialogVisible"
      :title="goodsDialogTitle"
      :live-room-id="roomId"
      :edit-id="goodsEditId"
      @success="loadGoods"
    />
  </div>
</template>

<script setup>
import LiveGoodsFormDialog from './components/LiveGoodsFormDialog'
import flvjs from 'flv.js'
import draggable from 'vuedraggable'
import {
  getRoomDetail,
  switchRoomStatus,
  getRoomStreamInfo,
  createRoomStream,
  closeRoomStream,
  getLiveGoodsList,
  deleteLiveGoods,
  showLiveGoods,
  batchSortLiveGoods,
  getLiveRoomUsers,
  muteRoomUser,
  kickRoomUser,
  getLiveComments,
  deleteLiveComment,
  getLiveRewardList,
  getLiveRoomStats
} from '@/api/live'
import { useWebsocketStore } from '@/store/modules/websocket'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Check,
  Close,
  Connection,
  CopyDocument,
  Plus,
  Present,
  Refresh,
  Sort,
  SwitchButton,
  User,
  UserFilled,
  VideoPlay
} from '@element-plus/icons-vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

defineOptions({
  name: 'MerchantLiveConsole'
})

// iframe 场景下 useRoute 可能不可用，做保护
let route = null
try {
  route = useRoute()
} catch (e) {
  route = { query: {}}
}
const router = useRouter()

// WebSocket store（Pinia）
const wsStore = useWebsocketStore()
const { connected: wsConnected, onlineUsers: wsOnlineUsers, consoleActions: wsConsoleActions } = storeToRefs(wsStore)
const {
  connectWebSocket,
  disconnectWebSocket,
  reconnectWebSocket,
  muteUser,
  kickUser,
  queryOnlineUsers,
  sendConsoleAction
} = wsStore

// 直播间
const roomId = ref(0)
// 推流地址弹窗
const streamDialogVisible = ref(false)
// 抽屉
const rewardDrawerVisible = ref(false)
const userDrawerVisible = ref(false)
// flv 播放器
let flvPlayer = null
const playerReady = ref(false)
const playerError = ref('')
const playerLoading = ref(false)
const infoLoading = ref(false)
const statusLoading = ref(false)
const roomInfo = ref({})
const stats = ref({})
// 推流
const streamInfo = ref({})
const streamLoading = ref(false)
const closeStreamLoading = ref(false)
// 商品
const goodsLoading = ref(false)
const goodsList = ref([])
const goodsTotal = ref(0)
const goodsPage = ref(1)
const goodsLimit = ref(20)
const goodsDialogVisible = ref(false)
const goodsDialogTitle = ref('添加商品')
const goodsEditId = ref(0)
const goodsSortMode = ref(false)
const goodsSortLoading = ref(false)
// 用户
const usersLoading = ref(false)
const usersList = ref([])
const usersTotal = ref(0)
const usersPage = ref(1)
const usersLimit = ref(20)
// 评论
const commentsLoading = ref(false)
const commentsList = ref([])
const commentsTotal = ref(0)
const commentsPage = ref(1)
const commentsLimit = ref(20)
// 打赏
const rewardLoading = ref(false)
const rewardList = ref([])
const rewardTotal = ref(0)
const rewardPage = ref(1)
const rewardLimit = ref(20)
// WebSocket 相关
const wsReconnecting = ref(false)
// 播放器控制
const playerCtrlVisible = ref(false)
const playerMuted = ref(true)
const playerVolume = ref(0)
const volumeBarVisible = ref(false)
const isFullscreen = ref(false)
let liveDurationNow = Date.now()
let liveDurationTimer = null

const flvVideo = ref(null)
const consoleWrap = ref(null)

// ===== computed =====

/** 后台解析的 HTTP-FLV 播流地址（live_play_url），兼容旧版字段 */
const livePlayUrl = computed(() => {
  return streamInfo.value.live_play_url ||
    streamInfo.value.external_play_url ||
    roomInfo.value.live_play_url ||
    roomInfo.value.external_play_url ||
    streamInfo.value.ali_pull_url ||
    streamInfo.value.qiniu_pull_url ||
    roomInfo.value.ali_pull_url ||
    roomInfo.value.qiniu_pull_url ||
    ''
})

const isExternalStream = computed(() => {
  return String(streamInfo.value.stream_type || roomInfo.value.stream_type || '') === 'external'
})

const hasPlayableStream = computed(() => {
  return !!(streamInfo.value.stream_name || livePlayUrl.value)
})

const isFlvPlayUrl = computed(() => {
  const url = String(livePlayUrl.value || '').split('?')[0]
  return /\.flv$/i.test(url)
})

/** 直播中：相对 start_time 的已播时长（每秒刷新） */
const liveElapsedText = computed(() => {
  if (roomInfo.value.status !== 1 || !roomInfo.value.start_time) {
    return ''
  }
  const t = new Date(String(roomInfo.value.start_time).replace(/-/g, '/')).getTime()
  if (Number.isNaN(t)) {
    return roomInfo.value.start_time
  }
  const sec = Math.max(0, Math.floor((liveDurationNow - t) / 1000))
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}:${pad(s)}`
})

// ===== watch =====

// 监听 WebSocket 在线用户变化
watch(wsOnlineUsers, (newVal) => {
  if (newVal.length > 0) {
    usersList.value = newVal.map(item => ({
      id: item.user_id,
      user_id: item.user_id,
      nickname: item.nickname,
      avatar: item.avatar,
      join_time: item.last_enter_time
    }))
    usersTotal.value = newVal.length
  }
})

// 监听 WebSocket 中控台操作广播（禁言/踢人等）
watch(wsConsoleActions, (newVal) => {
  if (newVal.length === 0) return
  const latestAction = newVal[0]
  const { action } = latestAction
  if (['mute', 'unmute', 'kick'].includes(action)) {
    console.log('[中控台] 收到用户状态变更广播:', action, latestAction.data)
    handleRefreshUsers()
  }
})

// 监听 WebSocket 连接状态
watch(wsConnected, (connected) => {
  if (connected) {
    console.log('[中控台] WebSocket 已连接')
    wsReconnecting.value = false
  } else {
    console.warn('[中控台] WebSocket 已断开')
  }
})

// ===== 生命周期 =====

onMounted(() => {
  roomId.value = Number(route.query.id)
  if (!roomId.value) {
    ElMessage.error('直播间 ID 无效')
    router.go(-1)
    return
  }
  loadAll()
  loadGoods()
  loadComments()
  loadUsers()
  loadReward()
  // 连接 WebSocket（传入 roomId 用于获取 ws_token）
  connectWebSocket(roomId.value)
})

onBeforeUnmount(() => {
  clearLiveDurationTimer()
  destroyFlvPlayer()
  // 断开 WebSocket
  disconnectWebSocket()
})

// ===== 方法 =====

function goBack() {
  router.go(-1)
}

function loadAll() {
  loadRoomInfo()
  loadStreamInfo()
  loadStats()
}

function loadRoomInfo() {
  infoLoading.value = true
  getRoomDetail(roomId.value).then(res => {
    roomInfo.value = res.data || {}
    syncLiveDurationTimer()
  }).finally(() => {
    infoLoading.value = false
    nextTick(() => initFlvPlayer())
  })
}

function syncLiveDurationTimer() {
  clearLiveDurationTimer()
  if (roomInfo.value.status === 1 && roomInfo.value.start_time) {
    liveDurationNow = Date.now()
    liveDurationTimer = setInterval(() => {
      liveDurationNow = Date.now()
    }, 1000)
  }
}

function clearLiveDurationTimer() {
  if (liveDurationTimer) {
    clearInterval(liveDurationTimer)
    liveDurationTimer = null
  }
}

function loadStats() {
  getLiveRoomStats(roomId.value).then(res => {
    stats.value = res.data || {}
  }).catch(() => {})
}

function loadStreamInfo() {
  getRoomStreamInfo(roomId.value).then(res => {
    streamInfo.value = res.data || {}
  }).catch(() => {
    streamInfo.value = {}
  }).finally(() => {
    nextTick(() => initFlvPlayer())
  })
}

function handleSwitchStatus(status) {
  const actionMap = { 1: '开播', 2: '结束直播' }
  ElMessageBox.confirm(`确定要${actionMap[status]}吗？`, '提示', { type: 'warning' }).then(() => {
    statusLoading.value = true
    switchRoomStatus(roomId.value, status).then(res => {
      const data = res.data || {}
      ElMessage.success(data.msg || '操作成功')
      loadRoomInfo()
      // 开播时直接使用后端返回的推流地址，无需再次请求
      if (status === 1) {
        if (data.stream_info) {
          streamInfo.value = data.stream_info
        } else {
          loadStreamInfo()
        }
        if (data.stream_warn) {
          ElMessage.warning(data.stream_warn)
        }
        sendConsoleAction({ action: 'start_live', actionData: {}}).catch(() => {})
      } else {
        sendConsoleAction({ action: 'end_live', actionData: {}}).catch(() => {})
      }
    }).finally(() => {
      statusLoading.value = false
    })
  })
}

// 推流
function handleCreateStream(force) {
  if (isExternalStream.value) {
    ElMessage.warning('外链直播无需生成推流地址')
    return
  }
  const isEnded = roomInfo.value.status === 2
  const tip = force
    ? '强制刷新将重新生成推流地址，原地址将失效。确认继续？'
    : isEnded
      ? '直播已结束，生成推流地址将自动重新开播。确认继续？'
      : '确认为该直播间生成推流地址？'
  ElMessageBox.confirm(tip, '提示', { type: 'warning' }).then(() => {
    streamLoading.value = true
    // 若直播已结束，先将状态设为开播再创建推流
    const doCreate = () => {
      createRoomStream(roomId.value, force || false).then(res => {
        ElMessage.success('推流地址生成成功')
        streamInfo.value = res.data || {}
        // 刷新房间信息（状态可能已变更）
        loadRoomInfo()
        loadStats()
      }).catch(() => {
        ElMessage.error('推流地址生成失败')
      }).finally(() => {
        streamLoading.value = false
      })
    }
    if (isEnded) {
      switchRoomStatus(roomId.value, 1).then(() => {
        roomInfo.value = { ...roomInfo.value, status: 1 }
        ElMessage.success('已自动开播')
        doCreate()
      }).catch(() => {
        streamLoading.value = false
        ElMessage.error('开播失败，请手动开播后重试')
      })
    } else {
      doCreate()
    }
  })
}

function handleCloseStream() {
  ElMessageBox.confirm('确认关闭推流？关闭后观众将无法继续观看', '警告', { type: 'warning' }).then(() => {
    closeStreamLoading.value = true
    closeRoomStream(roomId.value).then(() => {
      ElMessage.success('推流已关闭')
      loadStreamInfo()
    }).finally(() => {
      closeStreamLoading.value = false
    })
  })
}

function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => { ElMessage.success('复制成功') })
  } else {
    const el = document.createElement('input')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    ElMessage.success('复制成功')
  }
}

// 商品
function loadGoods() {
  goodsLoading.value = true
  getLiveGoodsList({ live_room_id: roomId.value, page: goodsPage.value, limit: goodsLimit.value }).then(res => {
    const list = res.data.list || res.data || []
    // 按 sort 降序，sort 相同则按 id 升序
    goodsList.value = list.slice().sort((a, b) => (b.sort || 0) - (a.sort || 0) || a.id - b.id)
    goodsTotal.value = res.data.total || 0
  }).finally(() => { goodsLoading.value = false })
}

function handleAddGoods() {
  goodsEditId.value = 0
  goodsDialogTitle.value = '添加商品'
  goodsDialogVisible.value = true
}

function handleEditGoods(id) {
  goodsEditId.value = id
  goodsDialogTitle.value = '编辑商品'
  goodsDialogVisible.value = true
}

function handleGoodsStatusChange(row) {
  showLiveGoods(row.id, row.status).then(() => {
    ElMessage.success('状态更新成功')
    // 通过 WebSocket 广播商品状态变更
    sendConsoleAction({
      action: 'goods_shelf',
      actionData: { goods_id: row.id, status: row.status }
    }).catch(() => {})
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleDeleteGoods(id) {
  ElMessageBox.confirm('确定要移除该挂载商品吗？', '提示', { type: 'warning' }).then(() => {
    deleteLiveGoods(id).then(() => {
      ElMessage.success('移除成功')
      loadGoods()
    })
  })
}

// 保存拖拽排序
function saveSortOrder() {
  goodsSortLoading.value = true
  const sortData = goodsList.value.map((item, index) => ({
    id: item.id,
    sort: goodsList.value.length - index
  }))
  batchSortLiveGoods(roomId.value, sortData).then(() => {
    ElMessage.success('排序保存成功')
    goodsSortMode.value = false
    loadGoods()
  }).catch(() => {
    ElMessage.error('排序保存失败')
  }).finally(() => {
    goodsSortLoading.value = false
  })
}

// 取消排序模式
function cancelSortMode() {
  goodsSortMode.value = false
  loadGoods()
}

// 用户
function loadUsers() {
  usersLoading.value = true
  getLiveRoomUsers(roomId.value, { page: usersPage.value, limit: usersLimit.value }).then(res => {
    usersList.value = res.data.list || res.data || []
    usersTotal.value = res.data.total || 0
  }).finally(() => { usersLoading.value = false })
}

function handleMuteUser(row) {
  ElMessageBox.confirm(`确定禁言用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    // 通过 WebSocket 发送禁言指令
    muteUser({ userId: row.user_id || row.id, duration: 3600, reason: '' })
      .then(() => {
        ElMessage.success('禁言指令已发送')
      })
      .catch(err => {
        console.error(err)
        ElMessage.error('禁言失败，请重试')
      })
  })
}

function handleKickUser(row) {
  ElMessageBox.confirm(`确定踢出用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    // 通过 WebSocket 发送踢人指令
    kickUser({ userId: row.user_id || row.id })
      .then(() => {
        ElMessage.success('踢出指令已发送')
        loadUsers()
      })
      .catch(err => {
        console.error(err)
        ElMessage.error('踢出失败，请重试')
      })
  })
}

// 评论
function loadComments() {
  commentsLoading.value = true
  getLiveComments(roomId.value, { page: commentsPage.value, limit: commentsLimit.value }).then(res => {
    commentsList.value = res.data.list || res.data || []
    commentsTotal.value = res.data.total || 0
  }).finally(() => { commentsLoading.value = false })
}

function handleDeleteComment(commentId) {
  ElMessageBox.confirm('确定要删除该评论吗？', '提示', { type: 'warning' }).then(() => {
    deleteLiveComment(roomId.value, commentId).then(() => {
      ElMessage.success('删除成功')
      loadComments()
    })
  })
}

// 刷新在线用户（通过 WebSocket）
function handleRefreshUsers() {
  if (!wsConnected.value) {
    ElMessage.warning('WebSocket 未连接，无法获取实时用户列表')
    return
  }
  queryOnlineUsers().catch(err => {
    console.error(err)
    ElMessage.error('获取用户列表失败')
  })
}

// 手动重连 WebSocket
function handleWsReconnect() {
  if (wsReconnecting.value) return
  wsReconnecting.value = true
  reconnectWebSocket()
  setTimeout(() => {
    wsReconnecting.value = false
  }, 3000)
}

// 打赏
function loadReward() {
  rewardLoading.value = true
  getLiveRewardList({ live_room_id: roomId.value, page: rewardPage.value, limit: rewardLimit.value }).then(res => {
    rewardList.value = res.data.list || res.data || []
    rewardTotal.value = res.data.total || 0
  }).finally(() => { rewardLoading.value = false })
}

// flv 播放器
function initFlvPlayer() {
  destroyFlvPlayer()
  const url = livePlayUrl.value
  if (!url) {
    playerError.value = isExternalStream.value ? '暂无直播链接，请先在直播间编辑页填写' : '暂无拉流地址，请先生成推流地址'
    return
  }
  const videoEl = flvVideo.value
  if (!videoEl) return
  if (isExternalStream.value && !isFlvPlayUrl.value) {
    videoEl.src = url
    videoEl.load()
    const playResult = videoEl.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => {})
    }
    playerReady.value = true
    playerError.value = ''
    playerLoading.value = false
    return
  }
  if (!flvjs.isSupported()) {
    playerError.value = '当前浏览器不支持 FLV 播放'
    return
  }
  playerError.value = ''
  playerLoading.value = true
  playerReady.value = false
  const player = flvjs.createPlayer({
    type: 'flv',
    url,
    isLive: true,
    cors: true
  }, {
    enableWorker: false,
    lazyLoadMaxDuration: 3 * 60,
    seekType: 'range'
  })
  player.attachMediaElement(videoEl)
  player.on(flvjs.Events.ERROR, (errType, errDetail) => {
    playerError.value = `播放出错：${errDetail || errType}`
    playerLoading.value = false
  })
  player.on(flvjs.Events.MEDIA_INFO, () => {
    playerLoading.value = false
    playerReady.value = true
  })
  player.load()
  player.play()
  flvPlayer = player
}

function destroyFlvPlayer() {
  if (flvPlayer) {
    flvPlayer.pause()
    flvPlayer.unload()
    flvPlayer.detachMediaElement()
    flvPlayer.destroy()
    flvPlayer = null
  }
  playerReady.value = false
  playerError.value = ''
  playerLoading.value = false
}

// 音量/静音控制
function toggleMute() {
  const video = flvVideo.value
  if (!video) return
  playerMuted.value = !playerMuted.value
  video.muted = playerMuted.value
  if (!playerMuted.value && playerVolume.value === 0) {
    playerVolume.value = 50
    video.volume = 0.5
  }
}

function onVolumeChange(val) {
  const video = flvVideo.value
  if (!video) return
  video.volume = val / 100
  playerMuted.value = val === 0
  video.muted = playerMuted.value
}

// 全屏切换
function toggleFullscreen() {
  const wrap = consoleWrap.value && consoleWrap.value.querySelector('.player-wrap')
  if (!wrap) return
  if (!document.fullscreenElement) {
    wrap.requestFullscreen && wrap.requestFullscreen()
    isFullscreen.value = true
  } else {
    document.exitFullscreen && document.exitFullscreen()
    isFullscreen.value = false
  }
}

// 评论气泡左边彩色竖线
function getBubbleColor(idx) {
  const colors = [
    '#00d4aa', '#ff6b6b', '#ffd700', '#a78bfa',
    '#38bdf8', '#fb923c', '#4ade80', '#f472b6'
  ]
  return colors[idx % colors.length]
}
</script>

<style lang="scss" scoped>
// ===== CSS 颜色变量 =====
.console-wrap {
  --live-bg: #0c0d12;
  --live-card-bg: #161822;
  --live-card-bg2: #1e2030;
  --live-border: #2a2d45;
  --live-green: #00d4aa;
  --live-red: #ff4757;
  --live-gold: #ffd700;
  --live-text: #e0e0f0;
  --live-text-dim: #7a7d9a;
  --live-text-muted: #4a4d6a;
  --live-accent: #4f6ef7;
}

// ===== 动画 =====
@keyframes live-pulse {
  0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7); }
  50% { opacity: 0.7; transform: scale(1.2); box-shadow: 0 0 0 6px rgba(255, 71, 87, 0); }
}
@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes reward-shine {
  0% { background-position: -200% center; }
  100% { background-position: 200% center; }
}

// ===== 整体容器 =====
.console-wrap {
  background: var(--live-bg);
  height: 100vh;
  overflow: hidden;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  display: flex;
  flex-direction: column;
}

// ===== 顶部信息栏 =====
.console-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 20px;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  border-bottom: 1px solid var(--live-border);
  position: sticky;
  top: 0;
  z-index: 200;
  box-shadow: 0 2px 20px rgba(0, 0, 0, 0.5);
}

.header-left {
  cursor: pointer;
  color: var(--live-text-dim);
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  transition: color 0.2s;
  &:hover { color: var(--live-text); }
}

.room-avatar {
  flex-shrink: 0;
  border-radius: 6px;
  border: 2px solid var(--live-border);
}
.room-avatar-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 6px;
  background: var(--live-card-bg2);
  border: 2px solid var(--live-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: var(--live-text-muted);
  flex-shrink: 0;
}

.header-title-block {
  flex-shrink: 0;
}
.header-room-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--live-text);
  display: block;
  line-height: 1.3;
}
.header-anchor {
  font-size: 12px;
  color: var(--live-text-dim);
}

// 直播中状态标签
.status-tag {
  flex-shrink: 0;
}
.live-tag {
  background: rgba(255, 71, 87, 0.15) !important;
  border-color: var(--live-red) !important;
  color: var(--live-red) !important;
  font-weight: 700;
  letter-spacing: 1px;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--live-red);
  display: inline-block;
  animation: live-pulse 1.5s ease-in-out infinite;
}

// 数据快报
.header-stats {
  display: flex;
  gap: 6px;
  flex: 1;
  justify-content: center;
  align-items: center;
}
.hstat-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  transition: background 0.2s;
  &:hover {
    background: rgba(255, 255, 255, 0.09);
  }
}
.hstat-num {
  font-size: 18px;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.5px;
}
.price-num {
  color: var(--live-gold);
  text-shadow: 0 0 10px rgba(255, 215, 0, 0.4);
}
.hstat-label {
  font-size: 11px;
  color: var(--live-text-dim);
  letter-spacing: 0.5px;
}

// 操作按钮区
.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.stream-btn-active {
  background: rgba(0, 212, 170, 0.15) !important;
  border-color: var(--live-green) !important;
  color: var(--live-green) !important;
  &:hover {
    background: rgba(0, 212, 170, 0.25) !important;
  }
}
.stream-btn-idle {
  background: rgba(255, 167, 38, 0.15) !important;
  border-color: #ffa726 !important;
  color: #ffa726 !important;
  &:hover {
    background: rgba(255, 167, 38, 0.25) !important;
  }
}
.refresh-btn {
  background: transparent !important;
  border-color: var(--live-border) !important;
  color: var(--live-text-dim) !important;
  &:hover {
    border-color: var(--live-text-dim) !important;
    color: var(--live-text) !important;
  }
}

// ===== 三栏主体 =====
.console-main {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  align-items: stretch;
  flex: 1;       // 占满 console-wrap 剩余高度
  overflow: hidden;
  min-height: 0; // flex 子元素必须设置才能正确收缩
}

.console-left {
  width: 30%;
  min-width: 280px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.console-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: hidden;
}

// ===== 公共卡片基底样式 =====
.live-card {
  background: var(--live-card-bg);
  border: 1px solid var(--live-border);
  border-radius: 10px;
  overflow: hidden;
}
.live-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--live-border);
  background: var(--live-card-bg2);
}
.live-card__title {
  font-size: 14px;
  font-weight: 600;
  color: var(--live-text);
  display: flex;
  align-items: center;
  gap: 6px;
  i { color: var(--live-green); }
}
.live-card__extra {
  display: flex;
  align-items: center;
  gap: 10px;
}

// ===== 左栏：播放器 =====
.player-wrap {
  position: relative;
  background: #0a0a0f;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--live-border);
  flex: 1;
  user-select: none;
}

.player-overlay-badges {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
}
.badge-online,
.badge-time {
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 5px;
  backdrop-filter: blur(4px);
  i { color: var(--live-green); }
}
.badge-time i { color: var(--live-text-dim); }

.flv-video {
  width: 100%;
  height: 100%;
  display: block;
  background: #000;
  max-height: unset;
  object-fit: contain;
}

// 无直播占位封面
.player-offline-mask {
  position: absolute;
  inset: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0a0a0f;
}
.offline-mask-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}
.offline-cover {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: blur(20px) brightness(0.25);
}
.offline-icon {
  position: relative;
  z-index: 1;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  i {
    font-size: 28px;
    color: var(--live-text-muted);
  }
}
.offline-tip {
  position: relative;
  z-index: 1;
  font-size: 13px;
  color: var(--live-text-dim);
  margin: 0;
}

// 连接中蒙层
.player-loading-mask {
  position: absolute;
  inset: 0;
  z-index: 6;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: rgba(10, 10, 15, 0.8);
  i {
    font-size: 28px;
    color: var(--live-green);
  }
  span {
    font-size: 13px;
    color: var(--live-text-dim);
  }
}

// 自定义控制栏（B站直播风格）
.player-ctrl-bar {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 20;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 12px;
  background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 100%);
  backdrop-filter: blur(2px);
}
.ctrl-left,
.ctrl-right {
  display: flex;
  align-items: center;
  gap: 4px;
}
.ctrl-btn {
  background: none;
  border: none;
  outline: none;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.85);
  width: 34px;
  height: 34px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, color 0.15s;
  i { font-size: 18px; }
  &:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
  }
  &:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
}

// LIVE 标识（控制栏左侧）
.live-badge-ctrl {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 4px;
  background: rgba(255, 71, 87, 0.85);
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  letter-spacing: 1px;
}
.live-dot-ctrl {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #fff;
  display: inline-block;
  animation: live-pulse 1.5s ease-in-out infinite;
}

// 音量控件
.volume-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.volume-slider-wrap {
  position: absolute;
  bottom: 40px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(20, 20, 30, 0.92);
  border: 1px solid var(--live-border);
  border-radius: 8px;
  padding: 10px 10px 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(6px);
  ::v-deep .el-slider__runway {
    background: rgba(255,255,255,0.15);
  }
  ::v-deep .el-slider__bar {
    background: var(--live-green);
  }
  ::v-deep .el-slider__button {
    width: 10px;
    height: 10px;
    border-color: var(--live-green);
  }
}

// 控制栏过渡动画
.ctrl-fade-enter-active, .ctrl-fade-leave-active {
  transition: opacity 0.2s, transform 0.2s;
}
.ctrl-fade-enter, .ctrl-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

// 音量滑块出现动画
.vol-slide-enter-active, .vol-slide-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.vol-slide-enter, .vol-slide-leave-to {
  opacity: 0;
  transform: translateY(6px);
}

// 状态角标（不悬浮时显示在左下角）
.player-status-corner {
  position: absolute;
  bottom: 10px;
  left: 12px;
  z-index: 15;
}
.pstatus {
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 5px;
  &.online { color: var(--live-green); }
  &.offline { color: var(--live-red); }
  &.loading { color: #ffa726; }
}

.player-error-bar {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 25;
  background: rgba(255, 71, 87, 0.85);
  color: #fff;
  font-size: 12px;
  padding: 6px 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

// ===== 中栏：商品 + 评论 =====
.goods-panel {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-height: 0; // 允许 flex 子元素收缩
}

.comment-panel {
  height: 280px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  .comment-stream {
    flex: 1;
    max-height: unset;
    overflow-y: auto;
  }
}

.goods-shelf-badge {
  font-size: 12px;
  color: var(--live-text-dim);
  em {
    font-style: normal;
    color: var(--live-green);
    font-weight: 700;
    font-size: 16px;
    text-shadow: 0 0 8px rgba(0, 212, 170, 0.6);
    margin: 0 2px;
  }
}

.goods-list {
  padding: 10px 14px;
  flex: 1;
  overflow-y: auto;
  min-height: 0;
  &::-webkit-scrollbar { width: 4px; }
  &::-webkit-scrollbar-track { background: transparent; }
  &::-webkit-scrollbar-thumb { background: var(--live-border); border-radius: 2px; }
}

.goods-item {
  display: flex;
  align-items: stretch;
  border: 1px solid var(--live-border);
  border-radius: 8px;
  margin-bottom: 8px;
  background: var(--live-card-bg2);
  overflow: hidden;
  transition: border-color 0.2s, transform 0.15s;
  animation: fade-in-up 0.3s ease;
  &:hover {
    border-color: var(--live-accent);
    transform: translateX(2px);
  }
  &--off {
    opacity: 0.45;
  }
  &--sort {
    cursor: default;
    &:hover {
      transform: none;
      border-color: var(--live-border);
    }
  }
}
.goods-drag-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  flex-shrink: 0;
  cursor: grab;
  color: var(--live-text-muted);
  transition: color 0.2s;
  &:hover { color: var(--live-text-dim); }
  &:active { cursor: grabbing; }
  i { font-size: 16px; }
}
.goods-drag-ghost {
  opacity: 0.4;
  background: var(--live-accent) !important;
  border-color: var(--live-accent) !important;
}
.goods-sort-hint {
  display: flex;
  align-items: center;
}
.sort-num {
  font-size: 12px;
  color: var(--live-text-muted);
}
.goods-item__shelf-line {
  width: 3px;
  flex-shrink: 0;
  background: var(--live-border);
  border-radius: 0;
  transition: background 0.2s;
  &.active {
    background: var(--live-green);
    box-shadow: 0 0 8px rgba(0, 212, 170, 0.6);
  }
}
.goods-item__body {
  flex: 1;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.goods-item__top {
  display: flex;
  align-items: center;
  gap: 8px;
}
.goods-type-tag {
  flex-shrink: 0;
}
.goods-title {
  font-size: 13px;
  color: var(--live-text);
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  flex: 1;
}
.goods-item__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.goods-price {
  font-size: 15px;
  color: var(--live-gold);
  font-weight: 700;
}
.goods-item__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.goods-switch {
  ::v-deep .el-switch__core {
    background: var(--live-text-muted) !important;
    border-color: var(--live-text-muted) !important;
  }
  &.is-checked {
    ::v-deep .el-switch__core {
      background: var(--live-green) !important;
      border-color: var(--live-green) !important;
    }
  }
}

// ===== 抽屉样式 =====
::v-deep .live-drawer {
  background: var(--live-card-bg) !important;
  .el-drawer__header {
    background: var(--live-card-bg2);
    border-bottom: 1px solid var(--live-border);
    color: var(--live-text);
    margin-bottom: 0;
    padding: 16px 20px;
  }
  .el-drawer__body {
    background: var(--live-card-bg);
    padding: 14px;
    overflow-y: auto;
  }
}
.drawer-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  height: 100%;
}
.drawer-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
}
.drawer-toolbar__right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.drawer-btn {
  background: rgba(79,110,247,0.15) !important;
  border-color: var(--live-accent) !important;
  color: var(--live-accent) !important;
  position: relative;
  &:hover { background: rgba(79,110,247,0.25) !important; }
}
.drawer-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  background: var(--live-red);
  color: #fff;
  border-radius: 8px;
  padding: 0 4px;
  font-size: 10px;
  line-height: 16px;
  height: 16px;
}
.online-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--live-green);
  display: inline-block;
  box-shadow: 0 0 6px var(--live-green);
  animation: live-pulse 2s ease-in-out infinite;
}

// 评论气泡
.comment-stream {
  max-height: unset;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-right: 4px;
  &::-webkit-scrollbar { width: 4px; }
  &::-webkit-scrollbar-track { background: transparent; }
  &::-webkit-scrollbar-thumb { background: var(--live-border); border-radius: 2px; }
}
.comment-bubble {
  display: flex;
  gap: 0;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--live-border);
  background: var(--live-card-bg2);
  transition: border-color 0.2s, transform 0.15s;
  animation: fade-in-up 0.3s ease;
  &:hover {
    border-color: var(--live-accent);
    transform: translateX(2px);
    .bubble-del { opacity: 1; }
  }
}
.bubble-accent {
  width: 3px;
  flex-shrink: 0;
}
.bubble-body {
  padding: 8px 10px;
  flex: 1;
}
.bubble-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}
.bubble-name {
  font-size: 12px;
  font-weight: 700;
  background: linear-gradient(90deg, #ffd700, #ff9500);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.bubble-time {
  font-size: 11px;
  color: var(--live-text-muted);
  flex: 1;
}
.bubble-del {
  opacity: 0;
  transition: opacity 0.2s;
  padding: 0 !important;
}
.bubble-content {
  font-size: 13px;
  color: var(--live-text);
  line-height: 1.5;
  word-break: break-all;
}

// 打赏卡片流
.reward-stream {
  max-height: unset;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
  &::-webkit-scrollbar { width: 4px; }
  &::-webkit-scrollbar-track { background: transparent; }
  &::-webkit-scrollbar-thumb { background: var(--live-border); border-radius: 2px; }
}
.reward-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: var(--live-card-bg2);
  border: 1px solid var(--live-border);
  animation: fade-in-up 0.3s ease;
  transition: border-color 0.2s;
  &:hover { border-color: var(--live-gold); }
}
.reward-gift {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.gift-img {
  width: 36px;
  height: 36px;
}
.gift-icon-default {
  font-size: 24px;
  color: var(--live-gold);
}
.reward-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.reward-name {
  font-size: 13px;
  color: var(--live-text);
  font-weight: 600;
}
.reward-gift-name {
  font-size: 12px;
  color: var(--live-text-dim);
}
.reward-amount {
  font-size: 18px;
  font-weight: 800;
  color: var(--live-gold);
  text-shadow: 0 0 12px rgba(255, 215, 0, 0.5);
  background: linear-gradient(135deg, #ffd700, #ff9500);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

// 用户网格
.user-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  max-height: unset;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  &::-webkit-scrollbar { width: 4px; }
  &::-webkit-scrollbar-track { background: transparent; }
  &::-webkit-scrollbar-thumb { background: var(--live-border); border-radius: 2px; }
  .live-empty {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 16px;
    width: 100%;
  }
}
.user-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  padding: 10px 6px;
  border-radius: 8px;
  background: var(--live-card-bg2);
  border: 1px solid var(--live-border);
  transition: border-color 0.2s;
  animation: fade-in-up 0.3s ease;
  &:hover {
    border-color: var(--live-accent);
    .user-card__actions { opacity: 1; }
  }
}
.user-card__name {
  font-size: 11px;
  color: var(--live-text);
  text-align: center;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  width: 100%;
}
.user-card__actions {
  display: flex;
  gap: 2px;
  opacity: 0;
  transition: opacity 0.2s;
}

// ===== 分页 =====
.pagination-wrap {
  margin-top: 8px;
  display: flex;
  justify-content: flex-end;
  ::v-deep .el-pagination {
    .el-pager li,
    .btn-prev,
    .btn-next {
      background: var(--live-card-bg2) !important;
      color: var(--live-text-dim) !important;
      border: 1px solid var(--live-border);
      &:hover { color: var(--live-green) !important; }
      &.active { background: var(--live-green) !important; color: #000 !important; border-color: var(--live-green) !important; }
    }
    .el-pagination__total { color: var(--live-text-dim); }
  }
}

// ===== 推流地址弹窗 =====
.stream-dialog-content {
  padding: 0 4px;
}
.stream-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  align-items: center;
}
.stream-loading-tip {
  padding: 20px;
  text-align: center;
  color: #909399;
}
.stream-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
}
.stream-name-label {
  font-size: 13px;
  color: #606266;
  flex-shrink: 0;
}
.stream-name-val {
  font-family: monospace;
  color: #409eff;
  font-weight: 600;
  font-size: 13px;
}
.stream-section {
  margin-top: 12px;
}
.stream-section-title {
  margin-bottom: 8px;
}
.stream-row {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
  gap: 8px;
}
.stream-label {
  min-width: 64px;
  font-size: 13px;
  color: #606266;
  flex-shrink: 0;
}
.stream-input { flex: 1; }

// ===== 通用按钮/文字 =====
.live-text-btn {
  color: var(--live-text-dim) !important;
  &:hover { color: var(--live-green) !important; }
}
.live-danger-btn {
  color: var(--live-text-dim) !important;
  &:hover { color: var(--live-red) !important; }
}
.live-icon-btn {
  background: transparent !important;
  border-color: var(--live-border) !important;
  color: var(--live-text-dim) !important;
  padding: 5px 8px !important;
  &:hover {
    border-color: var(--live-green) !important;
    color: var(--live-green) !important;
  }
}

// 空状态
.live-empty {
  padding: 32px 16px;
  text-align: center;
  color: var(--live-text-muted);
  i {
    font-size: 36px;
    display: block;
    margin-bottom: 8px;
    opacity: 0.4;
  }
  p {
    font-size: 13px;
    margin: 0;
  }
}

// ===== 全局 Element UI 深色覆盖 =====
::v-deep .el-loading-mask {
  background: rgba(12, 13, 18, 0.7);
}
::v-deep .el-loading-spinner .circular path,
::v-deep .el-loading-spinner .el-loading-text {
  stroke: var(--live-green);
  color: var(--live-green);
}
</style>

<style lang="scss">
// 中控台全屏页面（hideHeader 模式）：取消 app-main 内边距
.main-container.no-header {
  .app-main {
    padding: 0 !important;
    min-height: 100vh !important;
  }
}
</style>
