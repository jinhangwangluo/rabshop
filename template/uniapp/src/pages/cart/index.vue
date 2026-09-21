<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="cart-page">
    <app-safe-top
      title="购物车"
      background="#ffffff"
      title-color="#1a1c1c"
      icon-color="#1a1c1c"
    >
      <template #right>
        <text class="cart-page__edit" @click="handleManageToggle">
          {{ hasCartItems ? (isEditing ? '完成' : '编辑') : '' }}
        </text>
      </template>
    </app-safe-top>

    <view class="cart-page__content">
      <template v-if="hasCartItems">
        <view
          v-for="(shop, shopIndex) in cartList"
          :key="shop.shopId"
          class="merchant-card"
        >
          <view class="merchant-card__body">
            <view class="merchant-card__head">
              <view class="cart-checkbox merchant-card__check" @click="toggleShopSelected(shopIndex)">
                <view class="cart-checkbox__box" :class="{ 'cart-checkbox__box--checked': shop.selected }">
                  <text v-if="shop.selected" class="cart-checkbox__mark">✓</text>
                </view>
              </view>

              <view class="merchant-card__title-wrap" @click="handleMerchantClick(shop)">
                <text class="merchant-card__title">{{ shop.shopName }}</text>
                <text v-if="shop.canNavigate" class="merchant-card__arrow">›</text>
              </view>

              <text
                v-if="shop.couponLabel"
                class="merchant-card__coupon"
                @click="handleCouponClick(shop)"
              >{{ shop.couponLabel }}</text>
            </view>

            <view
              v-if="shop.promotion"
              class="merchant-card__promo"
              @click="handlePromotionClick(shop)"
            >
              <view class="merchant-card__promo-main">
                <text class="merchant-card__promo-tag">{{ shop.promotion.tag }}</text>
                <text class="merchant-card__promo-text">{{ shop.promotion.text }}</text>
              </view>
              <text class="merchant-card__promo-arrow">›</text>
            </view>

            <view
              v-for="(item, itemIndex) in shop.items"
              :key="item.id"
              class="cart-item"
              :class="{ 'cart-item--with-border': itemIndex > 0 }"
            >
              <view class="cart-checkbox cart-item__check" @click="toggleItemSelected(shopIndex, itemIndex)">
                <view class="cart-checkbox__box" :class="{ 'cart-checkbox__box--checked': item.selected }">
                  <text v-if="item.selected" class="cart-checkbox__mark">✓</text>
                </view>
              </view>

              <view class="cart-item__image-wrap">
                <image class="cart-item__image" :src="item.image" mode="aspectFill" />
              </view>

              <view class="cart-item__main">
                <view class="cart-item__name-row">
                  <text class="cart-item__name">{{ item.name }}</text>
                </view>
                <view v-if="item.specsText" class="cart-item__specs" @click="handleSpecClick(item)">
                  <text class="cart-item__specs-text">{{ item.specsText }}</text>
                  <text v-if="item.canChangeSku" class="iconfont icon-triangledownfill cart-item__specs-arrow"></text>
                </view>
                <text v-if="item.subtitle" class="cart-item__subtitle">{{ item.subtitle }}</text>

                <view class="cart-item__bottom">
                  <view class="cart-item__price-wrap">
                    <text v-if="item.useMemberPrice" class="cart-item__vip-tag">VIP</text>
                    <text class="cart-item__price">¥{{ formatAmount(item.displayPrice) }}</text>
                    <text v-if="item.useMemberPrice" class="cart-item__origin-price">¥{{ formatAmount(item.price) }}</text>
                    <text v-else-if="item.originalPrice > item.price" class="cart-item__origin-price">¥{{ formatAmount(item.originalPrice) }}</text>
                  </view>

                  <view v-if="item.canChangeCount" class="stepper">
                    <view
                      class="stepper__button"
                      :class="{ 'stepper__button--disabled': Number(item.count || 0) <= 1 }"
                      @click="changeItemCount(shopIndex, itemIndex, -1)"
                    >
                      <text class="stepper__symbol">−</text>
                    </view>
                    <text class="stepper__value">{{ item.count }}</text>
                    <view class="stepper__button" @click="changeItemCount(shopIndex, itemIndex, 1)">
                      <text class="stepper__symbol">＋</text>
                    </view>
                  </view>
                  <view v-else class="cart-item__remove" @click="removeCartItem(shopIndex, itemIndex)">
                    <text class="cart-item__remove-text">删除</text>
                  </view>
                </view>
              </view>
              <PluginSlot
                name="mobile.cart.item.after"
                :context="cartItemPluginContexts[item.pluginContextKey]"
              />
            </view>
          </view>
        </view>
      </template>

      <view v-else class="cart-empty">
        <view class="cart-empty__icon-wrap">
          <text class="iconfont icon-cart cart-empty__icon"></text>
        </view>
        <text class="cart-empty__title">购物车还是空的</text>
        <view class="cart-empty__button" @click="handleContinueShopping">
          <text class="cart-empty__button-text">去逛逛</text>
        </view>
      </view>

      <view class="cart-page__recommend">
        <guess-you-like :show-more="false" :limit="4" />
      </view>

      <view v-if="hasCartItems" class="cart-page__placeholder"></view>
    </view>

    <PluginSlot
      v-if="hasCartItems"
      name="mobile.cart.checkout.before"
      :context="checkoutPluginContext"
    />

    <view v-if="hasCartItems" class="settlement-bar">
      <view class="settlement-bar__select" @click="toggleAllSelected">
        <view class="cart-checkbox">
          <view class="cart-checkbox__box" :class="{ 'cart-checkbox__box--checked': allSelected }">
            <text v-if="allSelected" class="cart-checkbox__mark">✓</text>
          </view>
        </view>
        <text class="settlement-bar__select-text">全选</text>
      </view>

      <view class="settlement-bar__summary">
        <view class="settlement-bar__summary-row">
          <text class="settlement-bar__label">{{ isEditing ? '已选:' : '合计:' }}</text>
          <template v-if="isEditing">
            <text class="settlement-bar__amount settlement-bar__amount--plain">{{ selectedCount }}</text>
            <text class="settlement-bar__label">件</text>
          </template>
          <template v-else>
            <text class="settlement-bar__amount">¥{{ formatAmount(totalPrice) }}</text>
          </template>
        </view>
        <view v-if="showDiscount" class="settlement-bar__discount-row">
          <text class="settlement-bar__discount-text">已优惠 ¥{{ formatAmount(discountAmount) }}</text>
        </view>
      </view>

      <view class="settlement-bar__button" @click="handlePrimaryAction">
        <text class="settlement-bar__button-text">{{ primaryActionText }}</text>
      </view>
    </view>

    <product-sku-popup
      :visible="showSkuPopup"
      :product="skuPopupProduct"
      :groups="skuPopupGroups"
      :selected="skuPopupSelected"
      :quantity="skuPopupQuantity"
      :max-quantity="skuPopupMaxQuantity"
      :submit-loading="actionLoading"
      :show-quantity="false"
      scene="cart"
      :actions="cartSkuActions"
      @close="closeSkuPopup"
      @select="selectSkuOption"
      @quantity-change="setSkuPopupQuantity"
      @submit="submitCartSku"
    />
  </view>
