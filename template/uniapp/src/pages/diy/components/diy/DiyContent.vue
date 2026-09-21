<template>
	<view class="diy-content" :style="componentStyle">
		<view v-if="!isCategoryTabsStyle && (config.title || showMore)" class="content-header">
			<text v-if="config.title" class="content-title">{{ config.title }}</text>
			<view v-if="showMore" class="content-more" hover-class="content-more--hover" @click="handleMoreClick">
				<text>{{ config.more_text || '更多内容' }}</text>
				<uni-icons class="content-more__icon" type="right" size="13" color="#848484"></uni-icons>
			</view>
		</view>

		<view v-if="isCategoryTabsStyle" class="content-category-tabs">
			<scroll-view v-if="categoryTabs.length > 0" class="category-tabs-scroll" scroll-x scroll-with-animation
				:scroll-into-view="categoryTabIntoView" :scroll-left="categoryTabScrollLeft" :show-scrollbar="false">
				<view class="category-tabs-track">
					<view v-for="item in categoryTabs" :key="item.id" :id="item.anchorId" class="category-tab"
						:class="{ 'category-tab--active': item.id === currentCategoryId }"
						hover-class="category-tab--hover" @click="handleCategoryChange(item.id)">
						<text class="category-tab__text">{{ item.name }}</text>
						<view v-if="item.id === currentCategoryId" class="category-tab__line"></view>
					</view>
				</view>
			</scroll-view>

			<view v-if="categoryContentList.length === 0" class="content-empty" @click="handleRetryClick">
				<text class="content-empty__title">{{ emptyText }}</text>
			</view>

			<view v-else class="content-display-list content-display-list--tabs">
				<view v-for="item in categoryContentList" :key="item.renderKey" class="content-list-card"
					hover-class="content-list-card--hover" @click="handleClick(item)">
					<view class="content-list-card__cover" :style="imageRadiusStyle">
						<image v-if="item.image" class="content-list-card__image" :src="item.image" mode="aspectFill" />
						<view v-else class="content-cover-placeholder">
							<text>{{ item.typeLabel }}</text>
						</view>
						<view v-if="item.isVideo" class="content-list-card__play">
							<text class="iconfont icon-bofang"></text>
						</view>
					</view>

					<view class="content-list-card__body">
						<text class="content-list-card__title">{{ item.title }}</text>
						<text v-if="showDesc && item.description" class="content-list-card__desc"
							:class="item.descLineClass">{{ item.description }}</text>
						<view v-if="item.priceText || item.memberPriceText" class="content-list-card__price-row">
							<text class="content-list-card__price">{{ item.priceText }}</text>
							<view v-if="item.memberPriceText" class="content-list-card__vip">
								<image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
								<text class="content-list-card__vip-price">{{ item.memberPriceText }}</text>
							</view>
						</view>
						<view v-if="item.tags.length" class="content-list-card__tags">
							<tag-preview v-for="tag in item.tags" :key="item.renderKey + '-' + tag.renderKey" :tag="tag"
								 />
						</view>
						<view class="content-list-card__foot">
							<view class="content-list-card__type">
								<image class="content-type-icon" :src="item.typeIcon" mode="aspectFit" />
								<text>{{ item.typeLabel }}</text>
							</view>
							<view v-if="showPrice && item.canCart" class="content-cart-btn content-cart-btn--outline"
								hover-class="content-cart-btn--hover" @click.stop="handleCartClick(item)">
								<text class="iconfont icon-daohanggouwuche"></text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>

		<view v-else-if="contentList.length === 0" class="content-empty" @click="handleRetryClick">
			<text class="content-empty__title">{{ emptyText }}</text>
		</view>

		<view v-else-if="displayStyle === 'card'" class="content-display-card">
			<view v-for="item in contentList" :key="item.renderKey" class="content-grid-item"
				hover-class="content-grid-item--hover" @click="handleClick(item)">
				<view class="content-grid-card">
					<view v-if="showCover" class="content-grid-card__cover" :style="imageRadiusStyle">
						<image v-if="item.image" class="content-grid-card__image" :src="item.image" mode="aspectFill" />
						<view v-else class="content-cover-placeholder">
							<text>{{ item.typeLabel }}</text>
						</view>
						<view v-if="showPrice && item.canCart" class="content-cart-btn content-cart-btn--cover"
							hover-class="content-cart-btn--hover" @click.stop="handleCartClick(item)">
							<text class="iconfont icon-daohanggouwuche"></text>
						</view>
					</view>

					<view class="content-grid-card__body">
						<text class="content-grid-card__title">{{ item.title }}</text>
						<text v-if="showDesc && item.description" class="content-grid-card__desc"
							:class="item.descLineClass">{{ item.description }}</text>
						<view v-if="item.tags.length" class="content-grid-card__tags">
							<tag-preview v-for="tag in item.tags" :key="item.renderKey + '-' + tag.renderKey" :tag="tag"
								 />
						</view>
						<view v-if="item.priceText || item.originalPriceText" class="content-grid-card__price-row">
							<text class="content-grid-card__price">{{ item.priceText }}</text>
							<text v-if="item.originalPriceText"
								class="content-grid-card__original">{{ item.originalPriceText }}</text>
						</view>
						<view v-if="item.memberPriceText || item.savedPriceText" class="content-grid-card__deal">
							<view v-if="item.memberPriceText" class="content-grid-card__vip">
								<image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
								<text class="content-grid-card__vip-price">{{ item.memberPriceText }}</text>
							</view>
							<text v-if="item.savedPriceText"
								class="content-grid-card__saved">{{ item.savedPriceText }}</text>
						</view>
						<view class="content-grid-card__stat-row">
							<view class="content-grid-card__stat">
								<text class="iconfont icon-liebiao1"></text>
								<text>{{ item.statText || '0 浏览' }}</text>
							</view>
							<view class="content-grid-card__stat">
								<image class="content-type-icon content-type-icon--small" :src="item.typeIcon"
									mode="aspectFit" />
								<text>{{ item.typeLabel }}</text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>

		<view v-else-if="displayStyle === 'waterfall'" class="content-display-card">
			<view v-for="item in contentList" :key="item.renderKey" class="content-grid-item"
				hover-class="content-grid-item--hover" @click="handleClick(item)">
				<view class="content-grid-card">
					<view v-if="showCover" class="content-grid-card__cover" :style="imageRadiusStyle">
						<image v-if="item.image" class="content-grid-card__image" :src="item.image" mode="aspectFill" />
						<view v-else class="content-cover-placeholder">
							<text>{{ item.typeLabel }}</text>
						</view>
						<view v-if="showPrice && item.canCart" class="content-cart-btn content-cart-btn--cover"
							hover-class="content-cart-btn--hover" @click.stop="handleCartClick(item)">
							<text class="iconfont icon-daohanggouwuche"></text>
						</view>
						<view v-if="item.isVideo" class="content-grid-card__play">
							<text class="iconfont icon-bofang"></text>
						</view>
					</view>

					<view class="content-grid-card__body">
						<text class="content-grid-card__title">{{ item.title }}</text>
						<text v-if="showDesc && item.description" class="content-grid-card__desc"
							:class="item.descLineClass">{{ item.description }}</text>
						<view v-if="item.tags.length" class="content-grid-card__tags">
							<tag-preview v-for="tag in item.tags.slice(0, 2)" :key="item.renderKey + '-' + tag.renderKey" :tag="tag"
								 />
						</view>
						<view v-if="item.priceText || item.originalPriceText" class="content-grid-card__price-row">
							<text class="content-grid-card__price">{{ item.priceText }}</text>
							<text v-if="item.originalPriceText"
								class="content-grid-card__original">{{ item.originalPriceText }}</text>
						</view>
						<view v-if="item.memberPriceText || item.savedPriceText" class="content-grid-card__deal">
							<view v-if="item.memberPriceText" class="content-grid-card__vip">
								<image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
								<text class="content-grid-card__vip-price">{{ item.memberPriceText }}</text>
							</view>
							<text v-if="item.savedPriceText"
								class="content-grid-card__saved">{{ item.savedPriceText }}</text>
						</view>
						<view class="content-grid-card__stat-row">
							<view class="content-grid-card__stat">
								<text class="iconfont icon-liebiao1"></text>
								<text>{{ item.statText || '0 浏览' }}</text>
							</view>
							<view class="content-grid-card__stat">
								<image class="content-type-icon content-type-icon--small" :src="item.typeIcon"
									mode="aspectFit" />
								<text>{{ item.typeLabel }}</text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>

		<view v-else class="content-display-list">
			<view v-for="item in contentList" :key="item.renderKey" class="content-list-card"
				hover-class="content-list-card--hover" @click="handleClick(item)">
				<view v-if="showCover" class="content-list-card__cover" :style="imageRadiusStyle">
					<image v-if="item.image" class="content-list-card__image" :src="item.image" mode="aspectFill" />
					<view v-else class="content-cover-placeholder">
						<text>{{ item.typeLabel }}</text>
					</view>
					<view v-if="item.isVideo" class="content-list-card__play">
						<text class="iconfont icon-bofang"></text>
					</view>
				</view>
				<view class="content-list-card__body">
					<text class="content-list-card__title">{{ item.title }}</text>
					<text v-if="showDesc && item.description" class="content-list-card__desc"
						:class="item.descLineClass">{{ item.description }}</text>
					<view v-if="item.priceText || item.memberPriceText" class="content-list-card__price-row">
						<text class="content-list-card__price">{{ item.priceText }}</text>
						<view v-if="item.memberPriceText" class="content-list-card__vip">
							<image class="vip-price-icon" src="/static/images/vip.png" mode="aspectFill" />
							<text class="content-list-card__vip-price">{{ item.memberPriceText }}</text>
						</view>
					</view>
					<view v-if="item.tags.length" class="content-list-card__tags">
						<tag-preview v-for="tag in item.tags" :key="item.renderKey + '-' + tag.renderKey" :tag="tag"
							 />
					</view>
					<view class="content-list-card__foot">
						<view class="content-list-card__type">
							<image class="content-type-icon" :src="item.typeIcon" mode="aspectFit" />
							<text>{{ item.typeLabel }}</text>
						</view>
						<view v-if="showPrice && item.canCart" class="content-cart-btn content-cart-btn--outline"
							hover-class="content-cart-btn--hover" @click.stop="handleCartClick(item)">
							<text class="iconfont icon-daohanggouwuche"></text>
						</view>
					</view>
				</view>
			</view>
		</view>

		<view v-if="loadMoreText" class="content-load-more">
			<text>{{ loadMoreText }}</text>
		</view>
	</view>
