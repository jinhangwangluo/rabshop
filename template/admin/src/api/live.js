import request from '@/utils/request'

// ===================== 直播分类管理 =====================

// 获取直播分类列表
export function getCategoryList(params) {
  return request({ url: '/live/category', method: 'get', params })
}

// 新增直播分类
export function createCategory(data) {
  return request({ url: '/live/category', method: 'post', data })
}

// 编辑直播分类
export function updateCategory(id, data) {
  return request({ url: `/live/category/${id}`, method: 'put', data })
}

// 删除直播分类
export function deleteCategory(id) {
  return request({ url: `/live/category/${id}`, method: 'delete' })
}

// 切换直播分类状态
export function toggleCategoryStatus(id) {
  return request({ url: `/live/category/status/${id}`, method: 'get' })
}

// 获取直播分类下拉选项（启用状态）
export function getCategoryOptions() {
  return request({ url: '/live/category/options', method: 'get' })
}

// ===================== 主播管理 =====================

// 获取主播列表
export function getAnchorList(params) {
  return request({ url: '/live/anchor', method: 'get', params })
}

// 主播列表各 Tab 数量统计
export function getAnchorTabStats() {
  return request({ url: '/live/anchor/tab_stats', method: 'get' })
}

// 获取主播详情
export function getAnchorDetail(id) {
  return request({ url: `/live/anchor/${id}`, method: 'get' })
}

// 新增主播
export function createAnchor(data) {
  return request({ url: '/live/anchor', method: 'post', data })
}

// 编辑主播
export function updateAnchor(id, data) {
  return request({ url: `/live/anchor/${id}`, method: 'put', data })
}

// 删除主播
export function deleteAnchor(id) {
  return request({ url: `/live/anchor/${id}`, method: 'delete' })
}

// 启用/禁用主播
export function showAnchor(id) {
  return request({ url: `/live/anchor/${id}/show`, method: 'get' })
}

// 审核主播
export function auditAnchor(id, data) {
  return request({ url: `/live/anchor/${id}/audit`, method: 'post', data })
}

// 切换禁播状态
export function toggleAnchorBan(id) {
  return request({ url: `/live/anchor/${id}/toggle_ban`, method: 'post' })
}

// 记录违规
export function addAnchorViolation(id) {
  return request({ url: `/live/anchor/${id}/add_violation`, method: 'post' })
}

// 获取主播下拉选项
export function getAnchorOptions() {
  return request({ url: '/live/anchor_options', method: 'get' })
}

// ===================== 直播间管理 =====================

// 获取直播间列表
export function getRoomList(params) {
  return request({ url: '/live/room', method: 'get', params })
}

// 获取直播间列表各 Tab 数量统计
export function getRoomTabStats() {
  return request({ url: '/live/room/tab_stats', method: 'get' })
}

// 审核直播间
export function auditRoom(id, data) {
  return request({ url: `/live/room/${id}/audit`, method: 'post', data })
}

// 获取直播间详情
export function getRoomDetail(id) {
  return request({ url: `/live/room/${id}/detail`, method: 'get' })
}

// 新增直播间
export function createRoom(data) {
  return request({ url: '/live/room', method: 'post', data })
}

// 编辑直播间
export function updateRoom(id, data) {
  return request({ url: `/live/room/${id}`, method: 'put', data })
}

// 删除直播间
export function deleteRoom(id) {
  return request({ url: `/live/room/${id}`, method: 'delete' })
}

// 启用/禁用直播间
export function showRoom(id) {
  return request({ url: `/live/room/${id}/show`, method: 'get' })
}

// 切换直播状态
export function switchRoomStatus(id, status) {
  return request({ url: `/live/room/${id}/switch_status`, method: 'post', data: { status }})
}

// 切换热门推荐
export function toggleRoomHot(id) {
  return request({ url: `/live/room/${id}/toggle_hot`, method: 'post' })
}

