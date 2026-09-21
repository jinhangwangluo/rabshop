<template>
  <div class="console-wrap" :class="{ 'console-wrap--resizing': resizingPanel }">
    <!-- 独立中台的业务操作栏，不包含后台全局导航。 -->
    <div v-loading="infoLoading" class="console-header">
      <el-tooltip content="返回直播列表" placement="bottom">
        <el-button class="header-left live-icon-btn" :icon="ArrowLeft" aria-label="返回直播列表" @click="goBack" />
      </el-tooltip>

      <el-avatar
        v-if="roomInfo.cover"
        :src="roomInfo.cover"
        shape="square"
        :size="40"
        class="room-avatar"
      />
      <div v-else class="room-avatar-placeholder"><LegacyIcon name="el-icon-video-camera-solid" /></div>

      <div class="header-title-block">
        <h1 class="header-console-title">直播中台</h1>
        <div class="header-room-meta">
          <span class="header-room-name" :title="roomInfo.title">{{ roomInfo.title || '直播间' }}</span>
          <span class="header-anchor">主播：{{ roomInfo.anchor && roomInfo.anchor.name || '-' }}</span>
          <span class="header-room-id">ID {{ roomId }}</span>
        </div>
      </div>

      <el-tag v-if="roomInfo.status === 1" type="success" effect="light" class="status-tag live-tag">
        <span class="live-dot" />
        直播中
      </el-tag>
      <el-tag v-else-if="roomInfo.status === 0" type="info" class="status-tag">未开始</el-tag>
      <el-tag v-else type="info" class="status-tag">已结束</el-tag>

      <div class="header-actions">
        <!-- 外链重开复用状态切换，云推流保留原推流重建流程。 -->
        <el-button
          v-if="roomInfo.status === 0 || (roomInfo.status === 2 && isExternalStream)"
          type="success"
          :icon="VideoPlay"
          :loading="statusLoading"
          @click="handleSwitchStatus(1)"
        >开播</el-button>
        <el-button
          v-if="roomInfo.status === 2 && !isExternalStream"
          type="primary"
          :icon="VideoPlay"
          :loading="streamLoading"
          @click="handleCreateStream(false)"
        >重新开播</el-button>
        <el-button
          :class="hasPlayableStream ? 'stream-btn-active' : 'stream-btn-idle'"
          :icon="hasPlayableStream ? Connection : VideoPlay"
          @click="streamDialogVisible = true"
        >
          {{ isExternalStream ? '直播链接' : (streamInfo.stream_name ? '推流地址' : '生成推流') }}
        </el-button>
        <el-button class="drawer-btn" :icon="Present" @click="rewardDrawerVisible = true">打赏记录</el-button>
        <el-button class="drawer-btn" :icon="User" @click="userDrawerVisible = true">
          在线用户<span v-if="stats.online_count > 0" class="drawer-badge">{{ stats.online_count }}</span>
        </el-button>
        <el-button
          v-if="roomInfo.status === 1"
          type="danger"
          :icon="SwitchButton"
          :loading="statusLoading"
          @click="handleSwitchStatus(2)"
        >结束直播</el-button>
        <el-tooltip content="刷新直播信息" placement="bottom">
          <el-button :icon="Refresh" class="refresh-btn live-icon-btn" aria-label="刷新直播信息" @click="loadAll" />
        </el-tooltip>
      </div>
    </div>

    <div class="header-stats" aria-label="直播数据">
      <div class="hstat-item">
        <el-icon class="hstat-icon"><View /></el-icon>
        <div><span class="hstat-label">累计观看</span><span class="hstat-num">{{ stats.total_count || 0 }}</span></div>
      </div>
      <div class="hstat-item">
        <el-icon class="hstat-icon hstat-icon--online"><User /></el-icon>
        <div><span class="hstat-label">当前在线</span><span class="hstat-num">{{ stats.online_count || 0 }}</span></div>
      </div>
      <div class="hstat-item">
        <el-icon class="hstat-icon"><ChatDotRound /></el-icon>
        <div><span class="hstat-label">评论互动</span><span class="hstat-num">{{ stats.comment_count || 0 }}</span></div>
      </div>
      <div class="hstat-item">
        <el-icon class="hstat-icon hstat-icon--reward"><Present /></el-icon>
        <div><span class="hstat-label">打赏金额</span><span class="hstat-num price-num">¥{{ stats.reward_amount || '0.00' }}</span></div>
      </div>
    </div>

    <!-- 监看与运营工作区。 -->
    <div class="console-main">

      <!-- 监看下直接承接评论，不再留下撑满整列的空白。 -->
      <div ref="consoleCenterRef" class="console-center">
        <div class="live-card monitor-panel" :style="{ height: monitorPanelHeight + 'px' }">
          <div class="live-card__header monitor-header">
            <span class="live-card__title"><el-icon><VideoCamera /></el-icon>直播监看</span>
            <span class="monitor-mode">{{ isExternalStream ? '外链直播' : '云端推流' }}</span>
          </div>
          <div
            ref="playerWrapRef"
            class="player-wrap"
            tabindex="0"
            role="region"
            aria-label="直播播放器"
            @mouseenter="playerCtrlVisible = true"
            @mouseleave="playerCtrlVisible = false"
            @focusin="playerCtrlVisible = true"
            @focusout="playerCtrlVisible = false"
            @fullscreenchange="syncPlayerFullscreen"
          >
            <!-- 错误提示优先，在线人数仍保留在顶部统计，避免徽章被错误条遮挡。 -->
            <div v-if="!playerError" class="player-overlay-badges">
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
                <p class="offline-tip">{{ playerError ? '直播流未连接' : (roomInfo.status === 2 ? '直播已结束' : '暂未开播') }}</p>
              </div>
            </div>

            <!-- 连接中蒙层 -->
            <div v-if="playerLoading" class="player-loading-mask">
              <LegacyIcon name="el-icon-loading" />
              <span>连接中...</span>
            </div>

            <video
              ref="flvVideoRef"
              class="flv-video"
              autoplay
              muted
              @loadeddata="handleVideoReady"
              @error="handleVideoError"
            />

            <!-- 播放器控件不改变原有直播播放逻辑。 -->
            <transition name="ctrl-fade">
              <div v-show="playerCtrlVisible || !playerReady" class="player-ctrl-bar">
                <div class="ctrl-left">
                  <!-- 直播时不显示播放/暂停，只显示 LIVE 标识 -->
                  <template v-if="playerReady">
                    <div class="live-badge-ctrl">
                      <span class="live-dot-ctrl" />
                      {{ roomInfo.status === 1 ? 'LIVE' : '预览' }}
                    </div>
                  </template>
                  <template v-else>
                    <!-- 未直播时只显示刷新/重试按钮 -->
                    <el-tooltip content="重新连接直播流" placement="top">
                      <button type="button" class="ctrl-btn" aria-label="重新连接直播流" :disabled="playerLoading" @click="initFlvPlayer">
                        <el-icon :class="{ 'is-loading': playerLoading }"><component :is="playerLoading ? Loading : Refresh" /></el-icon>
                      </button>
                    </el-tooltip>
                  </template>
                </div>
                <div class="ctrl-right">
                  <!-- 音量控制 -->
                  <div
                    class="volume-wrap"
                    @mouseenter="volumeBarVisible = true"
                    @mouseleave="volumeBarVisible = false"
                    @focusin="volumeBarVisible = true"
                    @focusout="volumeBarVisible = false"
                  >
                    <el-tooltip :content="playerMuted ? '取消静音' : '静音'" placement="top">
                      <button type="button" class="ctrl-btn" :aria-label="playerMuted ? '取消静音' : '静音'" @click="toggleMute">
                        <el-icon><component :is="playerMuted ? Mute : Headset" /></el-icon>
                      </button>
                    </el-tooltip>
                    <transition name="vol-slide">
                      <div v-show="volumeBarVisible" class="volume-slider-wrap">
                        <el-slider
                          v-model="playerVolume"
                          vertical
                          height="80px"
                          :min="0"
                          :max="100"
                          :show-tooltip="false"
                          aria-label="播放音量"
                          @input="onVolumeChange"
                        />
                      </div>
                    </transition>
                  </div>
                  <!-- 全屏 -->
                  <el-tooltip :content="isFullscreen ? '退出全屏' : '全屏'" placement="top">
                    <button type="button" class="ctrl-btn" :aria-label="isFullscreen ? '退出全屏' : '全屏'" @click="toggleFullscreen">
                      <el-icon><component :is="isFullscreen ? Close : FullScreen" /></el-icon>
                    </button>
                  </el-tooltip>
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
          <div class="monitor-footer">
            <span v-if="playerReady" class="monitor-health"><span class="online-dot" />直播流正常</span>
            <span v-else-if="playerLoading" class="monitor-health is-pending"><el-icon class="is-loading"><Loading /></el-icon>连接中</span>
            <span v-else class="monitor-health is-offline"><el-icon><Warning /></el-icon>未连接</span>
            <span class="monitor-start">开播时间 {{ roomInfo.start_time || '-' }}</span>
          </div>
        </div>

        <div
          class="panel-resizer"
          role="separator"
          tabindex="0"
          aria-label="调整监看和评论区域高度"
          aria-orientation="horizontal"
          :aria-valuenow="monitorPanelHeight"
          :aria-valuemin="220"
          :aria-valuemax="maxMonitorPanelHeight"
          @mousedown="handlePanelResizeStart"
          @keydown.up.prevent="adjustMonitorPanelHeight(-20)"
          @keydown.down.prevent="adjustMonitorPanelHeight(20)"
        >
          <span class="panel-resizer__line" />
        </div>

        <div class="live-card comment-panel">
          <div class="live-card__header">
            <span class="live-card__title"><LegacyIcon name="el-icon-chat-dot-round" /> 实时评论</span>
            <div class="live-card__extra">
              <span class="comment-connection" :class="{ 'is-connected': wsConnected }"><span class="online-dot" />{{ wsConnected ? '已连接' : '未连接' }}</span>
              <span class="panel-total"><LegacyIcon name="el-icon-chat-dot-round" />{{ commentsTotal }} 条</span>
              <el-tooltip content="刷新评论" placement="top">
                <el-button size="small" class="live-icon-btn" :icon="Refresh" aria-label="刷新评论" @click="loadComments" />
              </el-tooltip>
            </div>
          </div>
          <div class="comment-panel__body">
            <div v-loading="commentsLoading" class="comment-stream">
              <div v-if="!commentsLoading && !commentsList.length" class="live-empty">
                <LegacyIcon name="el-icon-chat-line-square" />
                <p>暂无评论</p>
              </div>
              <div
                v-for="item in commentsList"
                :key="item.id || item.msg_id"
                class="comment-bubble"
              >
                <el-avatar :size="30" :src="getCommentAvatar(item)" :icon="UserFilled" class="bubble-avatar" />
                <div class="bubble-main">
                  <div class="bubble-header">
                    <span class="bubble-name">{{ item.nickname || item.user_id }}</span>
                    <span class="bubble-time">{{ item.create_time || item.send_time }}</span>
                  </div>
                  <div class="bubble-body">
                    <div class="bubble-content">{{ item.content }}</div>
                    <el-tooltip content="删除评论" placement="top">
                      <el-button
                        size="small"
                        text
                        :icon="Delete"
                        class="live-icon-btn live-danger-btn bubble-del"
                        aria-label="删除评论"
                        @click="handleDeleteComment(item.id)"
                      />
                    </el-tooltip>
                  </div>
                </div>
              </div>
            </div>
            <div class="comment-reply">
              <div class="comment-reply__user">
                <el-avatar :size="28" :src="adminInfo.avatar" :icon="UserFilled" />
                <span>{{ adminReplyUserText }}</span>
              </div>
              <div class="comment-reply__editor">
                <el-input
                  v-model="commentReplyInput"
                  type="textarea"
                  :rows="2"
                  resize="none"
                  maxlength="200"
                  show-word-limit
                  aria-label="回复直播间评论"
                  :disabled="!canSendAdminComment || commentReplySending"
                  :placeholder="adminReplyPlaceholder"
                  @keyup.enter.exact="handleSendCommentReply"
                />
                <el-button
                  type="primary"
                  :icon="Promotion"
                  :loading="commentReplySending"
                  :disabled="!canSendAdminComment"
                  @click="handleSendCommentReply"
                >发送</el-button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 商品独立通高，列表滚动与底部分页互不挤占。 -->
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
              size="small"
              :icon="Sort"
              @click="goodsSortMode = true"
            >排序</el-button>
            <template v-else>
              <el-button
                size="small"
                type="success"
                :icon="Check"
                :loading="goodsSortLoading"
                @click="saveSortOrder"
              >保存排序</el-button>
              <el-button
                size="small"
                :icon="Close"
                @click="cancelSortMode"
              >取消</el-button>
            </template>
            <el-tooltip v-if="!goodsSortMode" content="刷新挂载商品" placement="top">
              <el-button
                size="small"
                class="live-icon-btn"
                :icon="Refresh"
                aria-label="刷新挂载商品"
                :loading="goodsLoading"
                @click="loadGoods"
              />
            </el-tooltip>
            <el-button
              v-if="!goodsSortMode"
              size="small"
              type="primary"
              :icon="Plus"
              @click="handleAddGoods"
            >添加商品</el-button>
          </div>
        </div>

        <div v-loading="goodsLoading" class="goods-list">
          <div v-if="!goodsLoading && !goodsList.length" class="live-empty">
            <LegacyIcon name="el-icon-shopping-bag-1" />
            <p>暂无挂载商品</p>
          </div>
          <draggable
            item-key="id"
            :list="goodsSorted"
            :disabled="!goodsSortMode"
            handle=".goods-drag-handle"
            animation="200"
            ghost-class="goods-drag-ghost"
          >
            <template #item="{ element: item }">
              <div
                :key="item.id"
                class="goods-item"
                :class="{ 'goods-item--off': item.status === 0, 'goods-item--sort': goodsSortMode, 'goods-item--explaining': item.is_explaining === 1 }"
              >
                <div v-if="goodsSortMode" class="goods-drag-handle">
                  <LegacyIcon name="el-icon-rank" />
                </div>
                <div class="goods-item__shelf-line" :class="{ active: item.status === 1 }" />
                <div class="goods-item__cover">
                  <el-image v-if="item.goods_cover" :src="item.goods_cover" fit="cover" class="goods-item__cover-image" />
                  <div v-else class="goods-item__cover-placeholder">
                    <LegacyIcon :name="GOODS_TYPE_ICON[item.goods_type] || 'el-icon-document'" />
                  </div>
                </div>
                <div class="goods-item__body">
                  <div class="goods-item__top">
                    <el-tag
                      :type="GOODS_TYPE_TAG[item.goods_type] || 'info'"
                      size="small"
                      effect="light"
                      class="goods-type-tag"
                    >
                      {{ GOODS_TYPE_SHORT_LABEL[item.goods_type] || item.goods_type }}
                    </el-tag>
                    <span class="goods-title" :title="item.goods_title">{{ item.goods_title }}</span>
                    <el-tag
                      v-if="item.is_explaining === 1"
                      type="danger"
                      size="small"
                      effect="light"
                      class="goods-explaining-tag"
                    >
                      讲解中
                    </el-tag>
                  </div>
                  <div class="goods-item__bottom">
                    <span class="goods-price">¥{{ item.goods_price }}</span>
                    <div v-if="!goodsSortMode" class="goods-item__actions">
                      <el-switch
                        v-model="item.status"
                        :active-value="1"
                        :inactive-value="0"
                        :aria-label="'上架状态：' + item.goods_title"
                        class="goods-switch"
                        @change="handleGoodsStatusChange(item)"
                      />
                      <el-button
                        size="small"
                        text
                        :class="item.is_explaining === 1 ? 'live-danger-btn' : 'live-text-btn'"
                        @click="handleGoodsExplainingChange(item)"
                      >
                        {{ item.is_explaining === 1 ? '取消讲解' : '讲解' }}
                      </el-button>
                      <el-tooltip content="编辑商品" placement="top">
                        <el-button size="small" text :icon="Edit" class="live-icon-btn" aria-label="编辑商品" @click="handleEditGoods(item.id)" />
                      </el-tooltip>
                      <el-tooltip content="移除商品" placement="top">
                        <el-button size="small" text :icon="Delete" class="live-icon-btn live-danger-btn" aria-label="移除商品" @click="handleDeleteGoods(item.id)" />
                      </el-tooltip>
                    </div>
                    <div v-else class="goods-sort-hint">
                      <span class="sort-num">排序：{{ item.sort }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </draggable>
        </div>

        <div class="pagination-wrap">
          <el-pagination
            :current-page="goodsPage"
            :page-size="goodsLimit"
            :pager-count="5"
            small
            layout="total, prev, pager, next"
            :total="goodsTotal"
            @current-change="(v) => { goodsPage = v; loadGoods() }"
          />
        </div>
      </div>

    </div>

    <!-- ===== 打赏抽屉 ===== -->
    <el-drawer
      v-model="rewardDrawerVisible"
      title="打赏记录"
      direction="rtl"
      size="min(480px, 100vw)"
      class="live-drawer"
    >
      <div class="drawer-body">
        <div class="drawer-toolbar">
          <span class="panel-total"><LegacyIcon name="el-icon-present" /> {{ rewardTotal }} 条记录</span>
          <el-tooltip content="刷新打赏记录" placement="top">
            <el-button size="small" class="live-icon-btn" :icon="Refresh" aria-label="刷新打赏记录" @click="loadReward" />
          </el-tooltip>
        </div>
        <div v-loading="rewardLoading" class="reward-stream">
          <div v-if="!rewardLoading && !rewardList.length" class="live-empty">
            <LegacyIcon name="el-icon-present" />
            <p>暂无打赏记录</p>
          </div>
          <div v-for="item in rewardList" :key="item.id" class="reward-item">
            <div class="reward-gift">
              <el-image v-if="item.gift_icon" :src="item.gift_icon" class="gift-img" fit="contain" />
              <LegacyIcon v-else name="el-icon-present" class="gift-icon-default" />
            </div>
            <div class="reward-info">
              <span class="reward-name">送礼人：{{ getRewardUserText(item) }}</span>
              <span class="reward-gift-name">
                礼物：{{ item.gift_name || '未知礼物' }}
                <em v-if="item.quantity" class="reward-quantity">x{{ item.quantity }}</em>
              </span>
              <span class="reward-time">{{ item.send_time || item.create_time }}</span>
            </div>
            <div class="reward-amount">¥{{ formatRewardAmount(item) }}</div>
          </div>
        </div>
        <div class="pagination-wrap">
          <el-pagination
            :current-page="rewardPage"
            :page-size="rewardLimit"
            :pager-count="5"
            layout="total, prev, pager, next"
            :total="rewardTotal"
            small
            @current-change="(v) => { rewardPage = v; loadReward() }"
          />
        </div>
      </div>
    </el-drawer>

    <!-- ===== 用户抽屉 ===== -->
    <el-drawer
      v-model="userDrawerVisible"
      title="在线用户"
      direction="rtl"
      size="min(640px, 100vw)"
      class="live-drawer"
    >
      <div class="drawer-body">
        <div class="drawer-toolbar">
          <span class="panel-total">
            <span class="online-dot" />
            在线 {{ stats.online_count || 0 }} 人 · 记录 {{ usersTotal }} 条
          </span>
          <div class="drawer-toolbar__right">
            <el-tag v-if="wsConnected" type="success" size="small" effect="light">已连接</el-tag>
            <el-tag v-else type="info" size="small">未连接</el-tag>
            <el-tooltip content="刷新在线用户" placement="top">
              <el-button size="small" class="live-icon-btn" :icon="Refresh" aria-label="刷新在线用户" @click="handleRefreshUsers" />
            </el-tooltip>
            <el-button
              v-if="!wsConnected"
              size="small"
              type="danger"
              :icon="Connection"
              :loading="wsReconnecting"
              @click="handleWsReconnect"
            >重连</el-button>
          </div>
        </div>
        <div v-loading="usersLoading" class="user-list">
          <div v-if="!usersLoading && !usersList.length" class="live-empty">
            <LegacyIcon name="el-icon-user" />
            <p>暂无在线用户</p>
          </div>
          <div v-for="user in usersList" :key="user.id" class="user-list-item">
            <div class="user-list-item__avatar">
              <el-avatar :size="46" :src="getUserAvatar(user)" :icon="UserFilled" />
              <span class="user-list-item__online" :class="{ 'is-offline': !isUserOnline(user) }" />
            </div>
            <div class="user-list-item__main">
              <div class="user-list-item__top">
                <span class="user-list-item__name">{{ getUserName(user) }}</span>
                <el-tag :type="isUserOnline(user) ? 'success' : 'info'" size="small" effect="light">
                  {{ isUserOnline(user) ? '在线' : '已离开' }}
                </el-tag>
                <el-tag v-if="isUserMuted(user)" type="danger" size="small" effect="light">禁言中</el-tag>
              </div>
              <div class="user-list-item__meta">
                <span>用户ID：{{ user.user_id || user.id || '-' }}</span>
                <span>进入：{{ getUserEnterTime(user) }}</span>
                <span>进入次数：{{ user.online_count || 0 }}</span>
              </div>
              <div v-if="isUserMuted(user) && user.mute_end_time" class="user-list-item__muted">
                禁言至：{{ user.mute_end_time }}
              </div>
            </div>
            <div class="user-list-item__actions">
              <el-button
                size="small"
                text
                class="live-text-btn"
                :disabled="!isUserOnline(user)"
                @click="handleMuteUser(user)"
              >禁言</el-button>
              <el-button
                size="small"
                text
                class="live-danger-btn"
                :disabled="!isUserOnline(user)"
                @click="handleKickUser(user)"
              >踢出</el-button>
            </div>
          </div>
        </div>
        <div class="pagination-wrap">
          <el-pagination
            :current-page="usersPage"
            :page-size="usersLimit"
            :pager-count="5"
            layout="total, prev, pager, next"
            :total="usersTotal"
            small
            @current-change="(v) => { usersPage = v; loadUsers() }"
          />
        </div>
      </div>
    </el-drawer>

    <!-- ===== 推流地址弹窗 ===== -->
    <el-dialog
      v-model="streamDialogVisible"
      title="推流地址管理"
      width="min(760px, calc(100vw - 32px))"
      class="stream-dialog"
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
            <div class="stream-section-title"><el-tag type="warning" size="small">外链直播</el-tag></div>
            <div class="stream-row">
              <span class="stream-label">直播链接</span>
              <el-input :model-value="livePlayUrl" readonly class="stream-input" />
              <el-button size="small" :icon="CopyDocument" @click="copyText(livePlayUrl)">复制</el-button>
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
            <div class="stream-section-title"><el-tag type="primary" size="small">阿里云</el-tag></div>
            <template v-if="streamInfo.ali_push_base">
              <div class="stream-row">
                <span class="stream-label">推流地址</span>
                <el-input :model-value="streamInfo.ali_push_base" readonly class="stream-input" />
                <el-button size="small" :icon="CopyDocument" @click="copyText(streamInfo.ali_push_base)">复制</el-button>
              </div>
              <div class="stream-row">
                <span class="stream-label">推流 Key</span>
                <el-input :model-value="streamInfo.ali_push_key" readonly class="stream-input" />
                <el-button size="small" :icon="CopyDocument" @click="copyText(streamInfo.ali_push_key)">复制</el-button>
              </div>
            </template>
            <div v-if="streamInfo.ali_pull_url" class="stream-row">
              <span class="stream-label">播流地址</span>
              <el-input :model-value="streamInfo.ali_pull_url" readonly class="stream-input" />
              <el-button size="small" :icon="CopyDocument" @click="copyText(streamInfo.ali_pull_url)">复制</el-button>
            </div>
          </div>
          <!-- 七牛云 -->
          <div v-if="streamInfo.qiniu_push_base || streamInfo.qiniu_pull_url" class="stream-section">
            <div class="stream-section-title"><el-tag type="warning" size="small">七牛云</el-tag></div>
            <template v-if="streamInfo.qiniu_push_base">
              <div class="stream-row">
                <span class="stream-label">推流地址</span>
                <el-input :model-value="streamInfo.qiniu_push_base" readonly class="stream-input" />
                <el-button size="small" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_push_base)">复制</el-button>
              </div>
              <div class="stream-row">
                <span class="stream-label">推流 Key</span>
                <el-input :model-value="streamInfo.qiniu_push_key" readonly class="stream-input" />
                <el-button size="small" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_push_key)">复制</el-button>
              </div>
            </template>
            <div v-if="streamInfo.qiniu_pull_url" class="stream-row">
              <span class="stream-label">播流地址</span>
              <el-input :model-value="streamInfo.qiniu_pull_url" readonly class="stream-input" />
              <el-button size="small" :icon="CopyDocument" @click="copyText(streamInfo.qiniu_pull_url)">复制</el-button>
            </div>
          </div>
        </template>
        <el-empty v-else description="暂无推流地址，请点击「生成推流地址」" :image-size="80" />
      </div>
    </el-dialog>

    <!-- ===== 添加/编辑商品弹窗 ===== -->
    <LiveGoodsFormDialog
      v-model:visible="goodsDialogVisible"
      :title="goodsDialogTitle"
      :live-room-id="roomId"
      :edit-id="goodsEditId"
      :console-mode="true"
      @success="loadGoods"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import flvjs from 'flv.js'
