<template>
  <div class="designer-container">
    <!-- 顶部操作栏 -->
    <div class="designer-toolbar">
      <div class="toolbar-left">
        <el-button icon="el-icon-arrow-left" @click="handleBack">返回配置列表</el-button>
        <el-divider direction="vertical" />
        <span class="toolbar-title">表单设计器</span>
        <el-tag v-if="configName" size="medium" type="info">{{ configName }}</el-tag>
      </div>
      <div class="toolbar-right">
        <el-button icon="el-icon-view" @click="handlePreview">预览</el-button>
        <el-button icon="el-icon-document" @click="handleExportJson">导出JSON</el-button>
        <el-button icon="el-icon-delete" @click="handleClear">清空</el-button>
        <el-button type="primary" icon="el-icon-check" :loading="saving" @click="handleSave">保存配置</el-button>
      </div>
    </div>

    <div class="designer-body">
      <!-- 左侧组件面板 -->
      <div class="designer-panel panel-left">
        <div class="panel-title panel-title--with-action">
          <span>组件库</span>
        </div>
        <div v-if="aiPanelOpen" class="ai-config-panel">
          <div class="ai-config-header">
            <div class="ai-config-heading">
              <div class="ai-config-title"><LegacyIcon name="el-icon-magic-stick" />AI帮我配置</div>
              <div class="ai-config-subtitle">按当前字段和组件生成表单</div>
            </div>
            <el-tooltip content="缩小到底部">
              <el-button type="text" icon="el-icon-minus" :disabled="aiSending" @click="handleMinimizeAiPanel" />
            </el-tooltip>
          </div>
          <div ref="aiMsgList" class="ai-config-messages">
            <div v-if="aiMessages.length === 0" class="ai-config-empty">
              <LegacyIcon name="el-icon-chat-dot-round" />
              <div>描述你想要的配置布局</div>
              <p>例如：把支付配置分成基础信息、密钥配置、回调地址三个卡片。</p>
            </div>
            <div
              v-for="msg in aiMessages"
              :key="msg._id"
              class="ai-message"
              :class="'ai-message--' + msg.role"
            >
              <div class="ai-message-role">{{ msg.role === 'user' ? '我' : 'AI' }}</div>
              <div class="ai-message-bubble">
                <span v-if="msg.loading && !msg.content" class="ai-typing"><span /><span /><span /></span>
                <template v-else>{{ msg.content }}</template>
              </div>
              <div v-if="msg.newFieldKeys && msg.newFieldKeys.length > 0" class="ai-message-tags">
                <el-tag v-for="key in msg.newFieldKeys" :key="key" size="mini" type="warning">{{ key }}</el-tag>
              </div>
              <div v-if="msg.warnings && msg.warnings.length > 0" class="ai-message-warnings">
                <div v-for="warning in msg.warnings" :key="warning">{{ warning }}</div>
              </div>
            </div>
          </div>
          <div
            v-if="aiDraftFields.length > 0"
            class="ai-draft-bar"
            :class="{ 'is-collapsed': aiDraftCollapsed }"
          >
            <div class="ai-draft-header">
              <div class="ai-draft-info">
                <span>已生成 {{ aiDraftFieldTotal }} 个组件</span>
                <span v-if="aiDraftNewFieldKeys.length > 0">含 {{ aiDraftNewFieldKeys.length }} 个AI新增字段</span>
              </div>
              <el-tooltip :content="aiDraftCollapsed ? '展开草稿' : '折叠草稿'">
                <el-button
                  type="text"
                  size="mini"
                  :icon="aiDraftCollapsed ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"
                  class="ai-draft-toggle"
                  @click="aiDraftCollapsed = !aiDraftCollapsed"
                />
              </el-tooltip>
            </div>
            <div v-show="!aiDraftCollapsed" class="ai-draft-body">
              <div v-if="aiDraftNewFieldKeys.length > 0" class="ai-draft-tags">
                <el-tag v-for="key in aiDraftNewFieldKeys" :key="key" size="mini" type="warning">{{ key }}</el-tag>
              </div>
              <div class="ai-draft-actions">
                <el-button size="mini" icon="el-icon-document" @click="handlePreviewAiDraft">JSON</el-button>
                <el-button type="primary" size="mini" icon="el-icon-check" @click="handleApplyAiDraft">一键配置</el-button>
              </div>
            </div>
          </div>
          <div class="ai-config-input">
            <el-input
              ref="aiInputRef"
              v-model="aiInputText"
              type="textarea"
              :autosize="{ minRows: 2, maxRows: 4 }"
              resize="none"
              placeholder="输入配置需求，Enter发送，Shift+Enter换行"
              :disabled="aiSending"
              @compositionstart="handleAiCompositionStart"
              @compositionend="handleAiCompositionEnd"
              @keydown.enter="handleAiEnter"
            />
            <span
              class="ai-send-button"
              :class="{ 'is-disabled': aiSending || !aiInputText.trim() }"
              role="button"
              aria-label="发送"
              :aria-disabled="aiSending || !aiInputText.trim() ? 'true' : 'false'"
              :tabindex="aiSending || !aiInputText.trim() ? -1 : 0"
              @click="handleSendAiMessage"
              @keydown.enter.prevent="handleSendAiMessage"
              @keydown.space.prevent="handleSendAiMessage"
            >
              <LegacyIcon :name="aiSending ? 'el-icon-loading' : 'el-icon-s-promotion'" />
            </span>
          </div>
        </div>
        <template v-else>
          <div class="component-groups">
            <!-- 配置字段（预定义） -->
            <div v-if="availableDefinedFields.length > 0 || definedFields.length > 0" class="component-group">
              <div class="group-title">配置字段</div>
              <el-empty v-if="availableDefinedFields.length === 0" description="字段已全部加入画布" :image-size="50" />
              <draggable
                v-else
                item-key="field"
                :list="availableDefinedFields"
                :group="{ name: 'formDesigner', pull: 'clone', put: false }"
                :sort="false"
                :clone="cloneDefinedField"
                class="component-list component-list--defined"
              >
                <template #item="{ element: item }">
                  <div
                    :key="item.field"
                    class="component-item component-item--defined"
                  >
                    <LegacyIcon :name="getFieldTypeIcon(item.type)" />
                    <span class="item-label">{{ item.label }}</span>
                    <span class="item-field">{{ item.field }}</span>
                  </div>
                </template>
              </draggable>
            </div>
            <div class="component-group">
              <div class="group-title">基础组件</div>
              <draggable
                item-key="type"
                :list="basicComponents"
                :group="{ name: 'formDesigner', pull: 'clone', put: false }"
                :sort="false"
                :clone="cloneComponent"
                class="component-list"
              >
                <template #item="{ element: item }">
                  <div
                    :key="item.type"
                    class="component-item"
                  >
                    <LegacyIcon :name="item.icon" />
                    <span>{{ item.label }}</span>
                  </div>
                </template>
              </draggable>
            </div>
            <div class="component-group">
              <div class="group-title">布局组件</div>
              <draggable
                item-key="type"
                :list="layoutComponents"
                :group="{ name: 'formDesigner', pull: 'clone', put: false }"
                :sort="false"
                :clone="cloneComponent"
                class="component-list"
              >
                <template #item="{ element: item }">
                  <div
                    :key="item.type"
                    class="component-item"
                  >
                    <LegacyIcon :name="item.icon" />
                    <span>{{ item.label }}</span>
                  </div>
                </template>
              </draggable>
            </div>
          </div>
          <div class="ai-config-dock" @click="handleOpenAiPanel">
            <LegacyIcon name="el-icon-magic-stick" />
            <div>
              <div class="ai-config-dock-title">AI帮我配置</div>
              <div class="ai-config-dock-desc">多轮对话生成表单JSON</div>
            </div>
          </div>
        </template>
      </div>

      <!-- 中间画布 -->
      <div class="designer-panel panel-center">
        <div class="panel-title">表单画布</div>
        <div class="canvas-wrapper">
          <draggable
            v-model="formFields"
            item-key="_id"
            :group="{ name: 'formDesigner', put: true }"
            ghost-class="ghost-item"
            chosen-class="chosen-item"
            animation="200"
            handle=".drag-handle"
            class="canvas-area"
            @add="handleFieldAdd"
          >
            <template #item="{ element: field, index }">
              <div
                v-show="isCanvasFieldVisible(field)"
                :key="field._id"
                class="canvas-field"
                :class="{ 'is-active': activeFieldId === field._id }"
                @click="handleSelectField(field)"
              >
                <div class="field-header">
                  <LegacyIcon name="el-icon-rank" class=" drag-handle" />
                  <span class="field-type-tag">{{ field.label }}</span>
                  <el-tag v-if="field.props && field.props.aiNewField" size="mini" type="warning">AI新增</el-tag>
                  <span v-if="field.props.label" class="field-label">{{ field.props.label }}</span>
                </div>
                <div class="field-preview" @click.stop="handleSelectField(field)">
                  <!-- 文本框预览 -->
                  <template v-if="field.type === 'input'">
                    <div class="input-with-actions">
                      <el-input
                        v-model="field.props.defaultValue"
                        :placeholder="field.props.placeholder || '请输入默认值'"
                        :disabled="field.props.disabled"
                      />
                      <div v-if="field.props.showCopy || field.props.showRandom || field.props.fetchEnabled" class="input-actions">
                        <el-button v-if="field.props.fetchEnabled" type="primary" size="mini" icon="el-icon-download">
                          {{ field.props.fetchButtonText || '获取' }}
                        </el-button>
                        <el-button v-if="field.props.showCopy" type="primary" size="mini" icon="el-icon-document-copy">复制</el-button>
                        <el-button v-if="field.props.showRandom" type="primary" size="mini" icon="el-icon-refresh">随机</el-button>
                      </div>
                    </div>
                  </template>
                  <!-- 多行文本框预览 -->
                  <template v-else-if="field.type === 'textarea'">
                    <el-input v-model="field.props.defaultValue" type="textarea" :rows="field.props.rows || 3" :placeholder="field.props.placeholder || '请输入默认值'" />
                  </template>
                  <!-- 数字组件预览 -->
                  <template v-else-if="field.type === 'number'">
                    <el-input-number v-model="field.props.defaultValue" :min="field.props.min" :max="field.props.max" :step="field.props.step" controls-position="right" style="width: 100%" />
                  </template>
                  <!-- 开关组件预览 -->
                  <template v-else-if="field.type === 'switch'">
                    <el-switch
                      v-model="canvasFormData[field.props.field]"
                      :active-text="field.props.activeText || '开启'"
                      :inactive-text="field.props.inactiveText || '关闭'"
                      :active-value="getSwitchActiveValue(field)"
                      :inactive-value="getSwitchInactiveValue(field)"
                      :disabled="field.props.disabled"
                    />
                    <div v-if="hasEnabledLinkage(field)" class="linkage-tip">
                      <LegacyIcon name="el-icon-connection" />
                      已配置联动规则
                    </div>
                  </template>
                  <!-- 单选组件预览 -->
                  <template v-else-if="field.type === 'radio'">
                    <el-radio-group v-model="canvasFormData[field.props.field]">
                      <el-radio v-for="opt in (field.props.options || defaultRadioOptions)" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>
                    <div v-if="hasEnabledLinkage(field)" class="linkage-tip">
                      <LegacyIcon name="el-icon-connection" />
                      已配置联动规则
                    </div>
                  </template>
                  <!-- 多选组件预览 -->
                  <template v-else-if="field.type === 'checkbox'">
                    <el-checkbox-group v-model="field.props.defaultValue">
                      <el-checkbox v-for="opt in (field.props.options || defaultCheckboxOptions)" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                    </el-checkbox-group>
                  </template>
                  <!-- 上传组件预览 -->
                  <template v-else-if="field.type === 'upload'">
                    <div class="upload-preview-box">
                      <div class="upload-item">
                        <LegacyIcon name="el-icon-plus" class=" upload-icon" />
                      </div>
                    </div>
                  </template>
                  <!-- 卡片组件预览 -->
                  <template v-else-if="field.type === 'card'">
                    <el-card shadow="hover" class="preview-card">
                      <template #header>
                        <div>{{ field.props.title || '卡片标题' }}</div>
                      </template>
                      <div class="card-content-area">
                        <draggable
                          v-model="field.props.children"
                          item-key="_id"
                          :group="{ name: 'formDesigner', put: true }"
                          :move="checkNestedMove"
                          ghost-class="ghost-item"
                          chosen-class="chosen-item"
                          animation="200"
                          handle=".drag-handle"
                          class="card-children-area"
                          @add="handleCardChildAdd($event, field)"
                        >
                          <template #item="{ element: child, index: childIndex }">
                            <div
                              v-show="isCanvasFieldVisible(child)"
                              :key="child._id"
                              class="canvas-field card-child-field"
                              :class="{ 'is-active': activeFieldId === child._id }"
                              @click.stop="handleSelectField(child)"
                            >
                              <div class="field-header">
                                <LegacyIcon name="el-icon-rank" class=" drag-handle" />
                                <span class="field-type-tag">{{ child.label }}</span>
                                <el-tag v-if="child.props && child.props.aiNewField" size="mini" type="warning">AI新增</el-tag>
                                <span v-if="child.props.label" class="field-label">{{ child.props.label }}</span>
                              </div>
                              <div class="field-preview" @click.stop="handleSelectField(child)">
                                <template v-if="child.type === 'input'">
                                  <div class="input-with-actions">
                                    <el-input v-model="child.props.defaultValue" :placeholder="child.props.placeholder || '请输入默认值'" :disabled="child.props.disabled" />
                                    <div v-if="child.props.showCopy || child.props.showRandom || child.props.fetchEnabled" class="input-actions">
                                      <el-button v-if="child.props.fetchEnabled" type="primary" size="mini" icon="el-icon-download">
                                        {{ child.props.fetchButtonText || '获取' }}
                                      </el-button>
                                      <el-button v-if="child.props.showCopy" type="primary" size="mini" icon="el-icon-document-copy">复制</el-button>
                                      <el-button v-if="child.props.showRandom" type="primary" size="mini" icon="el-icon-refresh">随机</el-button>
                                    </div>
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'textarea'">
                                  <el-input v-model="child.props.defaultValue" type="textarea" :rows="child.props.rows || 3" :placeholder="child.props.placeholder || '请输入默认值'" />
                                </template>
                                <template v-else-if="child.type === 'number'">
                                  <el-input-number v-model="child.props.defaultValue" :min="child.props.min" :max="child.props.max" :step="child.props.step" controls-position="right" style="width: 100%" />
                                </template>
                                <template v-else-if="child.type === 'switch'">
                                  <el-switch
                                    v-model="canvasFormData[child.props.field]"
                                    :active-text="child.props.activeText || '开启'"
                                    :inactive-text="child.props.inactiveText || '关闭'"
                                    :active-value="getSwitchActiveValue(child)"
                                    :inactive-value="getSwitchInactiveValue(child)"
                                    :disabled="child.props.disabled"
                                  />
                                  <div v-if="hasEnabledLinkage(child)" class="linkage-tip">
                                    <LegacyIcon name="el-icon-connection" />已配置联动规则
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'radio'">
                                  <el-radio-group v-model="canvasFormData[child.props.field]">
                                    <el-radio v-for="opt in (child.props.options || [])" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                                  </el-radio-group>
                                  <div v-if="hasEnabledLinkage(child)" class="linkage-tip">
                                    <LegacyIcon name="el-icon-connection" />已配置联动规则
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'checkbox'">
                                  <el-checkbox-group v-model="child.props.defaultValue">
                                    <el-checkbox v-for="opt in (child.props.options || [])" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                                  </el-checkbox-group>
                                </template>
                                <template v-else-if="child.type === 'upload'">
                                  <div class="upload-preview-box">
                                    <div class="upload-item"><LegacyIcon name="el-icon-plus" class=" upload-icon" /></div>
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'alert'">
                                  <el-alert :title="child.props.title || '提示信息'" :type="child.props.alertType || 'info'" :description="child.props.description || ''" :closable="false" show-icon />
                                </template>
                              </div>
                              <div v-if="child.props.description && child.type !== 'alert'" class="field-description" v-html="renderSafeDescription(child.props.description)" />
                              <div class="field-actions">
                                <LegacyIcon name="el-icon-document-copy" title="复制" @click.stop="handleCopyCardChild(field, childIndex)" />
                                <LegacyIcon name="el-icon-delete" title="删除" @click.stop="handleRemoveCardChild(field, childIndex)" />
                              </div>
                            </div>
                          </template>
                          <template #footer>
                            <div v-if="!field.props.children || field.props.children.length === 0" class="card-empty-tip">
                              <span>拖拽组件到此处</span>
                            </div>
                          </template>
                        </draggable>
                      </div>
                    </el-card>
                  </template>
                  <!-- Tabs 组件预览 -->
                  <template v-else-if="field.type === 'tabs'">
                    <el-tabs v-model="field.props.activeTab" :type="field.props.tabType || ''" class="designer-tabs">
                      <el-tab-pane
                        v-for="(tab, tabIndex) in field.props.tabs"
                        :key="tab._id"
                        :label="tab.name"
                        :name="tab._id"
                      >
                        <draggable
                          v-model="tab.children"
                          item-key="_id"
                          :group="{ name: 'formDesigner', put: true }"
                          :move="checkNestedMove"
                          ghost-class="ghost-item"
                          chosen-class="chosen-item"
                          animation="200"
                          handle=".drag-handle"
                          class="tab-children-area"
                          @add="handleTabChildAdd($event, tab)"
                        >
                          <template #item="{ element: child, index: childIndex }">
                            <div
                              v-show="isCanvasFieldVisible(child)"
                              :key="child._id"
                              class="canvas-field card-child-field"
                              :class="{ 'is-active': activeFieldId === child._id }"
                              @click.stop="handleSelectField(child)"
                            >
                              <div class="field-header">
                                <LegacyIcon name="el-icon-rank" class=" drag-handle" />
                                <span class="field-type-tag">{{ child.label }}</span>
                                <el-tag v-if="child.props && child.props.aiNewField" size="mini" type="warning">AI新增</el-tag>
                                <span v-if="child.props.label" class="field-label">{{ child.props.label }}</span>
                              </div>
                              <div class="field-preview" @click.stop="handleSelectField(child)">
                                <template v-if="child.type === 'input'">
                                  <div class="input-with-actions">
                                    <el-input v-model="child.props.defaultValue" :placeholder="child.props.placeholder || '请输入默认值'" :disabled="child.props.disabled" />
                                    <div v-if="child.props.showCopy || child.props.showRandom || child.props.fetchEnabled" class="input-actions">
                                      <el-button v-if="child.props.fetchEnabled" type="primary" size="mini" icon="el-icon-download">
                                        {{ child.props.fetchButtonText || '获取' }}
                                      </el-button>
                                      <el-button v-if="child.props.showCopy" type="primary" size="mini" icon="el-icon-document-copy">复制</el-button>
                                      <el-button v-if="child.props.showRandom" type="primary" size="mini" icon="el-icon-refresh">随机</el-button>
                                    </div>
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'textarea'">
                                  <el-input v-model="child.props.defaultValue" type="textarea" :rows="child.props.rows || 3" :placeholder="child.props.placeholder || '请输入默认值'" />
                                </template>
                                <template v-else-if="child.type === 'number'">
                                  <el-input-number v-model="child.props.defaultValue" :min="child.props.min" :max="child.props.max" :step="child.props.step" controls-position="right" style="width: 100%" />
                                </template>
                                <template v-else-if="child.type === 'switch'">
                                  <el-switch
                                    v-model="canvasFormData[child.props.field]"
                                    :active-text="child.props.activeText || '开启'"
                                    :inactive-text="child.props.inactiveText || '关闭'"
                                    :active-value="getSwitchActiveValue(child)"
                                    :inactive-value="getSwitchInactiveValue(child)"
                                    :disabled="child.props.disabled"
                                  />
                                  <div v-if="hasEnabledLinkage(child)" class="linkage-tip">
                                    <LegacyIcon name="el-icon-connection" />已配置联动规则
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'radio'">
                                  <el-radio-group v-model="canvasFormData[child.props.field]">
                                    <el-radio v-for="opt in (child.props.options || [])" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                                  </el-radio-group>
                                  <div v-if="hasEnabledLinkage(child)" class="linkage-tip">
                                    <LegacyIcon name="el-icon-connection" />已配置联动规则
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'checkbox'">
                                  <el-checkbox-group v-model="child.props.defaultValue">
                                    <el-checkbox v-for="opt in (child.props.options || [])" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                                  </el-checkbox-group>
                                </template>
                                <template v-else-if="child.type === 'upload'">
                                  <div class="upload-preview-box">
                                    <div class="upload-item"><LegacyIcon name="el-icon-plus" class=" upload-icon" /></div>
                                  </div>
                                </template>
                                <template v-else-if="child.type === 'alert'">
                                  <el-alert :title="child.props.title || '提示信息'" :type="child.props.alertType || 'info'" :description="child.props.description || ''" :closable="false" show-icon />
                                </template>
                              </div>
                              <div v-if="child.props.description && child.type !== 'alert'" class="field-description" v-html="renderSafeDescription(child.props.description)" />
                              <div class="field-actions">
                                <LegacyIcon name="el-icon-document-copy" title="复制" @click.stop="handleCopyTabChild(field, tabIndex, childIndex)" />
                                <LegacyIcon name="el-icon-delete" title="删除" @click.stop="handleRemoveTabChild(field, tabIndex, childIndex)" />
                              </div>
                            </div>
                          </template>
                          <template #footer>
                            <div v-if="!tab.children || tab.children.length === 0" class="tab-empty-tip">
                              <span>拖拽组件到此处</span>
                            </div>
                          </template>
                        </draggable>
                      </el-tab-pane>
                    </el-tabs>
                  </template>
                  <!-- 警告组件预览 -->
                  <template v-else-if="field.type === 'alert'">
                    <el-alert
                      :title="field.props.title || '提示信息'"
                      :type="field.props.alertType || 'info'"
                      :description="field.props.description || ''"
                      :closable="false"
                      show-icon
                    />
                  </template>
                </div>
                <!-- 说明文字 -->
                <div
                  v-if="field.props.description && !['card', 'tabs', 'alert'].includes(field.type)"
                  class="field-description"
                  v-html="renderSafeDescription(field.props.description)"
                />
                <div class="field-actions">
                  <LegacyIcon name="el-icon-document-copy" title="复制" @click.stop="handleCopyField(index)" />
                  <LegacyIcon name="el-icon-delete" title="删除" @click.stop="handleRemoveField(index)" />
                </div>
              </div>
            </template>
          </draggable>
          <!-- 空状态 -->
          <div v-if="formFields.length === 0" class="canvas-empty">
            <LegacyIcon name="el-icon-upload2" />
            <p>从左侧拖拽组件到此处</p>
          </div>
        </div>
      </div>

      <!-- 右侧属性面板 -->
      <div class="designer-panel panel-right">
        <div class="panel-title">属性配置</div>
        <div v-if="activeField" class="property-form">
          <el-form label-position="top">
            <!-- 通用属性 -->
            <el-form-item v-if="!['card', 'alert', 'tabs'].includes(activeField.type)" label="字段标识">
              <el-input v-model="activeField.props.field" placeholder="字段key（英文）" />
            </el-form-item>
            <el-form-item label="标题">
              <el-input v-model="activeField.props.label" placeholder="请输入标题" />
            </el-form-item>

            <!-- 说明文字（除 card/alert/tabs 外通用） -->
            <el-form-item v-if="!['card', 'alert', 'tabs'].includes(activeField.type)" label="说明文字">
              <el-input
                v-model="activeField.props.description"
                type="textarea"
                :rows="2"
                placeholder="填写后展示在组件下方作为提示说明"
              />
            </el-form-item>

            <!-- 文本框 / 多行文本 属性 -->
            <template v-if="['input', 'textarea'].includes(activeField.type)">
              <el-form-item label="占位提示">
                <el-input v-model="activeField.props.placeholder" placeholder="请输入占位提示" />
              </el-form-item>
              <el-form-item label="默认值">
                <el-input
                  v-model="activeField.props.defaultValue"
                  :type="activeField.type === 'textarea' ? 'textarea' : 'text'"
                  :rows="activeField.type === 'textarea' ? 3 : undefined"
                  placeholder="请输入默认值"
                />
              </el-form-item>
              <el-form-item v-if="activeField.type === 'input'" label="最大长度">
                <el-input-number v-model="activeField.props.maxlength" :min="0" controls-position="right" style="width: 100%" />
              </el-form-item>
              <el-form-item v-if="activeField.type === 'textarea'" label="行数">
                <el-input-number v-model="activeField.props.rows" :min="2" :max="20" controls-position="right" style="width: 100%" />
              </el-form-item>
              <!-- 文本框特有功能 -->
              <template v-if="activeField.type === 'input'">
                <el-divider content-position="left">功能设置</el-divider>
                <el-form-item label="是否禁用">
                  <el-switch v-model="activeField.props.disabled" />
                </el-form-item>
                <el-form-item label="显示复制按钮">
                  <el-switch v-model="activeField.props.showCopy" />
                </el-form-item>
                <el-form-item label="显示随机生成按钮">
                  <el-switch v-model="activeField.props.showRandom" />
                </el-form-item>
                <el-form-item v-if="activeField.props.showRandom" label="随机字符串长度">
                  <el-input-number v-model="activeField.props.randomLength" :min="1" :max="100" controls-position="right" style="width: 100%" />
                </el-form-item>

                <el-divider content-position="left">接口回显</el-divider>
                <el-form-item label="启用接口回显按钮">
                  <el-switch v-model="activeField.props.fetchEnabled" />
                </el-form-item>
                <template v-if="activeField.props.fetchEnabled">
                  <el-form-item label="按钮文案">
                    <el-input v-model="activeField.props.fetchButtonText" placeholder="例如：获取/刷新" />
                  </el-form-item>
                  <el-form-item label="请求地址">
                    <el-input v-model="activeField.props.fetchRequest.url" placeholder="例如：/setting/live/get_secret" />
                  </el-form-item>
                  <el-form-item label="请求方式">
                    <el-select v-model="activeField.props.fetchRequest.method" placeholder="请选择" style="width: 100%">
                      <el-option label="GET" value="get" />
                      <el-option label="POST" value="post" />
                      <el-option label="PUT" value="put" />
                      <el-option label="DELETE" value="delete" />
                    </el-select>
                  </el-form-item>
                  <el-form-item
                    v-if="['get', 'delete'].includes((activeField.props.fetchRequest.method || 'get').toLowerCase())"
                    label="Query参数（JSON）"
                  >
                    <el-input
                      v-model="activeField.props.fetchRequest.paramsJson"
                      type="textarea"
                      :autosize="{ minRows: 3, maxRows: 6 }"
                      placeholder="例如：{&quot;id&quot;:1}"
                    />
                  </el-form-item>
                  <el-form-item v-else label="Body参数（JSON）">
                    <el-input
                      v-model="activeField.props.fetchRequest.dataJson"
                      type="textarea"
                      :autosize="{ minRows: 3, maxRows: 6 }"
                      placeholder="例如：{&quot;id&quot;:1}"
                    />
                  </el-form-item>
                  <el-form-item label="取值路径">
                    <el-input v-model="activeField.props.fetchValuePath" placeholder="例如：data.secret 或 data.items[0].id（留空默认取 data）" />
                  </el-form-item>
                </template>
              </template>
            </template>

            <!-- 数字组件属性 -->
            <template v-if="activeField.type === 'number'">
              <el-form-item label="默认值">
                <el-input-number v-model="activeField.props.defaultValue" :min="activeField.props.min" :max="activeField.props.max" :step="activeField.props.step" controls-position="right" style="width: 100%" />
              </el-form-item>
              <el-form-item label="最小值">
                <el-input-number v-model="activeField.props.min" controls-position="right" style="width: 100%" />
              </el-form-item>
              <el-form-item label="最大值">
                <el-input-number v-model="activeField.props.max" controls-position="right" style="width: 100%" />
              </el-form-item>
              <el-form-item label="步长">
                <el-input-number v-model="activeField.props.step" :min="1" controls-position="right" style="width: 100%" />
              </el-form-item>
            </template>

            <!-- 开关组件属性 -->
            <template v-if="activeField.type === 'switch'">
              <el-form-item label="开启文案">
                <el-input v-model="activeField.props.activeText" placeholder="开启" />
              </el-form-item>
              <el-form-item label="关闭文案">
                <el-input v-model="activeField.props.inactiveText" placeholder="关闭" />
              </el-form-item>
              <el-form-item label="开启值">
                <el-input-number
                  :value="getSwitchActiveValue(activeField)"
                  controls-position="right"
                  style="width: 100%"
                  @change="handleSwitchValueChange(activeField, 'activeValue', $event)"
                />
              </el-form-item>
              <el-form-item label="关闭值">
                <el-input-number
                  :value="getSwitchInactiveValue(activeField)"
                  controls-position="right"
                  style="width: 100%"
                  @change="handleSwitchValueChange(activeField, 'inactiveValue', $event)"
                />
              </el-form-item>
              <el-form-item label="默认状态">
                <el-switch
                  v-model="activeField.props.defaultValue"
                  :active-text="activeField.props.activeText || '开启'"
                  :inactive-text="activeField.props.inactiveText || '关闭'"
                  :active-value="getSwitchActiveValue(activeField)"
                  :inactive-value="getSwitchInactiveValue(activeField)"
                  @change="syncCanvasSwitchDefault(activeField)"
                />
              </el-form-item>
              <el-form-item label="是否禁用">
                <el-switch v-model="activeField.props.disabled" />
              </el-form-item>
            </template>

            <!-- 单选/多选组件属性 -->
            <template v-if="['radio', 'checkbox'].includes(activeField.type)">
              <el-form-item label="选项列表">
                <div class="options-editor">
                  <div v-for="(opt, idx) in activeField.props.options" :key="idx" class="option-row">
                    <el-input v-model="opt.label" placeholder="标签" size="mini" style="width: 40%" />
                    <el-input v-model="opt.value" placeholder="值" size="mini" style="width: 35%" />
                    <el-button type="text" size="mini" icon="el-icon-delete" class="opt-delete-btn" @click="removeOption(idx)" />
                  </div>
                  <el-button type="text" size="mini" icon="el-icon-plus" @click="addOption">添加选项</el-button>
                </div>
              </el-form-item>
              <!-- 单选默认值 -->
              <el-form-item v-if="activeField.type === 'radio'" label="默认值">
                <el-select
                  v-model="activeField.props.defaultValue"
                  placeholder="请选择默认值"
                  clearable
                  style="width: 100%"
                  @change="syncCanvasRadioDefault(activeField)"
                >
                  <el-option v-for="opt in activeField.props.options" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-form-item>
              <!-- 多选默认值 -->
              <el-form-item v-if="activeField.type === 'checkbox'" label="默认值">
                <el-select v-model="activeField.props.defaultValue" multiple placeholder="请选择默认值" style="width: 100%">
                  <el-option v-for="opt in activeField.props.options" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 单选/开关联动配置 -->
            <template v-if="['radio', 'switch'].includes(activeField.type)">
              <el-divider content-position="left">联动配置</el-divider>
              <div class="linkage-editor">
                <div class="linkage-tip-text">
                  <LegacyIcon name="el-icon-info" />
                  配置在选择某个值时，显示哪些其他字段
                </div>
                <div
                  v-for="(opt, linkageIndex) in getLinkageOptions(activeField)"
                  :key="linkageIndex + '_' + String(opt.value)"
                  class="linkage-row"
                >
                  <div class="linkage-row-label">
                    <el-tag size="mini" type="info">{{ opt.label }}（{{ opt.value }}）</el-tag>
                    <span class="linkage-row-desc">显示字段：</span>
                  </div>
                  <div class="linkage-row-control">
                    <el-switch
                      v-model="getLinkageRule(opt.value).enabled"
                      active-text="启用控制"
                      inactive-text="不限制"
                      style="margin-bottom: 8px"
                      @change="onLinkageChange"
                    />
                  </div>
                  <el-select
                    v-if="getLinkageRule(opt.value).enabled"
                    v-model="getLinkageRule(opt.value).showFields"
                    multiple
                    placeholder="选择显示的字段（为空则全部隐藏）"
                    style="width: 100%"
                    size="mini"
                    @change="onLinkageChange"
                  >
                    <el-option
                      v-for="f in allFieldOptions"
                      :key="f.value"
                      :label="f.label"
                      :value="f.value"
                    />
                  </el-select>
                  <div v-else class="linkage-disabled-tip">未启用时，当前值不影响其他字段显示</div>
                </div>
              </div>
            </template>

            <!-- 上传组件属性 -->
            <template v-if="activeField.type === 'upload'">
              <el-form-item label="按钮文字">
                <el-input v-model="activeField.props.buttonText" placeholder="点击上传" />
              </el-form-item>
              <el-form-item label="文件类型">
                <el-select v-model="activeField.props.accept" placeholder="请选择" style="width: 100%">
                  <el-option label="图片" value="image/*" />
                  <el-option label="视频" value="video/*" />
                  <el-option label="音频" value="audio/*" />
                  <el-option label="文档" value=".doc,.docx,.pdf,.xls,.xlsx" />
                  <el-option label="不限制" value="" />
                </el-select>
              </el-form-item>
              <el-form-item label="最大上传数">
                <el-input-number v-model="activeField.props.limit" :min="1" :max="20" controls-position="right" style="width: 100%" />
              </el-form-item>
            </template>

            <!-- 卡片组件属性 -->
            <template v-if="activeField.type === 'card'">
              <el-form-item label="卡片标题">
                <el-input v-model="activeField.props.title" placeholder="请输入卡片标题" />
              </el-form-item>
              <el-form-item label="卡片内容">
                <el-input v-model="activeField.props.content" type="textarea" :rows="3" placeholder="请输入卡片内容" />
              </el-form-item>
              <el-form-item label="阴影">
                <el-select v-model="activeField.props.shadow" style="width: 100%">
                  <el-option label="始终显示" value="always" />
                  <el-option label="悬浮显示" value="hover" />
                  <el-option label="不显示" value="never" />
                </el-select>
              </el-form-item>
            </template>

            <!-- Tabs 组件属性 -->
            <template v-if="activeField.type === 'tabs'">
              <el-form-item label="标签页列表">
                <div class="tabs-editor">
                  <div v-for="(tab, idx) in activeField.props.tabs" :key="tab._id" class="tab-row">
                    <el-input v-model="tab.name" placeholder="标签名" size="mini" style="flex: 1" />
                    <el-button
                      type="text"
                      size="mini"
                      icon="el-icon-delete"
                      class="opt-delete-btn"
                      :disabled="activeField.props.tabs.length <= 1"
                      @click="removeTab(idx)"
                    />
                  </div>
                  <el-button type="text" size="mini" icon="el-icon-plus" @click="addTab">添加标签页</el-button>
                </div>
              </el-form-item>
              <el-form-item label="标签类型">
                <el-select v-model="activeField.props.tabType" style="width: 100%">
                  <el-option label="默认" value="" />
                  <el-option label="卡片" value="card" />
                  <el-option label="边框卡片" value="border-card" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 警告组件属性 -->
            <template v-if="activeField.type === 'alert'">
              <el-form-item label="提示标题">
                <el-input v-model="activeField.props.title" placeholder="请输入提示标题" />
              </el-form-item>
              <el-form-item label="提示描述">
                <el-input v-model="activeField.props.description" type="textarea" :rows="2" placeholder="请输入描述" />
              </el-form-item>
              <el-form-item label="类型">
                <el-select v-model="activeField.props.alertType" style="width: 100%">
                  <el-option label="信息" value="info" />
                  <el-option label="成功" value="success" />
                  <el-option label="警告" value="warning" />
                  <el-option label="错误" value="error" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 通用属性：是否必填 -->
            <template v-if="!['card', 'alert', 'tabs'].includes(activeField.type)">
              <el-divider content-position="left">验证规则</el-divider>
              <el-form-item label="是否必填">
                <el-switch v-model="activeField.props.required" />
              </el-form-item>
            </template>
          </el-form>
        </div>
        <div v-else class="property-empty">
          <LegacyIcon name="el-icon-mouse" />
          <p>请选择一个组件进行配置</p>
        </div>
      </div>
    </div>

    <!-- 预览弹窗 -->
    <el-dialog v-model="previewVisible" title="表单预览" width="900px" :close-on-click-modal="false">
      <el-form ref="previewFormRef" :model="previewFormData" label-width="120px">
        <template v-for="field in formFields">
          <!-- 普通表单字段 -->
          <el-form-item
            v-if="!['card', 'alert', 'tabs'].includes(field.type) && isFieldVisible(field)"
            :key="field._id"
            :label="field.props.label || field.label"
            :prop="field.props.field"
            :rules="field.props.required ? [{ required: true, message: `请填写${field.props.label || field.label}`, trigger: 'blur' }] : []"
          >
            <div v-if="field.type === 'input'" class="input-with-actions">
              <el-input
                v-model="previewFormData[field.props.field]"
                :placeholder="field.props.placeholder"
                :maxlength="field.props.maxlength"
                :disabled="field.props.disabled"
              />
              <div v-if="field.props.showCopy || field.props.showRandom || field.props.fetchEnabled" class="input-actions">
                <el-button
                  v-if="field.props.fetchEnabled"
                  type="primary"
                  size="mini"
                  icon="el-icon-download"
                  :loading="previewFetchLoadingMap[field._id || field.props.field]"
                  @click="handlePreviewFetchAndFill(field)"
                >
                  {{ field.props.fetchButtonText || '获取' }}
                </el-button>
                <el-button v-if="field.props.showCopy" type="primary" size="mini" icon="el-icon-document-copy" @click="handleCopyText(field)">复制</el-button>
                <el-button v-if="field.props.showRandom" type="primary" size="mini" icon="el-icon-refresh" @click="handleGenerateRandom(field)">随机生成</el-button>
              </div>
            </div>
            <el-input v-else-if="field.type === 'textarea'" v-model="previewFormData[field.props.field]" type="textarea" :rows="field.props.rows" :placeholder="field.props.placeholder" />
            <el-input-number v-else-if="field.type === 'number'" v-model="previewFormData[field.props.field]" :min="field.props.min" :max="field.props.max" :step="field.props.step" controls-position="right" style="width: 100%" />
            <el-switch
              v-else-if="field.type === 'switch'"
              v-model="previewFormData[field.props.field]"
              :active-text="field.props.activeText || '开启'"
              :inactive-text="field.props.inactiveText || '关闭'"
              :active-value="getSwitchActiveValue(field)"
              :inactive-value="getSwitchInactiveValue(field)"
              :disabled="field.props.disabled"
            />
            <el-radio-group v-else-if="field.type === 'radio'" v-model="previewFormData[field.props.field]">
              <el-radio v-for="opt in field.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
            </el-radio-group>
            <el-checkbox-group v-else-if="field.type === 'checkbox'" v-model="previewFormData[field.props.field]">
              <el-checkbox v-for="opt in field.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
            </el-checkbox-group>
            <div v-else-if="field.type === 'upload'" class="upload-wrapper">
              <el-upload
                action="#"
                :auto-upload="false"
                :limit="field.props.limit"
                :accept="field.props.accept"
                :file-list="previewFormData[field.props.field] || []"
                :on-change="(file, fileList) => handlePreviewFileChange(field, fileList)"
                :on-remove="(file, fileList) => handlePreviewFileChange(field, fileList)"
                :on-preview="handlePreviewImage"
                list-type="picture-card"
                class="avatar-uploader"
              >
                <LegacyIcon name="el-icon-plus" />
              </el-upload>
            </div>
            <div v-if="field.props.description" class="form-field-description" v-html="renderSafeDescription(field.props.description)" />
          </el-form-item>

          <!-- 卡片组件 -->
          <el-card
            v-else-if="field.type === 'card'"
            :key="field._id + '_card'"
            :shadow="field.props.shadow || 'hover'"
            style="margin-bottom: 16px"
          >
            <template #header>
              <div>{{ field.props.title || '卡片标题' }}</div>
            </template>
            <template v-if="field.props.children && field.props.children.length > 0">
              <template v-for="child in field.props.children">
                <el-form-item
                  v-if="isFieldVisible(child)"
                  :key="child._id"
                  :label="child.props.label || child.label"
                  :prop="child.props.field"
                  :rules="child.props.required ? [{ required: true, message: `请填写${child.props.label || child.label}`, trigger: 'blur' }] : []"
                >
                  <div v-if="child.type === 'input'" class="input-with-actions">
                    <el-input v-model="previewFormData[child.props.field]" :placeholder="child.props.placeholder" :maxlength="child.props.maxlength" :disabled="child.props.disabled" />
                    <div v-if="child.props.showCopy || child.props.showRandom || child.props.fetchEnabled" class="input-actions">
                      <el-button v-if="child.props.fetchEnabled" type="primary" size="mini" icon="el-icon-download" :loading="previewFetchLoadingMap[child._id || child.props.field]" @click="handlePreviewFetchAndFill(child)">
                        {{ child.props.fetchButtonText || '获取' }}
                      </el-button>
                      <el-button v-if="child.props.showCopy" type="primary" size="mini" icon="el-icon-document-copy" @click="handleCopyText(child)">复制</el-button>
                      <el-button v-if="child.props.showRandom" type="primary" size="mini" icon="el-icon-refresh" @click="handleGenerateRandom(child)">随机生成</el-button>
                    </div>
                  </div>
                  <el-input v-else-if="child.type === 'textarea'" v-model="previewFormData[child.props.field]" type="textarea" :rows="child.props.rows" :placeholder="child.props.placeholder" />
                  <el-input-number v-else-if="child.type === 'number'" v-model="previewFormData[child.props.field]" :min="child.props.min" :max="child.props.max" :step="child.props.step" controls-position="right" style="width: 100%" />
                  <el-switch
                    v-else-if="child.type === 'switch'"
                    v-model="previewFormData[child.props.field]"
                    :active-text="child.props.activeText || '开启'"
                    :inactive-text="child.props.inactiveText || '关闭'"
                    :active-value="getSwitchActiveValue(child)"
                    :inactive-value="getSwitchInactiveValue(child)"
                    :disabled="child.props.disabled"
                  />
                  <el-radio-group v-else-if="child.type === 'radio'" v-model="previewFormData[child.props.field]">
                    <el-radio v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                  </el-radio-group>
                  <el-checkbox-group v-else-if="child.type === 'checkbox'" v-model="previewFormData[child.props.field]">
                    <el-checkbox v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                  </el-checkbox-group>
                  <div v-else-if="child.type === 'upload'" class="upload-wrapper">
                    <el-upload action="#" :auto-upload="false" :limit="child.props.limit" :accept="child.props.accept" :file-list="previewFormData[child.props.field] || []" :on-change="(file, fileList) => handlePreviewFileChange(child, fileList)" :on-remove="(file, fileList) => handlePreviewFileChange(child, fileList)" :on-preview="handlePreviewImage" list-type="picture-card" class="avatar-uploader">
                      <LegacyIcon name="el-icon-plus" />
                    </el-upload>
                  </div>
                  <el-alert v-else-if="child.type === 'alert'" :key="child._id + '_alert'" :title="child.props.title || '提示'" :type="child.props.alertType || 'info'" :description="child.props.description" :closable="false" show-icon />
                  <div v-if="child.props.description && child.type !== 'alert'" class="form-field-description" v-html="renderSafeDescription(child.props.description)" />
                </el-form-item>
              </template>
            </template>
            <p v-else>{{ field.props.content || '卡片内容' }}</p>
          </el-card>

          <!-- Tabs 组件 -->
          <el-tabs
            v-else-if="field.type === 'tabs'"
            :key="field._id + '_tabs'"
            v-model="previewTabActive[field._id]"
            :type="field.props.tabType || ''"
            style="margin-bottom: 16px"
          >
            <el-tab-pane
              v-for="tab in field.props.tabs"
              :key="tab._id"
              :label="tab.name"
              :name="tab._id"
            >
              <template v-for="child in tab.children">
                <el-form-item
                  v-if="!['alert'].includes(child.type) && isFieldVisible(child)"
                  :key="child._id"
                  :label="child.props.label || child.label"
                  :prop="child.props.field"
                  :rules="child.props.required ? [{ required: true, message: `请填写${child.props.label || child.label}`, trigger: 'blur' }] : []"
                >
                  <div v-if="child.type === 'input'" class="input-with-actions">
                    <el-input v-model="previewFormData[child.props.field]" :placeholder="child.props.placeholder" :maxlength="child.props.maxlength" :disabled="child.props.disabled" />
                    <div v-if="child.props.showCopy || child.props.showRandom || child.props.fetchEnabled" class="input-actions">
                      <el-button v-if="child.props.fetchEnabled" type="primary" size="mini" icon="el-icon-download" :loading="previewFetchLoadingMap[child._id || child.props.field]" @click="handlePreviewFetchAndFill(child)">
                        {{ child.props.fetchButtonText || '获取' }}
                      </el-button>
                      <el-button v-if="child.props.showCopy" type="primary" size="mini" icon="el-icon-document-copy" @click="handleCopyText(child)">复制</el-button>
                      <el-button v-if="child.props.showRandom" type="primary" size="mini" icon="el-icon-refresh" @click="handleGenerateRandom(child)">随机生成</el-button>
                    </div>
                  </div>
                  <el-input v-else-if="child.type === 'textarea'" v-model="previewFormData[child.props.field]" type="textarea" :rows="child.props.rows" :placeholder="child.props.placeholder" />
                  <el-input-number v-else-if="child.type === 'number'" v-model="previewFormData[child.props.field]" :min="child.props.min" :max="child.props.max" :step="child.props.step" controls-position="right" style="width: 100%" />
                  <el-switch
                    v-else-if="child.type === 'switch'"
                    v-model="previewFormData[child.props.field]"
                    :active-text="child.props.activeText || '开启'"
                    :inactive-text="child.props.inactiveText || '关闭'"
                    :active-value="getSwitchActiveValue(child)"
                    :inactive-value="getSwitchInactiveValue(child)"
                    :disabled="child.props.disabled"
                  />
                  <el-radio-group v-else-if="child.type === 'radio'" v-model="previewFormData[child.props.field]">
                    <el-radio v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                  </el-radio-group>
                  <el-checkbox-group v-else-if="child.type === 'checkbox'" v-model="previewFormData[child.props.field]">
                    <el-checkbox v-for="opt in child.props.options" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                  </el-checkbox-group>
                  <div v-else-if="child.type === 'upload'" class="upload-wrapper">
                    <el-upload action="#" :auto-upload="false" :limit="child.props.limit" :accept="child.props.accept" :file-list="previewFormData[child.props.field] || []" :on-change="(file, fileList) => handlePreviewFileChange(child, fileList)" :on-remove="(file, fileList) => handlePreviewFileChange(child, fileList)" :on-preview="handlePreviewImage" list-type="picture-card" class="avatar-uploader">
                      <LegacyIcon name="el-icon-plus" />
                    </el-upload>
                  </div>
                  <div v-if="child.props.description" class="form-field-description" v-html="renderSafeDescription(child.props.description)" />
                </el-form-item>
                <el-alert
                  v-else-if="child.type === 'alert'"
                  :key="child._id + '_alert'"
                  :title="child.props.title || '提示'"
                  :type="child.props.alertType || 'info'"
                  :description="child.props.description"
                  :closable="false"
                  show-icon
                  style="margin-bottom: 16px"
                />
              </template>
            </el-tab-pane>
          </el-tabs>

          <!-- 警告组件 -->
          <el-alert v-else-if="field.type === 'alert'" :key="field._id + '_alert'" :title="field.props.title || '提示'" :type="field.props.alertType || 'info'" :description="field.props.description" :closable="false" show-icon style="margin-bottom: 16px" />
        </template>
      </el-form>
      <template #footer>
        <div>
          <el-button @click="previewVisible = false">关闭</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- JSON导出弹窗 -->
    <el-dialog v-model="jsonVisible" title="导出JSON" width="650px">
      <el-input type="textarea" :value="exportJsonStr" :rows="18" readonly />
      <template #footer>
        <div>
          <el-button @click="jsonVisible = false">关闭</el-button>
          <el-button type="primary" @click="handleCopyJson">复制</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- AI生成JSON弹窗 -->
    <el-dialog v-model="aiDraftJsonVisible" title="AI生成JSON" width="650px">
      <el-input type="textarea" :value="aiDraftJsonStr" :rows="18" readonly />
      <template #footer>
        <div>
          <el-button @click="aiDraftJsonVisible = false">关闭</el-button>
          <el-button type="primary" @click="handleCopyAiDraftJson">复制</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 图片预览弹窗 -->
    <el-dialog v-model="imagePreviewVisible" title="图片预览" width="800px" append-to-body>
      <img :src="previewImageUrl" style="width: 100%; display: block" alt="预览图片">
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeMount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import draggable from 'vuedraggable'
import { getConfigTemplateFindApi, saveTemplateApi, streamConfigTemplateAiGenerate } from '@/api/setting'
import request from '@/utils/request'
import { renderSafeDescription } from '@/utils/renderDescription'

