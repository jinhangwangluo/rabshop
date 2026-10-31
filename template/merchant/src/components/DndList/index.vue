<template>
  <div class="dndList">
    <div :style="{width:width1}" class="dndList-list">
      <h3>{{ list1Title }}</h3>
      <draggable v-model="list1" item-key="id" :set-data="setData" group="article" class="dragArea">
        <template #item="{ element }">
          <div class="list-complete-item">
            <div class="list-complete-item-handle">
              {{ element.id }}[{{ element.author }}] {{ element.title }}
            </div>
            <div style="position:absolute;right:0px;">
              <span style="float: right ;margin-top: -20px;margin-right:5px;" @click="deleteEle(element)">
                <i style="color:#ff4949" class="el-icon-delete" />
              </span>
            </div>
          </div>
        </template>
      </draggable>
    </div>
    <div :style="{width:width2}" class="dndList-list">
      <h3>{{ list2Title }}</h3>
      <draggable v-model="list2" item-key="id" group="article" class="dragArea">
        <template #item="{ element }">
          <div class="list-complete-item">
            <div class="list-complete-item-handle2" @click="pushEle(element)">
              {{ element.id }} [{{ element.author }}] {{ element.title }}
            </div>
          </div>
        </template>
      </draggable>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import draggable from 'vuedraggable'

defineOptions({
  name: 'DndList'
})

const props = defineProps({
  list1: {
    type: Array,
    default() {
      return []
    }
  },
  list2: {
    type: Array,
    default() {
      return []
    }
  },
  list1Title: {
    type: String,
    default: 'list1'
  },
  list2Title: {
    type: String,
    default: 'list2'
  },
  width1: {
    type: String,
    default: '48%'
  },
  width2: {
    type: String,
    default: '48%'
  }
})

const emit = defineEmits(['update:list1', 'update:list2'])

const list1 = computed({
  get: () => props.list1,
  set: value => emit('update:list1', value)
})

const list2 = computed({
  get: () => props.list2,
  set: value => emit('update:list2', value)
})

function isNotInList1(value) {
  return props.list1.every(item => value.id !== item.id)
}

function isNotInList2(value) {
  return props.list2.every(item => value.id !== item.id)
}

function deleteEle(element) {
  emit('update:list1', props.list1.filter(item => item.id !== element.id))
  if (isNotInList2(element)) {
    emit('update:list2', [element, ...props.list2])
  }
}

function pushEle(element) {
  emit('update:list2', props.list2.filter(item => item.id !== element.id))
  if (isNotInList1(element)) {
    emit('update:list1', [...props.list1, element])
  }
}

function setData(dataTransfer) {
  // to avoid Firefox bug
  // Detail see : https://github.com/RubaXa/Sortable/issues/1012
  dataTransfer.setData('Text', '')
}
</script>

<style lang="scss" scoped>
.dndList {
  background: #fff;
  padding-bottom: 40px;
  &:after {
    content: "";
    display: table;
    clear: both;
  }
  .dndList-list {
    float: left;
    padding-bottom: 30px;
    &:first-of-type {
      margin-right: 2%;
    }
    .dragArea {
      margin-top: 15px;
      min-height: 50px;
      padding-bottom: 30px;
    }
  }
}

.list-complete-item {
  cursor: pointer;
  position: relative;
  font-size: 14px;
  padding: 5px 12px;
  margin-top: 4px;
  border: 1px solid #bfcbd9;
  transition: all 1s;
}

.list-complete-item-handle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-right: 50px;
}

.list-complete-item-handle2 {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-right: 20px;
}

.list-complete-item.sortable-chosen {
  background: #4AB7BD;
}

.list-complete-item.sortable-ghost {
  background: #30B08F;
}

.list-complete-enter,
.list-complete-leave-active {
  opacity: 0;
}
</style>
