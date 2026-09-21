import Clipboard from './clipboard'

const install = function(app) {
  app.directive('clipboard', Clipboard)
}

Clipboard.install = install
export default Clipboard
