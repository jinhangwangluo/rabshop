import { defineStore } from 'pinia'
import { nextTick, ref } from 'vue'

export const useTagsViewStore = defineStore('tagsView', () => {
  const visitedViews = ref([])
  const cachedViews = ref([])
  // 刷新计数器：AppMain 用它参与页面 key，递增即触发当前页面组件重建
  const refreshTick = ref(0)

  function addVisitedView(view) {
    if (visitedViews.value.some(v => v.path === view.path)) return
    visitedViews.value.push(
      Object.assign({}, view, {
        title: view.meta.title || 'no-name'
      })
    )
  }

  function addCachedView(view) {
    if (cachedViews.value.includes(view.name)) return
    if (!view.meta.noCache) {
      cachedViews.value.push(view.name)
    }
  }

  function delVisitedView(view) {
    for (const [i, v] of visitedViews.value.entries()) {
      if (v.path === view.path) {
        visitedViews.value.splice(i, 1)
        break
      }
    }
  }

  function delCachedView(view) {
    const index = cachedViews.value.indexOf(view.name)
    index > -1 && cachedViews.value.splice(index, 1)
  }

  function delOthersVisitedViews(view) {
    visitedViews.value = visitedViews.value.filter(v => {
      return v.meta.affix || v.path === view.path
    })
  }

  function delOthersCachedViews(view) {
    const index = cachedViews.value.indexOf(view.name)
    if (index > -1) {
      cachedViews.value = cachedViews.value.slice(index, index + 1)
    } else {
      // if index = -1, there is no cached tags
      cachedViews.value = []
    }
  }

  function delAllVisitedViews() {
    // keep affix tags
    visitedViews.value = visitedViews.value.filter(tag => tag.meta.affix)
  }

  function delAllCachedViews() {
    cachedViews.value = []
  }

  function updateVisitedView(view) {
    for (const visitedView of visitedViews.value) {
      if (visitedView.path === view.path) {
        Object.assign(visitedView, view)
        break
      }
    }
  }

  /**
   * 刷新当前页面视图：递增 key 重建页面组件。
   * 先把组件名从 keep-alive include 中摘除再重建，避免旧实例被保留为停用副本；
   * 重建完成后的下一帧恢复缓存登记，使可缓存页面在后续切换时继续生效。
   * @param {Object} view 当前路由对象（使用其 name 与 meta.noCache）。
   */
  async function refreshView(view) {
    if (view && view.name) {
      delCachedView(view)
      refreshTick.value += 1
      await nextTick()
      addCachedView(view)
      return
    }
    refreshTick.value += 1
  }

  function addView(view) {
    addVisitedView(view)
    addCachedView(view)
  }

  async function delView(view) {
    delVisitedView(view)
    delCachedView(view)
    return {
      visitedViews: [...visitedViews.value],
      cachedViews: [...cachedViews.value]
    }
  }

  async function delOthersViews(view) {
    delOthersVisitedViews(view)
    delOthersCachedViews(view)
    return {
      visitedViews: [...visitedViews.value],
      cachedViews: [...cachedViews.value]
    }
  }

  function delAllViews() {
    delAllVisitedViews()
    delAllCachedViews()
    return {
      visitedViews: [...visitedViews.value],
      cachedViews: [...cachedViews.value]
    }
  }

  return {
    visitedViews,
    cachedViews,
    refreshTick,
    refreshView,
    addView,
    addVisitedView,
    addCachedView,
    delView,
    delVisitedView: async(view) => {
      delVisitedView(view)
      return [...visitedViews.value]
    },
    delCachedView: async(view) => {
      delCachedView(view)
      return [...cachedViews.value]
    },
    delOthersViews,
    delOthersVisitedViews: async(view) => {
      delOthersVisitedViews(view)
      return [...visitedViews.value]
    },
    delOthersCachedViews: async(view) => {
      delOthersCachedViews(view)
      return [...cachedViews.value]
    },
    delAllViews,
    delAllVisitedViews: async() => {
      delAllVisitedViews()
      return [...visitedViews.value]
    },
    delAllCachedViews: async() => {
      delAllCachedViews()
      return [...cachedViews.value]
    },
    updateVisitedView
  }
})

export default useTagsViewStore
