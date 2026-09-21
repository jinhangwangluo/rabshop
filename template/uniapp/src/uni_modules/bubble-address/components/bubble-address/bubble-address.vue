<template>
    <view class="bubble-address-picker">
        <!-- 地址选择器弹窗 -->
        <uni-popup ref="addressPopup" type="bottom" :safe-area="false">
            <view class="address-picker-popup">
                <view class="popup-header">
                    <view class="popup-title">选择地址</view>
                    <view class="popup-close" @click="closePicker">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 6L18 18M6 18L18 6" stroke="#666" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </view>
                </view>

                <view class="address-tabs">
                    <view class="tab-item" :class="{ active: currentTab === 0 }" @click="switchTab(0)">
                        {{ selectedProvince || '请选择省份' }}
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 1, disabled: !selectedProvince }"
                        @click="switchTab(1)">
                        {{ selectedCity || '请选择城市' }}
                    </view>
                    <view class="tab-item" :class="{ active: currentTab === 2, disabled: !selectedCity }"
                        @click="switchTab(2)">
                        {{ selectedArea || '请选择区县' }}
                    </view>
                </view>

                <scroll-view class="address-list" scroll-y>
                    <view v-if="!isDataLoaded" class="loading-container">
                        <text class="loading-text">正在加载城市数据...</text>
                    </view>
                    <view v-else-if="currentList.length === 0" class="empty-container">
                        <text class="empty-text">暂无数据</text>
                    </view>
                    <view v-else class="address-item" v-for="(item, index) in currentList" :key="item.code"
                        :class="{ selected: isSelected(item) }" @click="selectItem(item)">
                        <text class="item-name">{{ item.name }}</text>
                        <svg v-if="isSelected(item)" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 6L9 17L4 12" :stroke="themePrimaryColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                    </view>
                </scroll-view>

                <view class="popup-footer">
                    <button class="confirm-btn" @click="confirmSelection" :disabled="!canConfirm">
                        确定
                    </button>
                </view>
            </view>
        </uni-popup>
    </view>
</template>

