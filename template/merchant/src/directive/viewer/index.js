import viewer from './viewer'

const install = function(Vue) {
  Vue.directive('viewer', viewer)
}

if (window.Vue) {
  window.viewer = viewer
  Vue.use(install); // eslint-disable-line
}

viewer.install = install
export default viewer
