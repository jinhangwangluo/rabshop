<template>
  <div class="special-create-container">
    <div class="sticky ivu-mb-16">
      <el-card
        shadow="never"
        class="box-card"
      >
        <div class="acea-row space-between">
          <div class="acea-row row-middle">
            <div
              class="font-sm after-line"
              @click="$router.push('/special/manage')"
            >
              <span class="el-icon-arrow-left" />
              <span class="pl10">返回</span>
            </div>
            <span class="ht_title ml10">{{ isEdit ? '编辑专题' : '添加专题' }}</span>
          </div>
        </div>
      </el-card>
    </div>

    <el-card shadow="never" class="form-card">
      <el-form
        ref="form"
        v-loading="formLoading"
        :model="form"
        :rules="rules"
        label-width="100px"
      >
        <!-- 基础信息 -->
        <div class="section-title">基础信息</div>

        <el-form-item label="专题标题" prop="title">
          <el-input
            v-model="form.title"
            placeholder="请输入专题标题"
            maxlength="100"
            show-word-limit
            class="form-input-lg"
          />
        </el-form-item>

        <el-form-item label="专题封面" prop="cover">
          <Upload
            v-model="form.cover"
            :limit="1"
          />
          <div class="upload-tip">建议尺寸：750×400px，支持JPG/PNG格式</div>
        </el-form-item>

        <el-form-item label="专题简介" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="3"
            placeholder="请输入专题简介"
            maxlength="500"
            show-word-limit
            class="form-input-lg"
          />
        </el-form-item>

        <el-form-item label="关键词" prop="keywords">
          <el-input
            v-model="form.keywords"
            placeholder="请输入关键词，多个关键词用逗号分隔"
            class="form-input-lg"
          />
        </el-form-item>

        <el-form-item label="热门推荐">
          <el-switch
            v-model="form.is_hot"
            :active-value="1"
            :inactive-value="0"
            active-text="是"
            inactive-text="否"
          />
        </el-form-item>

        <el-form-item label="排序">
          <el-input-number
            v-model="form.sort"
            :min="0"
            :max="9999"
            controls-position="right"
          />
          <span class="form-tip">数值越大排序越靠前</span>
        </el-form-item>

        <el-form-item label="显示状态">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">显示</el-radio>
            <el-radio :label="0">隐藏</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 价格设置 -->
        <div class="section-title">价格设置</div>

        <el-form-item label="专题类型" prop="special_type">
          <el-radio-group v-model="form.special_type">
            <el-radio :label="0">免费</el-radio>
            <el-radio :label="1">付费</el-radio>
          </el-radio-group>
        </el-form-item>

        <template v-if="form.special_type === 1">
          <el-form-item label="售价" prop="price">
            <el-input-number
              v-model="form.price"
              :min="0.01"
              :max="99999"
              :precision="2"
              controls-position="right"
              placeholder="请设置售价"
            />
            <span class="form-tip">元</span>
          </el-form-item>

          <el-form-item label="原价">
            <el-input-number
              v-model="form.original_price"
              :min="0"
              :max="99999"
              :precision="2"
              controls-position="right"
              placeholder="原价（可选）"
            />
            <span class="form-tip">元（用于显示划线价，可选）</span>
          </el-form-item>
        </template>

        <!-- 专题内容 -->
        <div class="section-title">
          专题内容
          <span class="section-desc">（共 {{ form.contents.length }} 个内容）</span>
        </div>

        <el-form-item label=" " class="contents-item">
          <!-- 已选内容列表 -->
          <div v-if="form.contents.length > 0" class="contents-list">
            <div
              v-for="(item, index) in form.contents"
              :key="item.article_id"
              class="content-item"
            >
              <div class="content-sort">
                <span class="sort-num">{{ index + 1 }}</span>
                <div class="sort-arrows">
                  <el-button
                    type="text"
                    :icon="Top"
                    :disabled="index === 0"
                    @click="moveContent(index, -1)"
                  />
                  <el-button
                    type="text"
                    :icon="Bottom"
                    :disabled="index === form.contents.length - 1"
                    @click="moveContent(index, 1)"
                  />
                </div>
              </div>
              <div class="content-cover">
                <el-image
                  v-if="item.cover"
                  :src="item.cover"
                  class="content-cover-img"
                  fit="cover"
                />
                <div v-else class="content-cover-placeholder">
                  <LegacyIcon name="el-icon-picture-outline" />
                </div>
              </div>
              <div class="content-info">
                <div class="content-title" :title="item.title">{{ item.title }}</div>
                <div class="content-meta">
                  <el-tag :type="getTypeTagColor(item.type)" size="mini">
                    {{ getTypeName(item.type) }}
                  </el-tag>
                  <span v-if="item.price > 0" class="meta-price">¥{{ item.price }}</span>
                  <span v-else class="meta-free">免费</span>
                </div>
              </div>
              <div class="content-sort-input">
                <el-input-number
                  v-model="item.sort"
                  :min="0"
                  size="mini"
                  controls-position="right"
                  style="width: 90px"
                />
              </div>
              <div class="content-actions">
                <el-button
                  type="text"
                  :icon="Delete"
                  class="remove-btn"
                  @click="removeContent(index)"
                >移除</el-button>
              </div>
            </div>
          </div>

          <div v-else class="contents-empty">
            <LegacyIcon name="el-icon-document" />
            <p>暂未添加内容，点击下方按钮选择内容</p>
          </div>

          <el-button
            type="primary"
            plain
            :icon="Plus"
            class="select-content-btn"
            @click="openContentSelector"
          >选择内容</el-button>
        </el-form-item>

        <!-- 提交按钮 -->
        <el-form-item>
          <el-button
            type="primary"
            :loading="submitLoading"
            @click="handleSubmit"
          >{{ isEdit ? '保存修改' : '立即添加' }}</el-button>
          <el-button @click="$router.push('/special/manage')">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 内容选择器弹窗 -->
    <el-dialog
      v-model="selectorVisible"
      title="选择内容"
      width="900px"
      :close-on-click-modal="false"
      append-to-body
      @open="onSelectorOpen"
      @close="onSelectorClose"
    >
      <!-- 搜索条件 -->
      <div class="selector-search">
        <el-form
          ref="searchForm"
          :model="selectorSearch"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="内容标题">
            <el-input
              v-model="selectorSearch.title"
              placeholder="搜索内容标题"
              clearable
              :prefix-icon="Search"
              class="selector-input"
              @keyup.enter="loadSelectorList"
            />
          </el-form-item>
          <el-form-item label="内容类型">
            <el-select
              v-model="selectorSearch.content_type"
              placeholder="全部类型"
              clearable
              class="selector-select"
            >
              <el-option
                v-for="item in contentTypeOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadSelectorList">搜索</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 内容列表 -->
      <el-table
        ref="selectorTable"
        v-loading="selectorLoading"
        :data="selectorList"

        row-key="id"
        @selection-change="handleSelectorSelection"
      >
        <el-table-column type="selection" width="45" align="center" :selectable="isSelectable" />
        <el-table-column prop="id" label="ID" width="60" align="center" />
        <el-table-column label="封面" width="65" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.cover"
              :src="row.cover"
              class="mini-cover"
              fit="cover"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
        <el-table-column label="类型" width="75" align="center">
          <template #default="{ row }">
            <el-tag :type="getTypeTagColor(row.content_type)" size="mini">
              {{ getTypeName(row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="90" align="center">
          <template #default="{ row }">
            <span v-if="row.price > 0" class="price-text">¥{{ row.price }}</span>
            <el-tag v-else type="success" size="mini">免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag v-if="isAlreadySelected(row.id)" type="info" size="mini">已选</el-tag>
            <el-tag v-else type="success" size="mini">可选</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="acea-row row-right page">
        <el-pagination
          v-model:current-page="selectorPage"
          v-model:page-size="selectorLimit"
          :page-sizes="[10, 20]"
          layout="total, sizes, prev, pager, next"
          :total="selectorTotal"
          @size-change="handleSelectorSizeChange"
          @current-change="handleSelectorPageChange"
        />
      </div>

      <template #footer>
        <span class="selector-tip">已选 {{ tempSelected.length }} 项</span>
        <el-button @click="selectorVisible = false">取消</el-button>
        <el-button type="primary" @click="confirmContentSelection">确认添加</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Bottom, Delete, Plus, Search, Top } from '@element-plus/icons-vue'
import { specialAdd, specialRead, specialSave, searchSpecialContent } from '@/api/special'
import Upload from '@/components/Upload/index'

defineOptions({
  name: 'SpecialCreate'
})

const route = useRoute()
const router = useRouter()

const CONTENT_TYPE_MAP = {
  1: { label: '图文', color: 'primary' },
  2: { label: '文字', color: '' },
  3: { label: '小说', color: 'warning' },
  4: { label: '图片', color: 'success' },
  5: { label: '音频', color: 'danger' },
  6: { label: '视频', color: 'info' }
}

const formRef = ref(null)
const formLoading = ref(false)
const submitLoading = ref(false)
const form = reactive({
  title: '',
  cover: '',
  description: '',
  keywords: '',
  special_type: 0,
  price: 0,
  original_price: 0,
  is_hot: 0,
  sort: 0,
  status: 1,
  contents: []
})
const rules = {
  title: [
    { required: true, message: '请输入专题标题', trigger: 'blur' },
    { min: 1, max: 100, message: '标题长度不超过100个字符', trigger: 'blur' }
  ],
  special_type: [
    { required: true, message: '请选择专题类型', trigger: 'change' }
  ],
  price: [
    {
      validator: (rule, value, callback) => {
        if (form.special_type === 1 && (!value || value <= 0)) {
          callback(new Error('付费专题必须设置售价'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}
const contentTypeOptions = Object.entries(CONTENT_TYPE_MAP).map(([value, info]) => ({
  value: Number(value),
  label: info.label
}))
// 内容选择器
const selectorVisible = ref(false)
const selectorLoading = ref(false)
const selectorList = ref([])
const selectorTotal = ref(0)
const selectorPage = ref(1)
const selectorLimit = ref(10)
const selectorSearch = reactive({
  title: '',
  content_type: ''
})
const tempSelected = ref([])

const isEdit = computed(() => {
  return !!route.query.id
})
const editId = computed(() => {
  return route.query.id ? Number(route.query.id) : null
})

// 加载编辑详情
function loadDetail() {
  formLoading.value = true
  specialRead(editId.value)
    .then(res => {
      const data = res.data
      Object.assign(form, {
        title: data.title || '',
        cover: data.cover || '',
        description: data.description || '',
        keywords: data.keywords || '',
        special_type: Number(data.special_type) || 0,
        price: parseFloat(data.price) || 0,
        original_price: parseFloat(data.original_price) || 0,
        is_hot: Number(data.is_hot) || 0,
        sort: Number(data.sort) || 0,
        status: Number(data.status) !== undefined ? Number(data.status) : 1,
        // 从详情中的 contents 数组提取内容
        contents: (data.contents || []).map((item, index) => ({
          article_id: item.article_id || (item.article && item.article.id),
          title: (item.article && item.article.title) || item.title || '',
          cover: (item.article && item.article.cover) || item.cover || '',
          type: item.type || (item.article && item.article.content_type) || 1,
          price: parseFloat((item.article && item.article.price) || 0),
          sort: item.sort !== undefined ? item.sort : index
        }))
      })
    })
    .finally(() => {
      formLoading.value = false
    })
}

// 提交表单
function handleSubmit() {
  formRef.value.validate(valid => {
    if (!valid) return
    if (form.special_type === 1 && form.price <= 0) {
      ElMessage.error('付费专题必须设置售价大于0')
      return
    }

    submitLoading.value = true
    const submitData = {
      title: form.title,
      cover: form.cover,
      description: form.description,
      keywords: form.keywords,
      special_type: form.special_type,
      price: form.special_type === 1 ? form.price : 0,
      original_price: form.special_type === 1 ? form.original_price : 0,
      is_hot: form.is_hot,
      sort: form.sort,
      status: form.status,
      // 只传后端需要的字段
      contents: form.contents.map((item, index) => ({
        article_id: item.article_id,
        type: item.type,
        sort: item.sort !== undefined ? item.sort : index
      }))
    }

    const request = isEdit.value
      ? specialSave(editId.value, submitData)
      : specialAdd(submitData)

    request
      .then(() => {
        ElMessage.success(isEdit.value ? '修改成功' : '添加成功')
        router.push('/special/manage')
      })
      .finally(() => {
        submitLoading.value = false
      })
  })
}

// ======== 内容管理 ========

// 上移/下移内容
function moveContent(index, direction) {
  const arr = form.contents
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= arr.length) return
  const temp = arr[index]
  arr[index] = arr[targetIndex]
  arr[targetIndex] = temp
  // 重置 sort 值
  arr.forEach((item, i) => { item.sort = i })
}

// 移除内容
function removeContent(index) {
  form.contents.splice(index, 1)
  // 重置 sort 值
  form.contents.forEach((item, i) => { item.sort = i })
}

// ======== 内容选择器 ========

// 打开选择器
function openContentSelector() {
  selectorVisible.value = true
}

// 弹窗打开时加载列表
function onSelectorOpen() {
  Object.assign(selectorSearch, { title: '', content_type: '' })
  selectorPage.value = 1
  tempSelected.value = []
  loadSelectorList()
}

// 弹窗关闭清空
function onSelectorClose() {
  tempSelected.value = []
  selectorList.value = []
}

// 加载选择器内容列表
function loadSelectorList() {
  selectorLoading.value = true
  const params = {
    page: selectorPage.value,
    limit: selectorLimit.value
  }
  if (selectorSearch.title) params.title = selectorSearch.title
  if (selectorSearch.content_type !== '') params.content_type = selectorSearch.content_type

  searchSpecialContent(params)
    .then(res => {
      selectorList.value = res.data.list || []
      selectorTotal.value = res.data.count || 0
    })
    .finally(() => {
      selectorLoading.value = false
    })
}

// 选择器分页
function handleSelectorSizeChange(val) {
  selectorLimit.value = val
  selectorPage.value = 1
  loadSelectorList()
}
function handleSelectorPageChange(val) {
  selectorPage.value = val
  loadSelectorList()
}

// 表格多选
function handleSelectorSelection(selection) {
  tempSelected.value = selection
}

// 判断是否已选择（用于表格行禁用和状态标记）
function isAlreadySelected(articleId) {
  return form.contents.some(item => item.article_id === articleId)
}

// 表格行是否可选（已选的行不可再选）
function isSelectable(row) {
  return !isAlreadySelected(row.id)
}

// 确认选择
function confirmContentSelection() {
  if (tempSelected.value.length === 0) {
    ElMessage.warning('请先选择内容')
    return
  }
  const currentIds = new Set(form.contents.map(item => item.article_id))
  const baseSort = form.contents.length
  let addCount = 0
  tempSelected.value.forEach((article, index) => {
    if (!currentIds.has(article.id)) {
      form.contents.push({
        article_id: article.id,
        title: article.title || '',
        cover: article.cover || '',
        type: article.content_type || 1,
        price: parseFloat(article.price) || 0,
        sort: baseSort + index
      })
      addCount++
    }
  })
  if (addCount > 0) {
    ElMessage.success(`已添加 ${addCount} 个内容`)
    selectorVisible.value = false
  } else {
    ElMessage.info('所选内容均已在专题中')
  }
}

// 内容类型标签颜色
function getTypeTagColor(type) {
  return (CONTENT_TYPE_MAP[type] || {}).color || ''
}
function getTypeName(type) {
  return (CONTENT_TYPE_MAP[type] || {}).label || '未知'
}

if (isEdit.value) {
  loadDetail()
}
</script>

<style lang="scss" scoped>
.special-create-container {
  min-height: 100%;

  .form-card {
    border-radius: 8px;

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 16px;
      font-weight: 600;
    }

    .section-title {
      font-size: 14px;
      font-weight: 600;
      color: #303133;
      padding: 12px 0 16px;
      border-bottom: 1px solid #ebeef5;
      margin-bottom: 20px;

      .section-desc {
        font-size: 13px;
        font-weight: normal;
        color: #909399;
        margin-left: 8px;
      }
    }

    .form-input-lg {
      width: 500px;
    }

    .upload-tip {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }

    .form-tip {
      margin-left: 8px;
      font-size: 12px;
      color: #909399;
    }

    // 内容列表区域
    .contents-item {
      ::v-deep .el-form-item__content {
        margin-left: 0 !important;
      }
    }

    .contents-list {
      border: 1px solid #ebeef5;
      border-radius: 6px;
      margin-bottom: 12px;
      overflow: hidden;

      .content-item {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        border-bottom: 1px solid #f2f6fc;
        background: #fff;
        transition: background 0.2s;

        &:last-child {
          border-bottom: none;
        }

        &:hover {
          background: #f5f7fa;
        }

        .content-sort {
          display: flex;
          flex-direction: column;
          align-items: center;
          width: 40px;
          flex-shrink: 0;

          .sort-num {
            font-size: 12px;
            color: #909399;
            line-height: 1;
            margin-bottom: 2px;
          }

          .sort-arrows {
            display: flex;
            flex-direction: column;

            .el-button {
              padding: 0;
              //height: 16px;
              //line-height: 16px;
              //font-size: 12px;
              margin: 0;
            }
          }
        }

        .content-cover {
          width: 56px;
          height: 42px;
          margin: 0 10px;
          flex-shrink: 0;

          .content-cover-img {
            width: 56px;
            height: 42px;
            border-radius: 4px;
            display: block;
          }

          .content-cover-placeholder {
            width: 56px;
            height: 42px;
            border-radius: 4px;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c0c4cc;
            font-size: 20px;
          }
        }

        .content-info {
          flex: 1;
          min-width: 0;
          margin-right: 10px;

          .content-title {
            font-size: 13px;
            color: #303133;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 4px;
          }

          .content-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;

            .meta-price {
              color: var(--text-price);
              font-weight: 600;
            }
            .meta-free {
              color: #67c23a;
            }
          }
        }

        .content-sort-input {
          margin-right: 10px;
          flex-shrink: 0;
        }

        .content-actions {
          flex-shrink: 0;

          .remove-btn {
            color: #f56c6c;
            font-size: 12px;
          }
        }
      }
    }

    .contents-empty {
      border: 1px dashed #dcdfe6;
      border-radius: 6px;
      padding: 30px;
      text-align: center;
      color: #c0c4cc;
      margin-bottom: 12px;

      i {
        font-size: 36px;
        display: block;
        margin-bottom: 8px;
      }

      p {
        margin: 0;
        font-size: 13px;
      }
    }

    .select-content-btn {
      margin-top: 0;
    }
  }

  // 内容选择器弹窗
  .selector-search {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;

    .selector-input {
      width: 240px;
    }
    .selector-select {
      width: 150px;
    }
  }

  .selector-pagination {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
  }

  .mini-cover {
    width: 40px;
    height: 32px;
    border-radius: 3px;
    display: block;
    margin: 0 auto;
  }

  .price-text {
    color: var(--text-price);
    font-weight: 600;
  }
}

.el-dialog__footer {

  .selector-tip {
    flex: 1;
    font-size: 13px;
    color: #909399;
    padding-right: 10px;
  }
}
</style>
