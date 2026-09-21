const DESCRIPTION_LINK_RE = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi

/**
 * Render config description text with safe anchor support.
 * Only http/https href values are converted into clickable links.
 *
 * @param {string} description
 * @returns {string}
 */
export function renderSafeDescription(description = '') {
  const text = String(description || '')
  if (!text) return ''

  let result = ''
  let lastIndex = 0

  text.replace(DESCRIPTION_LINK_RE, (match, attrs, label, offset) => {
    result += escapeHtml(text.slice(lastIndex, offset))

    const href = extractAnchorHref(attrs)
    if (isSafeHttpUrl(href)) {
      result += `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${escapeHtml(stripTags(label))}</a>`
    } else {
      result += escapeHtml(match)
    }

    lastIndex = offset + match.length
    return match
  })

  result += escapeHtml(text.slice(lastIndex))
  return result
}

function extractAnchorHref(attrs = '') {
  const source = String(attrs || '')
  const quoted = source.match(/\bhref\s*=\s*(['"])(.*?)\1/i)
  if (quoted) return String(quoted[2] || '').trim()

  const unquoted = source.match(/\bhref\s*=\s*([^\s>]+)/i)
  return unquoted ? String(unquoted[1] || '').trim() : ''
}

function isSafeHttpUrl(href) {
  try {
    const url = new URL(String(href || '').trim())
    return url.protocol === 'http:' || url.protocol === 'https:'
  } catch (e) {
    return false
  }
}

function stripTags(value) {
  return String(value || '').replace(/<[^>]*>/g, '')
}

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}
