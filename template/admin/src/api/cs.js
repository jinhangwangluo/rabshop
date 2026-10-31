import request from '@/utils/request'
import { streamAdminSse } from '@/utils/sse'

export function getCsSessionList(params) {
  return request({
    url: '/cs/session',
    method: 'get',
    params
  })
}

export function readCsSession(params) {
  return request({
    url: '/cs/session/read',
    method: 'get',
    params
  })
}

export function getCsSessionMessages(params) {
  return request({
    url: '/cs/session/messages',
    method: 'get',
    params
  })
}

export function getCsWsToken() {
  return request({
    url: '/cs/ws_token',
    method: 'get'
  })
}

export function createCsMessage(data) {
  return request({
    url: '/cs/message',
    method: 'post',
    data
  })
}

export function markCsSessionRead(data) {
  return request({
    url: '/cs/message/mark_session_read',
    method: 'put',
    data
  })
}

export function acceptCsSession(data) {
  return request({
    url: '/cs/session/assign',
    method: 'post',
    data
  })
}

export function transferCsSession(data) {
  return request({
    url: '/cs/session/transfer',
    method: 'post',
    data
  })
}

export function closeCsSession(data) {
  return request({
    url: '/cs/session/close',
    method: 'post',
    data
  })
}

export function getCsKnowledgeList(params) {
  return request({
    url: '/cs/knowledge',
    method: 'get',
    params
  })
}

export function createCsKnowledge(data) {
  return request({
    url: '/cs/knowledge',
    method: 'post',
    data
  })
}

export function updateCsKnowledge(id, data) {
  return request({
    url: `/cs/knowledge/${id}`,
    method: 'put',
    data
  })
}

export function deleteCsKnowledge(id) {
  return request({
    url: `/cs/knowledge/${id}`,
    method: 'delete'
  })
}

export function batchDeleteCsKnowledge(data) {
  return request({
    url: '/cs/knowledge/batch_delete',
    method: 'post',
    data
  })
}

export function vectorizeCsKnowledge(data) {
  return request({
    url: '/cs/knowledge/vectorize',
    method: 'post',
    data
  })
}

export function batchVectorizeCsKnowledge(data) {
  return request({
    url: '/cs/knowledge/batch_vectorize',
    method: 'post',
    data
  })
}

export function searchCsKnowledge(params) {
  return request({
    url: '/cs/knowledge/search',
    method: 'get',
    params
  })
}

export function importCsKnowledgeFile(formData) {
  return request({
    url: '/cs/knowledge/import_file',
    method: 'post',
    data: formData,
    headers: {
      'Content-Type': 'multipart/form-data'
    }
  })
}

export function importCsKnowledgeUrl(data) {
  return request({
    url: '/cs/knowledge/import_url',
    method: 'post',
    data
  })
}

export function getCsScriptList(params) {
  return request({
    url: '/cs/script',
    method: 'get',
    params
  })
}

export function createCsScript(data) {
  return request({
    url: '/cs/script',
    method: 'post',
    data
  })
}

export function updateCsScript(id, data) {
  return request({
    url: `/cs/script/${id}`,
    method: 'put',
    data
  })
}

export function updateCsScriptStatus(data) {
  return request({
    url: '/cs/script/status',
    method: 'put',
    data
  })
}

export function deleteCsScript(id) {
  return request({
    url: `/cs/script/${id}`,
    method: 'delete'
  })
}

export function batchDeleteCsScript(data) {
  return request({
    url: '/cs/script/batch_delete',
    method: 'post',
    data
  })
}

export function searchCsScript(params) {
  return request({
    url: '/cs/script/search',
    method: 'get',
    params
  })
}

export function recordUseCsScript(data) {
  return request({
    url: '/cs/script/use',
    method: 'post',
    data
  })
}

export function getCsScriptCategoryList(params) {
  return request({
    url: '/cs/script_category',
    method: 'get',
    params
  })
}

export function createCsScriptCategory(data) {
  return request({
    url: '/cs/script_category',
    method: 'post',
    data
  })
}