</template>

<script setup>
	import {
		computed,
		getCurrentInstance,
		nextTick,
		ref
	} from 'vue'
	import {
		addCart
	} from '@/api/cart'
	import {
		navigateByLink
	} from '@/utils/link'
	import {
		getImageRadiusStyle
	} from '@/utils/diy_adapter'
	import {
		normalizeDisplayTags
	} from '@/utils/tag'
	import IMAGE_TEXT_TYPE_ICON from '../../static/imgtxt.png'
	import NOVEL_TYPE_ICON from '../../static/novel.png'
	import IMAGE_TYPE_ICON from '../../static/img.png'
	import AUDIO_TYPE_ICON from '../../static/aud.png'
	import VIDEO_TYPE_ICON from '../../static/vid.png'

	const ORDER_TYPE_CONTENT = 1

	const CONTENT_TYPE_LABEL_MAP = {
		1: '图文',
		2: '文字',
		3: '小说',
		4: '图片',
		5: '音频',
		6: '视频',
		article: '图文',
		text: '文字',
		novel: '小说',
		image: '图片',
		audio: '音频',
		video: '视频'
	}

	const CONTENT_TYPE_ICON_MAP = {
		1: IMAGE_TEXT_TYPE_ICON,
		2: IMAGE_TEXT_TYPE_ICON,
		3: NOVEL_TYPE_ICON,
		4: IMAGE_TYPE_ICON,
		5: AUDIO_TYPE_ICON,
		6: VIDEO_TYPE_ICON,
		article: IMAGE_TEXT_TYPE_ICON,
		text: IMAGE_TEXT_TYPE_ICON,
		novel: NOVEL_TYPE_ICON,
		image: IMAGE_TYPE_ICON,
		audio: AUDIO_TYPE_ICON,
		video: VIDEO_TYPE_ICON
	}

	let diyContentTabUid = 0

	defineOptions({
		name: 'DiyContent'
	})

	const props = defineProps({
		config: {
			type: Object,
			default: () => ({})
		},
		data: {
			type: [Array, Object],
			default: () => []
		},
		loading: {
			type: Boolean,
			default: false
		},
		error: {
			type: String,
			default: ''
		}
	})

	const emit = defineEmits(['category-change', 'retry'])

	const instance = getCurrentInstance()

	diyContentTabUid += 1
	const categoryTabAnchorPrefix = `diy-content-category-${diyContentTabUid}`

	const activeCategoryId = ref(0)
	const categoryTabIntoView = ref('')
	const categoryTabScrollLeft = ref(0)

	const contentList = computed(() => {
		const source = Array.isArray(props.data) ?
			props.data :
			(props.data && Array.isArray(props.data.list) ? props.data.list : [])
		const limit = isLoadMoreMode.value ? source.length : Number(props.config.show_count || source.length ||
			3)
		return source.slice(0, limit).map((item, index) => mapItem(item, index))
	})

	const isLoadMoreMode = computed(() => {
		return !isCategoryTabsStyle.value &&
			(props.config.load_more === true || Number(props.config.load_more || 0) === 1)
	})

	const displayStyle = computed(() => {
		const style = String(props.config.display_style || props.config.style || 'list').trim()
		return ['list', 'card', 'waterfall', 'category_tabs'].indexOf(style) > -1 ? style : 'list'
	})

	const isCategoryTabsStyle = computed(() => {
		return displayStyle.value === 'category_tabs' || (props.data && props.data.mode === 'category_tabs')
	})

	const categoryTabs = computed(() => {
		if (props.data && typeof props.data === 'object' && !Array.isArray(props.data)) {
			const categories = Array.isArray(props.data.categories) ? props.data.categories : []
			if (categories.length > 0) {
				return categories.map(item => ({
					id: Number(item.id || 0),
					anchorId: buildTabAnchorId(categoryTabAnchorPrefix, item.id),
					name: item.name || item.label || '分类'
				})).filter(item => item.id > 0)
			}
		}

		const tabs = normalizeConfigTabs()
		if (tabs.length === 0) {
			return []
		}

		return tabs.map((item) => ({
			...item,
			items: []
		}))
	})

	const currentCategoryId = computed(() => {
		const exists = categoryTabs.value.some(item => item.id === activeCategoryId.value)
		if (exists) {
			return activeCategoryId.value
		}
		return categoryTabs.value.length > 0 ? categoryTabs.value[0].id : 0
	})

	const activeCategoryItems = computed(() => {
		const source = Array.isArray(props.data) ?
			props.data :
			(props.data && Array.isArray(props.data.list) ? props.data.list : [])
		const limit = Number(props.config.show_count || source.length || 4)
		return source.slice(0, limit)
	})

	const categoryContentList = computed(() => {
		return activeCategoryItems.value.map((item, index) => mapItem(item, index))
	})

	const showCover = computed(() => {
		return true
	})

	const showDesc = computed(() => {
		return true
	})

	const showPrice = computed(() => {
		return true
	})

	const showMore = computed(() => {
		return !isCategoryTabsStyle.value && props.config.show_more !== false
	})

	const componentStyle = computed(() => {
		const style = {}
		if (props.config.bg_color) {
			style.backgroundColor = props.config.bg_color
		}
		return style
	})

	const imageRadiusStyle = computed(() => {
		return getImageRadiusStyle(props.config.style_config)
	})

	const emptyText = computed(() => {
		if (props.loading) {
			return '内容加载中...'
		}

		if (props.error) {
			return `${props.error}，点击重试`
		}

		return '暂无内容数据'
	})

	const loadMoreStatus = computed(() => {
		if (!isLoadMoreMode.value || contentList.value.length === 0) {
			return ''
		}

		const data = props.data && typeof props.data === 'object' ? props.data : {}
		if (data.loading_more) {
			return 'loading'
		}

		if (Object.prototype.hasOwnProperty.call(data, 'has_more')) {
			const hasMore = data.has_more === true || Number(data.has_more || 0) === 1
			if (!hasMore) {
				return 'finished'
			}
		}

		const totalPage = Number(data.totalPage || data.total_page || 0)
		if (totalPage > 0 && Number(data.page || 1) >= totalPage) {
			return 'finished'
		}

		return 'ready'
	})

	const loadMoreText = computed(() => {
		if (loadMoreStatus.value === 'loading') {
			return '加载中...'
		}
		return loadMoreStatus.value === 'finished' ? '没有数据了' : ''
	})

	/**
	 * 按 key 写入分类 Tab 滚动状态（等价于原 Options API 的 this[key] 动态赋值）。
	 *
	 * @param {string} key 状态字段名（categoryTabIntoView / categoryTabScrollLeft）。
	 * @param {*} value 状态值。
	 * @returns {void}
	 */
	function setTabScrollState(key, value) {
		if (key === 'categoryTabIntoView') {
			categoryTabIntoView.value = value
		} else if (key === 'categoryTabScrollLeft') {
			categoryTabScrollLeft.value = value
		}
	}

	function normalizeConfigTabs() {
		const rawTabs = Array.isArray(props.config.category_tabs) && props.config.category_tabs.length > 0 ?
			props.config.category_tabs :
			props.config.category_ids
		let tabs = rawTabs
		if (typeof tabs === 'string') {
			tabs = tabs.split(',')
		}
		if (!Array.isArray(tabs)) {
			tabs = [tabs]
		}
		return tabs.map(item => {
			if (item && typeof item === 'object') {
				const id = Number(item.id || item.value || 0)
				return {
					id,
					anchorId: buildTabAnchorId(categoryTabAnchorPrefix, id),
					name: item.name || item.label || `分类 #${id}`,
					items: []
				}
			}
			const id = Number(item || 0)
			return {
				id,
				anchorId: buildTabAnchorId(categoryTabAnchorPrefix, id),
				name: `分类 #${id}`,
				items: []
			}
		}).filter(item => item.id > 0)
	}

	function handleCategoryChange(categoryId) {
		const nextCategoryId = Number(categoryId || 0)
		scrollCategoryTabIntoView(nextCategoryId)
		activeCategoryId.value = nextCategoryId
		emit('category-change', activeCategoryId.value)
	}

	function scrollCategoryTabIntoView(categoryId) {
		const anchorId = buildTabAnchorId(categoryTabAnchorPrefix, categoryId)
		const index = categoryTabs.value.findIndex(item => item.id === categoryId)
		scrollHorizontalTabIntoView(anchorId, '.category-tabs-scroll', 'categoryTabIntoView',
			'categoryTabScrollLeft', index, 168)
	}

	function scrollHorizontalTabIntoView(anchorId, containerSelector, intoViewKey, scrollLeftKey, index = 0, fallbackWidth =
		160) {
		if (!anchorId) {
			return
		}

		const estimatedLeft = Math.max(0, (Number(index || 0) - 1) * fallbackWidth)
		setTabScrollState(intoViewKey, '')
		setTabScrollState(scrollLeftKey, estimatedLeft > 0 ? estimatedLeft - 1 : 0)

		nextTick(() => {
			setTimeout(() => {
				setTabScrollState(scrollLeftKey, estimatedLeft)
				setTabScrollState(intoViewKey, anchorId)
				if (typeof uni === 'undefined' || typeof uni.createSelectorQuery !== 'function') {
					return
				}

				const query = uni.createSelectorQuery().in(instance.proxy)
				query.select(containerSelector).fields({
					rect: true,
					size: true,
					scrollOffset: true
				})
				query.select(`#${anchorId}`).boundingClientRect()
				query.exec((res = []) => {
					const scrollRect = res[0] || {}
					const tabRect = res[1] || {}
					const scrollLeft = Number(scrollRect.scrollLeft || 0)
					const scrollWidth = Number(scrollRect.width || 0)
					const tabWidth = Number(tabRect.width || 0)
					const tabLeft = scrollLeft + Number(tabRect.left || 0) - Number(
						scrollRect.left || 0)
					const targetLeft = tabWidth >= scrollWidth ?
						tabLeft :
						tabLeft - ((scrollWidth - tabWidth) / 2)
					if (isFinite(targetLeft)) {
						setTabScrollState(scrollLeftKey, Math.max(0, Math.round(targetLeft)))
					}
				})
			}, 30)
		})
	}

	function buildTabAnchorId(prefix, value) {
		return `${prefix}-${String(value || 0).replace(/[^A-Za-z0-9_-]/g, '-')}`
	}

	function mapItem(item = {}, index = 0) {
		const type = item.content_type !== undefined && item.content_type !== null && item.content_type !== '' ?
			item.content_type :
			(item.type || 'article')
		const title = item.title || item.name || '内容标题'
		const description = item.description || item.intro || item.desc || ''
		const priceValue = Number(item.price || item.money || item.pay_price || 0)
		const originalPriceValue = Number(item.original_price || item.market_price || 0)
		const memberPriceValue = Number(item.member_price || item.vip_price || 0)
		const memberPriceAvailable = item.member_price_available === true ||
			Number(item.member_price_available || 0) === 1
		const savedPriceValue = originalPriceValue > priceValue && priceValue > 0 ? originalPriceValue -
			priceValue : 0
		const views = Number(item.view_count || item.views || item.learn_count || 0)
		const typeLabel = item.content_type_text || item.type_text || CONTENT_TYPE_LABEL_MAP[type] || item
			.category_name || '内容'
		const priceTextValue = String(item.price_text || '').trim()
		const isPurchased = [item.is_purchased, item.has_buy, item.is_bought].some((value) =>
			value === true || Number(value || 0) > 0
		)
		const isFreeByFlag = [item.is_free, item.free].some((value) =>
			value === true || Number(value || 0) > 0
		)
		const isFree = isFreeByFlag || priceValue <= 0 || priceTextValue === '免费'
		const descLineClass = isPurchased || isFree ? 'is-desc-two-line' : 'is-desc-one-line'
		const priceText = isPurchased ? '' : (priceTextValue || (!isFree && priceValue > 0 ?
			`¥${priceValue.toFixed(2)}` : '免费'))
		const savedPriceText = !isPurchased && !isFree && savedPriceValue > 0 ?
			`已省 ¥${savedPriceValue.toFixed(2)}` : ''
		return {
			...item,
			renderKey: `${item.id || item.content_id || item.article_id || title}_${index}`,
			title,
			description,
			image: item.cover || item.image || '',
			type,
			typeLabel,
			typeIcon: CONTENT_TYPE_ICON_MAP[type] || IMAGE_TEXT_TYPE_ICON,
			isPurchased,
			isFree,
			descLineClass,
			canCart: !isFree && priceValue > 0 && !isPurchased,
			priceText,
			originalPriceText: !isPurchased && !isFree && originalPriceValue > priceValue && priceValue > 0 ?
				`¥${originalPriceValue.toFixed(2)}` : '',
			memberPriceText: !isPurchased && !isFree && memberPriceAvailable && memberPriceValue > 0 ?
				`¥${memberPriceValue.toFixed(2)}` : '',
			savedPriceText,
			tags: parseTags(item),
			views,
			statText: item.stat_text || (views > 0 ? `${formatCount(views)} 浏览` : '0 浏览'),
			isVideo: type === 6 || type === 'video',
			isHot: Number(item.is_hot || item.hot || 0) === 1,
			cornerText: Number(item.is_hot || item.hot || 0) === 1 ? '热销' : ''
		}
	}

	function parseTags(item = {}) {
		return normalizeDisplayTags(item.tag_list, 3)
	}

	function formatCount(value) {
		const count = Number(value || 0)
		return count >= 10000 ? `${(count / 10000).toFixed(1)}w` : `${count}`
	}

	function buildContentUrl(item = {}) {
		const id = Number(item.id || item.content_id || item.article_id || 0)
		if (id <= 0) return ''

		const type = item.type !== undefined && item.type !== null ? item.type : item.content_type
		if (type === 5 || type === 'audio') {
			return `/pages/contents/content/aud/index?id=${id}`
		}
		if (type === 6 || type === 'video') {
			return `/pages/contents/content/vid/index?id=${id}`
		}
		if (type === 3 || type === 'novel') {
			return `/pages/contents/content/novel/index?id=${id}`
		}
		return `/pages/contents/content/detail/index?id=${id}`
	}

	function handleClick(item = {}) {
		if (navigateByLink(item.link_url)) {
			return
		}

		const url = buildContentUrl(item)
		if (url) {
			uni.navigateTo({
				url
			})
			return
		}

		uni.showToast({
			title: '内容详情待接入',
			icon: 'none'
		})
	}

	async function handleCartClick(item = {}) {
		const id = Number(item.id || item.content_id || item.article_id || 0)
		const price = Number(item.price || item.money || item.pay_price || 0)
		if (id <= 0) {
			uni.showToast({
				title: '内容详情待接入',
				icon: 'none'
			})
			return
		}

		if (price <= 0) {
			handleClick(item)
			return
		}

		try {
			const res = await addCart({
				goods_id: id,
				cart_num: 1,
				order_type: ORDER_TYPE_CONTENT,
				product_attr_unique: ''
			})

			if (res.code === 200) {
				uni.showToast({
					title: '已加入购物车',
					icon: 'success'
				})
				return
			}

			uni.showToast({
				title: res.msg || '加入购物车失败',
				icon: 'none'
			})
		} catch (error) {
			uni.showToast({
				title: '请先登录后操作',
				icon: 'none'
			})
		}
	}

	function handleMoreClick() {
		const moreLinkUrl = String(props.config.more_link_url || '').trim()
		if (moreLinkUrl && navigateByLink(moreLinkUrl, {
				copyTip: '小程序暂不支持直接打开外链，链接已复制'
			})) {
			return
		}

		uni.navigateTo({
			url: '/pages/contents/content/list/index'
		})
	}

	function handleRetryClick() {
		if (props.error) {
			emit('retry')
		}
	}
