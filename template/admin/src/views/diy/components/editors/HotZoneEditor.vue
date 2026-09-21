<template>
  <div class="hot-zone-editor">
    <el-form :model="localConfig" label-width="100px">
      <el-form-item label="组件名称">
        <el-input v-model="localConfig.title" placeholder="热区图片" />
      </el-form-item>
      <el-form-item label="背景图片">
        <Upload v-model="localConfig.image" :limit="1" />
      </el-form-item>
      <el-form-item label="图片尺寸">
        <div class="size-config">
          <el-switch
            v-model="localConfig.auto_height"
            active-text="auto"
            inactive-text="自定义"
          />
          <template v-if="!localConfig.auto_height">
            <span class="size-label">宽度</span>
            <el-input-number
              v-model="localConfig.image_width"
              :min="100"
              :max="2000"
              :step="10"
              style="width: 100px"
            />
            <span class="size-unit">px</span>
          </template>
          <span v-else class="size-hint">（自动匹配图片比例）</span>
        </div>
      </el-form-item>
    </el-form>

    <!-- 打开热区绘制弹窗的按钮 -->
    <div class="draw-btn-wrap">
      <el-button
        type="primary"
        icon="el-icon-edit"
        :disabled="!localConfig.image"
        @click="openDrawDialog"
      >编辑热区</el-button>
      <span v-if="!localConfig.image" class="draw-tip">请先上传背景图片</span>
      <span v-else class="draw-tip">已绘制 {{ localConfig.hot_areas.length }} 个热区</span>
    </div>

    <!-- 热区链接列表 -->
    <div v-if="localConfig.hot_areas.length > 0" class="area-list-section">
      <div class="list-title">热区链接设置（共 {{ localConfig.hot_areas.length }} 个）</div>
      <div
        v-for="(area, index) in localConfig.hot_areas"
        :key="area.id || index"
        class="area-item"
      >
        <div class="area-item-header">
          <span class="area-num">{{ index + 1 }}</span>
          <span class="area-name">热区 {{ index + 1 }}</span>
          <LegacyIcon name="el-icon-circle-close" class=" area-del" @click.stop="removeHotArea(index)" />
        </div>
        <div class="area-item-body">
          <div class="field-row field-row--link">
            <span class="field-label">链接</span>
            <DiyLinkSelectField
              class="field-selector"
              :model-value="area.link_url"
              :link-id="area.link_id"
              :link-name="area.link_name"
              :link-type="area.link_type"
              :platform-type="area.platform_type"
              placeholder="请选择或输入链接地址"
              dialog-title="选择热区链接"
              @update:modelValue="val => { area.link_url = val }"
              @select="row => handleSelectLink(index, row)"
              @clear="() => clearSelectedLink(index)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- 无热区时的提示 -->
    <div v-if="localConfig.hot_areas.length === 0" class="empty-tip">
      <LegacyIcon name="el-icon-info" /> 上传图片后点击"编辑热区"按钮，在弹窗中拖拽绘制热区
    </div>

    <!-- ── 热区绘制弹窗 ── -->
    <el-dialog
      v-model="drawDialogVisible"
      title="编辑热区"
      width="860px"

      :close-on-click-modal="false"
      append-to-body
      @closed="onDialogClosed"
    >
      <div class="dialog-body">
        <div class="dialog-tip">
          <LegacyIcon name="el-icon-info" />
          在图片上拖拽鼠标绘制热区，点击热区可选中，拖拽四角调整大小，点击 × 删除
        </div>
        <div class="dialog-canvas-wrap">
          <!-- 无图片占位 -->
          <div v-if="!localConfig.image" class="canvas-placeholder">
            <LegacyIcon name="el-icon-picture-outline" />
            <span>请先上传背景图片</span>
          </div>

          <!-- 有图片时的画布 -->
          <div
            v-else
            ref="dialogCanvasRef"
            class="dialog-canvas"
            :style="dialogCanvasStyle"
            :class="{ 'is-drawing': isDrawing }"
            @mousedown="onMouseDown"
            @mousemove="onMouseMove"
            @mouseup="onMouseUp"
            @mouseleave="onMouseLeave"
          >
            <!-- auto 模式：img 撑开真实高度 -->
            <img
              v-if="localConfig.auto_height"
              :src="localConfig.image"
              class="canvas-bg-img"
              draggable="false"
            >
            <!-- 固定尺寸模式：padding-top 撑高 -->
            <div
              v-else
              class="canvas-bg-fixed"
              :style="fixedBgStyle"
            />

            <!-- 已有的热区 -->
            <div
              v-for="(area, index) in localConfig.hot_areas"
              :key="area.id || index"
              class="hot-area-overlay"
              :style="getAreaStyle(area)"
              :class="{ 'is-selected': selectedAreaIndex === index }"
              @click.stop="selectArea(index)"
            >
              <div class="area-label">{{ index + 1 }}</div>
              <div class="resize-handle nw" @mousedown.stop="startResize($event, index, 'nw')" />
              <div class="resize-handle ne" @mousedown.stop="startResize($event, index, 'ne')" />
              <div class="resize-handle sw" @mousedown.stop="startResize($event, index, 'sw')" />
              <div class="resize-handle se" @mousedown.stop="startResize($event, index, 'se')" />
              <div class="area-remove" @mousedown.stop @click.stop="removeHotArea(index)">
                <LegacyIcon name="el-icon-close" />
              </div>
            </div>

            <!-- 绘制中的热区 -->
            <div
              v-if="isDrawing && drawRect"
              class="hot-area-overlay drawing"
              :style="getAreaStyle(drawRect)"
            />
          </div>
        </div>
        <div class="dialog-count">已绘制 <strong>{{ localConfig.hot_areas.length }}</strong> 个热区</div>
      </div>
      <template #footer>
        <span>
          <el-button @click="drawDialogVisible = false">关闭</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'HotZoneEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const fromProp = ref(false)
