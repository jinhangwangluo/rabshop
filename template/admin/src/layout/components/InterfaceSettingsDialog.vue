<template>
  <el-dialog
    v-model="innerVisible"
    class="interface-settings-dialog"
    width="900px"
    :append-to-body="true"
    :close-on-click-modal="false"
    @close="handleCancel"
  >
    <template #header>
      <div class="interface-settings__title">界面设置</div>
    </template>
    <div class="interface-settings">
      <aside class="interface-settings__aside">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="interface-settings__tab"
          :class="{ 'interface-settings__tab--active': activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <LegacyIcon :name="tab.icon" />
          <span>{{ tab.label }}</span>
        </button>
      </aside>

      <section class="interface-settings__main">
        <div class="interface-settings__section">
          <div class="interface-settings__heading">样式切换</div>
          <div class="interface-settings__desc">选择应用的主题外观</div>
          <div class="interface-settings__option-row">
            <button
              v-for="item in themeModes"
              :key="item.value"
              type="button"
              class="interface-settings__option"
              :class="{ 'interface-settings__option--active': form.themeMode === item.value }"
              @click="form.themeMode = item.value"
            >
              <span class="interface-settings__option-left">
                <LegacyIcon :name="item.icon" />
                <strong>{{ item.label }}</strong>
              </span>
              <span class="interface-settings__radio" />
            </button>
          </div>
        </div>

        <div class="interface-settings__section">
          <div class="interface-settings__heading">菜单样式</div>
          <div class="interface-settings__desc">选择应用的菜单导航样式</div>
          <div class="interface-settings__menu-row">
            <button
              v-for="item in menuStyles"
              :key="item.value"
              type="button"
              class="interface-settings__menu-card"
              :class="{ 'interface-settings__menu-card--active': form.menuStyle === item.value }"
              @click="form.menuStyle = item.value"
            >
              <span class="interface-settings__preview" :class="item.previewClass">
                <span class="interface-settings__preview-side" />
                <span class="interface-settings__preview-main">
                  <i />
                  <i />
                  <i />
                </span>
              </span>
              <strong>{{ item.label }}</strong>
              <em>{{ item.desc }}</em>
              <span class="interface-settings__radio interface-settings__radio--corner" />
            </button>
          </div>
        </div>

        <div class="interface-settings__section interface-settings__section--split">
          <div>
            <div class="interface-settings__heading">色彩</div>
            <div class="interface-settings__desc">选择主题强调色</div>
            <div class="interface-settings__swatches">
              <button
                v-for="item in themeColors"
                :key="item.value"
                type="button"
                class="interface-settings__swatch"
                :class="{ 'interface-settings__swatch--active': form.themeColor === item.value }"
                :style="{ backgroundColor: item.color }"
                @click="form.themeColor = item.value"
              />
            </div>
          </div>
          <div>
            <div class="interface-settings__heading">字体</div>
            <div class="interface-settings__desc">调整后台基础字号</div>
            <div class="interface-settings__font-group">
              <button
                v-for="item in fontSizes"
                :key="item.value"
                type="button"
                class="interface-settings__font"
                :class="{ 'interface-settings__font--active': form.fontSize === item.value }"
                @click="form.fontSize = item.value"
              >
                {{ item.label }}
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <template #footer>
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" @click="handleConfirm">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useSettingsStore } from '@/store/modules/settings'

const defaultForm = {
  themeMode: 'light',
  menuStyle: 'handAccordion',
  themeColor: 'orange',
  fontSize: 'standard'
}

defineOptions({
  name: 'InterfaceSettingsDialog'
})

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  }
})
const emit = defineEmits(['update:visible'])
const settingsStore = useSettingsStore()
const { themeMode, menuStyle, themeColor, fontSize } = storeToRefs(settingsStore)
const innerVisible = ref(false)
const activeTab = ref('style')
const form = reactive({ ...defaultForm })
const tabs = [
  { key: 'appearance', label: '外观', icon: 'el-icon-magic-stick' },
  { key: 'style', label: '样式切换', icon: 'el-icon-s-operation' },
  { key: 'menu', label: '菜单样式', icon: 'el-icon-menu' },
  { key: 'layout', label: '布局', icon: 'el-icon-s-grid' },
  { key: 'color', label: '色彩', icon: 'el-icon-brush' },
  { key: 'font', label: '字体', icon: 'el-icon-edit-outline' },
  { key: 'other', label: '其他', icon: 'el-icon-setting' }
]
const themeModes = [
  { value: 'light', label: '浅色模式', icon: 'el-icon-sunny' },
  { value: 'dark', label: '深色模式', icon: 'el-icon-moon' },
  { value: 'system', label: '跟随系统', icon: 'el-icon-monitor' }
]
const menuStyles = [
  {
    value: 'handAccordion',
    label: '手风琴（侧边折叠）',
    desc: '侧边菜单分组折叠，节省空间，聚焦内容',
    previewClass: 'interface-settings__preview--side'
  },
  {
    value: 'topFlat',
    label: '顶部导航 + 二级平铺',
    desc: '顶部导航切换，二级功能平铺展示，层级清晰',
    previewClass: 'interface-settings__preview--top'
  }
]
const themeColors = [
  { value: 'orange', color: '#f34100' },
  { value: 'blue', color: '#2f7df6' },
  { value: 'green', color: '#16a36a' },
  { value: 'violet', color: '#6d5dfc' }
]
const fontSizes = [
  { value: 'compact', label: '紧凑' },
  { value: 'standard', label: '标准' },
  { value: 'large', label: '宽松' }
]