defineOptions({ name: 'ConfigFormDesigner' })

let fieldIdCounter = 0
function genId() {
  fieldIdCounter += 1
  return 'field_' + Date.now() + '_' + fieldIdCounter
}

const route = useRoute()
const router = useRouter()
const instance = getCurrentInstance()

const configId = ref(null)
const configName = ref('')
const saving = ref(false)
const definedFields = ref([])
const formFields = ref([])
const activeFieldId = ref(null)
const activeField = ref(null)
const defaultRadioOptions = [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }]
const defaultCheckboxOptions = [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }]
const canvasFormData = ref({})
const previewVisible = ref(false)
const previewFormData = ref({})
const previewTabActive = ref({})
const previewFetchLoadingMap = ref({})
const jsonVisible = ref(false)
const exportJsonStr = ref('')
const aiPanelOpen = ref(false)
const aiSending = ref(false)
const aiInputText = ref('')
const aiInputComposing = ref(false)
const aiMessages = ref([])
const aiDraftFields = ref([])
const aiDraftNewFieldKeys = ref([])
const aiDraftWarnings = ref([])
const aiDraftCollapsed = ref(false)
const aiDraftJsonVisible = ref(false)
const aiDraftJsonStr = ref('')
const imagePreviewVisible = ref(false)
const previewImageUrl = ref('')
const aiInputRef = ref(null)
const aiMsgList = ref(null)
const previewFormRef = ref(null)