<script>
export default {
    name: 'BubbleAddress',
    // 兼容 Vue3 的 emits 声明（在 Vue2 下无影响）
    emits: ['update:modelValue', 'change'],
    props: {
        // Vue3 v-model
        modelValue: {
            type: Object,
            default: () => ({})
        },
        // Vue2 v-model
        value: {
            type: Object,
            default: () => ({})
        },
        addressData: {
            type: Object,
            default: () => ({
                provinceList: [],
                cityList: [],
                areaList: []
            })
        }
    },
    data() {
        return {
            cityData: null,
            isDataLoaded: false,
            currentTab: 0,
            selectedProvince: '',
            selectedCity: '',
            selectedArea: '',
            selectedProvinceCode: '',
            selectedCityCode: '',
            selectedAreaCode: ''
        }
    },
    computed: {
        displayText() {
            const parts = []
            if (this.selectedProvince) parts.push(this.selectedProvince)
            if (this.selectedCity) parts.push(this.selectedCity)
            if (this.selectedArea) parts.push(this.selectedArea)
            return parts.join(' ')
        },
        currentList() {
            if (!this.isDataLoaded) return []
            switch (this.currentTab) {
                case 0:
                    return this.cityData.provinceList
                case 1:
                    if (!this.selectedProvinceCode) return []
                    return this.cityData.cityList.filter(city =>
                        city.province === this.selectedProvinceCode.substring(0, 2)
                    )
                case 2:
                    if (!this.selectedProvinceCode) return []
                    if (this.isMunicipality) {
                        return this.cityData.areaList.filter(area =>
                            area.province === this.selectedProvinceCode.substring(0, 2)
                        )
                    }
                    if (!this.selectedCityCode) return []
                    const rs = this.cityData.areaList.filter(area =>
                        area.province === this.selectedProvinceCode.substring(0, 2) &&
                        area.city === this.selectedCityCode.substring(2, 4)
                    )
                    return rs.length > 0 ? rs : []
                default:
                    return []
            }
        },
        isMunicipality() {
            if (!this.selectedProvinceCode || !this.isDataLoaded) return false
            const cityList = this.cityData.cityList.filter(city =>
                city.province === this.selectedProvinceCode.substring(0, 2)
            )
            return cityList.length === 0
        },
        canConfirm() {
            return this.selectedProvince && this.selectedCity && this.selectedArea
        },
        // 统一读取传入的值（兼容 value 与 modelValue）
        incomingValue() {
            return this.modelValue && Object.keys(this.modelValue).length ? this.modelValue : this.value
        }
    },
    watch: {
        addressData: {
            handler(newValue) {
                this.setCityData(newValue)
            },
            immediate: true,
            deep: true
        },
        // 初始化或外部变更时同步内部选中值
        incomingValue: {
            handler(newValue) {
                const val = newValue || {}
                this.selectedProvince = val.province || ''
                this.selectedCity = val.city || ''
                this.selectedArea = val.district || ''

                if (this.selectedProvince && this.isDataLoaded) {
                    const province = this.cityData.provinceList.find(p => p.name === this.selectedProvince)
                    if (province) {
                        this.selectedProvinceCode = province.code
                        if (this.isMunicipality) {
                            this.selectedCity = this.selectedProvince
                            this.selectedCityCode = province.code
                        }
                    }
                }

                if (this.selectedCity && this.selectedProvinceCode && !this.isMunicipality && this.isDataLoaded) {
                    const city = this.cityData.cityList.find(c =>
                        c.name === this.selectedCity && c.province === this.selectedProvinceCode.substring(0, 2)
                    )
                    if (city) this.selectedCityCode = city.code
                }

                if (this.selectedArea && this.selectedProvinceCode && this.isDataLoaded) {
                    const area = this.cityData.areaList.find(a =>
                        a.name === this.selectedArea &&
                        a.province === this.selectedProvinceCode.substring(0, 2) &&
                        (this.isMunicipality ?
                            a.city === this.selectedProvinceCode.substring(2, 4) :
                            a.city === this.selectedCityCode.substring(2, 4)
                        )
                    )
                    if (area) this.selectedAreaCode = area.code
                }
            },
            immediate: true,
            deep: true
        }
    },
    methods: {
        setCityData(data = {}) {
            const provinceList = Array.isArray(data.provinceList) ? data.provinceList : []
            const cityList = Array.isArray(data.cityList) ? data.cityList : []
            const areaList = Array.isArray(data.areaList) ? data.areaList : []

            this.cityData = {
                provinceList,
                cityList,
                areaList
            }
            this.isDataLoaded = provinceList.length > 0 || cityList.length > 0 || areaList.length > 0
            this.syncSelectionCodes()
        },
        syncSelectionCodes() {
            if (!this.isDataLoaded || !this.cityData) return

            if (this.selectedProvince) {
                const province = this.cityData.provinceList.find(p => p.name === this.selectedProvince)
                this.selectedProvinceCode = province ? province.code : ''
                if (province && this.isMunicipality) {
                    this.selectedCity = this.selectedProvince
                    this.selectedCityCode = province.code
                }
            }

            if (this.selectedCity && this.selectedProvinceCode && !this.isMunicipality) {
                const city = this.cityData.cityList.find(c =>
                    c.name === this.selectedCity && c.province === this.selectedProvinceCode.substring(0, 2)
                )
                this.selectedCityCode = city ? city.code : ''
            }

            if (this.selectedArea && this.selectedProvinceCode) {
                const area = this.cityData.areaList.find(a =>
                    a.name === this.selectedArea &&
                    a.province === this.selectedProvinceCode.substring(0, 2) &&
                    (this.isMunicipality ?
                        a.city === this.selectedProvinceCode.substring(2, 4) :
                        a.city === this.selectedCityCode.substring(2, 4)
                    )
                )
                this.selectedAreaCode = area ? area.code : ''
            }
        },
        showPicker() {
            this.$refs.addressPopup && this.$refs.addressPopup.open()
        },
        closePicker() {
            this.$refs.addressPopup && this.$refs.addressPopup.close()
        },
        switchTab(tab) {
            if (tab === 1 && !this.selectedProvince) return
            if (tab === 2 && !this.selectedCity) return
            this.currentTab = tab
        },
        isSelected(item) {
            switch (this.currentTab) {
                case 0:
                    return item.code === this.selectedProvinceCode
                case 1:
                    return item.code === this.selectedCityCode
                case 2:
                    return item.code === this.selectedAreaCode
                default:
                    return false
            }
        },
        selectItem(item) {
            switch (this.currentTab) {
                case 0:
                    this.selectedProvince = item.name
                    this.selectedProvinceCode = item.code
                    this.selectedCity = ''
                    this.selectedCityCode = ''
                    this.selectedArea = ''
                    this.selectedAreaCode = ''
                    if (this.isMunicipality) {
                        this.selectedCity = item.name
                        this.selectedCityCode = item.code
                        this.currentTab = 2
                    } else {
                        this.currentTab = 1
                    }
                    break
                case 1:
                    this.selectedCity = item.name
                    this.selectedCityCode = item.code
                    this.selectedArea = ''
                    this.selectedAreaCode = ''
                    {
                        const areaList = this.cityData.areaList.filter(area =>
                            area.province === this.selectedProvinceCode.substring(0, 2) &&
                            area.city === item.code.substring(2, 4)
                        )
                        if (areaList.length === 0) {
                            this.confirmSelection()
                        } else {
                            this.currentTab = 2
                        }
                    }
                    break
                case 2:
                    this.selectedArea = item.name
                    this.selectedAreaCode = item.code
                    this.confirmSelection()
                    break
            }
        },
        confirmSelection() {
            if (this.canConfirm) {
                const payload = {
                    province: this.selectedProvince,
                    city: this.isMunicipality ? this.selectedProvince : this.selectedCity,
                    district: this.selectedArea,
                    code: this.selectedAreaCode
                }
                // Vue3
                this.$emit('update:modelValue', payload)
                // Vue2
                this.$emit('input', payload)
                // 风格统一：对外抛出 change 事件
                this.$emit('change', { value: payload })
                this.closePicker()
            }
        }
    }
}
</script>

