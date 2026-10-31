<template>
  <div class="signin-setting-page">
    <CombinationDataListPanel
      ref="panelRef"
      config-key="activity_signin_reward"
      title="签到设置"
      :show-back="false"
      :allow-create="false"
      :allow-delete="false"
      :show-actions="false"
      :page-size="20"
      empty-text="暂无签到奖励配置"
      @loaded="handleCombinationLoaded"
    >
      <template #header-actions="{ reload }">
        <el-button size="mini" icon="el-icon-refresh" @click="reload">刷新</el-button>
        <el-button
          type="primary"
          size="mini"
          icon="el-icon-check"
          :loading="savingAll"
          @click="saveAllRewards"
        >
          保存全部
        </el-button>
      </template>

      <template #table="{ loading }">
        <el-table
          v-loading="loading || savingAll"
          :data="rewards"
          row-key="day"

          class="signin-reward-table"
        >
          <el-table-column label="签到天数" width="110" align="center">
            <template #default="{ row }">
              <el-tag>第{{ row.day }}天</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="积分奖励" width="180">
            <template #default="{ row }">
              <el-input-number
                v-model="row.points"

                controls-position="right"
                :min="0"
                :max="999999"
                :step="1"
              />
            </template>
          </el-table-column>
          <el-table-column label="优惠券奖励" min-width="260">
            <template #default="{ row }">
              <div
                :class="['coupon-select-box', row.coupon_id ? 'coupon-select-box--selected' : '']"
                @click="openCouponDialog(row)"
              >
                <div v-if="row.coupon_id" class="coupon-selected-tag">
                  <span class="coupon-selected-tag__text">{{ formatSelectedCoupon(row) }}</span>
                  <button
                    type="button"
                    class="coupon-selected-tag__close"
                    @click.stop="clearCoupon(row)"
                  >
                    <LegacyIcon name="el-icon-close" />
                  </button>
                </div>
                <span v-else class="coupon-select-placeholder">请选择优惠券</span>
                <LegacyIcon name="el-icon-arrow-down" class=" coupon-select-arrow" />
              </div>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120" fixed="right" align="center">
            <template #default="{ row }">
              <el-button type="text" :loading="row.saving" @click="saveReward(row)">保存</el-button>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </CombinationDataListPanel>

    <el-dialog
      v-model="couponDialogVisible"
      title="选择优惠券"
      width="860px"
      :close-on-click-modal="false"
    >
      <el-form :model="couponSearch" inline class="coupon-search-form" @submit.prevent>
        <el-form-item label="优惠券名称">
          <el-input
            v-model="couponSearch.name"
            clearable

            placeholder="请输入优惠券名称"
            prefix-icon="el-icon-search"
            @keyup.enter="handleCouponSearch"
          />
        </el-form-item>
        <el-form-item label="适用范围">
          <el-select v-model="couponSearch.scope_type" clearable placeholder="全部范围">
            <el-option
              v-for="item in scopeOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="handleCouponSearch">查询</el-button>
          <el-button icon="el-icon-refresh" @click="resetCouponSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="couponLoading" :data="couponList" row-key="id">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="优惠券名称" min-width="180" show-overflow-tooltip />
        <el-table-column label="优惠金额" width="110" align="center">
          <template #default="{ row }">
            <span class="amount-text">¥{{ formatMoney(row.discount_amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="使用门槛" width="110" align="center">
          <template #default="{ row }">
            <span v-if="Number(row.min_order_amount || 0) > 0">满¥{{ formatMoney(row.min_order_amount) }}</span>
            <span v-else class="text-muted">无门槛</span>
          </template>
        </el-table-column>
        <el-table-column label="适用范围" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="Number(row.scope_type) === 10 ? 'success' : 'warning'">
              {{ row.scope_type_text || getScopeLabel(row.scope_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效期" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.expire_text || formatExpireText(row) }}
          </template>
        </el-table-column>
        <el-table-column label="库存" width="100" align="center">
          <template #default="{ row }">
            <span v-if="Number(row.is_quantity_limited || 0) === 1">{{ row.remain_quantity }}</span>
            <span v-else class="text-muted">不限量</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="90" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="text" @click="selectCoupon(row)">选择</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="couponPage"
          :page-size="couponLimit"
          :total="couponTotal"
          layout="total, prev, pager, next"
          @current-change="handleCouponPageChange"
        />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import CombinationDataListPanel from '@/components/CombinationDataListPanel'
import { getSigninRewardCouponOptions } from '@/api/coupon'

const DEFAULT_POINTS = [5, 5, 8, 8, 10, 10, 15]

defineOptions({
  name: 'MarketingSignin'
})

const panelRef = ref(null)
const rewards = ref([])
const savingAll = ref(false)
const couponDialogVisible = ref(false)
const couponLoading = ref(false)
const couponList = ref([])
const couponTotal = ref(0)
const couponPage = ref(1)
const couponLimit = ref(10)
const activeReward = ref(null)
const selectedCouponMap = reactive({})
const couponSearch = reactive({
  name: '',
  scope_type: ''
})

const scopeOptions = [
  { label: '平台通用券', value: 10 },
  { label: '平台品类券', value: 11 }
]

function handleCombinationLoaded(payload) {
  resetSelectedCouponMap()
  const list = payload && payload.list ? payload.list : []
  rewards.value = buildRewardRows(list)
  void loadSelectedCouponNames()
}

function buildRewardRows(list) {
  const map = {}
  ;(Array.isArray(list) ? list : []).forEach(item => {
    const formData = resolveFormData(item)
    const day = Number(formData.day || item.data_day || 0)
    if (day >= 1 && day <= 7 && !map[day]) {
      map[day] = { item, formData }
    }
  })

  return DEFAULT_POINTS.map((point, index) => {
    const day = index + 1
    const source = map[day] || {}
    const item = source.item || {}
    const formData = source.formData || {}
    const couponId = Number(formData.coupon_id || item.data_coupon_id || 0)
    const savedPoints = hasValue(formData.points) ? formData.points : item.data_points

    return {
      id: Number(item.id || 0),
      day,
      points: hasValue(savedPoints) ? Number(savedPoints) : point,
      coupon_id: couponId,
      coupon_name: selectedCouponMap[couponId] || '',
      sort: item.sort === undefined || item.sort === null ? 100 - index * 10 : Number(item.sort),
      saving: false
    }
  })
}

function resolveFormData(item) {
  const formData = item && item.form_data ? item.form_data : {}
  if (typeof formData === 'string') {
    try {
      return JSON.parse(formData) || {}
    } catch {
      return {}
    }
  }
  return { ...(formData || {}) }
}

function hasValue(value) {
  return value !== undefined && value !== null && value !== ''
}

function setRewardSaving(row, value) {
  row.saving = value
}

async function saveReward(row) {
  const error = validateReward(row)
  if (error) {
    ElMessage.warning(error)
    return
  }

  setRewardSaving(row, true)
  try {
    await persistReward(row, false)
    await panelRef.value.loadData()
  } finally {
    setRewardSaving(row, false)
  }
}

async function saveAllRewards() {
  const error = validateAllRewards()
  if (error) {
    ElMessage.warning(error)
    return
  }

  savingAll.value = true
  try {
    await Promise.all(rewards.value.map(row => persistReward(row, true)))
    ElMessage.success('保存成功')
    await panelRef.value.loadData()
  } finally {
    savingAll.value = false
  }
}

function persistReward(row, silent) {
  const formData = {
    day: row.day,
    points: Number(row.points || 0),
    coupon_id: Number(row.coupon_id || 0)
  }

  return panelRef.value.saveRow(row, formData, {
    sort: row.sort,
    status: 1,
    reload: false,
    message: !silent
  })
}

function validateReward(row) {
  if (Number(row.points || 0) < 0) {
    return `第${row.day}天积分不能小于0`
  }
  if (Number(row.points || 0) <= 0 && Number(row.coupon_id || 0) <= 0) {
    return `请至少设置第${row.day}天的积分或优惠券奖励`
  }
  return ''
}

function validateAllRewards() {
  for (let i = 0; i < rewards.value.length; i++) {
    const error = validateReward(rewards.value[i])
    if (error) return error
  }
  return ''
}

function openCouponDialog(row) {
  activeReward.value = row
  couponPage.value = 1
  couponDialogVisible.value = true
  void loadCouponOptions()
}

function handleCouponSearch() {
  couponPage.value = 1
  void loadCouponOptions()
}

function resetCouponSearch() {
  Object.assign(couponSearch, { name: '', scope_type: '' })
  couponPage.value = 1
  void loadCouponOptions()
}

function handleCouponPageChange(page) {
  couponPage.value = page
  void loadCouponOptions()
}

async function loadCouponOptions(extraParams) {
  couponLoading.value = true
  const params = {
    ...couponSearch,
    ...(extraParams || {}),
    page: extraParams && extraParams.page ? extraParams.page : couponPage.value,
    limit: extraParams && extraParams.limit ? extraParams.limit : couponLimit.value
  }
  try {
    const res = await getSigninRewardCouponOptions(params)
    const data = res.data || {}
    const list = data.list || []
    couponList.value = list
    couponTotal.value = data.count || data.total || 0
    mergeCouponNames(list)
  } finally {
    couponLoading.value = false
  }
}

async function loadSelectedCouponNames() {
  const couponIds = rewards.value.map(row => Number(row.coupon_id || 0)).filter(id => id > 0)
  if (!couponIds.length) return
  try {
    const res = await getSigninRewardCouponOptions({ page: 1, limit: 200 })
    const data = res.data || {}
    mergeCouponNames(data.list || [])
    rewards.value.forEach(row => {
      row.coupon_name = selectedCouponMap[Number(row.coupon_id || 0)] || row.coupon_name
    })
  } catch {
    // 保持原有静默失败行为
  }
}

function mergeCouponNames(list) {
  const map = { ...selectedCouponMap }
  ;(Array.isArray(list) ? list : []).forEach(item => {
    const id = Number(item.id || item.coupon_id || 0)
    if (id > 0) {
      map[id] = item.name || `优惠券#${id}`
    }
  })
  resetSelectedCouponMap()
  Object.assign(selectedCouponMap, map)
}

function resetSelectedCouponMap() {
  Object.keys(selectedCouponMap).forEach(key => {
    delete selectedCouponMap[key]
  })
}

function selectCoupon(coupon) {
  if (!activeReward.value) return
  const couponId = Number(coupon.id || coupon.coupon_id || 0)
  activeReward.value.coupon_id = couponId
  activeReward.value.coupon_name = coupon.name || `优惠券#${couponId}`
  selectedCouponMap[couponId] = activeReward.value.coupon_name
  couponDialogVisible.value = false
}

function clearCoupon(row) {
  row.coupon_id = 0
  row.coupon_name = ''
}

function formatSelectedCoupon(row) {
  if (!row.coupon_id) return '未选择'
  return row.coupon_name || selectedCouponMap[row.coupon_id] || `优惠券#${row.coupon_id}`
}

function getScopeLabel(scopeType) {
  const type = Number(scopeType)
  const item = scopeOptions.find(option => option.value === type)
  return item ? item.label : '优惠券'
}

function formatMoney(value) {
  const text = Number(value || 0).toFixed(2)
  return text.replace(/\.00$/, '')
}

function formatExpireText(row) {
  if (Number(row.expire_type || 0) === 0) {
    return Number(row.valid_days || 0) > 0 ? `领取后${row.valid_days}天内有效` : '领取后有效'
  }
  if (row.valid_end_time) {
    return `有效期至 ${row.valid_end_time}`
  }
  return '有效期以券面为准'
}
</script>

<style scoped lang="scss">
.signin-setting-page {

  .signin-reward-table {
    :deep(.el-input-number) {
      width: 140px;
    }

    :deep(.el-select) {
      width: 120px;
    }
  }

  .coupon-select-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 240px;
    min-height: 32px;
    padding: 4px 34px 4px 10px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    transition: border-color .2s, box-shadow .2s;
  }

  .coupon-select-box:hover {
    border-color: #c0c4cc;
  }

  .coupon-select-box--selected {
    padding-top: 5px;
    padding-bottom: 5px;
  }

  .coupon-select-placeholder {
    color: #c0c4cc;
    font-size: 13px;
    line-height: 22px;
  }

  .coupon-select-arrow {
    position: absolute;
    top: 50%;
    right: 10px;
    color: #c0c4cc;
    font-size: 12px;
    transform: translateY(-50%);
    pointer-events: none;
  }

  .coupon-selected-tag {
    position: relative;
    display: inline-flex;
    align-items: center;
    max-width: 100%;
    min-height: 22px;
    padding: 0 20px 0 8px;
    border: 1px solid #b3e6d1;
    border-radius: 4px;
    background: #ecfdf5;
    color: #0f9f6e;
    font-size: 12px;
    line-height: 20px;
  }

  .coupon-selected-tag__text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .coupon-selected-tag__close {
    position: absolute;
    top: -7px;
    right: -7px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: 1px solid #dcdfe6;
    border-radius: 50%;
    background: #fff;
    color: #909399;
    cursor: pointer;
    font-size: 10px;
    line-height: 1;
  }

  .coupon-selected-tag__close:hover {
    border-color: #f56c6c;
    color: #f56c6c;
  }

  .text-muted {
    color: #909399;
  }

  .amount-text {
    color: #f56c6c;
    font-weight: 600;
  }

  .coupon-search-form {
    margin-bottom: 12px;
  }

  .pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding-top: 16px;
  }
}
</style>
