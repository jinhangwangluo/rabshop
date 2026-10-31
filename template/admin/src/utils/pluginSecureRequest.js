import request from '@/utils/request'

const PUBLIC_KEY_URL = '/plugins/security/public-key'
const SECURE_GATEWAY_URL = '/plugins/gateway'
const DEFAULT_PROTOCOL_VERSION = '1.0'
const CLIENT_ID_STORAGE_KEY = 'plugin_secure_client_id'
const PUBLIC_KEY_CACHE_SKEW = 30000
const DEFAULT_PUBLIC_KEY_TTL = 60000
// 后端网关允许一次短暂重试；为避免后端重试尚未结束时浏览器先超时，单次调用留出 30 秒。
const DEFAULT_GATEWAY_TIMEOUT = 30000

let cachedPublicKey = null
let cachedPublicKeyExpiresAt = 0
let pendingPublicKeyRequest = null
let memoryClientId = ''

/**
 * 读取插件安全网关的短期加密公钥。
 *
 * 公钥接口只做加密引导，不携带插件业务参数；真正的业务请求会通过
 * pluginSecureRequest() 进入 application/jose 网关。
 */
export function fetchPluginSecurePublicKey() {
  return request({
    url: PUBLIC_KEY_URL,
    method: 'get'
  })
}

/**
 * 发送插件域加密请求。
 *
 * 所有业务字段都会放入 JWE 密文，普通 URL 和 Header 只保留网关路径、
 * 请求 ID 与协议版本，防止插件生命周期参数从明文链路泄露。
 */
export async function pluginSecureRequest(options = {}) {
  const logicalMethod = normalizeLogicalMethod(options.logicalMethod || options.method || 'POST')
  const logicalPath = normalizeLogicalPath(options.logicalPath || options.path || '')
  const publicKey = await resolvePublicKey(options.publicKey, options.forceRefreshKey === true)
  const envelope = buildEnvelope({
    logicalMethod,
    logicalPath,
    body: options.body || options.data || {},
    clientId: options.clientId,
    idempotencyKey: options.idempotencyKey,
    requestId: options.requestId
  })
  const jose = await createEncryptedEnvelope(envelope, publicKey)
  const headers = {
    ...(options.requestOptions?.headers || {}),
    'Content-Type': 'application/jose',
    'X-Plugin-Protocol-Version': envelope.protocol_version,
    'X-Plugin-Request-Id': envelope.request_id
  }

  return request({
    ...(options.requestOptions || {}),
    url: options.gatewayUrl || SECURE_GATEWAY_URL,
    method: 'post',
    data: jose,
    headers,
    timeout: options.requestOptions?.timeout ?? DEFAULT_GATEWAY_TIMEOUT,
    // 网关用 HTTP 400 表示可解析的业务失败；保留响应体，交由统一拦截器展示后端 message。
    validateStatus: status => status === 200 || status === 400
  })
}

/**
 * 清理浏览器端公钥缓存。
 *
 * 密钥轮换、网关返回 kid 不匹配或管理员切换站点后，可主动调用该方法
 * 重新获取服务端当前公钥。
 */
export function clearPluginSecureKeyCache() {
  cachedPublicKey = null
  cachedPublicKeyExpiresAt = 0
  pendingPublicKeyRequest = null
}

async function resolvePublicKey(publicKey, forceRefresh) {
  if (publicKey) {
    return assertPublicKey(publicKey)
  }
  if (!forceRefresh && cachedPublicKey && Date.now() < cachedPublicKeyExpiresAt) {
    return cachedPublicKey
  }
  if (pendingPublicKeyRequest) {
    return pendingPublicKeyRequest
  }

  pendingPublicKeyRequest = fetchPluginSecurePublicKey()
    .then(response => {
      const payload = response?.data?.data || response?.data || {}
      const metadata = assertPublicKey(payload)
      cachedPublicKey = metadata
      cachedPublicKeyExpiresAt = resolvePublicKeyCacheExpiresAt(metadata)
      return metadata
    })
    .finally(() => {
      pendingPublicKeyRequest = null
    })
  return pendingPublicKeyRequest
}

function assertPublicKey(publicKey = {}) {
  const kid = String(publicKey.kid || '').trim()
  const algorithm = String(publicKey.algorithm || '').trim()
  const publicKeyPem = String(publicKey.public_key_pem || '').trim()

  if (!/^[A-Za-z0-9._:-]{1,64}$/.test(kid)) {
    throw new Error('插件安全公钥 kid 无效')
  }
  if (algorithm !== 'RSA-OAEP-256') {
    throw new Error('插件安全公钥算法不受支持')
  }
  if (!publicKeyPem.includes('BEGIN PUBLIC KEY')) {
    throw new Error('插件安全公钥格式无效')
  }

  return {
    ...publicKey,
    kid,
    algorithm,
    public_key_pem: publicKeyPem,
    protocol_version: String(publicKey.protocol_version || DEFAULT_PROTOCOL_VERSION)
  }
}

