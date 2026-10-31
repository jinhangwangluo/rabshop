<template>
  <div class="content-create-container">

    <div class="sticky ivu-mb-16">
      <el-card
        shadow="never"
        class="box-card"
      >
        <div class="create-header">
          <div class="create-header__left">
            <div
              class="font-sm after-line"
              @click="goBack"
            >
              <LegacyIcon name="el-icon-arrow-left" />
              <span class="pl10">返回</span>
            </div>
            <span class="ht_title ml10">{{ isEdit ? '编辑内容' : '添加内容' }}</span>
          </div>
          <el-button
            v-if="!isEdit"
            type="primary"
            icon="el-icon-download"
            @click="openCollectDialog"
          >采集内容</el-button>
        </div>
      </el-card>
    </div>

    <el-card shadow="never" class="create-card">
      <el-form
        ref="formRef"
        v-loading="loading"
        :model="form"
        :rules="rules"
        label-width="120px"
        class="create-form"
      >
        <!-- 基础信息 -->
        <div class="form-section">
          <div class="section-title">基础信息</div>

          <el-form-item label="内容类型" prop="content_type">
            <el-radio-group v-model="form.content_type" :disabled="isEdit" @change="handleTypeChange">
              <el-radio-button
                v-for="item in contentTypes"
                :key="item.value"
                :label="item.value"
              >
                {{ item.label }}
              </el-radio-button>
            </el-radio-group>
            <div v-if="isEdit" class="form-tip">内容类型创建后不可修改</div>
          </el-form-item>

          <el-form-item label="内容标题" prop="title">
            <div class="title-ai-row">
              <el-input
                v-model="form.title"
                placeholder="请输入内容标题"
                class="form-width"
                maxlength="100"
                show-word-limit
              />
              <button
                v-if="needsRichContent"
                type="button"
                class="ai-writing-btn"
                :class="{ 'is-loading': aiGenerateLoading }"
                :disabled="aiGenerateLoading"
                @click="handleAiGenerate"
              >
                <LegacyIcon v-if="aiGenerateLoading" name="el-icon-loading" />
                <span>{{ aiGenerateLoading ? '生成中' : 'AI润色写作' }}</span>
              </button>
            </div>
          </el-form-item>

          <el-form-item label="内容分类" prop="article_category_path">
            <div class="category-select-row">
              <el-cascader
                v-model="form.article_category_path"
                :options="categoryTree"
                :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
                placeholder="请选择分类"
                class="form-width"
                clearable
                filterable
                @change="handleCategoryChange"
              />
              <el-button
                type="text"
                icon="el-icon-circle-plus-outline"
                class="quick-add-btn"
                @click="openQuickAdd"
              >快捷添加
              </el-button>
            </div>
          </el-form-item>

          <el-form-item label="作者" prop="author">
            <el-input v-model="form.author" placeholder="请输入作者名" class="form-width" />
          </el-form-item>

          <el-form-item label="内容标签">
            <TagSelector ref="tagSelector" v-model="form.tag_ids" :tag-type="1" />
          </el-form-item>

          <el-form-item label="简介" prop="description">
            <el-input
              v-model="form.description"
              type="textarea"
              :rows="3"
              placeholder="请输入内容简介"
              class="form-width"
              maxlength="1000"
              show-word-limit
            />
          </el-form-item>

          <el-form-item label="封面图" prop="cover">
            <Upload v-model="form.cover" :limit="1" />
            <div class="form-tip">建议尺寸：300×300px，支持 JPG、PNG 格式</div>
          </el-form-item>
        </div>

        <!-- 价格设置 -->
        <div class="form-section">
          <div class="section-title">价格设置</div>

          <el-form-item label="是否付费">
            <el-radio-group v-model="isPaid" @change="handlePaidChange">
              <el-radio :label="false">免费</el-radio>
              <el-radio :label="true">付费</el-radio>
            </el-radio-group>
          </el-form-item>

          <template v-if="isPaid">
            <el-form-item label="售价" prop="price">
              <el-input-number
                v-model="form.price"
                :min="0"
                :precision="2"
                :step="1"
                placeholder="请输入售价"
              />
              <span class="unit-text">元</span>
            </el-form-item>

            <el-form-item label="原价">
              <el-input-number
                v-model="form.original_price"
                :min="0"
                :precision="2"
                :step="1"
                placeholder="请输入原价（可选）"
              />
              <span class="unit-text">元</span>
            </el-form-item>

            <el-form-item label="赠送积分" prop="gift_points">
              <el-input-number
                v-model="form.gift_points"
                :min="0"
                :precision="0"
                :step="1"
                placeholder="请输入赠送积分"
              />
            </el-form-item>

            <el-form-item label="支持试读">
              <el-radio-group v-model="form.is_trial">
                <el-radio :label="1">支持试读</el-radio>
                <el-radio :label="0">不支持</el-radio>
              </el-radio-group>
            </el-form-item>
          </template>
        </div>

        <!-- 内容区域 - 根据类型动态展示 -->
        <div class="form-section">
          <div class="section-title">内容详情</div>

          <!-- 图文/文字/小说 - 富文本编辑器 -->
          <el-form-item
            v-if="needsRichContent"
            prop="content"
            :label-width="'200px'"
          >
            <template #label>
              <span class="editor-label-text">正文内容</span>
              <button
                type="button"
                class="ai-continue-btn"
                :class="{ 'is-loading': aiContinueField === 'content' }"
                :disabled="aiContinueField !== '' || aiGenerateLoading"
                @click="handleAiContinue('content')"
              >
                <LegacyIcon v-if="aiContinueField === 'content'" name="el-icon-loading" />
                <span>AI继续帮我写</span>
              </button>
            </template>
            <div class="editor-preview-layout">
              <div class="editor-preview-layout__editor">
                <div class="editor-wrap">
                  <Toolbar
                    :key="`content-toolbar-${form.content_type}`"
                    :editor="editor"
                    :default-config="toolbarConfig"
                    :mode="editorMode"
                  />
                  <Editor
                    :key="`content-editor-${form.content_type}`"
                    v-model="form.content"
                    style="height: 400px; overflow-y: hidden;"
                    :default-config="editorConfig"
                    :mode="editorMode"
                    @onCreated="onEditorCreated"
                  />
                </div>
              </div>
              <ArticleContentPreview
                label="正文手机预览"
                :title="form.title"
                :author="form.author"
                :content="form.content"
              />
            </div>
          </el-form-item>

          <!-- 图文/文字/小说 - 试读富文本编辑器（付费且支持试读时显示） -->
          <el-form-item
            v-if="needsRichContent && isPaid && form.is_trial === 1"
            :label-width="'200px'"
          >
            <template #label>
              <span class="editor-label-text">试读内容</span>
              <button
                type="button"
                class="ai-continue-btn"
                :class="{ 'is-loading': aiContinueField === 'trial_content' }"
                :disabled="aiContinueField !== '' || aiGenerateLoading"
                @click="handleAiContinue('trial_content')"
              >
                <LegacyIcon v-if="aiContinueField === 'trial_content'" name="el-icon-loading" />
                <span>AI继续帮我写</span>
              </button>
            </template>
            <div class="form-tip trial-content-tip">
              <LegacyIcon name="el-icon-info" />
              用户付费前可免费阅读的内容，不填则无试读
            </div>
            <div class="editor-preview-layout">
              <div class="editor-preview-layout__editor">
                <div class="editor-wrap">
                  <Toolbar
                    :key="`trial-toolbar-${form.content_type}`"
                    :editor="trialEditor"
                    :default-config="toolbarConfig"
                    :mode="editorMode"
                  />
                  <Editor
                    :key="`trial-editor-${form.content_type}`"
                    v-model="form.trial_content"
                    style="height: 300px; overflow-y: hidden;"
                    :default-config="trialEditorConfig"
                    :mode="editorMode"
                    @onCreated="onTrialEditorCreated"
                  />
                </div>
              </div>
              <ArticleContentPreview
                label="试读手机预览"
                :title="form.title"
                :author="form.author"
                :content="form.trial_content"
                empty-text="填写试读内容后将在此处显示"
              />
            </div>
          </el-form-item>

          <!-- 图片类型 - 多图上传 -->
          <el-form-item
            v-if="isImageType"
            label="图片列表"
            prop="images"
          >
            <Upload v-model="form.images" :limit="100" />
            <div class="form-tip">可上传多张图片，最多100张</div>
          </el-form-item>
          <el-form-item
            v-if="isImageType && form.is_trial === 1"
            label="试看图片"
            prop="images"
          >
            <Upload v-model="form.trial_images" :limit="20" />
            <div class="form-tip">可上传多张图片，最多20张</div>
          </el-form-item>

          <!-- 音频类型 -->
          <template v-if="isAudioType">
            <el-form-item label="音频文件" prop="media_url">
              <BigFileUpload
                ref="audioUpload"
                v-model="form.media_url"
                :allow-extensions="['mp3', 'wav', 'aac', 'flac', 'm4a', 'ogg']"
                :trial-seconds="form.is_trial === 1 ? trialSeconds : 0"
                accept="audio/*,.mp3,.wav,.aac,.flac,.m4a,.ogg"
                tip="支持 mp3/wav/aac/flac/m4a/ogg 格式，大文件自动分片断点续传"
                class="form-width"
                @duration="handleMediaDuration"
                @success="handleMediaUploadSuccess"
                @trial-clipping="handleTrialClipping"
                @trial-uploaded="handleTrialUploaded"
              />
              <!-- 完整音频播放器 -->
              <div v-if="form.media_url" class="media-player-wrap">
                <div class="player-label">完整音频预览</div>
                <audio :src="previewMediaUrl" controls class="media-player" />
              </div>
            </el-form-item>
            <el-form-item label="音频时长(秒)" prop="media_duration">
              <el-input-number
                v-model="form.media_duration"
                :min="0"
                placeholder="请输入时长（秒）"
              />
              <span v-if="form.media_duration" class="unit-text">{{ formatDuration(form.media_duration) }}</span>
            </el-form-item>
            <!-- 试听片段区域 -->
            <el-form-item v-if="form.is_trial === 1" label="试听片段">
              <div class="trial-block">
                <!-- 截取秒数设置 -->
                <div class="trial-seconds-row">
                  <span class="trial-label">截取前</span>
                  <el-input-number
                    v-model="trialSeconds"
                    :min="5"
                    :max="600"
                    :step="5"
                    class="trial-num"
                  />
                  <span class="trial-label">秒作为试听片段</span>
                  <el-button
                    type="primary"
                    plain
                    icon="el-icon-scissors"
                    :loading="trialClipping"
                    :disabled="!form.media_url"
                    @click="doReClip('audioUpload')"
                  >立即截取
                  </el-button>
                </div>
                <!-- 试听播放器 -->
                <div v-if="form.trial_media_url" class="trial-player-wrap">
                  <div class="trial-player-header">
                    <el-tag type="success">试听片段已设置</el-tag>
                    <el-button
                      type="text"
                      icon="el-icon-delete"
                      class="ml-8"
                      @click="form.trial_media_url = ''"
                    >清除
                    </el-button>
                  </div>
                  <audio :src="previewTrialMediaUrl" controls class="media-player" />
                </div>
                <div v-else class="trial-empty">
                  <LegacyIcon name="el-icon-info" />
                  <span>请先上传音频文件，再点击“立即截取”生成试听片段</span>
                </div>
              </div>
            </el-form-item>
          </template>

          <!-- 视频类型 -->
          <template v-if="isVideoType">
            <el-form-item label="视频文件" prop="media_url">
              <BigFileUpload
                ref="videoUpload"
                v-model="form.media_url"
                :allow-extensions="['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']"
                :trial-seconds="form.is_trial === 1 ? trialSeconds : 0"
                accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv"
                tip="支持 mp4/avi/mov/wmv/flv/mkv 格式，大文件自动分片断点续传"
                class="form-width"
                @duration="handleMediaDuration"
                @success="handleMediaUploadSuccess"
                @trial-clipping="handleTrialClipping"
                @trial-uploaded="handleTrialUploaded"
              />
              <!-- 完整视频播放器 -->
              <div v-if="form.media_url" class="media-player-wrap">
                <div class="player-label">完整视频预览</div>
                <video :src="previewMediaUrl" controls class="media-player video-player" />
              </div>
            </el-form-item>
            <el-form-item label="视频时长(秒)" prop="media_duration">
              <el-input-number
                v-model="form.media_duration"
                :min="0"
                placeholder="请输入时长（秒）"
              />
              <span v-if="form.media_duration" class="unit-text">{{ formatDuration(form.media_duration) }}</span>
            </el-form-item>
            <!-- 试看片段区域 -->
            <el-form-item v-if="form.is_trial === 1" label="试看片段">
              <div class="trial-block">
                <!-- 截取秒数设置 -->
                <div class="trial-seconds-row">
                  <span class="trial-label">截取前</span>
                  <el-input-number
                    v-model="trialSeconds"
                    :min="5"
                    :max="600"
                    :step="5"
                    class="trial-num"
                  />
                  <span class="trial-label">秒作为试看片段</span>
                  <el-button
                    type="primary"
                    plain
                    icon="el-icon-scissors"
                    :loading="trialClipping"
                    :disabled="!form.media_url"
                    @click="doReClip('videoUpload')"
                  >立即截取
                  </el-button>
                </div>
                <!-- 试看播放器 -->
                <div v-if="form.trial_media_url" class="trial-player-wrap">
                  <div class="trial-player-header">
                    <el-tag type="success">试看片段已设置</el-tag>
                    <el-button
                      type="text"
                      icon="el-icon-delete"
                      class="ml-8"
                      @click="form.trial_media_url = ''"
                    >清除
                    </el-button>
                  </div>
                  <video :src="previewTrialMediaUrl" controls class="media-player video-player" />
                </div>
                <div v-else class="trial-empty">
                  <LegacyIcon name="el-icon-info" />
                  <span>请先上传视频文件，再点击“立即截取”生成试看片段</span>
                </div>
              </div>
            </el-form-item>
          </template>

          <!-- 小说专属字段 -->
          <el-form-item v-if="isNovelType" label="字数">
            <el-input-number v-model="form.word_count" :min="0" placeholder="字数统计" />
            <span class="unit-text">字</span>
          </el-form-item>
        </div>

        <!-- 其他设置 -->
        <div class="form-section">
          <div class="section-title">其他设置</div>

          <el-row :gutter="24">
            <el-col :span="8">
              <el-form-item label="排序" prop="sort">
                <el-input-number v-model="form.sort" :min="0" placeholder="数值越大越靠前" />
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="是否热门">
                <el-switch v-model="form.is_hot" :active-value="1" :inactive-value="0" />
              </el-form-item>
            </el-col>
            <!-- <el-col :span="8">
              <el-form-item label="是否轮播">
                <el-switch v-model="form.is_banner" :active-value="1" :inactive-value="0" />
              </el-form-item>
            </el-col> -->
          </el-row>

          <el-form-item label="虚拟学习人数">
            <el-input-number v-model="form.virtual_learn_count" :min="0" :precision="0" placeholder="前台展示时会叠加真实学习人数" />
            <span class="unit-text">人</span>
          </el-form-item>

          <el-form-item label="显示状态">
            <el-radio-group v-model="form.status">
              <el-radio :label="1">显示</el-radio>
              <el-radio :label="0">隐藏</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="关键词">
            <el-input
              v-model="form.keywords"
              placeholder="请输入关键词，多个用逗号分隔"
              class="form-width"
            />
            <div class="form-tip">关键词用于前台搜索使用，多个关键词用逗号分隔</div>
          </el-form-item>
        </div>

        <!-- 关联商品 -->
        <div class="form-section">
          <div class="section-title">
            关联商品
            <span class="section-desc">（已选 {{ form.products.length }} 个商品）</span>
          </div>

          <el-form-item label="关联商品">
            <div class="product-association">
              <div class="product-association-header">
                <el-button type="primary" plain @click="openProductPicker">选择商品</el-button>
                <el-button v-if="form.products.length" type="text" @click="clearProducts">清空</el-button>
              </div>

              <el-table
                v-if="form.products.length"
                :data="form.products"
                row-key="id"
                border
                class="product-table"
              >
                <el-table-column label="封面" width="90" align="center">
                  <template #default="scope">
                    <el-image
                      v-if="scope.row.cover"
                      :src="scope.row.cover"
                      class="mini-cover"
                      fit="cover"
                    />
                    <div v-else class="table-cover-placeholder">暂无</div>
                  </template>
                </el-table-column>
                <el-table-column prop="name" label="商品名称" min-width="220" show-overflow-tooltip />
                <el-table-column label="价格" width="110" align="center">
                  <template #default="scope">
                    ¥{{ formatProductPrice(scope.row.price) }}
                  </template>
                </el-table-column>
                <el-table-column prop="stock" label="库存" width="90" align="center" />
                <el-table-column label="状态" width="120" align="center">
                  <template #default="scope">
                    <el-tag :type="getProductStatusType(scope.row)" size="mini">
                      {{ getProductStatusText(scope.row) }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="90" fixed="right" align="center">
                  <template #default="scope">
                    <el-button type="text" @click="removeProduct(scope.$index)">移除</el-button>
                  </template>
                </el-table-column>
              </el-table>

              <div v-else class="product-empty">
                暂未关联商品，选择后会在移动端内容详情页展示
              </div>

              <div class="form-tip">
                商品用于内容详情页导购，只展示已上架且审核通过的商品；已下架或已删除商品会保留在列表中，可手动移除。
              </div>
            </div>
          </el-form-item>
        </div>

        <!-- 提交按钮 -->
        <div class="form-footer">
          <el-button type="primary" :loading="submitLoading" @click="submitForm">
            {{ isEdit ? '保存修改' : '立即发布' }}
          </el-button>
          <el-button @click="goBack">取消</el-button>
        </div>
      </el-form>
    </el-card>

    <!-- AI润色结果弹窗 -->
    <el-dialog
      v-model="aiResultVisible"
      title="AI润色结果"
      width="860px"
      destroy-on-close
    >
      <div v-if="aiResult" class="ai-result-dialog">
        <el-alert
          title="建议标签可直接导入当前内容标签。"
          type="info"
          show-icon
          :closable="false"
          class="ai-result-alert"
        />
        <div class="ai-result-apply">
          <span class="ai-result-apply__label">应用字段</span>
          <el-checkbox v-model="aiApplyFields.description">简介</el-checkbox>
          <el-checkbox v-model="aiApplyFields.content">正文内容</el-checkbox>
          <el-checkbox v-model="aiApplyFields.trial_content">试读内容</el-checkbox>
          <el-checkbox v-model="aiApplyFields.price">售价</el-checkbox>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">简介</div>
          <div class="ai-result-text">{{ aiResult.description || '暂无简介建议' }}</div>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">正文内容</div>
          <div class="ai-result-rich" v-html="aiResult.content || '暂无正文建议'" />
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">试读内容</div>
          <div class="ai-result-rich" v-html="aiResult.trial_content || '暂无试读建议'" />
        </div>

        <div class="ai-result-section ai-result-section--inline">
          <div class="ai-result-section__title">售价建议</div>
          <div class="ai-result-price">¥{{ formatAiPrice(aiResult.price) }}</div>
        </div>

        <div class="ai-result-section">
          <div class="ai-result-section__title">
            <span>建议标签</span>
            <el-button
              v-if="aiTagText"
              type="text"
              size="mini"
              icon="el-icon-document-copy"
              @click="copyAiTags"
            >复制</el-button>
            <el-button
              v-if="aiTagSuggestions.length"
              type="text"
              size="mini"
              icon="el-icon-download"
              :loading="aiTagImportLoading"
              @click="handleImportAiTags"
            >一键导入</el-button>
          </div>
          <div v-if="aiTagSuggestions.length" class="ai-tag-list">
            <div
              v-for="item in aiTagSuggestions"
              :key="item.name"
              class="ai-tag-item"
              :style="getAiTagStyle(item)"
            >
              <span class="ai-tag-item__name">{{ item.name }}</span>
              <span v-if="item.reason" class="ai-tag-item__reason">{{ item.reason }}</span>
            </div>
          </div>
          <div v-else class="ai-result-empty">暂无标签建议</div>
        </div>
      </div>
      <template #footer>
        <span>
          <el-button @click="aiResultVisible = false">取 消</el-button>
          <el-button type="primary" @click="applyAiResult">应用选中内容</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 内容采集弹窗 -->
    <el-dialog
      v-model="collectVisible"
      title="采集内容"
      width="760px"
      destroy-on-close
    >
      <div class="collect-dialog">
        <el-form label-width="90px" @submit.prevent>
          <el-form-item label="网页URL">
            <el-input
              v-model="collectForm.url"
              placeholder="请输入要采集的网页地址"
              @keyup.enter="handleCollect"
            >
              <template #append>
                <el-button :loading="collectLoading" @click="handleCollect">开始采集</el-button>
              </template>
            </el-input>
            <div class="form-tip">采集时只解析文字和远程图片地址，图片将在文章保存后异步本地化。</div>
          </el-form-item>
        </el-form>

        <template v-if="collectResult">
          <el-alert
            v-for="(item, index) in collectResult.warnings"
            :key="index"
            :title="item"
            type="warning"
            show-icon
            class="collect-warning"
          />
          <el-descriptions :column="1" border class="collect-preview">
            <el-descriptions-item label="标题">{{ collectResult.title || '-' }}</el-descriptions-item>
            <el-descriptions-item label="作者">{{ collectResult.author || '-' }}</el-descriptions-item>
            <el-descriptions-item label="来源">{{ collectResult.source || '-' }}</el-descriptions-item>
            <el-descriptions-item label="简介">{{ collectResult.description || '-' }}</el-descriptions-item>
            <el-descriptions-item label="内容类型">{{ getCollectTypeLabel(collectResult.content_type) }}</el-descriptions-item>
            <el-descriptions-item label="关键词">{{ collectResult.keywords || '-' }}</el-descriptions-item>
          </el-descriptions>
          <div v-if="collectResult.cover" class="collect-cover">
            <div class="collect-label">封面预览</div>
            <img :src="collectResult.cover" alt="封面预览">
          </div>
          <div v-if="collectResult.images && collectResult.images.length" class="collect-images">
            <div class="collect-label">图片预览（{{ collectResult.images.length }}张）</div>
            <div class="collect-image-list">
              <img
                v-for="(image, index) in collectResult.images.slice(0, 12)"
                :key="image + index"
                :src="image"
                alt="采集图片"
              >
            </div>
          </div>
          <div class="collect-content-preview">
            <div class="collect-label">正文预览</div>
            <div class="collect-content-box" v-html="collectResult.content || '暂无正文内容'" />
          </div>
        </template>
      </div>
      <template #footer>
        <span>
          <el-button @click="collectVisible = false">取 消</el-button>
          <el-button type="primary" :disabled="!collectResult" @click="applyCollectResult">确认回填</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 快捷添加分类弹窗 -->
    <el-dialog
      v-model="quickAddVisible"
      title="快捷添加分类"
      width="420px"
      destroy-on-close
    >
      <el-form label-width="90px" class="quick-add-form">
        <el-form-item label="上级分类">
          <el-select
            v-model="quickAddForm.pid"
            placeholder="不选则为一级分类"
            clearable
            style="width: 100%;"
          >
            <el-option label="无（一级分类）" :value="0" />
            <el-option
              v-for="item in quickAddParentList"
              :key="item.id || item.value"
              :label="item.label"
              :value="item.id || item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="分类名称">
          <el-input
            v-model="quickAddForm.name"
            placeholder="请输入分类名称"
            maxlength="30"
            show-word-limit
            @keyup.enter="submitQuickAdd"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="quickAddVisible = false">取 消</el-button>
          <el-button type="primary" :loading="quickAddLoading" @click="submitQuickAdd">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <ProductMultiPicker
      v-model:visible="productPickerVisible"
      :owner-id="form.mer_id"
      :selected-products="selectedAvailableProducts"
      @confirm="handleProductConfirm"
    />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  articleAdd,
  articleRead,
  articleSave,
  collectArticle,
  aiGenerateArticle,
  aiContinueArticle,
  getContentTypes,
  getArticleCategoryTree,
  articleCategoryQuickAdd
} from '@/api/article'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'
import Upload from '@/components/Upload/index'
import BigFileUpload from '@/components/BigFileUpload/index'
import TagSelector from '@/components/TagSelector/index'
import ProductMultiPicker from '@/components/ProductMultiPicker/index'
import { importAiTags } from '@/api/tag'
import { normalizeResourceUrl } from '@/utils'
import ArticleContentPreview from './components/ArticleContentPreview'

