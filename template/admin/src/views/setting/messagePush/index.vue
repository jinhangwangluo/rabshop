<template>
  <div class="message-push">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">消息推送</h1>
        <p class="page-curator-subtitle">
          配置系统消息推送渠道，管理模板同步、字段与发送记录
        </p>
      </div>
      <div class="page-curator-actions">
        <el-button type="primary" class="btn-curator-primary" :icon="Plus" @click="openEventDrawer">
          新增消息
        </el-button>
        <el-button
          class="btn-curator-reset"
          :icon="Refresh"
          :disabled="syncingChannel !== '' && syncingChannel !== 'official_account'"
          :loading="syncingChannel === 'official_account'"
          @click="handleSyncChannel('official_account')"
        >
          同步公众号模板
        </el-button>
        <el-button
          class="btn-curator-reset"
          :icon="Refresh"
          :disabled="syncingChannel !== '' && syncingChannel !== 'miniapp'"
          :loading="syncingChannel === 'miniapp'"
          @click="handleSyncChannel('miniapp')"
        >
          同步小程序模板
        </el-button>
        <el-button class="btn-curator-reset" :icon="Document" @click="openLogDialog()">
          发送日志
        </el-button>
      </div>
    </header>

    <div class="message-push-work-surface">
      <div class="search-form-wrap">
        <el-form inline @submit.prevent>
          <el-form-item label="消息名称：">
            <el-input
              v-model="keyword"
              clearable
              :prefix-icon="Search"
              placeholder="搜索消息名称 / 事件标识"
              class="form_content_width message-push__search"
              @keyup.enter="fetchList"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" class="btn-curator-primary" :loading="loading" @click="fetchList">查询</el-button>
            <el-button class="btn-curator-reset" @click="keyword = ''; fetchList()">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table
        v-loading="loading"
        :data="filteredList"
        row-key="event_key"
        class="message-push__table"
        highlight-current-row
      >
        <el-table-column label="消息事件" min-width="210">
          <template #default="{ row }">
            <div class="event-name">{{ row.event_name }}</div>
            <div class="event-meta">
              <el-tag type="info">{{ row.receiver_text }}</el-tag>
              <span>{{ row.event_key }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column
          v-for="channel in channelOptions"
          :key="channel.value"
          :label="channel.label"
          :min-width="getChannelColumnWidth(channel.value)"
        >
          <template #default="{ row }">
            <div v-if="isEventChannelConfigurable(row, channel.value)" class="channel-cell">
              <el-switch
                :model-value="getChannel(row, channel.value).enabled"
                :active-value="1"
                :inactive-value="0"
                @change="handleToggle(row, channel.value, $event)"
              />
              <div class="channel-cell__stack">
                <span>{{ getChannelTemplateStatus(getChannel(row, channel.value)) }}</span>
              </div>
            </div>
            <span v-else class="channel-cell__empty">--</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <a @click="handleEdit(row)">配置</a>
            <el-divider direction="vertical" />
            <a @click="openLogDialog(row)">日志</a>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-drawer
      v-model="eventDrawerVisible"
      title="新增消息"
      size="520px"
      custom-class="message-event-drawer"
      @closed="resetEventForm"
    >
      <div class="event-drawer-body">
        <el-form ref="eventFormRef" :model="eventForm" label-width="96px" class="event-form">
          <el-form-item label="消息名称">
            <el-input v-model="eventForm.event_name" placeholder="如：课程开播提醒" />
          </el-form-item>
          <el-form-item label="事件标识">
            <el-input v-model="eventForm.event_key" placeholder="如：course_live_start" />
            <div class="input-tip">以小写字母开头，只能包含小写字母、数字、下划线。</div>
          </el-form-item>
          <el-form-item label="通知类型">
            <el-select v-model="eventForm.receiver_type" class="event-form__select">
              <el-option
                v-for="item in receiverTypeOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="关联类型">
            <el-input v-model="eventForm.relation_type" placeholder="如：order / course / custom" />
          </el-form-item>
          <el-form-item label="变量">
            <div class="event-variable-editor">
              <div
                v-for="(item, index) in eventForm.variables"
                :key="index"
                class="event-variable-row"
              >
                <el-input v-model="item.key" placeholder="变量名" />
                <el-input v-model="item.label" placeholder="显示名称" />
                <el-input v-model="item.sample" placeholder="示例值" />
                <button type="button" class="event-variable-row__delete" @click="removeEventVariable(index)">
                  <el-icon><Delete /></el-icon>
                </button>
              </div>
              <el-button :icon="Plus" @click="addEventVariable">添加变量</el-button>
            </div>
          </el-form-item>
          <el-form-item label="排序">
            <el-input-number v-model="eventForm.sort" :min="0" :max="99999" controls-position="right" />
          </el-form-item>
          <el-form-item label="状态">
            <el-switch v-model="eventForm.status" :active-value="1" :inactive-value="0" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="eventForm.remark" type="textarea" :rows="3" maxlength="500" show-word-limit />
          </el-form-item>
        </el-form>
        <div class="drawer-footer event-drawer-footer">
          <el-button @click="eventDrawerVisible = false">取消</el-button>
          <el-button type="primary" :loading="eventSaving" @click="handleSaveEvent">保存消息</el-button>
        </div>
      </div>
    </el-drawer>

    <el-drawer
      v-model="drawerVisible"
      :title="drawerTitle"
      size="62%"
      custom-class="message-push-drawer"
      @closed="handleDrawerClosed"
    >
      <div v-if="selectedEvent" class="drawer-body">
        <div class="event-panel">
          <div>
            <div class="event-panel__title">{{ selectedEvent.event_name }}</div>
            <div class="event-panel__desc">接收对象：{{ selectedEvent.receiver_text }}</div>
          </div>
          <el-button :icon="Document" @click="openLogDialog(selectedEvent)">发送日志</el-button>
        </div>

        <el-tabs v-if="visibleEditChannels.length" v-model="activeChannel" type="border-card" class="message-push__tabs">
          <el-tab-pane
            v-for="channel in visibleEditChannels"
            :key="channel.channel"
            :name="channel.channel"
            :label="channel.channel_label"
          >
            <div class="channel-form">
              <el-form label-width="88px" class="drawer-config-form">
                <section class="drawer-card drawer-card--basic">
                  <div class="drawer-card__title">基础配置</div>
                  <div class="basic-config-grid">
                    <el-form-item label="启用状态">
                      <el-switch v-model="channel.enabled" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                    <el-form-item
                      v-if="!isWebhookChannel(channel.channel)"
                      :label="getTemplateCodeLabel(channel.channel)"
                      class="form-item-with-tip"
                    >
                      <el-input v-model="channel.template_code" :placeholder="getTemplateCodePlaceholder(channel.channel)" />
                      <div v-if="channel.channel === 'miniapp'" class="input-tip">
                        模板编号是微信订阅消息公共模板库编号，如 1451，用来记录当前消息使用的模板类型；K- 开头的是模版ID，请填写到右侧“模版ID”。
                      </div>
                    </el-form-item>
                    <el-form-item v-if="isWechatTemplateChannel(channel.channel)" label="模版ID" class="form-item-with-tip">
                      <el-input
                        v-model="channel.external_template_id"
                        :placeholder="getExternalTemplateIdPlaceholder(channel.channel)"
                      />
                      <div v-if="channel.channel === 'miniapp'" class="input-tip">
                        登录微信公众平台 -> 开发 -> 订阅消息 -> 模板管理，复制模版ID
                      </div>
                    </el-form-item>
                    <template v-if="channel.channel === 'email'">
                      <el-form-item label="邮件标题模板" class="drawer-form-full">
                        <el-input
                          v-model="channel.sync_config.subject_template"
                          placeholder="支持 {{变量}}，如：{{site_name}}消息通知"
                        />
                      </el-form-item>
                      <el-form-item label="发件人名称" class="drawer-form-full">
                        <el-input v-model="channel.sync_config.from_name" placeholder="可选，如：系统通知" />
                      </el-form-item>
                    </template>
                    <el-form-item v-if="channel.channel === 'sms'" label="短信签名">
                      <el-input v-model="channel.sync_config.sign_name" placeholder="不填则使用系统阿里云短信签名" />
                    </el-form-item>
                  </div>
                  <div v-if="isWebhookChannel(channel.channel)" class="webhook-config-grid">
                    <el-form-item label="Webhook地址" class="drawer-form-full">
                      <el-input v-model="channel.sync_config.webhook_url" placeholder="请输入 BOT Webhook 地址" />
                    </el-form-item>
                    <el-form-item v-if="channel.channel === 'dingtalk_bot'" label="签名密钥" class="drawer-form-full">
                      <el-input v-model="channel.sync_config.secret" placeholder="钉钉机器人加签密钥，可选" show-password />
                    </el-form-item>
                    <el-form-item label="请求头" class="drawer-form-full">
                      <div class="webhook-header-editor">
                        <div
                          v-for="(header, index) in channel.sync_config.headers"
                          :key="index"
                          class="webhook-header-row"
                        >
                          <el-input v-model="header.key" placeholder="Header 名称" />
                          <el-input v-model="header.value" placeholder="Header 值" />
                          <button type="button" class="webhook-header-row__delete" @click="removeWebhookHeader(channel, index)">
                            <el-icon><Delete /></el-icon>
                          </button>
                        </div>
                        <el-button :icon="Plus" @click="addWebhookHeader(channel)">添加请求头</el-button>
                      </div>
                    </el-form-item>
                  </div>
                  <el-form-item v-if="isTextTemplateChannel(channel.channel)" label="内容模板" class="drawer-form-full">
                    <el-input
                      v-model="channel.content_template"
                      type="textarea"
                      :rows="4"
                      maxlength="500"
                      show-word-limit
                      :placeholder="getContentTemplatePlaceholder(channel.channel)"
                    />
                    <div v-if="channel.channel === 'sms'" class="content-template-actions">
                      <el-button

                        :icon="DocumentCopy"
                        class="btn-curator-reset"
                        @click="copyContentTemplate(channel)"
                      >复制模板</el-button>
                      <span class="input-tip">短信内容模板为阿里云短信模板，变量使用 ${变量} 格式，需与阿里云短信后台模板内容一致。</span>
                    </div>
                  </el-form-item>
                </section>

                <section v-if="isWechatTemplateChannel(channel.channel)" class="drawer-card field-map-card">
                  <field-map-editor
                    :rows="channel.field_map"
                    :variables="selectedEvent.variables"
                    :show-color="channel.channel === 'official_account'"
                    title="模板字段映射"
                    :description="getFieldMapDescription(channel.channel)"
                    @change="updateFieldMap(channel, $event)"
                  />
                </section>

                <section class="drawer-card variables-card">
                  <div class="variables-card__head">
                    <div class="variables-card__intro">
                      <div class="drawer-card__title">内置变量</div>
                      <div class="variables-card__usage">{{ getVariableUsageText(channel.channel) }}</div>
                    </div>
                    <button
                      v-if="hasMoreVariables()"
                      type="button"
                      class="variables-card__more"
                      @click="variableExpanded = !variableExpanded"
                    >
                      {{ variableExpanded ? '收起' : '展开更多' }}
                      <el-icon><component :is="variableExpanded ? ArrowUp : ArrowDown" /></el-icon>
                    </button>
                  </div>
                  <div class="variable-list">
                    <el-tag
                      v-for="item in getVisibleVariables()"
                      :key="item.key"
                      class="variable-tag"
                      :title="getVariableTagTitle(channel.channel, item.key)"
                      @click="handleVariableClick(channel, item.key)"
                    >{{ item.label }}</el-tag>
                  </div>
                </section>

                <div class="drawer-bottom-grid" :class="{ 'drawer-bottom-grid--single': !hasJumpConfig(channel.channel) }">
                  <section v-if="hasJumpConfig(channel.channel)" class="drawer-card jump-card">
                    <template v-if="channel.channel === 'official_account'">
                      <div class="drawer-card__title">公众号跳转</div>
                      <div class="channel-help">
                        可配置模板消息点击后的网页链接，或跳转到指定小程序页面。
                      </div>
                      <el-form-item label="跳转链接">
                        <el-input v-model="channel.jump_config.url" placeholder="模板消息跳转URL" />
                      </el-form-item>
                      <el-form-item label="小程序AppID">
                        <el-input v-model="channel.jump_config.mini_appid" placeholder="可选，跳转小程序 AppID" />
                      </el-form-item>
                      <el-form-item label="小程序路径">
                        <el-input v-model="channel.jump_config.mini_page_path" placeholder="可选，跳转小程序页面路径" />
                      </el-form-item>
                    </template>
                    <template v-if="channel.channel === 'miniapp'">
                      <div class="drawer-card__title">小程序跳转</div>
                      <el-form-item label="跳转页面">
                        <el-input
                          :value="channel.jump_config.page || '系统固定跳转页'"
                          disabled
                          class="fixed-page-input"
                        />
                      </el-form-item>
                      <div class="channel-help">
                        跳转页面由系统按消息事件固定，发送时会自动拼接对应业务ID。
                      </div>
                    </template>
                  </section>

                  <section class="drawer-card test-card">
                    <div class="drawer-card__head">
                      <div class="drawer-card__title">测试发送</div>
                      <el-button @click="fillSamplePayload">填充示例变量</el-button>
                    </div>
                    <div class="test-card__content">
                      <div v-if="channel.channel === 'email'" class="test-card__accounts test-card__accounts--single">
                        <el-form-item label="测试邮箱">
                          <el-input v-model="testReceiver.email" placeholder="请输入邮箱" />
                        </el-form-item>
                      </div>
                      <div v-else-if="!isWebhookChannel(channel.channel)" class="test-card__accounts">
                        <el-form-item label="测试手机号">
                          <el-input v-model="testReceiver.phone" placeholder="请输入手机号" />
                        </el-form-item>
                        <el-form-item label="公众号openid">
                          <el-input v-model="testReceiver.official_openid" placeholder="请输入公众号 openid" />
                        </el-form-item>
                        <el-form-item label="小程序openid">
                          <el-input v-model="testReceiver.mini_openid" placeholder="请输入小程序 openid" />
                        </el-form-item>
                      </div>
                      <div v-else class="test-card__accounts test-card__accounts--webhook">
                        <div class="channel-help">
                          Webhook 测试会直接发送到当前配置的 BOT 地址，不需要填写接收账号。
                        </div>
                      </div>
                      <el-form-item label="JSON 测试数据" class="test-card__payload">
                        <el-input
                          v-model="testPayloadText"
                          type="textarea"
                          :rows="8"
                          placeholder="测试变量JSON"
                        />
                      </el-form-item>
                      <div class="side-actions">
                        <el-button type="primary" :loading="testing" @click="handleTest(channel)">发送测试</el-button>
                      </div>
                    </div>
                    <div class="test-result">
                      <div class="test-result__title">发送结果</div>
                      <div class="test-result__state" :class="'test-result__state--' + testResult.status">
                        <el-icon><component :is="getTestResultIcon(testResult.status)" /></el-icon>
                        <span>{{ getTestResultLabel(testResult.status) }}</span>
                        <em>{{ testResult.message }}</em>
                      </div>
                    </div>
                  </section>
                </div>
              </el-form>
            </div>

            <div class="drawer-footer">
              <el-button @click="drawerVisible = false">取消</el-button>
              <el-button type="primary" :loading="saving" @click="handleSave(channel)">保存配置</el-button>
            </div>
          </el-tab-pane>
        </el-tabs>
        <el-empty v-else description="当前接收对象暂无可配置渠道" />
      </div>
    </el-drawer>

    <el-dialog v-model="logDialogVisible" title="发送日志" width="980px" append-to-body>
      <div class="log-toolbar">
        <el-select v-model="logQuery.channel" clearable placeholder="渠道" @change="fetchLogs">
          <el-option v-for="item in channelOptions" :key="item.value" :label="item.label" :value="item.value" />
        </el-select>
        <el-select v-model="logQuery.status" clearable placeholder="状态" @change="fetchLogs">
          <el-option label="成功" :value="1" />
          <el-option label="失败" :value="2" />
          <el-option label="跳过" :value="3" />
        </el-select>
      </div>
      <el-table v-loading="logLoading" :data="logs" row-key="id" border>
        <el-table-column prop="event_key" label="事件" min-width="150" />
        <el-table-column prop="channel" label="渠道" width="130" />
        <el-table-column prop="receiver_account" label="接收账号" min-width="160" show-overflow-tooltip />
        <el-table-column prop="rendered_content" label="内容" min-width="240" show-overflow-tooltip />
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="getLogStatusType(row.status)">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="error_message" label="错误" min-width="180" show-overflow-tooltip />
        <el-table-column prop="create_time" label="时间" width="160" />
      </el-table>
      <div class="pagination-wrap">
        <el-pagination
          :current-page="logQuery.page"
          :page-size="logQuery.limit"
          :total="logTotal"
          layout="total, prev, pager, next"
          @current-change="handleLogPageChange"
        />
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeMount, reactive, ref, resolveComponent } from 'vue'
import {
  ArrowDown,
  ArrowUp,
  CircleCheck,
  CircleClose,
  Delete,
  Document,
  DocumentCopy,
  Plus,
  Refresh,
  Remove,
  Search
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import {
  getMessagePushEvents,
  getMessagePushList,
  getMessagePushLogs,
  saveMessagePushConfig,
  saveMessagePushEvent,
  setMessagePushStatus,
  syncMessagePushChannel,
  testMessagePushTemplate
} from '@/api/messagePush'

defineOptions({ name: 'MessagePush' })

function hasFieldMapContent(row) {
  if (!row || typeof row !== 'object') return false
  return ['field', 'value', 'color'].some(key => String(row[key] ?? '').trim() !== '')
}

function normalizeFieldMap(rows) {
  return Array.isArray(rows) ? rows.filter(hasFieldMapContent) : []
}

const FieldMapEditor = defineComponent({
  name: 'FieldMapEditor',
  props: {
    rows: {
      type: Array,
      default: () => []
    },
    variables: {
      type: Array,
      default: () => []
    },
    showColor: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: '模板字段映射'
    },
    description: {
      type: String,
      default: ''
    }
  },
  emits: ['change'],
  setup(props, { emit }) {
    const ElButton = resolveComponent('el-button')
    const ElColorPicker = resolveComponent('el-color-picker')
    const ElInput = resolveComponent('el-input')
    const ElOption = resolveComponent('el-option')
    const ElSelect = resolveComponent('el-select')
    const emitChange = nextRows => emit('change', nextRows)

    function addRow() {
      emitChange([...(props.rows || []), { field: '', value_type: 'variable', value: '', color: '' }])
    }

    function removeRow(index) {
      const nextRows = [...(props.rows || [])]
      nextRows.splice(index, 1)
      emitChange(nextRows)
    }

    function updateRow(index, key, value) {
      const nextRows = [...(props.rows || [])]
      nextRows[index] = { ...nextRows[index], [key]: value }
      emitChange(nextRows)
    }

    return () => {
      const rows = Array.isArray(props.rows) ? props.rows : []
      const layoutClass = props.showColor ? 'with-color' : 'no-color'
      const fieldMapContent = rows.length
        ? [
          h('div', { class: ['field-map-editor__columns', `field-map-editor__columns--${layoutClass}`] }, [
            h('span', '微信字段名'),
            h('span', '取值方式'),
            h('span', '字段值'),
            props.showColor ? h('span', '颜色') : null,
            h('span')
          ]),
          h('div', { class: 'field-map-editor__body' }, rows.map((row, index) => h('div', { class: ['field-map-row', `field-map-row--${layoutClass}`], key: index }, [
            h(ElInput, {
              class: 'field-map-row__field',
              modelValue: row.field,
              placeholder: '如 keyword1 / thing1',
              'onUpdate:modelValue': value => updateRow(index, 'field', value)
            }),
            h(ElSelect, {
              class: 'field-map-row__type',
              modelValue: row.value_type || 'variable',
              'onUpdate:modelValue': value => updateRow(index, 'value_type', value)
            }, [
              h(ElOption, { label: '变量', value: 'variable' }),
              h(ElOption, { label: '文本', value: 'text' })
            ]),
            row.value_type === 'text'
              ? h(ElInput, {
                class: 'field-map-row__value',
                modelValue: row.value,
                placeholder: '固定文本，支持 {{变量}}',
                'onUpdate:modelValue': value => updateRow(index, 'value', value)
              })
              : h(ElSelect, {
                class: 'field-map-row__value',
                filterable: true,
                modelValue: row.value,
                placeholder: '选择变量',
                'onUpdate:modelValue': value => updateRow(index, 'value', value)
              }, (props.variables || []).map(item => h(ElOption, {
                key: item.key,
                label: item.label,
                value: item.key
              }))),
            props.showColor ? h(ElColorPicker, {
              class: 'field-map-row__color',
              modelValue: row.color || '',
              showAlpha: false,
              predefine: ['#173177', '#ff3b30', '#ff7a00', '#19be6b', '#409eff', '#606266'],
              'onUpdate:modelValue': value => updateRow(index, 'color', value || '')
            }) : null,
            h('span', {
              class: 'field-map-row__delete',
              title: '删除字段',
              onClick: () => removeRow(index)
            }, [h(Delete)])
          ])))
        ]
        : []

      return h('div', { class: 'field-map-editor' }, [
        h('div', { class: 'field-map-editor__header' }, [
          h('div', { class: 'field-map-editor__intro' }, [
            h('div', { class: 'field-map-editor__title' }, props.title),
            props.description ? h('div', { class: 'field-map-editor__desc' }, props.description) : null
          ]),
          h(ElButton, {
            icon: Plus,
            onClick: addRow
          }, '添加字段')
        ]),
        ...fieldMapContent
      ])
    }
  }
})

