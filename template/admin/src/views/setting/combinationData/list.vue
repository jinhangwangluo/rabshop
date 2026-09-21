<template>
  <div class="combination-data-list-page">
    <CombinationDataListPanel
      :config-id="configId"
      :category-id="categoryId"
      :back-route="{ name: 'CombinationData' }"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CombinationDataListPanel from '@/components/CombinationDataListPanel'

defineOptions({
  name: 'CombinationDataList'
})

const route = useRoute()

const configId = computed(() => Number(route.query.config_id || 0))
const categoryId = computed(() => Number(route.query.category_id || 0))
</script>