defineOptions({
  name: 'ContentCreate'
})

const TYPE_GRAPHIC = 1
const TYPE_TEXT = 2
const TYPE_NOVEL = 3
const TYPE_IMAGE = 4
const TYPE_AUDIO = 5
const TYPE_VIDEO = 6

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const imgModal = proxy.$imgModal

const formRef = ref(null)
const tagSelector = ref(null)
const audioUpload = ref(null)
const videoUpload = ref(null)
const editor = ref(null)
const trialEditor = ref(null)

const loading = ref(false)
const submitLoading = ref(false)
const aiGenerateLoading = ref(false)
const aiContinueField = ref('')
const aiTagImportLoading = ref(false)
const aiResultVisible = ref(false)
const aiResult = ref(null)
const categoryTree = ref([])
const categoryMap = ref({})
const contentTypes = ref([])
const isPaid = ref(false)
const trialSeconds = ref(30)
const trialClipping = ref(false)
const quickAddVisible = ref(false)
const quickAddLoading = ref(false)
const quickAddParentList = ref([])
const collectVisible = ref(false)
const collectLoading = ref(false)
const collectResult = ref(null)
const editorMode = ref('default')

const aiApplyFields = reactive({
  description: true,
  content: true,
  trial_content: true,
  price: true
})

const quickAddForm = reactive({
  name: '',
  pid: 0
})

