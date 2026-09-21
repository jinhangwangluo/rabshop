<template>
  <i
    v-bind="$attrs"
    :class="iconClasses"
    aria-hidden="true"
  >
    <component :is="iconComponent" v-if="iconComponent" />
  </i>
</template>

<script setup>
import { computed } from 'vue'
import { normalizeLegacyIconClasses, resolveLegacyIcon } from '@/utils/legacyIcons'

defineOptions({
  name: 'LegacyIcon',
  inheritAttrs: false
})

const props = defineProps({
  name: {
    type: [String, Array],
    default: ''
  }
})

const iconComponent = computed(() => resolveLegacyIcon(props.name))
const iconClasses = computed(() => normalizeLegacyIconClasses(props.name))
</script>

<style scoped>
.legacy-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1em;
  height: 1em;
  line-height: 1;
  vertical-align: -0.125em;
  flex-shrink: 0;
}

.legacy-icon > :deep(svg) {
  width: 1em;
  height: 1em;
}
</style>
