import request from '@/utils/request'
import { streamAdminSse } from '@/utils/sse'

// 管理员管理
export function getAdminList(params) {
  return request({
    url: '/setting/system_admin',
    method: 'get',
    params
  })
}

export function createAdmin(data) {
  return request({
    url: '/setting/system_admin/create',
    method: 'post',
    data
  })
}

export function editAdmin(data) {
  return request({
    url: '/setting/system_admin/edit',
    method: 'post',
    data
  })
}

export function deleteAdmin(params) {
  return request({
    url: '/setting/system_admin/delete',
    method: 'get',
    params
  })
}

export function getAdminInfo(params) {
  return request({
    url: '/setting/system_admin/info',
    method: 'get',
    params
  })
}

// 系统配置
export function getConfigList(params) {
  return request({
    url: '/setting/system_config',
    method: 'get',
    params
  })
}

export function createConfig(data) {
  return request({
    url: '/setting/system_config/create',
    method: 'post',
    data
  })
}

export function saveConfig(data) {
  return request({
    url: '/setting/system_config/save',
    method: 'post',
    data
  })
}

// 系统配置标签
export function getConfigTabList(params) {
  return request({
    url: '/setting/system_config_tab',
    method: 'get',
    params
  })
}

export function createConfigTab(data) {
  return request({
    url: '/setting/system_config_tab/create',
    method: 'post',
    data
  })
}

export function editConfigTab(data) {
  return request({
    url: '/setting/system_config_tab/edit',
    method: 'post',
    data
  })
}

export function deleteConfigTab(params) {
  return request({
    url: '/setting/system_config_tab/delete',
    method: 'get',
    params
  })
}

// 系统角色
export function getRoleList(params) {
  return request({
    url: '/setting/system_role',
    method: 'get',
    params
  })
}

export function createRole(data) {
  return request({
    url: '/setting/system_role/create',
    method: 'post',
    data
  })
}

export function editRole(data) {
  return request({
    url: '/setting/system_role/edit',
    method: 'post',
    data
  })
}

export function deleteRole(params) {
  return request({
    url: '/setting/system_role/delete',
    method: 'get',
    params
  })
}

// 系统广播
export function getBroadcastList(params) {
  return request({
    url: '/setting/system_broadcast',
    method: 'get',
    params
  })
}

export function createBroadcast(data) {
  return request({
    url: '/setting/system_broadcast/create',
    method: 'post',
    data
  })
}

export function deleteBroadcast(params) {
  return request({
    url: '/setting/system_broadcast/delete',
    method: 'get',
    params
  })
}

// 系统存储桶
export function getBucketList(params) {
  return request({
    url: '/setting/system_bucket',
    method: 'get',
    params
  })
}

export function createBucket(data) {
  return request({
    url: '/setting/system_bucket/create',
    method: 'post',
    data
  })
}

export function deleteBucket(params) {
  return request({
    url: '/setting/system_bucket/delete',
    method: 'get',
    params
  })
}

// 系统菜单
export function getMenusList(params) {
  return request({
    url: '/setting/system_menus',
    method: 'get',
    params
  })
}

export function editMenusContent(data) {
  return request({
    url: '/setting/system_menus/edit_content',
    method: 'post',
    data
  })
}

// 系统消息
export function getMessageList(params) {
  return request({
    url: '/setting/system_message',
    method: 'get',
    params
  })
}

export function createMessage(data) {
  return request({
    url: '/setting/system_message/create',
    method: 'post',
    data
  })
}

export function deleteMessage(params) {
  return request({
    url: '/setting/system_message/delete',
    method: 'get',
    params
  })
}

// 系统通知
export function getNoticeList(params) {
  return request({
    url: '/setting/system_notice',
    method: 'get',
    params
  })
}

export function sendNotice(data) {
  return request({
    url: '/setting/system_notice/message',
    method: 'post',
    data
  })
}

// 系统平台
export function getPlatInfo(params) {
  return request({
    url: '/setting/system_plat',
    method: 'get',
    params
  })
}

export function savePlatInfo(data) {
  return request({
    url: '/setting/system_plat/save',
    method: 'post',
    data
  })
}

export function saveSmsInfo(data) {
  return request({
    url: '/setting/system_plat/sms_save',
    method: 'post',
    data
  })
}

// 系统分组
export function getGroupList(params) {
  return request({
    url: '/setting/system_group',
    method: 'get',
    params
  })
}

export function createGroup(data) {
  return request({
    url: '/setting/system_group/create',
    method: 'post',
    data
  })
}

export function editGroup(data) {
  return request({
    url: '/setting/system_group/edit',
    method: 'post',
    data
  })
}

export function deleteGroup(params) {
  return request({
    url: '/setting/system_group/delete',
    method: 'get',
    params
  })
}

// 系统分组数据
export function getGroupDataList(params) {
  return request({
    url: '/setting/system_group_data',
    method: 'get',
    params
  })
}

export function createGroupData(data) {
  return request({
    url: '/setting/system_group_data/create',
    method: 'post',
    data
  })
}

export function editGroupData(data) {
  return request({
    url: '/setting/system_group_data/edit',
    method: 'post',
    data
  })
}

export function deleteGroupData(params) {
  return request({
    url: '/setting/system_group_data/delete',
    method: 'get',
    params
  })
}

/**
 * 保存模板配置
 * @param data
 * @returns {*}
 */
export function saveTemplateApi(data) {
  return request({
    url: '/setting/config_template/save_template',
    method: 'post',
    data
  })
}

/**
 * 获取单个模板配置
 * @param id
 * @returns {*}
 */
export function getConfigTemplateFindApi(id) {
  return request({
    url: '/setting/config_template/' + id,
    method: 'get'
  })
}

/**
 * 流式生成配置模板设计草稿
 * @param {Object} data 请求参数，包含 id/prompt/messages/current_fields
 * @param {(payload: Object) => void} onEvent SSE事件回调
 * @returns {Promise<void>}
 */
export function streamConfigTemplateAiGenerate(data, onEvent) {
  return streamAdminSse('/setting/config_template/ai_generate', data, onEvent)
}
