<template>
	<page-meta :page-style="themePageStyle"></page-meta>
  <view class="agreement-page">
    <app-safe-top :title="title" background="#ffffff" title-color="#1a1c1c" icon-color="#1a1c1c" />

    <view v-if="loading" class="agreement-state">
      <text class="agreement-state__text">加载中...</text>
    </view>

    <view v-else-if="errorMessage" class="agreement-state">
      <text class="agreement-state__text">{{ errorMessage }}</text>
      <view class="agreement-state__button" @click="fetchAgreement">
        <text>重新加载</text>
      </view>
    </view>

    <scroll-view v-else scroll-y class="agreement-scroll" :show-scrollbar="false">
      <view class="agreement-scroll__body">
        <rich-text v-if="content" class="agreement-content" :nodes="content"></rich-text>
        <view v-else class="agreement-state agreement-state--empty">
          <text class="agreement-state__text">暂无协议内容</text>
        </view>
      </view>
    </scroll-view>
  </view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getAgreementDetail } from '@/api/agreement'
import { getAgreementTitle, normalizeAgreementType } from '@/utils/agreement'

defineOptions({
  name: 'AgreementIndexPage'
})

const type = ref('member')
const title = ref('协议详情')
const content = ref('')
const loading = ref(true)
const errorMessage = ref('')

onLoad((options = {}) => {
  type.value = normalizeAgreementType(options.type)
  title.value = decodeTitle(options.title) || getAgreementTitle(type.value)
  fetchAgreement()
})

function decodeTitle(titleValue = '') {
  const value = String(titleValue || '').trim()
  if (!value) {
    return ''
  }

  try {
    return decodeURIComponent(value)
  } catch (e) {
    return value
  }
}

async function fetchAgreement() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await getAgreementDetail(type.value, { showError: false })
    if (!res || res.code !== 200 || !res.data) {
      errorMessage.value = '协议加载失败'
      return
    }

    const data = res.data || {}
    title.value = data.title || title.value || getAgreementTitle(type.value)
    content.value = data.content || ''
  } catch (e) {
    errorMessage.value = '协议加载失败'
  } finally {
    loading.value = false
  }
}
</script>

<style lang="scss">
page {
  background: #ffffff;
}
</style>

<style scoped lang="scss">
.agreement-page {
  min-height: 100%;
  background: #ffffff;
  display: flex;
  flex-direction: column;
}

.agreement-scroll {
  flex: 1;
  min-height: 0;

  &__body {
    padding: 32rpx;
  }
}

.agreement-content {
  display: block;
  font-size: 28rpx;
  line-height: 1.8;
  color: #3f4652;
}

.agreement-state {
  flex: 1;
  min-height: 360rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64rpx 32rpx;

  &--empty {
    min-height: 420rpx;
  }

  &__text {
    font-size: 28rpx;
    line-height: 40rpx;
    color: #8b8b92;
  }

  &__button {
    margin-top: 24rpx;
    padding: 16rpx 40rpx;
    border-radius: 999rpx;
    background: var(--brand-primary, #ea580c);

    text {
      font-size: 26rpx;
      line-height: 1;
      color: #ffffff;
      font-weight: 700;
    }
  }
}
</style>
