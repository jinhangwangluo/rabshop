const THEME_COLOR_MAP = {
  orange: {
    primary: '#f34100',
    hover: '#d93a00',
    light: '#ff8a5c',
    soft: '#fff0ea',
    outline: 'rgba(243, 65, 0, 0.12)',
    shadow: 'rgba(243, 65, 0, 0.18)',
    shadowHover: 'rgba(243, 65, 0, 0.22)'
  },
  blue: {
    primary: '#2f7df6',
    hover: '#1d63d6',
    light: '#71a7ff',
    soft: '#eaf2ff',
    outline: 'rgba(47, 125, 246, 0.14)',
    shadow: 'rgba(47, 125, 246, 0.18)',
    shadowHover: 'rgba(47, 125, 246, 0.22)'
  },
  green: {
    primary: '#16a36a',
    hover: '#0f8555',
    light: '#5bd59e',
    soft: '#e8f8f0',
    outline: 'rgba(22, 163, 106, 0.14)',
    shadow: 'rgba(22, 163, 106, 0.18)',
    shadowHover: 'rgba(22, 163, 106, 0.22)'
  },
  violet: {
    primary: '#6d5dfc',
    hover: '#5848dc',
    light: '#9d91ff',
    soft: '#f0eeff',
    outline: 'rgba(109, 93, 252, 0.14)',
    shadow: 'rgba(109, 93, 252, 0.18)',
    shadowHover: 'rgba(109, 93, 252, 0.22)'
  }
}

const THEME_SURFACE_MAP = {
  light: {
    bg: '#f7f8fb',
    surface: '#ffffff',
    muted: '#f3f5f8',
    border: '#e7eaf0',
    borderSoft: '#f0f2f6',
    textPrimary: '#1f2937',
    textSecondary: '#4b5563',
    textMuted: '#8b95a5',
    menuBg: '#ffffff',
    menuText: '#2f3747',
    menuSubText: '#5f6877'
  },
  dark: {
    bg: '#0f141b',
    surface: '#151b24',
    muted: '#1d2530',
    border: '#2b3542',
    borderSoft: '#232c37',
    textPrimary: '#eef2f7',
    textSecondary: '#c7d0dd',
    textMuted: '#8e9bad',
    menuBg: '#121821',
    menuText: '#eef2f7',
    menuSubText: '#aab5c4'
  }
}

const FONT_SIZE_MAP = {
  compact: '13px',
  standard: '14px',
  large: '15px'
}

function getSystemTheme() {
  if (typeof window === 'undefined' || !window.matchMedia) {
    return 'light'
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function resolveThemeMode(themeMode) {
  return themeMode === 'system' ? getSystemTheme() : (themeMode || 'light')
}

function setStyleVar(root, key, value) {
  root.style.setProperty(key, value)
}

export function applyInterfaceTheme(settings = {}) {
  if (typeof document === 'undefined') {
    return
  }

  const root = document.documentElement
  const themeMode = settings.themeMode || 'light'
  const resolvedTheme = resolveThemeMode(themeMode)
  const color = THEME_COLOR_MAP[settings.themeColor] || THEME_COLOR_MAP.orange
  const surface = THEME_SURFACE_MAP[resolvedTheme] || THEME_SURFACE_MAP.light
  const fontSize = FONT_SIZE_MAP[settings.fontSize] || FONT_SIZE_MAP.standard
  const menuStyle = settings.menuStyle || 'handAccordion'

  root.setAttribute('data-theme-mode', themeMode)
  root.setAttribute('data-theme', resolvedTheme)
  root.setAttribute('data-menu-style', menuStyle)
  root.setAttribute('data-font-size', settings.fontSize || 'standard')

  setStyleVar(root, '--dashboard-accent', color.primary)
  setStyleVar(root, '--dashboard-accent-hover', color.hover)
  setStyleVar(root, '--dashboard-accent-light', color.light)
  setStyleVar(root, '--dashboard-accent-soft', color.soft)
  setStyleVar(root, '--dashboard-accent-outline', color.outline)
  setStyleVar(root, '--dashboard-accent-shadow', color.shadow)
  setStyleVar(root, '--dashboard-accent-shadow-hover', color.shadowHover)
  setStyleVar(root, '--dashboard-bg', surface.bg)
  setStyleVar(root, '--dashboard-surface', surface.surface)
  setStyleVar(root, '--dashboard-surface-muted', surface.muted)
  setStyleVar(root, '--dashboard-border', surface.border)
  setStyleVar(root, '--dashboard-border-soft', surface.borderSoft)
  setStyleVar(root, '--dashboard-text-primary', surface.textPrimary)
  setStyleVar(root, '--dashboard-text-secondary', surface.textSecondary)
  setStyleVar(root, '--dashboard-text-muted', surface.textMuted)
  setStyleVar(root, '--dashboard-menu-bg', surface.menuBg)
  setStyleVar(root, '--dashboard-menu-text', surface.menuText)
  setStyleVar(root, '--dashboard-menu-sub-text', surface.menuSubText)
  setStyleVar(root, '--dashboard-font-size', fontSize)
}

export function bindSystemThemeListener(callback) {
  if (typeof window === 'undefined' || !window.matchMedia) {
    return null
  }

  const matcher = window.matchMedia('(prefers-color-scheme: dark)')
  const handler = () => callback && callback()

  if (matcher.addEventListener) {
    matcher.addEventListener('change', handler)
    return () => matcher.removeEventListener('change', handler)
  }

  matcher.addListener(handler)
  return () => matcher.removeListener(handler)
}

export const interfaceThemeOptions = {
  colors: THEME_COLOR_MAP,
  fontSizes: FONT_SIZE_MAP
}