const loading = ref(false)
const saving = ref(false)
const testing = ref(false)
const eventSaving = ref(false)
const syncingChannel = ref('')
const keyword = ref('')
const list = ref([])
const eventDrawerVisible = ref(false)
const drawerVisible = ref(false)
const selectedEvent = ref(null)
const activeChannel = ref('sms')
const testPayloadText = ref('')
const logDialogVisible = ref(false)
const logLoading = ref(false)
const logs = ref([])
const logTotal = ref(0)
const variableExpanded = ref(false)

const eventFormRef = ref(null)

const eventForm = reactive(createDefaultEventForm())
const editForm = reactive({ channels: [] })
const testReceiver = reactive({ phone: '', email: '', official_openid: '', mini_openid: '' })
const logQuery = reactive({
  event_key: '',
  channel: '',
  status: '',
  page: 1,
  limit: 10
})
const testResult = reactive(createDefaultTestResult())

const channelOptions = ref([
  { label: '短信', value: 'sms' },
  { label: '邮箱推送', value: 'email' },
  { label: '公众号模板消息', value: 'official_account' },
  { label: '小程序订阅消息', value: 'miniapp' },
  { label: '企微BOT', value: 'wecom_bot' },
  { label: '钉钉BOT', value: 'dingtalk_bot' },
  { label: '其他BOT', value: 'custom_bot' }
])
const receiverTypeOptions = ref([
  { label: '用户', value: 'user' },
  { label: '商户', value: 'merchant' },
  { label: '平台管理员', value: 'admin' }
])

