<template>
  <view
    v-if="entries.length"
    class="plugin-slot"
    :data-plugin-slot="name"
  >
    <GeneratedPluginSlotRenderer
      v-for="entry in entries"
      :key="entry.key"
      :component-id="entry.componentId"
      :component-props="entry.props"
    />
  </view>
</template>

<script setup>
import { computed } from 'vue'
import { getPluginSlotEntries } from '@/plugins/registry'
import GeneratedPluginSlotRenderer from './generated.vue'

defineOptions({ name: 'PluginSlot' })

const props = defineProps({
  name: { type: String, required: true },
  context: { type: Object, default: () => ({}) }
})

const entries = computed(() => getPluginSlotEntries(props.name, props.context))
</script>

<style scoped>
.plugin-slot {
  display: block;
}
</style>
