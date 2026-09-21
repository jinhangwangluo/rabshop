<template>
  <div>
    <el-card class="border-radius-top" :bordered="false" shadow="never" :body-style="{ padding: '0 0 10px'}">
      <div class="padding-add">
        <div class="header-action">
          <span style="font-weight: bold; font-size: 16px;">微信菜单管理</span>
        </div>
      </div>
    </el-card>

    <el-card :bordered="false" shadow="never" class="ivu-mt-16 border-radius-bottom">
      <div class="menu-container">
        <!-- Mobile Preview -->
        <div class="mobile-preview">
          <div class="mobile-header">公众号</div>
          <div class="mobile-body" />
          <div class="mobile-footer">
            <ul class="menu-list">
              <li
                v-for="(menu, index) in menus"
                :key="index"
                class="menu-item"
                :class="{ active: checkedMenuId === index && parentMenuId === null }"
                @click="activeMenu(menu, index, null)"
              >
                <span>{{ menu.name || '一级菜单' }}</span>

                <!-- Submenus -->
                <div v-show="checkedMenuId === index" class="sub-menu-list">
                  <div
                    v-for="(child, cindex) in menu.sub_button"
                    :key="cindex"
                    class="sub-menu-item"
                    :class="{ active: checkedMenu === child }"
                    @click.stop="activeMenu(child, cindex, index)"
                  >
                    {{ child.name || '二级菜单' }}
                  </div>
                  <div
                    v-if="!menu.sub_button || menu.sub_button.length < 5"
                    class="sub-menu-item add-btn"
                    @click.stop="addChild(menu, index)"
                  >
                    <el-icon><Plus /></el-icon>
                  </div>
                  <div class="arrow-down" />
                </div>
              </li>

              <li
                v-if="menus.length < 3"
                class="menu-item add-btn"
                @click="addMenu"
              >
                <el-icon><Plus /></el-icon>
              </li>
            </ul>
          </div>
        </div>

        <!-- Editor -->
        <div v-if="checkedMenuId !== null" class="menu-editor">
          <div class="editor-header">
            <span>{{ parentMenuId !== null ? '子菜单设置' : '一级菜单设置' }}</span>
            <el-button type="text" class="delete-btn" @click="delMenu">删除菜单</el-button>
          </div>

          <div class="editor-content">
            <el-form label-width="100px">
              <el-form-item label="菜单名称">
                <el-input v-model="checkedMenu.name" placeholder="菜单名称" maxlength="13" show-word-limit />
                <div class="tips">字数不超过13个汉字</div>
              </el-form-item>

              <el-form-item v-if="!hasSubMenu(checkedMenu)" label="菜单类型">
                <el-select v-model="checkedMenu.type" placeholder="请选择菜单类型" style="width: 100%;">
                  <el-option value="click" label="关键字" />
                  <el-option value="view" label="跳转网页" />
                  <el-option value="miniprogram" label="小程序" />
                </el-select>
              </el-form-item>

              <div v-if="!hasSubMenu(checkedMenu)">
                <el-form-item v-if="checkedMenu.type === 'click'" label="关键字">
                  <el-input v-model="checkedMenu.key" placeholder="请输入关键字" />
                </el-form-item>

                <el-form-item v-if="checkedMenu.type === 'view'" label="跳转地址">
                  <el-input v-model="checkedMenu.url" placeholder="请输入 https:// 开头的链接" />
                </el-form-item>

                <template v-if="checkedMenu.type === 'miniprogram'">
                  <el-form-item label="AppID">
                    <el-input v-model="checkedMenu.appid" placeholder="请输入小程序 AppID" />
                  </el-form-item>
                  <el-form-item label="备用网页">
                    <el-input v-model="checkedMenu.url" placeholder="请输入备用网页 URL" />
                  </el-form-item>
                  <el-form-item label="小程序路径">
                    <el-input v-model="checkedMenu.pagepath" placeholder="请输入小程序页面路径" />
                  </el-form-item>
                </template>
              </div>

              <div v-else class="tips-box">
                已添加子菜单，仅可设置菜单名称。
              </div>

              <el-form-item style="margin-top: 20px;">
                <el-button type="primary" :loading="submitting" @click="submit">保存发布</el-button>
              </el-form-item>
            </el-form>
          </div>
        </div>

        <!-- 未选中时的提示 -->
        <div v-if="checkedMenuId === null" class="menu-editor menu-editor--empty">
          <div class="empty-tip">
            <el-icon><MenuIcon /></el-icon>
            <p>请在左侧选择或添加菜单</p>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { onBeforeMount, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Menu as MenuIcon, Plus } from '@element-plus/icons-vue'
import { getMenus, saveMenus } from '@/api/wechat'

defineOptions({ name: 'WechatMenus' })

const menus = ref([])
const checkedMenu = ref(defaultMenuData())
const checkedMenuId = ref(null)
const parentMenuId = ref(null)
const submitting = ref(false)

function fetchMenus() {
  getMenus().then(res => {
    menus.value = (res.data || []).map(item => ({
      ...item,
      sub_button: item.sub_button || []
    }))
  })
}

function defaultMenuData() {
  return { type: 'click', name: '', sub_button: [] }
}

function defaultChildData() {
  return { type: 'click', name: '', key: '', url: '', appid: '', pagepath: '' }
}

function addMenu() {
  const data = defaultMenuData()
  menus.value.push(data)
  checkedMenu.value = data
  checkedMenuId.value = menus.value.length - 1
  parentMenuId.value = null
}