const collectForm = reactive({
  url: ''
})

function createFormState() {
  return {
    title: '',
    article_category_id: '',
    article_category_path: [],
    author: '',
    description: '',
    cover: '',
    content_type: TYPE_GRAPHIC,
    content: '',
    trial_content: '',
    images: [],
    trial_images: [],
    media_url: '',
    media_duration: 0,
    trial_media_url: '',
    word_count: 0,
    virtual_learn_count: 0,
    price: 0,
    original_price: 0,
    gift_points: 0,
    is_trial: 0,
    sort: 0,
    is_hot: 0,
    is_banner: 0,
    status: 1,
    keywords: '',
    source: '',
    mer_id: 0,
    tag_ids: [],
    products: []
  }
}

const form = reactive(createFormState())

const productPickerVisible = ref(false)

const selectedAvailableProducts = computed(() => form.products.filter(
  (item) => item && item.is_available !== false && !item.is_deleted
))

function openProductPicker() {
  productPickerVisible.value = true
}

/**
 * 合并商品选择结果。
 *
 * 弹窗只展示在售商品，已下架或已删除的历史关联不在弹窗内，需要保留下来由用户手动移除。
 */
function handleProductConfirm(products) {
  const confirmed = Array.isArray(products) ? products : []
  const confirmedIds = new Set(confirmed.map((item) => Number(item.id)))
  const unavailable = form.products.filter(
    (item) => (item.is_available === false || item.is_deleted) && !confirmedIds.has(Number(item.id))
  )
  form.products = [...confirmed, ...unavailable]
}