// ===================== 礼物管理 =====================

// 获取礼物列表
export function getGiftList(params) {
  return request({ url: '/live/gift', method: 'get', params })
}

// 获取可用礼物（前端直播间）
export function getAvailableGifts() {
  return request({ url: '/live/gift/available', method: 'get' })
}

// 获取礼物详情
export function getGiftDetail(id) {
  return request({ url: `/live/gift/${id}`, method: 'get' })
}

// 新增礼物
export function createGift(data) {
  return request({ url: '/live/gift', method: 'post', data })
}

// 编辑礼物
export function updateGift(id, data) {
  return request({ url: `/live/gift/${id}`, method: 'put', data })
}

// 删除礼物
export function deleteGift(id) {
  return request({ url: `/live/gift/${id}`, method: 'delete' })
}

// 启用/禁用礼物
export function showGift(id, status) {
  return request({ url: `/live/gift/status/${id}`, method: 'get', params: { status }})
}

// ===================== 直播带货商品管理 =====================

// 获取直播商品列表
export function getLiveGoodsList(params) {
  return request({ url: '/live/goods', method: 'get', params })
}

// 获取直播商品详情
export function getLiveGoodsDetail(id) {
  return request({ url: `/live/goods/${id}`, method: 'get' })
}

// 新增直播商品
export function createLiveGoods(data) {
  return request({ url: '/live/goods', method: 'post', data })
}

// 编辑直播商品
export function updateLiveGoods(id, data) {
  return request({ url: `/live/goods/${id}`, method: 'put', data })
}

// 删除直播商品
export function deleteLiveGoods(id) {
  return request({ url: `/live/goods/${id}`, method: 'delete' })
}

// 启用/禁用直播商品
export function showLiveGoods(id, status) {
  return request({ url: `/live/goods/status/${id}`, method: 'get', params: { status }})
}

// 设置/取消直播商品正在讲解
export function setLiveGoodsExplaining(id, isExplaining = 1) {
  return request({ url: `/live/goods/explaining/${id}`, method: 'post', data: { is_explaining: isExplaining }})
}

// 获取商品管理 - 付费专题下拉选项
export function getSpecialOptions() {
  return request({ url: '/live/goods/special_options', method: 'get' })
}

// 批量更新排序
export function batchSortLiveGoods(liveRoomId, sortData) {
  return request({ url: '/live/goods/batch_sort', method: 'post', data: { live_room_id: liveRoomId, sort_data: sortData }})
}

// ===================== 直播云配置管理 =====================

// 获取所有云配置（脱敏）
export function getStreamConfigAll() {
  return request({ url: '/live/stream_config/all', method: 'get' })
}

// 获取云配置列表（分页）
export function getStreamConfigList(params) {
  return request({ url: '/live/stream_config', method: 'get', params })
}

// 获取云配置详情
export function getStreamConfigDetail(id) {
  return request({ url: `/live/stream_config/${id}/show`, method: 'get' })
}

// 保存云配置（新增/编辑）
export function saveStreamConfig(data) {
  return request({ url: '/live/stream_config/save', method: 'post', data })
}

// 删除云配置
export function deleteStreamConfig(id) {
  return request({ url: `/live/stream_config/${id}`, method: 'delete' })
}

// 设为默认云
export function setStreamConfigDefault(id) {
  return request({ url: `/live/stream_config/${id}/set_default`, method: 'post' })
}

// ===================== 直播间推拉流管理 =====================

// 为直播间生成双云推拉流地址
export function createRoomStream(id, force = false) {
  return request({ url: `/live/room/${id}/create_stream`, method: 'post', data: { force }})
}

// 关闭直播间双云推拉流
export function closeRoomStream(id) {
  return request({ url: `/live/room/${id}/close_stream`, method: 'post' })
}

// 获取直播间推拉流信息
export function getRoomStreamInfo(id) {
  return request({ url: `/live/room/${id}/stream_info`, method: 'get' })
}

