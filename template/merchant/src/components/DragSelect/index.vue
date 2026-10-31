<template>
  <el-select ref="dragSelect" v-model="selectVal" v-bind="$attrs" class="drag-select" multiple>
    <slot />
  </el-select>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import Sortable from 'sortablejs'

defineOptions({
  name: 'DragSelect'
})

const props = defineProps({
  modelValue: {
    type: Array,
    default: undefined
  },
  value: {
    type: Array,
    default: undefined
  }
})

const emit = defineEmits(['update:modelValue', 'input'])
const dragSelect = ref(null)
let sortable = null

const currentValue = computed(() => Array.isArray(props.modelValue) ? props.modelValue : props.value || [])
const selectVal = computed({
  get() {
    return [...currentValue.value]
  },
  set(val) {
    const nextValue = [...val]
    emit('update:modelValue', nextValue)
    emit('input', nextValue)
  }
})

function setSort() {
  const el = dragSelect.value && dragSelect.value.$el && dragSelect.value.$el.querySelectorAll('.el-select__tags > span')[0]
  if (!el) {
    return
  }
  sortable = Sortable.create(el, {
    ghostClass: 'sortable-ghost',
    setData(dataTransfer) {
      dataTransfer.setData('Text', '')
      // to avoid Firefox bug
      // Detail see : https://github.com/RubaXa/Sortable/issues/1012
    },
    onEnd(evt) {
      const nextValue = [...currentValue.value]
      const targetRow = nextValue.splice(evt.oldIndex, 1)[0]
      nextValue.splice(evt.newIndex, 0, targetRow)
      emit('update:modelValue', nextValue)
      emit('input', nextValue)
    }
  })
}

onMounted(() => {
  nextTick(() => {
    setSort()
  })
})

onBeforeUnmount(() => {
  if (sortable) {
    sortable.destroy()
    sortable = null
  }
})
</script>

<style lang="scss" scoped>
.drag-select {
  :deep(.sortable-ghost) {
    opacity: .8;
    color: #fff !important;
    background: #42b983 !important;
  }

  :deep(.el-tag) {
    cursor: pointer;
  }
}
</style>