function removeProduct(index) {
  form.products.splice(index, 1)
}

function clearProducts() {
  form.products = []
}

function formatProductPrice(value) {
  return Number(value || 0).toFixed(2)
}

function getProductStatusText(item) {
  if (item.is_deleted) return '已删除'
  if (Number(item.status) !== 1) return '已下架'
  if (Number(item.audit_status) !== 1) return '审核未通过'
  return '在售'
}

function getProductStatusType(item) {
  if (item.is_deleted) return 'danger'
  if (Number(item.status) !== 1) return 'info'
  if (Number(item.audit_status) !== 1) return 'warning'
  return 'success'
}

const rules = {
  title: [
    { required: true, message: '请输入内容标题', trigger: 'blur' },
    { max: 100, message: '标题最多100个字符', trigger: 'blur' }
  ],
  content_type: [
    { required: true, message: '请选择内容类型', trigger: 'change' }
  ],
  article_category_path: [
    { type: 'array', required: true, message: '请选择内容分类', trigger: 'change' }
  ],
  cover: [
    { required: true, message: '请上传封面图', trigger: 'change' }
  ],
  price: [
    {
      validator: (rule, value, callback) => {
        const price = Number(value || 0)
        if (isPaid.value && price <= 0) {
          callback(new Error('付费内容请设置大于0的价格'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ],
  gift_points: [
    {
      validator: (rule, value, callback) => {
        const points = Number(value || 0)
        if (!Number.isInteger(points) || points < 0) {
          callback(new Error('赠送积分必须为非负整数'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ]
}

const isEdit = computed(() => !!route.query.id)
const needsRichContent = computed(() => [TYPE_GRAPHIC, TYPE_TEXT, TYPE_NOVEL].includes(form.content_type))
const isImageType = computed(() => form.content_type === TYPE_IMAGE)
const isAudioType = computed(() => form.content_type === TYPE_AUDIO)
const isVideoType = computed(() => form.content_type === TYPE_VIDEO)
const isNovelType = computed(() => form.content_type === TYPE_NOVEL)
const canEditorSelectImage = computed(() => form.content_type === TYPE_GRAPHIC)

const toolbarConfig = computed(() => {
  const excludeKeys = ['uploadVideo', 'insertVideo', 'group-video']
  if (!canEditorSelectImage.value) {
    excludeKeys.push('group-image')
  }
  return { excludeKeys }
})

const editorConfig = computed(() => {
  const config = { placeholder: '请输入内容...' }
  if (canEditorSelectImage.value) {
    config.MENU_CONF = {
      uploadImage: {
        customBrowseAndUpload: (insertFn) => {
          handleEditorImageSelect(insertFn)
        }
      }
    }
  }
  return config
})

const trialEditorConfig = computed(() => {
  const config = { placeholder: '请输入试读内容（选填）...' }
  if (canEditorSelectImage.value) {
    config.MENU_CONF = {
      uploadImage: {
        customBrowseAndUpload: (insertFn) => {
          handleEditorImageSelect(insertFn)
        }
      }
    }
  }
  return config
})

const aiTagSuggestions = computed(() => {
  if (!aiResult.value || !Array.isArray(aiResult.value.tag_suggestions)) {
    return []
  }
  return aiResult.value.tag_suggestions
})

const aiTagText = computed(() => aiTagSuggestions.value.map(item => item.name).filter(Boolean).join('，'))
const previewMediaUrl = computed(() => normalizeResourceUrl(form.media_url))
const previewTrialMediaUrl = computed(() => normalizeResourceUrl(form.trial_media_url))

const uploaderRefs = {
  audioUpload,
  videoUpload
}

watch(() => form.content_type, () => {
  if (editor.value) {
    editor.value.destroy()
    editor.value = null
  }
  if (trialEditor.value) {
    trialEditor.value.destroy()
    trialEditor.value = null
  }
})

onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
    editor.value = null
  }
  if (trialEditor.value) {
    trialEditor.value.destroy()
    trialEditor.value = null
  }
})

function onEditorCreated(instance) {
  editor.value = Object.seal(instance)
}

function onTrialEditorCreated(instance) {
  trialEditor.value = Object.seal(instance)
}

function handleEditorImageSelect(insertFn) {
  if (typeof insertFn !== 'function') return
  imgModal((image) => {
    const imageList = Array.isArray(image) ? image : (image ? [image] : [])
    imageList.forEach(item => {
      const url = item && (item.path || item.url || item.att_dir || item.src)
      if (url) {
        insertFn(url, item.real_name || item.name || '', url)
      }
    })
  }, 20)
}

async function initData() {
  await Promise.all([
    loadContentTypes(),
    loadCategoryList()
  ])
  if (isEdit.value) {
    getDetail(route.query.id)
  } else if (route.query.cid) {
    const cid = Number(route.query.cid)
    form.article_category_id = cid
    const item = categoryMap.value[cid]
    if (item && item.pid) {
      form.article_category_path = [item.pid, cid]
    } else {
      form.article_category_path = [cid]
    }
  }
}

function loadContentTypes() {
  return getContentTypes().then(res => {
    contentTypes.value = res.data.list || []
  })
}

function loadCategoryList() {
  return getArticleCategoryTree().then(res => {
    const tree = res.data || []
    categoryTree.value = tree
    quickAddParentList.value = tree
    const buildMap = (list, map = {}) => {
      list.forEach(item => {
        map[item.value] = item
        if (item.children && item.children.length) {
          buildMap(item.children, map)
        }
      })
      return map
    }
    categoryMap.value = buildMap(tree)
  })
}

function getDetail(id) {
  loading.value = true
  articleRead(id).then(res => {
    const data = res.data
    const catId = data.article_category_id ? Number(data.article_category_id) : ''
    let catPath = []
    if (catId) {
      const catItem = categoryMap.value[catId]
      if (catItem && catItem.pid) {
        catPath = [catItem.pid, catId]
      } else if (catId) {
        catPath = [catId]
      }
    }
    Object.assign(form, createFormState(), data, {
      article_category_id: catId,
      article_category_path: catPath,
      content_type: Number(data.content_type) || TYPE_GRAPHIC,
      status: Number(data.status),
      is_hot: Number(data.is_hot),
      is_banner: Number(data.is_banner),
      is_trial: Number(data.is_trial),
      sort: Number(data.sort) || 0,
      price: parseFloat(data.price) || 0,
      original_price: parseFloat(data.original_price) || 0,
      gift_points: Number(data.gift_points) || 0,
      word_count: Number(data.word_count) || 0,
      virtual_learn_count: Number(data.virtual_learn_count) || 0,
      media_url: normalizeResourceUrl(data.media_url),
      media_duration: Number(data.media_duration) || 0,
      trial_media_url: normalizeResourceUrl(data.trial_media_url),
      images: Array.isArray(data.images) ? data.images : [],
      trial_images: Array.isArray(data.trial_images) ? data.trial_images : [],
      content: data.content || '',
      trial_content: data.trial_content || '',
      mer_id: Number(data.mer_id) || 0,
      tag_ids: Array.isArray(data.tag_ids) ? data.tag_ids : [],
      products: Array.isArray(data.products) ? data.products : []
    })
    isPaid.value = form.price > 0
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

function handleTypeChange() {
  if (editor.value) {
    editor.value.destroy()
    editor.value = null
  }
  if (trialEditor.value) {
    trialEditor.value.destroy()
    trialEditor.value = null
  }
  form.content = ''
  form.trial_content = ''
  form.images = []
  form.trial_images = []
  form.media_url = ''
  form.media_duration = 0
  form.trial_media_url = ''
}

function handlePaidChange(val) {
  if (!val) {
    form.price = 0
    form.original_price = 0
    form.is_trial = 0
  }
}

function handleAiGenerate() {
  if (!needsRichContent.value) {
    message.warning('AI润色写作仅支持图文、文字、小说类型')
    return
  }
  if (!String(form.title || '').trim()) {
    message.warning('请先输入内容标题')
    return
  }
  aiGenerateLoading.value = true
  aiGenerateArticle({
    title: form.title,
    content_type: form.content_type,
    author: form.author,
    keywords: form.keywords,
    description: form.description,
    content: form.content,
    trial_content: form.trial_content,
    price: form.price,
    is_trial: form.is_trial
  }).then(res => {
    aiResult.value = res.data || null
    Object.assign(aiApplyFields, {
      description: true,
      content: true,
      trial_content: true,
      price: true
    })
    aiResultVisible.value = true
    message.success(res.message || 'AI生成成功')
  }).catch(res => {
    message.error(res.message || 'AI生成失败')
  }).finally(() => {
    aiGenerateLoading.value = false
  })
}

function applyAiResult() {
  if (!aiResult.value) return
  if (aiApplyFields.description) {
    form.description = aiResult.value.description || form.description
  }
  if (aiApplyFields.content) {
    form.content = aiResult.value.content || form.content
  }
  if (aiApplyFields.trial_content) {
    form.trial_content = aiResult.value.trial_content || form.trial_content
  }
  if (aiApplyFields.price) {
    const price = Number(aiResult.value.price || 0)
    form.price = price > 0 ? price : 0
    isPaid.value = form.price > 0
    if (isPaid.value && form.trial_content) {
      form.is_trial = 1
    } else if (!isPaid.value) {
      handlePaidChange(false)
    }
  }
  aiResultVisible.value = false
  message.success('已应用AI润色内容')
}

function handleAiContinue(field) {
  if (!needsRichContent.value) {
    message.warning('AI续写仅支持图文、文字、小说类型')
    return
  }
  if (!String(form.title || '').trim()) {
    message.warning('请先输入内容标题')
    return
  }
  if (aiContinueField.value !== '') return
  aiContinueField.value = field
  aiContinueArticle({
    title: form.title,
    content_type: form.content_type,
    field,
    content: form[field] || ''
  }).then(res => {
    const added = res && res.data && res.data.content
    if (!added) {
      message.warning((res && res.message) || 'AI未返回续写内容，请重试')
      return
    }
    form[field] = (form[field] || '') + added
    message.success((res && res.message) || '续写成功')
  }).catch(err => {
    message.error((err && err.message) || 'AI续写失败')
  }).finally(() => {
    aiContinueField.value = ''
  })
}

function handleImportAiTags() {
  if (!aiTagSuggestions.value.length) {
    message.warning('暂无可导入的建议标签')
    return
  }
  aiTagImportLoading.value = true
  importAiTags({
    tag_type: 1,
    tag_suggestions: aiTagSuggestions.value
  }).then(res => {
    const data = res.data || {}
    const ids = Array.isArray(data.tag_ids) ? data.tag_ids.map(item => Number(item)).filter(item => item > 0) : []
    const currentIds = Array.isArray(form.tag_ids) ? form.tag_ids.map(item => Number(item)) : []
    const mergedIds = currentIds.slice()
    ids.forEach(id => {
      if (mergedIds.indexOf(id) === -1) {
        mergedIds.push(id)
      }
    })
    form.tag_ids = mergedIds
    refreshTagSelector()
    message.success(formatAiTagImportMessage(data))
  }).finally(() => {
    aiTagImportLoading.value = false
  })
}

function refreshTagSelector() {
  nextTick(() => {
    if (tagSelector.value && typeof tagSelector.value.loadData === 'function') {
      tagSelector.value.loadData()
    }
  })
}

function formatAiTagImportMessage(data) {
  const createdCount = Number(data.created_count || 0)
  const existsCount = Number(data.exists_count || 0)
  if (createdCount > 0 && existsCount > 0) {
    return `已新增${createdCount}个标签，复用${existsCount}个已有标签`
  }
  if (createdCount > 0) {
    return `已新增${createdCount}个标签`
  }
  return existsCount > 0 ? `已复用${existsCount}个已有标签` : '标签已导入'
}

function getAiTagStyle(item) {
  const style = {}
  if (item.text_color) {
    style.color = item.text_color
  }
  if (item.bg_color) {
    style.backgroundColor = item.bg_color
  }
  if (item.border_color) {
    style.borderColor = item.border_color
  }
  return style
}

function copyAiTags() {
  const text = aiTagText.value
  if (!text) {
    message.warning('暂无可复制的建议标签')
    return
  }
  if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => {
      message.success('建议标签已复制')
    }).catch(() => {
      copyTextFallback(text)
    })
    return
  }
  copyTextFallback(text)
}

function copyTextFallback(text) {
  const input = document.createElement('textarea')
  input.value = text
  input.style.position = 'fixed'
  input.style.left = '-9999px'
  document.body.appendChild(input)
  input.select()
  document.execCommand('copy')
  document.body.removeChild(input)
  message.success('建议标签已复制')
}

function formatAiPrice(price) {
  const value = Number(price || 0)
  return value.toFixed(2)
}

function handleCategoryChange(val) {
  form.article_category_id = val && val.length > 0 ? val[val.length - 1] : ''
}

function openQuickAdd() {
  Object.assign(quickAddForm, { name: '', pid: 0 })
  quickAddVisible.value = true
}

function openCollectDialog() {
  collectForm.url = ''
  collectResult.value = null
  collectVisible.value = true
}

function handleCollect() {
  const url = collectForm.url.trim()
  if (!url) {
    message.warning('请输入要采集的网页地址')
    return
  }
  collectLoading.value = true
  collectArticle({ url }).then(res => {
    collectResult.value = res.data || null
    message.success(res.message || '采集成功')
  }).catch(res => {
    message.error(res.message || '采集失败')
  }).finally(() => {
    collectLoading.value = false
  })
}

function getCollectTypeLabel(type) {
  const item = contentTypes.value.find(item => Number(item.value) === Number(type))
  return item ? item.label : '未知'
}

function applyCollectResult() {
  const data = collectResult.value
  if (!data) return
  const contentType = Number(data.content_type) || TYPE_GRAPHIC
  form.title = data.title || form.title
  form.author = data.author || form.author
  form.source = data.source || form.source
  form.description = data.description || form.description
  form.cover = data.cover || form.cover
  form.content_type = contentType
  form.keywords = data.keywords || form.keywords
  if ([TYPE_GRAPHIC, TYPE_TEXT, TYPE_NOVEL].includes(contentType)) {
    form.content = data.content || ''
    form.images = []
  } else if (contentType === TYPE_IMAGE) {
    form.content = ''
    form.images = Array.isArray(data.images) ? data.images.slice(0, 100) : []
  }
  form.media_url = ''
  form.media_duration = 0
  form.trial_media_url = ''
  collectVisible.value = false
  message.success('采集内容已回填，请检查后提交')
}

function submitQuickAdd() {
  const name = quickAddForm.name.trim()
  if (!name) {
    message.warning('请输入分类名称')
    return
  }
  quickAddLoading.value = true
  articleCategoryQuickAdd({
    name,
    pid: quickAddForm.pid || 0
  }).then(res => {
    message.success('添加成功')
    quickAddVisible.value = false
    loadCategoryList().then(() => {
      const newItem = res.data
      if (newItem && newItem.id) {
        const pid = newItem.pid || 0
        if (pid > 0) {
          form.article_category_path = [pid, newItem.id]
        } else {
          form.article_category_path = [newItem.id]
        }
        form.article_category_id = newItem.id
      }
    })
    quickAddLoading.value = false
  }).catch(() => {
    quickAddLoading.value = false
  })
}

function submitForm() {
  formRef.value.validate(valid => {
    if (!valid) return
    if ((isAudioType.value || isVideoType.value) && !form.media_url) {
      message.error('请上传媒体文件')
      return
    }
    if (isImageType.value && (!form.images || form.images.length === 0)) {
      message.error('请上传至少一张图片')
      return
    }
    if (isImageType.value && form.is_trial && (!form.trial_images || form.trial_images.length === 0)) {
      message.error('请上传至少一张图片')
      return
    }
    submitLoading.value = true
    const submitData = { ...form }
    submitData.product_ids = form.products.map((item) => Number(item.id)).filter((id) => id > 0)
    delete submitData.products
    if (!isPaid.value) {
      submitData.price = 0
      submitData.original_price = 0
      submitData.gift_points = 0
      submitData.is_trial = 0
    } else {
      submitData.gift_points = Math.max(0, parseInt(submitData.gift_points || 0, 10))
    }
    const api = isEdit.value
      ? articleSave(route.query.id, submitData)
      : articleAdd(submitData)
    api.then(res => {
      message.success(res.message || '操作成功')
      goBack()
      submitLoading.value = false
    }).catch(res => {
      submitLoading.value = false
      message.error(res.message || '操作失败')
    })
  })
}

async function doReClip(refName) {
  const uploader = uploaderRefs[refName] && uploaderRefs[refName].value
  if (!uploader) return
  uploader.trialSeconds = trialSeconds.value
  await uploader.reGenerateTrialClip()
}

function handleTrialClipping(isClipping) {
  trialClipping.value = isClipping
}

function handleTrialUploaded(url) {
  form.trial_media_url = normalizeResourceUrl(url)
  message.success('试听片段已生成并上传成功')
}

function handleMediaUploadSuccess({ url }) {
  form.media_url = normalizeResourceUrl(url)
}

function handleMediaDuration(seconds) {
  if (seconds > 0) {
    form.media_duration = seconds
    message.success('已自动识别时长：' + formatDuration(seconds))
  }
}

function formatDuration(sec) {
  if (!sec) return ''
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  if (h > 0) return `${h}时${m}分${s}秒`
  if (m > 0) return `${m}分${s}秒`
  return `${s}秒`
}

function goBack() {
  router.push('/article/article')
}

initData()
</script>

<style lang="scss" scoped>
.content-create-container {
  min-height: calc(100vh - 100px);
  color: var(--prev-color-text-primary);
  background: var(--prev-bg-main-color);
}

.sticky {
  background: var(--prev-bg-main-color);
}

.create-card {
  border-radius: 8px;
  margin: 0 auto;
  border-color: var(--prev-border-color-light);
  background: var(--prev-bg-white);

  :deep(.el-card__header) {
    padding: 16px 24px;
    border-bottom: 1px solid var(--prev-border-color-lighter);
    background: var(--prev-bg-white);
  }

  :deep(.el-card__body) {
    color: var(--prev-color-text-primary);
    background: var(--prev-bg-white);
  }
}

.box-card {
  border-color: var(--prev-border-color-light);
  background: var(--prev-bg-white);

  :deep(.el-card__body) {
    color: var(--prev-color-text-primary);
    background: var(--prev-bg-white);
  }
}

.create-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;

  &__left {
    display: flex;
    align-items: center;
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;

  .header-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--prev-color-text-primary);

    i {
      margin-right: 8px;
      color: var(--prev-color-primary);
    }
  }
}

.create-form {
  padding: 8px 0;
}

.form-section {
  margin-bottom: 24px;
  padding: 20px 24px;
  background: var(--prev-bg-color);
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;

  &:last-of-type {
    margin-bottom: 0;
  }

  .section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--prev-color-text-primary);
    margin-bottom: 20px;
    padding-left: 10px;
    border-left: 3px solid var(--prev-color-primary);
  }

  .section-desc {
    margin-left: 8px;
    font-size: 12px;
    font-weight: 400;
    color: var(--prev-color-text-placeholder);
  }
}

.product-association {
  width: 100%;

  .product-association-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
  }

  .product-table {
    width: 100%;
  }

  .mini-cover {
    width: 54px;
    height: 38px;
    border-radius: 4px;
  }

  .table-cover-placeholder {
    color: var(--prev-color-text-placeholder);
    font-size: 12px;
  }

  .product-empty {
    padding: 24px 0;
    color: var(--prev-color-text-placeholder);
    font-size: 13px;
  }
}

.form-width {
  width: 480px;
}

.title-ai-row {
  display: flex;
  align-items: center;
  gap: 14px;
}

.ai-writing-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 110px;
  height: 30px;
  padding: 0 18px;
  border: 0;
  border-radius: 22px;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: 0;
  text-shadow: 0 1px 6px rgba(19, 28, 84, 0.35);
  background-image: url('~@/assets/images/button_ai.jpg');
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
  box-shadow: 0 8px 20px rgba(109, 94, 248, 0.24);
  cursor: pointer;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;

  i {
    margin-right: 6px;
    font-size: 15px;
  }

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(46, 214, 201, 0.26);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.72;
  }
}

.editor-label-text {
  margin-right: 8px;
  white-space: nowrap;
  display: block;
}

.ai-continue-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  height: 24px;
  padding: 0 10px;
  border: 0;
  border-radius: 12px;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  text-shadow: 0 1px 6px rgba(19, 28, 84, 0.35);
  background-image: url('~@/assets/images/button_ai.jpg');
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
  box-shadow: 0 4px 12px rgba(109, 94, 248, 0.22);
  cursor: pointer;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;

  i {
    margin-right: 4px;
    font-size: 12px;
  }

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(46, 214, 201, 0.26);
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.72;
  }
}

