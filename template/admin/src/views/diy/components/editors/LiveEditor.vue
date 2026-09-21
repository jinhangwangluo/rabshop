<template>
  <div class="live-editor">
    <el-form label-width="100px">
      <el-form-item label="标题">
        <el-input v-model="localConfig.title" placeholder="直播标题（如：热门直播）" />
      </el-form-item>
      <el-form-item label="展示样式">
        <el-radio-group v-model="localConfig.display_style">
          <el-radio label="small">小图</el-radio>
          <el-radio label="big">大图</el-radio>
          <el-radio label="scroll">横向滑动</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item v-if="localConfig.display_style === 'scroll'" label="竖屏模式">
        <el-switch v-model="localConfig.vertical" />
      </el-form-item>
      <el-form-item label="显示数量">
        <el-input-number v-model="localConfig.show_count" :min="1" :max="20" :step="1" controls-position="right" style="width: 120px" />
      </el-form-item>
      <el-form-item label="背景图">
        <Upload v-model="localConfig.background_image" :limit="1" :file-type="backgroundFileTypes" />
        <div class="field-tip">不上传时使用默认热门直播背景</div>
      </el-form-item>
      <el-form-item label="数据来源">
        <el-radio-group v-model="localConfig.data_source">
          <el-radio label="auto">自动获取</el-radio>
          <el-radio label="manual">手动指定</el-radio>
        </el-radio-group>
      </el-form-item>

      <template v-if="localConfig.data_source === 'auto'">
        <el-form-item label="显示状态">
          <el-radio-group v-model="localConfig.show_status">
            <el-radio label="all">全部</el-radio>
            <el-radio label="living">直播中</el-radio>
            <el-radio label="preview">预告</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="直播分类">
          <el-select
            v-model="localConfig.category_ids"
            multiple
            collapse-tags
            placeholder="不限分类（可多选）"
            style="width: 100%"
            clearable
          >
            <el-option
              v-for="item in categoryOptions"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
          <div class="field-tip">不选则显示所有分类</div>
        </el-form-item>
      </template>

      <el-form-item v-if="localConfig.data_source === 'manual'" label="选择直播间">
        <el-button type="primary" icon="el-icon-plus" @click="openSelector">添加直播间</el-button>
        <div v-if="localConfig.manual_ids && localConfig.manual_ids.length > 0" class="selected-list">
          <div v-for="(item, index) in localConfig.manual_ids" :key="item.id || index" class="selected-item">
            <span class="selected-title">{{ item.title || ('直播间 #' + item.id) }}</span>
            <el-button type="text" size="mini" @click="removeManual(index)">
              <LegacyIcon name="el-icon-close" />
            </el-button>
          </div>
        </div>
        <div v-else class="field-tip">暂未选择直播间</div>
      </el-form-item>

      <el-form-item label="显示更多">
        <el-switch v-model="localConfig.show_more" />
      </el-form-item>
      <el-form-item label="显示人数">
        <el-switch v-model="localConfig.show_count_info" />
      </el-form-item>
    </el-form>

    <el-dialog
      v-model="selectorVisible"
      title="选择直播间"
      width="640px"
      append-to-body
      @open="loadRoomList"
    >
      <div class="selector-search">
        <el-input
          v-model="roomSearchTitle"
          placeholder="搜索直播间名称"
          prefix-icon="el-icon-search"
          clearable
          style="width: 240px"
          @keyup.enter="loadRoomList"
          @clear="loadRoomList"
        />
        <el-button type="primary" @click="loadRoomList">搜索</el-button>
      </div>

      <el-table
        v-loading="roomLoading"
        :data="roomList"
        style="width: 100%; margin-top: 12px"
        :row-key="row => row.id"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="42" :reserve-selection="true" />
        <el-table-column label="封面" width="80">
          <template #default="{ row }">
            <img v-if="row.cover" :src="row.cover" style="width: 50px; height: 30px; object-fit: cover; border-radius: 3px;">
            <span v-else style="color: #ccc; font-size: 12px">无封面</span>
          </template>
        </el-table-column>
        <el-table-column label="直播间名称" prop="title" show-overflow-tooltip />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag v-if="getRoomStatus(row) === 1" type="danger" size="mini">直播中</el-tag>
            <el-tag v-else-if="getRoomStatus(row) === 0" type="warning" size="mini">即将开始</el-tag>
            <el-tag v-else type="info" size="mini">已结束</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="观看人数" prop="view_count" width="80" align="right" />
      </el-table>

      <div class="selector-pagination">
        <el-pagination
          small
          layout="total, prev, pager, next"
          :total="roomTotal"
          :current-page="roomPage"
          :page-size="10"
          @current-change="onPageChange"
        />
      </div>

      <template #footer>
        <span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmSelection">确认（已选 {{ tempSelected.length }} 个）</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import { getCategoryOptions, getRoomList } from '@/api/live'
