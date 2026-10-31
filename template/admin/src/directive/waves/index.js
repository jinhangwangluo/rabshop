import waves from './waves'

const install = function(app) {
  app.directive('waves', waves)
}

waves.install = install
export default waves