// 弹窗中预览区最大宽度（对话框内可以更大）
const previewMaxWidth = ref(700)
// 图片实际尺寸（auto 模式比例计算）
const naturalSize = reactive({
  width: 0,
  height: 0
})
// 绘制状态
const isDrawing = ref(false)
const drawStart = ref(null)
const drawRect = ref(null)
const selectedAreaIndex = ref(-1)
// 弹窗
const drawDialogVisible = ref(false)
const dialogCanvasRef = ref(null)
const localConfig = reactive({
  title: '热区图片',
  image: '',
  auto_height: true,
  image_width: 375,
  image_height: 200,
  hot_areas: []
})

// 画布容器宽度统一为 previewMaxWidth，高度由内部内容撑开
const dialogCanvasStyle = computed(() => ({
  width: previewMaxWidth.value + 'px'
}))

// 固定尺寸模式：用 padding-top 撑开容器，背景铺满
const fixedBgStyle = computed(() => {
  const w = localConfig.image_width || 375
  const h = localConfig.image_height || 200
  const ratio = (h / w * 100).toFixed(4)
  return {
    width: '100%',
    paddingTop: ratio + '%',
    backgroundImage: `url(${localConfig.image})`,
    backgroundSize: '100% 100%',
    backgroundRepeat: 'no-repeat'
  }
})

function normalizeHotAreas(areas = []) {
  return Array.isArray(areas) ? areas.map((a, idx) => ({
    id: a.id !== undefined ? a.id : idx,
    name: a.name || `热区${idx + 1}`,
    x: a.x !== undefined ? a.x : 0,
    y: a.y !== undefined ? a.y : 0,
    width: a.width !== undefined ? a.width : 50,
    height: a.height !== undefined ? a.height : 50,
    link_type: a.link_type || 'page',
    link_url: a.link_url || '',
    link_id: a.link_id || '',
    link_name: a.link_name || '',
    platform_type: a.platform_type || ''
  })) : []
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined ? val.title : '热区图片',
    image: val.image || '',
    auto_height: val.auto_height !== undefined ? val.auto_height : true,
    image_width: val.image_width || 375,
    image_height: val.image_height || 200,
    hot_areas: normalizeHotAreas(val.hot_areas)
  })
  if (localConfig.image && localConfig.auto_height) {
    loadImageSize(localConfig.image)
  }
  nextTick(() => {
    fromProp.value = false
  })
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(() => localConfig.image, val => {
  if (!val) {
    naturalSize.width = 0
    naturalSize.height = 0
    return
  }
  loadImageSize(val)
})