</template>

<script setup>
import { computed, ref } from 'vue'
import { onLoad, onShow, onUnload } from '@dcloudio/uni-app'
import GuessYouLike from '@/components/guess-you-like/index.vue'
import ProductSkuPopup from '@/components/product-sku-popup/index.vue'
import PluginSlot from '@/components/plugin-slot/index.vue'
import { changeCartNum, changeCartSku, deleteCart, getCartList, settlementCart } from '@/api/cart'
import { getProductSku } from '@/api/product'

defineOptions({
  name: 'CartPage'
})

const isEditing = ref(false)
const loading = ref(false)
const actionLoading = ref(false)
const settlementPreview = ref({})
const cartList = ref([])
const cartItemPluginContexts = ref({})
const showSkuPopup = ref(false)
const skuPopupItem = ref(null)
const skuPopupData = ref(createEmptySkuData())
const skuPopupSelected = ref({})
const skuPopupQuantity = ref(1)
let settlementTimer = null
const cartSkuActions = [{
    type: 'confirm',
    text: '确定',
    className: 'sku-submit--confirm'
}]
const hasCartItems = computed(() => {
    return cartItemCount.value > 0
})
const cartItemCount = computed(() => {
    return cartList.value.reduce((total, shop) => total + shop.items.length, 0)
})
const selectedItems = computed(() => {
  return cartList.value.reduce((result, shop) => result.concat(shop.items.filter(item => item.selected)), [])
})
function buildSelectedPluginPayload() {
  const result = {}
  selectedItems.value.forEach((item) => {
    const payload = item.raw && typeof item.raw.plugin_payload === 'object' ? item.raw.plugin_payload : {}
    Object.keys(payload).forEach((code) => {
      if (payload[code] && typeof payload[code] === 'object') result[code] = payload[code]
    })
  })
  return result
}

/**
 * 更新单个购物车商品的插件载荷，并保留到本次结算请求。
 *
 * @param {Object} item 页面购物车商品。
 * @param {string} pluginCode 插件编码。
 * @param {Object} value 插件命名空间载荷。
 * @param {boolean} refreshPreview 是否刷新结算预览。
 * @returns {boolean} 是否完成更新。
 */
function setCartItemPluginPayload(item, pluginCode, value, refreshPreview = true) {
  const code = String(pluginCode || '').trim().toLowerCase()
  if (!item || !code || !value || typeof value !== 'object') return false
  if (String(item.pluginCode || '').trim().toLowerCase() !== code) return false
  const nextPayload = { ...(item.pluginPayload || {}), [code]: { ...value } }
  item.pluginPayload = nextPayload
  if (item.raw && typeof item.raw === 'object') item.raw.plugin_payload = nextPayload
  const context = cartItemPluginContexts.value[item.pluginContextKey]
  if (context) context.plugin_payload = nextPayload
  if (refreshPreview) scheduleSettlementPreview()
  return true
}

/**
 * 更新当前选中商品的同一插件载荷。
 *
 * @param {string} pluginCode 插件编码。
 * @param {Object} value 插件命名空间载荷。
 * @returns {void}
 */
