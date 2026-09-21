import { imageAmplify, on } from '@/utils/index'

export default {
  mounted(el, binding) {
    const { value } = binding
    const trigger = value && value.trigger
    const triggerDom = trigger ? document.querySelector(trigger) : el
    const handleClick = () => {
      imageAmplify(value)
    }

    on(triggerDom, 'click', handleClick)
    el.__viewerTrigger = triggerDom
    el.__viewerHandler = handleClick
  },
  beforeUnmount(el) {
    if (el.__viewerTrigger && el.__viewerHandler) {
      el.__viewerTrigger.removeEventListener('click', el.__viewerHandler, false)
    }
    delete el.__viewerTrigger
    delete el.__viewerHandler
  }
}
