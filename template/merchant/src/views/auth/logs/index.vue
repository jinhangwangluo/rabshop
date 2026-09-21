<template>
  <div class="auth-logs-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">操作日志</h1>
        <p class="page-curator-subtitle">
          查看后台管理员的访问轨迹、操作时间与请求路径，便于排查权限与行为问题。
        </p>
      </div>
    </header>

    <div class="auth-logs-work-surface">
      <el-card
        :bordered="false"
        shadow="never"
        class="search-card"
        :body-style="{padding:0}"
      >
        <div>
          <el-form
            ref="formValidate"
            inline
            class="tabform"
            @submit.prevent
          >
            <el-form-item :label="fromList.title + '：'">
              <el-date-picker
                v-model="timeVal"
                clearable
                :editable="false"
                format="yyyy/MM/dd HH:mm:ss"
                type="datetimerange"
                value-format="yyyy/MM/dd HH:mm:ss"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                :default-time="['00:00:00', '23:59:59']"
                @change="onchangeTime"
              />
            </el-form-item>
            <el-form-item label="管理员：">
              <el-select
                v-model="formValidate.user_id"
                multiple
                clearable
                class="form_content_width"
                @change="userSearchs"
              >
                <el-option
                  v-for="item in dataList"
                  :key="item.value"
                  :value="item.value"
                  :label="item.label"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="链接：">
              <el-input
                v-model="formValidate.path"
                placeholder="请输入链接"
                class="form_content_width"
                clearable
              />
            </el-form-item>
            <el-form-item>
              <el-button
                type="primary"
                class="btn-curator-primary userSearch"
                @click="userSearchs"
              >
                搜索
              </el-button>
            </el-form-item>
          </el-form>
        </div>
      </el-card>
      <el-card
        :bordered="false"
        shadow="never"
        class="table-card"
      >
        <el-table
          ref="selection"
          v-loading="loading"
          :data="tabList"
          class="auth-logs-curator-table"
          empty-text="暂无数据"
          highlight-current-row
        >
          <el-table-column
            prop="id"
            label="ID"
            width="80"
          />
          <el-table-column
            label="ID/名称"
            min-width="100"
          >
            <template #default="{ row }">
              <span>{{ row.admin_id + ' / ' + row.adminUser.account }}</span>
            </template>
          </el-table-column>
          <el-table-column
            prop="path"
            label="链接"
            min-width="100"
          />
          <el-table-column
            prop="ip"
            label="操作ip"
            min-width="100"
          />
          <el-table-column
            prop="create_time"
            label="操作时间"
            min-width="100"
          />
        </el-table>
        <div class="acea-row row-right page">
          <el-pagination
            v-if="total"
            v-model:current-page="formValidate.page"
            class="auth-logs-curator-pagination"
            :total="total"
            :page-size="formValidate.limit"
            @current-change="currentChange"
          />
        </div>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { searchAdminApi, systemListApi } from '@/api/system';
import { onMounted, reactive, ref } from 'vue';
import { ElMessage } from 'element-plus';

defineOptions({
  name: 'SystemLog'
});

const fromList = {
  title: '选择时间',
  custom: true,
  fromTxt: [
    { text: '全部', val: '' },
    { text: '今天', val: 'today' },
    { text: '昨天', val: 'yesterday' },
    { text: '最近7天', val: 'lately7' },
    { text: '最近30天', val: 'lately30' },
    { text: '本月', val: 'month' },
    { text: '本年', val: 'year' }
  ]
};
const timeVal = ref([]);
const formValidate = reactive({
  limit: 20,
  page: 1,
  method: '',
  created_at: '',
  path: '',
  user_id: []
});
const loading = ref(false);
const tabList = ref([]);
const total = ref(0);
const dataList = ref([]);

onMounted(() => {
  getSearchAdmin();
  getList();
});

function currentChange(page) {
  formValidate.page = page
  getList()
}

// 具体日期
function onchangeTime(e) {
  timeVal.value = e;
  formValidate.created_at = timeVal.value ? timeVal.value.join('-') : '';
  formValidate.page = 1;
  getList();
}

// 选择时间
function selectChange(tab) {
  formValidate.created_at = tab;
  timeVal.value = [];
  formValidate.page = 1;
  getList();
}

// 搜索条件
function getSearchAdmin() {
  searchAdminApi()
    .then(async(res) => {
      dataList.value = res.data;
    })
    .catch((res) => {
      ElMessage.error(res.message);
    });
}

// 列表
function getList() {
  loading.value = true;
  systemListApi(formValidate)
    .then(async(res) => {
      const data = res.data;
      tabList.value = data.list;
      total.value = data.count;
      loading.value = false;
    })
    .catch((res) => {
      loading.value = false;
      ElMessage.error(res.message);
    });
}

// 搜索
function userSearchs() {
  formValidate.page = 1;
  getList();
}
</script>

<style scoped lang="scss">
.auth-logs-curator-page {
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
  max-width: 620px;
}

.auth-logs-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-card,
.table-card {
  border: none;
  background: transparent;

  :deep(.el-card__body) {
    padding: 0;
  }
}

.search-card {
  margin-bottom: 8px;
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px var(--brand-shadow);
}

.auth-logs-curator-table {
  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }
}

.auth-logs-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next:hover),
  :deep(.btn-prev:hover),
  :deep(.el-pager li:hover) {
    color: var(--ds-primary);
  }
}
</style>