import draggable from 'vuedraggable'
import { storeToRefs } from 'pinia'
import {
  ArrowLeft, ChatDotRound, Check, Close, Connection, CopyDocument, Delete, Edit,
  FullScreen, Headset, Loading, Mute, Plus, Present, Promotion, Refresh, Sort,
  SwitchButton, User, UserFilled, VideoCamera, VideoPlay, View, Warning
} from '@element-plus/icons-vue'
import {
  getRoomDetail,
  switchRoomStatus,
  getRoomStreamInfo,
  createRoomStream,
  closeRoomStream,
  getLiveGoodsList,
  deleteLiveGoods,
  showLiveGoods,
  setLiveGoodsExplaining,
  batchSortLiveGoods,
  getLiveRoomUsers,
  muteRoomUser,
  kickRoomUser,
  getLiveComments,
  deleteLiveComment,
  sendLiveComment,
  getLiveRewardList,
  getLiveRoomStats
} from '@/api/live'
import { useUserStore } from '@/store/modules/user'
import { useWebsocketStore } from '@/store/modules/websocket'
import LiveGoodsFormDialog from './components/LiveGoodsFormDialog.vue'

defineOptions({ name: 'LiveConsole' })

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const userStore = useUserStore()
const websocketStore = useWebsocketStore()
const { adminInfo } = storeToRefs(userStore)
const {
  connected: wsConnected,
  onlineUsers: wsOnlineUsers,
  chatMessages: wsChatMessages,
  consoleActions: wsConsoleActions
} = storeToRefs(websocketStore)

