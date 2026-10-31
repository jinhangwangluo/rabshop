<template>
  <div class="article-content-preview">
    <div class="article-content-preview__heading">
      <span>{{ props.label }}</span>
      <span class="article-content-preview__status">
        <i class="article-content-preview__status-dot" />
        实时预览
      </span>
    </div>

    <div class="phone-frame">
      <div class="phone-frame__speaker" />
      <div class="phone-status-bar">
        <span>9:41</span>
        <div class="phone-status-bar__icons">
          <LegacyIcon name="el-icon-signal" />
          <LegacyIcon name="el-icon-wifi" />
          <LegacyIcon name="el-icon-battery-full" />
        </div>
      </div>
      <div class="phone-nav-bar">
        <LegacyIcon name="el-icon-arrow-left" />
        <span>内容详情</span>
        <LegacyIcon name="el-icon-more" />
      </div>

      <div class="phone-content">
        <div class="phone-article">
          <div class="phone-article__title">{{ props.title || '请输入内容标题' }}</div>
          <div class="phone-article__meta">
            <span class="phone-article__avatar">
              <LegacyIcon name="el-icon-user-solid" />
            </span>
            <span>{{ props.author || '作者' }}</span>
          </div>
          <div class="phone-article__divider" />
          <div
            v-if="hasContent"
            class="phone-article__rich-text"
            v-html="props.content"
          />
          <div v-else class="phone-article__empty">
            <LegacyIcon name="el-icon-document" />
            <span>{{ props.emptyText }}</span>
          </div>
        </div>
      </div>
      <div class="phone-frame__home-indicator" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineOptions({
  name: 'ArticleContentPreview'
})

const props = defineProps({
  label: {
    type: String,
    default: '手机端预览'
  },
  title: {
    type: String,
    default: ''
  },
  author: {
    type: String,
    default: ''
  },
  content: {
    type: String,
    default: ''
  },
  emptyText: {
    type: String,
    default: '编辑内容后将在此处显示'
  }
})

const hasContent = computed(() => {
  return String(props.content || '')
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;|\s/g, '')
    .length > 0 || /<(img|video|audio|table)\b/i.test(props.content || '')
})
</script>

<style lang="scss" scoped>
.article-content-preview {
  width: 286px;
  flex-shrink: 0;

  &__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--prev-color-text-primary);
    font-size: 13px;
    font-weight: 600;
    line-height: 20px;
  }

  &__status {
    display: inline-flex;
    align-items: center;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
    font-weight: 400;
  }

  &__status-dot {
    width: 6px;
    height: 6px;
    margin-right: 5px;
    border-radius: 50%;
    background: #35b46f;
  }
}

.phone-frame {
  position: relative;
  box-sizing: border-box;
  width: 286px;
  height: 520px;
  padding: 8px;
  overflow: hidden;
  border: 1px solid #111111;
  border-radius: 30px;
  background: #111111;
  box-shadow: 0 12px 28px rgba(17, 17, 17, 0.16);

  &__speaker {
    position: absolute;
    top: 12px;
    left: 50%;
    z-index: 2;
    width: 46px;
    height: 4px;
    border-radius: 2px;
    background: #303030;
    transform: translateX(-50%);
  }

  &__home-indicator {
    position: absolute;
    bottom: 12px;
    left: 50%;
    z-index: 2;
    width: 86px;
    height: 4px;
    border-radius: 2px;
    background: #111111;
    transform: translateX(-50%);
  }
}

.phone-status-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
  height: 26px;
  padding: 4px 13px 0;
  border-radius: 22px 22px 0 0;
  color: #1a1c1c;
  background: #ffffff;
  font-size: 10px;
  font-weight: 600;

  &__icons {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 10px;
  }
}

.phone-nav-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
  height: 40px;
  padding: 0 13px;
  color: #1a1c1c;
  background: #ffffff;
  font-size: 13px;
  font-weight: 600;
}

.phone-content {
  height: calc(100% - 66px);
  overflow-y: auto;
  border-radius: 0 0 22px 22px;
  background: #ffffff;
  scrollbar-width: thin;

  &::-webkit-scrollbar {
    width: 3px;
  }

  &::-webkit-scrollbar-thumb {
    border-radius: 2px;
    background: #d8d8dc;
  }
}

.phone-article {
  box-sizing: border-box;
  min-height: 100%;
  padding: 14px 16px 32px;
  color: #1a1c1c;

  &__title {
    overflow-wrap: anywhere;
    font-size: 17px;
    font-weight: 700;
    line-height: 1.45;
  }

  &__meta {
    display: flex;
    align-items: center;
    margin-top: 13px;
    color: #6f7378;
    font-size: 11px;
    line-height: 24px;
  }

  &__avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    margin-right: 8px;
    border-radius: 50%;
    color: #ffffff;
    background: #b7bbc0;
    font-size: 11px;
  }

  &__divider {
    height: 1px;
    margin: 13px 0;
    background: #f0f0f2;
  }

  &__rich-text {
    overflow-wrap: anywhere;
    color: #303236;
    font-size: 13px;
    line-height: 1.8;

    :deep(p) {
      margin: 0 0 10px;
    }

    :deep(h1),
    :deep(h2),
    :deep(h3),
    :deep(h4),
    :deep(h5),
    :deep(h6) {
      margin: 16px 0 8px;
      color: #1a1c1c;
      font-size: 15px;
      line-height: 1.5;
    }

    :deep(img),
    :deep(video) {
      display: block;
      max-width: 100%;
      height: auto;
      margin: 10px auto;
      border-radius: 4px;
    }

    :deep(table) {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    :deep(th),
    :deep(td) {
      padding: 5px;
      overflow-wrap: anywhere;
      border: 1px solid #e5e6e8;
    }

    :deep(blockquote) {
      margin: 10px 0;
      padding: 8px 10px;
      border-left: 3px solid #d9dadd;
      color: #6f7378;
      background: #f7f7f8;
    }

    :deep(pre) {
      max-width: 100%;
      padding: 9px;
      overflow-x: auto;
      border-radius: 4px;
      background: #f4f4f5;
      white-space: pre-wrap;
    }

    :deep(ul),
    :deep(ol) {
      margin: 10px 0;
      padding-left: 20px;
    }
  }

  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 260px;
    color: #a2a5a9;
    font-size: 12px;

    i {
      margin-bottom: 8px;
      font-size: 26px;
    }
  }
}
</style>
