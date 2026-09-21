import * as ElementPlusIcons from '@element-plus/icons-vue'
import legacyIconNames from './icons'

const LEGACY_ICON_ALIASES = {
  'el-icon-battery-full': 'Cellphone',
  'el-icon-bangzhu': 'HelpFilled',
  'el-icon-c-scale-to-original': 'ScaleToOriginal',
  'el-icon-camera-solid': 'CameraFilled',
  'el-icon-delete-solid': 'DeleteFilled',
  'el-icon-education': 'School',
  'el-icon-full-screen': 'FullScreen',
  'el-icon-help': 'Help',
  'el-icon-info': 'InfoFilled',
  'el-icon-mouse': 'Mouse',
  'el-icon-platform-eleme': 'Platform',
  'el-icon-picture-outline': 'Picture',
  'el-icon-picture-outline-round': 'PictureRounded',
  'el-icon-question': 'QuestionFilled',
  'el-icon-s-check': 'Checked',
  'el-icon-s-claim': 'Finished',
  'el-icon-s-cooperation': 'Connection',
  'el-icon-s-comment': 'ChatRound',
  'el-icon-s-custom': 'UserFilled',
  'el-icon-s-data': 'DataBoard',
  'el-icon-s-finance': 'Money',
  'el-icon-s-flag': 'Flag',
  'el-icon-s-goods': 'GoodsFilled',
  'el-icon-s-grid': 'Grid',
  'el-icon-s-help': 'HelpFilled',
  'el-icon-s-home': 'HomeFilled',
  'el-icon-s-management': 'Management',
  'el-icon-s-marketing': 'Promotion',
  'el-icon-s-opportunity': 'Opportunity',
  'el-icon-s-open': 'Open',
  'el-icon-s-order': 'List',
  'el-icon-s-platform': 'Platform',
  'el-icon-s-promotion': 'Promotion',
  'el-icon-s-release': 'Upload',
  'el-icon-s-shop': 'Shop',
  'el-icon-s-ticket': 'Ticket',
  'el-icon-s-tools': 'Tools',
  'el-icon-s-unfold': 'Expand',
  'el-icon-signal': 'Connection',
  'el-icon-star-off': 'Star',
  'el-icon-star-on': 'StarFilled',
  'el-icon-success': 'SuccessFilled',
  'el-icon-user-solid': 'UserFilled',
  'el-icon-video-camera-solid': 'VideoCameraFilled',
  'el-icon-volume-notification': 'Notification',
  'el-icon-warning-outline': 'WarnTriangleFilled',
  'el-icon-wifi': 'Connection'
}

const EXTRA_LEGACY_ICON_NAMES = [
  'el-icon-battery-full',
  'el-icon-signal',
  'el-icon-volume-notification',
  'el-icon-wifi'
]

function toPascalCase(name) {
  return name
    .replace(/^el-icon-/, '')
    .split('-')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join('')
}

function resolveIconComponent(name) {
  const componentName = LEGACY_ICON_ALIASES[name] || toPascalCase(name)
  return ElementPlusIcons[componentName] || ElementPlusIcons.Picture
}

const allLegacyIconNames = [...new Set([...legacyIconNames, ...EXTRA_LEGACY_ICON_NAMES])]

export const legacyIconComponents = allLegacyIconNames.reduce((map, name) => {
  map[name] = resolveIconComponent(name)
  return map
}, {})

export function resolveLegacyIcon(name) {
  const values = Array.isArray(name) ? name : [name]
  const legacyName = values.find(value => String(value || '').startsWith('el-icon-'))
  if (legacyName) {
    return legacyIconComponents[legacyName] || resolveIconComponent(legacyName)
  }

  const customIconName = values.find(value => {
    const valueText = String(value || '')
    return valueText && valueText !== 'iconfont'
  })

  return customIconName ? ElementPlusIcons.Picture : null
}

export function normalizeLegacyIconClasses(name) {
  const values = (Array.isArray(name) ? name : [name])
    .filter(value => typeof value === 'string' && value.trim())
    .map(value => value.trim())

  if (values.length === 0) {
    return ['legacy-icon']
  }

  if (values.some(value => value.startsWith('el-icon-'))) {
    return ['legacy-icon', ...values]
  }

  return ['legacy-icon', 'iconfont', ...values]
}