const flvVideoRef = ref(null)
const playerWrapRef = ref(null)
const consoleCenterRef = ref(null)
const roomId = ref(0)
const streamDialogVisible = ref(false)
const rewardDrawerVisible = ref(false)
const userDrawerVisible = ref(false)
const flvPlayer = shallowRef(null)
const playerReady = ref(false)
const playerError = ref('')
const playerLoading = ref(false)
const infoLoading = ref(false)
const statusLoading = ref(false)
const roomInfo = ref({})
const stats = ref({})
const streamInfo = ref({})
const streamLoading = ref(false)
const closeStreamLoading = ref(false)
const goodsLoading = ref(false)
const goodsList = ref([])
const goodsTotal = ref(0)
const goodsPage = ref(1)
const goodsLimit = ref(20)
const goodsDialogVisible = ref(false)
const goodsDialogTitle = ref('添加商品')
const goodsEditId = ref(0)

// 挂载商品类型的展示映射：article=单篇内容、special=付费专题、product=平台商品
const GOODS_TYPE_SHORT_LABEL = {
  article: '内容',
  special: '专题',
  product: '商品'
}
const GOODS_TYPE_TAG = {
  article: 'primary',
  special: 'warning',
  product: 'success'
}
const GOODS_TYPE_ICON = {
  article: 'el-icon-document',
  special: 'el-icon-collection',
  product: 'el-icon-shopping-cart-2'
}
const goodsSortMode = ref(false)
const goodsSortLoading = ref(false)
const usersLoading = ref(false)
const usersList = ref([])
const usersTotal = ref(0)
const usersPage = ref(1)
const usersLimit = ref(20)
const commentsLoading = ref(false)
const commentsList = ref([])
const commentsTotal = ref(0)
const commentsPage = ref(1)
const commentsLimit = ref(20)
const commentReplyInput = ref('')
const commentReplySending = ref(false)
const rewardLoading = ref(false)
const rewardList = ref([])
const rewardTotal = ref(0)
const rewardPage = ref(1)
const rewardLimit = ref(20)
const wsReconnecting = ref(false)
const playerCtrlVisible = ref(false)
const playerMuted = ref(true)
const playerVolume = ref(0)
const volumeBarVisible = ref(false)
const isFullscreen = ref(false)
const liveDurationNow = ref(Date.now())
const monitorPanelHeight = ref(380)
const monitorPanelAdjusted = ref(false)
const resizingPanel = ref(false)
const resizeStartY = ref(0)
const resizeStartHeight = ref(0)
const centerHeight = ref(0)
let liveDurationTimer = null
let panelResizeObserver = null