const filteredList = computed(() => {
  const text = keyword.value.trim()
  if (!text) return list.value
  return list.value.filter(item => item.event_name.indexOf(text) > -1 || item.event_key.indexOf(text) > -1)
})

const drawerTitle = computed(() => (selectedEvent.value ? `配置${selectedEvent.value.event_name}` : '消息推送配置'))

const visibleEditChannels = computed(() => {
  const channels = editForm.channels || []
  return channels.filter(item => isChannelVisible(item.channel))
})

function createDefaultEventForm() {
  return {
    id: 0,
    event_key: '',
    event_name: '',
    receiver_type: 'user',
    relation_type: 'custom',
    variables: [],
    status: 1,
    sort: 1000,
    remark: ''
  }
}

function createDefaultTestResult() {
  return {
    status: 'idle',
    message: '尚未发送测试消息'
  }
}

async function fetchMeta() {
  try {
    const res = await getMessagePushEvents()
    const data = res.data || {}
    if (Array.isArray(data.channels) && data.channels.length) {
      channelOptions.value = data.channels
    }
    if (Array.isArray(data.receiver_types) && data.receiver_types.length) {
      receiverTypeOptions.value = data.receiver_types
    }
  } catch {
    // 列表接口仍能兜底渲染，元数据失败时保留本地默认选项。
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getMessagePushList()
    const data = res.data || {}
    list.value = data.list || []
  } catch (error) {
    ElMessage.error(error.message || '获取消息推送配置失败')
  } finally {
    loading.value = false
  }
}