function setSelectedPluginPayload(pluginCode, value) {
  let changed = false
  selectedItems.value.forEach((item) => {
    changed = setCartItemPluginPayload(item, pluginCode, value, false) || changed
  })
  if (changed) scheduleSettlementPreview()
}
const checkoutPluginContext = computed(() => ({
  items: selectedItems.value,
  plugin_payload: buildSelectedPluginPayload(),
  setPluginPayload: setSelectedPluginPayload
}))
const selectedCartIds = computed(() => {
    return selectedItems.value.map(item => Number(item.id || item.cartId || 0)).filter(id => id > 0)
})
const selectedCount = computed(() => {
    return selectedItems.value.length
})
const totalPrice = computed(() => {
    const summary = settlementPreview.value.summary || {}
    if (!isEditing.value && selectedCount.value && summary.pay_amount !== undefined) {
      return Number(summary.pay_amount || 0)
    }

    return selectedItems.value.reduce((total, item) => total + Number(item.displayPrice || item.price || 0) * Number(item.count || 0), 0)
})
const discountAmount = computed(() => {
    const summary = settlementPreview.value.summary || {}
    return Number(summary.discount_amount || 0)
})
const showDiscount = computed(() => {
    return !isEditing.value && selectedCount.value > 0 && discountAmount.value > 0
})
const selectedCouponIds = computed(() => {
    const coupon = settlementPreview.value.coupon || {}
    const selectedCouponId = Number(coupon.selected_coupon_id || 0)
    if (selectedCouponId <= 0) {
      return {
        platformCouponId: 0,
        merchantCouponId: 0
      }
    }

    const merchantCouponCount = Number(coupon.merchant_coupon_count || 0)
    return {
      platformCouponId: merchantCouponCount > 0 ? 0 : selectedCouponId,
      merchantCouponId: merchantCouponCount > 0 ? selectedCouponId : 0
    }
})
const allSelected = computed(() => {
    return hasCartItems.value && cartList.value.every(shop => shop.items.every(item => item.selected))
})
const primaryActionText = computed(() => {
    if (isEditing.value) {
      return selectedCount.value ? `删除(${selectedCount.value})` : '删除'
    }

    return `结算(${selectedCount.value})`
})
const skuPopupSkuList = computed(() => {
    return Array.isArray(skuPopupData.value.sku_list) ? skuPopupData.value.sku_list : []
})
const skuPopupSelectedSpecKey = computed(() => {
    return skuPopupGroups.value.map((group) => String(skuPopupSelected.value[group.key] || '')).filter(Boolean).join('||')
})
const skuPopupSelectedComplete = computed(() => {
    return skuPopupGroups.value.length > 0 && skuPopupGroups.value.every((group) => String(skuPopupSelected.value[group.key] || '') !== '')
})
const skuPopupSelectedSku = computed(() => {
    const currentKey = skuPopupSelectedSpecKey.value
    if (skuPopupSelectedComplete.value && currentKey) {
      const matched = skuPopupSkuList.value.find((item) => String(item.spec_key || '') === currentKey)
      return matched || null
    }

    const defaultSku = skuPopupData.value.default_sku
    return defaultSku && typeof defaultSku === 'object' ? defaultSku : null
})
const skuPopupGroups = computed(() => {
    const specList = Array.isArray(skuPopupData.value.spec_list) ? skuPopupData.value.spec_list : []
    return specList.map((group, index) => ({
      key: String(group.spec_id || ''),
      title: String(group.name || ''),
      options: normalizeSkuPopupValues(group.values),
      small: index > 0
    })).filter((group) => group.key && group.title && group.options.length)
})
const skuPopupMaxQuantity = computed(() => {
    const stock = skuPopupSelectedSku.value ? skuPopupSelectedSku.value.stock : (skuPopupItem.value ? skuPopupItem.value.stock : 1)
    return Math.max(1, Number(stock || 1))
})
const skuPopupProduct = computed(() => {
    const item = skuPopupItem.value || {}
    const selectedSku = skuPopupSelectedSku.value
    const price = selectedSku ? Number(selectedSku.price || 0) : Number(item.price || 0)
    const originalPrice = selectedSku ? Number(selectedSku.ot_price || 0) : Number(item.originalPrice || 0)
    const thumb = selectedSku && String(selectedSku.pics || '').trim() ? String(selectedSku.pics || '').trim() : String(item.image || '')
    return {
      id: Number(item.goodsId || 0),
      name: String(item.name || ''),
      priceText: formatAmount(price),
      originalPriceText: originalPrice > price ? formatAmount(originalPrice) : '',
      stock: skuPopupMaxQuantity.value,
      thumb
    }
})
onLoad(() => {
  fetchCartData()
})
onShow(() => {
  fetchCartData()
})
onUnload(() => {
  clearSettlementTimer()
})
  /**
   * 初始化购物车页数据。
   *
   * @returns {Promise<void>}
   */
async function fetchCartData() {
    if (loading.value) {
      return
    }

    loading.value = true
    try {
      const res = await getCartList()
      if (!res || res.code !== 200 || !res.data) {
        cartList.value = []
        cartItemPluginContexts.value = {}
        settlementPreview.value = {}
        return
      }

      cartList.value = normalizeCartGroups(res.data)
      rebuildCartItemPluginContexts(cartList.value)
      syncAllShopSelected()
      scheduleSettlementPreview()
    } catch (error) {
      console.error('[cart] fetchCartData failed', error)
      cartList.value = []
      cartItemPluginContexts.value = {}
      settlementPreview.value = {}
    } finally {
      loading.value = false
    }
  }

  /**
   * 将接口购物车分组转换为页面展示结构。
   *
   * @param {Object} data 接口返回数据。
   * @returns {Array<Object>} 页面分组列表。
   */
function normalizeCartGroups(data = {}) {
    const groups = Array.isArray(data.groups) ? data.groups : []
    return groups.map((group) => {
      const items = Array.isArray(group.items) ? group.items.map((item) => normalizeCartItem(item)) : []
      return {
        shopId: Number(group.merchant_id || 0),
        shopName: String(group.merchant_name || '平台自营'),
        merchantId: Number(group.merchant_id || 0),
        merchantType: String(group.merchant_type || 'platform'),
        canNavigate: Number(group.merchant_id || 0) > 0,
        couponLabel: buildCouponLabel(group),
        promotion: null,
        selected: items.length > 0 && items.every(item => item.selected),
        items
      }
    }).filter(shop => shop.items.length > 0)
  }

  /**
   * 为购物车商品预构造插件插槽上下文，避免模板内创建函数或复杂对象。
   *
   * @param {Array<Object>} groups 页面购物车分组。
   * @returns {void}
   */
function rebuildCartItemPluginContexts(groups = []) {
    const contexts = {}
    groups.forEach((shop, shopIndex) => {
      shop.items.forEach((item, itemIndex) => {
        const contextKey = `${shop.shopId}:${item.cartId}:${shopIndex}:${itemIndex}`
        item.pluginContextKey = contextKey

        function setPluginPayload(pluginCode, value) {
          setCartItemPluginPayload(item, pluginCode, value)
        }

        contexts[contextKey] = {
          item: item.raw || item,
          shop,
          plugin_payload: item.pluginPayload,
          setPluginPayload
        }
      })
    })
    cartItemPluginContexts.value = contexts
  }

  /**
   * 转换购物车商品数据。
   *
   * @param {Object} item 接口商品项。
   * @returns {Object} 页面商品项。
   */
