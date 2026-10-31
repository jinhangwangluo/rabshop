<template>
  <div
    v-if="entries.length"
    class="plugin-slot"
    :data-plugin-slot="name"
  >
    <component
      :is="entry.component"
      v-for="entry in entries"
      :key="`${entry.pluginCode}:${name}`"
      v-bind="entry.props"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { getPluginSlotEntries } from '@/plugins/registry'

defineOptions({ name: 'PluginSlot' })

const props = defineProps({
  name: { type: String, required: true },
  context: { type: Object, default: () => ({}) }
})

const entries = computed(() => getPluginSlotEntries(props.name, props.context))
</script>

<style lang="scss" scoped>
.plugin-slot {
  display: block;
}
</style>