<style lang="scss" scoped>
.bubble-address-picker {
    width: 0;
    height: 0;
}

.address-picker-popup {
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    height: 80vh;
    display: flex;
    flex-direction: column;

    .popup-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 32rpx;
        border-bottom: 2rpx solid #f0f0f0;

        .popup-title {
            font-size: 32rpx;
            font-weight: 600;
            color: #333;
        }

        .popup-close {
            padding: 8rpx;
        }
    }

    .address-tabs {
        display: flex;
        padding: 0 32rpx;
        border-bottom: 2rpx solid #f0f0f0;

        .tab-item {
            flex: 1;
            text-align: center;
            padding: 24rpx 0;
            font-size: 28rpx;
            color: #666;
            position: relative;

            &.active {
                color: var(--brand-primary, #ea580c);
                font-weight: 500;

                &::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 60rpx;
                    height: 4rpx;
                    background: var(--brand-primary, #ea580c);
                    border-radius: 2rpx;
                }
            }

            &.disabled {
                color: #ccc;
            }
        }
    }

    .address-list {
        flex: 1;
        height: 400rpx;

        .loading-container,
        .empty-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200rpx;

            .loading-text,
            .empty-text {
                font-size: 28rpx;
                color: #999;
            }
        }

        .address-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24rpx 32rpx;
            border-bottom: 2rpx solid #f8f9fa;

            &.selected {
                background: var(--brand-primary-pale, #fff7ed);
            }

            .item-name {
                font-size: 28rpx;
                color: #333;
            }
        }
    }

    .popup-footer {
        padding: 32rpx;
        border-top: 2rpx solid #f0f0f0;

        .confirm-btn {
            width: 100%;
            height: 88rpx;
            background: var(--brand-primary, #ea580c);
            color: #fff;
            font-size: 32rpx;
            border-radius: 12rpx;
            border: none;
            font-weight: 500;

            &:disabled {
                background: #ccc;
            }
        }
    }
}
</style>
