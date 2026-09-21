<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="address-page">
    <app-safe-top title="收货地址" background="#ffffff" />

    <!-- 主内容区 -->
    <scroll-view scroll-y class="address-scroll">
      <view class="address-content">
        <view v-if="loading" class="address-state">
          <text class="address-state__text">加载中...</text>
        </view>

        <view v-else-if="!hasAddress" class="address-empty">
          <image class="address-empty__image" :src="emptyImage" mode="aspectFit" />
          <text class="address-empty__title">暂无收货地址</text>
          <text class="address-empty__desc">添加地址后，下单会更方便</text>
        </view>

        <template v-else>
          <!-- 常用收货地址 -->
          <view v-if="defaultAddress" class="address-section">
            <text class="address-section__title">常用收货地址</text>
            <view class="address-card">
              <view class="address-card__header">
                <view class="address-card__name-row">
                  <text class="address-card__name">{{ defaultAddress.name }}</text>
                  <view class="address-card__tag address-card__tag--default">默认</view>
                </view>
                <view class="address-card__edit" @click="handleEdit(defaultAddress.id)">
                  <uni-icons type="compose" size="17" color="#9ca3af"></uni-icons>
                </view>
              </view>
              <text class="address-card__phone">{{ defaultAddress.phone }}</text>
              <text class="address-card__detail">{{ defaultAddress.detail }}</text>
              <view class="address-card__footer">
                <view class="address-card__delete" @click="handleDelete(defaultAddress.id)">
                  <uni-icons type="trash" size="13" color="#9ca3af"></uni-icons>
                  <text class="address-card__delete-text">删除</text>
                </view>
              </view>
            </view>
          </view>

          <!-- 其他收货地址 -->
          <view v-if="otherAddresses.length" class="address-section">
            <text class="address-section__title">其他收货地址</text>
            <view class="address-list">
              <view
                v-for="item in otherAddresses"
                :key="item.id"
                class="address-card"
              >
                <view class="address-card__header">
                  <text class="address-card__name">{{ item.name }}</text>
                  <view class="address-card__edit" @click="handleEdit(item.id)">
                    <uni-icons type="compose" size="17" color="#9ca3af"></uni-icons>
                  </view>
                </view>
                <text class="address-card__phone">{{ item.phone }}</text>
                <text class="address-card__detail">{{ item.detail }}</text>
                <view class="address-card__footer">
                  <view class="address-card__delete" @click="handleDelete(item.id)">
                    <uni-icons type="trash" size="13" color="#9ca3af"></uni-icons>
                    <text class="address-card__delete-text">删除</text>
                  </view>
                </view>
              </view>
            </view>

          </view>
        </template>
      </view>
    </scroll-view>

    <!-- 底部添加按钮 -->
    <view class="address-footer">
      <button class="add-btn" @click="handleAddAddress">
        <uni-icons type="plusempty" size="18" color="#ffffff"></uni-icons>
        <text class="add-btn__text">添加新地址</text>
      </button>
    </view>

    <app-floating-button />
  </view>
</template>

<script setup>
import { deleteAddress, getAddressList } from '@/api/address'
import { getStatusImageUrl } from '@/utils/status_images'
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'

defineOptions({ name: 'AddressList' })

const loading = ref(false)
const emptyImage = ref(getStatusImageUrl('no_address.png'))
const addressList = ref([])

const hasAddress = computed(() => {
  return addressList.value.length > 0
})

const defaultAddress = computed(() => {
  return addressList.value.find(item => item.isDefault) || null
})

const otherAddresses = computed(() => {
  return addressList.value.filter(item => !item.isDefault)
})

onLoad(() => {
  loadAddressList()
})

onShow(() => {
  loadAddressList()
})

/**
 * 加载地址列表。
 *
 * @returns {Promise<void>}
 */
async function loadAddressList() {
  loading.value = true
  try {
    const res = await getAddressList()
    const list = res.data && Array.isArray(res.data.list) ? res.data.list : []
    addressList.value = list.map(item => normalizeAddress(item))
  } catch (e) {
    console.error('加载地址列表失败', e)
  } finally {
    loading.value = false
  }
}

/**
 * 标准化地址展示字段。
 *
 * @param {Object} item 接口返回的地址项。
 * @returns {Object} 页面展示用地址对象。
 */
function normalizeAddress(item = {}) {
  const regionParts = [item.province || '', item.city || '', item.district || ''].filter(Boolean)
  const fallbackDetail = `${regionParts.join(' ')} ${item.detail || ''}`.trim()

  return {
    id: Number(item.id || 0),
    name: item.real_name || '',
    phone: item.phone || '',
    detail: item.user_address || fallbackDetail,
    isDefault: Number(item.is_default || 0) === 1
  }
}

/**
 * 编辑地址。
 *
 * @param {number} id 地址ID。
 */
function handleEdit(id) {
  uni.navigateTo({ url: `/pages/users/address/add/index?id=${id}` })
}

/**
 * 删除地址。
 *
 * @param {number} id 地址ID。
 */
function handleDelete(id) {
  uni.showModal({
    title: '提示',
    content: '确定要删除该地址吗？',
    confirmColor: '#ff4d4f',
    success: (res) => {
      if (res.confirm) {
        confirmDelete(id)
      }
    }
  })
}