function normalizeCartItem(item = {}) {
    const price = Number(item.price || 0)
    const memberPrice = Number(item.member_price || item.effective_price || price)
    const useMemberPrice = (item.use_member_price === true || Number(item.use_member_price || 0) === 1)
      && memberPrice >= 0
      && memberPrice < price
    const originalPrice = Number(item.original_price || item.ot_price || item.market_price || item.line_price || price)
    const count = Number(item.cart_num || 1)
    return {
      id: Number(item.cart_id || item.id || 0),
      cartId: Number(item.cart_id || item.id || 0),
      goodsId: Number(item.goods_id || 0),
      orderType: Number(item.order_type || 2),
      selected: item.checked !== false,
      image: item.goods_image || '',
      name: item.goods_name || '未命名商品',
      subtitle: String(item.subtitle || item.sub_title || item.goods_subtitle || item.description || (item.product && (item.product.subtitle || item.product.sub_title || item.product.description)) || '').trim(),
      specsText: item.sku_name || getResourceTypeText(item.order_type),
      productAttrUnique: String(item.product_attr_unique || ''),
      price,
      memberPrice,
      displayPrice: useMemberPrice ? memberPrice : price,
      useMemberPrice,
      originalPrice,
      count,
      stock: item.stock,
      canChangeCount: Number(item.order_type || 2) === 2,
      canChangeSku: Number(item.order_type || 2) === 2 && Number(item.goods_id || 0) > 0,
      pluginCode: String(item.plugin_code || '').trim().toLowerCase(),
      pluginPayload: item.plugin_payload && typeof item.plugin_payload === 'object' ? item.plugin_payload : {},
      raw: item
    }
  }

  /**
   * 构造优惠券入口文案。
   *
   * @param {Object} group 购物车店铺分组。
   * @returns {string} 入口文案。
   */
function buildCouponLabel(group = {}) {
    const count = Number(group.coupon_count || group.available_coupon_count || 0)
    return count > 0 ? `${count}张券` : ''
  }

  /**
   * 获取资源类型文案。
   *
   * @param {number|string} orderType 订单类型。
   * @returns {string} 类型文案。
   */
function getResourceTypeText(orderType) {
    const type = Number(orderType || 0)
    if (type === 1) {
      return '内容商品'
    }
    if (type === 3) {
      return '专题商品'
    }
    return '默认规格'
  }

  /**
   * 刷新当前选中商品的结算预览。
   *
   * @returns {Promise<void>}
   */
async function fetchSettlementPreview() {
    if (isEditing.value || !selectedCartIds.value.length) {
      settlementPreview.value = {}
      return
    }

    try {
      const res = await settlementCart({ cart_ids: selectedCartIds.value, plugin_payload: buildSelectedPluginPayload() })
      if (res && res.code === 200 && res.data) {
        settlementPreview.value = res.data
        applySettlementMemberPrices(res.data)
      }
    } catch (error) {
      console.error('[cart] fetchSettlementPreview failed', error)
    }
  }

  /**
   * 将结算接口计算出的 VIP 单价同步到购物车商品展示。
   *
   * @param {Object} preview 购物车结算预览。
   * @returns {void}
   */
function applySettlementMemberPrices(preview = {}) {
    const groups = Array.isArray(preview.groups) ? preview.groups : []
    const priceMap = {}
    groups.forEach((group) => {
      const items = Array.isArray(group.items) ? group.items : []
      items.forEach((item) => {
        const cartId = Number(item.cart_id || item.id || 0)
        if (cartId > 0) {
          priceMap[cartId] = item
        }
      })
    })

    cartList.value.forEach((shop) => {
      shop.items.forEach((item, index) => {
        const pricing = priceMap[Number(item.cartId || item.id || 0)]
        if (!pricing) {
          return
        }
        const normalized = normalizeCartItem(Object.assign({}, item.raw || {}, pricing))
        shop.items[index] = Object.assign({}, item, normalized, { selected: item.selected })
      })
    })
    rebuildCartItemPluginContexts(cartList.value)
  }

  /**
   * 延迟刷新结算预览，避免频繁请求。
   *
   * @returns {void}
   */
function scheduleSettlementPreview() {
    clearSettlementTimer()
    settlementTimer = setTimeout(() => {
      fetchSettlementPreview()
    }, 200)
  }

  /**
   * 清理结算预览定时器。
   *
   * @returns {void}
   */
function clearSettlementTimer() {
    if (settlementTimer) {
      clearTimeout(settlementTimer)
      settlementTimer = null
    }
  }

  /**
   * 切换页面编辑态。
   *
   * @returns {void}
   */
function handleManageToggle() {
    if (!hasCartItems.value) {
      return
    }

    isEditing.value = !isEditing.value
    scheduleSettlementPreview()
  }

  /**
   * 点击商家标题区。
   *
   * @param {Object} shop 商家分组数据。
   * @returns {void}
   */
function handleMerchantClick(shop = {}) {
    if (!shop.canNavigate) {
      return
    }

    uni.navigateTo({ url: `/pages/merchant/user/index?merchant_id=${shop.merchantId}` })
  }

  /**
   * 点击领券入口。
   *
   * @param {Object} shop 商家分组数据。
   * @returns {void}
   */
function handleCouponClick(shop = {}) {
    uni.showToast({
      title: shop.couponLabel ? '请在确认订单页选择优惠券' : '暂无可领取优惠券',
      icon: 'none'
    })
  }

  /**
   * 点击活动条。
   *
   * @param {Object} shop 商家分组数据。
   * @returns {void}
   */
function handlePromotionClick(shop = {}) {
    uni.showToast({
      title: shop.promotion && shop.promotion.text ? shop.promotion.text : '暂无活动详情',
      icon: 'none'
    })
  }

/**
 * 点击规格区域，打开普通商品 SKU 弹窗。
 *
 * @param {Object} item 商品数据。
 * @returns {void}
 */
function handleSpecClick(item = {}) {
    if (!item.canChangeSku) {
      uni.showToast({ title: item.specsText || '默认规格', icon: 'none' })
      return
    }

    openSkuPopup(item)
}

/**
 * 打开购物车 SKU 切换弹窗。
 *
 * @param {Object} item 购物车商品。
 * @returns {void}
 */