watch(localConfig, () => {
  if (fromProp.value) return
  emitConfig()
}, {
  deep: true
})

// ── 加载图片实际尺寸 ──
function loadImageSize(src) {
  const img = new Image()
  img.onload = () => {
    naturalSize.width = img.naturalWidth
    naturalSize.height = img.naturalHeight
  }
  img.onerror = () => {
    naturalSize.width = 0
    naturalSize.height = 0
  }
  img.src = src
}

// ── 打开弹窗 ──
function openDrawDialog() {
  selectedAreaIndex.value = -1
  isDrawing.value = false
  drawRect.value = null
  drawStart.value = null
  drawDialogVisible.value = true
}

function onDialogClosed() {
  isDrawing.value = false
  drawRect.value = null
  drawStart.value = null
  selectedAreaIndex.value = -1
}

// ── 热区选中 ──
function selectArea(index) {
  selectedAreaIndex.value = index
}

// ── 鼠标拖拽绘制热区 ──
function onMouseDown(e) {
  if (!localConfig.image) return
  if (e.target.closest('.hot-area-overlay')) return

  const canvas = dialogCanvasRef.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  const x = (e.clientX - rect.left) / rect.width * 100
  const y = (e.clientY - rect.top) / rect.height * 100

  isDrawing.value = true
  drawStart.value = { x, y }
  drawRect.value = { x, y, width: 0, height: 0 }
  selectedAreaIndex.value = -1
}

function onMouseMove(e) {
  if (!isDrawing.value || !drawStart.value) return

  const canvas = dialogCanvasRef.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  const curX = (e.clientX - rect.left) / rect.width * 100
  const curY = (e.clientY - rect.top) / rect.height * 100

  const x1 = Math.min(drawStart.value.x, curX)
  const y1 = Math.min(drawStart.value.y, curY)
  const x2 = Math.max(drawStart.value.x, curX)
  const y2 = Math.max(drawStart.value.y, curY)

  drawRect.value = { x: x1, y: y1, width: x2 - x1, height: y2 - y1 }
}

function onMouseUp(e) {
  if (!isDrawing.value || !drawRect.value) return
  isDrawing.value = false

  if (drawRect.value.width < 1 || drawRect.value.height < 1) {
    drawRect.value = null
    drawStart.value = null
    return
  }

  const newArea = {
    id: Date.now(),
    name: `热区${localConfig.hot_areas.length + 1}`,
    x: parseFloat(drawRect.value.x.toFixed(2)),
    y: parseFloat(drawRect.value.y.toFixed(2)),
    width: parseFloat(drawRect.value.width.toFixed(2)),
    height: parseFloat(drawRect.value.height.toFixed(2)),
    link_type: 'page',
    link_url: '',
    link_id: '',
    link_name: '',
    platform_type: ''
  }
  localConfig.hot_areas.push(newArea)
  selectedAreaIndex.value = localConfig.hot_areas.length - 1

  drawRect.value = null
  drawStart.value = null
}

function onMouseLeave(e) {
  if (isDrawing.value) onMouseUp(e)
}

// ── 热区样式 ──
function getAreaStyle(area) {
  return {
    left: area.x + '%',
    top: area.y + '%',
    width: area.width + '%',
    height: area.height + '%'
  }
}

