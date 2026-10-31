import { PageSlotError } from './core.js'

/**
 * 绑定响应式模型的一个字段。
 * @param {object} model 页面模型。
 * @param {string} id 字段标识。
 * @param {string} label 中文名称。
 * @param {object} options 约束、说明、选项和可用条件，可覆盖读写方法。
 * @returns {object} 字段声明。
 */
export function modelField(model, id, label, options = {}) {
  return {
    id, label, type: options.schema?.type || 'string',
    get: () => model[id],
    set: value => { model[id] = value },
    ...options
  }
}

/**
 * 创建动作声明；handler 必须返回明确的 ok 结果。
 * @param {string} description 动作说明。
 * @param {Function} handler 页面原有业务方法。
 * @param {object} options 参数约束和可用条件。
 * @returns {object} 动作声明。
 */
export function slotAction(description, handler, options = {}) {
  return { description, handler, schema: { type: 'object', properties: {}}, ...options }
}

/**
 * 等待 Element Plus 表单校验并返回结果，避免把校验失败当成调用成功。
 * @param {object|null} form Element Plus 表单实例。
 * @returns {Promise<object>} 校验结果。
 */
export async function validateForm(form) {
  if (!form) return { ok: false, code: 'NOT_READY', message: '表单尚未准备好' }
  const valid = await form.validate().catch(() => false)
  return valid ? { ok: true } : { ok: false, code: 'VALIDATION_FAILED', message: '请检查表单填写是否正确' }
}

/**
 * 将请求错误转为简短结果，不回传 Axios 配置、请求体、堆栈或认证数据。
 * @param {*} error 请求或确认错误。
 * @returns {object} 明确的失败/取消结果。
 */
export function operationResult(error) {
  if (error instanceof PageSlotError) return { ok: false, code: error.code, message: error.message }
  if (error === 'cancel' || error === 'close') return { ok: false, code: 'CANCELLED', message: '操作已取消' }
  return { ok: false, code: 'OPERATION_FAILED', message: '操作失败，请检查页面提示' }
}
