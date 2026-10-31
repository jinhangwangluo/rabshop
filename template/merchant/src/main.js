import { createApp } from 'vue'

import Cookies from 'js-cookie'

import 'normalize.css/normalize.css' // a modern alternative to CSS resets

import ElementPlus from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import { ElMessage, ElMessageBox, ElNotification } from 'element-plus'
import 'element-plus/dist/index.css'
import './styles/element-variables.scss'

import './styles/index.scss' // global css

import App from './App.vue'
import pinia from './store'
import router from './router'
import settings from '@/settings'
import modalForm from '@/utils/modalForm'
import { imageAmplify, modalSure, setAppContext } from '@/utils/index'
import formCreate from '@form-create/element-ui'
import pagesHeader from '@/components/pagesHeader'
import Upload from '@/components/Upload/index'

import icons from './icons'
import './permission' // permission control
import installErrorHandler from './utils/error-log'
import VueLazyLoad from 'vue3-lazyload'
import noImage from './assets/images/no.png'
import loadingImage from './assets/images/moren.jpg'
import viewer from '@/directive/viewer'
import imgModal from '@/components/UploadPicture/model'
import { initializePluginRegistry } from '@/plugins/registry'
import { registerSubmitHandler } from '@/plugins/submit-events'
import PluginSlot from '@/components/PluginSlot/index.vue'

const brandConsoleLogo = `
               :==:              :-=:
             .#%@%%#:          :#%%@%#.
             *@%%%%%%-        -%%%%%%@*
            .%%%%%%%%%.      :%%%%%%%%%
            .%%%%%%%%%+      *%%%%%%%%%
             #%%%%%%%%%     .%%%%%%%%%*
             -%%%%%%%%%:    :%%%%%%%%%:
              +%%%%%%%%#****#%%%%%%%%=
              .#%%%%%@@@@@@@@@@%%%%%*
            .+#%%@%%*+=--::--=+*%%@%%%+.
           =%%%%%+:              :+%%%%%+
          *@%%@#.   :         .:   .#@%%@*
         +@%%@#.   #@#        #@*   .#@%%@+
        .%%%%=     *%*        *%+     =%%%%.
        :%%@+       .   :==:   .       +@%%:
        .%%%#.         :%@@#.         .#%%%
         :%%%%+:         ..         :+%%%%:
          .+%%@@%*+=-:::....::--=+*%@@%%+.
             :+*#%@@@@@@@@@@@@@@@@%#*+:
                 .::-===+++====-::.
`

function printBrandConsole() {
  console.log('%c%s', 'color:#ff5a1f;font-family:Menlo,Consolas,monospace;line-height:1.1;', brandConsoleLogo)
  console.log(
    '%c 西安锦航网络科技有限公司 %c 官网地址：www.rabshop.cn %c 温馨提示：代码正在值班，Bug 请先取号排队。',
    'background:#202b3c;color:#ffffff;padding:3px 8px;border-radius:3px 0 0 3px;font-weight:bold;',
    'background:#eef6ff;color:#1b6ebf;padding:3px 8px;font-weight:bold;',
    'background:#f5c542;color:#202b3c;padding:3px 8px;border-radius:0 3px 3px 0;'
  )
}

printBrandConsole()

const app = createApp(App)
initializePluginRegistry({ registerSubmitHandler })
const cookieSize = Cookies.get('size') || 'default'
const elementSize = cookieSize === 'medium' ? 'default' : cookieSize

app.use(pinia)
app.use(router)
app.use(ElementPlus, {
  size: elementSize,
  locale: zhCn
})
app.use(formCreate)
app.use(imgModal)
app.use(VueLazyLoad, {
  preLoad: 1.3,
  error: noImage,
  loading: loadingImage,
  attempt: 1,
  listenEvents: ['scroll', 'wheel', 'mousewheel', 'resize', 'animationend', 'transitionend', 'touchmove']
})
app.use(icons)

app.component('pagesHeader', pagesHeader)
app.component('PagesHeader', pagesHeader)
app.component('Upload', Upload)
app.component('PluginSlot', PluginSlot)
app.directive('viewer', viewer)

app.config.globalProperties.$routeProStr = settings.prefix ? '/' + settings.prefix + '/' : ''
app.config.globalProperties.$modalForm = modalForm
app.config.globalProperties.$modalSure = modalSure
app.config.globalProperties.$imageAmplify = imageAmplify
app.config.globalProperties.$message = ElMessage
app.config.globalProperties.$confirm = ElMessageBox.confirm
app.config.globalProperties.$alert = ElMessageBox.alert
app.config.globalProperties.$prompt = ElMessageBox.prompt
app.config.globalProperties.$notify = ElNotification

installErrorHandler(app, pinia)
setAppContext(app._context)
app.mount('#app')
