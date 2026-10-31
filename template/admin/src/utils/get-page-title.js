import defaultSettings from '@/settings'

const title = defaultSettings.title || '乖乖兔多商户商城系统'

export default function getPageTitle(pageTitle) {
  if (pageTitle) {
    return `${pageTitle} - ${title}`
  }
  return `${title}`
}
