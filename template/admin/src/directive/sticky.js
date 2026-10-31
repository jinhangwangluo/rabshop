const vueSticky = {
  install(app) {
    app.directive('sticky', {
      mounted(el, binding) {
        const params = binding.value || {}
        const stickyTop = params.stickyTop || 0
        const zIndex = params.zIndex || 1000
        const elStyle = el.style
        const originalStyle = {
          position: elStyle.position,
          width: elStyle.width,
          height: elStyle.height,
          top: elStyle.top,
          zIndex: elStyle.zIndex
        }

        elStyle.position = '-webkit-sticky'
        elStyle.position = 'sticky'
        // if the browser support css sticky（Currently Safari, Firefox and Chrome Canary）
        // if (~elStyle.position.indexOf('sticky')) {
        //     elStyle.top = `${stickyTop}px`;
        //     elStyle.zIndex = zIndex;
        //     return
        // }
        const elHeight = el.getBoundingClientRect().height
        const elWidth = el.getBoundingClientRect().width
        elStyle.top = `${stickyTop}px`
        elStyle.zIndex = zIndex

        const parentElm = el.parentNode || document.documentElement
        const placeholder = document.createElement('div')
        placeholder.style.display = 'none'
        placeholder.style.width = `${elWidth}px`
        placeholder.style.height = `${elHeight}px`
        parentElm.insertBefore(placeholder, el)

        let active = false

        const getScroll = (target, top) => {
          const prop = top ? 'pageYOffset' : 'pageXOffset'
          const method = top ? 'scrollTop' : 'scrollLeft'
          let ret = target[prop]
          if (typeof ret !== 'number') {
            ret = window.document.documentElement[method]
          }
          return ret
        }

        const sticky = () => {
          if (active) {
            return
          }
          if (!elStyle.height) {
            elStyle.height = `${el.offsetHeight}px`
          }

          elStyle.position = 'fixed'
          elStyle.width = `${elWidth}px`
          placeholder.style.display = 'inline-block'
          active = true
        }

        const reset = () => {
          if (!active) {
            return
          }

          elStyle.position = originalStyle.position
          elStyle.width = originalStyle.width
          elStyle.height = originalStyle.height
          placeholder.style.display = 'none'
          active = false
        }

        const check = () => {
          const scrollTop = getScroll(window, true)
          const offsetTop = el.getBoundingClientRect().top
          if (offsetTop < stickyTop) {
            sticky()
          } else if (scrollTop < elHeight + stickyTop) {
            reset()
          }
        }
        const listenAction = () => {
          check()
        }

        el.__stickyState = {
          listenAction,
          placeholder,
          originalStyle
        }
        window.addEventListener('scroll', listenAction)
      },

      unmounted(el) {
        const state = el.__stickyState
        if (!state) return

        window.removeEventListener('scroll', state.listenAction)
        if (state.placeholder.parentNode) {
          state.placeholder.parentNode.removeChild(state.placeholder)
        }
        el.style.position = state.originalStyle.position
        el.style.width = state.originalStyle.width
        el.style.height = state.originalStyle.height
        el.style.top = state.originalStyle.top
        el.style.zIndex = state.originalStyle.zIndex
        delete el.__stickyState
      }
    })
  }
}

export default vueSticky