function openEventDrawer() {
  Object.assign(eventForm, createDefaultEventForm())
  eventDrawerVisible.value = true
}

function resetEventForm() {
  Object.assign(eventForm, createDefaultEventForm())
  eventSaving.value = false
}

function addEventVariable() {
  eventForm.variables.push({ key: '', label: '', sample: '' })
}

function removeEventVariable(index) {
  eventForm.variables.splice(index, 1)
}

async function handleSaveEvent() {
  eventSaving.value = true
  try {
    await saveMessagePushEvent(buildEventPayload())
    ElMessage.success('消息添加成功')
    eventDrawerVisible.value = false
    await fetchList()
  } catch (error) {
    ElMessage.error(error.message || '保存消息失败')
  } finally {
    eventSaving.value = false
  }
}

function buildEventPayload() {
  const variables = (eventForm.variables || [])
    .filter(item => item.key || item.label || item.sample)
    .map(item => ({
      key: item.key || '',
      label: item.label || '',
      sample: item.sample || ''
    }))

  return {
    id: eventForm.id || 0,
    event_key: eventForm.event_key || '',
    event_name: eventForm.event_name || '',
    receiver_type: eventForm.receiver_type || 'user',
    relation_type: eventForm.relation_type || 'custom',
    variables,
    status: eventForm.status,
    sort: eventForm.sort || 0,
    remark: eventForm.remark || ''
  }
}

function getChannel(row, channel) {
  const channels = row.channels || []
  return channels.find(item => item.channel === channel) || {
    id: 0,
    channel,
    enabled: 0,
    template_code: '',
    external_template_id: '',
    sync_config: {}
  }
}

function getChannelTemplateStatus(channel) {
  if (channel.channel === 'email') {
    return channel.template_code ? '已配置邮件模板' : '未配置邮件模板'
  }
  if (isWebhookChannel(channel.channel)) {
    return channel.sync_config && channel.sync_config.webhook_url ? '已配置Webhook' : '未配置Webhook'
  }
  const templateId = channel.channel === 'sms'
    ? channel.template_code
    : channel.external_template_id
  return templateId ? '已配置模板ID' : '未配置模板ID'
}

function getChannelColumnWidth(channel) {
  if (channel === 'sms') return 170
  if (channel === 'email') return 190
  if (isWebhookChannel(channel)) return 180
  return 210
}

function getChannelLabel(channel) {
  const option = channelOptions.value.find(item => item.value === channel)
  return option ? option.label : channel
}

