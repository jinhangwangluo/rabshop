import { createVNode, render } from 'vue'
import imgSelectModal from './imgModal.vue'
import { open as openImgModal } from './controller'

const imgModal = {}

imgModal.install = function(app) {
  const container = document.createElement('div')
  const vnode = createVNode(imgSelectModal)
  vnode.appContext = app._context
  render(vnode, container)
  document.body.appendChild(container)

  // 通过模块级共享状态控制弹窗（组件实例 proxy 无法写入 <script setup> 绑定）
  app.config.globalProperties.$imgModal = function(callback, maxLength, modelName, boolean) {
    openImgModal(callback, maxLength, modelName, boolean)
  }
}

export default imgModal