const basicComponents = [
  { type: 'input', label: '文本框', icon: 'el-icon-edit', defaultProps: { field: '', label: '文本框', placeholder: '请输入', maxlength: 200, required: false, disabled: false, showCopy: false, showRandom: false, randomLength: 16, defaultValue: '', description: '', fetchEnabled: false, fetchButtonText: '获取', fetchRequest: { url: '', method: 'get', paramsJson: '', dataJson: '' }, fetchValuePath: 'data' }},
  { type: 'textarea', label: '多行文本', icon: 'el-icon-document', defaultProps: { field: '', label: '多行文本', placeholder: '请输入', rows: 3, required: false, disabled: false, defaultValue: '', description: '' }},
  { type: 'number', label: '数字', icon: 'el-icon-s-data', defaultProps: { field: '', label: '数字', min: 0, max: 99999, step: 1, required: false, disabled: false, defaultValue: null, description: '' }},
  { type: 'switch', label: '开关', icon: 'el-icon-switch-button', defaultProps: { field: '', label: '开关', activeText: '开启', inactiveText: '关闭', activeValue: 1, inactiveValue: 0, required: false, disabled: false, defaultValue: 0, description: '', linkage: [] }},
  { type: 'radio', label: '单选', icon: 'el-icon-circle-check', defaultProps: { field: '', label: '单选', options: [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }], required: false, disabled: false, defaultValue: '', description: '', linkage: [] }},
  { type: 'checkbox', label: '多选', icon: 'el-icon-finished', defaultProps: { field: '', label: '多选', options: [{ label: '选项一', value: '1' }, { label: '选项二', value: '2' }], required: false, disabled: false, defaultValue: [], description: '' }},
  { type: 'upload', label: '上传', icon: 'el-icon-upload', defaultProps: { field: '', label: '上传', buttonText: '点击上传', accept: 'image/*', limit: 1, required: false, disabled: false, description: '' }}
]