function resolvePublicKeyCacheExpiresAt(publicKey) {
  const expiresAt = parseDateTime(publicKey.expires_at)
  if (expiresAt > Date.now()) {
    return Math.max(Date.now(), expiresAt - PUBLIC_KEY_CACHE_SKEW)
  }
  return Date.now() + DEFAULT_PUBLIC_KEY_TTL
}

function parseDateTime(value) {
  if (value === undefined || value === null || value === '') {
    return 0
  }
  if (typeof value === 'number') {
    return value > 1000000000000 ? value : value * 1000
  }
  if (/^\d+$/.test(String(value))) {
    const timestamp = Number(value)
    return timestamp > 1000000000000 ? timestamp : timestamp * 1000
  }
  const parsed = Date.parse(String(value))
  return Number.isFinite(parsed) ? parsed : 0
}

function buildEnvelope(options) {
  const clientId = resolveClientId(options.clientId)
  const requestId = options.requestId ? String(options.requestId).trim() : createUuid()
  const envelope = {
    protocol_version: DEFAULT_PROTOCOL_VERSION,
    request_id: requestId,
    timestamp: Math.floor(Date.now() / 1000),
    nonce: createNonce(),
    client_id: clientId,
    logical_method: options.logicalMethod,
    logical_path: options.logicalPath,
    idempotency_key: resolveIdempotencyKey(options.logicalMethod, options.idempotencyKey),
    body: normalizeBody(options.body)
  }

  assertRequestId(envelope.request_id)
  return envelope
}

function normalizeLogicalMethod(method) {
  const normalized = String(method || '').trim().toUpperCase()
  if (!['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].includes(normalized)) {
    throw new Error('插件逻辑请求方法无效')
  }
  return normalized
}

function normalizeLogicalPath(path) {
  const normalized = String(path || '').trim()
  if (
    normalized === '' ||
    normalized.length > 190 ||
    normalized[0] !== '/' ||
    normalized.includes('?') ||
    normalized.includes('..') ||
    /[\x00-\x1F\x7F]/.test(normalized)
  ) {
    throw new Error('插件逻辑请求路径无效')
  }
  return normalized
}

function normalizeBody(body) {
  if (body === null || body === undefined) {
    return {}
  }
  if (Array.isArray(body) || typeof body !== 'object') {
    throw new Error('插件逻辑请求体必须为对象')
  }
  return clonePlain(body)
}

function resolveIdempotencyKey(method, value) {
  const key = String(value || '').trim()
  if (key !== '') {
    return assertIdempotencyKey(key)
  }
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    return assertIdempotencyKey(`req.${Date.now()}.${base64UrlEncode(randomBytes(16))}`)
  }
  return ''
}

function assertIdempotencyKey(value) {
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(value)) {
    throw new Error('插件业务幂等键无效')
  }
  return value
}

function resolveClientId(clientId) {
  const configured = String(clientId || '').trim()
  if (configured !== '') {
    return assertClientId(configured)
  }
  if (memoryClientId !== '') {
    return memoryClientId
  }

  const storage = getSessionStorage()
  const stored = storage?.getItem(CLIENT_ID_STORAGE_KEY) || ''
  if (stored !== '' && /^[A-Za-z0-9._:-]{1,64}$/.test(stored)) {
    memoryClientId = stored
    return memoryClientId
  }

  memoryClientId = assertClientId(`browser.${createUuid()}`)
  storage?.setItem(CLIENT_ID_STORAGE_KEY, memoryClientId)
  return memoryClientId
}

function assertClientId(value) {
  if (!/^[A-Za-z0-9._:-]{1,64}$/.test(value)) {
    throw new Error('插件客户端标识无效')
  }
  return value
}

function assertRequestId(value) {
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value)) {
    throw new Error('插件请求 ID 无效')
  }
}

function getSessionStorage() {
  try {
    return window.sessionStorage
  } catch (error) {
    return null
  }
}

