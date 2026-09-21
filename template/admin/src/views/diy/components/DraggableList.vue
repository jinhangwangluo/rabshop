<template>
  <draggable
    v-model="localList"
    item-key="id"
    v-bind="dragOptions"
    class="draggable-list"
    :class="{ 'is-disabled': disabled }"
    @end="handleDragEnd"
  >
    <template #item="{ element, index }">
      <slot :element="element" :index="index" />
    </template>
  </draggable>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import draggable from 'vuedraggable'

defineOptions({
  name: 'DraggableList'
})

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  disabled: {
    type: Boolean,
    default: false
  },
  group: {
    type: String,
    default: 'components'
  },
  animation: {
    type: Number,
    default: 200
  }
})

const emit = defineEmits(['update:modelValue', 'sort'])
const { modelValue, disabled, group, animation } = toRefs(props)

const localList = computed({
  get: () => modelValue.value,
  set: value => emit('update:modelValue', value)
})

const dragOptions = computed(() => ({
  animation: animation.value,
  group: group.value,
  ghostClass: 'ghost',
  disabled: disabled.value,
  handle: '.preview-component'
}))

function handleDragEnd() {
  emit('sort', localList.value)
}
</script>

<style lang="scss">
.draggable-list {
  min-height: 50px;

  &.is-disabled {
    pointer-events: none;
  }

  .ghost {
    opacity: 0.4;
    background: var(--prev-color-primary-light-9);
    border: 2px dashed var(--prev-color-primary);
  }
}
</style>
