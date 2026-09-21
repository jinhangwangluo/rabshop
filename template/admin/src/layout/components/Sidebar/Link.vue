<template>
  <component :is="type" v-bind="linkProps(to)">
    <slot />
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { isExternal as isExternalPath } from '@/utils/validate'

const { to } = defineProps({
  to: {
    type: String,
    required: true
  }
})

const isExternal = computed(() => isExternalPath(to))

const type = computed(() => (isExternal.value ? 'a' : 'router-link'))

function linkProps(target = to) {
  if (isExternal.value) {
    return {
      href: target,
      target: '_blank',
      rel: 'noopener'
    }
  }
  return {
    to: target
  }
}
</script>
