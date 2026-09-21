/**
 * 将错误消息收敛为可直接展示给用户的文案。
 *
 * @param {string} message 原始错误消息。
 * @param {string} fallback 无法安全展示时使用的兜底文案。
 * @returns {string}
 */
export function normalizePublicErrorMessage(message = '', fallback = '请求失败') {
	const normalized = String(message || '').trim()
	if (!normalized) {
		return fallback
	}

	const lowered = normalized.toLowerCase()
	const verboseMarkers = [
		'stack trace',
		'exception trace',
		'thrown in',
		'uncaught',
		'fatal error',
		'parse error',
		'syntax error',
		'typeerror',
		'pdoexception',
		'errorexception',
		'argument #',
		'call to undefined',
		'request:fail',
		'request failed',
		'network request failed',
		'network error',
		'failed to fetch',
		'fetch failed',
		'timed out',
		'timeout',
		'connection refused',
		'connection reset',
		'cannot connect',
		'connect failed',
		'service unavailable',
		'gateway timeout',
		'bad gateway',
		'internal server error',
		'enotfound',
		'econnrefused',
		'etimedout',
		'sqlstate',
		'ssl',
		'certificate',
		'vendor/',
		'app\\',
		'/users/',
		'/private/',
		'/var/'
	]

	const hasVerboseMarker = verboseMarkers.some((marker) => lowered.includes(marker))
	if (hasVerboseMarker || (normalized.includes('\n') && normalized.length > 120)) {
		return fallback
	}

	return normalized
}