const layoutComponents = [
  { type: 'card', label: '卡片', icon: 'el-icon-postcard', defaultProps: { title: '卡片标题', content: '卡片内容区域', shadow: 'hover', children: [] }},
  { type: 'tabs', label: '标签页', icon: 'el-icon-tickets', defaultProps: { label: '标签页', tabType: 'border-card', activeTab: '', tabs: [{ _id: 'tab_default_1', name: '标签一', children: [] }, { _id: 'tab_default_2', name: '标签二', children: [] }] }},
  { type: 'alert', label: '温馨提示', icon: 'el-icon-warning-outline', defaultProps: { title: '提示信息', description: '', alertType: 'info' }}
]

const usedFieldKeys = computed(() => collectAllFields(formFields.value)
  .filter(f => f.props && f.props.field)
  .map(f => f.props.field))

const availableDefinedFields = computed(() => definedFields.value.filter(f => !usedFieldKeys.value.includes(f.field)))

const allFieldOptions = computed(() => {
  const options = []
  const collect = (fields) => {
    fields.forEach(f => {
      if (!['card', 'alert', 'tabs'].includes(f.type) && f.props.field && f._id !== (activeField.value && activeField.value._id)) {
        options.push({ label: `${f.props.label || f.props.field}（${f.props.field}）`, value: f.props.field })
      }
      if (f.type === 'card' && f.props.children) {
        collect(f.props.children)
      }
      if (f.type === 'tabs' && f.props.tabs) {
        f.props.tabs.forEach(tab => collect(tab.children || []))
      }
    })
  }
  collect(formFields.value)
  return options
})

