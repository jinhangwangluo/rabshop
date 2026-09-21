// 图片选择弹窗的模块级共享状态
// 背景：imgModal.vue 迁移为 <script setup> 后，其绑定无法通过组件 proxy 读写
// （Vue3 禁止外部 mutate script setup 绑定，set 会抛 TypeError），
// 因此把弹窗状态提升到模块作用域，由 model.js 直接驱动。
import { ref } from 'vue'

const visible = ref(false)
const callback = ref(() => {})
const maxLength = ref(1)
const modelName = ref('')
const booleanVal = ref(false)

function open(cb, max, name, bool) {
  callback.value = cb
  maxLength.value = max
  modelName.value = name
  booleanVal.value = bool
  visible.value = true
}

function close() {
  visible.value = false
}

export {
  visible,
  callback,
  maxLength,
  modelName,
  booleanVal,
  open,
  close
}
