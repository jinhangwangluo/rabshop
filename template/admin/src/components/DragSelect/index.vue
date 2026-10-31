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
    required: true
  }
})

const emit = defineEmits(['update:modelValue'])
const dragSelect = ref(null)
let sortable = null

const selectVal = computed({
  get() {
    return [...props.modelValue]
  },
  set(val) {
    emit('update:modelValue', [...val])
  }
})

function setSort() {
  const el = dragSelect.value && dragSelect.value.$el && dragSelect.value.$el.querySelectorAll('.el-select__tags > span')[0]
  if (!el) return
  sortable = Sortable.create(el, {
    ghostClass: 'sortable-ghost',
    setData(dataTransfer) {
      dataTransfer.setData('Text', '')
      // to avoid Firefox bug
      // Detail see : https://github.com/RubaXa/Sortable/issues/1012
    },
    onEnd(evt) {
      const nextValue = [...props.modelValue]
      const targetRow = nextValue.splice(evt.oldIndex, 1)[0]
      nextValue.splice(evt.newIndex, 0, targetRow)
      emit('update:modelValue', nextValue)
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
