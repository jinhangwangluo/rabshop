import { imageAmplify, on } from '@/utils/index'

export default {
  inserted(el, binding, vnode) {
    const { value } = binding;
    const triggerDom = document.querySelector(binding.value.trigger);
    on(triggerDom, 'click', function() {
      imageAmplify(value)
    })
  }
}
