<template>
  <el-dialog
    v-model="dialogVisible"
    title="选择位置"
    width="700px"
    top="8vh"
    :close-on-click-modal="false"
    @open="onDialogOpen"
    @closed="onDialogClosed"
  >
    <!-- 搜索框 -->
    <div class="map-search-box">
      <el-input
        v-model="searchKeyword"
        placeholder="搜索地点"
        :prefix-icon="Search"
        clearable
        @keyup.enter="handleSearch"
        @clear="clearSearch"
      />
    </div>

    <!-- 搜索建议列表 -->
    <div v-if="suggestions.length > 0" class="suggestions-list">
      <div
        v-for="(item, index) in suggestions"
        :key="index"
        class="suggestion-item"
        @click="selectSuggestion(item)"
      >
        <LegacyIcon name="el-icon-location-outline" />
        <span class="suggestion-title">{{ item.title }}</span>
        <span class="suggestion-addr">{{ item.address }}</span>
      </div>
    </div>

    <!-- 地图容器 -->
    <div id="tencent-map-container" class="map-container" />

    <!-- 位置信息 -->
    <div class="location-info-box">
      <div class="location-title">{{ currentAddress || '我的位置' }}</div>
      <div class="location-city">{{ currentCity }}</div>
      <div v-if="errorMsg" class="location-error">{{ errorMsg }}</div>
    </div>

    <template #footer>
      <div class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" :disabled="!currentLat" @click="handleConfirm">提 交</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup>
import { getPublicConfigApi } from '@/api/common'
import { Search } from '@element-plus/icons-vue'
import { computed, nextTick, ref } from 'vue'

defineOptions({
  name: 'TencentMapPicker'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  lat: {
    type: [Number, String],
    default: 0
  },
  lng: {
    type: [Number, String],
    default: 0
  }
})

const emit = defineEmits(['update:visible', 'confirm'])

let TMap = null // 腾讯地图全局引用

const mapKey = ref('')
const searchKeyword = ref('')
const suggestions = ref([])
const currentLat = ref(0)
const currentLng = ref(0)
const currentAddress = ref('')
const currentCity = ref('')
const errorMsg = ref('')
const mapLoaded = ref(false)
let mapInstance = null
let markerLayer = null

const dialogVisible = computed({
  get() {
    return props.visible
  },
  set(val) {
    emit('update:visible', val)
  }
})

// 弹窗打开
function onDialogOpen() {
  currentLat.value = parseFloat(props.lat) || 0
  currentLng.value = parseFloat(props.lng) || 0
  errorMsg.value = ''
  suggestions.value = []
  searchKeyword.value = ''
  loadMap()
}

// 弹窗关闭
function onDialogClosed() {
  mapInstance = null
  markerLayer = null
  mapLoaded.value = false
  TMap = null
}

// 加载腾讯地图 SDK
function loadMap() {
  if (!mapKey.value) {
    getPublicConfigApi().then(res => {
      mapKey.value = res.data.tencent_map_key || ''
      if (!mapKey.value) {
        errorMsg.value = '未配置腾讯地图 Key，请在平台配置中设置'
        return
      }
      loadMapScript()
    }).catch(() => {
      errorMsg.value = '获取地图配置失败'
    })
  } else {
    loadMapScript()
  }
}

// 动态注入腾讯地图脚本
function loadMapScript() {
  if (window.TMap) {
    TMap = window.TMap
    nextTick(() => initMap())
    return
  }
  const scriptId = 'tencent-map-sdk'
  if (document.getElementById(scriptId)) {
    // 脚本已注入，等待加载完成
    const check = setInterval(() => {
      if (window.TMap) {
        clearInterval(check)
        TMap = window.TMap
        nextTick(() => initMap())
      }
    }, 100)
    return
  }
  const script = document.createElement('script')
  script.id = scriptId
  script.src = `https://map.qq.com/api/gljs?v=1.exp&key=${mapKey.value}&libraries=service`
  script.onload = () => {
    TMap = window.TMap
    nextTick(() => initMap())
  }
  script.onerror = () => {
    errorMsg.value = '腾讯地图加载失败，请检查 Key 是否正确'
  }
  document.head.appendChild(script)
}

