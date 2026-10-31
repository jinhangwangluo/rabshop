<template>
  <div class="coupon-create-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">{{ isEdit ? '编辑优惠券' : '创建优惠券' }}</h1>
        <p class="page-curator-subtitle">
          配置优惠券基础信息、发放规则与有效期，平台优惠券适用于全平台用户。
        </p>
      </div>
      <el-button class="btn-curator-reset" icon="el-icon-arrow-left" @click="handleBack">返回列表</el-button>
    </header>

    <div v-loading="pageLoading" class="coupon-create-body">
      <el-form
        ref="couponFormRef"
        :model="formData"
        :rules="formRules"
        label-width="130px"
        class="coupon-form"
      >
        <!-- 基础信息 -->
        <el-card class="form-card" shadow="never">
          <template #header>
            <div class="card-header">
              <span class="card-title">基础信息</span>
            </div>
          </template>
          <el-row :gutter="24">
            <el-col :span="12">
              <el-form-item label="优惠券名称" prop="name">
                <el-input
                  v-model="formData.name"
                  placeholder="请输入优惠券名称"
                  maxlength="64"
                  show-word-limit
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="适用范围" prop="scope_type">
                <el-select v-model="formData.scope_type" placeholder="请选择适用范围" style="width:100%" @change="handleScopeTypeChange">
                  <el-option
                    v-for="item in scopeTypes"
                    :key="item.value"
                    :label="item.label"
                    :value="item.value"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="优惠金额(元)" prop="discount_amount">
                <el-input-number
                  v-model="formData.discount_amount"
                  :min="0.01"
                  :precision="2"
                  :step="1"
                  placeholder="请输入优惠金额"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="最低订单金额(元)">
                <el-input-number
                  v-model="formData.min_order_amount"
                  :min="0"
                  :precision="2"
                  :step="1"
                  placeholder="0表示无门槛"
                  style="width:100%"
                />
                <div class="form-tip">设为0则为无门槛优惠券</div>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="排序">
                <el-input-number
                  v-model="formData.sort"
                  :min="0"
                  :max="9999"
                  placeholder="数值越大越靠前"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="状态">
                <el-radio-group v-model="formData.status">
                  <el-radio :label="1">启用</el-radio>
                  <el-radio :label="0">禁用</el-radio>
                </el-radio-group>
              </el-form-item>
            </el-col>
          </el-row>

          <!-- 商品关联（scope_type=1 商品券） -->
          <template v-if="formData.scope_type === 1">
            <el-row :gutter="24">
              <el-col :span="24">
                <el-form-item label="关联内容类型">
                  <el-radio-group v-model="formData.relation_type" @change="handleRelationTypeChange">
                    <el-radio
                      v-for="item in productRelationTypes"
                      :key="item.value"
                      :label="item.value"
                      :disabled="item.disabled"
                    >{{ item.label }}</el-radio>
                  </el-radio-group>
                </el-form-item>
              </el-col>
            </el-row>

            <!-- scope_type=1 且 relation_type=1：内容（文章）弹窗多选 -->
            <el-row v-if="formData.relation_type === 1" :gutter="24">
              <el-col :span="24">
                <el-form-item label="关联内容" prop="relations">
                  <el-button type="primary" plain icon="el-icon-plus" @click="articlePickerVisible = true">选择内容</el-button>
                  <el-table
                    v-if="selectedArticles.length > 0"
                    :data="selectedArticles"

                    class="selected-table"
                    style="margin-top:10px"
                  >
                    <el-table-column label="封面" width="70" align="center">
                      <template #default="{ row }">
                        <el-image v-if="row.cover" :src="row.cover" class="selected-cover" fit="cover" />
                        <span v-else class="no-cover">暂无</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
                    <el-table-column label="类型" width="80" align="center">
                      <template #default="{ row }">
                        <el-tag v-if="row.content_type">{{ row.content_type_text || row.content_type }}</el-tag>
                        <span v-else class="no-cover">-</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="价格" width="90" align="center">
                      <template #default="{ row }">
                        <span v-if="row.price > 0" class="price-text">¥{{ row.price }}</span>
                        <el-tag v-else-if="row.price === 0 && row.id" size="mini" type="success">免费</el-tag>
                        <span v-else class="no-cover">-</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="操作" width="70" align="center">
                      <template #default="{ row }">
                        <el-button type="text" icon="el-icon-delete" class="remove-btn" @click="removeArticle(row.id)" />
                      </template>
                    </el-table-column>
                  </el-table>
                  <div class="form-tip">商品券仅对选中的内容生效</div>
                </el-form-item>
              </el-col>
            </el-row>

            <!-- scope_type=1 且 relation_type=2：专题弹窗多选 -->
            <el-row v-if="formData.relation_type === 2" :gutter="24">
              <el-col :span="24">
                <el-form-item label="关联专题" prop="relations">
                  <el-button type="primary" plain icon="el-icon-plus" @click="specialPickerVisible = true">选择专题</el-button>
                  <el-table
                    v-if="selectedSpecials.length > 0"
                    :data="selectedSpecials"

                    class="selected-table"
                    style="margin-top:10px"
                  >
                    <el-table-column label="封面" width="70" align="center">
                      <template #default="{ row }">
                        <el-image v-if="row.cover" :src="row.cover" class="selected-cover" fit="cover" />
                        <span v-else class="no-cover">暂无</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
                    <el-table-column label="价格" width="90" align="center">
                      <template #default="{ row }">
                        <span v-if="row.price > 0" class="price-text">¥{{ row.price }}</span>
                        <span v-else class="no-cover">-</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="操作" width="70" align="center">
                      <template #default="{ row }">
                        <el-button type="text" icon="el-icon-delete" class="remove-btn" @click="removeSpecial(row.id)" />
                      </template>
                    </el-table-column>
                  </el-table>
                  <div class="form-tip">商品券仅对选中的专题生效</div>
                </el-form-item>
              </el-col>
            </el-row>
          </template>

          <!-- 品类关联（scope_type=11 平台品类券） -->
          <template v-if="formData.scope_type === 11">
            <el-row :gutter="24">
              <el-col :span="24">
                <el-form-item label="品类子类型">
                  <el-radio-group v-model="formData.relation_type" @change="handleRelationTypeChange">
                    <el-radio
                      v-for="item in categoryRelationTypes"
                      :key="item.value"
                      :label="item.value"
                      :disabled="item.disabled"
                    >{{ item.label }}</el-radio>
                  </el-radio-group>
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="24">
              <el-col :span="24">
                <el-form-item label="关联品类" prop="relations">
                  <el-select
                    v-model="formData.relations"
                    multiple
                    placeholder="请选择适用品类"
                    style="width:100%"
                  >
                    <el-option
                      v-for="item in categoryOptions"
                      :key="item.id"
                      :label="item.label"
                      :value="item.id"
                      :disabled="item.disabled"
                    />
                  </el-select>
                  <div class="form-tip">品类券仅对选中品类下的内容生效</div>
                </el-form-item>
              </el-col>
            </el-row>
          </template>
        </el-card>

        <!-- 发放规则 -->
        <el-card class="form-card mt10" shadow="never">
          <template #header>
            <div class="card-header">
              <span class="card-title">发放规则</span>
            </div>
          </template>
          <el-row :gutter="24">
            <el-col :span="24">
              <el-form-item label="发放类型" prop="distribute_type">
                <el-radio-group v-model="formData.distribute_type" @change="handleDistributeTypeChange">
                  <el-radio
                    v-for="item in distributeTypes"
                    :key="item.value"
                    :label="item.value"
                  >{{ item.label }}</el-radio>
                </el-radio-group>
              </el-form-item>
            </el-col>

            <!-- 满赠最低消费（distribute_type=1） -->
            <el-col v-if="formData.distribute_type === 1" :span="12">
              <el-form-item label="满赠消费金额(元)" prop="min_consume_amount">
                <el-input-number
                  v-model="formData.min_consume_amount"
                  :min="0.01"
                  :precision="2"
                  placeholder="用户消费满该金额赠送"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="是否限量">
                <el-switch
                  v-model="formData.is_quantity_limited"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="限量"
                  inactive-text="不限"
                />
              </el-form-item>
            </el-col>

            <!-- 总量（is_quantity_limited=1） -->
            <el-col v-if="formData.is_quantity_limited === 1" :span="12">
              <el-form-item label="发放总量" prop="total_quantity">
                <el-input-number
                  v-model="formData.total_quantity"
                  :min="1"
                  placeholder="请输入发放总量"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>

            <!-- 编辑时显示剩余量 -->
            <el-col v-if="isEdit && formData.is_quantity_limited === 1" :span="12">
              <el-form-item label="剩余数量">
                <el-input :value="formData.remain_quantity" disabled />
              </el-form-item>
            </el-col>

            <el-col :span="12">
              <el-form-item label="是否限时领取">
                <el-switch
                  v-model="formData.is_time_limited"
                  :active-value="1"
                  :inactive-value="0"
                  active-text="限时"
                  inactive-text="不限"
                />
              </el-form-item>
            </el-col>

            <!-- 领取时间范围（is_time_limited=1） -->
            <el-col v-if="formData.is_time_limited === 1" :span="12">
              <el-form-item label="领取开始时间" prop="receive_start_time">
                <el-date-picker
                  v-model="formData.receive_start_time"
                  type="datetime"
                  placeholder="选择领取开始时间"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>
            <el-col v-if="formData.is_time_limited === 1" :span="12">
              <el-form-item label="领取截止时间" prop="receive_end_time">
                <el-date-picker
                  v-model="formData.receive_end_time"
                  type="datetime"
                  placeholder="选择领取截止时间"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>
          </el-row>
        </el-card>

        <!-- 有效期设置 -->
        <el-card class="form-card mt10" shadow="never">
          <template #header>
            <div class="card-header">
              <span class="card-title">有效期设置</span>
            </div>
          </template>
          <el-row :gutter="24">
            <el-col :span="24">
              <el-form-item label="有效期类型" prop="expire_type">
                <el-radio-group v-model="formData.expire_type" @change="handleExpireTypeChange">
                  <el-radio
                    v-for="item in expireTypes"
                    :key="item.value"
                    :label="item.value"
                  >{{ item.label }}</el-radio>
                </el-radio-group>
              </el-form-item>
            </el-col>

            <!-- 有效天数（expire_type=0） -->
            <el-col v-if="formData.expire_type === 0" :span="12">
              <el-form-item label="有效天数" prop="valid_days">
                <el-input-number
                  v-model="formData.valid_days"
                  :min="1"
                  :max="3650"
                  placeholder="领取后N天内有效"
                  style="width:100%"
                />
                <div class="form-tip">用户领取后，在该天数内使用有效</div>
              </el-form-item>
            </el-col>

            <!-- 固定时间段（expire_type=1） -->
            <el-col v-if="formData.expire_type === 1" :span="12">
              <el-form-item label="有效开始时间" prop="valid_start_time">
                <el-date-picker
                  v-model="formData.valid_start_time"
                  type="datetime"
                  placeholder="选择有效开始时间"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>
            <el-col v-if="formData.expire_type === 1" :span="12">
              <el-form-item label="有效截止时间" prop="valid_end_time">
                <el-date-picker
                  v-model="formData.valid_end_time"
                  type="datetime"
                  placeholder="选择有效截止时间"
                  value-format="yyyy-MM-dd HH:mm:ss"
                  style="width:100%"
                />
              </el-form-item>
            </el-col>
          </el-row>
        </el-card>
      </el-form>

      <!-- 内容选择弹窗（多选） -->
      <content-picker
        v-model:visible="articlePickerVisible"
        type="article"
        :multiple="true"
        :selected-ids="formData.relations"
        @select="onArticleSelect"
      />

      <!-- 专题选择弹窗（多选） -->
      <content-picker
        v-model:visible="specialPickerVisible"
        type="special"
        :multiple="true"
        :selected-ids="formData.relations"
        @select="onSpecialSelect"
      />

      <!-- 底部操作栏 -->
      <div class="form-footer">
        <el-button @click="handleBack">取消</el-button>
        <el-button :loading="submitLoading" @click="handleSubmit(0)">保存并关闭</el-button>
        <el-button type="primary" class="btn-curator-primary" :loading="submitLoading" @click="handleSubmit(1)">保存并启用</el-button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { getCurrentInstance, onBeforeMount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getCouponDetail,
  createCoupon,
  updateCoupon,
  getCouponScopeTypes,
  getCouponExpireTypes,
  getCouponDistributeTypes,
  getCouponRelationTypes
} from '@/api/coupon'
import { getArticleCategoryTree } from '@/api/article'
import { getSpecialCategoryTree } from '@/api/special'
import ContentPicker from '@/components/ContentPicker/index.vue'

