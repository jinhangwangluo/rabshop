<template>
  <div class="kb-detail">
    <!-- 左侧导航 -->
    <div class="kb-sidebar">
      <div class="kb-back" @click="router.push({ name: 'CsKnowledge' })">
        <LegacyIcon name="el-icon-arrow-left" />
        <span>返回列表</span>
      </div>
      <div v-loading="baseLoading" class="kb-sidebar-info">
        <div class="kb-cover-mini">
          <img v-if="base.cover" :src="base.cover" class="kb-cover-mini-img">
          <div v-else class="kb-cover-mini-placeholder"><LegacyIcon name="el-icon-collection" /></div>
        </div>
        <div class="kb-sidebar-name" :title="base.name">{{ base.name || '...' }}</div>
      </div>

      <div class="kb-nav">
        <div
          v-for="item in navItems"
          :key="item.key"
          :class="['kb-nav-item', { active: activeMenu === item.key }]"
          @click="activeMenu = item.key"
        >
          <LegacyIcon :name="item.icon" />
          <span>{{ item.label }}</span>
        </div>
      </div>
    </div>

    <!-- 右侧内容 -->
    <div class="kb-main">
      <knowledge-content-panel
        v-if="activeMenu === 'content'"
        :knowledge-base-id="baseId"
      />
      <knowledge-segment-panel
        v-else-if="activeMenu === 'segment'"
        :knowledge-base-id="baseId"
      />
      <knowledge-qa-panel
        v-else-if="activeMenu === 'qa'"
        :knowledge-base-id="baseId"
      />
      <knowledge-test-reply
        v-else-if="activeMenu === 'test'"
        :knowledge-base-id="baseId"
        :base-info="base"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeMount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getCsKnowledgeBase } from '@/api/cs'
import KnowledgeContentPanel from './KnowledgeContentPanel.vue'
import KnowledgeSegmentPanel from './KnowledgeSegmentPanel.vue'
import KnowledgeQaPanel from './KnowledgeQaPanel.vue'
import KnowledgeTestReply from './KnowledgeTestReply.vue'

const NAV_ITEMS = [
  { key: 'content', icon: 'el-icon-document', label: '知识库内容' },
  { key: 'segment', icon: 'el-icon-s-grid', label: '分段管理' },
  { key: 'qa', icon: 'el-icon-chat-dot-round', label: '问答设置' },
  { key: 'test', icon: 'el-icon-magic-stick', label: '测试回复' }
]

defineOptions({
  name: 'KnowledgeBaseDetail'
})

const route = useRoute()
const router = useRouter()
const baseLoading = ref(false)
const base = ref({})
const activeMenu = ref('content')
const navItems = NAV_ITEMS
const baseId = computed(() => Number(route.params.id || 0))

async function fetchBase() {
  if (!baseId.value) return
  baseLoading.value = true
  try {
    const res = await getCsKnowledgeBase(baseId.value)
    base.value = (res && res.data && res.data.data) ? res.data.data : (res.data || {})
  } finally {
    baseLoading.value = false
  }
}

onBeforeMount(() => {
  if (route.query.menu) {
    activeMenu.value = route.query.menu
  }
  fetchBase()
})
</script>

<style lang="scss" scoped>
.kb-detail {
  display: flex;
  height: 100%;
  min-height: calc(100vh - 120px);
  background: var(--dashboard-bg);
  gap: 0;
  color: var(--dashboard-text-primary);
}

/* 左侧导航 */
.kb-sidebar {
  width: 200px;
  flex-shrink: 0;
  background: var(--dashboard-surface);
  border-right: 1px solid var(--dashboard-border);
  display: flex;
  flex-direction: column;
  padding-bottom: 24px;
}

.kb-back {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 16px 18px;
  font-size: 13px;
  color: var(--dashboard-text-secondary);
  cursor: pointer;
  border-bottom: 1px solid var(--dashboard-border);
  transition: color 0.2s;

  &:hover {
    color: var(--dashboard-accent);
  }
}

.kb-sidebar-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px 16px 16px;
  border-bottom: 1px solid var(--dashboard-border-soft);
}

.kb-cover-mini {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 10px;
  flex-shrink: 0;
}

.kb-cover-mini-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.kb-cover-mini-placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, var(--dashboard-accent-outline) 0%, var(--dashboard-accent-light) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  color: var(--dashboard-accent);
}

.kb-sidebar-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--dashboard-text-primary);
  text-align: center;
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kb-nav {
  flex: 1;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.kb-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 14px;
  color: var(--dashboard-text-muted);
  cursor: pointer;
  transition: all 0.2s ease;

  i {
    font-size: 16px;
  }

  &:hover {
    background: var(--dashboard-surface-muted);
    color: var(--dashboard-accent);
  }

  &.active {
    background: var(--dashboard-accent-outline);
    color: var(--dashboard-accent);
    font-weight: 500;
    border-left: 3px solid var(--dashboard-accent);
    padding-left: 11px;
  }
}

/* 右侧内容 */
.kb-main {
  flex: 1;
  min-width: 0;
  overflow: auto;
  background: var(--dashboard-bg);
}
</style>
