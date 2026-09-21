<template>
  <div class="user-center-preview">
    <div class="phone-shell">
      <div class="phone-status-bar">
        <span>9:41</span>
        <span class="status-icons"><LegacyIcon name="el-icon-signal" /><LegacyIcon name="el-icon-wifi" /><LegacyIcon name="el-icon-battery-full" /></span>
      </div>
      <div class="phone-body">
        <div class="phone-content">
          <div class="hero-section">
            <div class="hero-profile">
              <div class="avatar-shell" />
              <div class="profile-info">
                <div class="profile-name">林知行</div>
                <div class="profile-id">ID: 88294710</div>
              </div>
              <div class="profile-actions"><LegacyIcon name="el-icon-setting" /><LegacyIcon name="el-icon-bell" /></div>
            </div>
            <div class="asset-card">
              <div v-for="item in assetCards" :key="item.label" class="asset-item">
                <strong>{{ item.value }}</strong>
                <span>{{ item.label }}</span>
              </div>
              <div class="vip-strip">黄金会员 · 查看权益</div>
            </div>
          </div>

          <div class="content-section">
            <div class="section-card editable-card" :class="{ 'is-active': selectedKey === 'order_menu' }" @click.stop="handleSelect('order_menu')">
              <div class="editable-tag">可配置</div>
              <div class="section-header"><span>{{ orderMenu.title || '我的订单' }}</span><em>全部订单 ></em></div>
              <div class="order-list">
                <div v-for="item in orderMenus" :key="item.key" class="order-item">
                  <div class="order-icon">
                    <img v-if="item.image" :src="item.image" alt="order">
                    <LegacyIcon
                      v-else-if="item.icon"
                      :name="resolvePreviewIconClass(item.icon)"
                      class="order-item__icon-font"
                    />
                    <img v-else-if="item.fallbackImage" :src="item.fallbackImage" alt="order">
                    <LegacyIcon
                      v-else-if="item.fallbackIcon"
                      :name="resolvePreviewIconClass(item.fallbackIcon)"
                      class="order-item__icon-font"
                    />
                  </div>
                  <span>{{ item.name }}</span>
                </div>
              </div>
            </div>

            <div class="section-card editable-card" :class="{ 'is-active': selectedKey === 'service_menu' }" @click.stop="handleSelect('service_menu')">
              <div class="editable-tag">可配置</div>
              <div class="section-header"><span>{{ serviceMenu.title || '必备服务' }}</span></div>
              <div class="service-list">
                <div v-for="(item, index) in serviceItems" :key="index" class="service-item" :style="serviceItemStyle">
                  <div class="service-icon">
                    <img v-if="item.image" :src="item.image" alt="menu">
                    <LegacyIcon v-else name="el-icon-menu" />
                  </div>
                  <span>{{ item.name || '菜单' }}</span>
                </div>
              </div>
            </div>

            <div class="promo-banner editable-card" :class="{ 'is-active': selectedKey === 'banner' }" @click.stop="handleSelect('banner')">
              <div class="editable-tag">可配置</div>
              <img v-if="banner.image" :src="banner.image" alt="banner">
              <div v-else class="banner-placeholder" />
              <div class="banner-mask">
                <strong>{{ banner.title || '广告标题' }}</strong>
                <span>{{ banner.desc || '广告描述' }}</span>
              </div>
            </div>

            <div class="recommend-section">
              <div class="recommend-title">猜你喜欢</div>
              <div class="recommend-grid">
                <div v-for="item in 4" :key="item" class="product-card">
                  <div class="product-img" />
                  <div class="product-name">精选知识课程</div>
                  <div class="product-price">¥{{ item }}99</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import orderNoAddressImage from '@/assets/images/diy/user-center/order_no_adderss.png'
import orderNoAssessImage from '@/assets/images/diy/user-center/order_no_assess.png'
import orderNoPayImage from '@/assets/images/diy/user-center/order_no_pay.png'
import orderNoRefundImage from '@/assets/images/diy/user-center/order_no_refund.png'
import orderNoShipImage from '@/assets/images/diy/user-center/order_no_ship.png'

const DEFAULT_ORDER_ITEMS = [
  { key: 'pending-payment', name: '待付款', icon: 'icon-moreandroid', image: orderNoPayImage },
  { key: 'pending-shipment', name: '待发货', icon: 'icon-send', image: orderNoShipImage },
  { key: 'pending-receipt', name: '待收货', icon: 'icon-order', image: orderNoAddressImage },
  { key: 'pending-review', name: '待评价', icon: 'icon-evaluate', image: orderNoAssessImage },
  { key: 'after-sale', name: '售后', icon: 'icon-refund', image: orderNoRefundImage }
]

defineOptions({
  name: 'UserCenterPreview'
})

