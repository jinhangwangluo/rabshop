<template>
  <el-dialog
    v-model="dialogVisible"
    title="选择商品"
    width="900px"
    :close-on-click-modal="false"
    append-to-body
    @open="onOpen"
  >
    <div class="product-picker">
      <!-- 搜索栏 -->
      <div class="picker-search">
        <el-input
          v-model="keyword"
          placeholder="搜索商品名称"
          clearable
          class="picker-search-input"
          @keyup.enter="loadList"
        >
          <template #append>
            <el-button icon="el-icon-search" @click="loadList" />
          </template>
        </el-input>
        <span class="picker-tip">仅可选择平台商品</span>
      </div>

      <!-- 列表 -->
      <el-table
        v-loading="loading"
        :data="list"
        class="picker-table"
        row-key="id"
      >
        <el-table-column label="封面" width="70" align="center">
          <template #default="{ row }">
            <el-image v-if="row.cover" :src="row.cover" class="picker-cover" fit="cover" />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="商品名称" min-width="200" show-overflow-tooltip />
        <el-table-column label="价格" width="110" align="center">
          <template #default="{ row }">
            <span class="price-text">¥{{ row.price }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="stock" label="库存" width="90" align="center" />
        <el-table-column label="操作" width="90" align="center" fixed="right">
          <template #default="{ row }">
            <a class="picker-select-link" @click="handleSelect(row)">选择</a>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </el-dialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { getStoreProductOptions } from '@/api/product'

defineOptions({
  name: 'ProductPicker'
})

const props = defineProps({
  // 弹窗显隐通过 visible/update:visible 协议控制
  visible: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['select', 'update:visible'])

const keyword = ref('')
const loading = ref(false)
const list = ref([])

const dialogVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

function onOpen() {
  keyword.value = ''
  loadList()
}

/**
 * 加载可选商品列表。
 *
 * 固定传 platform_only=1，接口侧只返回平台商品（merchant_id = 0），
 * 避免把商户商品挂载到直播间。
 */
function loadList() {
  loading.value = true
  getStoreProductOptions({
    name: keyword.value || undefined,
    platform_only: 1
  }).then((res) => {
    const data = res.data || []
    list.value = Array.isArray(data) ? data : (data.list || [])
  }).catch(() => {
    list.value = []
  }).finally(() => {
    loading.value = false
  })
}

function handleSelect(row) {
  emit('select', row)
  emit('update:visible', false)
}
</script>

<style lang="scss" scoped>
.product-picker {
  .picker-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
  }

  .picker-search-input {
    width: 320px;
  }

  .picker-tip {
    font-size: 12px;
    color: #909399;
  }

  .picker-table {
    width: 100%;

    .picker-cover {
      width: 54px;
      height: 38px;
      border-radius: 4px;
    }
  }

  // 操作列使用链接式入口：比小按钮更窄，避免列内容溢出被截断成省略号
  .picker-select-link {
    color: var(--dashboard-accent, #f34100);
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;

    &:hover {
      color: var(--dashboard-accent-hover, #d93a00);
    }
  }

  .no-cover {
    color: #c0c4cc;
    font-size: 12px;
  }

  .price-text {
    color: #e6a23c;
    font-weight: 600;
    font-size: 13px;
  }
}
</style>
