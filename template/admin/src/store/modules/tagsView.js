import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useTagsViewStore = defineStore('tagsView', () => {
  const visitedViews = ref([])
  const cachedViews = ref([])

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