function openSkuPopup(item = {}) {
    if (actionLoading.value) {
      return
    }

    skuPopupItem.value = item
    skuPopupData.value = createEmptySkuData()
    skuPopupSelected.value = {}
    skuPopupQuantity.value = Number(item.count || 1)
    showSkuPopup.value = true

    getProductSku({ id: item.goodsId }).then((res) => {
      if (res.code !== 200) {
        closeSkuPopup()
        return
      }

      skuPopupData.value = normalizeSkuData(res.data)
      applyCartSkuSelection(item.productAttrUnique)
    }).catch((res) => {
      closeSkuPopup()
      uni.showToast({
        title: resolveErrorMessage(res),
        icon: 'none'
      })
    })
}

/**
 * 关闭购物车 SKU 弹窗。
 *
 * @returns {void}
 */
function closeSkuPopup() {
    showSkuPopup.value = false
    skuPopupItem.value = null
    skuPopupData.value = createEmptySkuData()
    skuPopupSelected.value = {}
    skuPopupQuantity.value = 1
}

/**
 * 同步 SKU 弹窗选择。
 *
 * @param {Object} payload 规格选择事件参数。
 * @param {string} payload.type 规格组 ID。
 * @param {string} payload.value 规格值 ID。
 * @returns {void}
 */
function selectSkuOption(payload = {}) {
    const type = String(payload.type || '')
    const value = String(payload.value || '')
    if (!type || !value) {
      return
    }

    skuPopupSelected.value = {
      ...skuPopupSelected.value,
      [type]: value
    }
    skuPopupQuantity.value = Math.min(skuPopupMaxQuantity.value, skuPopupQuantity.value)
}

/**
 * 同步 SKU 弹窗数量。
 *
 * @param {number|string} value 数量。
 * @returns {void}
 */
function setSkuPopupQuantity(value) {
    skuPopupQuantity.value = Math.min(skuPopupMaxQuantity.value, Math.max(1, Number(value || 1)))
}

/**
 * 提交购物车 SKU 切换。
 *
 * @returns {void}
 */
function submitCartSku() {
    const item = skuPopupItem.value
    if (!item || actionLoading.value) {
      return
    }

    if (!validateCartSkuSelection()) {
      return
    }

    const productAttrUnique = String(skuPopupSelectedSku.value.uniqid || '')
    if (productAttrUnique === String(item.productAttrUnique || '')) {
      closeSkuPopup()
      return
    }

    actionLoading.value = true
    changeCartSku({
      cart_id: item.cartId,
      product_attr_unique: productAttrUnique
    }).then((res) => {
      if (res.code !== 200) {
        return
      }

      closeSkuPopup()
      uni.showToast({ title: '规格已更新', icon: 'none' })
      fetchCartData()
    }).catch((res) => {
      uni.showToast({
        title: resolveErrorMessage(res),
        icon: 'none'
      })
    }).finally(() => {
      actionLoading.value = false
    })
}

/**
 * 校验 SKU 弹窗选择。
 *
 * @returns {boolean} 是否可提交。
 */
function validateCartSkuSelection() {
    if (skuPopupGroups.value.some((group) => !skuPopupSelected.value[group.key])) {
      uni.showToast({ title: '请选择完整规格', icon: 'none' })
      return false
    }

    if (!skuPopupSelectedSku.value || !String(skuPopupSelectedSku.value.uniqid || '')) {
      uni.showToast({ title: '当前规格不可购买', icon: 'none' })
      return false
    }

    return true
}

/**
 * 规范化商品规格数据。
 *
 * @param {Object} data 商品规格接口数据。
 * @returns {Object} 可渲染规格数据。
 */
function normalizeSkuData(data = {}) {
    const source = data && typeof data === 'object' ? data : {}
    return {
      product_id: Number(source.product_id || 0),
      spec_type: Number(source.spec_type || 0),
      spec_list: Array.isArray(source.spec_list) ? source.spec_list : [],
      sku_list: Array.isArray(source.sku_list) ? source.sku_list : [],
      default_sku: source.default_sku && typeof source.default_sku === 'object' ? source.default_sku : null
    }
}

/**
 * 生成空规格数据。
 *
 * @returns {Object} 空规格数据。
 */
function createEmptySkuData() {
    return {
      product_id: 0,
      spec_type: 0,
      spec_list: [],
      sku_list: [],
      default_sku: null
    }
}

/**
 * 根据当前购物车 SKU 初始化弹窗选中态。
 *
 * @param {string} productAttrUnique 购物车记录中的 SKU 唯一值。
 * @returns {void}
 */
function applyCartSkuSelection(productAttrUnique = '') {
    const unique = String(productAttrUnique || '')
    const currentSku = skuPopupSkuList.value.find((item) => String(item.uniqid || '') === unique)
    const fallbackSku = skuPopupData.value.default_sku && typeof skuPopupData.value.default_sku === 'object'
      ? skuPopupData.value.default_sku
      : null
    const targetSku = currentSku || fallbackSku
    const valueIds = targetSku && String(targetSku.spec_key || '') ? String(targetSku.spec_key || '').split('||') : []
    const nextSelected = {}
    const specList = Array.isArray(skuPopupData.value.spec_list) ? skuPopupData.value.spec_list : []
    specList.forEach((group, index) => {
      const groupKey = String(group.spec_id || '')
      const valueId = String(valueIds[index] || '')
      if (groupKey && valueId) {
        nextSelected[groupKey] = valueId
      }
    })
    skuPopupSelected.value = nextSelected
}

/**
 * 规范化弹窗规格值。
 *
 * @param {Array<Object>} values 规格值列表。
 * @returns {Array<Object>} 弹窗规格值。
 */
function normalizeSkuPopupValues(values = []) {
    if (!Array.isArray(values)) {
      return []
    }

    return values.map((item) => {
      const value = String(item.id || '')
      return {
        label: String(item.value || ''),
        value,
        image: findSkuPopupImage(value, String(item.pic || '').trim()),
        priceText: findSkuPopupPriceText(value),
        disabled: item.available === false
      }
    }).filter((item) => item.label && item.value)
}

/**
 * 查找规格值对应图片。
 *
 * @param {string} valueId 规格值 ID。
 * @param {string} explicitImage 后端规格值图片。
 * @returns {string} 图片地址。
 */
