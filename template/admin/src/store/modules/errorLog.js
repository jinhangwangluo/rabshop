import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useErrorLogStore = defineStore('errorLog', () => {
  const logs = ref([])

  function addErrorLog(log) {
    logs.value.push(log)
  }

  function clearErrorLog() {
    logs.value.splice(0)
  }

  return {
    logs,
    addErrorLog,
    clearErrorLog
  }
})

export default useErrorLogStore