function addChild(menu, index) {
  if (!menu.sub_button) {
    menu.sub_button = []
  }

  const data = defaultChildData()
  menu.sub_button.push(data)
  checkedMenu.value = data
  checkedMenuId.value = index
  parentMenuId.value = index
}

function activeMenu(menu, index, pid) {
  checkedMenu.value = menu
  if (pid === null) {
    checkedMenuId.value = index
    parentMenuId.value = null
  } else {
    checkedMenuId.value = pid
    parentMenuId.value = pid
  }
}

function delMenu() {
  ElMessageBox.confirm('确定删除该菜单吗？', '提示', {
    type: 'warning'
  }).then(() => {
    if (parentMenuId.value === null) {
      menus.value.splice(checkedMenuId.value, 1)
    } else {
      const subList = menus.value[parentMenuId.value].sub_button
      subList.splice(subList.indexOf(checkedMenu.value), 1)
    }
    checkedMenu.value = defaultMenuData()
    checkedMenuId.value = null
    parentMenuId.value = null
  }).catch(() => {})
}

function validate() {
  if (!menus.value.length) {
    ElMessage.error('请先添加菜单！')
    return false
  }

  for (const menu of menus.value) {
    if (!menu.name) {
      ElMessage.error('菜单名称不能为空！')
      return false
    }
    if (Array.isArray(menu.sub_button) && menu.sub_button.length > 0) {
      for (const child of menu.sub_button) {
        if (!child.name) {
          ElMessage.error('子菜单名称不能为空！')
          return false
        }
        if (child.type === 'view' && !child.url) {
          ElMessage.error('跳转网页类型的子菜单需填写跳转地址！')
          return false
        }
      }
    } else if (menu.type === 'view' && !menu.url) {
      ElMessage.error('跳转网页类型的菜单需填写跳转地址！')
      return false
    }
  }

  return true
}

function hasSubMenu(menu) {
  if (menus.value.includes(menu)) {
    return Array.isArray(menu.sub_button) && menu.sub_button.length > 0
  }
  return false
}

function submit() {
  if (!validate()) return
  submitting.value = true
  saveMenus({ button: menus.value })
    .then(() => {
      ElMessage.success('菜单保存并发布成功！')
    })
    .catch(err => {
      ElMessage.error((err && err.message) || '保存失败，请稍后重试')
    })
    .finally(() => {
      submitting.value = false
    })
}

onBeforeMount(fetchMenus)
</script>

<style scoped>
.menu-container {
  display: flex;
  justify-content: flex-start;
  align-items: flex-start;
  gap: 20px;
}

.mobile-preview {
  width: 317px;
  height: 580px;
  border: 1px solid var(--dashboard-border);
  background: var(--dashboard-surface-muted);
  position: relative;
  display: flex;
  flex-direction: column;
}

.mobile-header {
  height: 50px;
  background: var(--dashboard-text-primary);
  color: var(--dashboard-surface);
  text-align: center;
  line-height: 50px;
  font-size: 15px;
}

.mobile-body {
  flex: 1;
}

.mobile-footer {
  height: 50px;
  background: var(--dashboard-surface);
  border-top: 1px solid var(--dashboard-border);
}

.menu-list {
  display: flex;
  margin: 0;
  padding: 0;
  list-style: none;
  height: 100%;
}

.menu-item {
  flex: 1;
  border-left: 1px solid var(--dashboard-border);
  text-align: center;
  line-height: 50px;
  cursor: pointer;
  position: relative;
  color: var(--dashboard-text-secondary);
}

.menu-item:first-child {
  border-left: none;
}

.menu-item.active {
  border: 1px solid var(--dashboard-accent);
  color: var(--dashboard-accent);
  background: var(--dashboard-surface);
}

.sub-menu-list {
  position: absolute;
  bottom: 60px;
  left: 0;
  width: 100%;
  border: 1px solid var(--dashboard-border);
  background: var(--dashboard-surface);
  z-index: 10;
}

.sub-menu-item {
  height: 50px;
  line-height: 50px;
  border-bottom: 1px solid var(--dashboard-border);
  cursor: pointer;
  color: var(--dashboard-text-secondary);
}

.sub-menu-item:last-child {
  border-bottom: none;
}

.sub-menu-item.active {
  color: var(--dashboard-accent);
  background: var(--dashboard-accent-outline);
}

.arrow-down {
  position: absolute;
  bottom: -6px;
  left: 50%;
  margin-left: -6px;
  width: 0;
  height: 0;
  border-left: 6px solid transparent;
  border-right: 6px solid transparent;
  border-top: 6px solid var(--dashboard-border);
}

.menu-editor {
  flex: 1;
  background: var(--dashboard-surface-muted);
  border: 1px solid var(--dashboard-border);
  padding: 20px;
  min-height: 580px;
  color: var(--dashboard-text-primary);
}

.editor-header {
  display: flex;
  justify-content: space-between;
  border-bottom: 1px solid var(--dashboard-border);
  padding-bottom: 10px;
  margin-bottom: 20px;
}

.tips {
  color: var(--dashboard-text-muted);
  font-size: 12px;
}

.tips-box {
  padding: 10px;
  color: var(--dashboard-text-secondary);
  background-color: var(--dashboard-accent-outline);
  border-left: 4px solid var(--dashboard-accent);
  margin-bottom: 20px;
}

.menu-editor--empty {
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-tip {
  text-align: center;
  color: var(--dashboard-text-muted);
}

.empty-tip i {
  font-size: 40px;
}

.empty-tip p {
  margin-top: 8px;
  font-size: 14px;
}
.border-radius-top{
  border-radius: 12px 12px 0 0;
}
.border-radius-bottom{
  border-radius: 0 0 12px 12px;
}

</style>