function isEventChannelConfigurable(event, channel) {
  if (channel === 'sms') {
    return true
  }
  if (channel === 'email') {
    return true
  }
  if (channel === 'official_account' || channel === 'miniapp') {
    return isUserReceiver(event)
  }
  if (isWebhookChannel(channel)) {
    return true
  }
  return false
}

function isWechatTemplateChannel(channel) {
  return channel === 'official_account' || channel === 'miniapp'
}

function isWebhookChannel(channel) {
  return channel === 'wecom_bot' || channel === 'dingtalk_bot' || channel === 'custom_bot'
}

function isTextTemplateChannel(channel) {
  return channel === 'sms' || channel === 'email' || isWebhookChannel(channel)
}

function hasJumpConfig(channel) {
  return channel === 'official_account' || channel === 'miniapp'
}

function isUserReceiver(event) {
  const receiverType = event ? (event.receiver_type || '') : ''
  const receiverText = event ? (event.receiver_text || '') : ''
  return receiverType === 'user' || (!receiverType && receiverText === '用户')
}

async function handleToggle(row, channel, status) {
  if (!isEventChannelConfigurable(row, channel)) return
  const config = getChannel(row, channel)
  if (!config.id) return
  try {
    await setMessagePushStatus(config.id, status)
    ElMessage.success('状态修改成功')
    await fetchList()
  } catch (error) {
    ElMessage.error(error.message || '状态修改失败')
    await fetchList()
  }
}

function handleEdit(row) {
  selectedEvent.value = clone(row)
  editForm.channels = normalizeChannels(row.channels || [])
  activeChannel.value = getFirstVisibleChannel()
  testPayloadText.value = JSON.stringify(buildSamplePayload(selectedEvent.value.variables || []), null, 2)
  variableExpanded.value = false
  Object.assign(testResult, createDefaultTestResult())
  drawerVisible.value = true
}

function normalizeChannels(channels) {
  return channels.map(item => ({
    ...clone(item),
    field_map: normalizeFieldMap(item.field_map),
    jump_config: item.jump_config || {},
    sync_config: normalizeSyncConfig(item)
  }))
}

function normalizeSyncConfig(item) {
  const syncConfig = item.sync_config || {}
  if (item.channel === 'sms') {
    return { sign_name: syncConfig.sign_name || '' }
  }
  if (item.channel === 'email') {
    return {
      subject_template: syncConfig.subject_template || '',
      from_name: syncConfig.from_name || ''
    }
  }
  if (isWebhookChannel(item.channel)) {
    return {
      webhook_url: syncConfig.webhook_url || '',
      secret: syncConfig.secret || '',
      headers: Array.isArray(syncConfig.headers) ? syncConfig.headers : []
    }
  }
  return {}
}

function handleDrawerClosed() {
  selectedEvent.value = null
  editForm.channels = []
  activeChannel.value = 'sms'
  variableExpanded.value = false
  Object.assign(testResult, createDefaultTestResult())
}

function isChannelVisible(channel) {
  return isEventChannelConfigurable(selectedEvent.value, channel)
}

function getFirstVisibleChannel() {
  return visibleEditChannels.value.length ? visibleEditChannels.value[0].channel : ''
}

function handleVariableClick(channel, key) {
  if (isTextTemplateChannel(channel.channel)) {
    insertVariable(channel, key)
    return
  }

  copyText(formatVariableToken(key), '变量已复制')
}

function insertVariable(channel, key) {
  const current = channel.content_template || ''
  channel.content_template = current + formatVariableToken(key, channel.channel)
  ElMessage.success('变量已插入')
}

function updateFieldMap(channel, value) {
  channel.field_map = value
}

function addWebhookHeader(channel) {
  if (!channel.sync_config.headers) {
    channel.sync_config.headers = []
  }
  channel.sync_config.headers.push({ key: '', value: '' })
}

function removeWebhookHeader(channel, index) {
  channel.sync_config.headers.splice(index, 1)
}

