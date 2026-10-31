<template>
  <div class="navbar">
    <hamburger
      id="hamburger-container"
      :is-active="sidebar.opened"
      class="hamburger-container"
      @toggleClick="toggleSideBar"
    />

    <app-breadcrumb
      id="breadcrumb-container"
      class="breadcrumb-container"
    />

    <div class="right-menu">
      <template v-if="device!=='mobile'">

        <div class="layout-navbars-breadcrumb-user-icon" @click="refresh">
          <Refresh class="refresh" :class="animation ? 'element' : ''" />
        </div>

        <search
          id="header-search"
          class="right-menu-item"
        />

        <error-log class="errLog-container right-menu-item hover-effect" />

        <screenfull
          id="screenfull"
          class="right-menu-item hover-effect"
        />
      </template>

      <backend-message-center />

      <el-tooltip
        v-if="aiImageGeneratePinned"
        content="生成AI图片"
        placement="bottom"
      >
        <button
          type="button"
          class="ai-image-entry"
          @click="openAiImageGenerate"
        >
          <MagicStick />
        </button>
      </el-tooltip>

      <el-dropdown
        class="avatar-container right-menu-item hover-effect"
        trigger="click"
      >
        <div class="avatar-wrapper">
          <img
            v-if="adminInfo.avatar"
            :src="adminInfo.avatar"
            class="user-avatar"
          >
          <span class="username">{{ adminInfo.username }}</span>
          <CaretBottom class="navbar-avatar-arrow" />
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <router-link :to="{ name: 'SettingAdminInfo' }">
              <el-dropdown-item>个人信息</el-dropdown-item>
            </router-link>
            <el-dropdown-item
              divided
              @click="logout"
            >
              <span style="display:block;">退出登录</span>
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
    <ai-image-generate-dialog v-model:visible="aiImageGenerateVisibleValue" />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { CaretBottom, MagicStick, Refresh } from '@element-plus/icons-vue'
import AppBreadcrumb from '@/components/Breadcrumb'
import Hamburger from '@/components/Hamburger'
import ErrorLog from '@/components/ErrorLog'
import Screenfull from '@/components/Screenfull'
import Search from '@/components/HeaderSearch'
import BackendMessageCenter from '@/components/BackendMessageCenter'
import AiImageGenerateDialog from '@/components/UploadPicture/AiImageGenerateDialog.vue'
import { useAiImageGenerateStore } from '@/store/modules/aiImageGenerate'
import { useAppStore } from '@/store/modules/app'
import { useBackendMessageStore } from '@/store/modules/backendMessage'
import { useSettingsStore } from '@/store/modules/settings'
import { useUserStore } from '@/store/modules/user'

defineOptions({
  name: 'Navbar'
})

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()
const settingsStore = useSettingsStore()
const userStore = useUserStore()
const backendMessageStore = useBackendMessageStore()
const aiImageGenerateStore = useAiImageGenerateStore()
const { sidebar, device } = storeToRefs(appStore)
const { adminInfo } = storeToRefs(userStore)
const { pinned: aiImageGeneratePinned, visible: aiImageGenerateVisible } = storeToRefs(aiImageGenerateStore)

const animation = ref(false)
const aiImageGenerateVisibleValue = computed({
  get: () => aiImageGenerateVisible.value,
  set: value => aiImageGenerateStore.setVisible(value)
})

onMounted(() => {
  backendMessageStore.init()
})

onBeforeUnmount(() => {
  backendMessageStore.disconnect()
})

function toggleSideBar() {
  appStore.toggleSideBar()
}

async function logout() {
  await userStore.logout()
  router.push(`/login?redirect=${route.fullPath}`)
}

function openAiImageGenerate() {
  aiImageGenerateStore.open()
}

function refresh() {
  animation.value = true
  settingsStore.setIsShowLink(false)
  setTimeout(() => {
    settingsStore.setIsShowLink(true)
    setTimeout(() => {
      animation.value = false
    }, 1000)
  }, 100)
}
</script>

<style lang="scss" scoped>
.navbar {
  height: 50px;
  overflow: hidden;
  position: relative;
  background: var(--ds-surface-card);
  border-bottom: 1px solid var(--ds-outline-ghost);
  box-shadow: 0 6px 18px rgba(15, 12, 0, 0.04);

  .hamburger-container {
    line-height: 46px;
    height: 100%;
    float: left;
    cursor: pointer;
    transition: background .3s;
    -webkit-tap-highlight-color: transparent;

    &:hover {
      background: var(--ds-surface-low);
    }
  }

  .breadcrumb-container {
    float: left;
  }

  .username {
    font-size: 12px;
    color: var(--text-secondary);
  }

  .layout-navbars-breadcrumb-user-icon {
    text-align: center;
    align-items: center;
    display: flex;
    padding: 0 8px;
    border-radius: 8px;
    transition: background .3s;

    &:hover {
      background: var(--ds-surface-low);
    }
  }

  @keyframes rotate {
    from {
      transform: rotate(0deg);
    }
    to {
      transform: rotate(360deg);
    }
  }

  .element {
    animation: rotate 2s linear infinite;
  }

  .layout-navbars-breadcrumb-user-icon .refresh {
    cursor: pointer;
    font-size: 20px;
    color: var(--text-secondary);
    font-weight: 800;
  }

  .errLog-container {
    display: inline-block;
    vertical-align: top;
  }

  .right-menu {
    display: flex;
    float: right;
    height: 100%;
    line-height: 50px;
    align-items: center;
    padding-right: 20px;

    &:focus {
      outline: none;
    }

    .right-menu-item {
      display: inline-flex;
      align-items: center;
      padding: 0 8px;
      height: 100%;
      font-size: 18px;
      color: var(--text-secondary);
      vertical-align: text-bottom;
      border-radius: 8px;

      &.hover-effect {
        cursor: pointer;
        transition: background .3s;

        &:hover {
          background: var(--ds-surface-low);
        }
      }
    }

    .ai-image-entry {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      width: 34px;
      height: 34px;
      margin: 0 8px 0 0;
      padding: 0;
      font-size: 20px;
      color: var(--text-secondary);
      cursor: pointer;
      background: transparent;
      border: 0;
      border-radius: 10px;
      transition: background .2s, color .2s;

      &:hover,
      &:focus {
        color: var(--brand-primary);
        background: var(--ds-surface-low);
        outline: none;
      }
    }

    .avatar-container {
      margin-right: 0;
      margin-left: 8px;

      .avatar-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 8px;
        border-radius: 10px;
        transition: background .3s;

        &:hover {
          background: var(--ds-surface-low);
        }

        .user-avatar {
          cursor: pointer;
          width: 36px;
          height: 36px;
          border-radius: 10px;
          box-shadow: 0 0 0 1px var(--ds-outline-ghost);
        }

        .navbar-avatar-arrow {
          cursor: pointer;
          position: static;
          margin-left: 2px;
          font-size: 12px;
          color: var(--text-placeholder);
        }
      }
    }
  }
}
</style>