watch(() => props.visible, (value) => {
  innerVisible.value = value
  if (value) {
    syncForm()
  }
}, { immediate: true })

watch(innerVisible, (value) => {
  emit('update:visible', value)
})

function syncForm() {
  Object.assign(form, {
    ...defaultForm,
    themeMode: themeMode.value,
    menuStyle: menuStyle.value,
    themeColor: themeColor.value,
    fontSize: fontSize.value
  })
}

function handleConfirm() {
  settingsStore.saveInterfaceSettings({ ...form })
  innerVisible.value = false
}

function handleCancel() {
  syncForm()
  innerVisible.value = false
}
</script>

<style lang="scss" scoped>
.interface-settings {
  display: flex;
  min-height: 460px;
  margin: -24px;
  color: var(--dashboard-text-primary);
  background: var(--dashboard-surface);
}

.interface-settings__title {
  font-weight: 700;
  color: var(--dashboard-text-primary);
}

.interface-settings__aside {
  width: 190px;
  padding: 20px 12px;
  background: var(--dashboard-surface-muted);
  border-right: 1px solid var(--dashboard-border);
}

.interface-settings__tab {
  display: flex;
  align-items: center;
  width: 100%;
  height: 40px;
  padding: 0 14px;
  margin-bottom: 6px;
  color: var(--dashboard-text-secondary);
  cursor: pointer;
  background: transparent;
  border: 0;
  border-radius: 8px;
  transition: all .2s;

  i {
    margin-right: 10px;
    font-size: 16px;
  }

  &:hover,
  &--active {
    color: var(--dashboard-accent);
    background: var(--dashboard-accent-soft);
  }
}

.interface-settings__main {
  flex: 1;
  padding: 26px 30px;
}

.interface-settings__section {
  padding-bottom: 24px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--dashboard-border);

  &:last-child {
    margin-bottom: 0;
    border-bottom: 0;
  }
}

.interface-settings__section--split {
  display: flex;
  justify-content: space-between;
  gap: 36px;
}

.interface-settings__heading {
  margin-bottom: 6px;
  font-size: 18px;
  font-weight: 700;
}

.interface-settings__desc {
  margin-bottom: 16px;
  font-size: 13px;
  color: var(--dashboard-text-muted);
}

.interface-settings__option-row,
.interface-settings__menu-row,
.interface-settings__font-group {
  display: flex;
  gap: 16px;
}

.interface-settings__option,
.interface-settings__menu-card {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 64px;
  padding: 0 18px;
  color: var(--dashboard-text-primary);
  cursor: pointer;
  background: var(--dashboard-surface);
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;
  transition: all .2s;

  &:hover,
  &--active {
    border-color: var(--dashboard-accent);
    box-shadow: 0 8px 20px rgba(47, 79, 120, .12);
  }
}

.interface-settings__option {
  flex: 1;
}

.interface-settings__option-left {
  display: flex;
  align-items: center;

  i {
    margin-right: 10px;
    font-size: 22px;
  }
}

.interface-settings__radio {
  width: 18px;
  height: 18px;
  border: 2px solid var(--dashboard-border);
  border-radius: 50%;
}

.interface-settings__option--active .interface-settings__radio,
.interface-settings__menu-card--active .interface-settings__radio {
  border: 5px solid var(--dashboard-accent);
}

.interface-settings__radio--corner {
  position: absolute;
  top: 16px;
  right: 16px;
}

.interface-settings__menu-card {
  flex: 1;
  flex-direction: column;
  align-items: flex-start;
  justify-content: flex-start;
  min-height: 196px;
  padding: 18px;

  strong {
    margin-top: 14px;
    font-size: 15px;
  }

  em {
    margin-top: 8px;
    font-style: normal;
    font-size: 12px;
    color: var(--dashboard-text-muted);
  }
}

.interface-settings__preview {
  display: flex;
  width: 100%;
  height: 92px;
  padding: 14px;
  background: linear-gradient(135deg, var(--dashboard-surface-muted), var(--dashboard-surface));
  border-radius: 8px;
}

.interface-settings__preview > .interface-settings__preview-side {
  width: 86px;
  margin-right: 12px;
  background: linear-gradient(180deg, var(--dashboard-accent-soft), var(--dashboard-surface-muted));
  border-radius: 6px;
}

.interface-settings__preview-main {
  flex: 1;

  i {
    display: block;
    height: 12px;
    margin-bottom: 10px;
    background: var(--dashboard-surface-muted);
    border-radius: 4px;
  }
}

.interface-settings__preview--top {
  flex-direction: column;

  > .interface-settings__preview-side {
    width: 100%;
    height: 16px;
    margin: 0 0 12px;
  }
}

.interface-settings__swatches {
  display: flex;
  gap: 12px;
}

.interface-settings__swatch {
  width: 34px;
  height: 34px;
  cursor: pointer;
  border: 3px solid transparent;
  border-radius: 50%;

  &--active {
    box-shadow: 0 0 0 3px var(--dashboard-surface), 0 0 0 5px var(--dashboard-accent);
  }
}

.interface-settings__font {
  min-width: 72px;
  height: 34px;
  color: var(--dashboard-text-secondary);
  cursor: pointer;
  background: var(--dashboard-surface-muted);
  border: 1px solid var(--dashboard-border);
  border-radius: 8px;

  &--active {
    color: var(--dashboard-accent);
    border-color: var(--dashboard-accent);
    background: var(--dashboard-accent-soft);
  }
}
</style>