.category-select-row {
  display: flex;
  align-items: center;
  gap: 10px;

  .form-width {
    flex-shrink: 0;
  }
}

.quick-add-btn {
  font-size: 13px;
  padding: 0;
  white-space: nowrap;
}

.quick-add-form {
  padding: 0 8px;
}

.form-tip {
  font-size: 12px;
  color: var(--prev-color-text-secondary);
  margin-top: 6px;
  line-height: 1.5;
}

.trial-content-tip {
  margin-top: 0;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 4px;

  i {
    color: var(--prev-color-primary);
  }
}

.unit-text {
  margin-left: 8px;
  font-size: 14px;
  color: var(--prev-color-text-regular);
}

// ===== 完整文件播放器 =====
.media-player-wrap {
  margin-top: 10px;
  max-width: 480px;

  .player-label {
    font-size: 12px;
    color: var(--prev-color-text-secondary);
    margin-bottom: 6px;
  }
}

.media-player {
  width: 100%;
  outline: none;
  border-radius: 4px;
}

.video-player {
  max-height: 240px;
  background: #000;
}

// ===== 试听/试看片段区域 =====
.trial-block {
  max-width: 560px;
}

.trial-seconds-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 12px;

  .trial-label {
    font-size: 13px;
    color: var(--prev-color-text-regular);
    white-space: nowrap;
  }

  .trial-num {
    width: 110px;
  }
}