async function handleSave(channel) {
  saving.value = true
  try {
    const payload = buildSavePayload(channel)
    await saveMessagePushConfig(payload)
    ElMessage.success('保存成功')
    await fetchList()
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

function buildSavePayload(channel) {
  const payload = {
    event_key: selectedEvent.value.event_key,
    channel: channel.channel,
    enabled: channel.enabled,
    template_code: channel.template_code || '',
    external_template_id: channel.external_template_id || '',
    field_map: normalizeFieldMap(channel.field_map)
  }

  if (channel.channel === 'sms') {
    const syncConfig = channel.sync_config || {}
    payload.content_template = channel.content_template || ''
    payload.sync_config = { sign_name: syncConfig.sign_name || '' }
  }
  if (channel.channel === 'email') {
    const syncConfig = channel.sync_config || {}
    payload.content_template = channel.content_template || ''
    payload.sync_config = {
      subject_template: syncConfig.subject_template || '',
      from_name: syncConfig.from_name || ''
    }
    payload.template_code = channel.template_code || ''
    payload.external_template_id = ''
    payload.field_map = []
  }
  if (channel.channel === 'official_account') {
    const jumpConfig = channel.jump_config || {}
    payload.content_template = ''
    payload.jump_config = {
      url: jumpConfig.url || '',
      mini_appid: jumpConfig.mini_appid || '',
      mini_page_path: jumpConfig.mini_page_path || ''
    }
  }
  if (channel.channel === 'miniapp') {
    payload.content_template = ''
  }
  if (isWebhookChannel(channel.channel)) {
    const syncConfig = channel.sync_config || {}
    payload.content_template = channel.content_template || ''
    payload.template_code = ''
    payload.external_template_id = ''
    payload.field_map = []
    payload.sync_config = {
      webhook_url: syncConfig.webhook_url || '',
      secret: syncConfig.secret || '',
      headers: Array.isArray(syncConfig.headers) ? syncConfig.headers : []
    }
  }

  return payload
}

async function handleSyncChannel(channel) {
  syncingChannel.value = channel
  try {
    const res = await syncMessagePushChannel(channel)
    const data = res.data || {}
    const success = data.success || 0
    const failed = data.failed || 0
    const label = data.channel_label || getChannelLabel(channel)
    const message = `${label}同步完成：成功 ${success} 条，失败 ${failed} 条`

    if (failed > 0) {
      const failedItem = (data.items || []).find(item => item.status === 'failed')
      const failedMessage = failedItem ? `，首个失败：${failedItem.event_name} ${failedItem.message}` : ''
      ElMessage.warning(message + failedMessage)
    } else {
      ElMessage.success(message)
    }

    await fetchList()
  } catch (error) {
    ElMessage.error(error.message || '同步失败')
  } finally {
    syncingChannel.value = ''
  }
}

async function handleTest(channel) {
  testing.value = true
  try {
    const payload = parseTestPayload()
    await saveMessagePushConfig(buildSavePayload(channel))
    await testMessagePushTemplate(channel.id, {
      receiver: testReceiver,
      payload
    })
    Object.assign(testResult, {
      status: 'success',
      message: '测试消息已提交，请查看发送日志确认邮件/微信/短信侧结果'
    })
    ElMessage.success('测试发送已执行')
  } catch (error) {
    Object.assign(testResult, {
      status: 'error',
      message: error.message || '测试发送失败'
    })
    ElMessage.error(error.message || '测试发送失败')
  } finally {
    testing.value = false
  }
}

function parseTestPayload() {
  if (!testPayloadText.value.trim()) return {}
  try {
    return JSON.parse(testPayloadText.value)
  } catch (error) {
    ElMessage.error('测试变量 JSON 格式错误')
    throw error
  }
}

function fillSamplePayload() {
  testPayloadText.value = JSON.stringify(buildSamplePayload(selectedEvent.value.variables || []), null, 2)
}

function buildSamplePayload(variables) {
  const payload = {}
  variables.forEach(item => {
    payload[item.key] = item.sample || ''
  })
  payload.user_id = payload.user_id || 1
  payload.merchant_id = payload.merchant_id || 1
  return payload
}

function openLogDialog(row) {
  logDialogVisible.value = true
  logQuery.event_key = row && row.event_key ? row.event_key : ''
  logQuery.page = 1
  fetchLogs()
}

async function fetchLogs() {
  logLoading.value = true
  try {
    const res = await getMessagePushLogs(logQuery)
    const data = res.data || {}
    logs.value = data.list || []
    logTotal.value = data.total || 0
  } catch (error) {
    ElMessage.error(error.message || '获取发送日志失败')
  } finally {
    logLoading.value = false
  }
}

function handleLogPageChange(page) {
  logQuery.page = page
  fetchLogs()
}

function getTemplateCodeLabel(channel) {
  if (channel === 'sms') return '短信模板Code'
  if (channel === 'email') return '邮件模板标识'
  if (channel === 'official_account') return '公众号短ID'
  return '模板编号'
}

function getTemplateCodePlaceholder(channel) {
  if (channel === 'sms') return '阿里云短信模板 Code'
  if (channel === 'email') return '邮件模板标识，如 verify_code'
  if (channel === 'official_account') return '微信模板库短 ID'
  return '如 1451，仅用于记录微信后台模板编号'
}

function getExternalTemplateIdPlaceholder(channel) {
  if (channel === 'miniapp') return '填写微信后台模板 ID，如 K-5r...64LQ'
  return '填写微信后台模板 ID'
}

function getFieldMapDescription(channel) {
  if (channel === 'official_account') {
    return '字段名填写公众号模板详情中的字段，如 keyword1 / thing1 / amount2；取值方式选“变量”时从内置变量取值，选“文本”时可填写固定内容，也支持在文本中写 {{变量}}，颜色仅公众号可填。'
  }
  if (isWebhookChannel(channel)) {
    return 'Webhook BOT 使用内容模板发送，不需要配置微信字段映射。'
  }
  return '字段名必须以微信模板详情实际展示为准，如 thing1、amount2、date3；字段值选择右侧内置变量，不存在的内容可用「文本」并写固定内容或 {{变量}}。'
}

function getVariableUsageText(channel) {
  if (channel === 'sms') {
    return '点击变量会插入内容模板；手动填写时使用 ${变量名}，与阿里云短信模板变量格式一致。'
  }
  if (isTextTemplateChannel(channel)) {
    return '点击变量会插入内容模板；手动填写时使用 {{变量名}}。'
  }

  return '点击变量会复制 {{变量名}}；字段映射选择「变量」时直接选变量，选择「文本」时可粘贴 {{变量名}}。'
}

function getVariableTagTitle(channel, key) {
  const token = formatVariableToken(key, channel)
  return isTextTemplateChannel(channel) ? `点击插入 ${token}` : `点击复制 ${token}`
}

function formatVariableToken(key, channel) {
  return channel === 'sms' ? `\${${key}}` : `{{${key}}}`
}

function getContentTemplatePlaceholder(channel) {
  if (channel === 'sms') return '支持 ${变量}，如：您的订单 ${order_sn} 已发货'
  return '支持 {{变量}}'
}

function copyContentTemplate(channel) {
  copyText(channel.content_template || '', '模板内容已复制')
}

function copyText(text, successMessage) {
  if (!text) {
    ElMessage.warning('没有可复制的内容')
    return
  }

  if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      ElMessage.success(successMessage || '复制成功')
    }).catch(() => {
      copyTextFallback(text, successMessage)
    })
    return
  }

  copyTextFallback(text, successMessage)
}

function copyTextFallback(text, successMessage) {
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.setAttribute('readonly', 'readonly')
  textarea.style.position = 'fixed'
  textarea.style.left = '-9999px'
  textarea.style.top = '-9999px'
  document.body.appendChild(textarea)
  textarea.select()

  try {
    const copied = document.execCommand('copy')
    if (copied) {
      ElMessage.success(successMessage || '复制成功')
    } else {
      ElMessage.error('复制失败，请手动复制')
    }
  } catch (error) {
    ElMessage.error((error && error.message) || '复制失败，请手动复制')
  } finally {
    document.body.removeChild(textarea)
  }
}

function getVisibleVariables() {
  const variables = selectedEvent.value && Array.isArray(selectedEvent.value.variables)
    ? selectedEvent.value.variables
    : []
  return variableExpanded.value ? variables : variables.slice(0, 10)
}

function hasMoreVariables() {
  const variables = selectedEvent.value && Array.isArray(selectedEvent.value.variables)
    ? selectedEvent.value.variables
    : []
  return variables.length > 10
}

function getTestResultIcon(status) {
  if (status === 'success') return CircleCheck
  if (status === 'error') return CircleClose
  return Remove
}

function getTestResultLabel(status) {
  if (status === 'success') return '已发送'
  if (status === 'error') return '发送失败'
  return '未发送'
}

function getLogStatusType(status) {
  if (status === 1) return 'success'
  if (status === 2) return 'danger'
  if (status === 3) return 'info'
  return 'warning'
}

function clone(data) {
  return JSON.parse(JSON.stringify(data || {}))
}

onBeforeMount(() => {
  fetchMeta()
  fetchList()
})
</script>

<style lang="scss" scoped>
.message-push {
  min-height: 100%;
  box-sizing: border-box;

  &__search {
    width: 260px;
  }
}

.page-curator-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-bottom: 20px;
}

.page-curator-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.page-curator-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  flex-shrink: 0;
}

.message-push-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

.search-form-wrap {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--ds-outline-variant);

  :deep(.el-form-item) {
    margin-bottom: 0;
  }
}

:deep(.btn-curator-primary.el-button--primary) {
  background: var(--ds-primary);
  border-color: var(--ds-primary);
  color: var(--ds-on-primary);

  &:hover, &:focus {
    background: var(--ds-primary-hover);
    border-color: var(--ds-primary-hover);
  }
}

