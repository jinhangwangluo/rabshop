<template>
  <el-card
    shadow="never"
    :class="['metric-card', `metric-card--${type}`, { 'is-clickable': clickable }]"
    :role="clickable ? 'button' : null"
    :tabindex="clickable ? 0 : -1"
    @click="handleClick"
    @keyup.enter="handleClick"
  >
    <div class="metric-card__head">
      <span class="metric-card__title">{{ title }}</span>
      <span class="metric-card__icon">
        <LegacyIcon :name="icon" />
      </span>
    </div>

    <div class="metric-card__value">{{ formattedValue }}</div>

    <div class="metric-card__foot">
      <span class="metric-card__desc">{{ description }}</span>
      <span
        v-if="showTrend"
        :class="['metric-card__trend', trend >= 0 ? 'is-up' : 'is-down']"
      >
        <LegacyIcon :name="trend >= 0 ? 'el-icon-top' : 'el-icon-bottom'" />
        {{ Math.abs(trend) }}%
      </span>
    </div>
  </el-card>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'MetricCard'
})

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  value: {
    type: [Number, String],
    default: 0
  },
  format: {
    type: String,
    default: 'number'
  },
  description: {
    type: String,
    default: ''
  },
  trend: {
    type: Number,
    default: null
  },
  icon: {
    type: String,
    default: 'el-icon-data-analysis'
  },
  type: {
    type: String,
    default: 'info'
  },
  clickable: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])

const formattedValue = computed(() => {
  const number = Number(props.value || 0)
  const value = Number.isNaN(number) ? 0 : number
  if (props.format === 'currency') {
    return `¥${value.toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`
  }
  return value.toLocaleString('zh-CN')
})

const showTrend = computed(() => props.trend !== null && props.trend !== undefined)

function handleClick() {
  if (!props.clickable) return
  emit('click')
}
</script>

<style lang="scss" scoped>
.metric-card {
  height: 100%;
  min-height: 132px;
  border: 1px solid var(--dashboard-border, #e2e2e2);
  border-radius: 12px;
  background: var(--dashboard-surface, #ffffff);
  transition: border-color 0.2s ease, transform 0.2s ease;

  &.is-clickable {
    cursor: pointer;
  }

  &:hover {
    transform: translateY(-2px);
    border-color: rgba(243, 65, 0, 0.22);
  }

  :deep(.el-card__body) {
    height: 100%;
    padding: 14px 16px 12px;
    display: flex;
    flex-direction: column;
  }

  &__head,
  &__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  &__title {
    color: var(--dashboard-text-secondary, #7a7672);
    font-size: 13px;
    line-height: 18px;
  }

  &__icon {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: rgba(243, 65, 0, 0.24);
    font-size: 16px;
  }

  &__value {
    margin-top: 12px;
    color: var(--dashboard-text-primary, #1a1c1c);
    font-size: 16px;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: 0.01em;
    word-break: break-all;
  }

  &__foot {
    margin-top: auto;
    padding-top: 12px;
  }

  &__desc {
    flex: 1;
    color: var(--dashboard-text-muted, #8f8a86);
    font-size: 10px;
    line-height: 16px;
  }

  &__trend {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 11px;
    font-weight: 700;

    &.is-up {
      color: var(--dashboard-accent, #f34100);
    }

    &.is-down {
      color: var(--dashboard-danger, #ba1a1a);
    }
  }

  &--warning {
    .metric-card__icon {
      color: rgba(243, 65, 0, 0.18);
    }
  }

  &--success {
    .metric-card__trend.is-up {
      color: var(--dashboard-accent, #f34100);
    }
  }
}
</style>
