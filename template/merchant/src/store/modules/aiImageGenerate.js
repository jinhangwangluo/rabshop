import localStorage from '@/utils/store/localStorage'
import { defineStore } from 'pinia'
import { ref } from 'vue'

const pinnedKey = 'rabbit-MerchantAiImageGeneratePinned'

export const useAiImageGenerateStore = defineStore('aiImageGenerate', () => {
  const pinned = ref(localStorage.has(pinnedKey) ? Boolean(localStorage.get(pinnedKey)) : false)
  const visible = ref(false)

  function setPinned(value) {
    pinned.value = Boolean(value)
    localStorage.set(pinnedKey, pinned.value)
  }

  function open() {
    visible.value = true
  }

  function close() {
    visible.value = false
  }

  function setVisible(value) {
    visible.value = Boolean(value)
  }

  return {
    pinned,
    visible,
    setPinned,
    open,
    close,
    setVisible
  }
})

export default useAiImageGenerateStore