// 初始化地图
function initMap() {
  const container = document.getElementById('tencent-map-container')
  if (!container || !TMap) return

  const center = (currentLat.value && currentLng.value)
    ? new TMap.LatLng(currentLat.value, currentLng.value)
    : new TMap.LatLng(39.984120, 116.307484)

  mapInstance = new TMap.Map(container, {
    zoom: 15,
    center
  })

  // 初始化 Marker
  markerLayer = new TMap.MultiMarker({
    map: mapInstance,
    styles: {
      marker: new TMap.MarkerStyle({
        width: 25,
        height: 35,
        anchor: { x: 16, y: 32 }
      })
    },
    geometries: (currentLat.value && currentLng.value) ? [{
      id: 'location',
      styleId: 'marker',
      position: center
    }] : []
  })

  // 点击地图选点
  mapInstance.on('click', (evt) => {
    const latLng = evt.latLng
    updateMarker(latLng.getLat(), latLng.getLng())
  })

  mapLoaded.value = true

  // 如果已有坐标，反查地址
  if (currentLat.value && currentLng.value) {
    reverseGeocode(currentLat.value, currentLng.value)
  } else {
    getCurrentLocation()
  }
}

// 更新标记点
function updateMarker(lat, lng) {
  currentLat.value = lat
  currentLng.value = lng
  const position = new TMap.LatLng(lat, lng)
  if (markerLayer) {
    markerLayer.setGeometries([{
      id: 'location',
      styleId: 'marker',
      position
    }])
  }
  mapInstance.setCenter(position)
  reverseGeocode(lat, lng)
}

// 反向地理编码
function reverseGeocode(lat, lng) {
  errorMsg.value = ''
  const geocoder = new TMap.service.Geocoder()
  geocoder.getAddress({ location: new TMap.LatLng(lat, lng) })
    .then(result => {
      const addr = result.result
      currentAddress.value = addr.address || '我的位置'
      currentCity.value = addr.address_component
        ? (addr.address_component.city || addr.address_component.province || '')
        : ''
    })
    .catch(() => {
      errorMsg.value = '获取消息列表失败'
    })
}

// 获取当前位置
function getCurrentLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        updateMarker(
          parseFloat(pos.coords.latitude.toFixed(6)),
          parseFloat(pos.coords.longitude.toFixed(6))
        )
      },
      () => {
        currentAddress.value = '我的位置'
        currentCity.value = ''
      }
    )
  }
}

// 搜索地点
function handleSearch() {
  if (!searchKeyword.value.trim()) return
  const search = new TMap.service.Search({ pageSize: 10 })
  search.searchRectangle({
    keyword: searchKeyword.value,
    bounds: mapInstance.getBounds()
  }).then(result => {
    suggestions.value = result.data || []
    if (suggestions.value.length === 0) {
      errorMsg.value = '未找到相关地点'
    }
  }).catch(() => {
    errorMsg.value = '搜索失败，请重试'
  })
}

// 清空搜索
function clearSearch() {
  suggestions.value = []
  errorMsg.value = ''
}

// 选中搜索建议
function selectSuggestion(item) {
  suggestions.value = []
  searchKeyword.value = item.title
  const lat = item.location.lat
  const lng = item.location.lng
  updateMarker(lat, lng)
  currentAddress.value = item.title
  currentCity.value = item.city || ''
}

// 确认选点
function handleConfirm() {
  emit('confirm', {
    lat: currentLat.value,
    lng: currentLng.value,
    address: currentAddress.value
  })
  dialogVisible.value = false
}
</script>

<style lang="scss" scoped>
.map-search-box {
  margin-bottom: 10px;
}

.suggestions-list {
  position: absolute;
  z-index: 9999;
  background: #fff;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  width: calc(100% - 48px);
  max-height: 200px;
  overflow-y: auto;

  .suggestion-item {
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;

    &:hover {
      background: #f5f7fa;
    }

    .el-icon-location-outline {
      color: #409eff;
      flex-shrink: 0;
    }

    .suggestion-title {
      color: #303133;
      font-weight: 500;
    }

    .suggestion-addr {
      color: #909399;
      font-size: 12px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }
}

.map-container {
  width: 100%;
  height: 360px;
  border-radius: 4px;
  overflow: hidden;
}

.location-info-box {
  padding: 10px 0;
  border-bottom: 1px solid #ebeef5;

  .location-title {
    font-size: 14px;
    font-weight: 500;
    color: #303133;
  }

  .location-city {
    font-size: 12px;
    color: #606266;
    margin-top: 2px;
  }

  .location-error {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
    text-align: center;
  }
}

.dialog-footer {
  text-align: right;
}
</style>
