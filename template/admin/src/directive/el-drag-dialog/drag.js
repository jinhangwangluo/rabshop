export default {
  beforeMount(el, binding, vnode) {
    const dialogHeaderEl = el.querySelector('.el-dialog__header')
    const dragDom = el.querySelector('.el-dialog')
    if (!dialogHeaderEl || !dragDom) return

    dialogHeaderEl.style.cssText += ';cursor:move;'
    dragDom.style.cssText += ';top:0px;'

    // 获取原有属性 ie dom元素.currentStyle 火狐谷歌 window.getComputedStyle(dom元素, null);
    const getStyle = (function() {
      if (window.document.currentStyle) {
        return (dom, attr) => dom.currentStyle[attr]
      } else {
        return (dom, attr) => getComputedStyle(dom, false)[attr]
      }
    })()

    let disX = 0
    let disY = 0
    let minDragDomLeft = 0
    let maxDragDomLeft = 0
    let minDragDomTop = 0
    let maxDragDomTop = 0
    let styL = 0
    let styT = 0

    const handleMousemove = (e) => {
      // 通过事件委托，计算移动的距离
      let left = e.clientX - disX
      let top = e.clientY - disY

      // 边界处理
      if (-(left) > minDragDomLeft) {
        left = -minDragDomLeft
      } else if (left > maxDragDomLeft) {
        left = maxDragDomLeft
      }

      if (-(top) > minDragDomTop) {
        top = -minDragDomTop
      } else if (top > maxDragDomTop) {
        top = maxDragDomTop
      }

      // 移动当前元素
      dragDom.style.cssText += `;left:${left + styL}px;top:${top + styT}px;`

      // Vue3 不再提供 vnode.child，存在事件监听时再通知宿主组件
      const instance = (vnode && vnode.component && vnode.component.proxy) || binding.instance
      if (instance && typeof instance.$emit === 'function') {
        instance.$emit('dragDialog')
      }
    }

    const handleMouseup = () => {
      document.onmousemove = null
      document.onmouseup = null
    }

    const handleMousedown = (e) => {
      // 鼠标按下，计算当前元素距离可视区的距离
      disX = e.clientX - dialogHeaderEl.offsetLeft
      disY = e.clientY - dialogHeaderEl.offsetTop

      const dragDomWidth = dragDom.offsetWidth
      const dragDomHeight = dragDom.offsetHeight

      const screenWidth = document.body.clientWidth
      const screenHeight = document.body.clientHeight

      minDragDomLeft = dragDom.offsetLeft
      maxDragDomLeft = screenWidth - dragDom.offsetLeft - dragDomWidth

      minDragDomTop = dragDom.offsetTop
      maxDragDomTop = screenHeight - dragDom.offsetTop - dragDomHeight

      // 获取到的值带px 正则匹配替换
      styL = getStyle(dragDom, 'left')
      styT = getStyle(dragDom, 'top')

      if (styL.includes('%')) {
        styL = +document.body.clientWidth * (+styL.replace(/\%/g, '') / 100)
        styT = +document.body.clientHeight * (+styT.replace(/\%/g, '') / 100)
      } else {
        styL = +styL.replace(/\px/g, '')
        styT = +styT.replace(/\px/g, '')
      }

      document.onmousemove = handleMousemove
      document.onmouseup = handleMouseup
    }

    dialogHeaderEl.onmousedown = handleMousedown
    el.__dragDialogState = {
      dialogHeaderEl,
      handleMousedown,
      handleMousemove,
      handleMouseup
    }
  },
  unmounted(el) {
    const state = el.__dragDialogState
    if (!state) return

    if (state.dialogHeaderEl.onmousedown === state.handleMousedown) {
      state.dialogHeaderEl.onmousedown = null
    }
    if (document.onmousemove === state.handleMousemove) {
      document.onmousemove = null
    }
    if (document.onmouseup === state.handleMouseup) {
      document.onmouseup = null
    }
    delete el.__dragDialogState
  }
}