</script>

<style lang="scss" scoped>
	.diy-content {
		width: auto;
		box-sizing: border-box;
	}

	.content-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 16rpx 20rpx 18rpx;
	}

	.content-title {
		flex: 1;
		min-width: 0;
		color: #1a1c1c;
		font-size: 32rpx;
		line-height: 44rpx;
		font-weight: 700;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-more {
		display: flex;
		align-items: center;
		flex-shrink: 0;
		margin-left: 20rpx;
		color: #848484;
		font-size: 24rpx;
		line-height: 34rpx;
	}

	.content-more--hover {
		opacity: 0.86;
	}

	.content-more__icon {
		margin-left: 6rpx;
	}

	.content-empty {
		padding-top: 48rpx;
		padding-bottom: 48rpx;
		text-align: center;
	}

	.content-empty__title {
		font-size: 24rpx;
		color: #999999;
	}

	.content-display-list {
		padding-left: 20rpx;
		padding-right: 20rpx;
		box-sizing: border-box;
	}

	.content-display-list--tabs {
		padding-top: 2rpx;
	}

	.content-list-card {
		display: flex;
		min-height: 292rpx;
		margin-bottom: 22rpx;
		padding: 18rpx;
		border-radius: 22rpx;
		background: #ffffff;
		box-shadow: 0 10rpx 30rpx rgba(15, 23, 42, 0.06);
		box-sizing: border-box;
	}

	.content-list-card:last-child {
		margin-bottom: 0;
	}

	.content-list-card--hover {
		opacity: 0.92;
	}

	.content-list-card__cover {
		position: relative;
		flex-shrink: 0;
		width: 224rpx;
		height: 286rpx;
		overflow: hidden;
		border-radius: 18rpx;
		background: #eeeeef;
	}

	.content-list-card__image,
	.content-list-card__placeholder,
	.content-grid-card__image {
		width: 100%;
		height: 100%;
	}

	.content-list-card__play,
	.content-grid-card__play {
		position: absolute;
		right: 18rpx;
		bottom: 18rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 56rpx;
		height: 56rpx;
		border: 3rpx solid rgba(255, 255, 255, 0.88);
		border-radius: 50%;
		color: #ffffff;
		background: rgba(0, 0, 0, 0.28);
		box-sizing: border-box;
	}

	.content-list-card__play .iconfont,
	.content-grid-card__play .iconfont {
		font-size: 22rpx;
	}

	.content-list-card__body {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		padding: 6rpx 0 0 28rpx;
		box-sizing: border-box;
	}

	.content-list-card__title {
		display: block;
		height: 44rpx;
		color: #08090a;
		font-size: 34rpx;
		line-height: 44rpx;
		font-weight: 800;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-list-card__desc {
		display: block;
		height: 36rpx;
		margin-top: 12rpx;
		color: #8a8d93;
		font-size: 24rpx;
		line-height: 36rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-list-card__price-row {
		display: flex;
		align-items: center;
		min-height: 52rpx;
	}

	.content-list-card__price {
		flex-shrink: 0;
		color: #ef3333;
		font-size: 42rpx;
		line-height: 52rpx;
		font-weight: 900;
	}

	.content-list-card__vip {
		flex-shrink: 0;
		min-width: 0;
		display: flex;
		align-items: center;
		margin-left: 14rpx;
		overflow: hidden;
		white-space: nowrap;
	}

	.vip-price-icon {
		display: block;
		flex-shrink: 0;
		width: $vip-price-icon-width;
		height: $vip-price-icon-height;
		margin-right: $vip-price-icon-margin;
	}

	.content-list-card__vip-price {
		min-width: 0;
		color: $vip-price-color;
		font-size: $vip-price-font-size;
		line-height: $vip-price-line-height;
		font-weight: $vip-price-font-weight;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-list-card__tags,
	.content-grid-card__tags {
		display: flex;
		align-items: center;
		min-height: 40rpx;
		overflow: hidden;
	}

	.content-list-card__foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: auto;
		min-height: 66rpx;
	}

	.content-list-card__type {
		flex: 1;
		min-width: 0;
		display: flex;
		align-items: center;
		color: #8a8d93;
		font-size: 26rpx;
		line-height: 36rpx;
	}

	.content-type-icon {
		display: block;
		flex-shrink: 0;
		width: 30rpx;
		height: 30rpx;
		margin-right: 12rpx;

		&--small {
			width: 26rpx;
			height: 26rpx;
			margin-right: 6rpx;
		}
	}

	.content-display-card {
		display: flex;
		flex-wrap: wrap;
		margin-left: -10rpx;
		margin-right: -10rpx;
		margin-bottom: -22rpx;
		padding-left: 20rpx;
		padding-right: 20rpx;
		box-sizing: border-box;
	}

	.content-grid-item {
		width: 50%;
		padding-left: 10rpx;
		padding-right: 10rpx;
		padding-bottom: 22rpx;
		box-sizing: border-box;
	}

	.content-grid-item--hover {
		opacity: 0.92;
	}

	.content-grid-card {
		min-height: 496rpx;
		overflow: hidden;
		border-radius: 20rpx;
		background: #ffffff;
		box-shadow: 0 10rpx 30rpx rgba(15, 23, 42, 0.07);
	}

	.content-grid-card__cover {
		position: relative;
		width: 100%;
		height: 260rpx;
		overflow: hidden;
		background: #eeeeef;
	}

	.content-grid-card__badge {
		position: absolute;
		left: 14rpx;
		top: 14rpx;
		height: 42rpx;
		padding-left: 14rpx;
		padding-right: 14rpx;
		border-radius: 10rpx;
		color: #ffffff;
		font-size: 22rpx;
		line-height: 42rpx;
		font-weight: 800;
		background: linear-gradient(135deg, #8b5cf6 0%, #6d5dfc 100%);
	}

	.content-grid-card__corner {
		position: absolute;
		right: 14rpx;
		top: 14rpx;
		height: 42rpx;
		padding-left: 14rpx;
		padding-right: 14rpx;
		border-radius: 10rpx;
		color: #ffffff;
		font-size: 22rpx;
		line-height: 42rpx;
		font-weight: 800;
		background: #ff4f40;
	}

	.content-grid-card__body {
		position: relative;
		padding: 18rpx 18rpx 20rpx;
		box-sizing: border-box;
	}

	.content-grid-card__price-row {
		display: flex;
		align-items: center;
		min-height: 54rpx;
		padding-right: 58rpx;
	}

	.content-grid-card__original {
		flex-shrink: 1;
		min-width: 0;
		color: #9ca0a8;
		font-size: 22rpx;
		line-height: 30rpx;
		text-decoration: line-through;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-grid-card__price {
		flex-shrink: 0;
		margin-left: 8rpx;
		color: #ef3333;
		font-size: 34rpx;
		line-height: 46rpx;
		font-weight: 900;
	}

	.content-grid-card__title {
		display: block;
		height: 72rpx;
		margin-top: 8rpx;
		color: #08090a;
		font-size: 28rpx;
		line-height: 36rpx;
		font-weight: 800;
		overflow: hidden;
	}

	.content-grid-card__desc {
		display: block;
		height: 34rpx;
		margin-top: 8rpx;
		color: #8a8d93;
		font-size: 22rpx;
		line-height: 34rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-grid-card__tags {
		margin-top: 14rpx;
	}

	.content-grid-card__stat {
		display: block;
		height: 30rpx;
		margin-top: 12rpx;
		color: #8a8d93;
		font-size: 22rpx;
		line-height: 30rpx;
		text-align: right;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-cart-btn {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 68rpx;
		min-width: 68rpx;
		height: 68rpx;
		border-radius: 50%;
		box-sizing: border-box;
	}

	.content-cart-btn .iconfont {
		font-size: 32rpx;
		line-height: 1;
	}

	.content-cart-btn--outline {
		margin-left: 14rpx;
		border: 2rpx solid rgba(239, 51, 51, 0.58);
		color: #ef3333;
		background: #fff8f8;
		box-shadow: 0 8rpx 20rpx rgba(239, 51, 51, 0.16);
	}

	.content-cart-btn--grid {
		position: absolute;
		right: 18rpx;
		top: 18rpx;
		width: 58rpx;
		min-width: 58rpx;
		height: 58rpx;
		border: 2rpx solid rgba(239, 51, 51, 0.58);
		color: #ef3333;
		background: #fff8f8;
		box-shadow: 0 8rpx 20rpx rgba(239, 51, 51, 0.14);
	}

	.content-cart-btn--grid .iconfont {
		font-size: 28rpx;
	}

	.content-cart-btn--hover {
		opacity: 0.86;
	}

	.content-display-card {
		margin-left: -6rpx;
		margin-right: -6rpx;
		margin-bottom: -24rpx;
		padding-left: 20rpx;
		padding-right: 20rpx;
	}

	.content-grid-item {
		padding-left: 6rpx;
		padding-right: 6rpx;
		padding-bottom: 24rpx;
	}

	.content-grid-card {
		min-height: 0;
		padding: 12rpx;
		overflow: visible;
		border-radius: 32rpx;
		background: var(--surface-card, #ffffff);
		box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.06);
		box-sizing: border-box;
	}

	.content-grid-card__cover {
		height: 180rpx;
		border-radius: 28rpx;
	}

	.content-grid-card__body {
		position: relative;
		padding: 18rpx 4rpx 4rpx;
	}

	.content-grid-card__title {
		display: block;
		height: 42rpx;
		margin-top: 0;
		color: var(--text-title, #333333);
		font-size: 30rpx;
		line-height: 42rpx;
		font-weight: 800;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-grid-card__desc {
		display: -webkit-box;
		height: 68rpx;
		margin-top: 12rpx;
		color: var(--text-secondary, #999999);
		font-size: 24rpx;
		line-height: 34rpx;
		overflow: hidden;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.content-list-card__desc,
	.content-grid-card__desc {
		&.is-desc-one-line {
			display: block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		&.is-desc-two-line {
			display: -webkit-box;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: normal;
			-webkit-line-clamp: 2;
			-webkit-box-orient: vertical;
		}
	}

	.content-list-card__desc.is-desc-one-line {
		display: -webkit-box;
		height: 72rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: normal;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.content-list-card__desc.is-desc-two-line {
		height: 72rpx;
	}

	.content-grid-card__desc.is-desc-one-line {
		height: 34rpx;
	}

	.content-grid-card__desc.is-desc-two-line {
		height: 68rpx;
	}

	.content-grid-card__tags {
		min-height: 36rpx;
		margin-top: 14rpx;
	}

	.content-grid-card__price-row {
		align-items: baseline;
		min-height: 44rpx;
		margin-top: 18rpx;
		padding-right: 0;
	}

	.content-grid-card__price {
		flex-shrink: 0;
		margin-left: 0;
		color: var(--brand-primary, #ea580c);
		font-size: 36rpx;
		line-height: 44rpx;
		font-weight: 900;
	}

	.content-grid-card__original {
		flex-shrink: 1;
		min-width: 0;
		margin-left: 12rpx;
		color: var(--text-secondary, #999999);
		font-size: 22rpx;
		line-height: 30rpx;
		text-decoration: line-through;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-grid-card__deal {
		display: flex;
		align-items: center;
		min-height: 40rpx;
		margin-top: 12rpx;
		overflow: hidden;
	}

	.content-grid-card__vip,
	.content-grid-card__saved {
		flex-shrink: 0;
		max-width: 132rpx;
		height: 40rpx;
		padding: 0 12rpx;
		border-radius: 8rpx;
		font-size: 22rpx;
		line-height: 40rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-grid-card__vip {
		display: flex;
		align-items: center;
		color: var(--brand-primary, #ea580c);
	}

	.content-grid-card__vip-price {
		min-width: 0;
		color: $vip-price-color;
		font-size: $vip-price-font-size;
		line-height: $vip-price-line-height;
		font-weight: $vip-price-font-weight;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.content-grid-card__saved {
		margin-left: 8rpx;
		color: var(--text-danger, #ff3b30);
		background: rgba(255, 59, 48, 0.1);
	}

	.content-grid-card__stat-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 18rpx;
	}

	.content-grid-card__stat {
		display: flex;
		align-items: center;
		min-width: 0;
		height: 28rpx;
		margin-top: 0;
		color: var(--text-secondary, #999999);
		font-size: 20rpx;
		line-height: 28rpx;
		text-align: left;
	}

	.content-grid-card__stat .iconfont {
		flex-shrink: 0;
		margin-right: 6rpx;
		font-size: 26rpx;
		line-height: 1;
	}

	.content-cart-btn--cover {
		position: absolute;
		top: 12rpx;
		right: 12rpx;
		width: 56rpx;
		min-width: 56rpx;
		height: 56rpx;
		color: var(--brand-primary, #ea580c);
		background: var(--surface-card, #ffffff);
		box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.12);
	}

	.content-cart-btn--cover .iconfont {
		font-size: 32rpx;
	}

	.content-cart-btn--outline {
		border-color: var(--brand-shadow-hover, rgba(234, 88, 12, 0.26));
		color: var(--brand-primary, #ea580c);
		background: var(--brand-primary-pale, #fff7ed);
		box-shadow: 0 8rpx 20rpx var(--brand-focus-shadow, rgba(234, 88, 12, 0.16));
	}

	.content-category-tabs {
		padding-top: 4rpx;
		background: var(--surface-card, #ffffff);
	}

	.category-tabs-scroll {
		width: 100%;
		border-bottom: 1rpx solid #eeeeef;
		white-space: nowrap;
		box-sizing: border-box;
		margin-bottom: 20rpx;
	}

	/* #ifdef H5 */
	.category-tabs-scroll {
		scrollbar-width: none;
	}

	.category-tabs-scroll::-webkit-scrollbar {
		display: none;
		width: 0;
		height: 0;
	}

	.category-tabs-scroll ::v-deep .uni-scroll-view,
	.category-tabs-scroll ::v-deep .uni-scroll-view-content {
		overflow-x: auto !important;
		overflow-y: hidden !important;
		scrollbar-width: none !important;
		-ms-overflow-style: none !important;
	}

	.category-tabs-scroll ::v-deep .uni-scroll-view::-webkit-scrollbar,
	.category-tabs-scroll ::v-deep .uni-scroll-view-content::-webkit-scrollbar {
		display: none !important;
		width: 0 !important;
		height: 0 !important;
		background: transparent !important;
	}

	/* #endif */

	.category-tabs-track {
		display: inline-block;
		min-width: 100%;
		height: 88rpx;
		padding-left: 28rpx;
		padding-right: 28rpx;
		white-space: nowrap;
		box-sizing: border-box;
	}

	.category-tab {
		position: relative;
		display: inline-block;
		min-width: 120rpx;
		height: 88rpx;
		margin-right: 14rpx;
		padding-left: 24rpx;
		padding-right: 24rpx;
		color: #303236;
		font-size: 32rpx;
		font-weight: 700;
		line-height: 88rpx;
		text-align: center;
		vertical-align: top;
	}

	.category-tab__text {
		display: block;
		line-height: 88rpx;
		white-space: nowrap;
	}

	.category-tab--active {
		color: var(--content-tab-active, #ef3333);
		font-weight: 800;
	}

	.category-tab__line {
		position: absolute;
		left: 18rpx;
		right: 18rpx;
		bottom: 0;
		height: 6rpx;
		border-radius: 999rpx;
		background: var(--content-tab-active, #ef3333);
	}

	.category-tab--hover {
		opacity: 0.88;
	}

	.content-cover-placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 100%;
		color: #c6c6c6;
		background: #f3f3f3;
		font-size: 24rpx;
		font-weight: 700;
	}

	.content-load-more {
		padding-top: 18rpx;
		padding-bottom: 18rpx;
		text-align: center;
		color: #999999;
		font-size: 22rpx;
	}

	/* #ifdef MP-WEIXIN */
	.content-list-card,
	.content-grid-card,
	.content-cart-btn {
		box-sizing: border-box;
	}

	/* #endif */
</style>

<style lang="scss">
	/* #ifdef H5 */
	.category-tabs-scroll,
	.category-tabs-scroll .uni-scroll-view,
	.category-tabs-scroll .uni-scroll-view-content,
	.category-tabs-scroll>div {
		scrollbar-width: none !important;
		-ms-overflow-style: none !important;
	}

	.category-tabs-scroll::-webkit-scrollbar,
	.category-tabs-scroll .uni-scroll-view::-webkit-scrollbar,
	.category-tabs-scroll .uni-scroll-view-content::-webkit-scrollbar,
	.category-tabs-scroll>div::-webkit-scrollbar {
		display: none !important;
		width: 0 !important;
		height: 0 !important;
		background: transparent !important;
	}

	/* #endif */
</style>