const aiDraftFieldTotal = computed(() => (aiDraftFields.value.length > 0 ? collectAllFields(aiDraftFields.value).length : 0))

function normalizeInputFetchProps(field) {
  if (!field || field.type !== 'input' || !field.props) return
  if (typeof field.props.fetchEnabled !== 'boolean') {
    field.props.fetchEnabled = false
  }
  if (field.props.fetchButtonText === undefined || field.props.fetchButtonText === null || String(field.props.fetchButtonText).trim() === '') {
    field.props.fetchButtonText = '获取'
  }
  if (!field.props.fetchRequest || typeof field.props.fetchRequest !== 'object') {
    field.props.fetchRequest = { url: '', method: 'get', paramsJson: '', dataJson: '' }
  } else {
    if (field.props.fetchRequest.url === undefined) field.props.fetchRequest.url = ''
    if (!field.props.fetchRequest.method) field.props.fetchRequest.method = 'get'
    if (field.props.fetchRequest.paramsJson === undefined) field.props.fetchRequest.paramsJson = ''
    if (field.props.fetchRequest.dataJson === undefined) field.props.fetchRequest.dataJson = ''
  }
  if (field.props.fetchValuePath === undefined || field.props.fetchValuePath === null || String(field.props.fetchValuePath).trim() === '') {
    field.props.fetchValuePath = 'data'
  }
}

function normalizeSwitchProps(field) {
  if (!field || field.type !== 'switch' || !field.props) return
  const props = field.props
  if (props.activeText === undefined || props.activeText === null || String(props.activeText).trim() === '') {
    props.activeText = '开启'
  }
  if (props.inactiveText === undefined || props.inactiveText === null || String(props.inactiveText).trim() === '') {
    props.inactiveText = '关闭'
  }
  if (props.activeValue === undefined || props.activeValue === null) props.activeValue = 1
  if (props.inactiveValue === undefined || props.inactiveValue === null) props.inactiveValue = 0
  if (!Array.isArray(props.linkage)) props.linkage = []
  if (!isSameLinkageValue(props.defaultValue, props.activeValue) && !isSameLinkageValue(props.defaultValue, props.inactiveValue)) {
    props.defaultValue = props.inactiveValue
  }
}

function normalizeLinkageProps(field) {
  if (!field || !['radio', 'switch'].includes(field.type) || !field.props) return
  if (!Array.isArray(field.props.linkage)) field.props.linkage = []
}

function normalizeFieldsProps(fields) {
  if (!Array.isArray(fields)) return
  fields.forEach(f => {
    normalizeInputFetchProps(f)
    normalizeSwitchProps(f)
    normalizeLinkageProps(f)
    if (f.type === 'card' && f.props && Array.isArray(f.props.children)) {
      normalizeFieldsProps(f.props.children)
    }
    if (f.type === 'tabs' && f.props && Array.isArray(f.props.tabs)) {
      f.props.tabs.forEach(tab => {
        if (tab && Array.isArray(tab.children)) normalizeFieldsProps(tab.children)
      })
    }
  })
}

function handleOpenAiPanel() {
  aiPanelOpen.value = true
  nextTick(() => {
    aiInputRef.value?.focus?.()
    scrollAiMessages()
  })
}

function handleMinimizeAiPanel() {
  aiPanelOpen.value = false
}

function setAiSending(value) {
  aiSending.value = value
}

function replaceFormFields(fields) {
  formFields.value = fields
}

function setPreviewFetchLoading(loadingKey, value) {
  previewFetchLoadingMap.value[loadingKey] = value
}

function handleAiCompositionStart() {
  aiInputComposing.value = true
}

function handleAiCompositionEnd() {
  aiInputComposing.value = false
}

function handleAiEnter(e) {
  if (aiInputComposing.value || e.isComposing || e.keyCode === 229) return
  if (e.shiftKey) return
  e.preventDefault()
  handleSendAiMessage()
}

function buildAiMessagesPayload() {
  return aiMessages.value
    .filter(msg => msg && !msg.loading && msg.content && ['user', 'assistant'].includes(msg.role))
    .slice(-12)
    .map(msg => ({
      role: msg.role,
      content: String(msg.content).slice(0, 1200)
    }))
}

async function handleSendAiMessage() {
  const prompt = aiInputText.value.trim()
  if (!prompt || aiSending.value) return
  if (!configId.value) {
    ElMessage.warning('请先选择要配置的模板')
    return
  }

  const history = buildAiMessagesPayload()
  aiInputText.value = ''
  aiMessages.value.push({ _id: genId(), role: 'user', content: prompt })
  const aiMessage = {
    _id: genId(),
    role: 'assistant',
    content: '',
    rawContent: '',
    loading: true,
    newFieldKeys: [],
    warnings: []
  }
  aiMessages.value.push(aiMessage)
  setAiSending(true)
  nextTick(() => scrollAiMessages())

  try {
    await streamConfigTemplateAiGenerate({
      id: configId.value,
      prompt,
      messages: history,
      current_fields: JSON.parse(JSON.stringify(formFields.value))
    }, payload => {
      handleAiStreamPayload(payload, aiMessage)
    })
    finishAiMessage(aiMessage)
  } catch (e) {
    failAiMessage(aiMessage, (e && e.message) || 'AI生成失败，请稍后重试')
  } finally {
    setAiSending(false)
    nextTick(() => scrollAiMessages())
  }
}

function handleAiStreamPayload(payload, aiMessage) {
  if (!payload || typeof payload !== 'object' || !aiMessage) return

  if (payload.type === 'start') {
    aiMessage.content = payload.message || '正在分析当前配置...'
    nextTick(() => scrollAiMessages())
    return
  }

  if (payload.type === 'chunk') {
    aiMessage.rawContent += payload.delta || ''
    if (!aiMessage.hasDraft) {
      aiMessage.content = aiMessage.rawContent
    }
    nextTick(() => scrollAiMessages())
    return
  }

  if (payload.type === 'draft' || payload.type === 'done') {
    setAiDraft(payload, aiMessage)
    nextTick(() => scrollAiMessages())
    return
  }

  if (payload.type === 'error') {
    failAiMessage(aiMessage, payload.message || 'AI模型调用失败，请检查模型配置')
  }
}

function setAiDraft(payload, aiMessage) {
  const rawFields = Array.isArray(payload.fields) ? payload.fields : []
  const fields = normalizeAiGeneratedFields(rawFields, true)
  if (fields.length > 0) {
    aiDraftFields.value = fields
    aiDraftNewFieldKeys.value = Array.isArray(payload.new_field_keys)
      ? payload.new_field_keys.map(key => String(key)).filter(Boolean)
      : collectAiNewFieldKeys(fields)
    aiDraftWarnings.value = Array.isArray(payload.warnings)
      ? payload.warnings.map(item => String(item)).filter(Boolean)
      : []
    aiMessage.fields = fields
    aiMessage.newFieldKeys = aiDraftNewFieldKeys.value
    aiMessage.warnings = aiDraftWarnings.value
    aiDraftCollapsed.value = false
  }
  aiMessage.hasDraft = true
  aiMessage.loading = false
  aiMessage.content = payload.content || '已生成配置草稿，可继续对话修改或一键配置。'
}

function finishAiMessage(aiMessage) {
  if (!aiMessage) return
  if (!aiMessage.content) {
    aiMessage.content = aiDraftFields.value.length > 0 ? '已生成配置草稿。' : '暂无可用回复'
  }
  aiMessage.loading = false
}

function failAiMessage(aiMessage, message) {
  if (!aiMessage) return
  aiMessage.loading = false
  aiMessage.content = message
  nextTick(() => scrollAiMessages())
}

function scrollAiMessages() {
  const el = aiMsgList.value
  if (el) el.scrollTop = el.scrollHeight
}

function handlePreviewAiDraft() {
  if (aiDraftFields.value.length === 0) {
    ElMessage.warning('暂无AI生成草稿')
    return
  }
  aiDraftJsonStr.value = JSON.stringify(aiDraftFields.value, null, 2)
  aiDraftJsonVisible.value = true
}

function handleCopyAiDraftJson() {
  const textarea = document.createElement('textarea')
  textarea.value = aiDraftJsonStr.value
  document.body.appendChild(textarea)
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
  ElMessage.success('已复制到剪贴板')
}

async function handleApplyAiDraft() {
  if (aiDraftFields.value.length === 0) {
    ElMessage.warning('暂无可应用的AI草稿')
    return
  }

  if (formFields.value.length > 0) {
    try {
      await ElMessageBox.confirm('一键配置会覆盖当前画布中的所有组件，确定继续吗？', '提示', {
        confirmButtonText: '确定覆盖',
        cancelButtonText: '取消',
        type: 'warning'
      })
    } catch (e) {
      return
    }
  }

  const fields = normalizeAiGeneratedFields(aiDraftFields.value, true)
  if (fields.length === 0) {
    ElMessage.warning('AI草稿中没有可用组件')
    return
  }
  replaceFormFields(fields)
  activeFieldId.value = null
  activeField.value = null
  canvasFormData.value = {}
  normalizeFieldsProps(formFields.value)
  if (collectAiNewFieldKeys(fields).length > 0) {
    ElMessage.warning('已应用AI草稿，请核对AI新增字段标识后再保存')
  } else {
    ElMessage.success('已应用AI配置草稿')
  }
}

function normalizeAiGeneratedFields(fields, isTopLevel) {
  if (!Array.isArray(fields)) return []
  const result = []
  fields.forEach(field => {
    const normalized = normalizeAiGeneratedField(field, isTopLevel !== false)
    if (normalized) result.push(normalized)
  })
  return result
}

function normalizeAiGeneratedField(rawField, isTopLevel) {
  if (!rawField || typeof rawField !== 'object') return null
  const type = String(rawField.type || 'input')
  if (!['input', 'textarea', 'number', 'switch', 'radio', 'checkbox', 'upload', 'card', 'tabs', 'alert'].includes(type)) {
    return null
  }
  if (isTopLevel === false && ['card', 'tabs'].includes(type)) {
    return null
  }

  const definition = getAiComponentDefinition(type)
  const props = JSON.parse(JSON.stringify(definition.defaultProps || {}))
  const rawProps = rawField.props && typeof rawField.props === 'object' ? rawField.props : {}
  Object.assign(props, rawProps)

  const item = {
    _id: genId(),
    type,
    label: definition.label,
    icon: definition.icon,
    props
  }

  if (type === 'card') {
    item.props.title = item.props.title || rawField.label || '卡片标题'
    item.props.children = normalizeAiGeneratedFields(rawProps.children || [], false)
    return item
  }

  if (type === 'tabs') {
    const rawTabs = Array.isArray(rawProps.tabs) && rawProps.tabs.length > 0 ? rawProps.tabs : (Array.isArray(props.tabs) ? props.tabs : [])
    item.props.tabs = rawTabs.map((tab, index) => ({
      _id: genId(),
      name: tab && tab.name ? String(tab.name) : `标签${index + 1}`,
      children: normalizeAiGeneratedFields(tab && tab.children ? tab.children : [], false)
    }))
    if (item.props.tabs.length === 0) {
      item.props.tabs = [{ _id: genId(), name: '标签一', children: [] }]
    }
    item.props.activeTab = item.props.tabs[0]._id
    return item
  }

  if (type === 'alert') {
    item.props.title = item.props.title || rawField.label || '提示信息'
    item.props.description = item.props.description || ''
    item.props.alertType = ['success', 'warning', 'info', 'error'].includes(item.props.alertType) ? item.props.alertType : 'info'
    return item
  }

  item.props.field = item.props.field ? String(item.props.field).trim() : ''
  item.props.label = item.props.label || rawField.label || definition.label
  item.props.required = !!item.props.required
  item.props.disabled = !!item.props.disabled
  if (item.props.aiNewField) item.props.aiNewField = true

  if (type === 'input') {
    if (!item.props.fetchRequest || Array.isArray(item.props.fetchRequest) || typeof item.props.fetchRequest !== 'object') {
      item.props.fetchRequest = { url: '', method: 'get', paramsJson: '', dataJson: '' }
    }
    normalizeInputFetchProps(item)
  }

  if (type === 'radio' || type === 'checkbox') {
    item.props.options = normalizeAiOptions(item.props.options)
    if (type === 'checkbox') {
      item.props.defaultValue = Array.isArray(item.props.defaultValue) ? item.props.defaultValue : []
    } else if (item.props.defaultValue === undefined || item.props.defaultValue === null || item.props.defaultValue === '') {
      item.props.defaultValue = item.props.options[0].value
    }
    if (type === 'radio' && !Array.isArray(item.props.linkage)) {
      item.props.linkage = []
    }
  }

  if (type === 'number') {
    item.props.min = Number(item.props.min || 0)
    item.props.max = Number(item.props.max || 99999)
    item.props.step = Number(item.props.step || 1)
    if (item.props.defaultValue !== null && item.props.defaultValue !== undefined && item.props.defaultValue !== '') {
      item.props.defaultValue = Number(item.props.defaultValue)
    } else {
      item.props.defaultValue = null
    }
  }

  if (type === 'switch') {
    normalizeSwitchProps(item)
  }

  if (type === 'upload') {
    item.props.buttonText = item.props.buttonText || '点击上传'
    item.props.accept = item.props.accept || 'image/*'
    item.props.limit = Number(item.props.limit || 1)
  }

  return item
}

