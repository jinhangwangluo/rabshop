<template>
  <div>
    <!-- 返回按钮 -->
    <el-card shadow="never" class="back-card">
      <div class="header-row">
        <div class="header-left" @click="goBack">
          <LegacyIcon name="el-icon-arrow-left" />
          <span class="pl10">返回直播间列表</span>
        </div>
        <span class="page-title">直播间贡献榜 - {{ roomInfo.title || '未知直播间' }}</span>
        <div class="header-right" />
      </div>
    </el-card>

    <!-- 基本信息 -->
    <el-card v-loading="roomLoading" shadow="never">
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
            <span>主播：{{ roomInfo.anchor_name || '—' }}</span>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 排行榜表格 -->
    <el-card shadow="never" class="mt14">
      <div class="card-header">
        <span class="header-title">本场贡献榜Top {{ rankList.length }}</span>
        <el-button icon="el-icon-refresh" @click="loadRankList">刷新</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="rankList"
        class="mt14"
      >
        <el-table-column label="排名" width="80" align="center">
          <template #default="scope">
            <el-tag
              v-if="scope.$index === 0"
              type="warning"
              effect="dark"
            >🏆 {{ scope.$index + 1 }}</el-tag>
            <el-tag
              v-else-if="scope.$index === 1"
              type="success"
              effect="dark"
            >🥈 {{ scope.$index + 1 }}</el-tag>
            <el-tag
              v-else-if="scope.$index === 2"
              type="primary"

              effect="dark"
            >🥉 {{ scope.$index + 1 }}</el-tag>
            <span v-else>{{ scope.$index + 1 }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户头像" width="80" align="center">
          <template #default="scope">
            <el-avatar :size="40" :src="scope.row.avatar || defaultAvatar" />
          </template>
        </el-table-column>
        <el-table-column prop="nickname" label="用户昵称" min-width="150" show-overflow-tooltip />
        <el-table-column label="等级徽章" width="150" align="center">
          <template #default="scope">
            <div class="level-badge">
              <el-image
                v-if="scope.row.level_icon"
                :src="scope.row.level_icon"
                class="level-icon"
                fit="contain"
              />
              <span v-if="scope.row.level_name" class="level-name">{{ scope.row.level_name }}</span>
              <span v-else class="level-name">暂无等级</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="contribution" label="累计贡献值 (元)" width="140" align="center">
          <template #default="scope">
            <span class="price-text">¥{{ parseFloat(scope.row.contribution).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="gift_count" label="送礼次数" width="90" align="center" />
        <el-table-column prop="create_time" label="首次送礼时间" width="170" align="center" />
        <el-table-column prop="update_time" label="最近送礼时间" width="170" align="center" />
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
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
import { getCurrentInstance, onBeforeMount, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { getRoomRankList, getRoomDetail } from '@/api/live'
import defaultAvatar from '@/assets/images/user.png'

defineOptions({
  name: 'LiveRoomRank'
})

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance() || {}

const loading = ref(false)
const roomLoading = ref(false)
const rankList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const roomId = ref(null)
const roomInfo = ref({})

onBeforeMount(() => {
  roomId.value = route.query.room_id
  if (!roomId.value) {
    proxy?.$message?.error('缺少直播间ID 参数')
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
    proxy?.$message?.error('加载排行榜失败')
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
  router.push('/live/index')
}
</script>

<style scoped lang="scss">
.back-card {
  margin-bottom: 14px;
}
.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-left {
  cursor: pointer;
  color: #409EFF;
  font-size: 14px;
}
.page-title {
  font-size: 16px;
  font-weight: bold;
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
  background-color: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 40px;
  color: #c0c4cc;
}
.room-meta {
  flex: 1;
  margin-left: 20px;
}
.room-title {
  font-size: 18px;
  font-weight: bold;
  margin-bottom: 8px;
}
.room-sub {
  color: #909399;
  font-size: 14px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-title {
  font-size: 16px;
  font-weight: bold;
}
.price-text {
  color: #F56C6C;
  font-weight: bold;
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
  color: #606266;
}
</style>
