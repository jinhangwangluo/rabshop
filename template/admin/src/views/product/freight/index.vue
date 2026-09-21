<template>
  <div class="product-freight-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">运费模板</h1>
        <p class="page-curator-subtitle">
          配置商品运费模板：基础信息 + 默认运费 + 指定地区运费，支持按件数/按重量计价。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="openDrawer()"
      >
        添加模板
      </el-button>
    </header>

    <div class="category-work-surface">
      <!-- 搜索区域 -->
      <el-form :model="searchForm" inline class="category-search-form" @submit.prevent>
        <el-form-item label="模板名称">
          <el-input
            v-model="searchForm.name"
            clearable
            placeholder="请输入模板名称"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="计价方式">
          <el-select
            v-model="searchForm.charge_type"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="按件数" :value="1" />
            <el-option label="按重量" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="是否默认">
          <el-select
            v-model="searchForm.is_default"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="默认" :value="1" />
            <el-option label="非默认" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table
        v-loading="loading"
        :data="list"
        row-key="id"
        class="category-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="name" label="模板名称" min-width="150" show-overflow-tooltip />
        <el-table-column label="发货时间" width="110" align="center">
          <template #default="scope">
            <span>{{ scope.row.delivery_time }}</span>
          </template>
        </el-table-column>
        <el-table-column label="计价方式" width="100" align="center">
          <template #default="scope">
            <el-tag :type="Number(scope.row.charge_type) === 1 ? 'info' : 'warning'">
              {{ chargeTypeText(scope.row.charge_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="是否默认" width="90" align="center">
          <template #default="scope">
            <el-tag v-if="Number(scope.row.is_default) === 1" type="danger">默认</el-tag>
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column label="是否包邮" width="90" align="center">
          <template #default="scope">
            <el-tag v-if="Number(scope.row.is_free) === 1" type="success">包邮</el-tag>
            <span v-else class="empty-text">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="rule_count" label="规则数" width="80" align="center" />
        <el-table-column prop="sort" label="排序" width="80" align="center" />
        <el-table-column label="状态" width="90" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="170" align="center" />
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="scope">
            <a @click="openDrawer(scope.row)">编辑</a>
            <el-divider v-if="Number(scope.row.is_default) !== 1" direction="vertical" />
            <a v-if="Number(scope.row.is_default) !== 1" @click="handleSetDefault(scope.row)">设为默认</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-if="total > 0"
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 添加/编辑运费模板抽屉 -->
    <el-drawer
      v-model="drawerVisible"
      :title="form.id ? '编辑运费模板' : '添加运费模板'"
      size="920px"
      :close-on-click-modal="false"
      :before-close="handleClose"
      destroy-on-close
    >
      <div class="freight-drawer">
        <el-form
          ref="formRef"
          :model="form"
          :rules="formRules"
          label-width="110px"
          class="freight-form"
        >
          <!-- 基础信息 -->
          <div class="freight-section">
            <div class="freight-section__title">基础信息</div>
            <el-row :gutter="24">
              <el-col :span="12">
                <el-form-item label="模板名称" prop="name">
                  <el-input v-model="form.name" placeholder="请输入模板名称" maxlength="30" show-word-limit />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="发货时间" prop="delivery_time">
                  <el-select v-model="form.delivery_time" class="form-width" placeholder="请选择发货时间">
                    <el-option
                      v-for="item in deliveryTimeOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value"
                    />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="排序">
                  <el-input-number v-model="form.sort" :min="0" :max="9999" class="form-width" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="是否默认">
                  <el-radio-group v-model="form.is_default">
                    <el-radio :label="1">是</el-radio>
                    <el-radio :label="0">否</el-radio>
                  </el-radio-group>
                  <div class="form-tip">同商户仅一个默认模板，保存后自动取消其他默认</div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="是否包邮">
                  <el-radio-group v-model="form.is_free">
                    <el-radio :label="1">是</el-radio>
                    <el-radio :label="0">否</el-radio>
                  </el-radio-group>
                  <div class="form-tip">"是"则全免，阶梯保留（取消包邮后恢复）</div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="计价方式" prop="charge_type">
                  <el-radio-group v-model="form.charge_type">
                    <el-radio :label="1">按件数</el-radio>
                    <el-radio :label="2">按重量</el-radio>
                  </el-radio-group>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="状态">
                  <el-radio-group v-model="form.status">
                    <el-radio :label="1">启用</el-radio>
                    <el-radio :label="0">禁用</el-radio>
                  </el-radio-group>
                </el-form-item>
              </el-col>
            </el-row>
          </div>

          <el-divider />

          <!-- 默认运费 -->
          <div class="freight-section">
            <div class="freight-section__title">
              默认运费
              <span v-if="!isFreeDisabled" class="freight-section__desc">未配置指定地区规则的收货地址，按以下默认阶梯计费</span>
            </div>
            <div v-if="isFreeDisabled" class="freight-section__disabled-tip">包邮模板无需配置运费，阶梯字段已保留（取消包邮后恢复配置）</div>
            <el-row :gutter="24">
              <el-col :span="12">
                <el-form-item :label="`首${chargeUnitText}数量`" prop="default_first_quantity">
                  <el-input-number
                    v-model="form.default_first_quantity"
                    :min="0"
                    :precision="quantityPrecision"
                    class="form-width"
                    :disabled="isFreeDisabled"
                  />
                  <div class="form-tip">阶梯按{{ chargeUnitText === '件' ? '件数' : '重量' }}计算</div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="首费（元）" prop="default_first_amount">
                  <el-input-number
                    v-model="form.default_first_amount"
                    :min="0"
                    :precision="2"
                    class="form-width"
                    :disabled="isFreeDisabled"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item :label="`续${chargeUnitText}数量`">
                  <el-input-number
                    v-model="form.default_continue_quantity"
                    :min="0"
                    :precision="quantityPrecision"
                    class="form-width"
                    :disabled="isFreeDisabled"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="续费（元）">
                  <el-input-number
                    v-model="form.default_continue_amount"
                    :min="0"
                    :precision="2"
                    class="form-width"
                    :disabled="isFreeDisabled"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="满额免运费">
                  <el-input-number
                    v-model="form.free_amount"
                    :min="0"
                    :precision="2"
                    class="form-width"
                    :disabled="isFreeDisabled"
                  />
                  <div class="form-tip">订单金额≥该值免运费，0 不启用</div>
                </el-form-item>
              </el-col>
            </el-row>
          </div>

          <el-divider />

          <!-- 指定地区运费 -->
          <div class="freight-section">
            <div class="freight-section__header">
              <div class="freight-section__title">
                指定地区运费
                <span class="freight-section__desc">未命中以下地区的订单按默认运费计费</span>
              </div>
              <el-button
                type="primary"
                plain
                size="small"
                :disabled="isFreeDisabled"
                @click="addRule"
              >
                添加地区规则
              </el-button>
            </div>
            <el-table
              :data="form.rules"
              border

              class="rule-table"
              :empty-text="isFreeDisabled ? '包邮模板无需配置地区规则' : ''"
            >
              <template #empty>
                <div v-if="!isFreeDisabled" class="rule-empty" @click="addRule">
                  ✚ 添加地区规则
                </div>
                <div v-else class="rule-empty is-disabled">包邮模板无需配置地区规则</div>
              </template>
              <el-table-column label="运送到" min-width="260">
                <template #default="scope">
                  <div class="rule-city">
                    <span
                      class="rule-city__trigger"
                      :class="{ 'is-empty': !scope.row.city_names.length, 'is-disabled': isFreeDisabled }"
                      @click="isFreeDisabled ? null : openCityDialog(scope.$index)"
                    >
                      {{ scope.row.city_names.length ? scope.row.city_names.join('、') : '请选择地区' }}
                    </span>
                    <span class="rule-city__del" :class="{ 'is-disabled': isFreeDisabled }" @click="removeRule(scope.$index)">删除</span>
                  </div>
                </template>
              </el-table-column>
              <el-table-column :label="`首${chargeUnitText}`" width="110">
                <template #default="scope">
                  <el-input-number
                    v-model="scope.row.first_quantity"
                    :min="0"
                    :precision="quantityPrecision"

                    :controls="false"
                    :disabled="isFreeDisabled"
                    class="rule-input"
                  />
                </template>
              </el-table-column>
              <el-table-column label="首费" width="100">
                <template #default="scope">
                  <el-input-number
                    v-model="scope.row.first_amount"
                    :min="0"
                    :precision="2"

                    :controls="false"
                    :disabled="isFreeDisabled"
                    class="rule-input"
                  />
                </template>
              </el-table-column>
              <el-table-column :label="`续${chargeUnitText}`" width="110">
                <template #default="scope">
                  <el-input-number
                    v-model="scope.row.continue_quantity"
                    :min="0"
                    :precision="quantityPrecision"

                    :controls="false"
                    :disabled="isFreeDisabled"
                    class="rule-input"
                  />
                </template>
              </el-table-column>
              <el-table-column label="续费" width="100">
                <template #default="scope">
                  <el-input-number
                    v-model="scope.row.continue_amount"
                    :min="0"
                    :precision="2"

                    :controls="false"
                    :disabled="isFreeDisabled"
                    class="rule-input"
                  />
                </template>
              </el-table-column>
              <el-table-column label="满免" width="100">
                <template #default="scope">
                  <el-input-number
                    v-model="scope.row.free_amount"
                    :min="0"
                    :precision="2"

                    :controls="false"
                    :disabled="isFreeDisabled"
                    class="rule-input"
                  />
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-form>

        <div class="freight-drawer__footer">
          <el-button @click="handleClose">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="submitForm">确 定</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 地区选择弹窗（级联面板多选，交互参照业务规范） -->
    <el-dialog
      v-model="cityDialogVisible"
      title="编辑地区"
      width="780px"
      append-to-body
      destroy-on-close
    >
      <el-cascader-panel
        v-model="selectedPaths"
        :options="cityTreeWithRoot"
        :props="cityPanelProps"
        class="city-cascader"
      />
      <div class="city-dialog-tip">勾选省、市、区县或末级区域作为本行规则，未配置的地区按默认运费计费</div>
      <template #footer>
        <span class="city-dialog-footer">
          <span class="city-dialog-footer__left">
            <el-button type="primary" @click="selectAllCities">全 选</el-button>
            <el-button @click="clearCities">清 空</el-button>
          </span>
          <span class="city-dialog-footer__right">
            <el-button @click="cancelCityDialog">取 消</el-button>
            <el-button type="primary" @click="confirmCities">确 认</el-button>
          </span>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeMount, reactive, ref } from 'vue'
import {
  getFreightList,
  getFreightRead,
  createFreight,
  updateFreight,
  deleteFreight,
  setFreightStatus,
  setFreightDefault,
  getDeliveryTimeOptions,
  getCityTree,
  getFreightRules
} from '@/api/product'

defineOptions({
  name: 'ProductFreight'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm

const loading = ref(false)
const list = ref([])
const total = ref(0)
const drawerVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref(null)

const deliveryTimeOptions = ref([])
const cityTree = ref([])
const cityDialogVisible = ref(false)
const cityDialogIndex = ref(-1)
const selectedPaths = ref([])

const searchForm = reactive({
  name: '',
  charge_type: '',
  is_default: '',
  status: '',
  page: 1,
  limit: 20
})

const form = reactive(getDefaultForm())

const formRules = {
  name: [
    { required: true, message: '请输入模板名称', trigger: 'blur' }
  ],
  delivery_time: [
    { required: true, message: '请选择发货时间', trigger: 'change' }
  ],
  charge_type: [
    { required: true, message: '请选择计价方式', trigger: 'change' }
  ],
  default_first_quantity: [
    { required: true, message: '请输入首件数量', trigger: 'blur' }
  ],
  default_first_amount: [
    { required: true, message: '请输入首费金额', trigger: 'blur' }
  ]
}

const isFreeDisabled = computed(() => Number(form.is_free) === 1)
const chargeUnitText = computed(() => (Number(form.charge_type) === 2 ? '重' : '件'))
const quantityPrecision = computed(() => (Number(form.charge_type) === 2 ? 2 : 0))

// 级联面板多选配置：勾选上级时自动联动选择全部下级地区。
const cityPanelProps = {
  multiple: true,
  checkStrictly: false,
  label: 'label',
  value: 'value',
  children: 'children'
}

// 虚拟根"中国"（与业务地区面板一致，勾选时仅作展示入口）
const cityTreeWithRoot = computed(() => cityTree.value.length
  ? [{ value: 0, label: '中国', children: cityTree.value }]
  : [])

// value -> 完整路径（含虚拟根 0），用于回显与祖先消歧
const idToChain = computed(() => {
  const map = {}
  const walk = (nodes, chain) => {
    (nodes || []).forEach(node => {
      const nextChain = [...chain, node.value]
      map[node.value] = nextChain
      walk(node.children, nextChain)
    })
  }
  walk(cityTree.value, [0])
  return map
})

const labelMap = computed(() => {
  const map = {}
  const walk = nodes => {
    (nodes || []).forEach(node => {
      map[node.value] = node.label
      walk(node.children)
    })
  }
  walk(cityTree.value)
  return map
})

function getDefaultForm() {
  return {
    id: 0,
    name: '',
    delivery_time: '',
    is_default: 0,
    is_free: 0,
    charge_type: 1,
    sort: 0,
    status: 1,
    default_first_quantity: 1,
    default_first_amount: 0,
    default_continue_quantity: 0,
    default_continue_amount: 0,
    free_amount: 0,
    rules: [getDefaultRule()]
  }
}

function getDefaultRule() {
  return {
    city_ids: '',
    city_names: [],
    first_quantity: 1,
    first_amount: 0,
    continue_quantity: 0,
    continue_amount: 0,
    free_amount: 0
  }
}

function chargeTypeText(type) {
  const map = { 1: '按件数', 2: '按重量' }
  return map[Number(type)] || '-'
}

function getList() {
  loading.value = true
  getFreightList(searchForm).then(res => {
    const data = res.data || {}
    list.value = data.list || []
    total.value = data.total || 0
  }).finally(() => {
    loading.value = false
  })
}

function handleSearch() {
  searchForm.page = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, { name: '', charge_type: '', is_default: '', status: '', page: 1, limit: 20 })
  getList()
}

function handleSizeChange(size) {
  searchForm.limit = size
  searchForm.page = 1
  getList()
}

function handlePageChange(page) {
  searchForm.page = page
  getList()
}

function openDrawer(row) {
  Object.assign(form, getDefaultForm())
  if (row && row.id) {
    Promise.all([getFreightRead(row.id), getFreightRules(row.id)]).then(([detailRes, rulesRes]) => {
      const detail = detailRes.data || {}
      const rules = rulesRes.data || []
      const normalizedRules = rules.map(rule => ({
        city_ids: rule.city_ids || '',
        city_names: rule.city_names || [],
        first_quantity: Number(rule.first_quantity) || 0,
        first_amount: Number(rule.first_amount) || 0,
        continue_quantity: Number(rule.continue_quantity) || 0,
        continue_amount: Number(rule.continue_amount) || 0,
        free_amount: Number(rule.free_amount) || 0
      }))
      Object.assign(form, {
        id: detail.id,
        name: detail.name,
        delivery_time: detail.delivery_time,
        is_default: Number(detail.is_default),
        is_free: Number(detail.is_free),
        charge_type: Number(detail.charge_type),
        sort: Number(detail.sort) || 0,
        status: Number(detail.status),
        default_first_quantity: Number(detail.default_first_quantity) || 0,
        default_first_amount: Number(detail.default_first_amount) || 0,
        default_continue_quantity: Number(detail.default_continue_quantity) || 0,
        default_continue_amount: Number(detail.default_continue_amount) || 0,
        free_amount: Number(detail.free_amount) || 0,
        rules: normalizedRules.length ? normalizedRules : [getDefaultRule()]
      })
    })
  }
  drawerVisible.value = true
}

function handleClose() {
  drawerVisible.value = false
  nextTick(() => {
    formRef.value && formRef.value.resetFields()
    Object.assign(form, getDefaultForm())
  })
}

function addRule() {
  form.rules.push(getDefaultRule())
  const index = form.rules.length - 1
  nextTick(() => {
    openCityDialog(index)
  })
}

function removeRule(index) {
  form.rules.splice(index, 1)
}

function openCityDialog(index) {
  cityDialogIndex.value = index
  const rule = form.rules[index]
  const ids = rule.city_ids
    ? String(rule.city_ids).split(',').map(Number).filter(Boolean)
    : []
  selectedPaths.value = ids.map(id => idToChain.value[Number(id)] || [0, Number(id)])
  cityDialogVisible.value = true
}

function cancelCityDialog() {
  cityDialogVisible.value = false
}

// 全选：勾选全部省级节点，级联面板会同步选中下级地区。
function selectAllCities() {
  selectedPaths.value = cityTree.value.map(item => [0, item.value])
}

function clearCities() {
  selectedPaths.value = []
}

function confirmCities() {
  const rule = form.rules[cityDialogIndex.value]
  if (rule) {
    const result = resolveSelectedCities()
    rule.city_ids = result.join(',')
    rule.city_names = result.map(id => labelMap.value[id]).filter(Boolean)
  }
  cityDialogVisible.value = false
}

// 解析最终地区集合：过滤虚拟根，且同一规则内若同时勾选祖先与后代，仅保留后代（避免区域交叉）
function resolveSelectedCities() {
  const chains = selectedPaths.value
    .map(path => (Array.isArray(path) ? path : []))
    .filter(path => path.length && Number(path[path.length - 1]) !== 0)
    .map(path => path.map(Number))
  return chains.filter(chain => {
    const hasDescendant = chains.some(other =>
      other.length > chain.length && chain.every((v, idx) => other[idx] === v)
    )
    return !hasDescendant
  }).map(chain => chain[chain.length - 1])
}

function isDefaultEmptyRule(rule) {
  return !String(rule.city_ids || '').trim() &&
    Number(rule.first_quantity) === 1 &&
    Number(rule.first_amount) === 0 &&
    Number(rule.continue_quantity) === 0 &&
    Number(rule.continue_amount) === 0 &&
    Number(rule.free_amount) === 0
}

function submitForm() {
  formRef.value && formRef.value.validate(valid => {
    if (!valid) return
    const rules = form.rules.filter(rule => !isDefaultEmptyRule(rule))
    // 前端防重复地区（后端兜底校验，避免一次请求往返）
    const citySet = new Set()
    for (const rule of rules) {
      const ids = String(rule.city_ids).split(',').filter(Boolean)
      if (!ids.length) {
        message.warning('每条指定地区规则必须选择地区')
        return
      }
      for (const id of ids) {
        if (citySet.has(id)) {
          message.warning('地区不能重复配置')
          return
        }
        citySet.add(id)
      }
    }
    submitLoading.value = true
    const payload = {
      name: form.name,
      delivery_time: form.delivery_time,
      is_default: form.is_default,
      is_free: form.is_free,
      charge_type: form.charge_type,
      default_first_quantity: form.default_first_quantity,
      default_first_amount: form.default_first_amount,
      default_continue_quantity: form.default_continue_quantity,
      default_continue_amount: form.default_continue_amount,
      free_amount: form.free_amount,
      sort: form.sort,
      status: form.status,
      rules: rules.map(rule => ({
        city_ids: rule.city_ids,
        first_quantity: rule.first_quantity,
        first_amount: rule.first_amount,
        continue_quantity: rule.continue_quantity,
        continue_amount: rule.continue_amount,
        free_amount: rule.free_amount
      }))
    }
    const api = form.id
      ? updateFreight(form.id, payload)
      : createFreight(payload)
    api.then(res => {
      message.success(res.message || '操作成功')
      handleClose()
      getList()
    }).catch(() => {}).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleSetDefault(row) {
  setFreightDefault(row.id).then(res => {
    message.success(res.message || '设置成功')
    getList()
  }).catch(() => {})
}

function handleDelete(id) {
  confirm('确定要删除该运费模板吗？', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteFreight(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    }).catch(() => {})
  }).catch(() => {})
}

function handleStatusChange(row) {
  setFreightStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

onBeforeMount(() => {
  getList()
  getDeliveryTimeOptions().then(res => {
    deliveryTimeOptions.value = Object.keys(res.data || {}).map(value => ({ value, label: res.data[value] }))
  }).catch(() => {})
  getCityTree().then(res => {
    cityTree.value = res.data || []
  }).catch(() => {})
})
</script>

<style lang="scss" scoped>
.product-freight-page {
  min-height: 100%;
  box-sizing: border-box;
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.category-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--ds-primary-container);
    border-color: var(--ds-primary-container);
  }
}

.category-search-form {
  margin-bottom: 8px;
}

.category-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.search-input { width: 180px; }
.search-select-sm { width: 120px; }

.danger-link {
  color: #f56c6c;

  &:hover {
    color: #f56c6c;
    opacity: 0.8;
  }
}

.empty-text {
  color: #909399;
  font-size: 12px;
}

.pagination-wrap {
  padding: 16px 0 0;
  display: flex;
  justify-content: flex-end;
}

/* 抽屉 */
.freight-drawer {
  padding: 0 24px 24px;

  &__footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;
  }
}

.freight-section {
  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;

    .freight-section__title {
      margin-bottom: 0;
    }
  }

  &__title {
    margin-bottom: 16px;
    font-size: 15px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  &__desc {
    margin-left: 8px;
    font-size: 12px;
    font-weight: 400;
    color: #909399;
  }

  &__disabled-tip {
    padding: 8px 12px;
    margin-bottom: 12px;
    border-radius: 6px;
    background: rgba(243, 65, 0, 0.06);
    color: #f34100;
    font-size: 12px;
  }
}

.form-unit {
  display: block;
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}

.form-width {
  width: 100%;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
  width: 100%;
}

.rule-table {
  margin-top: 12px;
  width: 100%;

  :deep(.el-table__cell) {
    padding: 4px 0;
  }
}

.rule-input {
  width: 100%;

  :deep(.el-input__wrapper) {
    padding: 0 6px;
  }
}

.rule-city {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 24px;

  &__trigger {
    flex: 1;
    min-width: 0;
    color: var(--ds-primary);
    font-size: 12px;
    line-height: 1.4;
    cursor: pointer;

    &:hover {
      color: var(--ds-primary-hover);
      text-decoration: underline;
    }

    &.is-empty {
      color: #f56c6c;
    }

    &.is-disabled {
      color: #909399;
      cursor: not-allowed;
      text-decoration: none;

      &:hover {
        color: #909399;
        text-decoration: none;
      }
    }
  }

  &__del {
    flex-shrink: 0;
    color: #f56c6c;
    font-size: 12px;
    cursor: pointer;

    &:hover {
      opacity: 0.8;
    }

    &.is-disabled {
      color: #c0c4cc;
      cursor: not-allowed;
    }
  }
}

.rule-empty {
  padding: 12px;
  color: var(--ds-primary);
  font-size: 13px;
  text-align: center;
  cursor: pointer;

  &:hover {
    color: var(--ds-primary-hover);
  }

  &.is-disabled {
    color: #909399;
    cursor: not-allowed;
  }
}

.city-cascader {
  width: 100%;
  height: 380px;
  border: 1px solid var(--prev-border-color-light, #ebeef5);
  border-radius: 8px;
  overflow: hidden;

  :deep(.el-cascader-panel__label) {
    color: var(--ds-on-surface);
  }

  :deep(.el-cascader-node) {
    height: 38px;
  }

  :deep(.el-cascader-node__label) {
    font-size: 13px;
    color: var(--ds-on-surface-variant);
  }

  :deep(.el-cascader-node__label:hover) {
    color: var(--ds-primary);
  }

  :deep(.el-cascader-node--selectable + .el-cascader-node) {
    flex: 1;
  }
}

:deep(.el-scrollbar__wrap){
  height: 100%;
}

.city-dialog-tip {
  margin-top: 10px;
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}

.city-dialog-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__left,
  &__right {
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
</style>
