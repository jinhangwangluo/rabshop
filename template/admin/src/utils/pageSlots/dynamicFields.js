import { modelField } from './helpers.js'

/**
 * 将动态配置表单的叶字段转为声明；容器和请求配置不对外公开。
 * @param {object[]} fields 已递归展开的表单配置。
 * @param {object} model 页面真实表单模型。
 * @param {Function} isVisible 沿用页面的业务显示规则。
 * @param {Function} switchValues 获取开关的实际活动/非活动值。
 * @returns {object[]} 插槽字段声明。
 */
export function dynamicSlotFields(fields, model, isVisible, switchValues) {
  return fields.filter(field => field.props?.field && ['input', 'textarea', 'number', 'switch', 'radio', 'checkbox', 'upload'].includes(field.type))
    .map(field => {
      const props = field.props
      const options = (props.options || []).map(item => ({ label: item.label, value: item.value }))
      let schema = { type: 'string', maxLength: Number(props.maxlength) || 100000 }
      if (field.type === 'number') schema = { type: 'number', min: props.min, max: props.max }
      if (field.type === 'switch') schema = { enum: switchValues(field) }
      if (field.type === 'radio') schema = { enum: options.map(item => item.value) }
      if (field.type === 'checkbox') schema = { type: 'array', unique: true, items: { enum: options.map(item => item.value) }}
      if (field.type === 'upload') {
        schema = {
          type: 'array', maxItems: Number(props.limit) > 0 ? Number(props.limit) : 200,
          items: {
            type: 'object', required: ['url'],
            properties: {
              url: { type: 'string', maxLength: 2048, pattern: '^(https?://|/)' },
              name: { type: 'string', maxLength: 255 },
              fileName: { type: 'string', maxLength: 255 }
            }
          }
        }
      }
      return modelField(model, props.field, props.label || field.label || props.field, {
        type: field.type,
        description: typeof props.description === 'string' ? props.description : '',
        required: !!props.required,
        expose: props.expose,
        sensitive: props.sensitive,
        inputType: props.type,
        schema,
        options: field.type === 'switch' ? switchValues(field).map(value => ({ value })) : options,
        available: () => !props.disabled && !props.readonly && isVisible(field)
      })
    })
}
