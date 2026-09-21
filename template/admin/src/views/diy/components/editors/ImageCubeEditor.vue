<template>
  <div class="cube-editor">
    <!-- 画布区域 -->
    <div class="canvas-wrap">
      <div class="canvas-hint">在格子上按住拖拽以选定区块范围</div>
      <div
        ref="canvasRef"
        class="cube-canvas"
        @mousedown.prevent="onMouseDown"
        @mousemove="onMouseMove"
        @mouseup="onMouseUp"
        @mouseleave="onMouseLeave"
      >
        <!-- 16个背景格子 -->
        <div
          v-for="cell in 16"
          :key="cell"
          class="grid-cell"
          :class="{
            occupied: isCellOccupied(cellCol(cell), cellRow(cell)),
            previewing: isCellInPreview(cellCol(cell), cellRow(cell))
          }"
        />

        <!-- 已有区块覆盖层 -->
        <div
          v-for="(block, index) in localConfig.blocks"
          :key="block.id"
          class="block-overlay"
          :style="blockOverlayStyle(block)"
        >
          <span class="block-num">{{ index + 1 }}</span>
          <LegacyIcon name="el-icon-close" class=" block-remove" @click.stop="removeBlock(block.id)" />
        </div>

        <!-- 拖拽预选区 -->
        <div
          v-if="isDragging && dragCurrent"
          class="drag-preview"
          :style="dragPreviewStyle"
        />
      </div>
      <div class="canvas-actions">
        <el-button size="mini" type="danger" plain :disabled="localConfig.blocks.length === 0" @click="clearAll">
          清除全部
        </el-button>
      </div>
    </div>

    <!-- 区块配置列表 -->
    <div v-if="localConfig.blocks.length > 0" class="block-list">
      <div
        v-for="(block, index) in localConfig.blocks"
        :key="block.id"
        class="block-card"
      >
        <!-- 缩略图 + 透明上传覆盖层 -->
        <div class="card-thumb">
          <!-- 有图片：显示预览 + 悬浮删除 -->
          <template v-if="block.image">
            <img :src="block.image" alt="">
            <div class="thumb-delete" @click.stop="onImageInput(index, '')">
              <LegacyIcon name="el-icon-delete" />
            </div>
          </template>
          <!-- 无图片：显示上传组件 -->
          <template v-else>
            <div class="thumb-placeholder">
              <LegacyIcon name="el-icon-camera" />
            </div>
            <Upload
              :model-value="block.image"
              :limit="1"
              class="thumb-upload-overlay"
              @update:modelValue="val => onImageInput(index, val)"
            />
          </template>
        </div>

        <!-- 字段区 -->
        <div class="card-fields">
          <div class="card-field-row">
            <span class="field-label">图片</span>
            <span class="field-hint">{{ block.image ? '点击删除图标重新上传' : '点击上传图片' }}</span>
          </div>
          <div class="card-field-row">
            <span class="field-label">区块</span>
            <span class="field-range">第{{ block.rowStart }}-{{ block.rowEnd }}行 第{{ block.colStart }}-{{ block.colEnd }}列</span>
          </div>
          <div class="card-field-row card-field-row--link">
            <span class="field-label">链接</span>
            <DiyLinkSelectField
              class="field-selector"
              :model-value="block.link_url"
              :link-id="block.link_id"
              :link-name="block.link_name"
              :link-type="block.link_type"
              :platform-type="block.platform_type"
              placeholder="请选择或输入链接地址"
              dialog-title="选择魔方区块链接"
              @update:modelValue="val => { block.link_url = val }"
              @select="row => handleSelectLink(index, row)"
              @clear="() => clearSelectedLink(index)"
            />
          </div>
        </div>

        <!-- 删除按钮悬浮右上角 -->
        <LegacyIcon name="el-icon-circle-close" class=" card-remove" @click="removeBlock(block.id)" />
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, reactive, ref, watch } from 'vue'
import Upload from '@/components/Upload'
import DiyLinkSelectField from '../DiyLinkSelectField.vue'