const props = defineProps({
  serviceMenu: {
    type: Object,
    default: () => ({})
  },
  orderMenu: {
    type: Object,
    default: () => ({})
  },
  banner: {
    type: Object,
    default: () => ({})
  },
  selectedKey: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['select'])
const assetCards = [
  { label: '余额', value: '¥1,280.50' },
  { label: '积分', value: '3,420' },
  { label: '佣金', value: '¥452.00' }
]

const orderMenus = computed(() => {
  const items = Array.isArray(props.orderMenu.items) ? props.orderMenu.items : []
  const itemsByKey = {}

  items.forEach((item) => {
    if (item && item.key) {
      itemsByKey[item.key] = item
    }
  })

  return DEFAULT_ORDER_ITEMS.map((defaultItem, index) => {
    const current = itemsByKey[defaultItem.key] || items[index] || {}
    const icon = String(current.icon || '').trim()
    const image = String(current.image || defaultItem.image || '').trim()
    return {
      key: defaultItem.key,
      name: current.name || current.text || current.title || defaultItem.name,
      icon,
      image,
      fallbackIcon: defaultItem.icon,
      fallbackImage: defaultItem.image
    }
  })
})

const serviceItems = computed(() => (Array.isArray(props.serviceMenu.items) ? props.serviceMenu.items : []))
const serviceItemStyle = computed(() => {
  const columns = [3, 4, 5].includes(Number(props.serviceMenu.columns)) ? Number(props.serviceMenu.columns) : 4
  return { width: `${100 / columns}%` }
})

function resolvePreviewIconClass(icon) {
  const value = String(icon || '').trim()
  if (!value) {
    return ''
  }

  return value.indexOf('el-icon-') === 0 ? value : ['iconfont', value]
}

function handleSelect(key) {
  emit('select', key)
}
</script>

<style lang="scss" scoped>
.user-center-preview { display: flex; justify-content: center; }
.phone-shell { width: 375px; overflow: hidden; border: 0; border-radius: 0; background: #f5f7fa; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
.phone-status-bar { display: flex; justify-content: space-between; align-items: center; height: 34px; padding: 0 18px; color: #111827; background: #f7ebda; font-size: 12px; font-weight: 600; }
.status-icons { display: inline-flex; gap: 5px; }
.phone-body { height: 720px; overflow: hidden auto; -ms-overflow-style: none; scrollbar-width: none; }
.phone-body::-webkit-scrollbar { display: none; width: 0; height: 0; }
.phone-content { min-height: 100%; background: #f5f7fa; }
.hero-section { padding: 20px 16px 22px; background: linear-gradient(135deg, #f7ebda 0%, #fff7ed 100%); }
.hero-profile { display: flex; align-items: center; gap: 12px; }
.avatar-shell { width: 54px; height: 54px; border: 3px solid #fff; border-radius: 50%; background: linear-gradient(135deg, #c8a15a, #f6e6c8); }
.profile-info { flex: 1; }
.profile-name { color: #1f2937; font-size: 17px; font-weight: 700; }
.profile-id { margin-top: 4px; color: #909399; font-size: 12px; }
.profile-actions { display: flex; gap: 8px; color: #606266; }
.asset-card { margin-top: 18px; padding: 16px; border-radius: 18px; background: #fff; box-shadow: 0 12px 32px rgba(184, 138, 61, 0.14); }
.asset-item { display: inline-flex; flex-direction: column; align-items: center; width: 33.33%; color: #606266; font-size: 12px; }
.asset-item strong { color: #1f2937; font-size: 16px; }
.vip-strip { margin-top: 14px; padding: 9px 12px; border-radius: 999px; color: #f6e6c8; background: linear-gradient(90deg, #1f2937, #4b3b27); font-size: 12px; }
.content-section { padding: 14px 14px 30px; }
.section-card { position: relative; margin-bottom: 14px; padding: 14px; border-radius: 16px; background: #fff; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; color: #1f2937; font-weight: 700; }
.section-header em { color: #909399; font-style: normal; font-size: 12px; font-weight: 400; }
.order-list, .service-list { display: flex; flex-wrap: wrap; }
.order-item { display: flex; flex-direction: column; align-items: center; width: 20%; color: #606266; font-size: 12px; }
.order-icon { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; margin-bottom: 8px; color: #c8a15a; font-size: 22px; }
.order-icon img { display: block; width: 100%; height: 100%; object-fit: contain; }
.order-item__icon-font { font-size: 22px; line-height: 1; color: #c8a15a; }
.service-item { display: flex; flex-direction: column; align-items: center; margin-bottom: 12px; color: #606266; font-size: 12px; }
.service-icon { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin-bottom: 7px; border-radius: 14px; color: #c8a15a; background: #fff7ed; font-size: 20px; }
.service-icon img { width: 100%; height: 100%; border-radius: 14px; object-fit: cover; }
.editable-card { cursor: pointer; border: 1px solid transparent; transition: all .2s; }
.editable-card:hover, .editable-card.is-active { border-color: #c8a15a; box-shadow: 0 0 0 3px rgba(200, 161, 90, .14); }
.editable-tag { position: absolute; top: -8px; right: 12px; z-index: 2; padding: 2px 8px; border-radius: 999px; color: #fff; background: #c8a15a; font-size: 12px; }
.promo-banner { position: relative; height: 118px; margin-bottom: 14px; overflow: hidden; border-radius: 18px; }
.promo-banner img, .banner-placeholder { width: 100%; height: 100%; object-fit: cover; background: linear-gradient(135deg, #c8a15a, #f6e6c8); }
.banner-mask { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; padding: 0 22px; color: #fff; background: linear-gradient(90deg, rgba(0,0,0,.38), rgba(0,0,0,.05)); }
.banner-mask strong { font-size: 18px; }
.banner-mask span { margin-top: 6px; font-size: 12px; }
.recommend-title { margin: 18px 0 12px; text-align: center; color: #606266; font-weight: 600; }
.recommend-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.product-card { width: calc(50% - 5px); padding: 8px; border-radius: 14px; background: #fff; }
.product-img { height: 96px; border-radius: 12px; background: #f6e6c8; }
.product-name { margin-top: 8px; color: #1f2937; font-size: 12px; }
.product-price { margin-top: 6px; color: #c8a15a; font-weight: 700; }
</style>