const maxMonitorPanelHeight = computed(() => Math.max(220, Math.min(
  centerHeight.value - 240 - 10,
  Math.floor(centerHeight.value * 0.62)
)))

const goodsSorted = computed(() => {
  if (goodsSortMode.value) return goodsList.value
  return [...goodsList.value].sort((a, b) => b.status - a.status)
})

const livePlayUrl = computed(() => streamInfo.value.live_play_url ||
  streamInfo.value.external_play_url ||
  roomInfo.value.live_play_url ||
  roomInfo.value.external_play_url ||
  streamInfo.value.ali_pull_url ||
  streamInfo.value.qiniu_pull_url ||
  roomInfo.value.ali_pull_url ||
  roomInfo.value.qiniu_pull_url ||
  '')

const isExternalStream = computed(() => String(streamInfo.value.stream_type || roomInfo.value.stream_type || '') === 'external')

const hasPlayableStream = computed(() => !!(streamInfo.value.stream_name || livePlayUrl.value))

const isFlvPlayUrl = computed(() => {
  const url = String(livePlayUrl.value || '').split('?')[0]
  return /\.flv$/i.test(url)
})

const liveElapsedText = computed(() => {
  if (roomInfo.value.status !== 1 || !roomInfo.value.start_time) {
    return ''
  }
  const t = new Date(String(roomInfo.value.start_time).replace(/-/g, '/')).getTime()
  if (Number.isNaN(t)) {
    return roomInfo.value.start_time
  }
  const sec = Math.max(0, Math.floor((liveDurationNow.value - t) / 1000))
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(h)}:${pad(m)}:${pad(s)}`
})

const canSendAdminComment = computed(() => Number((adminInfo.value && adminInfo.value.user_id) || 0) > 0)

const adminReplyUserText = computed(() => {
  if (!canSendAdminComment.value) return '未绑定用户'
  return `绑定用户ID：${adminInfo.value.user_id}`
})

const adminReplyPlaceholder = computed(() => (
  canSendAdminComment.value
    ? '以绑定用户身份回复直播间评论'
    : '当前管理员未绑定用户，请先到管理员管理中绑定用户'
))

/** 返回直播列表；独立新窗口没有可用的上一条页面历史。 */
function goBack() {
  router.push({ name: 'LiveRoomList' })
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
    liveDurationNow.value = Date.now()
    liveDurationTimer = setInterval(() => {
      liveDurationNow.value = Date.now()
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
  // 单独收敛确认取消，不捕获后续业务接口的错误。
  proxy.$confirm(`确定要${actionMap[status]}吗？`, '提示', { type: 'warning' }).then(() => {
    statusLoading.value = true
    switchRoomStatus(roomId.value, status).then(res => {
      const data = res.data || {}
      proxy?.$message?.success(data.msg || '操作成功')
      loadRoomInfo()
      if (status === 1) {
        if (data.stream_info) {
          streamInfo.value = data.stream_info
        } else {
          loadStreamInfo()
        }
        if (data.stream_warn) {
          proxy?.$message?.warning(data.stream_warn)
        }
        websocketStore.sendConsoleAction({ action: 'start_live', actionData: {}}).catch(() => {})
      } else {
        websocketStore.sendConsoleAction({ action: 'end_live', actionData: {}}).catch(() => {})
      }
    }).finally(() => {
      statusLoading.value = false
    })
  }, () => {})
}

function handleCreateStream(force) {
  if (isExternalStream.value) {
    proxy?.$message?.warning('外链直播无需生成推流地址')
    return
  }
  const isEnded = roomInfo.value.status === 2
  const tip = force
    ? '强制刷新将重新生成推流地址，原地址将失效。确认继续？'
    : isEnded
      ? '直播已结束，生成推流地址将自动重新开播。确认继续？'
      : '确认为该直播间生成推流地址？'
  // 取消确认不触发状态切换和推流重建。
  proxy.$confirm(tip, '提示', { type: 'warning' }).then(() => {
    streamLoading.value = true
    const doCreate = () => {
      createRoomStream(roomId.value, force || false).then(res => {
        proxy?.$message?.success('推流地址生成成功')
        streamInfo.value = res.data || {}
        loadRoomInfo()
        loadStats()
      }).catch(() => {
        proxy?.$message?.error('推流地址生成失败')
      }).finally(() => {
        streamLoading.value = false
      })
    }
    if (isEnded) {
      switchRoomStatus(roomId.value, 1).then(() => {
        roomInfo.value = { ...roomInfo.value, status: 1 }
        proxy?.$message?.success('已自动开播')
        doCreate()
      }).catch(() => {
        streamLoading.value = false
        proxy?.$message?.error('开播失败，请手动开播后重试')
      })
    } else {
      doCreate()
    }
  }, () => {})
}

function handleCloseStream() {
  proxy.$confirm('确认关闭推流？关闭后观众将无法继续观看', '警告', { type: 'warning' }).then(() => {
    closeStreamLoading.value = true
    closeRoomStream(roomId.value).then(() => {
      proxy?.$message?.success('推流已关闭')
      loadStreamInfo()
    }).finally(() => {
      closeStreamLoading.value = false
    })
  })
}

function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => { proxy?.$message?.success('复制成功') })
  } else {
    const el = document.createElement('input')
    el.value = text
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    proxy?.$message?.success('复制成功')
  }
}

function loadGoods() {
  goodsLoading.value = true
  getLiveGoodsList({ live_room_id: roomId.value, page: goodsPage.value, limit: goodsLimit.value }).then(res => {
    const list = res.data.list || res.data || []
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
    proxy?.$message?.success('状态更新成功')
    if (row.status !== 1) {
      row.is_explaining = 0
    }
    websocketStore.sendConsoleAction({
      action: 'goods_shelf',
      actionData: { goods_id: row.id, status: row.status }
    }).catch(() => {})
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function handleGoodsExplainingChange(row) {
  if (row.status !== 1) {
    proxy?.$message?.warning('请先上架商品后再设置正在讲解')
    return
  }

  const nextStatus = row.is_explaining === 1 ? 0 : 1
  setLiveGoodsExplaining(row.id, nextStatus).then(() => {
    proxy?.$message?.success(nextStatus === 1 ? '已设置为正在讲解' : '已取消讲解')
    goodsList.value = goodsList.value.map(item => ({
      ...item,
      is_explaining: item.id === row.id ? nextStatus : (nextStatus === 1 ? 0 : item.is_explaining)
    }))
    websocketStore.sendConsoleAction({
      action: 'goods_explaining',
      actionData: { goods_id: row.id, is_explaining: nextStatus }
    }).catch(() => {})
  })
}

function handleDeleteGoods(id) {
  proxy.$confirm('确定要移除该挂载商品吗？', '提示', { type: 'warning' }).then(() => {
    deleteLiveGoods(id).then(() => {
      proxy?.$message?.success('移除成功')
      loadGoods()
    })
  })
}

function saveSortOrder() {
  goodsSortLoading.value = true
  const sortData = goodsList.value.map((item, index) => ({
    id: item.id,
    sort: goodsList.value.length - index
  }))
  batchSortLiveGoods(roomId.value, sortData).then(() => {
    proxy?.$message?.success('排序保存成功')
    goodsSortMode.value = false
    loadGoods()
  }).catch(() => {
    proxy?.$message?.error('排序保存失败')
  }).finally(() => {
    goodsSortLoading.value = false
  })
}

function cancelSortMode() {
  goodsSortMode.value = false
  loadGoods()
}

function handlePanelResizeStart(event) {
  if (event.button !== 0) return
  event.preventDefault()
  resizingPanel.value = true
  monitorPanelAdjusted.value = true
  resizeStartY.value = event.clientY
  resizeStartHeight.value = monitorPanelHeight.value
  window.addEventListener('mousemove', handlePanelResizing)
  window.addEventListener('mouseup', handlePanelResizeEnd)
}

function handlePanelResizing(event) {
  if (!resizingPanel.value) return
  const center = consoleCenterRef.value
  if (!center) return
  const deltaY = event.clientY - resizeStartY.value
  centerHeight.value = center.clientHeight
  constrainMonitorPanelHeight(resizeStartHeight.value + deltaY)
}

/** 限制监看区高度，height 为像素值，确保评论及回复栏始终可操作。 */
function constrainMonitorPanelHeight(height) {
  monitorPanelHeight.value = Math.min(Math.max(height, 220), maxMonitorPanelHeight.value)
}

/** 使用键盘调整分隔条，delta 为像素增量。 */
function adjustMonitorPanelHeight(delta) {
  monitorPanelAdjusted.value = true
  constrainMonitorPanelHeight(monitorPanelHeight.value + delta)
}

function handlePanelResizeEnd() {
  resizingPanel.value = false
  removePanelResizeListeners()
}

function removePanelResizeListeners() {
  window.removeEventListener('mousemove', handlePanelResizing)
  window.removeEventListener('mouseup', handlePanelResizeEnd)
}

function loadUsers() {
  usersLoading.value = true
  getLiveRoomUsers(roomId.value, { page: usersPage.value, limit: usersLimit.value }).then(res => {
    usersList.value = res.data.list || res.data || []
    usersTotal.value = res.data.total || 0
  }).finally(() => { usersLoading.value = false })
}

function handleMuteUser(row) {
  proxy.$confirm(`确定禁言用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    websocketStore.muteUser({ userId: row.user_id || row.id, duration: 3600, reason: '' })
      .then(() => {
        proxy?.$message?.success('禁言指令已发送')
      })
      .catch(err => {
        console.error(err)
        proxy?.$message?.error('禁言失败，请重试')
      })
  })
}

