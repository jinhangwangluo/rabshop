<template>
  <div class="coupon-editor">
    <el-form label-width="100px">
      <el-form-item label="标题">
        <el-input v-model="localConfig.title" placeholder="优惠券" />
      </el-form-item>
      <el-form-item label="展示样式">
        <el-radio-group v-model="localConfig.display_style">
          <el-radio label="scroll">横向滑动</el-radio>
          <el-radio label="grid">网格</el-radio>
          <el-radio label="large_image">大图样式</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item v-if="localConfig.display_style === 'grid'" label="每行数量">
        <el-radio-group v-model="localConfig.columns">
          <el-radio :label="2">2个</el-radio>
          <el-radio :label="3">3个</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="背景图">
        <Upload v-model="localConfig.background_image" :limit="1" :file-type="backgroundFileTypes" />
        <div class="field-tip">不上传时使用默认优惠券背景</div>
      </el-form-item>
      <el-form-item label="优惠券类型">
        <el-radio-group v-model="localConfig.scope_type">
          <el-radio :label="0">全部</el-radio>
          <el-radio :label="10">平台通用</el-radio>
          <el-radio :label="20">店铺券</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="数据来源">
        <el-radio-group v-model="localConfig.data_source">
          <el-radio label="auto">自动可领取</el-radio>
          <el-radio label="manual">手动指定</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item v-if="localConfig.data_source === 'auto'" label="显示数量">
        <el-input-number
          v-model="localConfig.show_count"
          :min="1"
          :max="20"
          :step="1"
          controls-position="right"
          style="width: 120px"
        />
      </el-form-item>
      <el-form-item v-if="localConfig.data_source === 'manual'" label="选择优惠券">
        <el-button type="primary" @click="openSelector">+ 添加优惠券</el-button>
        <div v-if="localConfig.manual_ids && localConfig.manual_ids.length > 0" class="selected-list">
          <div v-for="(item, index) in localConfig.manual_ids" :key="item.id || index" class="selected-item">
            <span class="selected-name">{{ item.name || ('优惠券 #' + item.id) }}</span>
            <span class="selected-price">¥{{ item.price || 0 }}</span>
            <button
              type="button"
              class="selected-delete"
              title="删除优惠券"
              aria-label="删除优惠券"
              @click="removeManual(index)"
            >
              <LegacyIcon name="el-icon-delete" />
            </button>
          </div>
        </div>
      </el-form-item>
      <el-form-item label="显示领取按钮">
        <el-switch v-model="localConfig.show_receive_btn" />
      </el-form-item>
      <el-form-item label="背景颜色">
        <el-color-picker v-model="localConfig.bg_color" show-alpha />
        <el-input v-model="localConfig.bg_color" style="width: 150px; margin-left: 10px" />
      </el-form-item>
    </el-form>

    <el-dialog
      v-model="selectorVisible"
      title="选择优惠券"
      width="700px"
      append-to-body
      @open="loadCouponList"
    >
      <div class="selector-search">
        <el-input
          v-model="searchKeyword"
          placeholder="搜索优惠券名称"
          style="width: 180px"
          clearable
          @change="loadCouponList"
        />
        <el-select
          v-model="searchScopeType"
          style="width: 120px; margin-left: 8px"
          @change="loadCouponList"
        >
          <el-option :value="null" label="全部类型" />
          <el-option :value="10" label="平台通用" />
          <el-option :value="20" label="店铺券" />
        </el-select>
        <el-button type="primary" style="margin-left: 8px" @click="loadCouponList">搜索</el-button>
      </div>
      <el-table
        ref="couponTable"
        v-loading="couponLoading"
        :data="couponList"
        style="margin-top: 12px"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="45" />
        <el-table-column label="名称" prop="name" show-overflow-tooltip />
        <el-table-column label="面额" width="80">
          <template #default="{ row }">¥{{ row.price || 0 }}</template>
        </el-table-column>
        <el-table-column label="门槛" width="100">
          <template #default="{ row }">
            {{ row.min_price > 0 ? '满' + row.min_price + '可用' : '无门槛' }}
          </template>
        </el-table-column>
        <el-table-column label="类型" width="90">
          <template #default="{ row }">
            <el-tag size="mini" :type="row.scope_type === 10 ? '' : 'success'">
              {{ row.scope_type === 10 ? '平台通用' : row.scope_type === 20 ? '店铺券' : '通用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效期" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.expire_day > 0 ? '领取后' + row.expire_day + '天' : (row.use_end_time || '长期') }}
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        small
        layout="prev, pager, next"
        :total="couponTotal"
        :page-size="10"
        :current-page="couponPage"
        style="margin-top: 10px; text-align: right"
        @current-change="onPageChange"
      />
      <template #footer>
        <span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" @click="confirmSelection">
            确认（已选 {{ tempSelected.length }} 个）
          </el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { nextTick, reactive, ref, watch } from 'vue'
import { getCouponList } from '@/api/coupon'
import Upload from '@/components/Upload'

defineOptions({
  name: 'CouponEditor'
})

const props = defineProps({
  config: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['change'])
const localConfig = reactive({
  title: '优惠券',
  display_style: 'scroll',
  columns: 2,
  background_image: '',
  show_count: 4,
  scope_type: 0,
  data_source: 'auto',
  manual_ids: [],
  show_receive_btn: true,
  bg_color: '#ffffff'
})
const selectorVisible = ref(false)
const couponList = ref([])
const couponTotal = ref(0)
const couponPage = ref(1)
const couponLoading = ref(false)
const backgroundFileTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
const searchKeyword = ref('')
const searchScopeType = ref(null)
const tempSelected = ref([])
const fromProp = ref(false)
const lastEmit = ref('')

function emitConfig() {
  const cfg = { ...localConfig }
  lastEmit.value = JSON.stringify(cfg)
  emit('change', cfg)
}

function getConfig() {
  return { ...localConfig }
}

function openSelector() {
  tempSelected.value = [...(localConfig.manual_ids || [])]
  couponPage.value = 1
  searchKeyword.value = ''
  searchScopeType.value = localConfig.scope_type || null
  selectorVisible.value = true
}

async function loadCouponList() {
  couponLoading.value = true
  try {
    const params = {
      page: couponPage.value,
      page_size: 10,
      status: 1
    }
    if (searchKeyword.value) params.name = searchKeyword.value
    if (searchScopeType.value) params.scope_type = searchScopeType.value
    const res = await getCouponList(params)
    const data = res.data || {}
    couponList.value = data.list || data.data || []
    couponTotal.value = data.total || 0
  } catch (error) {
    couponList.value = []
    couponTotal.value = 0
  } finally {
    couponLoading.value = false
  }
}

function onPageChange(page) {
  couponPage.value = page
  loadCouponList()
}

function handleSelectionChange(rows) {
  tempSelected.value = rows.map(r => ({
    id: r.id,
    name: r.name,
    price: r.price || 0,
    min_price: r.min_price || 0,
    scope_type: r.scope_type,
    expire_day: r.expire_day || 0,
    use_end_time: r.use_end_time || ''
  }))
}

function confirmSelection() {
  localConfig.manual_ids = [...tempSelected.value]
  selectorVisible.value = false
}

function removeManual(index) {
  const list = [...localConfig.manual_ids]
  list.splice(index, 1)
  localConfig.manual_ids = list
}

function applyConfig(val) {
  if (!val || Object.keys(val).length === 0) return
  if (lastEmit.value && JSON.stringify(val) === lastEmit.value) return
  fromProp.value = true
  Object.assign(localConfig, {
    title: val.title !== undefined ? val.title : '优惠券',
    display_style: val.display_style || 'scroll',
    columns: val.columns !== undefined ? val.columns : 2,
    background_image: val.background_image || '',
    show_count: val.show_count !== undefined ? val.show_count : 4,
    scope_type: val.scope_type !== undefined ? val.scope_type : 0,
    data_source: val.data_source || 'auto',
    manual_ids: Array.isArray(val.manual_ids) ? val.manual_ids : [],
    show_receive_btn: val.show_receive_btn !== undefined ? val.show_receive_btn : true,
    bg_color: val.bg_color || '#ffffff'
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

defineExpose({
  getConfig
})
</script>

<style lang="scss" scoped>
.coupon-editor {
  .field-tip {
    margin-top: 6px;
    color: var(--dashboard-text-muted);
    font-size: 12px;
    line-height: 18px;
  }

  .selector-search {
    display: flex;
    align-items: center;
  }

  .selected-list {
    margin-top: 8px;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    padding: 4px 0;
    max-height: 160px;
    overflow-y: auto;
  }

  .selected-item {
    display: flex;
    align-items: center;
    padding: 4px 10px;
    font-size: 12px;

    &:hover {
      background: #f5f7fa;
    }

    .selected-name {
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: #333;
    }

    .selected-price {
      color: #e6a23c;
      font-weight: 600;
      margin-right: 8px;
      flex-shrink: 0;
    }

    .selected-delete {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      width: 24px;
      height: 24px;
      padding: 0;
      border: 0;
      background: transparent;
      color: #ff4d2e;
      font-size: 14px;
      line-height: 1;
      cursor: pointer;

      &:hover {
        color: #d9361b;
      }
    }
  }
}
</style>