export function updateCsScriptCategory(id, data) {
  return request({
    url: `/cs/script_category/${id}`,
    method: 'put',
    data
  })
}

export function deleteCsScriptCategory(id) {
  return request({
    url: `/cs/script_category/${id}`,
    method: 'delete'
  })
}

export function getCsKnowledgeCategoryList(params) {
  return request({
    url: '/cs/knowledge_category',
    method: 'get',
    params
  })
}

export function createCsKnowledgeCategory(data) {
  return request({
    url: '/cs/knowledge_category',
    method: 'post',
    data
  })
}

export function updateCsKnowledgeCategory(id, data) {
  return request({
    url: `/cs/knowledge_category/${id}`,
    method: 'put',
    data
  })
}

export function deleteCsKnowledgeCategory(id) {
  return request({
    url: `/cs/knowledge_category/${id}`,
    method: 'delete'
  })
}

export function quickAddCsKnowledgeCategory(data) {
  return request({
    url: '/cs/knowledge_category/quick_add',
    method: 'post',
    data
  })
}

export function getCsStaffList(params) {
  return request({
    url: '/cs/staff',
    method: 'get',
    params
  })
}

export function createCsStaff(data) {
  return request({
    url: '/cs/staff',
    method: 'post',
    data
  })
}

export function updateCsStaff(id, data) {
  return request({
    url: `/cs/staff/${id}`,
    method: 'put',
    data
  })
}

export function deleteCsStaff(id) {
  return request({
    url: `/cs/staff/${id}`,
    method: 'delete'
  })
}

export function updateCsStaffOnlineStatus(data) {
  return request({
    url: '/cs/staff/online_status',
    method: 'put',
    data
  })
}

export function getCsOnlineStaff(params) {
  return request({
    url: '/cs/staff/online',
    method: 'get',
    params
  })
}

export function getCsAiProviderList(params) {
  return request({
    url: '/cs/ai_provider',
    method: 'get',
    params
  })
}

export function createCsAiProvider(data) {
  return request({
    url: '/cs/ai_provider',
    method: 'post',
    data
  })
}

export function updateCsAiProvider(id, data) {
  return request({
    url: `/cs/ai_provider/${id}`,
    method: 'put',
    data
  })
}

export function updateCsAiProviderStatus(id, data) {
  return request({
    url: `/cs/ai_provider/${id}/status`,
    method: 'put',
    data
  })
}

export function deleteCsAiProvider(id) {
  return request({
    url: `/cs/ai_provider/${id}`,
    method: 'delete'
  })
}

/**
 * 获取 AI 模型类型下拉选项。
 * @returns {*}
 */
export function getCsAiModelTypeOptions() {
  return request({
    url: '/cs/ai_model/type_options',
    method: 'get'
  })
}

export function getCsAiModelList(params) {
  return request({
    url: '/cs/ai_model',
    method: 'get',
    params
  })
}

export function createCsAiModel(data) {
  return request({
    url: '/cs/ai_model',
    method: 'post',
    data
  })
}

export function updateCsAiModel(id, data) {
  return request({
    url: `/cs/ai_model/${id}`,
    method: 'put',
    data
  })
}

export function updateCsAiModelStatus(id, data) {
  return request({
    url: `/cs/ai_model/${id}/status`,
    method: 'put',
    data
  })
}

export function setCsAiModelDefault(id) {
  return request({
    url: `/cs/ai_model/${id}/default`,
    method: 'put'
  })
}

export function deleteCsAiModel(id) {
  return request({
    url: `/cs/ai_model/${id}`,
    method: 'delete'
  })
}

// ===================== 知识库实体（Knowledge Base） =====================

export function getCsKnowledgeBaseList(params) {
  return request({
    url: '/cs/knowledge_base',
    method: 'get',
    params
  })
}

export function createCsKnowledgeBase(data) {
  return request({
    url: '/cs/knowledge_base',
    method: 'post',
    data
  })
}

