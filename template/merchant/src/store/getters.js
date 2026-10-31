const getters = {
  sidebar: state => state.app.sidebar,
  size: state => state.app.size,
  device: state => state.app.device,
  visitedViews: state => state.tagsView.visitedViews,
  cachedViews: state => state.tagsView.cachedViews,
  token: state => state.user.token,
  adminInfo: state => state.user.adminInfo,
  permission_routes: state => state.permission.routes,
  errorLogs: state => state.errorLog.logs,
  menus: state => state.user.menus,
  appMenus: state => state.user.appMenus,
  appId: state => state.user.appId,
  backendMessageUnreadCount: state => state.backendMessage ? state.backendMessage.unreadCount : 0,
  backendMessageLatest: state => state.backendMessage ? state.backendMessage.latestMessage : null,
  aiImageGeneratePinned: state => state.aiImageGenerate ? state.aiImageGenerate.pinned : false,
  aiImageGenerateVisible: state => state.aiImageGenerate ? state.aiImageGenerate.visible : false,
  // WebSocket 相关
  wsConnected: state => state.websocket.connected,
  wsCurrentRoomId: state => state.websocket.currentRoomId,
  wsOnlineUsers: state => state.websocket.onlineUsers,
  wsChatMessages: state => state.websocket.chatMessages,
  wsConsoleActions: state => state.websocket.consoleActions,
  wsMessageHistory: state => state.websocket.messageHistory
}
export default getters
