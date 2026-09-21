import request from '@/utils/request'
import { h } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import formCreate from '@form-create/element-ui'

let unique = 1

const uniqueId = () => ++unique

function callOpenSuccess(config, data) {
  if (typeof config.openSuccess !== 'function') {
    return
  }

  try {
    config.openSuccess(data)
  } catch (error) {
    console.error(error)
  }
}

export default function modalForm(formRequestPromise, config = {}) {
  return new Promise((resolve, reject) => {
    formRequestPromise
      .then(({ data }) => {
        callOpenSuccess(config, data)
        if (!data.config) data.config = {}
        data.config.submitBtn = false
        data.config.resetBtn = false
        if (!data.config.form) data.config.form = {}
        if (!data.config.formData) data.config.formData = {}
        data.config.formData = { ...data.config.formData, ...config.formData }
        data.config.form.labelWidth = '105px'

        let formApi
        const setFormApi = (api) => {
          formApi = api
        }

        data.config.global = {
          upload: {
            props: {
              onSuccess(rep, file) {
                if (rep.status === 200) {
                  file.url = rep.data.url
                }
              }
            }
          },
          frame: {
            props: {
              onLoad(e) {
                console.log(e, 'rep')
                e.fApi = formApi
              }
            }
          },
          inputNumber: {
            props: {
              controls: false
            }
          }
        }
        data.rule.forEach((e) => {
          e.title += '：'
        })

        ElMessageBox({
          title: data.title,
          showCancelButton: true,
          customClass: config.class || 'modal-form',
          modal: false,
          closeOnClickModal: false,
          message: h(
            'div',
            { class: 'common-form-create', key: uniqueId() },
            [
              h(formCreate.default, {
                rule: data.rule,
                option: data.config,
                onMounted: setFormApi,
                'onUpdate:api': setFormApi
              })
            ]
          ),
          beforeClose: (action, instance, done) => {
            const fn = () => {
              setTimeout(() => {
                instance.confirmButtonLoading = false
              }, 500)
            }

            if (action === 'confirm') {
              if (!formApi) {
                ElMessage.error('表单初始化中，请稍后再试')
                fn()
                return
              }

              instance.confirmButtonLoading = true
              formApi.submit(
                (formData) => {
                  request[data.method.toLowerCase()](data.action, formData)
                    .then((res) => {
                      done()
                      ElMessage.success(res.msg || '提交成功')
                      resolve(res)
                    })
                    .catch((err) => {
                      ElMessage.error(err.msg || '提交失败')
                      reject(err)
                    })
                    .finally(() => {
                      fn()
                    })
                },
                () => fn()
              )
            } else {
              fn()
              done()
            }
          }
        }).catch(() => undefined)
      })
      .catch((e) => {
        callOpenSuccess(config, {})
        ElMessage.error(e.message || '--')
      })
  })
}