function findSkuPopupImage(valueId, explicitImage = '') {
    if (explicitImage) {
      return explicitImage
    }

    const target = String(valueId || '')
    if (!target) {
      return ''
    }

    const sku = skuPopupSkuList.value.find((item) => String(item.spec_key || '').split('||').includes(target) && String(item.pics || '').trim())
    return sku ? String(sku.pics || '').trim() : ''
}

/**
 * 查找规格值对应价格文案。
 *
 * @param {string} valueId 规格值 ID。
 * @returns {string} 价格文案。
 */
function findSkuPopupPriceText(valueId) {
    const target = String(valueId || '')
    const sku = skuPopupSkuList.value.find((item) => String(item.spec_key || '').split('||').includes(target))
    return sku ? formatAmount(Number(sku.price || 0)) : formatAmount(Number(skuPopupItem.value ? skuPopupItem.value.price : 0))
}

/**
 * 解析请求错误文案。
 *
 * @param {Object|string} error 请求错误。
 * @returns {string} 错误文案。
 */
function resolveErrorMessage(error) {
    if (error && typeof error === 'object') {
      return String(error.msg || error.message || '请求失败')
    }

    return String(error || '请求失败')
}

  /**
   * 切换全选状态。
   *
   * @returns {void}
   */
function toggleAllSelected() {
    const nextSelected = !allSelected.value
    cartList.value.forEach((shop) => {
      shop.selected = nextSelected
      shop.items.forEach((item) => {
        item.selected = nextSelected
      })
    })
    scheduleSettlementPreview()
  }

  /**
   * 切换店铺勾选状态。
   *
   * @param {number} shopIndex 店铺下标。
   * @returns {void}
   */
function toggleShopSelected(shopIndex) {
    const shop = cartList.value[shopIndex]
    if (!shop) {
      return
    }

    const nextSelected = !shop.selected
    shop.selected = nextSelected
    shop.items.forEach((item) => {
      item.selected = nextSelected
    })
    scheduleSettlementPreview()
  }

  /**
   * 切换商品勾选状态。
   *
   * @param {number} shopIndex 店铺下标。
   * @param {number} itemIndex 商品下标。
   * @returns {void}
   */
function toggleItemSelected(shopIndex, itemIndex) {
    const shop = cartList.value[shopIndex]
    const item = shop && shop.items[itemIndex]

    if (!item) {
      return
    }

    item.selected = !item.selected
    syncShopSelected(shopIndex)
    scheduleSettlementPreview()
  }

  /**
   * 调整商品购买数量。
   *
   * @param {number} shopIndex 店铺下标。
   * @param {number} itemIndex 商品下标。
   * @param {number} delta 数量增减值。
   * @returns {Promise<void>}
   */
async function changeItemCount(shopIndex, itemIndex, delta) {
    const shop = cartList.value[shopIndex]
    const item = shop && shop.items[itemIndex]

    if (!item || actionLoading.value) {
      return
    }
    if (!item.canChangeCount) {
      uni.showToast({ title: '该商品数量固定为1', icon: 'none' })
      return
    }

    const nextCount = Math.max(1, Number(item.count || 0) + Number(delta || 0))
    if (nextCount === Number(item.count || 0)) {
      return
    }

    const oldCount = item.count
    item.count = nextCount
    actionLoading.value = true
    try {
      const res = await changeCartNum({ cart_id: item.cartId, cart_num: nextCount })
      if (!res || res.code !== 200) {
        item.count = oldCount
        return
      }

      if (res.data) {
        const nextItem = normalizeCartItem(res.data)
        Object.assign(item, nextItem, { selected: item.selected })
      }
      scheduleSettlementPreview()
    } catch (error) {
      item.count = oldCount
      console.error('[cart] changeItemCount failed', error)
    } finally {
      actionLoading.value = false
    }
  }

  /**
   * 删除单个购物车商品。
   *
   * @param {number} shopIndex 店铺下标。
   * @param {number} itemIndex 商品下标。
   * @returns {void}
   */
function removeCartItem(shopIndex, itemIndex) {
    const shop = cartList.value[shopIndex]
    const item = shop && shop.items[itemIndex]
    if (!item) {
      return
    }

    deleteCartItems([item.cartId])
  }

  /**
   * 删除当前选中的商品。
   *
   * @returns {void}
   */
function removeSelectedItems() {
    if (!selectedCount.value) {
      uni.showToast({ title: '请选择要删除的商品', icon: 'none' })
      return
    }

    deleteCartItems(selectedCartIds.value)
  }

  /**
   * 调用接口删除购物车商品。
   *
   * @param {Array<number>} ids 购物车 ID 列表。
   * @returns {Promise<void>}
   */
async function deleteCartItems(ids = []) {
    const cartIds = ids.map(id => Number(id || 0)).filter(id => id > 0)
    if (!cartIds.length || actionLoading.value) {
      return
    }

    uni.showModal({
      title: '确认删除',
      content: cartIds.length > 1 ? `确定删除选中的 ${cartIds.length} 件商品吗？` : '确定删除该商品吗？',
      success: async (modalRes) => {
        if (!modalRes.confirm) {
          return
        }

        actionLoading.value = true
        try {
          const res = await deleteCart({ ids: cartIds })
          if (!res || res.code !== 200) {
            return
          }

          removeLocalCartItems(cartIds)
          isEditing.value = false
          uni.showToast({ title: '删除成功', icon: 'none' })
        } catch (error) {
          console.error('[cart] deleteCartItems failed', error)
        } finally {
          actionLoading.value = false
        }
      }
    })
  }

  /**
   * 从本地列表移除已删除商品。
   *
   * @param {Array<number>} ids 购物车 ID 列表。
   * @returns {void}
   */
function removeLocalCartItems(ids = []) {
    const idMap = ids.reduce((result, id) => {
      result[Number(id)] = true
      return result
    }, {})
    cartList.value = cartList.value
      .map(shop => ({
        ...shop,
        items: shop.items.filter(item => !idMap[Number(item.cartId || item.id || 0)])
      }))
      .filter(shop => shop.items.length > 0)
    cleanupEmptyShops()
    scheduleSettlementPreview()
  }

  /**
   * 处理底部主操作。
   *
   * @returns {void}
   */