// ── 四角拖拽调整热区大小 ──
function startResize(e, index, corner) {
  if (!localConfig.image) return
  e.preventDefault()

  const canvas = dialogCanvasRef.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  const area = localConfig.hot_areas[index]

  const orig = {
    x: area.x, y: area.y,
    w: area.width, h: area.height
  }

  const onMove = (me) => {
    const px = (me.clientX - rect.left) / rect.width * 100
    const py = (me.clientY - rect.top) / rect.height * 100
    let { x, y, w, h } = orig

    if (corner === 'se') {
      w = Math.max(1, px - x)
      h = Math.max(1, py - y)
    } else if (corner === 'nw') {
      const nx = Math.min(px, x + w - 1)
      const ny = Math.min(py, y + h - 1)
      w = x + w - nx
      h = y + h - ny
      x = nx; y = ny
    } else if (corner === 'ne') {
      w = Math.max(1, px - x)
      const ny = Math.min(py, y + h - 1)
      h = y + h - ny
      y = ny
    } else if (corner === 'sw') {
      const nx = Math.min(px, x + w - 1)
      w = x + w - nx
      h = Math.max(1, py - y)
      x = nx
    }

    area.x = parseFloat(x.toFixed(2))
    area.y = parseFloat(y.toFixed(2))
    area.width = parseFloat(w.toFixed(2))
    area.height = parseFloat(h.toFixed(2))
    localConfig.hot_areas[index] = { ...area }
  }

  const onUp = () => {
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}

// ── 删除热区 ──
function removeHotArea(index) {
  localConfig.hot_areas.splice(index, 1)
  if (selectedAreaIndex.value === index) {
    selectedAreaIndex.value = -1
  } else if (selectedAreaIndex.value > index) {
    selectedAreaIndex.value--
  }
}

// ── 链接回调 ──
function handleSelectLink(index, row) {
  const area = localConfig.hot_areas[index]
  if (!area) return
  area.link_url = row.value || row.url || ''
  area.link_id = row.id || ''
  area.link_name = row.name || row.title || ''
  area.link_type = row.link_type || 'page'
  area.platform_type = row.platform_type || ''
  localConfig.hot_areas[index] = { ...area }
}

function clearSelectedLink(index) {
  const area = localConfig.hot_areas[index]
  if (!area) return
  area.link_url = ''
  area.link_id = ''
  area.link_name = ''
  area.link_type = 'page'
  area.platform_type = ''
  localConfig.hot_areas[index] = { ...area }
}

// ── 派发配置 ──
function emitConfig() {
  emit('change', {
    title: localConfig.title,
    image: localConfig.image,
    auto_height: localConfig.auto_height,
    image_width: localConfig.image_width,
    image_height: localConfig.image_height,
    hot_areas: localConfig.hot_areas.map(a => ({
      name: a.name,
      x: a.x,
      y: a.y,
      width: a.width,
      height: a.height,
      link_type: a.link_type,
      link_url: a.link_url,
      link_id: a.link_id,
      link_name: a.link_name,
      platform_type: a.platform_type
    }))
  })
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.hot-zone-editor {
  padding: 4px 0;

  /* ── 图片尺寸配置 ── */
  .size-config {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;

    .size-label {
      font-size: 12px;
      color: #606266;
      margin-left: 4px;
    }

    .size-unit {
      font-size: 12px;
      color: #909399;
    }

    .size-hint {
      font-size: 12px;
      color: var(--prev-color-primary);
    }
  }

  /* ── 打开弹窗按钮 ── */
  .draw-btn-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 12px 0;
    padding: 0 0 0 4px;

    .draw-tip {
      font-size: 12px;
      color: #909399;
    }
  }

  /* ── 热区列表 ── */
  .area-list-section {
    margin-top: 4px;
  }

  .list-title {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 8px;
  }

  .area-item {
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    margin-bottom: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s;

    &:hover {
      border-color: var(--dashboard-accent-light);
    }

    .area-item-header {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: #f5f7fa;
      border-bottom: 1px solid #ebeef5;

      .area-num {
        width: 20px;
        height: 20px;
        background: var(--prev-color-primary);
        color: #fff;
        border-radius: 50%;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }

      .area-name {
        flex: 1;
        font-size: 12px;
        color: #606266;
      }

      .area-del {
        font-size: 16px;
        color: #c0c4cc;
        transition: color 0.2s;
        cursor: pointer;

        &:hover {
          color: #f56c6c;
        }
      }
    }

    .area-item-body {
      padding: 10px 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;

      .field-row {
        display: flex;
        align-items: center;
        gap: 8px;

        .field-label {
          font-size: 12px;
          color: #909399;
          width: 28px;
          flex-shrink: 0;
        }

        .field-selector {
          flex: 1;
          min-width: 0;
        }
      }

      .field-row--link {
        align-items: flex-start;
      }
    }
  }

  /* ── 提示 ── */
  .empty-tip {
    margin-top: 12px;
    font-size: 12px;
    color: #909399;
    text-align: center;
    padding: 20px;
    background: #f5f7fa;
    border-radius: 6px;
    border: 1px dashed #dcdfe6;
  }
}

/* ── 弹窗内容 ── */
.dialog-body {
  padding: 0;

  .dialog-tip {
    font-size: 12px;
    color: #e6a23c;
    margin-bottom: 12px;
    line-height: 1.5;
  }

  .dialog-canvas-wrap {
    border: 2px dashed #dcdfe6;
    border-radius: 8px;
    overflow: hidden;
    background: #f5f7fa;
    width: 100%;
  }

  /* 无图片占位 */
  .canvas-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 260px;
    color: #c0c4cc;
    font-size: 14px;
    gap: 8px;

    i {
      font-size: 40px;
    }
  }

  .dialog-canvas {
    position: relative;
    cursor: crosshair;
    user-select: none;
    margin: 0 auto;
    display: block;
    border: 1px solid #dcdfe6;

    &.is-drawing {
      cursor: crosshair;
    }

    /* auto 模式：img 撑开高度 */
    .canvas-bg-img {
      display: block;
      width: 100%;
      height: auto;
      pointer-events: none;
    }

    /* 固定尺寸模式：padding-top 撑高，背景图填充 */
    .canvas-bg-fixed {
      position: relative;
      box-sizing: border-box;
    }
  }

  .dialog-count {
    margin-top: 10px;
    font-size: 12px;
    color: #606266;

    strong {
      color: var(--prev-color-primary);
    }
  }
}