// ===================== 直播间回放归档管理 =====================

// 获取直播间回放列表
export function getRoomRecords(roomId, params = {}) {
  return request({ url: `/live/room/${roomId}/records`, method: 'get', params })
}

// 删除某条回放归档
export function deleteRoomRecord(roomId, recordId) {
  return request({ url: `/live/room/${roomId}/record/${recordId}`, method: 'delete' })
}

// 设置某条回放为当前播放（将直播间状态切到直播中并替换播流地址）
export function setRoomPlaybackRecord(roomId, recordId) {
  return request({ url: `/live/room/${roomId}/record/${recordId}/playback`, method: 'post' })
}

// ===================== 直播间用户管理 =====================

// 获取直播间用户列表
export function getLiveRoomUsers(id, params) {
  return request({ url: `/live/room/${id}/users`, method: 'get', params })
}

// 禁言直播间用户
export function muteRoomUser(roomId, userId) {
  return request({ url: `/live/room/${roomId}/user/${userId}/mute`, method: 'post' })
}

// 踢出直播间用户
export function kickRoomUser(roomId, userId) {
  return request({ url: `/live/room/${roomId}/user/${userId}/kick`, method: 'post' })
}

// ===================== 直播间评论管理 =====================

// 获取直播间评论列表
export function getLiveComments(id, params) {
  return request({ url: `/live/room/${id}/comments`, method: 'get', params })
}

// 删除直播间评论
export function deleteLiveComment(roomId, commentId) {
  return request({ url: `/live/room/${roomId}/comment/${commentId}`, method: 'delete' })
}

// 管理员按绑定用户身份发送直播间评论
export function sendLiveComment(roomId, data) {
  return request({ url: `/live/room/${roomId}/comment/send`, method: 'post', data })
}

// ===================== 直播订单统计 =====================

// 获取直播间礼物打赏记录
export function getLiveRewardList(params) {
  return request({ url: '/live/reward', method: 'get', params })
}

// 获取直播间商品订单列表
export function getLiveOrderList(params) {
  return request({ url: '/live/order', method: 'get', params })
}

// 获取直播间统计概览
export function getLiveRoomStats(id) {
  return request({ url: `/live/room/${id}/stats`, method: 'get' })
}

// ===================== 直播间等级配置管理 =====================

// 获取等级配置列表
export function getCategoryLevelList(params) {
  return request({ url: '/live/level_config', method: 'get', params })
}

// 新增等级配置
export function createCategoryLevelConfig(data) {
  return request({ url: '/live/level_config', method: 'post', data })
}

// 编辑等级配置
export function updateCategoryLevelConfig(id, data) {
  return request({ url: `/live/level_config/${id}`, method: 'put', data })
}

// 删除等级配置
export function deleteCategoryLevelConfig(id) {
  return request({ url: `/live/level_config/${id}`, method: 'delete' })
}

// 切换等级配置状态
export function toggleCategoryLevelConfigStatus(id) {
  return request({ url: `/live/level_config/status/${id}`, method: 'get' })
}

// 获取等级配置下拉选项（启用状态）
export function getCategoryLevelOptions() {
  return request({ url: '/live/level_config/options', method: 'get' })
}

// ===================== 用户直播间等级管理 =====================

// 获取直播间贡献排行榜
export function getRoomRankList(roomId, params) {
  return request({ url: `/live/room/${roomId}/rank`, method: 'get', params })
}

// 获取指定用户在直播间的等级
export function getUserLevelInRoom(roomId, userId) {
  return request({ url: `/live/room/${roomId}/user_level/${userId}`, method: 'get' })
}

// ===================== WebSocket 相关 =====================

// 获取 WebSocket Token（用于连接鉴权）
export function getWsToken(roomId) {
  return request({ url: `/live/room/${roomId}/ws_token`, method: 'get' })
}