export function getCsKnowledgeBase(id) {
  return request({
    url: `/cs/knowledge_base/${id}`,
    method: 'get'
  })
}

export function updateCsKnowledgeBase(id, data) {
  return request({
    url: `/cs/knowledge_base/${id}`,
    method: 'put',
    data
  })
}

export function deleteCsKnowledgeBase(id) {
  return request({
    url: `/cs/knowledge_base/${id}`,
    method: 'delete'
  })
}

export function toggleCsKnowledgeBaseStatus(id, params) {
  return request({
    url: `/cs/knowledge_base/${id}/show`,
    method: 'get',
    params
  })
}

export function testCsKnowledgeBaseReply(id, data) {
  return request({
    url: `/cs/knowledge_base/${id}/test_reply`,
    method: 'post',
    data
  })
}

/**
 * 流式测试知识库回复。
 * @param {number|string} id 知识库ID
 * @param {{question: string}} data 提问内容
 * @param {(payload: Object) => void} onEvent SSE事件回调
 * @return {Promise<void>}
 */
export function streamCsKnowledgeBaseReply(id, data, onEvent) {
  return streamAdminSse(`/cs/knowledge_base/${id}/test_reply`, data, onEvent)
}

// ===================== 知识库分段 + QA =====================

/**
 * 解析内容（不入库），返回段落预览
 * source: manual / url / file
 */
export function parseKnowledgeContent(data) {
  return request({
    url: '/cs/knowledge/parse_content',
    method: 'post',
    data
  })
}

/** 获取知识的段落列表 */
export function getKnowledgeSegmentList(params) {
  return request({
    url: '/cs/knowledge_segment',
    method: 'get',
    params
  })
}

/** 批量保存段落（覆盖式） */
export function batchSaveKnowledgeSegments(data) {
  return request({
    url: '/cs/knowledge_segment/batch_save',
    method: 'post',
    data
  })
}

/** 更新单个段落 */
export function updateKnowledgeSegment(id, data) {
  return request({
    url: `/cs/knowledge_segment/${id}`,
    method: 'put',
    data
  })
}

/** 删除段落 */
export function deleteKnowledgeSegment(id) {
  return request({
    url: `/cs/knowledge_segment/${id}`,
    method: 'delete'
  })
}

/** 单段落向量化 */
export function vectorizeKnowledgeSegment(id) {
  return request({
    url: `/cs/knowledge_segment/${id}/vectorize`,
    method: 'post'
  })
}

/** 批量段落向量化 */
export function batchVectorizeKnowledgeSegment(data) {
  return request({
    url: '/cs/knowledge_segment/batch_vectorize',
    method: 'post',
    data
  })
}

/** 获取段落的 QA 列表 */
export function getKnowledgeQaList(params) {
  return request({
    url: '/cs/knowledge_qa',
    method: 'get',
    params
  })
}

/** 新增单条 QA */
export function createKnowledgeQa(data) {
  return request({
    url: '/cs/knowledge_qa',
    method: 'post',
    data
  })
}

/** 批量保存某段落的 QA（覆盖式） */
export function batchSaveKnowledgeQa(data) {
  return request({
    url: '/cs/knowledge_qa/batch_save',
    method: 'post',
    data
  })
}

/** 更新单条 QA */
export function updateKnowledgeQa(id, data) {
  return request({
    url: `/cs/knowledge_qa/${id}`,
    method: 'put',
    data
  })
}

/** 删除 QA */
export function deleteKnowledgeQa(id) {
  return request({
    url: `/cs/knowledge_qa/${id}`,
    method: 'delete'
  })
}

/** 单条 QA 向量化 */
export function vectorizeKnowledgeQa(id) {
  return request({
    url: `/cs/knowledge_qa/${id}/vectorize`,
    method: 'post'
  })
}

/** 批量 QA 向量化 */
export function batchVectorizeKnowledgeQa(data) {
  return request({
    url: '/cs/knowledge_qa/batch_vectorize',
    method: 'post',
    data
  })
}