/* ── 热区覆盖层（弹窗内） ── */
.hot-area-overlay {
  position: absolute;
  border: 1.5px dashed rgba(243, 65, 0, 0.85);
  background: rgba(243, 65, 0, 0.12);
  cursor: pointer;
  box-sizing: border-box;

  &:hover,
  &.is-selected {
    border-color: var(--prev-color-primary);
    background: rgba(243, 65, 0, 0.18);
  }

  &.drawing {
    border-color: var(--prev-color-primary);
    background: rgba(243, 65, 0, 0.22);
    pointer-events: none;
  }

  .area-label {
    position: absolute;
    top: 2px;
    left: 4px;
    font-size: 11px;
    color: #fff;
    background: rgba(243, 65, 0, 0.85);
    border-radius: 3px;
    padding: 0 4px;
    line-height: 16px;
    pointer-events: none;
  }

  /* 四角拖拽手柄 */
  .resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: var(--prev-color-primary);
    border-radius: 50%;
    z-index: 2;
    opacity: 0;
    transition: opacity 0.15s;

    &.nw { top: -5px; left: -5px; cursor: nw-resize; }
    &.ne { top: -5px; right: -5px; cursor: ne-resize; }
    &.sw { bottom: -5px; left: -5px; cursor: sw-resize; }
    &.se { bottom: -5px; right: -5px; cursor: se-resize; }
  }

  /* 删除按钮 */
  .area-remove {
    position: absolute;
    top: -12px;
    right: -12px;
    width: 22px;
    height: 22px;
    background: #f56c6c;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s;
    z-index: 3;

    i {
      font-size: 12px;
      color: #fff;
    }

    &:hover {
      background: #f78989;
    }
  }

  &:hover,
  &.is-selected {
    .resize-handle {
      opacity: 1;
    }
    .area-remove {
      opacity: 1;
    }
  }
}
</style>