function getAiComponentDefinition(type) {
  const list = basicComponents.concat(layoutComponents)
  return list.find(item => item.type === type) || basicComponents[0]
}

function normalizeAiOptions(options) {
  const result = []
  if (Array.isArray(options)) {
    options.forEach((option, index) => {
      if (option && typeof option === 'object') {
        const label = option.label ? String(option.label) : `选项${index + 1}`
        const value = option.value !== undefined && option.value !== null ? String(option.value) : String(index + 1)
        result.push({ label, value })
      } else if (option !== undefined && option !== null && String(option).trim() !== '') {
        result.push({ label: String(option), value: String(index + 1) })
      }
    })
  }
  if (result.length < 2) {
    return [{ label: '是', value: '1' }, { label: '否', value: '0' }]
  }
  return result
}

function collectAiNewFieldKeys(fields) {
  return collectAllFields(fields)
    .filter(field => field.props && field.props.aiNewField && field.props.field)
    .map(field => field.props.field)
}

function cloneComponent(original) {
  const props = JSON.parse(JSON.stringify(original.defaultProps))
  const cloned = {
    _id: genId(),
    type: original.type,
    label: original.label,
    icon: original.icon,
    props
  }
  if (cloned.type === 'tabs') {
    cloned.props.tabs = cloned.props.tabs.map(tab => ({ ...tab, _id: genId(), children: [] }))
    if (cloned.props.tabs.length > 0) {
      cloned.props.activeTab = cloned.props.tabs[0]._id
    }
  }
  return cloned
}

function cloneDefinedField(original) {
  const typeMap = basicComponents.reduce((map, c) => {
    map[c.type] = c
    return map
  }, {})
  const base = typeMap[original.type] || typeMap.input
  const props = JSON.parse(JSON.stringify(base.defaultProps))
  props.field = original.field
  props.label = original.label
  return {
    _id: genId(),
    type: original.type,
    label: base.label,
    icon: base.icon,
    props
  }
}

function getFieldTypeIcon(type) {
  const iconMap = {
    input: 'el-icon-edit',
    textarea: 'el-icon-document',
    number: 'el-icon-s-data',
    switch: 'el-icon-switch-button',
    radio: 'el-icon-circle-check',
    checkbox: 'el-icon-finished',
    upload: 'el-icon-upload'
  }
  return iconMap[type] || 'el-icon-edit'
}

function getSwitchActiveValue(field) {
  const props = field && field.props ? field.props : {}
  return props.activeValue !== undefined && props.activeValue !== null ? props.activeValue : 1
}

function getSwitchInactiveValue(field) {
  const props = field && field.props ? field.props : {}
  return props.inactiveValue !== undefined && props.inactiveValue !== null ? props.inactiveValue : 0
}

function isSameLinkageValue(left, right) {
  if (left === right) return true
  if (left === undefined || left === null || right === undefined || right === null) return false
  return String(left) === String(right)
}

function handleSwitchValueChange(field, valueKey, value) {
  if (!field || field.type !== 'switch' || !field.props) return
  if (value === undefined || value === null) {
    ElMessage.warning('开关值不能为空')
    instance?.proxy?.$forceUpdate?.()
    return
  }

  const props = field.props
  const otherKey = valueKey === 'activeValue' ? 'inactiveValue' : 'activeValue'
  if (isSameLinkageValue(value, props[otherKey])) {
    ElMessage.warning('开启值和关闭值不能相同')
    instance?.proxy?.$forceUpdate?.()
    return
  }

  const oldValue = props[valueKey]
  const shouldSyncDefault = isSameLinkageValue(props.defaultValue, oldValue)
  const shouldSyncCanvas = props.field && isSameLinkageValue(canvasFormData.value[props.field], oldValue)
  const rule = Array.isArray(props.linkage)
    ? props.linkage.find(item => item && isSameLinkageValue(item.value, oldValue))
    : null

  props[valueKey] = value
  if (shouldSyncDefault) props.defaultValue = value
  if (shouldSyncCanvas) canvasFormData.value[props.field] = value
  if (rule) rule.value = value
  onLinkageChange()
}

function handleFieldAdd() {
  if (formFields.value.length > 0) {
    const lastField = formFields.value[formFields.value.length - 1]
    handleSelectField(lastField)
  }
}

function handleSelectField(field) {
  activeFieldId.value = field._id
  normalizeInputFetchProps(field)
  normalizeSwitchProps(field)
  normalizeLinkageProps(field)
  activeField.value = field
}

function syncCanvasRadioDefault(field) {
  if (!field || field.type !== 'radio' || !field.props || !field.props.field) return
  const defaultValue = field.props.defaultValue === undefined || field.props.defaultValue === null ? '' : field.props.defaultValue
  canvasFormData.value[field.props.field] = defaultValue
}

function syncCanvasSwitchDefault(field) {
  if (!field || field.type !== 'switch' || !field.props || !field.props.field) return
  const defaultValue = field.props.defaultValue === undefined || field.props.defaultValue === null
    ? getSwitchInactiveValue(field)
    : field.props.defaultValue
  canvasFormData.value[field.props.field] = defaultValue
}

function handleCopyField(index) {
  const source = formFields.value[index]
  const copy = JSON.parse(JSON.stringify(source))
  copy._id = genId()
  if (copy.props.field) copy.props.field = copy.props.field + '_copy'
  if (copy.type === 'tabs' && copy.props.tabs) {
    copy.props.tabs = copy.props.tabs.map(tab => ({ ...tab, _id: genId() }))
    if (copy.props.tabs.length > 0) copy.props.activeTab = copy.props.tabs[0]._id
  }
  formFields.value.splice(index + 1, 0, copy)
  handleSelectField(copy)
}

function handleRemoveField(index) {
  const removed = formFields.value[index]
  formFields.value.splice(index, 1)
  if (activeFieldId.value === removed._id) {
    activeFieldId.value = null
    activeField.value = null
  }
}

function addOption() {
  if (!activeField.value || !activeField.value.props.options) return
  const idx = activeField.value.props.options.length + 1
  activeField.value.props.options.push({ label: `选项${idx}`, value: String(idx) })
  if (activeField.value.type === 'radio') {
    onLinkageChange()
  }
}

function removeOption(idx) {
  if (!activeField.value || !activeField.value.props.options) return
  if (activeField.value.props.options.length <= 1) {
    ElMessage.warning('至少保留一个选项')
    return
  }
  const removedValue = activeField.value.props.options[idx].value
  activeField.value.props.options.splice(idx, 1)
  if (activeField.value.type === 'radio' && activeField.value.props.linkage) {
    const li = activeField.value.props.linkage.findIndex(l => isSameLinkageValue(l.value, removedValue))
    if (li > -1) activeField.value.props.linkage.splice(li, 1)
  }
}

function getLinkageOptions(field) {
  if (!field || !field.props) return []
  if (field.type === 'radio') return Array.isArray(field.props.options) ? field.props.options : []
  if (field.type === 'switch') {
    return [
      { label: field.props.activeText || '开启', value: getSwitchActiveValue(field) },
      { label: field.props.inactiveText || '关闭', value: getSwitchInactiveValue(field) }
    ]
  }
  return []
}

function getLinkageRule(optValue) {
  if (!activeField.value.props.linkage) {
    activeField.value.props.linkage = []
  }
  let rule = activeField.value.props.linkage.find(l => isSameLinkageValue(l.value, optValue))
  if (!rule) {
    rule = { value: optValue, enabled: false, showFields: [] }
    activeField.value.props.linkage.push(rule)
  }
  if (rule.enabled === undefined) {
    rule.enabled = false
  }
  if (!Array.isArray(rule.showFields)) {
    rule.showFields = []
  }
  return rule
}

function onLinkageChange() {
  if (activeField.value) {
    activeField.value.props.linkage = [...(activeField.value.props.linkage || [])]
  }
}

function hasEnabledLinkage(field) {
  if (!field || !['radio', 'switch'].includes(field.type) || !field.props || !Array.isArray(field.props.linkage)) return false
  return field.props.linkage.some(rule => rule && rule.enabled)
}

function addTab() {
  if (!activeField.value || activeField.value.type !== 'tabs') return
  const idx = activeField.value.props.tabs.length + 1
  const newTab = { _id: genId(), name: `标签${idx}`, children: [] }
  activeField.value.props.tabs.push(newTab)
}

function removeTab(idx) {
  if (!activeField.value || activeField.value.type !== 'tabs') return
  if (activeField.value.props.tabs.length <= 1) {
    ElMessage.warning('至少保留一个标签页')
    return
  }
  const removed = activeField.value.props.tabs[idx]
  activeField.value.props.tabs.splice(idx, 1)
  if (activeField.value.props.activeTab === removed._id) {
    activeField.value.props.activeTab = activeField.value.props.tabs[0]._id
  }
}

async function loadFormConfig() {
  try {
    const res = await getConfigTemplateFindApi(configId.value)
    const config = res.data
    if (config && config.fields && Array.isArray(config.fields)) {
      definedFields.value = config.fields
    }
    if (config && config.content) {
      const fields = typeof config.content === 'string' ? JSON.parse(config.content) : config.content
      if (Array.isArray(fields)) {
        replaceFormFields(fields.map(f => ({
          ...f,
          _id: f._id || genId(),
          props: {
            ...f.props,
            ...(f.type === 'input' ? {
              fetchEnabled: typeof f.props.fetchEnabled === 'boolean' ? f.props.fetchEnabled : false,
              fetchButtonText: (f.props.fetchButtonText !== undefined && f.props.fetchButtonText !== null && f.props.fetchButtonText !== '') ? f.props.fetchButtonText : '获取',
              fetchRequest: f.props.fetchRequest && typeof f.props.fetchRequest === 'object'
                ? {
                  url: f.props.fetchRequest.url || '',
                  method: (f.props.fetchRequest.method || 'get').toLowerCase(),
                  paramsJson: f.props.fetchRequest.paramsJson || '',
                  dataJson: f.props.fetchRequest.dataJson || ''
                }
                : { url: '', method: 'get', paramsJson: '', dataJson: '' },
              fetchValuePath: (f.props.fetchValuePath !== undefined && f.props.fetchValuePath !== null && f.props.fetchValuePath !== '') ? f.props.fetchValuePath : 'data'
            } : {}),
            ...(f.type === 'radio' && !f.props.linkage ? { linkage: [] } : {}),
            ...(f.type === 'tabs' && f.props.tabs ? {
              tabs: f.props.tabs.map(tab => ({ ...tab, _id: tab._id || genId(), children: (tab.children || []).map(c => ({ ...c, _id: c._id || genId() })) }))
            } : {})
          }
        })))
        normalizeFieldsProps(formFields.value)
      }
    }
  } catch (e) {
    // 配置可能还没有表单设计数据，忽略
  }
}

function collectAllFields(fields) {
  const result = []
  if (!Array.isArray(fields)) return result
  fields.forEach(f => {
    result.push(f)
    if (f.type === 'card' && Array.isArray(f.props.children)) {
      result.push(...collectAllFields(f.props.children))
    }
    if (f.type === 'tabs' && Array.isArray(f.props.tabs)) {
      f.props.tabs.forEach(tab => {
        result.push(...collectAllFields(tab.children || []))
      })
    }
  })
  return result
}