.trial-player-wrap {
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
  padding: 12px 14px;
  background: var(--prev-bg-white);

  .trial-player-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
  }
}

.trial-empty {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--prev-color-text-secondary);
  padding: 10px 0;

  i {
    color: var(--prev-color-primary);
  }
}

.collect-dialog {
  .collect-warning {
    margin-bottom: 10px;
  }
}

.collect-preview {
  margin-top: 12px;
}

.collect-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--prev-color-text-primary);
  margin: 14px 0 8px;
}

.collect-cover {
  img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid var(--prev-border-color-lighter);
  }
}

.collect-image-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  img {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid var(--prev-border-color-lighter);
  }
}

.collect-content-box {
  max-height: 240px;
  overflow-y: auto;
  padding: 12px;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
  color: var(--prev-color-text-primary);
  background: var(--prev-bg-color);

  :deep(img) {
    max-width: 100%;
    height: auto;
  }
}

.ai-result-dialog {
  color: var(--prev-color-text-primary);
}

.ai-result-alert {
  margin-bottom: 14px;
}

.ai-result-apply {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  padding: 12px 14px;
  margin-bottom: 14px;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
  background: var(--prev-bg-color);

  &__label {
    color: var(--prev-color-text-primary);
    font-weight: 600;
  }
}

.ai-result-section {
  margin-bottom: 14px;
  padding: 14px;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
  background: var(--prev-bg-white);

  &--inline {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--prev-color-text-primary);
    font-weight: 600;
  }
}