defineOptions({
  name: 'ImageCubeEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const { proxy } = getCurrentInstance()
const localConfig = reactive({
  gap: 4,
  blocks: []
})
const isDragging = ref(false)
const dragStart = ref(null)
const dragCurrent = ref(null)
const fromProp = ref(false)
const canvasRef = ref(null)

// 拖拽预选区的坐标（规范化 start <= end）
const dragRect = computed(() => {
  if (!dragStart.value || !dragCurrent.value) return null
  return {
    colStart: Math.min(dragStart.value.col, dragCurrent.value.col),
    colEnd: Math.max(dragStart.value.col, dragCurrent.value.col),
    rowStart: Math.min(dragStart.value.row, dragCurrent.value.row),
    rowEnd: Math.max(dragStart.value.row, dragCurrent.value.row)
  }
})

const dragPreviewStyle = computed(() => {
  if (!dragRect.value) return {}
  return blockOverlayStyle(dragRect.value)
})

function normalizeBlock(block = {}, idx = 0) {
  return {
    id: block.id || idx,
    colStart: block.colStart || 1,
    rowStart: block.rowStart || 1,
    colEnd: block.colEnd || 1,
    rowEnd: block.rowEnd || 1,
    image: block.image || '',
    link_type: block.link_type || '',
    link_url: block.link_url || '',
    link_id: block.link_id || 0,
    link_name: block.link_name || '',
    platform_type: block.platform_type || '',
    label: block.label || '',
    sub_label: block.sub_label || ''
  }
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  fromProp.value = true
  Object.assign(localConfig, {
    gap: val.gap !== undefined ? val.gap : 4,
    blocks: Array.isArray(val.blocks) ? val.blocks.map((block, index) => normalizeBlock(block, index)) : []
  })
  nextTick(() => {
    fromProp.value = false
  })
}

watch(() => props.config, applyConfig, {
  immediate: true
})

watch(localConfig, () => {
  if (fromProp.value) return
  emit('change', JSON.parse(JSON.stringify(localConfig)))
}, {
  deep: true
})

// ── 辅助：cell序号(1-16) → col/row（1-based）
function cellCol(cell) {
  return ((cell - 1) % 4) + 1
}

function cellRow(cell) {
  return Math.ceil(cell / 4)
}

// ── 判断格子是否被已有区块占用
function isCellOccupied(col, row) {
  return localConfig.blocks.some(b =>
    col >= b.colStart && col <= b.colEnd &&
    row >= b.rowStart && row <= b.rowEnd
  )
}

// ── 判断格子是否在拖拽预选区内
function isCellInPreview(col, row) {
  if (!isDragging.value || !dragRect.value) return false
  const r = dragRect.value
  return col >= r.colStart && col <= r.colEnd && row >= r.rowStart && row <= r.rowEnd
}

// ── 区块/预选区覆盖层的绝对定位样式
function blockOverlayStyle(block) {
  const pct = 25 // 100% / 4
  return {
    left: `${(block.colStart - 1) * pct}%`,
    top: `${(block.rowStart - 1) * pct}%`,
    width: `${(block.colEnd - block.colStart + 1) * pct}%`,
    height: `${(block.rowEnd - block.rowStart + 1) * pct}%`
  }
}

// ── 从鼠标事件计算格子坐标
function getCellFromEvent(e) {
  const canvas = canvasRef.value
  if (!canvas) return null
  const rect = canvas.getBoundingClientRect()
  const cellW = rect.width / 4
  const cellH = rect.height / 4
  const col = Math.min(4, Math.max(1, Math.ceil((e.clientX - rect.left) / cellW)))
  const row = Math.min(4, Math.max(1, Math.ceil((e.clientY - rect.top) / cellH)))
  return { col, row }
}

// ── 检测新区块是否与已有区块重叠
function isOverlapping(newBlock) {
  return localConfig.blocks.some(b =>
    newBlock.colStart <= b.colEnd && newBlock.colEnd >= b.colStart &&
    newBlock.rowStart <= b.rowEnd && newBlock.rowEnd >= b.rowStart
  )
}

// ── 鼠标事件处理
function onMouseDown(e) {
  const cell = getCellFromEvent(e)
  if (!cell) return
  isDragging.value = true
  dragStart.value = cell
  dragCurrent.value = cell
}

function onMouseMove(e) {
  if (!isDragging.value) return
  dragCurrent.value = getCellFromEvent(e)
}

function onMouseUp(e) {
  if (!isDragging.value) return
  dragCurrent.value = getCellFromEvent(e)
  finishDrag()
}

function onMouseLeave() {
  if (isDragging.value) {
    finishDrag()
  }
}

function finishDrag() {
  const rect = dragRect.value
  if (rect) {
    if (!isOverlapping(rect)) {
      localConfig.blocks.push(normalizeBlock({
        id: Date.now(),
        colStart: rect.colStart,
        rowStart: rect.rowStart,
        colEnd: rect.colEnd,
        rowEnd: rect.rowEnd,
        image: '',
        link_type: '',
        link_url: ''
      }))
    } else {
      proxy.$message({ message: '选区与已有区块重叠，请重新选择', type: 'warning', duration: 2000 })
    }
  }
  isDragging.value = false
  dragStart.value = null
  dragCurrent.value = null
}

// ── 删除区块
function removeBlock(id) {
  const idx = localConfig.blocks.findIndex(b => b.id === id)
  if (idx !== -1) localConfig.blocks.splice(idx, 1)
}

function clearAll() {
  localConfig.blocks = []
}

// ── 图片上传回调
function onImageInput(index, val) {
  localConfig.blocks[index] = {
    ...localConfig.blocks[index],
    image: val
  }
}

function handleSelectLink(index, row) {
  const block = localConfig.blocks[index]
  if (!block) return
  localConfig.blocks[index] = {
    ...block,
    link_type: row.link_type || block.link_type,
    link_url: row.link_url || row.value || row.url || '',
    link_id: row.id || 0,
    link_name: row.link_name || row.name || row.title || '',
    platform_type: row.platform_type || ''
  }
}

function clearSelectedLink(index) {
  const block = localConfig.blocks[index]
  if (!block) return
  localConfig.blocks[index] = {
    ...block,
    link_id: 0,
    link_name: '',
    platform_type: ''
  }
}

function getConfig() {
  return localConfig
}

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.cube-editor {
  padding: 4px 0;
}

/* ── 画布 ── */
.canvas-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 16px;
}

.canvas-hint {
  font-size: 12px;
  color: #909399;
  margin-bottom: 8px;
}

.cube-canvas {
  width: 320px;
  height: 320px;
  position: relative;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: repeat(4, 1fr);
  gap: 2px;
  background: #d0d3d8;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  user-select: none;
  cursor: crosshair;
}

.grid-cell {
  background: #f5f7fa;
  transition: background 0.1s;

  &.occupied {
    background: var(--prev-color-primary-light-9);
  }

  &.previewing {
    background: var(--dashboard-accent-light);
  }
}

/* ── 区块覆盖层 ── */
.block-overlay {
  position: absolute;
  background: rgba(243, 65, 0, 0.22);
  border: 2px solid var(--prev-color-primary);
  border-radius: 3px;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  box-sizing: border-box;

  .block-num {
    font-size: 18px;
    font-weight: 700;
    color: var(--prev-color-primary);
    pointer-events: none;
  }

  .block-remove {
    position: absolute;
    top: 3px;
    right: 5px;
    font-size: 13px;
    color: #f56c6c;
    cursor: pointer;
    pointer-events: auto;

    &:hover {
      color: #e64242;
    }
  }
}

/* ── 拖拽预选区 ── */
.drag-preview {
  position: absolute;
  background: rgba(243, 65, 0, 0.12);
  border: 2px dashed var(--prev-color-primary);
  border-radius: 3px;
  pointer-events: none;
  z-index: 3;
  box-sizing: border-box;
}

.canvas-actions {
  width: 100%;
  margin-top: 8px;
  display: flex;
  justify-content: flex-end;
}

/* ── 区块配置列表 ── */
.block-list {
  margin-bottom: 12px;
}

.block-card {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 28px 10px 10px;
  margin-bottom: 8px;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.card-thumb {
  position: relative;
  width: 72px;
  height: 72px;
  flex-shrink: 0;
  border-radius: 4px;
  overflow: hidden;
  background: #f5f7fa;
  border: 1px dashed #dcdfe6;
  cursor: pointer;

  &:hover {
    border-color: var(--prev-color-primary);
  }

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .thumb-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c0c4cc;
    font-size: 24px;
  }

  .thumb-upload-overlay {
    position: absolute;
    inset: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;

    :deep(.el-upload) {
      width: 100%;
      height: 100%;
      display: block;
    }

    :deep(.el-upload-dragger),
    :deep(.el-upload--text) {
      width: 100%;
      height: 100%;
      display: block;
    }
  }

  .thumb-delete {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.45);
    color: #fff;
    font-size: 18px;
    opacity: 0;
    transition: opacity 0.2s;
    cursor: pointer;
  }

  &:hover .thumb-delete {
    opacity: 1;
  }
}

.card-fields {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 0;
}

.card-field-row {
  display: flex;
  align-items: center;
  gap: 6px;

  .field-label {
    font-size: 12px;
    color: #909399;
    width: 28px;
    flex-shrink: 0;
  }

  .field-hint {
    font-size: 11px;
    color: #c0c4cc;
  }

  .field-range {
    font-size: 11px;
    color: #909399;
  }

  .field-input {
    flex: 1;
    min-width: 0;
  }

  .field-link-icon {
    cursor: pointer;
    line-height: 32px;
    color: #909399;
  }
}

.card-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  font-size: 17px;
  color: #c0c4cc;
  cursor: pointer;
  transition: color 0.2s;
  z-index: 1;

  &:hover {
    color: #f56c6c;
  }
}

</style>