function handleKickUser(row) {
  proxy.$confirm(`确定踢出用户「${row.nickname || row.user_id}」吗？`, '提示', { type: 'warning' }).then(() => {
    websocketStore.kickUser({ userId: row.user_id || row.id })
      .then(() => {
        proxy?.$message?.success('踢出指令已发送')
        loadUsers()
      })
      .catch(err => {
        console.error(err)
        proxy?.$message?.error('踢出失败，请重试')
      })
  })
}

function loadComments() {
  commentsLoading.value = true
  getLiveComments(roomId.value, { page: commentsPage.value, limit: commentsLimit.value }).then(res => {
    commentsList.value = res.data.list || res.data || []
    commentsTotal.value = res.data.total || 0
  }).finally(() => { commentsLoading.value = false })
}

function prependComment(comment) {
  if (!comment || (!comment.id && !comment.msg_id)) return
  const exists = commentsList.value.some(item => {
    if (comment.id && item.id) return Number(item.id) === Number(comment.id)
    return item.msg_id && comment.msg_id && item.msg_id === comment.msg_id
  })
  if (exists) return
  commentsList.value = [comment, ...commentsList.value].slice(0, commentsLimit.value)
  commentsTotal.value += 1
}

function handleSendCommentReply() {
  const content = commentReplyInput.value.trim()
  if (!canSendAdminComment.value) {
    proxy?.$message?.warning('当前管理员未绑定用户，请先到管理员管理中绑定用户')
    return
  }
  if (!content) {
    proxy?.$message?.warning('请输入回复内容')
    return
  }
  if (commentReplySending.value) return
  commentReplySending.value = true
  sendLiveComment(roomId.value, { content }).then(res => {
    proxy?.$message?.success(res.message || '发送成功')
    commentReplyInput.value = ''
    if (res.data) {
      prependComment(res.data)
    } else {
      loadComments()
    }
    loadStats()
  }).finally(() => {
    commentReplySending.value = false
  })
}

function handleDeleteComment(commentId) {
  if (!commentId) return
  proxy.$confirm('确定要删除该评论吗？', '提示', { type: 'warning' }).then(() => {
    deleteLiveComment(roomId.value, commentId).then(() => {
      proxy?.$message?.success('删除成功')
      loadComments()
    })
  })
}

function handleRefreshUsers() {
  if (!wsConnected.value) {
    proxy?.$message?.warning('WebSocket 未连接，无法获取实时用户列表')
    return
  }
  websocketStore.queryOnlineUsers().catch(err => {
    console.error(err)
    proxy?.$message?.error('获取用户列表失败')
  })
}

function handleWsReconnect() {
  if (wsReconnecting.value) return
  wsReconnecting.value = true
  websocketStore.reconnectWebSocket()
  setTimeout(() => {
    wsReconnecting.value = false
  }, 3000)
}

function loadReward() {
  rewardLoading.value = true
  getLiveRewardList({ live_room_id: roomId.value, page: rewardPage.value, limit: rewardLimit.value }).then(res => {
    rewardList.value = res.data.list || res.data || []
    rewardTotal.value = res.data.total || 0
  }).finally(() => { rewardLoading.value = false })
}

function getRewardUserText(item) {
  if (!item) return '未知用户'
  return item.nickname || item.user_nickname || (item.user_id ? '用户' + item.user_id : '未知用户')
}

function formatRewardAmount(item) {
  if (!item) return '0.00'
  const value = item.exchange_amount || item.total_amount || item.gift_price || item.price || 0
  const numberValue = Number(value)
  if (Number.isNaN(numberValue)) return value
  return numberValue.toFixed(2)
}

