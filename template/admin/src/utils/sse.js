import setting from '@/settings'
import { getToken } from '@/utils/auth'

function buildAdminApiUrl(path) {
  const baseUri = String(setting.baseUri || '').replace(/\/$/, '')
  return baseUri + path
}

async function readResponseErrorMessage(response) {
  const message = '请求失败'
  try {
    const text = await response.text()
    if (!text) return message
    const data = JSON.parse(text)
    return data.message || message
  } catch (error) {
    return message
  }
}

async function readSseResponse(response, onEvent) {
  if (!response.ok) {
    throw new Error(await readResponseErrorMessage(response))
  }

  const contentType = response.headers.get('content-type') || ''
  if (contentType.indexOf('text/event-stream') === -1) {
    const text = await response.text()
    if (!text) return
    try {
      const data = JSON.parse(text)
      if (data.status && data.status !== 200) {
        throw new Error(data.message || '请求失败')
      }
      if (data.data && typeof onEvent === 'function') {
        onEvent({
          type: 'done',
          content: data.data.answer || data.data.content || '',
          fields: data.data.fields || [],
          hits: data.data.hits || [],
          done: true
        })
      }
      return
    } catch (error) {
      if (error && error.message) throw error
      throw new Error('响应解析失败')
    }
  }

  const reader = response.body && response.body.getReader ? response.body.getReader() : null
  if (!reader) {
    throw new Error('当前浏览器不支持流式响应')
  }

  const decoder = new TextDecoder('utf-8')
  let buffer = ''
  let reading = true
  while (reading) {
    const result = await reader.read()
    if (result.done) {
      reading = false
      continue
    }

    buffer += decoder.decode(result.value, { stream: true })
    const events = buffer.split('\n\n')
    buffer = events.pop() || ''
    events.forEach(eventText => {
      emitSseText(eventText, onEvent)
    })
  }

  emitSseText(buffer.trim(), onEvent)
}

function emitSseText(eventText, onEvent) {
  if (!eventText || typeof onEvent !== 'function') return
  const dataLines = eventText
    .split('\n')
    .filter(line => line.indexOf('data:') === 0)
    .map(line => line.slice(5).trim())
  if (!dataLines.length) return

  const raw = dataLines.join('\n')
  if (!raw || raw === '[DONE]') return
  onEvent(JSON.parse(raw))
}

/**
 * 使用后台鉴权发起 POST SSE 请求。
 * @param {string} path 后台接口路径
 * @param {Object} data 请求体
 * @param {(payload: Object) => void} onEvent SSE事件回调
 * @returns {Promise<void>}
 */
export function streamAdminSse(path, data, onEvent) {
  return fetch(buildAdminApiUrl(path), {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + getToken(),
      'Content-Type': 'application/json',
      'Accept': 'text/event-stream'
    },
    body: JSON.stringify(data || {})
  }).then(response => readSseResponse(response, onEvent))
}