function handlePrimaryAction() {
    if (isEditing.value) {
      removeSelectedItems()
      return
    }

    if (!selectedCount.value) {
      uni.showToast({ title: '请选择要结算的商品', icon: 'none' })
      return
    }

    const query = [
      `cart_ids=${encodeURIComponent(selectedCartIds.value.join(','))}`
    ]
    if (selectedCouponIds.value.platformCouponId > 0) {
      query.push(`platform_coupon_id=${selectedCouponIds.value.platformCouponId}`)
    }
    if (selectedCouponIds.value.merchantCouponId > 0) {
      query.push(`merchant_coupon_id=${selectedCouponIds.value.merchantCouponId}`)
    }

    uni.navigateTo({
      url: `/pages/orders/confirm/index?${query.join('&')}`
    })
  }

  /**
   * 空状态继续购物。
   *
   * @returns {void}
   */
function handleContinueShopping() {
    uni.switchTab({ url: '/pages/index/index' })
  }

  /**
   * 同步全部店铺勾选状态。
   *
   * @returns {void}
   */
function syncAllShopSelected() {
    cartList.value.forEach((_, shopIndex) => {
      syncShopSelected(shopIndex)
    })
  }

  /**
   * 同步店铺勾选状态。
   *
   * @param {number} shopIndex 店铺下标。
   * @returns {void}
   */
function syncShopSelected(shopIndex) {
    const shop = cartList.value[shopIndex]
    if (!shop) {
      return
    }

    shop.selected = shop.items.length > 0 && shop.items.every(item => item.selected)
  }

  /**
   * 清理空店铺并同步编辑态。
   *
   * @returns {void}
   */
function cleanupEmptyShops() {
    cartList.value = cartList.value.filter(shop => shop.items.length > 0)
    syncAllShopSelected()

    if (!cartList.value.length) {
      isEditing.value = false
    }
  }

  /**
   * 格式化金额文本。
   *
   * @param {number|string} value 金额值。
   * @param {number} fractionDigits 保留小数位数。
   * @returns {string} 格式化后的金额文本。
   */
function formatAmount(value, fractionDigits = 2) {
    const amount = Number(value || 0)
    if (!Number.isFinite(amount)) {
      return fractionDigits > 0 ? '0.00' : '0'
    }

    return amount.toLocaleString('en-US', {
      minimumFractionDigits: fractionDigits,
      maximumFractionDigits: fractionDigits
    })
  }
</script>

<style lang="scss">
page {
  background: #f2f3f5;
}
</style>

<style lang="scss" scoped>
.cart-page {
  --cart-bg: #f2f3f5;
  --cart-surface: #ffffff;
  --cart-surface-soft: #f3f4f6;
  --cart-border: #e2e2e2;
  --cart-divider: #f0f0f0;
  --cart-text: #1a1c1c;
  --cart-text-soft: #4c4546;
  --cart-text-muted: #71717a;
  --cart-text-faint: #a1a1aa;
  --cart-accent: var(--brand-primary, #ea580c);
  --cart-accent-strong: var(--brand-primary, #ea580c);
  --cart-accent-soft: var(--brand-primary-pale, #fff7ed);
  --cart-accent-shadow: rgba(255, 69, 0, 0.2);
  min-height: 100%;
  background: var(--cart-bg);
}

.cart-page__edit {
  font-size: 28rpx;
  line-height: 1;
  color: var(--cart-text);
}

.cart-page__content {
  padding: 16rpx 32rpx 0;
}

.cart-page__placeholder {
  height: 236rpx;
}

.merchant-card {
  margin-bottom: 24rpx;
  border-radius: 24rpx;
  background: var(--cart-surface);
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.03);
  overflow: hidden;
}

.merchant-card__body {
  padding: 24rpx;
}

.merchant-card__head {
  display: flex;
  align-items: center;
}

.merchant-card__check {
  margin-right: 16rpx;
}

.merchant-card__title-wrap {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
}

.merchant-card__title {
  max-width: 360rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 34rpx;
  line-height: 48rpx;
  font-weight: 700;
  color: var(--cart-text);
}

.merchant-card__arrow {
  margin-left: 10rpx;
  font-size: 28rpx;
  line-height: 1;
  color: var(--cart-text-soft);
}

.merchant-card__coupon {
  margin-left: 16rpx;
  padding: 6rpx 16rpx;
  border-radius: 999rpx;
  background: var(--cart-accent-soft);
  font-size: 20rpx;
  line-height: 1.3;
  color: var(--cart-accent);
}

.merchant-card__promo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 24rpx;
  padding: 16rpx 18rpx;
  border-radius: 16rpx;
  background: rgba(243, 65, 0, 0.06);
}

.merchant-card__promo-main {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
}

.merchant-card__promo-tag {
  flex-shrink: 0;
  padding: 6rpx 12rpx;
  border-radius: 8rpx;
  background: var(--cart-accent);
  font-size: 18rpx;
  line-height: 1.2;
  font-weight: 700;
  color: #ffffff;
}

.merchant-card__promo-text {
  flex: 1;
  min-width: 0;
  margin-left: 16rpx;
  font-size: 20rpx;
  line-height: 1.4;
  color: var(--cart-accent);
}

.merchant-card__promo-arrow {
  margin-left: 16rpx;
  font-size: 28rpx;
  line-height: 1;
  color: var(--cart-accent);
}

.cart-item {
  display: flex;
  padding: 24rpx 0;
}

.cart-item--with-border {
  border-top: 1rpx solid var(--cart-divider);
}

.cart-item__check {
  margin-top: 52rpx;
  margin-right: 16rpx;
  flex-shrink: 0;
}

.cart-item__image-wrap {
  position: relative;
  width: 192rpx;
  height: 192rpx;
  flex-shrink: 0;
}

.cart-item__image {
  width: 192rpx;
  height: 192rpx;
  border-radius: 16rpx;
  background: #f6f6f6;
}

.cart-item__main {
  flex: 1;
  min-width: 0;
  min-height: 192rpx;
  margin-left: 20rpx;
  display: flex;
  flex-direction: column;
}

.cart-item__name-row {
  display: flex;
  align-items: flex-start;
}

.cart-item__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 28rpx;
  line-height: 42rpx;
  font-weight: 700;
  color: var(--cart-text);
}