import Upload from '@/components/Upload'

defineOptions({
  name: 'LiveEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const categoryOptions = ref([])
const localConfig = reactive({
  title: '直播',
  display_style: 'small',
  show_count: 4,
  data_source: 'auto',
  category_ids: [],
  manual_ids: [],
  show_status: 'all',
  show_more: true,
  show_count_info: true,
  vertical: false,
  background_image: ''
})
const selectorVisible = ref(false)
const roomList = ref([])
const roomTotal = ref(0)
const roomPage = ref(1)
const roomLoading = ref(false)
const roomSearchTitle = ref('')
const backgroundFileTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
const tempSelected = ref([])
const fromProp = ref(false)
const lastEmit = ref('')

async function fetchCategoryOptions() {
  try {
    const res = await getCategoryOptions()
    categoryOptions.value = Array.isArray(res) ? res : (res && res.data ? res.data : [])
  } catch (e) {
    categoryOptions.value = []
  }
}

function emitConfig() {
  const cfg = { ...localConfig }
  lastEmit.value = JSON.stringify(cfg)
  emit('change', cfg)
}

function removeManual(index) {
  const list = [...localConfig.manual_ids]
  list.splice(index, 1)
  localConfig.manual_ids = list
}

function openSelector() {
  tempSelected.value = [...(localConfig.manual_ids || [])]
  roomPage.value = 1
  roomSearchTitle.value = ''
  selectorVisible.value = true
}

async function loadRoomList() {
  roomLoading.value = true
  try {
    const res = await getRoomList({
      page: roomPage.value,
      limit: 10,
      title: roomSearchTitle.value || undefined
    })
    const data = res.data || {}
    roomList.value = data.list || data || []
    roomTotal.value = data.total || 0
  } finally {
    roomLoading.value = false
  }
}

function onPageChange(page) {
  roomPage.value = page
  loadRoomList()
}

function handleSelectionChange(rows) {
  tempSelected.value = rows.map(r => ({
    id: r.id,
    title: r.title,
    cover: r.cover || r.image || '',
    anchor_name: r.anchor_name || (r.anchor && r.anchor.name) || '',
    anchor_avatar: r.anchor_avatar || (r.anchor && r.anchor.avatar) || '',
    live_status: r.live_status !== undefined ? r.live_status : r.status,
    view_count: r.view_count || 0,
    like_count: r.like_count || 0
  }))
}

function getRoomStatus(row = {}) {
  const status = row.live_status !== undefined ? row.live_status : row.status
  return Number(status)
}

function confirmSelection() {
  localConfig.manual_ids = [...tempSelected.value]
  selectorVisible.value = false
}

function getConfig() {
  return localConfig
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  if (lastEmit.value && JSON.stringify(val) === lastEmit.value) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined && val.title !== '' ? val.title : '直播',
    display_style: val.display_style || 'small',
    show_count: val.show_count !== undefined ? val.show_count : 4,
    data_source: val.data_source || 'auto',
    category_ids: Array.isArray(val.category_ids) ? val.category_ids : [],
    manual_ids: Array.isArray(val.manual_ids) ? val.manual_ids : [],
    show_status: val.show_status || 'all',
    show_more: val.show_more !== false,
    show_count_info: val.show_count_info !== false,
    vertical: val.vertical === true,
    background_image: val.background_image || ''
  })
  nextTick(() => {
    fromProp.value = false
  })
}

watch(() => props.config, applyConfig, {
  immediate: true,
  deep: true
})

watch(localConfig, () => {
  if (fromProp.value) return
  emitConfig()
}, {
  deep: true
})

fetchCategoryOptions()

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.live-editor {
  padding: 4px 0;

  .field-tip {
    font-size: 11px;
    color: #909399;
    margin-top: 4px;
    line-height: 1.4;
  }

  .selected-list {
    margin-top: 8px;

    .selected-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 6px 10px;
      margin-bottom: 4px;
      background: #f5f7fa;
      border-radius: 4px;
      font-size: 12px;

      .selected-title {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #303133;
      }
    }
  }
}

.selector-search {
  display: flex;
  align-items: center;
  gap: 8px;
}

.selector-pagination {
  margin-top: 12px;
  text-align: right;
}
</style>