defineOptions({
  name: 'CouponCreate'
})

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message

const couponFormRef = ref(null)
const pageLoading = ref(false)
const submitLoading = ref(false)
const isEdit = ref(false)
const couponId = ref(null)
const scopeTypes = ref([])
const expireTypes = ref([])
const distributeTypes = ref([])
const productRelationTypes = ref([])
const categoryRelationTypes = ref([])
const selectedArticles = ref([])
const selectedSpecials = ref([])
const articlePickerVisible = ref(false)
const specialPickerVisible = ref(false)
const categoryOptions = ref([])

const formData = reactive(getDefaultFormData())

const formRules = {
  name: [
    { required: true, message: '请输入优惠券名称', trigger: 'blur' },
    { max: 64, message: '名称最多64个字符', trigger: 'blur' }
  ],
  scope_type: [
    { required: true, message: '请选择适用范围', trigger: 'change' }
  ],
  discount_amount: [
    { required: true, message: '请输入优惠金额', trigger: 'blur' }
  ],
  distribute_type: [
    { required: true, message: '请选择发放类型', trigger: 'change' }
  ],
  expire_type: [
    { required: true, message: '请选择有效期类型', trigger: 'change' }
  ],
  relations: [
    {
      validator: (rule, value, callback) => {
        if (formData.scope_type === 1 && (!Array.isArray(value) || value.length === 0)) {
          callback(new Error('请选择关联内容或专题'))
          return
        }
        if (formData.scope_type === 11 && (!Array.isArray(value) || value.length === 0)) {
          callback(new Error('请选择关联品类'))
          return
        }
        callback()
      },
      trigger: 'change'
    }
  ],
  min_consume_amount: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.distribute_type === 1 && (!value || value <= 0)) {
          callback(new Error('请输入满赠消费金额'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ],
  total_quantity: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.is_quantity_limited === 1 && (!value || value < 1)) {
          callback(new Error('请输入发放总量'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ],
  receive_start_time: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.is_time_limited === 1 && !value) {
          callback(new Error('请选择领取开始时间'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ],
  receive_end_time: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.is_time_limited === 1 && !value) {
          callback(new Error('请选择领取截止时间'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ],
  valid_days: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.expire_type === 0 && (!value || value < 1)) {
          callback(new Error('请输入有效天数'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ],
  valid_start_time: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.expire_type === 1 && !value) {
          callback(new Error('请选择有效开始时间'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ],
  valid_end_time: [
    {
      required: true,
      validator: (rule, value, callback) => {
        if (formData.expire_type === 1 && !value) {
          callback(new Error('请选择有效截止时间'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ]
}

function getDefaultFormData() {
  return {
    name: '',
    discount_amount: null,
    min_order_amount: 0,
    scope_type: 10,
    relation_type: 1,
    distribute_type: 0,
    min_consume_amount: null,
    is_quantity_limited: 0,
    total_quantity: null,
    remain_quantity: null,
    is_time_limited: 0,
    receive_start_time: '',
    receive_end_time: '',
    expire_type: 0,
    valid_days: 30,
    valid_start_time: '',
    valid_end_time: '',
    sort: 1,
    status: 1,
    relations: []
  }
}

function loadEnums() {
  getCouponScopeTypes().then(res => { scopeTypes.value = res.data || [] })
  getCouponExpireTypes().then(res => { expireTypes.value = res.data || [] })
  getCouponDistributeTypes().then(res => { distributeTypes.value = res.data || [] })
  getCouponRelationTypes().then(res => {
    const data = res.data || {}
    productRelationTypes.value = data.product_types || []
    categoryRelationTypes.value = data.category_types || []
  })
}

function loadDetail(id) {
  pageLoading.value = true
  getCouponDetail(id).then(res => {
    const data = res.data || {}
    Object.assign(formData, {
      name: data.name || '',
      discount_amount: data.discount_amount || null,
      min_order_amount: data.min_order_amount || 0,
      scope_type: data.scope_type !== undefined ? data.scope_type : 10,
      relation_type: data.relations && data.relations.length > 0
        ? (data.relations[0].relation_type || 1)
        : 1,
      distribute_type: data.distribute_type !== undefined ? data.distribute_type : 0,
      min_consume_amount: data.min_consume_amount || null,
      is_quantity_limited: data.is_quantity_limited || 0,
      total_quantity: data.total_quantity || null,
      remain_quantity: data.remain_quantity || null,
      is_time_limited: data.is_time_limited || 0,
      receive_start_time: data.receive_start_time || '',
      receive_end_time: data.receive_end_time || '',
      expire_type: data.expire_type !== undefined ? data.expire_type : 0,
      valid_days: data.valid_days || 30,
      valid_start_time: data.valid_start_time || '',
      valid_end_time: data.valid_end_time || '',
      sort: data.sort || 1,
      status: data.status !== undefined ? data.status : 1,
      relations: data.relations ? data.relations.map(item => item.relation_id) : []
    })
    const relationType = formData.relation_type
    if (formData.relations.length > 0) {
      const placeholders = formData.relations.map(id => ({ id, title: 'ID:' + id, cover: '' }))
      if (relationType === 1) {
        selectedArticles.value = placeholders
      } else if (relationType === 2) {
        selectedSpecials.value = placeholders
      }
    }
    if (formData.scope_type === 11) {
      loadCategoryOptions()
    }
  }).finally(() => {
    pageLoading.value = false
  })
}

function handleScopeTypeChange() {
  formData.relations = []
  formData.relation_type = formData.scope_type === 11 ? 4 : 1
  selectedArticles.value = []
  selectedSpecials.value = []
  categoryOptions.value = []
  if (formData.scope_type === 11) {
    loadCategoryOptions()
  }
}

function handleRelationTypeChange() {
  formData.relations = []
  selectedArticles.value = []
  selectedSpecials.value = []
  if (formData.scope_type === 11) {
    loadCategoryOptions()
  }
}

function handleDistributeTypeChange() {
  formData.min_consume_amount = null
}

function handleExpireTypeChange() {
  formData.valid_days = 30
  formData.valid_start_time = ''
  formData.valid_end_time = ''
}

function onArticleSelect(row) {
  if (formData.relations.includes(row.id)) return
  formData.relations.push(row.id)
  selectedArticles.value.push(row)
}

function removeArticle(id) {
  formData.relations = formData.relations.filter(v => v !== id)
  selectedArticles.value = selectedArticles.value.filter(v => v.id !== id)
}

function onSpecialSelect(row) {
  if (formData.relations.includes(row.id)) return
  formData.relations.push(row.id)
  selectedSpecials.value.push(row)
}

function removeSpecial(id) {
  formData.relations = formData.relations.filter(v => v !== id)
  selectedSpecials.value = selectedSpecials.value.filter(v => v.id !== id)
}

function loadCategoryOptions() {
  categoryOptions.value = []
  const request = formData.relation_type === 5
    ? getSpecialCategoryTree()
    : getArticleCategoryTree()

  request.then(res => {
    categoryOptions.value = flattenCategoryOptions(res.data || [])
  }).catch(() => {
    categoryOptions.value = []
  })
}

function flattenCategoryOptions(list, level = 0) {
  if (!Array.isArray(list)) {
    return []
  }
  const options = []
  list.forEach(item => {
    const id = Number(item.id || item.value || 0)
    if (id > 0) {
      options.push({
        id,
        label: `${level > 0 ? '  '.repeat(level) + '- ' : ''}${item.name || item.label || ''}`,
        disabled: Number(item.status) === 0
      })
    }
    if (Array.isArray(item.children) && item.children.length > 0) {
      options.push(...flattenCategoryOptions(item.children, level + 1))
    }
  })
  return options
}

function handleSubmit(status) {
  if (!couponFormRef.value) return
  couponFormRef.value.validate(valid => {
    if (!valid) return
    submitLoading.value = true
    const data = { ...formData, status }
    const request = isEdit.value
      ? updateCoupon(couponId.value, data)
      : createCoupon(data)
    request.then(res => {
      message.success(res.message || '操作成功')
      router.push('/coupon/list')
    }).finally(() => {
      submitLoading.value = false
    })
  })
}

function handleBack() {
  router.push('/coupon/list')
}

onBeforeMount(() => {
  loadEnums()
  const id = route.params.id
  if (id) {
    isEdit.value = true
    couponId.value = id
    loadDetail(id)
  }
})
</script>

<style lang="scss" scoped>
.coupon-create-page {
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

.coupon-create-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-card {
  border-radius: 12px;
  border: 1px solid rgba(var(--ds-surface-low-rgb), 0.8);

  :deep(.el-card__header) {
    padding: 16px 24px;
    border-bottom: 1px solid rgba(var(--ds-surface-low-rgb), 0.8);
    background: rgba(var(--ds-surface-low-rgb), 0.3);
    border-radius: 12px 12px 0 0;
  }

  :deep(.el-card__body) {
    padding: 24px;
  }
}

.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.coupon-form {
  :deep(.el-input-number) {
    width: 100%;

    .el-input__inner {
      text-align: left;
    }
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
}

.form-footer {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 16px 24px;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.selected-table {
  border: 1px solid #ebeef5;
  border-radius: 6px;
  overflow: hidden;

  .selected-cover {
    width: 44px;
    height: 32px;
    border-radius: 3px;
  }
}

.price-text {
  color: #e6a23c;
  font-weight: 600;
  font-size: 13px;
}

.remove-btn {
  color: #f56c6c;
  padding: 0;
}

.no-cover {
  color: #c0c4cc;
  font-size: 12px;
}
</style>