async function handleSave() {
  const allFields = collectAllFields(formFields.value)
  const invalidSwitch = allFields.find(field => field.type === 'switch' && isSameLinkageValue(getSwitchActiveValue(field), getSwitchInactiveValue(field)))
  if (invalidSwitch) {
    ElMessage.warning(`开关组件「${invalidSwitch.props.label || invalidSwitch.label}」的开启值和关闭值不能相同`)
    handleSelectField(invalidSwitch)
    return
  }
  const fieldsWithoutField = allFields.filter(f => !['card', 'alert', 'tabs'].includes(f.type) && !f.props.field)
  if (fieldsWithoutField.length > 0) {
    ElMessage.warning('请为所有表单组件填写字段标识')
    if (fieldsWithoutField[0]) handleSelectField(fieldsWithoutField[0])
    return
  }
  const fieldKeys = allFields.filter(f => f.props.field).map(f => f.props.field)
  const duplicates = fieldKeys.filter((item, index) => fieldKeys.indexOf(item) !== index)
  if (duplicates.length > 0) {
    ElMessage.warning(`字段标识「${duplicates[0]}」重复，请修改`)
    return
  }
  const aiNewFields = allFields.filter(f => f.props && f.props.aiNewField && f.props.field)
  if (aiNewFields.length > 0) {
    try {
      await ElMessageBox.confirm(`当前存在 ${aiNewFields.length} 个AI新增字段，请确认字段标识已核对无误后再保存。`, '提示', {
        confirmButtonText: '确认保存',
        cancelButtonText: '取消',
        type: 'warning'
      })
    } catch (e) {
      return
    }
  }

  saving.value = true
  try {
    const data = {
      id: configId.value,
      content: JSON.stringify(formFields.value),
      field_keys: JSON.stringify(fieldKeys)
    }
    const res = await saveTemplateApi(data)
    ElMessage.success(res.message || '保存成功')
  } catch (e) {
    ElMessage.error(e.message || '保存失败')
  } finally {
    saving.value = false
  }
}

function handlePreview() {
  if (formFields.value.length === 0) {
    ElMessage.warning('请先添加组件')
    return
  }
  const data = {}
  const tabActive = {}
  const initFieldData = (field) => {
    if (field.props.field) {
      if (field.type === 'checkbox') {
        data[field.props.field] = Array.isArray(field.props.defaultValue) && field.props.defaultValue.length > 0 ? [...field.props.defaultValue] : []
      } else if (field.type === 'number') {
        data[field.props.field] = field.props.defaultValue !== null && field.props.defaultValue !== undefined ? field.props.defaultValue : (field.props.min || 0)
      } else if (field.type === 'switch') {
        data[field.props.field] = field.props.defaultValue !== undefined && field.props.defaultValue !== null ? field.props.defaultValue : getSwitchInactiveValue(field)
      } else {
        data[field.props.field] = field.props.defaultValue !== undefined ? field.props.defaultValue : ''
      }
    }
    if (field.type === 'card' && field.props.children) {
      field.props.children.forEach(child => initFieldData(child))
    }
    if (field.type === 'tabs' && field.props.tabs) {
      field.props.tabs.forEach(tab => {
        (tab.children || []).forEach(child => initFieldData(child))
      })
      if (field.props.tabs.length > 0) {
        tabActive[field._id] = field.props.tabs[0]._id
      }
    }
  }
  formFields.value.forEach(field => initFieldData(field))
  previewFormData.value = data
  previewTabActive.value = tabActive
  previewVisible.value = true
}

function isCanvasFieldVisible(field) {
  if (!field.props || !field.props.field) return true
  const allFields = collectAllFields(formFields.value)
  const controllerFields = allFields.filter(f => ['radio', 'switch'].includes(f.type) && f.props.linkage && f.props.linkage.length > 0)
  for (const controllerField of controllerFields) {
    const enabledRules = controllerField.props.linkage.filter(rule => rule && rule.enabled)
    if (enabledRules.length === 0) continue
    const controlledFields = new Set()
    enabledRules.forEach(rule => {
      if (Array.isArray(rule.showFields)) {
        rule.showFields.forEach(f => controlledFields.add(f))
      }
    })
    if (!controlledFields.has(field.props.field)) continue
    const currentValue = canvasFormData.value[controllerField.props.field]
    const rule = enabledRules.find(item => isSameLinkageValue(item.value, currentValue))
    if (rule) {
      if (!Array.isArray(rule.showFields) || !rule.showFields.includes(field.props.field)) {
        return false
      }
    }
  }
  return true
}

function isFieldVisible(field) {
  if (!field.props.field) return true
  const allFields = collectAllFields(formFields.value)
  const controllerFields = allFields.filter(f => ['radio', 'switch'].includes(f.type) && f.props.linkage && f.props.linkage.length > 0)
  for (const controllerField of controllerFields) {
    const enabledRules = controllerField.props.linkage.filter(rule => rule && rule.enabled)
    if (enabledRules.length === 0) continue
    const controlledFields = new Set()
    enabledRules.forEach(rule => {
      if (Array.isArray(rule.showFields)) {
        rule.showFields.forEach(f => controlledFields.add(f))
      }
    })
    if (!controlledFields.has(field.props.field)) continue
    const currentValue = previewFormData.value[controllerField.props.field]
    const rule = enabledRules.find(item => isSameLinkageValue(item.value, currentValue))
    if (rule) {
      if (!Array.isArray(rule.showFields) || !rule.showFields.includes(field.props.field)) {
        return false
      }
    }
  }
  return true
}

function handleExportJson() {
  if (formFields.value.length === 0) {
    ElMessage.warning('请先添加组件')
    return
  }
  exportJsonStr.value = JSON.stringify(formFields.value, null, 2)
  jsonVisible.value = true
}

function handleCopyJson() {
  const textarea = document.createElement('textarea')
  textarea.value = exportJsonStr.value
  document.body.appendChild(textarea)
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
  ElMessage.success('已复制到剪贴板')
}

async function handleClear() {
  if (formFields.value.length === 0) return
  try {
    await ElMessageBox.confirm('确定清空所有已添加的组件吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
  } catch (e) {
    return
  }
  replaceFormFields([])
  activeFieldId.value = null
  activeField.value = null
}

function handleBack() {
  router.push({ name: 'system_config' })
}

function handleCopyText(field) {
  const text = previewFormData.value[field.props.field] || ''
  if (!text) {
    ElMessage.warning('没有可复制的内容')
    return
  }
  const textarea = document.createElement('textarea')
  textarea.value = text
  document.body.appendChild(textarea)
  textarea.select()
  document.execCommand('copy')
  document.body.removeChild(textarea)
  ElMessage.success('已复制到剪贴板')
}

function handleGenerateRandom(field) {
  const length = field.props.randomLength || 16
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
  let result = ''
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  previewFormData.value[field.props.field] = result
  ElMessage.success('随机字符串已生成')
}

function checkNestedMove(evt) {
  const draggedElement = evt.draggedContext.element
  if (draggedElement && (draggedElement.type === 'card' || draggedElement.type === 'tabs')) {
    return false
  }
  return true
}

function handleCardChildAdd(event, cardField) {
  if (cardField.props.children && cardField.props.children.length > 0) {
    const lastChild = cardField.props.children[cardField.props.children.length - 1]
    handleSelectField(lastChild)
  }
}

function handleCopyCardChild(cardField, childIndex) {
  const source = cardField.props.children[childIndex]
  const copy = JSON.parse(JSON.stringify(source))
  copy._id = genId()
  if (copy.props.field) copy.props.field = copy.props.field + '_copy'
  cardField.props.children.splice(childIndex + 1, 0, copy)
  handleSelectField(copy)
}

function handleRemoveCardChild(cardField, childIndex) {
  const removed = cardField.props.children[childIndex]
  cardField.props.children.splice(childIndex, 1)
  if (activeFieldId.value === removed._id) {
    activeFieldId.value = null
    activeField.value = null
  }
}

function handleTabChildAdd(event, tab) {
  if (tab.children && tab.children.length > 0) {
    const lastChild = tab.children[tab.children.length - 1]
    handleSelectField(lastChild)
  }
}

function handleCopyTabChild(tabsField, tabIndex, childIndex) {
  const tab = tabsField.props.tabs[tabIndex]
  const source = tab.children[childIndex]
  const copy = JSON.parse(JSON.stringify(source))
  copy._id = genId()
  if (copy.props.field) copy.props.field = copy.props.field + '_copy'
  tab.children.splice(childIndex + 1, 0, copy)
  handleSelectField(copy)
}

function handleRemoveTabChild(tabsField, tabIndex, childIndex) {
  const tab = tabsField.props.tabs[tabIndex]
  const removed = tab.children[childIndex]
  tab.children.splice(childIndex, 1)
  if (activeFieldId.value === removed._id) {
    activeFieldId.value = null
    activeField.value = null
  }
}

function handlePreviewFileChange(field, fileList) {
  previewFormData.value[field.props.field] = fileList
}

async function handlePreviewFetchAndFill(field) {
  if (!field || !field.props || !field.props.fetchEnabled || !field.props.fetchRequest || !field.props.fetchRequest.url) {
    ElMessage.warning('未配置请求地址')
    return
  }
  const loadingKey = field._id || field.props.field
  if (previewFetchLoadingMap.value[loadingKey]) return

  const req = field.props.fetchRequest
  const url = req.url
  const method = (req.method || 'get').toLowerCase()

  let params = {}
  let data = {}
  try {
    const parseJson = (text) => {
      if (!text || !String(text).trim()) return {}
      return JSON.parse(String(text).trim())
    }
    params = parseJson(req.paramsJson)
    data = parseJson(req.dataJson)
  } catch (e) {
    ElMessage.error('请求参数 JSON 格式错误')
    return
  }

  setPreviewFetchLoading(loadingKey, true)
  try {
    const config = { url, method }
    if (method === 'get' || method === 'delete') {
      config.params = params
    } else {
      config.data = data
    }
    const res = await request(config)
    const valuePath = field.props.fetchValuePath
    let value
    if (!valuePath || !String(valuePath).trim()) {
      value = res && res.data !== undefined ? res.data : res
    } else {
      const p = String(valuePath).trim().replace(/\[(\d+)\]/g, '.$1').replace(/^\./, '')
      const parts = p.split('.').filter(Boolean)
      let cur = res
      for (const key of parts) {
        if (cur === undefined || cur === null) break
        cur = cur[key]
      }
      value = cur
    }
    if (value === undefined) {
      ElMessage.error('接口返回未找到指定路径')
      return
    }
    previewFormData.value[field.props.field] = value
    ElMessage.success('获取成功')
  } catch (e) {
    ElMessage.error(e.message || '获取失败')
  } finally {
    setPreviewFetchLoading(loadingKey, false)
  }
}

function handlePreviewImage(file) {
  previewImageUrl.value = file.url
  imagePreviewVisible.value = true
}

watch(formFields, fields => {
  const allFields = collectAllFields(fields)
  allFields.forEach(f => {
    if (!f.props || !f.props.field || !['radio', 'switch'].includes(f.type) || f.props.field in canvasFormData.value) return
    const defaultValue = f.type === 'switch'
      ? (f.props.defaultValue !== undefined && f.props.defaultValue !== null ? f.props.defaultValue : getSwitchInactiveValue(f))
      : (f.props.defaultValue !== undefined && f.props.defaultValue !== null ? f.props.defaultValue : '')
    canvasFormData.value[f.props.field] = defaultValue
  })
}, { deep: true })

onBeforeMount(() => {
  configId.value = route.query.id || null
  configName.value = route.query.name || ''
  if (configId.value) {
    loadFormConfig()
  }
})
</script>

<style scoped lang="scss">
.designer-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 84px);
  background: var(--ds-background);
}

// 顶部工具栏
.designer-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  background: var(--dashboard-surface);
  border-bottom: 1px solid var(--dashboard-border);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);

  .toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;

    .toolbar-title {
      font-size: 15px;
      font-weight: 600;
      color: var(--dashboard-text-primary);
    }
  }

  .toolbar-right {
    display: flex;
    gap: 8px;
  }
}

// 设计器主体
.designer-body {
  display: flex;
  flex: 1;
  position: relative;
  overflow: hidden;
}

// 面板通用样式
.designer-panel {
  display: flex;
  flex-direction: column;
  background: var(--dashboard-surface);
  border-right: 1px solid var(--dashboard-border);

  &:last-child {
    border-right: none;
  }

  .panel-title {
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 600;
    color: var(--dashboard-text-primary);
    border-bottom: 1px solid var(--dashboard-border);
    background: var(--dashboard-surface-muted);

    &.panel-title--with-action {
      display: flex;
      align-items: center;
      justify-content: space-between;
      min-height: 47px;
    }

    .panel-ai-btn {
      padding: 0;
      color: var(--ds-primary);
    }
  }
}

// 左侧组件面板
.panel-left {
  width: 250px;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.component-groups {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 8px;
}

.component-group {
  margin-bottom: 8px;

  .group-title {
    padding: 8px 12px;
    font-size: 12px;
    color: var(--dashboard-text-muted);
    font-weight: 500;
  }
}

.ai-config-panel {
  display: flex;
  flex: 1;
  min-height: 0;
  flex-direction: column;
  background: var(--dashboard-surface);
}

.ai-config-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid var(--dashboard-border);
  background: var(--dashboard-surface);
}

.ai-config-heading {
  min-width: 0;
}

.ai-config-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: var(--dashboard-text-primary);

  i {
    color: var(--ds-primary);
  }
}

.ai-config-subtitle {
  margin-top: 3px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
}

.ai-config-messages {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 12px;
  background: var(--dashboard-bg);
}

