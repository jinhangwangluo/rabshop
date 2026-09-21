import { reactive } from 'vue'
import { getThemeConfig } from '@/api/theme'
import { STORAGE_KEYS } from './config'

export const DEFAULT_THEME = Object.freeze({
	theme_key: 'orange',
	theme_name: '活力橙',
	primary_color: '#ea580c',
	secondary_color: '#fb923c',
	gradient_color: 'linear-gradient(135deg, #ea580c 0%, #fb923c 100%)'
})

export const themeState = reactive({
	...DEFAULT_THEME
})

/**
 * 同步后台主题配置并应用。
 *
 * @param {Object} options 请求选项。
 * @returns {Promise<Object>} 当前主题配置。
 */
export async function syncThemeConfig(options = {}) {
	try {
		const res = await getThemeConfig({}, {
			showError: false,
			...options
		})
		const config = normalizeThemeConfig(res && res.code === 200 ? res.data : {})
		setThemeConfig(config)
		return config
	} catch (error) {
		const config = getStoredThemeConfig()
		applyThemeConfig(config)
		return config
	}
}

/**
 * 保存并应用主题配置。
 *
 * @param {Object} config 主题配置。
 * @returns {Object} 标准化后的主题配置。
 */
export function setThemeConfig(config = {}) {
	const normalized = normalizeThemeConfig(config)
	uni.setStorageSync(STORAGE_KEYS.themeConfig, normalized)
	applyThemeConfig(normalized)
	return normalized
}

/**
 * 获取本地主题配置。
 *
 * @returns {Object} 标准化后的主题配置。
 */
export function getStoredThemeConfig() {
	const stored = uni.getStorageSync(STORAGE_KEYS.themeConfig)
	return normalizeThemeConfig(stored && typeof stored === 'object' ? stored : {})
}

/**
 * 应用主题配置到运行时 CSS 变量。
 *
 * @param {Object} config 主题配置。
 * @returns {Object} 标准化后的主题配置。
 */
export function applyThemeConfig(config = {}) {
	const normalized = normalizeThemeConfig(config)
	Object.assign(themeState, normalized)
	const variables = buildThemeVariables(normalized)

	// #ifdef H5
	if (typeof document !== 'undefined' && document.documentElement) {
		Object.keys(variables).forEach((key) => {
			document.documentElement.style.setProperty(key, variables[key])
		})
	}
	// #endif

	uni.$emit('theme:updated', normalized)
	return normalized
}

/**
 * 构建用于 page-meta 的页面根样式。
 *
 * @param {Object} config 主题配置。
 * @returns {string} CSS 变量样式字符串。
 */
export function buildThemeStyleString(config = themeState) {
	const variables = buildThemeVariables(config)
	return Object.keys(variables).map((key) => `${key}: ${variables[key]}`).join('; ')
}

/**
 * 全局主题 mixin，页面和组件可直接使用主题变量。
 *
 * @returns {Object} Vue mixin 配置。
 */
export function createThemeMixin() {
	return {
		computed: {
			themePageStyle() {
				return buildThemeStyleString(themeState)
			},
			themePrimaryColor() {
				return themeState.primary_color
			},
			themeSecondaryColor() {
				return themeState.secondary_color
			},
			themeGradientColor() {
				return themeState.gradient_color
			}
		}
	}
}

/**
 * 根据三种主题颜色派生现有页面使用的 CSS 变量。
 *
 * @param {Object} config 主题配置。
 * @returns {Object} CSS 变量和值的映射。
 */
export function buildThemeVariables(config = {}) {
	const normalized = normalizeThemeConfig(config)
	const primary = normalized.primary_color
	const secondary = normalized.secondary_color
	const gradient = normalized.gradient_color

	return {
		'--theme-primary': primary,
		'--theme-secondary': secondary,
		'--theme-gradient': gradient,
		'--brand-primary': primary,
		'--brand-primary-dark': primary,
		'--brand-primary-light': secondary,
		'--brand-primary-pale': toSoftBackground(secondary),
		'--brand-shadow': toRgba(primary, 0.18),
		'--brand-shadow-hover': toRgba(primary, 0.26),
		'--brand-focus-shadow': toRgba(primary, 0.16),
		'--text-link': primary,
		'--text-link-hover': primary,
		'--surface-user-hero': toSoftBackground(secondary),
		'--surface-user-hero-start': toSoftBackground(secondary),
		'--surface-user-hero-end': secondary
	}
}

function normalizeThemeConfig(config = {}) {
	return {
		theme_key: String(config.theme_key || DEFAULT_THEME.theme_key),
		theme_name: String(config.theme_name || DEFAULT_THEME.theme_name),
		primary_color: normalizeHexColor(config.primary_color, DEFAULT_THEME.primary_color),
		secondary_color: normalizeHexColor(config.secondary_color, DEFAULT_THEME.secondary_color),
		gradient_color: normalizeGradient(config.gradient_color, DEFAULT_THEME.gradient_color)
	}
}

function normalizeHexColor(value, fallback) {
	const color = String(value || '').trim()
	if (/^#[0-9a-fA-F]{6}$/.test(color)) {
		return color.toLowerCase()
	}
	if (/^[0-9a-fA-F]{6}$/.test(color)) {
		return `#${color.toLowerCase()}`
	}
	return fallback
}

function normalizeGradient(value, fallback) {
	const gradient = String(value || '').trim()
	return /^linear-gradient\([#a-zA-Z0-9\s,%.()-]+\)$/.test(gradient) ? gradient : fallback
}

function toRgba(hex, alpha) {
	const color = normalizeHexColor(hex, DEFAULT_THEME.primary_color).slice(1)
	const red = parseInt(color.slice(0, 2), 16)
	const green = parseInt(color.slice(2, 4), 16)
	const blue = parseInt(color.slice(4, 6), 16)
	return `rgba(${red}, ${green}, ${blue}, ${alpha})`
}

function toSoftBackground(hex) {
	const color = normalizeHexColor(hex, DEFAULT_THEME.secondary_color).slice(1)
	const red = parseInt(color.slice(0, 2), 16)
	const green = parseInt(color.slice(2, 4), 16)
	const blue = parseInt(color.slice(4, 6), 16)
	const softRed = Math.round(red + (255 - red) * 0.86)
	const softGreen = Math.round(green + (255 - green) * 0.86)
	const softBlue = Math.round(blue + (255 - blue) * 0.86)
	return `rgb(${softRed}, ${softGreen}, ${softBlue})`
}
