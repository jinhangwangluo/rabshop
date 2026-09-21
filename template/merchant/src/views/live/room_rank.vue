<template>
  <div class="room-rank-curator-page">
    <header class="page-curator-header page-curator-header--compact">
      <div class="page-curator-header__text">
        <div class="page-curator-back" @click="goBack">
          <LegacyIcon name="el-icon-arrow-left" />
          <span>返回直播间列表</span>
        </div>
        <h1 class="page-curator-title">直播间贡献榜</h1>
        <p class="page-curator-subtitle">
          查看当前直播间的用户贡献排行与送礼活跃情况。{{ roomInfo.title ? `当前直播间：${roomInfo.title}` : '' }}
        </p>
      </div>
    </header>

    <el-card v-loading="roomLoading" shadow="never" class="room-brief-surface">
      <div class="room-brief">
        <el-image
          v-if="roomInfo.cover"
          :src="roomInfo.cover"
          class="room-cover"
          fit="cover"
          :preview-src-list="[roomInfo.cover]"
        />
        <div v-else class="room-cover-placeholder"><LegacyIcon name="el-icon-video-camera-solid" /></div>
        <div class="room-meta">
          <div class="room-title">{{ roomInfo.title || '—' }}</div>
          <div class="room-sub">
            <span>主播：{{ roomInfo.anchor_name || (roomInfo.anchor && roomInfo.anchor.name) || '—' }}</span>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 排行榜表格 -->
    <el-card shadow="never" class="room-rank-table-surface mt14">
      <div class="card-header">
        <span class="header-title">本场贡献榜Top {{ rankList.length }}</span>
        <el-button :icon="Refresh" @click="loadRankList">刷新</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="rankList"
        class="mt14"
      >
        <el-table-column label="排名" width="80" align="center">
          <template #default="{ $index }">
            <el-tag
              v-if="$index === 0"
              type="warning"

              effect="dark"
            >🏆 {{ $index + 1 }}</el-tag>
            <el-tag
              v-else-if="$index === 1"
              type="success"

              effect="dark"
            >🥈 {{ $index + 1 }}</el-tag>
            <el-tag
              v-else-if="$index === 2"
              type="primary"

              effect="dark"
            >🥉 {{ $index + 1 }}</el-tag>
            <span v-else>{{ $index + 1 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户头像" width="80" align="center">
          <template #default="{ row }">
            <el-avatar :size="40" :src="row.avatar || defaultAvatar" />
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="用户昵称" min-width="150" show-overflow-tooltip />
        <el-table-column label="等级徽章" width="150" align="center">
          <template #default="{ row }">
            <div class="level-badge">
              <el-image
                v-if="row.level_icon"
                :src="row.level_icon"
                class="level-icon"
                fit="contain"
              />
              <span v-if="row.level_name" class="level-name">{{ row.level_name }}</span>
              <span v-else class="level-name">暂无等级</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="contribution" label="累计贡献值 (元)" width="140" align="center">
          <template #default="{ row }">
            <span class="price-text">¥{{ parseFloat(row.contribution).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="gift_count" label="送礼次数" width="90" align="center" />
        <el-table-column prop="create_time" label="首次送礼时间" width="170" align="center" />
        <el-table-column prop="update_time" label="最近送礼时间" width="170" align="center" />
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="limit"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { getRoomRankList, getRoomDetail } from '@/api/live'
import defaultAvatar from '@/assets/images/user.png'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

defineOptions({
  name: 'MerchantLiveRoomRank'
})

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const roomLoading = ref(false)
const rankList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const roomId = ref(null)
const roomInfo = ref({})

onMounted(() => {
  roomId.value = route.query.room_id
  if (!roomId.value) {
    ElMessage.error('缺少直播间ID 参数')
    return
  }
  loadRoomInfo()
  loadRankList()
})

async function loadRoomInfo() {
  roomLoading.value = true
  try {
    const res = await getRoomDetail(roomId.value)
    roomInfo.value = res.data || {}
  } catch (error) {
    console.error(error)
  } finally {
    roomLoading.value = false
  }
}

async function loadRankList() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: limit.value
    }
    const res = await getRoomRankList(roomId.value, params)
    rankList.value = res.data.list || []
    total.value = res.data.total || 0
  } catch (error) {
    console.error(error)
    ElMessage.error('加载排行榜失败')
  } finally {
    loading.value = false
  }
}

function handleSizeChange(val) {
  limit.value = val
  loadRankList()
}

function handleCurrentChange(val) {
  page.value = val
  loadRankList()
}

function goBack() {
  router.push({ name: 'MerchantLiveRoomList' })
}
</script>

<style scoped lang="scss">
.room-rank-curator-page {
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

.page-curator-header--compact {
  margin-bottom: 16px;
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

.page-curator-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
  color: var(--ds-primary);
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;

  &:hover {
    color: var(--ds-primary-hover);
  }
}

.room-brief-surface,
.room-rank-table-surface {
  border-radius: 12px;
  border-color: var(--ds-outline-ghost);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.mt14 {
  margin-top: 14px;
}

.room-brief {
  display: flex;
  align-items: flex-start;
}

.room-cover,
.room-cover-placeholder {
  width: 120px;
  height: 120px;
  border-radius: 8px;
  object-fit: cover;
  background-color: var(--ds-surface-low);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40px;
  color: var(--text-placeholder);
}

.room-meta {
  flex: 1;
  margin-left: 20px;
}

.room-title {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 8px;
  color: var(--ds-on-surface);
}

.room-sub {
  color: var(--text-secondary);
  font-size: 14px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.room-rank-table-surface {
  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }

  :deep(.el-pager li.active) {
    background: var(--ds-primary);
    color: var(--ds-on-primary);
    border-radius: 4px;
  }
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.price-text {
  color: var(--text-price);
  font-weight: 600;
}

.level-badge {
  display: flex;
  align-items: center;
  justify-content: center;
}

.level-icon {
  width: 30px;
  height: 30px;
  margin-right: 8px;
}

.level-name {
  font-size: 13px;
  color: var(--text-secondary);
}
</style>
