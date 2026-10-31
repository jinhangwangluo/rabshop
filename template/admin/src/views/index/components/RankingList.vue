<template>
  <el-card shadow="never" class="ranking-list">
    <template #header>
      <div class="ranking-list__header">
        <span class="ranking-list__title">{{ title }}</span>
        <button
          v-if="actionText"
          type="button"
          :class="['ranking-list__action', { 'is-clickable': actionClickable }]"
          @click="handleActionClick"
        >
          {{ actionText }}
        </button>
      </div>
    </template>

    <el-empty v-if="!list.length" :image-size="80" description="暂无排行数据" />

    <div v-else class="ranking-list__body">
      <div class="ranking-list__thead">
        <span class="ranking-list__col ranking-list__col--index">排名</span>
        <span class="ranking-list__col ranking-list__col--name">{{ nameLabel }}</span>
        <span class="ranking-list__col ranking-list__col--value">{{ valueLabel }}</span>
      </div>

      <div
        v-for="(item, index) in list"
        :key="item.id || index"
        :class="['ranking-list__row', { 'is-clickable': clickable }]"
        :role="clickable ? 'button' : null"
        :tabindex="clickable ? 0 : -1"
        @click="handleRowClick(item)"
        @keyup.enter="handleRowClick(item)"
      >
        <div class="ranking-list__col ranking-list__col--index">
          <span :class="indexClass(index)">{{ formatIndex(index) }}</span>
        </div>
        <div class="ranking-list__col ranking-list__col--name ranking-list__name-wrap">
          <div class="ranking-list__name">{{ item.name || '未命名' }}</div>
          <div v-if="showExtra(item)" class="ranking-list__meta">{{ extraLabel }}：{{ formatNumber(item.extra || 0) }}</div>
        </div>
        <div class="ranking-list__col ranking-list__col--value ranking-list__value">{{ formatDisplayValue(item.value || 0) }}</div>
      </div>
    </div>
  </el-card>
</template>

<script setup>
defineOptions({
  name: 'RankingList'
})

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  list: {
    type: Array,
    default: () => []
  },
  unit: {
    type: String,
    default: ''
  },
  extraLabel: {
    type: String,
    default: '关联数据'
  },
  valueLabel: {
    type: String,
    default: '数值'
  },
  nameLabel: {
    type: String,
    default: '名称'
  },
  actionText: {
    type: String,
    default: ''
  },
  clickable: {
    type: Boolean,
    default: false
  },
  actionClickable: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['action-click', 'row-click'])

function formatNumber(value) {
  const number = Number(value || 0)
  return Number.isNaN(number) ? '0' : number.toLocaleString('zh-CN')
}

function formatDisplayValue(value) {
  if (props.unit === '元') return `¥${formatNumber(value)}`
  return `${formatNumber(value)}${props.unit ? ` ${props.unit}` : ''}`
}

function formatIndex(index) {
  return String(index + 1).padStart(2, '0')
}

function showExtra(item) {
  return item && item.extra !== null && item.extra !== undefined && item.extra !== ''
}

function indexClass(index) {
  return {
    'ranking-list__badge': true,
    'is-first': index === 0,
    'is-second': index === 1,
    'is-third': index === 2,
    'is-default': index > 2
  }
}

function handleActionClick() {
  if (!props.actionClickable) return
  emit('action-click')
}

function handleRowClick(item) {
  if (!props.clickable) return
  emit('row-click', item)
}
</script>

<style lang="scss" scoped>
.ranking-list {
  height: 100%;
  border: 1px solid var(--dashboard-border, #e2e2e2);
  border-radius: 12px;
  background: var(--dashboard-surface, #ffffff);
  overflow: hidden;

  :deep(.el-card__header) {
    padding: 14px 16px;
    border-bottom: 1px solid var(--dashboard-border, #e2e2e2);
  }

  :deep(.el-card__body) {
    padding: 0;
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  &__title {
    color: var(--dashboard-text-primary, #1a1c1c);
    font-size: 15px;
    font-weight: 700;
  }

  &__action {
    padding: 0;
    border: none;
    background: transparent;
    color: var(--dashboard-accent, #f34100);
    font-size: 12px;
    font-weight: 700;

    &.is-clickable {
      cursor: pointer;
    }
  }

  &__thead,
  &__row {
    display: grid;
    grid-template-columns: 64px minmax(0, 1fr) 132px;
    gap: 8px;
    align-items: center;
    padding: 0 16px;
  }

  &__thead {
    min-height: 40px;
    background: var(--dashboard-surface-muted, #f3f3f3);
    color: var(--dashboard-text-muted, #908b87);
    font-size: 11px;
    font-weight: 700;
  }

  &__row {
    min-height: 56px;
    border-top: 1px solid var(--dashboard-border-soft, #f0ece8);
    transition: background-color 0.2s ease;

    &.is-clickable {
      cursor: pointer;
    }

    &:hover {
      background: var(--dashboard-surface-muted, #f3f3f3);
    }
  }

  &__col--value {
    text-align: right;
  }

  &__name-wrap {
    min-width: 0;
  }

  &__name {
    color: var(--dashboard-text-primary, #1a1c1c);
    font-size: 14px;
    font-weight: 700;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__meta {
    margin-top: 3px;
    color: var(--dashboard-text-muted, #8f8a86);
    font-size: 11px;
    line-height: 16px;
  }

  &__value {
    color: var(--dashboard-text-primary, #1a1c1c);
    font-size: 14px;
    font-weight: 700;
  }

  &__badge {
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 800;

    &.is-first {
      background: var(--dashboard-accent, #f34100);
      color: #ffffff;
    }

    &.is-second {
      background: rgba(243, 65, 0, 0.12);
      color: var(--dashboard-accent, #f34100);
    }

    &.is-third {
      background: var(--dashboard-surface-muted, #f3f3f3);
      color: var(--dashboard-text-secondary, #6f6965);
    }

    &.is-default {
      color: var(--dashboard-text-muted, #9b9691);
      background: transparent;
    }
  }
}
</style>