/**
 * 确认删除地址。
 *
 * @param {number} id 地址ID。
 * @returns {Promise<void>}
 */
async function confirmDelete(id) {
  try {
    await deleteAddress({ id })
    uni.showToast({ title: '删除成功', icon: 'success' })
    loadAddressList()
  } catch (e) {
    console.error('删除地址失败', e)
  }
}

/** 添加新地址。 */
function handleAddAddress() {
  uni.navigateTo({ url: '/pages/users/address/add/index' })
}
</script>

<style scoped lang="scss">
.address-page {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: #f9f9f9;
}

/* ========== 滚动区域 ========== */
.address-scroll {
  flex: 1;
  height: calc(100vh - 88rpx - var(--status-bar-height, 44px) - 140rpx);
}

.address-content {
  padding: 24rpx 32rpx;
  /* 为底部按钮预留空间 */
  padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}

/* #ifdef H5 */
.address-content {
  padding-bottom: calc(140rpx + var(--window-bottom, 0));
}
/* #endif */

/* ========== 状态区域 ========== */
.address-state,
.address-empty {
  min-height: 520rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.address-state__text {
  font-size: 26rpx;
  color: #9ca3af;
}

.address-empty__image {
  width: 420rpx;
  height: 280rpx;
}

.address-empty__title {
  margin-top: 24rpx;
  font-size: 30rpx;
  font-weight: 700;
  color: #1a1c1c;
}

.address-empty__desc {
  margin-top: 12rpx;
  font-size: 24rpx;
  color: #9ca3af;
}

/* ========== 分组区域 ========== */
.address-section {
  margin-bottom: 48rpx;

  &__title {
    display: block;
    font-size: 28rpx;
    font-weight: 700;
    color: #4c4546;
    margin-bottom: 24rpx;
    padding-left: 8rpx;
  }
}

/* ========== 地址卡片 ========== */
.address-list {
  display: flex;
  flex-direction: column;
}

.address-list .address-card + .address-card {
  margin-top: 20rpx;
}

.address-card {
  background-color: #ffffff;
  border-radius: 24rpx;
  padding: 28rpx 30rpx;
  display: flex;
  flex-direction: column;

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 8rpx;
  }

  &__name-row {
    display: flex;
    align-items: center;
  }

  &__name {
    font-size: 28rpx;
    font-weight: 700;
    color: #1a1c1c;
    line-height: 1.4;
  }

  &__tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 10rpx;
    font-size: 18rpx;
    font-weight: 700;
    line-height: 1.2;
    padding: 4rpx 10rpx;
    border-radius: 6rpx;
    text-transform: uppercase;

    &--default {
      background-color: var(--brand-primary, #ea580c);
      color: #ffffff;
    }
  }

  &__edit {
    width: 52rpx;
    height: 52rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &:active {
      opacity: 0.6;
    }
  }

  &__phone {
    display: block;
    font-size: 24rpx;
    color: #5f6368;
    margin-top: 8rpx;
    line-height: 1.4;
  }

  &__detail {
    display: block;
    font-size: 26rpx;
    color: #1a1c1c;
    margin-top: 10rpx;
    line-height: 1.5;
  }

  &__footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 16rpx;
  }

  &__delete {
    display: flex;
    align-items: center;
    padding: 8rpx 12rpx;

    &:active {
      opacity: 0.6;
    }
  }

  &__delete-text {
    margin-left: 6rpx;
    font-size: 22rpx;
    color: #5f6368;
    font-weight: 500;
  }
}

/* ========== 地图 / 附近自提点 ========== */
.map-hint {
  position: relative;
  height: 240rpx;
  border-radius: 24rpx;
  overflow: hidden;
  margin-top: 48rpx;

  &__bg {
    width: 100%;
    height: 100%;
    opacity: 0.55;
  }

  &__overlay {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 60%;
    background: linear-gradient(to top, rgba(255, 255, 255, 0.85), transparent);
  }

  &__badge {
    position: absolute;
    left: 22rpx;
    bottom: 22rpx;
    display: flex;
    align-items: center;
    background-color: rgba(255, 255, 255, 0.92);
    padding: 16rpx 22rpx;
    border-radius: 16rpx;

    &:active {
      opacity: 0.85;
    }
  }

  &__badge-text {
    margin-left: 10rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #1a1c1c;
  }
}

/* ========== 底部操作区 ========== */
.address-footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 20rpx 32rpx;
  padding-bottom: calc(env(safe-area-inset-bottom) + 20rpx);
  background-color: #f9f9f9;
}

/* #ifdef H5 */
.address-footer {
  bottom: var(--window-bottom, 0);
}
/* #endif */

.add-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 96rpx;
  background-color: var(--brand-primary, #ea580c);
  color: #ffffff;
  border-radius: 24rpx;
  font-size: 28rpx;
  font-weight: 600;
  border: none;
  box-shadow: 0 8rpx 24rpx rgba(233, 89, 15, 0.3);

  &::after {
    border: none;
  }

  &:active {
    transform: scale(0.98);
    opacity: 0.92;
  }

  &__text {
    margin-left: 10rpx;
    letter-spacing: 2rpx;
  }
}
</style>