.ai-result-text {
  color: var(--prev-color-text-regular);
  line-height: 1.7;
}

.ai-result-rich {
  max-height: 260px;
  overflow-y: auto;
  padding: 12px;
  color: var(--prev-color-text-regular);
  line-height: 1.75;
  border-radius: 4px;
  background: var(--prev-bg-color);

  :deep(p) {
    margin: 0 0 8px;
  }

  :deep(h2),
  :deep(h3),
  :deep(h4) {
    margin: 10px 0 8px;
    color: var(--prev-color-text-primary);
  }

  :deep(ul),
  :deep(ol) {
    padding-left: 20px;
  }
}

.ai-result-price {
  color: var(--prev-color-primary);
  font-size: 18px;
  font-weight: 700;
}

.ai-tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.ai-tag-item {
  display: inline-flex;
  align-items: center;
  max-width: 100%;
  padding: 6px 10px;
  border: 1px solid rgba(109, 94, 248, 0.24);
  border-radius: 16px;
  background: var(--prev-color-primary-light-9);

  &__name {
    flex-shrink: 0;
    color: inherit;
    font-weight: 600;
  }

  &__reason {
    margin-left: 8px;
    color: var(--prev-color-text-secondary);
    font-size: 12px;
  }
}

.ai-result-empty {
  color: var(--prev-color-text-secondary);
  font-size: 13px;
}

