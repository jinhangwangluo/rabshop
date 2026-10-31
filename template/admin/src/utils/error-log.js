import { useErrorLogStore } from '@/store/modules/errorLog'
import { isString, isArray } from '@/utils/validate'
import settings from '@/settings'

// you can set in settings.js
// errorLog:'production' | ['production', 'development']
const { errorLog: needErrorLog } = settings

function checkNeed() {
  const env = import.meta.env.MODE
  if (isString(needErrorLog)) {
    return env === needErrorLog
  }
  if (isArray(needErrorLog)) {
    return needErrorLog.includes(env)
  }
  return false
}

export default function installErrorHandler(app, pinia) {
  if (!checkNeed()) {
    return
  }

  app.config.errorHandler = (err, instance, info) => {
    const errorLogStore = useErrorLogStore(pinia)
    errorLogStore.addErrorLog({
      err,
      vm: instance && instance.proxy ? instance.proxy : null,
      info,
      url: window.location.href
    })
    console.error(err, info)
  }
}