async function createEncryptedEnvelope(envelope, publicKey) {
  const browserCrypto = getBrowserCrypto()
  const subtle = getSubtleCrypto()
  const protectedHeader = buildProtectedHeader(envelope, publicKey)
  const protectedSegment = base64UrlEncode(JSON.stringify(protectedHeader))
  const aad = buildJoseAad(protectedSegment, envelope)
  const rsaKey = await importRsaPublicKey(publicKey.public_key_pem)
  const contentKey = await subtle.generateKey(
    { name: 'AES-GCM', length: 256 },
    true,
    ['encrypt']
  )
  const rawContentKey = await subtle.exportKey('raw', contentKey)
  const encryptedKey = await subtle.encrypt({ name: 'RSA-OAEP' }, rsaKey, rawContentKey)
  const iv = browserCrypto.getRandomValues(new Uint8Array(12))
  const encryptedPayload = new Uint8Array(await subtle.encrypt(
    {
      name: 'AES-GCM',
      iv,
      additionalData: encodeText(aad),
      tagLength: 128
    },
    contentKey,
    encodeText(JSON.stringify(envelope))
  ))
  const tag = encryptedPayload.slice(encryptedPayload.length - 16)
  const ciphertext = encryptedPayload.slice(0, encryptedPayload.length - 16)

  return [
    protectedSegment,
    base64UrlEncode(encryptedKey),
    base64UrlEncode(iv),
    base64UrlEncode(ciphertext),
    base64UrlEncode(tag)
  ].join('.')
}

function buildProtectedHeader(envelope, publicKey) {
  return {
    alg: 'RSA-OAEP-256',
    enc: 'A256GCM',
    kid: publicKey.kid,
    typ: 'JOSE',
    cty: 'application/json',
    protocol_version: publicKey.protocol_version || DEFAULT_PROTOCOL_VERSION,
    request_id: envelope.request_id,
    client_id: envelope.client_id,
    logical_method: envelope.logical_method,
    logical_path: envelope.logical_path
  }
}

function buildJoseAad(protectedSegment, envelope) {
  return `${protectedSegment}.${base64UrlEncode([
    envelope.logical_method,
    envelope.logical_path,
    envelope.client_id,
    envelope.request_id
  ].join('\n'))}`
}

async function importRsaPublicKey(publicKeyPem) {
  return getSubtleCrypto().importKey(
    'spki',
    pemToArrayBuffer(publicKeyPem),
    { name: 'RSA-OAEP', hash: 'SHA-256' },
    false,
    ['encrypt']
  )
}

function pemToArrayBuffer(publicKeyPem) {
  const base64 = publicKeyPem
    .replace(/-----BEGIN PUBLIC KEY-----/g, '')
    .replace(/-----END PUBLIC KEY-----/g, '')
    .replace(/\s/g, '')
  if (base64 === '') {
    throw new Error('插件安全公钥格式无效')
  }
  return base64ToBytes(base64).buffer
}

function createNonce() {
  return base64UrlEncode(randomBytes(24))
}

function createUuid() {
  const browserCrypto = getBrowserCrypto()
  if (typeof browserCrypto.randomUUID === 'function') {
    return browserCrypto.randomUUID()
  }

  const bytes = randomBytes(16)
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80
  const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

function randomBytes(length) {
  const bytes = new Uint8Array(length)
  getBrowserCrypto().getRandomValues(bytes)
  return bytes
}

function getBrowserCrypto() {
  const browserCrypto = window.crypto || window.msCrypto
  if (!browserCrypto) {
    throw new Error('当前浏览器不支持插件安全请求')
  }
  return browserCrypto
}

function getSubtleCrypto() {
  const subtle = getBrowserCrypto().subtle
  if (!subtle) {
    throw new Error('当前浏览器不支持插件加密请求')
  }
  return subtle
}

function encodeText(value) {
  return new TextEncoder().encode(value)
}

function base64UrlEncode(input) {
  const bytes = typeof input === 'string' ? encodeText(input) : new Uint8Array(input)
  return btoa(bytesToBinary(bytes))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '')
}

function base64ToBytes(value) {
  const binary = atob(value)
  const bytes = new Uint8Array(binary.length)
  for (let index = 0; index < binary.length; index++) {
    bytes[index] = binary.charCodeAt(index)
  }
  return bytes
}

function bytesToBinary(bytes) {
  const chunkSize = 8192
  let binary = ''
  for (let index = 0; index < bytes.length; index += chunkSize) {
    binary += String.fromCharCode(...bytes.slice(index, index + chunkSize))
  }
  return binary
}

function clonePlain(value) {
  if (typeof window !== 'undefined' && typeof window.structuredClone === 'function') {
    try {
      return window.structuredClone(value)
    } catch (error) {
      // Vue 的 reactive/ref 代理对象不可结构化克隆，降级为 JSON 深拷贝
    }
  }
  return JSON.parse(JSON.stringify(value))
}

export default pluginSecureRequest