.ml-8 {
  margin-left: 8px;
}

.editor-wrap {
  border: 1px solid var(--prev-border-color-base);
  border-radius: 4px;
  overflow: hidden;
  background: var(--prev-bg-white);

  :deep(.w-e-bar),
  :deep(.w-e-toolbar) {
    color: var(--prev-color-text-primary) !important;
    background-color: var(--prev-bg-white) !important;
    border-bottom: 1px solid var(--prev-border-color-lighter) !important;
  }

  :deep(.w-e-bar-item button),
  :deep(.w-e-bar-item .title) {
    color: var(--prev-color-text-regular) !important;
  }

  :deep(.w-e-bar-item button:hover),
  :deep(.w-e-bar-item .active) {
    color: var(--prev-color-primary) !important;
    background-color: var(--prev-color-primary-light-9) !important;
  }

  :deep(.w-e-text-container) {
    color: var(--prev-color-text-primary) !important;
    background-color: var(--prev-bg-white) !important;
  }

  :deep(.w-e-text-container [data-slate-editor]),
  :deep(.w-e-scroll),
  :deep(.w-e-text) {
    color: var(--prev-color-text-primary) !important;
    background-color: var(--prev-bg-white) !important;
  }

  :deep(.w-e-panel),
  :deep(.w-e-select-list) {
    color: var(--prev-color-text-primary) !important;
    background-color: var(--prev-bg-white) !important;
    border-color: var(--prev-border-color-base) !important;
  }
}

.editor-preview-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 286px;
  align-items: start;
  gap: 24px;
  width: 100%;

  &__editor {
    min-width: 0;
  }
}

@media (max-width: 1180px) {
  .editor-preview-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}

.form-footer {
  padding: 24px 24px 8px;
  display: flex;
  align-items: center;
  gap: 12px;

  :deep(.el-button) {
    padding: 10px 30px;
    font-size: 14px;
  }
}

:deep(.el-radio-button__orig-radio:checked + .el-radio-button__inner) {
  color: var(--prev-color-text-white);
  background-color: var(--prev-color-primary);
  border-color: var(--prev-color-primary);
}

:deep(.el-form-item__label) {
  font-weight: 500;
  color: var(--prev-color-text-regular);
}

:deep(.el-input__inner),
:deep(.el-textarea__inner),
:deep(.el-radio-button__inner) {
  color: var(--prev-color-text-primary);
  background-color: var(--prev-bg-white);
  border-color: var(--prev-border-color-base);
}

:deep(.el-input__inner::placeholder),
:deep(.el-textarea__inner::placeholder) {
  color: var(--prev-color-text-placeholder);
}

:deep(.el-radio),
:deep(.el-radio__label) {
  color: var(--prev-color-text-regular);
}

:deep(.el-input-group__append),
:deep(.el-input-group__prepend) {
  color: var(--prev-color-text-regular);
  background-color: var(--prev-bg-color);
  border-color: var(--prev-border-color-base);
}

:deep(.el-descriptions__body),
:deep(.el-descriptions .is-bordered .el-descriptions-item__cell) {
  color: var(--prev-color-text-primary);
  background: var(--prev-bg-white);
  border-color: var(--prev-border-color-base);
}

:deep(.el-descriptions-item__label) {
  color: var(--prev-color-text-regular);
  background: var(--prev-bg-color);
}

:deep(.el-input-number) {
  .el-input__inner {
    text-align: left;
  }
}
</style>