.cart-item__delete {
  margin-left: 16rpx;
  font-size: 22rpx;
  line-height: 1.4;
  color: var(--cart-accent);
  flex-shrink: 0;
}

.cart-item__subtitle {
  display: block;
  margin-top: 8rpx;
  max-width: 100%;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 22rpx;
  line-height: 32rpx;
  color: var(--cart-text-muted);
}

.cart-item__specs {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  max-width: 100%;
  margin-top: 10rpx;
  padding: 8rpx 14rpx;
  border-radius: 999rpx;
  background: var(--cart-surface-soft);
}

.cart-item__specs-text {
  display: block;
  max-width: 340rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 20rpx;
  line-height: 1.3;
  color: var(--cart-text-muted);
}

.cart-item__specs-arrow {
  margin-left: 8rpx;
  font-size: 18rpx;
  line-height: 1;
  color: var(--cart-text-muted);
  flex-shrink: 0;
}

.cart-item__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 16rpx;
}

.cart-item__price-wrap {
  flex: 1;
  min-width: 0;
  padding-right: 16rpx;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.cart-item__vip-tag {
  display: inline-block;
  margin-right: 8rpx;
  padding: 3rpx 6rpx;
  border-radius: 8rpx;
  background: #1a1c1c;
  font-size: 18rpx;
  line-height: 22rpx;
  font-weight: 700;
  color: #ffd76a;
}

.cart-item__price {
  display: inline-block;
  font-size: 28rpx;
  line-height: 1.2;
  font-weight: 700;
  color: var(--cart-accent);
}

.cart-item__origin-price {
  display: inline-block;
  margin-left: 8rpx;
  font-size: 20rpx;
  line-height: 1.2;
  color: var(--cart-text-faint);
  text-decoration: line-through;
}

.stepper {
  display: flex;
  align-items: center;
  border-radius: 16rpx;
  background: var(--cart-surface-soft);
  overflow: hidden;
  flex-shrink: 0;
}

.stepper__button {
  width: 56rpx;
  height: 56rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stepper__button--disabled .stepper__symbol {
  color: #d4d4d8;
}

.stepper__symbol {
  font-size: 26rpx;
  line-height: 1;
  color: var(--cart-text-soft);
}

.stepper__value {
  width: 56rpx;
  text-align: center;
  font-size: 24rpx;
  line-height: 56rpx;
  font-weight: 700;
  color: var(--cart-text);
}

.cart-item__remove {
  flex-shrink: 0;
  height: 56rpx;
  padding: 0 28rpx;
  border-radius: 999rpx;
  background: var(--cart-accent-soft);
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-item__remove-text {
  font-size: 24rpx;
  line-height: 1;
  color: var(--cart-accent);
}

.cart-checkbox {
  width: 40rpx;
  height: 40rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cart-checkbox__box {
  width: 40rpx;
  height: 40rpx;
  border-radius: 8rpx;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-checkbox__box--checked {
  background: var(--cart-accent);
}

.cart-checkbox__mark {
  font-size: 24rpx;
  line-height: 1;
  color: #ffffff;
  font-weight: 700;
}

.cart-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 96rpx 32rpx 72rpx;
}

.cart-empty__icon-wrap {
  width: 240rpx;
  height: 240rpx;
  border-radius: 50%;
  background: #ececec;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-empty__icon {
  font-size: 112rpx;
  line-height: 1;
  color: #c7c7cc;
}

.cart-empty__title {
  margin-top: 36rpx;
  font-size: 26rpx;
  line-height: 1.4;
  color: var(--cart-text-muted);
}

.cart-empty__button {
  margin-top: 28rpx;
  padding: 0 40rpx;
  height: 72rpx;
  border-radius: 16rpx;
  border: 2rpx solid var(--cart-text);
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cart-empty__button-text {
  font-size: 28rpx;
  line-height: 1;
  font-weight: 700;
  color: var(--cart-text);
}

.cart-page__recommend {
  padding-bottom: 32rpx;
}

.settlement-bar {
  position: fixed;
  left: 32rpx;
  right: 32rpx;
  bottom: 112rpx;
  z-index: 20;
  display: flex;
  align-items: center;
  padding: 18rpx 20rpx 18rpx 16rpx;
  border: 1rpx solid var(--cart-border);
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.settlement-bar__select {
  display: flex;
  align-items: center;
  min-width: 116rpx;
  flex-shrink: 0;
}

.settlement-bar__select-text {
  margin-left: 10rpx;
  font-size: 24rpx;
  line-height: 1.3;
  color: var(--cart-text-muted);
}

.settlement-bar__summary {
  flex: 1;
  min-width: 0;
  padding: 0 18rpx 0 12rpx;
  text-align: right;
}

.settlement-bar__summary-row {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
}

.settlement-bar__discount-row {
  margin-top: 6rpx;
}

.settlement-bar__discount-text {
  font-size: 20rpx;
  line-height: 1.3;
  color: var(--cart-accent);
}

.settlement-bar__label {
  font-size: 22rpx;
  line-height: 1.3;
  color: var(--cart-text-soft);
}

.settlement-bar__amount {
  margin-left: 6rpx;
  font-size: 28rpx;
  line-height: 1.2;
  font-weight: 700;
  color: var(--cart-accent);
}

.settlement-bar__amount--plain {
  color: var(--cart-text);
}

.settlement-bar__button {
  height: 88rpx;
  min-width: 180rpx;
  padding: 0 30rpx;
  border-radius: 999rpx;
  background: var(--cart-accent-strong);
  box-shadow: 0 10rpx 20rpx var(--cart-accent-shadow);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.settlement-bar__button-text {
  font-size: 30rpx;
  line-height: 1;
  font-weight: 700;
  color: #ffffff;
}
</style>