:deep(.btn-curator-reset) {
  background: var(--ds-surface-card);
  border-color: var(--ds-outline);
  color: var(--ds-on-surface);

  &:hover, &:focus {
    background: var(--ds-surface-low);
    border-color: var(--ds-primary);
    color: var(--ds-primary);
  }
}

:deep(.message-push__table a) {
  color: var(--ds-primary);
  cursor: pointer;
}

.event-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

.event-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
  color: var(--ds-on-surface-variant);
  font-size: 12px;
}

.channel-cell {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 36px;

  &__text {
    color: var(--ds-on-surface-variant);
    font-size: 12px;
  }

  &__stack {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    font-size: 12px;
    color: var(--ds-on-surface-variant);

    span {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 150px;
    }
  }

  &__empty {
    display: inline-flex;
    align-items: center;
    min-height: 36px;
    color: var(--ds-on-surface-variant);
    font-size: 13px;
  }
}

:deep(.message-push-drawer) {
  display: flex;
  flex-direction: column;
  min-width: 980px;
  max-width: 1840px;
  background: var(--ds-surface-card, #fff);
}

:deep(.message-event-drawer) {
  display: flex;
  flex-direction: column;
  background: var(--ds-surface-card, #fff);
}

:deep(.message-event-drawer .el-drawer__header) {
  margin-bottom: 0;
  padding: 18px 28px 22px;
  border-bottom: 1px solid var(--ds-outline);
  background: var(--ds-surface-card, #fff);
  color: var(--ds-on-surface);
  font-size: 16px;
  font-weight: 600;
}

:deep(.message-event-drawer .el-drawer__body) {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.event-drawer-body {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  padding: 22px 28px 0;
  box-sizing: border-box;
}

.event-form {
  flex: 1;
  min-height: 0;
  overflow-y: auto;

  &__select {
    width: 100%;
  }
}

.event-variable-editor,
.webhook-header-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.event-variable-row,
.webhook-header-row {
  display: grid;
  gap: 8px;
  align-items: center;

  &__delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid var(--ds-outline);
    border-radius: 4px;
    background: var(--ds-surface-card, #fff);
    color: var(--ds-on-surface-variant);
    cursor: pointer;

    &:hover {
      color: var(--dashboard-danger, #f56c6c);
      border-color: var(--dashboard-danger, #f56c6c);
    }
  }
}

.event-variable-row {
  grid-template-columns: minmax(90px, 1fr) minmax(90px, 1fr) minmax(90px, 1fr) 32px;
}

.webhook-header-row {
  grid-template-columns: minmax(130px, 1fr) minmax(160px, 1.2fr) 32px;
}

.event-drawer-footer {
  margin: 0 -28px;
}

:deep(.message-push-drawer .el-drawer__header) {
  margin-bottom: 0;
  padding: 18px 28px 22px;
  border-bottom: 1px solid var(--ds-outline);
  background: var(--ds-surface-card, #fff);
  color: var(--ds-on-surface);
  font-size: 16px;
  font-weight: 600;
}

:deep(.message-push-drawer .el-drawer__close-btn) {
  color: var(--ds-on-surface-variant);
}

.event-panel {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: space-between;
  margin: 8px 0 18px;

  &__title {
    font-size: 18px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  &__desc {
    margin-top: 6px;
    color: var(--ds-on-surface);
    font-size: 13px;
    font-weight: 600;
  }
}

:deep(.message-push__tabs.el-tabs--border-card) {
  border-color: var(--ds-outline);
  background: var(--ds-surface-card, #fff);
  box-shadow: none;
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__header) {
  background: var(--ds-surface-card, #fff);
  border-bottom-color: var(--ds-outline);
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__content) {
  padding: 0;
  background: var(--ds-surface-card, #fff);
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__header .el-tabs__item) {
  min-width: 112px;
  height: 48px;
  line-height: 48px;
  color: var(--ds-on-surface);
  background: var(--ds-surface-card, #fff);
  border-right-color: var(--ds-outline);
  border-left-color: transparent;
  text-align: center;
  font-weight: 600;
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__header .el-tabs__item:hover) {
  color: var(--ds-primary);
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__header .el-tabs__item.is-active) {
  color: var(--ds-primary);
  background: var(--ds-surface-card, #fff);
  border-right-color: var(--ds-outline);
  border-left-color: var(--ds-outline);
  border-bottom-color: var(--ds-surface-card, #fff);
}

.channel-form {
  overflow-x: hidden;
  overflow-y: auto;
  padding: 0;
  flex: 1;
  min-height: 0;
}

.drawer-config-form {
  display: flex;
  flex-direction: column;
}

:deep(.drawer-config-form .el-input__inner),
:deep(.drawer-config-form .el-textarea__inner) {
  border-color: var(--ds-outline);
  border-radius: 4px;
  color: var(--ds-on-surface);
}

:deep(.drawer-config-form .el-form-item) {
  margin-bottom: 14px;
}

:deep(.drawer-config-form .el-form-item__label) {
  color: var(--ds-on-surface-variant);
  font-weight: 600;
}

.drawer-card {
  padding: 18px 22px;
  border: 1px solid var(--ds-outline);
  border-top: 0;
  background: var(--ds-surface-card, #fff);
  box-sizing: border-box;

  &--basic {
    border-top: 1px solid var(--ds-outline);
  }

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
  }

  &__title {
    margin-bottom: 14px;
    font-size: 15px;
    line-height: 1.4;
    font-weight: 600;
    color: var(--ds-on-surface);
  }
}

.drawer-card__head .drawer-card__title {
  margin-bottom: 0;
}

.basic-config-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  column-gap: 52px;
  row-gap: 12px;

  :deep(.el-form-item) {
    margin-bottom: 0;
  }

  :deep(.el-form-item:first-child) {
    grid-column: 1 / -1;
  }
}

.drawer-form-full {
  margin-top: 14px;
}

.form-item-with-tip {
  :deep(.el-form-item__content) {
    line-height: 1.4;
  }
}

.input-tip {
  margin-top: 7px;
  color: var(--ds-on-surface-variant);
  font-size: 12px;
  line-height: 1.5;
}

.content-template-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 8px;

  .input-tip {
    margin-top: 0;
  }
}

.webhook-config-grid {
  display: flex;
  flex-direction: column;
  margin-top: 14px;
}

.field-map-card {
  padding-bottom: 18px;
}

.variables-card {
  padding: 16px 22px;
}

.variables-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 14px;
}

.variables-card__intro {
  min-width: 0;
}

.variables-card__head .drawer-card__title {
  margin-bottom: 6px;
}

.variables-card__usage {
  color: var(--ds-on-surface-variant);
  font-size: 12px;
  line-height: 1.6;
}

.variables-card__more {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--ds-on-surface-variant);
  font-size: 13px;
  line-height: 1;
  cursor: pointer;
}

.variable-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  min-height: 28px;
}

.variable-tag {
  cursor: pointer;
}

:deep(.variable-tag.el-tag) {
  height: 28px;
  padding: 0 16px;
  border-color: var(--ds-primary);
  border-radius: 15px;
  background: var(--ds-surface-card, #fff);
  color: var(--ds-primary);
  font-weight: 600;
  line-height: 26px;

  &:hover {
    background: var(--ds-surface-low);
    border-color: var(--ds-primary-hover);
    color: var(--ds-primary-hover);
  }
}

.drawer-bottom-grid {
  display: grid;
  grid-template-columns: minmax(280px, 0.86fr) minmax(0, 1.7fr);
}

.drawer-bottom-grid--single {
  grid-template-columns: minmax(0, 1fr);
}

.drawer-bottom-grid .drawer-card {
  min-height: 208px;
}

.drawer-bottom-grid .drawer-card + .drawer-card {
  border-left: 0;
}

.channel-help {
  margin-top: 8px;
  color: var(--ds-on-surface-variant);
  font-size: 13px;
  line-height: 1.8;
}

.test-card {
  display: flex;
  flex-direction: column;

  &__content {
    display: grid;
    grid-template-columns: minmax(220px, 0.78fr) minmax(360px, 1.45fr);
    column-gap: 20px;
    align-items: start;
  }

  &__accounts {
    min-width: 0;
  }

  &__payload {
    min-width: 0;
  }
}

:deep(.test-card__payload .el-form-item__label) {
  width: 112px !important;
  white-space: nowrap;
}

:deep(.test-card__payload .el-form-item__content) {
  margin-left: 112px !important;
}

.side-actions {
  display: flex;
  grid-column: 2;
  justify-content: flex-end;
  margin-top: 10px;
}

.test-result {
  margin-top: 12px;

  &__title {
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  &__state {
    display: flex;
    align-items: center;
    min-height: 32px;
    padding: 0 12px;
    border: 1px solid var(--ds-outline);
    border-radius: 16px;
    background: var(--ds-surface-card, #fff);
    color: var(--ds-on-surface-variant);
    font-size: 12px;
    line-height: 1.4;

    i {
      margin-right: 6px;
      font-size: 14px;
    }

    span {
      flex-shrink: 0;
      margin-right: 18px;
      font-weight: 600;
    }

    em {
      min-width: 0;
      overflow: hidden;
      color: var(--ds-on-surface-variant);
      font-style: normal;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }

  &__state--success {
    border-color: var(--dashboard-success, #67c23a);
    background: var(--ds-surface-card, #fff);
    color: var(--dashboard-success, #67c23a);
  }

  &__state--error {
    border-color: var(--dashboard-danger, #f56c6c);
    background: var(--ds-surface-card, #fff);
    color: var(--dashboard-danger, #f56c6c);
  }
}

.drawer-footer {
  display: flex;
  flex-shrink: 0;
  justify-content: flex-end;
  gap: 16px;
  margin: 0 -28px;
  padding: 18px 28px;
  border-top: 1px solid var(--ds-outline);
  background: var(--ds-surface-card, #fff);
}

:deep(.drawer-footer .el-button) {
  min-width: 104px;
}

:deep(.field-map-editor) {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

:deep(.field-map-editor__header) {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 14px;
}

:deep(.field-map-editor__intro) {
  min-width: 0;
  flex: 1;
}

:deep(.field-map-editor__title) {
  margin-bottom: 8px;
  font-size: 15px;
  line-height: 1.4;
  font-weight: 600;
  color: var(--ds-on-surface);
}

:deep(.field-map-editor__desc) {
  padding: 0 2px;
  color: var(--ds-on-surface-variant);
  font-size: 13px;
  line-height: 1.7;
}

:deep(.field-map-editor__columns),
:deep(.field-map-row) {
  display: grid;
  align-items: center;
  min-width: 0;
  column-gap: 16px;
}

:deep(.field-map-editor__columns) {
  min-height: 44px;
  padding: 0 16px;
  color: var(--ds-on-surface);
  background: var(--ds-surface-low);
  font-size: 13px;
  font-weight: 600;
}

:deep(.field-map-editor__columns--with-color),
:deep(.field-map-row--with-color) {
  grid-template-columns: minmax(130px, 1fr) minmax(130px, 0.9fr) minmax(260px, 1.7fr) 96px 32px;
}

:deep(.field-map-editor__columns--no-color),
:deep(.field-map-row--no-color) {
  grid-template-columns: minmax(150px, 1fr) minmax(150px, 0.9fr) minmax(300px, 1.8fr) 32px;
}

:deep(.field-map-editor__body) {
  display: flex;
  flex-direction: column;
}

:deep(.field-map-row) {
  min-height: 54px;
  padding: 8px 16px;
  border-top: 1px solid var(--ds-outline);
}

:deep(.field-map-row .el-input),
:deep(.field-map-row .el-select) {
  min-width: 0;
  width: 100%;
}

:deep(.field-map-row .el-input__inner) {
  height: 32px;
  line-height: 32px;
  padding-left: 10px;
  padding-right: 10px;
}

:deep(.field-map-row__color) {
  display: flex;
  align-items: center;
  width: 100%;
  height: 32px;
}

:deep(.field-map-row__color .el-color-picker__trigger) {
  width: 100%;
  height: 32px;
  padding: 3px;
  border-color: var(--ds-outline);
  border-radius: 4px;
  box-sizing: border-box;
}

:deep(.field-map-row__color .el-color-picker__color) {
  border-radius: 3px;
}

:deep(.field-map-row__color .el-color-picker__color-inner) {
  border-radius: 2px;
}

:deep(.field-map-row__delete) {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  color: var(--ds-on-surface-variant);
  cursor: pointer;
  user-select: none;
  font-size: 12px;
  cursor: pointer;

  &:hover {
    color: var(--dashboard-danger, #f56c6c);
  }
}

:deep(.field-map-row__delete i) {
  line-height: 1;
}

:deep(.field-map-editor__empty) {
  padding: 14px 16px;
  border-top: 1px solid var(--ds-outline);
  color: var(--ds-on-surface-variant);
  font-size: 13px;
}

.danger-text {
  color: var(--dashboard-danger, #f56c6c);
}

.log-toolbar {
  display: flex;
  gap: 8px;
  margin-bottom: 12px;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding-top: 12px;
}

:deep(.message-push-drawer .el-drawer__body) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

.drawer-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
  padding: 0 28px;
  box-sizing: border-box;
}

:deep(.message-push__tabs) {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__header) {
  flex-shrink: 0;
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__content) {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__content > .el-tab-pane) {
  flex: 1;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

:deep(.message-push__tabs.el-tabs--border-card > .el-tabs__content > .el-tab-pane:not([style*="display: none"])) {
  display: flex !important;
}

@media (max-width: 1280px) {
  :deep(.message-push-drawer) {
    min-width: calc(100vw - 80px);
  }

  .test-card__content {
    grid-template-columns: minmax(190px, 0.85fr) minmax(280px, 1.4fr);
    column-gap: 14px;
  }
}
</style>