function initFlvPlayer() {
  destroyFlvPlayer()
  const url = livePlayUrl.value
  if (!url) {
    playerError.value = isExternalStream.value ? '暂无直播链接，请先在直播间编辑页填写' : '暂无拉流地址，请先生成推流地址'
    return
  }
  const videoEl = flvVideoRef.value
  if (!videoEl) return
  if (isExternalStream.value && !isFlvPlayUrl.value) {
    // 外链地址配置成功不代表视频可播放，健康状态以媒体加载事件为准。
    playerLoading.value = true
    videoEl.src = url
    videoEl.load()
    const playResult = videoEl.play()
    if (playResult && typeof playResult.catch === 'function') {
      playResult.catch(() => {})
    }
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
  flvPlayer.value = player
}

function destroyFlvPlayer() {
  if (flvPlayer.value) {
    flvPlayer.value.pause()
    flvPlayer.value.unload()
    flvPlayer.value.detachMediaElement()
    flvPlayer.value.destroy()
    flvPlayer.value = null
  }
  playerReady.value = false
  playerError.value = ''
  playerLoading.value = false
}

function toggleMute() {
  const video = flvVideoRef.value
  if (!video) return
  playerMuted.value = !playerMuted.value
  video.muted = playerMuted.value
  if (!playerMuted.value && playerVolume.value === 0) {
    playerVolume.value = 50
    video.volume = 0.5
  }
}

function onVolumeChange(val) {
  const video = flvVideoRef.value
  if (!video) return
  video.volume = val / 100
  playerMuted.value = val === 0
  video.muted = playerMuted.value
}

/** 原生媒体加载后同步监看状态。 */
function handleVideoReady() {
  playerReady.value = true
  playerLoading.value = false
  playerError.value = ''
}

/** 外链失效或格式不支持时展示真实错误状态。 */
function handleVideoError() {
  playerReady.value = false
  playerLoading.value = false
  playerError.value = '直播流暂不可播放，请检查直播链接或稍后重试'
}

/** 同步浏览器实际全屏状态，包括通过 Escape 退出全屏。 */
function syncPlayerFullscreen() {
  isFullscreen.value = document.fullscreenElement === playerWrapRef.value
}

function toggleFullscreen() {
  const wrap = playerWrapRef.value
  if (!wrap) return
  const request = document.fullscreenElement ? document.exitFullscreen?.() : wrap.requestFullscreen?.()
  request?.catch(() => proxy?.$message?.warning('当前窗口无法切换全屏'))
}

function getUserAvatar(user) {
  return user.avatar || user.user_avatar || user.headimgurl || user.head_img || ''
}

function getUserName(user) {
  return user.nickname || user.user_nickname || user.name || `用户 ${user.user_id || user.id || '-'}`
}

function isUserOnline(user) {
  return Number(user && user.is_online) !== 0
}

function isUserMuted(user) {
  return Number(user && user.is_muted) === 1
}

function getUserEnterTime(user) {
  return user.last_enter_time || user.join_time || '-'
}

function getCommentAvatar(item) {
  return item.avatar || item.user_avatar || item.headimgurl || item.head_img || ''
}

watch(wsOnlineUsers, newVal => {
  if (newVal.length > 0) {
    usersList.value = newVal.map(item => ({
      id: item.user_id,
      user_id: item.user_id,
      nickname: item.nickname,
      avatar: item.avatar,
      is_online: 1,
      is_muted: item.is_muted || 0,
      mute_end_time: item.mute_end_time || '',
      online_count: item.online_count || 1,
      join_time: item.last_enter_time
    }))
    usersTotal.value = newVal.length
  }
}, { deep: true })

watch(wsConsoleActions, newVal => {
  if (newVal.length === 0) return
  const latestAction = newVal[0]
  const { action } = latestAction
  if (['mute', 'unmute', 'kick'].includes(action)) {
    console.log('[中控台] 收到用户状态变更广播:', action, latestAction.data)
    handleRefreshUsers()
  }
}, { deep: true })

watch(wsChatMessages, newVal => {
  if (!newVal.length) return
  prependComment(newVal[0])
}, { deep: true })

watch(wsConnected, connected => {
  if (connected) {
    console.log('[中控台] WebSocket 已连接')
    wsReconnecting.value = false
  } else {
    console.warn('[中控台] WebSocket 已断开')
  }
})

onMounted(() => {
  roomId.value = Number(route.query.id)
  if (!roomId.value) {
    proxy?.$message?.error('直播间 ID 无效')
    goBack()
    return
  }
  userStore.getInfo().catch(() => {})
  loadAll()
  loadGoods()
  loadComments()
  loadUsers()
  loadReward()
  websocketStore.connectWebSocket(roomId.value)
  nextTick(() => {
    const center = consoleCenterRef.value
    if (!center) return
    // 初始按视频比例设置高度；手动调整后只约束边界，不覆盖用户选择。
    panelResizeObserver = new ResizeObserver(() => {
      centerHeight.value = center.clientHeight
      const idealHeight = center.clientWidth
        ? Math.round(center.clientWidth * 9 / 16 + 80)
        : monitorPanelHeight.value
      constrainMonitorPanelHeight(monitorPanelAdjusted.value ? monitorPanelHeight.value : idealHeight)
    })
    panelResizeObserver.observe(center)
  })
})

onBeforeUnmount(() => {
  clearLiveDurationTimer()
  destroyFlvPlayer()
  removePanelResizeListeners()
  panelResizeObserver?.disconnect()
  websocketStore.disconnectWebSocket()
})
</script>

<style lang="scss" scoped>
@mixin console-theme {
  color-scheme: light;
  --live-bg: #ffffff;
  --live-card-bg: #ffffff;
  --live-card-bg2: #f6f7f8;
  --live-border: #e8eaed;
  --live-green: var(--dashboard-success, #1ab394);
  --live-red: var(--dashboard-danger, #ba1a1a);
  --live-gold: var(--dashboard-accent, #f34100);
  --live-text: #1a1c1c;
  --live-text-dim: #4c4546;
  --live-text-muted: #73777d;
  --live-accent: var(--dashboard-accent, #f34100);
  // 中台始终白底，局部中性色不能继承后台暗色主题的近白文字。
  --dashboard-surface: #ffffff;
  --dashboard-surface-muted: #f6f7f8;
  --dashboard-border: var(--live-border);
  --dashboard-border-soft: #f0f1f3;
  --dashboard-text-primary: var(--live-text);
  --dashboard-text-secondary: var(--live-text-dim);
  --dashboard-text-muted: var(--live-text-muted);
  --el-color-primary: var(--live-accent);
  --el-color-primary-dark-2: var(--dashboard-accent-hover, #d93a00);
  --el-color-primary-light-3: var(--dashboard-accent-light, #ff8a5c);
  --el-color-primary-light-5: color-mix(in srgb, var(--live-accent) 50%, white);
  --el-color-primary-light-7: color-mix(in srgb, var(--live-accent) 30%, white);
  --el-color-primary-light-8: color-mix(in srgb, var(--live-accent) 20%, white);
  --el-color-primary-light-9: color-mix(in srgb, var(--live-accent) 10%, white);
  --el-color-success: var(--live-green);
  --el-color-danger: var(--live-red);
  --el-text-color-primary: var(--live-text);
  --el-text-color-regular: var(--live-text-dim);
  --el-text-color-secondary: var(--live-text-muted);
  --el-text-color-placeholder: #92969c;
  --el-disabled-text-color: #92969c;
  --el-disabled-bg-color: #f6f7f8;
  --el-disabled-border-color: var(--live-border);
  --el-border-color: var(--live-border);
  --el-border-color-light: var(--live-border);
  --el-border-color-lighter: #f0f1f3;
  --el-fill-color: #f0f1f3;
  --el-fill-color-light: #f6f7f8;
  --el-fill-color-lighter: #fafafa;
  --el-fill-color-blank: #ffffff;
  --el-bg-color: #ffffff;
  --el-bg-color-overlay: #ffffff;
}

.console-wrap {
  @include console-theme;
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-height: 660px;
  overflow-x: hidden;
  overflow-y: auto;
  background: var(--live-bg);
  color: var(--live-text);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  letter-spacing: 0;

  :deep(.el-button + .el-button) { margin-left: 0; }
  :deep(.el-button) { border-radius: 6px; }
  :deep(.el-loading-mask) { background: rgba(255, 255, 255, 0.82); }
}
.console-wrap--resizing { cursor: row-resize; user-select: none; }

.console-header {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-shrink: 0;
  min-height: 72px;
  padding: 10px 24px;
  background: var(--live-card-bg);
  border-bottom: 1px solid var(--live-border);
}
.header-left { flex-shrink: 0; }
.room-avatar, .room-avatar-placeholder {
  flex-shrink: 0;
  border: 1px solid var(--live-border);
  border-radius: 6px;
}
.room-avatar-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: var(--live-card-bg2);
  color: var(--live-accent);
  font-size: 22px;
}
.header-title-block { flex: 1; min-width: 0; }
.header-console-title {
  margin: 0 0 5px;
  font-size: 18px;
  font-weight: 650;
  line-height: 1.35;
}
.header-room-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 12px;
  color: var(--live-text-muted);
  font-size: 12px;
  line-height: 1.5;
}
.header-room-name {
  max-width: 280px;
  overflow: hidden;
  color: var(--live-text-dim);
  white-space: nowrap;
  text-overflow: ellipsis;
}
.header-anchor { overflow-wrap: anywhere; }
.header-room-id { font-variant-numeric: tabular-nums; }
.status-tag { flex-shrink: 0; }
.live-tag :deep(.el-tag__content) { display: flex; align-items: center; gap: 6px; }
.live-dot, .online-dot, .live-dot-ctrl {
  display: inline-block;
  flex-shrink: 0;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--live-green);
}
.header-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.stream-btn-active {
  --el-button-text-color: var(--live-green);
  --el-button-border-color: #c8e9df;
  --el-button-bg-color: #f4fbf8;
  --el-button-hover-text-color: var(--live-green);
  --el-button-hover-border-color: var(--live-green);
  --el-button-hover-bg-color: #eaf8f1;
}
.stream-btn-idle { color: var(--live-text-dim); }
.drawer-badge {
  min-width: 20px;
  height: 20px;
  margin-left: 6px;
  padding: 0 5px;
  border-radius: 4px;
  background: var(--live-card-bg2);
  color: var(--live-text-dim);
  font-size: 11px;
  line-height: 20px;
  font-variant-numeric: tabular-nums;
}
.header-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  flex-shrink: 0;
  padding: 14px 24px;
  border-bottom: 1px solid var(--live-border);
}
.hstat-item {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  padding: 0 28px;
  border-left: 1px solid var(--live-border);
  &:first-child { padding-left: 0; border-left: 0; }
  > div { min-width: 0; }
}
.hstat-icon {
  flex-shrink: 0;
  width: 38px;
  height: 38px;
  border-radius: 6px;
  background: var(--live-card-bg2);
  color: var(--live-text-muted);
  font-size: 20px;
}
.hstat-icon--online { color: var(--live-green); background: #eff9f5; }
.hstat-icon--reward { color: var(--live-accent); background: var(--el-color-primary-light-9); }
.hstat-label { display: block; margin-bottom: 5px; color: var(--live-text-muted); font-size: 12px; }
.hstat-num {
  display: block;
  overflow-wrap: anywhere;
  font-size: 24px;
  font-weight: 650;
  line-height: 1.15;
  font-variant-numeric: tabular-nums;
}
.price-num { color: var(--live-accent); }

.console-main {
  display: flex;
  align-items: stretch;
  flex: 1;
  gap: 28px;
  // 470px 操作区加上下内边距；矮窗口在根容器滚动，不裁切回复栏。
  min-height: 502px;
  padding: 12px 24px 20px;
  overflow: hidden;
}
.console-center {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
  min-height: 470px;
  overflow: hidden;
}
.live-card { overflow: hidden; background: var(--live-card-bg); }
.live-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 10px;
  min-height: 44px;
  padding: 8px 0;
  border-bottom: 1px solid var(--live-border);
}
.live-card__title {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  gap: 8px;
  color: var(--live-text);
  font-size: 15px;
  font-weight: 650;
  .el-icon, > i { color: var(--live-text-muted); font-size: 18px; }
}
.live-card__extra { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; min-width: 0; }
.monitor-mode, .monitor-start, .panel-total { color: var(--live-text-muted); font-size: 12px; }
.monitor-panel { display: flex; flex: 0 0 auto; flex-direction: column; min-height: 220px; }
.player-wrap {
  position: relative;
  flex: 1;
  min-height: 0;
  width: 100%;
  margin-top: 0;
  overflow: hidden;
  border-radius: 6px;
  background: #141414;
  user-select: none;
}
.flv-video { display: block; width: 100%; height: 100%; background: #141414; object-fit: contain; }
.player-wrap:fullscreen { width: 100vw; height: 100vh; max-height: none; margin: 0; border-radius: 0; aspect-ratio: auto; }
.player-overlay-badges {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 10;
  display: flex;
  align-items: flex-end;
  flex-direction: column;
  gap: 6px;
}
.badge-online, .badge-time {
  display: flex;
  align-items: center;
  gap: 5px;
  max-width: 100%;
  padding: 4px 8px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.6);
  color: #ffffff;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}
.player-offline-mask, .player-loading-mask {
  position: absolute;
  inset: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 12px;
  background: #f4f5f6;
  color: var(--live-text-muted);
}
.player-loading-mask { z-index: 6; i { font-size: 28px; color: var(--live-accent); } span { font-size: 13px; } }
.offline-mask-inner { display: flex; align-items: center; flex-direction: column; gap: 12px; }
.offline-cover { position: absolute; inset: 0; background-size: contain; background-position: center; background-repeat: no-repeat; opacity: 0.15; }
.offline-icon {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border: 1px solid var(--live-border);
  border-radius: 8px;
  background: #ffffff;
  color: var(--live-text-muted);
  font-size: 26px;
}
.offline-tip { position: relative; z-index: 1; margin: 0; font-size: 13px; }
.player-ctrl-bar {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 44px;
  padding: 0 10px;
  background: rgba(0, 0, 0, 0.65);
}
.ctrl-left, .ctrl-right, .volume-wrap { display: flex; align-items: center; gap: 4px; }
.ctrl-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  padding: 0;
  border: 0;
  border-radius: 4px;
  background: transparent;
  color: #ffffff;
  font-size: 18px;
  cursor: pointer;
  &:hover { background: rgba(255, 255, 255, 0.18); }
  &:focus-visible { outline: 2px solid #ffffff; outline-offset: -2px; }
  &:disabled { opacity: 0.5; cursor: not-allowed; }
}
.live-badge-ctrl { display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 4px; color: #ffffff; font-size: 11px; font-weight: 650; }
.live-dot-ctrl { background: #ffffff; }
.volume-wrap { position: relative; }
.volume-slider-wrap {
  position: absolute;
  bottom: 100%;
  left: 50%;
  display: flex;
  justify-content: center;
  padding: 14px 12px;
  border: 1px solid var(--live-border);
  border-radius: 6px;
  background: #ffffff;
  transform: translateX(-50%);
  :deep(.el-slider__button) { width: 12px; height: 12px; }
}
.ctrl-fade-enter-active, .ctrl-fade-leave-active, .vol-slide-enter-active, .vol-slide-leave-active { transition: opacity 0.15s; }
.ctrl-fade-enter-from, .ctrl-fade-leave-to, .vol-slide-enter-from, .vol-slide-leave-to { opacity: 0; }
.player-status-corner { position: absolute; bottom: 10px; left: 12px; z-index: 15; }
.pstatus {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 8px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.65);
  color: #ffffff;
  font-size: 12px;
  &.online i { color: #4bd5a3; }
}
.player-error-bar {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  z-index: 25;
  display: flex;
  align-items: flex-start;
  gap: 6px;
  padding: 8px 12px;
  background: var(--live-red);
  color: #ffffff;
  font-size: 12px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.monitor-footer { display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; flex-wrap: wrap; gap: 4px 8px; min-height: 36px; padding: 8px 0; }
.monitor-health { display: inline-flex; align-items: center; gap: 6px; color: var(--live-green); font-size: 12px; }
.monitor-health.is-pending { color: var(--live-text-muted); }
.monitor-health.is-offline { color: var(--live-text-muted); }

.goods-panel { display: flex; flex: 1; flex-direction: column; min-width: 0; min-height: 0; padding-left: 24px; border-left: 1px solid var(--live-border); }
.goods-shelf-badge { color: var(--live-text-muted); font-size: 12px; white-space: nowrap; em { margin: 0 2px; color: var(--live-green); font-style: normal; font-weight: 650; } }
.goods-list, .comment-stream, .reward-stream, .user-list {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #d6d9dd transparent;
  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { border-radius: 3px; background: #d6d9dd; }
}
.goods-list { padding: 12px 0; }
.goods-item {
  display: flex;
  align-items: stretch;
  margin-bottom: 8px;
  overflow: hidden;
  border: 1px solid var(--live-border);
  border-radius: 6px;
  background: #ffffff;
  transition: border-color 0.15s;
  &:last-child { margin-bottom: 0; }
  &:hover { border-color: #cfd2d6; }
  &--off { background: #fafafa; .goods-item__cover { opacity: 0.6; } }
  &--explaining { border-color: var(--el-color-primary-light-5); background: var(--el-color-primary-light-9); }
}
.goods-drag-handle { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 24px; color: var(--live-text-muted); cursor: grab; &:active { cursor: grabbing; } }
.goods-drag-ghost { opacity: 0.45; background: var(--el-color-primary-light-9); }
.goods-item__shelf-line { flex-shrink: 0; width: 3px; background: var(--live-border); &.active { background: var(--live-green); } }
.goods-item__cover { flex-shrink: 0; width: 58px; height: 58px; margin: 12px 0 12px 10px; overflow: hidden; border-radius: 4px; background: var(--live-card-bg2); }
.goods-item__cover-image { width: 100%; height: 100%; }
.goods-item__cover-placeholder { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; color: var(--live-text-muted); font-size: 24px; }
.goods-item__body { display: flex; flex: 1; flex-direction: column; gap: 8px; min-width: 0; padding: 11px 10px; }
.goods-item__top { display: flex; align-items: flex-start; gap: 6px; min-width: 0; }
.goods-type-tag, .goods-explaining-tag { flex-shrink: 0; margin-top: 1px; }
.goods-title { display: -webkit-box; flex: 1; min-width: 0; overflow: hidden; color: var(--live-text); font-size: 13px; line-height: 1.5; overflow-wrap: anywhere; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.goods-item__bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px; }
.goods-price { flex-shrink: 0; color: var(--live-accent); font-size: 15px; font-weight: 650; font-variant-numeric: tabular-nums; }
.goods-item__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; }
.goods-switch { --el-switch-on-color: var(--live-green); --el-switch-off-color: #cfd2d6; }
.goods-sort-hint { color: var(--live-text-muted); font-size: 12px; }
.panel-resizer {
  position: relative;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 10px;
  height: 10px;
  cursor: row-resize;
  &:hover, &:focus-visible { background: var(--live-card-bg2); outline: none; .panel-resizer__line { background: var(--live-accent); } }
}
.panel-resizer__line { width: 48px; height: 3px; border-radius: 2px; background: #d6d9dd; }
.comment-panel { display: flex; flex: 1; flex-direction: column; min-height: 240px; }
.comment-panel__body { display: flex; flex: 1; flex-direction: column; min-height: 0; }
.comment-connection { display: inline-flex; align-items: center; gap: 5px; color: var(--live-text-muted); font-size: 12px; .online-dot { background: #b5b5b5; } &.is-connected { color: var(--live-green); .online-dot { background: var(--live-green); } } }
.panel-total { display: inline-flex; align-items: center; gap: 5px; }
.comment-stream { padding: 4px 0; }
.comment-bubble { display: flex; align-items: flex-start; gap: 10px; width: 100%; padding: 9px 0; border-bottom: 1px solid #f1f2f3; &:last-child { border-bottom: 0; } }
.bubble-avatar { flex-shrink: 0; :deep(img) { width: 100%; height: 100%; object-fit: cover; } }
.bubble-main { flex: 1; min-width: 0; }
.bubble-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 5px; margin-bottom: 5px; }
.bubble-name { color: var(--live-text-dim); font-size: 12px; font-weight: 650; overflow-wrap: anywhere; }
.bubble-time { flex-shrink: 0; color: var(--live-text-muted); font-size: 11px; }
.bubble-body { display: flex; align-items: flex-start; gap: 8px; }
.bubble-content { flex: 1; min-width: 0; color: var(--live-text); font-size: 13px; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.bubble-del { flex-shrink: 0; }
.comment-reply { flex-shrink: 0; padding: 8px 0 0; border-top: 1px solid var(--live-border); }
.comment-reply__user { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: var(--live-text-muted); font-size: 12px; }
.comment-reply__editor {
  display: flex;
  align-items: stretch;
  gap: 8px;
  :deep(.el-textarea) { flex: 1; min-width: 0; }
  :deep(.el-textarea__inner) { min-height: 64px !important; padding-bottom: 22px; background: #fafafa; color: var(--live-text); resize: none; }
  :deep(.el-input__count) { background: #fafafa; }
  > .el-button { align-self: flex-end; flex-shrink: 0; width: 76px; height: 36px; }
}

:deep(.live-drawer), :deep(.stream-dialog) { @include console-theme; background: #ffffff; color: var(--live-text); }
.console-wrap :deep(.el-button--primary), :deep(.stream-dialog .el-button--primary) {
  background: var(--el-color-primary-dark-2);
  border-color: var(--el-color-primary-dark-2);
  color: #ffffff !important;
  box-shadow: none;
  &:hover, &:focus-visible { background: color-mix(in srgb, var(--el-color-primary-dark-2) 90%, black); }
  &.is-disabled, &.is-disabled:hover {
    background: var(--el-disabled-bg-color);
    border-color: var(--el-disabled-border-color);
    color: var(--live-text-dim) !important;
  }
}
:deep(.live-drawer) {
  .el-drawer__header { margin-bottom: 0; padding: 20px; border-bottom: 1px solid var(--live-border); color: var(--live-text); font-size: 16px; font-weight: 650; }
  .el-drawer__body { min-height: 0; overflow: hidden; padding: 16px 20px; }
  .el-button + .el-button { margin-left: 0; }
}
:deep(.stream-dialog) {
  border-radius: 8px;
  .el-dialog__header { margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--live-border); }
  .el-dialog__title { font-size: 16px; font-weight: 650; }
}
.drawer-body { display: flex; flex-direction: column; gap: 12px; height: 100%; min-height: 0; }
.drawer-toolbar { display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; flex-wrap: wrap; gap: 8px; }
.drawer-toolbar__right { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.reward-stream, .user-list { display: flex; flex-direction: column; }
.reward-item, .user-list-item { display: flex; align-items: center; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--live-border); &:last-child { border-bottom: 0; } }
.reward-gift, .gift-img { flex-shrink: 0; width: 36px; height: 36px; }
.reward-gift { display: flex; align-items: center; justify-content: center; }
.gift-icon-default { color: var(--live-accent); font-size: 24px; }
.reward-info { display: flex; flex: 1; flex-direction: column; gap: 4px; min-width: 0; }
.reward-name, .reward-gift-name { overflow-wrap: anywhere; }
.reward-name { color: var(--live-text); font-size: 13px; font-weight: 600; }
.reward-gift-name, .reward-time { color: var(--live-text-muted); font-size: 12px; }
.reward-quantity { margin-left: 4px; color: var(--live-accent); font-style: normal; }
.reward-amount { flex-shrink: 0; color: var(--live-accent); font-size: 18px; font-weight: 650; font-variant-numeric: tabular-nums; }
.user-list-item__avatar { position: relative; flex-shrink: 0; }
.user-list-item__online { position: absolute; right: 0; bottom: 1px; width: 10px; height: 10px; border: 2px solid #ffffff; border-radius: 50%; background: var(--live-green); &.is-offline { background: #b5b5b5; } }
.user-list-item__main { flex: 1; min-width: 0; }
.user-list-item__top { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; min-width: 0; }
.user-list-item__name { max-width: 180px; overflow: hidden; color: var(--live-text); font-size: 14px; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.user-list-item__meta { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 6px; color: var(--live-text-muted); font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
.user-list-item__muted { margin-top: 5px; color: var(--live-red); font-size: 12px; overflow-wrap: anywhere; }
.user-list-item__actions { display: flex; align-items: center; flex-shrink: 0; flex-wrap: wrap; gap: 6px; }
.pagination-wrap { display: flex; justify-content: flex-end; flex-shrink: 0; padding: 10px 0 0; border-top: 1px solid var(--live-border); :deep(.el-pagination) { flex-wrap: wrap; justify-content: flex-end; gap: 4px; } }
.stream-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.stream-loading-tip { padding: 24px; color: var(--live-text-muted); text-align: center; }
.stream-name-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; padding: 12px; border-radius: 6px; background: var(--live-card-bg2); }
.stream-name-label, .stream-label { flex-shrink: 0; color: var(--live-text-dim); font-size: 13px; }
.stream-name-val { color: var(--live-text); font-family: monospace; font-size: 13px; overflow-wrap: anywhere; }
.stream-section { margin-top: 16px; }
.stream-section-title { margin-bottom: 10px; }
.stream-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.stream-label { width: 68px; }
.stream-input { flex: 1; min-width: 0; :deep(.el-input__wrapper) { background: #fafafa; } :deep(.el-input__inner) { font-family: monospace; font-size: 12px; } }
.live-text-btn { color: var(--live-text-dim); &:hover { color: var(--live-accent); } }
.live-danger-btn { color: var(--live-red); &:hover { color: var(--live-red); background: #fff2f2; } }
// 图标工具不继承全局 small 按钮尺寸和强制内边距。
.live-icon-btn { flex-shrink: 0; width: 30px; height: 30px !important; padding: 0 !important; color: var(--live-text-muted); &:hover { color: var(--live-accent); } }
.live-empty { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 10px; min-height: 100%; padding: 24px 16px; color: var(--live-text-muted); font-size: 13px; i { color: #bec2c7; font-size: 30px; } p { margin: 0; } }

@media (max-width: 1200px) {
  .console-header { flex-wrap: wrap; }
  .header-actions { justify-content: flex-end; width: 100%; }
  .header-stats { padding: 12px 24px; }
  .hstat-item { padding: 0 18px; }
  .console-main { gap: 20px; }
  .goods-panel { padding-left: 20px; }
  .goods-panel .live-card__header { align-items: flex-start; flex-wrap: wrap; }
  .goods-panel .live-card__extra { justify-content: flex-start; }
  .goods-shelf-badge { display: none; }
}
@media (max-width: 900px) {
  .console-wrap { height: auto; min-height: 100vh; overflow: visible; }
  .console-header { gap: 10px; padding: 12px 16px; }
  .header-actions { justify-content: flex-start; }
  .header-stats { padding: 14px 16px; }
  .hstat-item { gap: 8px; padding: 0 12px; }
  .hstat-icon { width: 30px; height: 30px; font-size: 17px; }
  .hstat-num { font-size: 20px; }
  .console-main { flex-direction: column; gap: 16px; min-height: 0; padding: 12px 16px 20px; overflow: visible; }
  .console-center { flex: none; min-height: 0; overflow: visible; }
  .monitor-panel { height: auto !important; min-height: 0; }
  .player-wrap { flex: none; aspect-ratio: 16 / 9; }
  .panel-resizer { display: none; }
  .comment-panel { flex: none; min-height: 0; margin-top: 12px; }
  .comment-panel__body { flex: none; }
  .comment-stream { flex: none; max-height: 280px; }
  .goods-panel { flex: none; min-height: 0; padding-left: 0; border-left: 0; }
  .goods-list { flex: none; max-height: 480px; }
  .live-empty { min-height: 100px; }
  .goods-shelf-badge { display: inline; }
}
@media (max-width: 540px) {
  .header-room-name { max-width: 170px; }
  .header-room-meta { gap: 3px 8px; }
  .header-actions { gap: 6px; :deep(.el-button) { padding: 8px 10px !important; } }
  .header-actions .live-icon-btn { padding: 0 !important; }
  .header-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 16px; }
  .hstat-item { padding: 0 12px; &:nth-child(3) { padding-left: 0; border-left: 0; } }
  .hstat-num { font-size: 22px; }
  .hstat-icon { width: 34px; height: 34px; }
  .live-card__header { flex-wrap: wrap; }
  .goods-panel .live-card__extra { gap: 6px; }
  .goods-shelf-badge { display: none; }
  .goods-item__cover { width: 48px; height: 48px; margin-left: 8px; }
  .goods-item__body { padding: 10px 8px; }
  .goods-item__actions { gap: 3px; }
  .goods-title { -webkit-line-clamp: 3; }
  .goods-item__top { flex-wrap: wrap; }
  .comment-connection { display: none; }
  .bubble-header { align-items: flex-start; flex-direction: column; }
  .user-list-item { flex-wrap: wrap; gap: 10px; }
  .user-list-item__actions { justify-content: flex-end; width: 100%; }
  .stream-row { flex-wrap: wrap; .stream-input { min-width: calc(100% - 76px); } .el-button { margin-left: 76px; } }
  .drawer-toolbar__right { gap: 5px; }
}
</style>