.ai-config-empty {
  padding: 18px 10px;
  text-align: center;
  color: var(--dashboard-text-muted);
  font-size: 13px;

  i {
    display: block;
    margin-bottom: 8px;
    font-size: 28px;
    color: var(--ds-primary);
  }

  p {
    margin: 8px 0 0;
    line-height: 1.5;
    font-size: 12px;
  }
}

.ai-message {
  margin-bottom: 12px;
}

.ai-message-role {
  margin-bottom: 4px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
}

.ai-message-bubble {
  max-width: 100%;
  padding: 8px 10px;
  border-radius: 6px;
  line-height: 1.5;
  font-size: 12px;
  white-space: pre-wrap;
  word-break: break-word;
}

.ai-message--user {
  .ai-message-role {
    text-align: right;
  }

  .ai-message-bubble {
    color: #fff;
    background: var(--ds-primary);
  }
}

.ai-message--assistant {
  .ai-message-bubble {
    color: var(--dashboard-text-primary);
    background: var(--dashboard-surface);
    border: 1px solid var(--dashboard-border);
  }
}

.ai-message-tags,
.ai-draft-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
}

.ai-message-warnings {
  margin-top: 6px;
  padding: 6px 8px;
  border-radius: 4px;
  color: var(--dashboard-text-secondary);
  background: var(--dashboard-surface-muted);
  font-size: 12px;
  line-height: 1.4;
}

.ai-typing {
  display: inline-flex;
  align-items: center;
  gap: 4px;

  span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--dashboard-text-muted);
    animation: aiTypingPulse 1s infinite ease-in-out;

    &:nth-child(2) {
      animation-delay: 0.15s;
    }

    &:nth-child(3) {
      animation-delay: 0.3s;
    }
  }
}

.ai-draft-bar {
  flex-shrink: 0;
  padding: 10px 12px;
  border-top: 1px solid var(--dashboard-border);
  background: var(--dashboard-surface);

  &.is-collapsed {
    padding-top: 8px;
    padding-bottom: 8px;
  }
}

.ai-draft-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ai-draft-body {
  margin-top: 8px;
}

.ai-draft-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 2px;
  font-size: 12px;
  color: var(--dashboard-text-secondary);
}

.ai-draft-toggle {
  flex-shrink: 0;
  padding: 0;
  color: var(--dashboard-text-muted);

  &:hover {
    color: var(--ds-primary);
  }
}

.ai-draft-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  margin-top: 8px;

  .el-button {
    margin: 0;
  }
}

.ai-config-input {
  display: flex;
  align-items: flex-end;
  gap: 6px;
  padding: 10px 12px;
  border-top: 1px solid var(--dashboard-border);
  background: var(--dashboard-surface);
}

.ai-send-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 36px;
  width: 36px;
  height: 40px;
  border-radius: 4px;
  color: #fff;
  background: var(--ds-primary);
  cursor: pointer;
  transition: background-color 0.2s, opacity 0.2s;

  &:not(.is-disabled):hover {
    opacity: 0.85;
  }

  &:focus-visible {
    outline: 2px solid var(--ds-primary);
    outline-offset: 2px;
  }

  &.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  i {
    font-size: 14px;
  }
}

.ai-config-dock {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  margin: 8px;
  padding: 10px;
  border: 1px solid var(--dashboard-border);
  border-radius: 6px;
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface-muted);
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    border-color: var(--ds-primary);
    color: var(--ds-primary);
  }

  > i {
    font-size: 18px;
    color: var(--ds-primary);
  }
}

.ai-config-dock-title {
  font-size: 13px;
  font-weight: 600;
}

.ai-config-dock-desc {
  margin-top: 2px;
  font-size: 11px;
  color: var(--dashboard-text-muted);
}

.ai-config-float {
  position: absolute;
  left: 12px;
  bottom: 78px;
  z-index: 8;
  box-shadow: 0 4px 12px var(--ds-outline-ghost);
}

@keyframes aiTypingPulse {
  0%,
  80%,
  100% {
    opacity: 0.35;
    transform: translateY(0);
  }

  40% {
    opacity: 1;
    transform: translateY(-2px);
  }
}

.component-list {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 0 8px;
}

.component-list--defined {
  grid-template-columns: minmax(0, 1fr);
}

.component-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  border: 1px dashed var(--dashboard-border);
  border-radius: 4px;
  cursor: grab;
  font-size: 13px;
  color: var(--dashboard-text-secondary);
  transition: all 0.2s;
  user-select: none;

  &:hover {
    border-color: var(--ds-primary);
    color: var(--ds-primary);
    background: var(--ds-surface-container-highest);
  }

  i {
    font-size: 15px;
  }
}

// 预定义字段卡片样式
.component-item--defined {
  display: grid;
  grid-template-columns: 16px minmax(0, 1fr) minmax(0, auto);
  align-items: center;
  gap: 6px;
  width: 100%;
  min-width: 0;
  padding: 8px 10px;

  .item-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 500;
    font-size: 13px;
    line-height: 1.4;
  }

  .item-field {
    max-width: 112px;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 11px;
    color: var(--dashboard-text-muted);
    line-height: 1.3;
  }

  i {
    font-size: 15px;
  }
}

// 中间画布
.panel-center {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.canvas-wrapper {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  background: var(--dashboard-bg);
  position: relative;
  height: calc(100% - 47px);
}

.canvas-area {
  min-height: 100%;
}

.canvas-field {
  position: relative;
  margin-bottom: 12px;
  padding: 12px 16px;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    border-color: var(--ds-primary-container);
    box-shadow: 0 2px 8px var(--ds-outline-ghost);
  }

  &.is-active {
    border-color: var(--ds-primary);
    box-shadow: 0 2px 12px var(--ds-outline-ghost);

    .field-actions {
      opacity: 1;
    }
  }

  .field-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;

    .drag-handle {
      cursor: grab;
      color: var(--dashboard-text-muted);
      font-size: 16px;

      &:hover {
        color: var(--ds-primary);
      }
    }

    .field-type-tag {
      font-size: 12px;
      color: var(--dashboard-text-secondary);
      background: var(--dashboard-surface-muted);
      padding: 2px 8px;
      border-radius: 3px;
    }

    .field-label {
      font-size: 13px;
      font-weight: 500;
      color: var(--dashboard-text-primary);
    }
  }

  .field-preview {
    padding-left: 24px;

    :deep(.el-input__inner),
    :deep(.el-textarea__inner) {
      color: var(--dashboard-text-primary);
      background: var(--dashboard-surface);
      border-color: var(--dashboard-border);
    }

    :deep(.el-input.is-disabled .el-input__inner),
    :deep(.el-textarea.is-disabled .el-textarea__inner) {
      color: var(--dashboard-text-muted) !important;
      background: var(--dashboard-surface-muted) !important;
      border-color: var(--dashboard-border) !important;
    }

    :deep(.el-input__inner::placeholder),
    :deep(.el-textarea__inner::placeholder) {
      color: var(--dashboard-text-muted);
    }

    .upload-preview-box {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;

      .upload-item {
        width: 80px;
        height: 80px;
        border: 1px dashed var(--dashboard-border);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--dashboard-surface-muted);

        .upload-icon {
          font-size: 28px;
          color: #8c939d;
        }
      }
    }

    .preview-card {
      :deep(.el-card__header) {
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 500;
      }
    }

    .input-with-actions {
      display: flex;
      align-items: center;
      gap: 8px;

      .el-input {
        flex: 1;
      }

      .input-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
      }
    }

    .linkage-tip {
      margin-top: 6px;
      font-size: 12px;
      color: var(--ds-primary);
      display: flex;
      align-items: center;
      gap: 4px;
    }

    // Tabs 组件设计器样式
    .designer-tabs {
      :deep(.el-tabs__header) {
        margin-bottom: 0;
      }
    }
  }

  .field-description {
    margin-top: 6px;
    padding-left: 24px;
    font-size: 12px;
    color: var(--dashboard-text-muted);
    line-height: 1.5;

    :deep(a) {
      color: var(--ds-primary);
      text-decoration: none;
      word-break: break-all;

      &:hover {
        text-decoration: underline;
      }
    }
  }

  .field-actions {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.2s;

    i {
      font-size: 20px;
      color: var(--dashboard-text-muted);
      cursor: pointer;
      padding: 4px;
      border-radius: 3px;
      transition: all 0.2s;

      &:hover {
        color: var(--ds-primary);
        background: var(--ds-surface-container-highest);
      }

      &.el-icon-delete:hover {
        color: #f56c6c;
        background: rgba(245, 108, 108, 0.12);
      }
    }
  }
}

// 卡片子组件区域
.card-children-area {
  min-height: 60px;
  padding: 4px;
  background: var(--dashboard-surface-muted);
  border-radius: 4px;
  border: 1px dashed var(--dashboard-border);
}

// Tab 子组件区域
.tab-children-area {
  min-height: 60px;
  padding: 4px;
  background: var(--dashboard-surface-muted);
  border-radius: 4px;
}

.card-child-field {
  margin-bottom: 8px;

  &:last-child {
    margin-bottom: 0;
  }
}

.card-empty-tip,
.tab-empty-tip {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 50px;
  color: var(--dashboard-text-muted);
  font-size: 13px;
  border: 1px dashed var(--dashboard-border);
  border-radius: 4px;
  background: var(--dashboard-surface);
}

.ghost-item {
  opacity: 0.5;
  background: var(--ds-surface-container-highest);
  border: 2px dashed var(--ds-primary);
}

.chosen-item {
  opacity: 0.9;
}

.canvas-empty {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: var(--dashboard-text-muted);

  i {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
  }

  p {
    font-size: 14px;
  }
}

// 右侧属性面板
.panel-right {
  width: 300px;
  flex-shrink: 0;
  overflow-y: auto;
  border-left: 1px solid var(--dashboard-border);
}

.property-form {
  padding: 16px;

  :deep(.el-form-item) {
    margin-bottom: 16px;
  }

  :deep(.el-form-item__label) {
    font-size: 13px;
    color: var(--dashboard-text-secondary);
    padding-bottom: 4px;
  }

  :deep(.el-divider__text) {
    font-size: 13px;
    color: var(--dashboard-text-muted);
    background: var(--dashboard-surface);
  }

  :deep(.el-divider) {
    background-color: var(--dashboard-border);
  }
}

.options-editor {
  .option-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;

    .opt-delete-btn {
      color: #f56c6c;
      padding: 4px;
    }
  }
}

// 联动配置区域
.linkage-editor {
  .linkage-tip-text {
    font-size: 12px;
    color: var(--dashboard-text-muted);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 4px;
    line-height: 1.5;
  }

  .linkage-row {
    margin-bottom: 12px;
    padding: 10px;
    background: var(--dashboard-surface-muted);
    border-radius: 4px;
    border: 1px solid var(--dashboard-border);

    .linkage-row-label {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 6px;
      flex-wrap: wrap;

      .linkage-row-desc {
        font-size: 12px;
        color: var(--dashboard-text-secondary);
      }
    }

    .linkage-row-control {
      margin-bottom: 4px;
    }

    .linkage-disabled-tip {
      font-size: 12px;
      color: var(--dashboard-text-muted);
      padding: 4px 0;
      font-style: italic;
    }
  }
}

// Tabs 配置区域
.tabs-editor {
  .tab-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;

    .opt-delete-btn {
      color: #f56c6c;
      padding: 4px;
    }
  }
}

.property-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 300px;
  color: var(--dashboard-text-muted);

  i {
    font-size: 40px;
    margin-bottom: 12px;
  }

  p {
    font-size: 14px;
  }
}

// 说明文字
.field-description {
  margin-top: 6px;
  font-size: 12px;
  color: var(--dashboard-text-muted);
  line-height: 1.5;
}

// 弹窗样式
:deep(.el-dialog) {
  border-radius: 8px;

  .el-dialog__header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--dashboard-border);
  }

  .el-dialog__body {
    padding: 24px;
  }

  .el-dialog__footer {
    padding: 16px 24px;
    border-top: 1px solid var(--dashboard-border);
  }

  .form-field-description {
    margin-top: 4px;
    font-size: 12px;
    color: var(--dashboard-text-muted);
    line-height: 1.5;

    :deep(a) {
      color: var(--ds-primary);
      text-decoration: none;
      word-break: break-all;

      &:hover {
        text-decoration: underline;
      }
    }
  }

  .input-with-actions {
    display: flex;
    align-items: center;
    gap: 8px;

    .el-input {
      flex: 1;
    }

    .input-actions {
      display: flex;
      gap: 6px;
      flex-shrink: 0;
    }
  }

  .upload-wrapper {
    .avatar-uploader {
      :deep(.el-upload--picture-card) {
        width: 80px;
        height: 80px;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 1px dashed var(--dashboard-border);
        border-radius: 6px;
        background: var(--dashboard-surface-muted);
        transition: all 0.3s;

        &:hover {
          border-color: var(--ds-primary);
        }

        .el-icon-plus {
          font-size: 28px;
          color: #8c939d;
        }
      }

      :deep(.el-upload-list__item) {
        width: 80px;
        height: 80px;
        border-radius: 6px;
        margin: 0 8px 8px 0;
      }

      :deep(.el-upload-list--picture-card) {
        display: flex;
        flex-wrap: wrap;
      }
    }
  }
}

:deep(.el-upload--picture-card) {
  width: 80px;
  height: 80px;
  display: flex;
  justify-content: center;
  align-items: center;
}
</style>
