<template>
  <div class="product-list-create-page">
    <!-- 顶部 -->
    <div class="sticky">
      <el-card shadow="never" class="create-header">
        <div class="create-header__inner">
          <div class="create-header__left">
            <span class="back-close" @click="goBack">
              <LegacyIcon name="el-icon-close" />
            </span>
            <span class="create-title">{{ isEdit ? '编辑商品' : '添加商品' }}</span>
          </div>
          <div class="create-header__right">
            <el-button class="btn-curator-reset" @click="goBack">取消</el-button>
            <el-button v-if="isEdit && isLibraryMode" :loading="skuSaving" @click="submitSku">保存SKU</el-button>
            <el-button type="primary" class="btn-curator-primary" :loading="submitLoading" @click="submitForm">确 定</el-button>
          </div>
        </div>
      </el-card>

      <!-- 步骤条 -->
      <div class="steps-bar">
        <div
          v-for="step in visibleSteps"
          :key="step.index"
          class="steps-bar__item"
          :class="{ 'is-active': activeStep === step.index, 'is-done': step.index < activeStep }"
          @click="activeStep = step.index"
        >
          <span class="steps-bar__name">{{ step.label }}</span>
          <span class="steps-bar__arrow" />
        </div>
      </div>
    </div>

    <el-card shadow="never" class="create-body">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" class="create-form">
        <!-- ============ 基本信息 ============ -->
        <div v-show="activeStep === 0" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />基本信息
          </div>

          <!-- 商品库：置顶；输入框展示已选库商品（可多选），按钮打开选品 -->
          <el-form-item label="商品库">
            <div class="pick-block library-pick-block">
              <div class="library-pick-bar">
                <el-input-tag
                  :model-value="libraryTagValues"
                  clearable
                  placeholder="暂无选择（可直接自定义规格）"
                  class="library-pick-input"
                  @add-tag="handleLibraryTagAdd"
                  @remove-tag="handleLibraryTagRemove"
                  @clear="clearLibraryProducts"
                />
                <div class="library-pick-actions">
                  <el-button type="primary" class="btn-curator-primary library-pick-btn" @click="openPickDialog">选择库商品</el-button>
                </div>
              </div>
              <div class="form-tip">
                可选商品库模式：选 1 个库商品 = 单规格店铺商品；多选自动组合成多规格商品；也可以不选，直接自定义规格
              </div>
            </div>
          </el-form-item>

          <el-form-item label="商品类型">
            <div class="type-cards">
              <div
                v-for="type in typeOptions"
                :key="type.value"
                class="type-card"
                :class="{ 'is-selected': Number(form.goods_type) === type.value }"
                @click="form.goods_type = type.value"
              >
                <span class="type-card__title">
                  {{ type.title }}
                  <el-tooltip v-if="type.tip" :content="type.tip" placement="top">
                    <LegacyIcon name="el-icon-question" class="type-card__q" />
                  </el-tooltip>
                </span>
                <span class="type-card__desc">{{ type.desc }}</span>
                <span v-if="Number(form.goods_type) === type.value" class="type-card__check">
                  <LegacyIcon name="el-icon-check" />
                </span>
              </div>
            </div>
            <div class="form-tip">不同商品类型可编辑的字段内容不同，商品类型一旦上架后将不可更改</div>
          </el-form-item>

          <el-form-item label="商品名称" prop="name">
            <el-input v-model="form.name" placeholder="请填写商品名称" maxlength="100" show-word-limit />
          </el-form-item>

          <el-form-item label="商品图片">
            <div>
              <Upload v-model="form.pics" :limit="9" />
              <div class="form-tip">您可以拖拽调整图片顺序，第一张图将作为商品主图展示；建议尺寸 750×750，支持 jpg/png/gif/webp</div>
            </div>
          </el-form-item>

          <el-form-item label="商品视频">
            <div>
              <div class="video-upload-wrap">
                <BigFileUpload
                  v-model="form.video"
                  :allow-extensions="['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv']"
                  accept="video/*,.mp4,.avi,.mov,.wmv,.flv,.mkv"
                  tip="支持 mp4/avi/mov/wmv/flv/mkv 格式"
                />
              </div>
              <div v-if="form.video" class="video-preview" @click="previewVideo">
                <LegacyIcon name="el-icon-video-play" />
                <span>点击预览视频</span>
              </div>
              <div class="form-tip">如有商品视频则商品视频将作为商品主图展示</div>
            </div>
          </el-form-item>

          <el-form-item label="商品品牌" prop="brand_id">
            <el-select v-model="form.brand_id" placeholder="选择品牌" clearable class="form-width">
              <el-option v-for="item in brandOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>

          <el-form-item label="平台分类" prop="category_id">
            <div class="category-field">
              <el-cascader
                v-model="platformCategoryPath"
                :options="platformCategoryOptions"
                :props="{ checkStrictly: false, emitPath: true, value: 'value', label: 'label', children: 'children' }"
                placeholder="请选择平台分类"
                clearable
                filterable
                class="category-select"
                @change="applyPlatformCategoryPath"
              />
            </div>
            <div class="form-tip">移动端分类页仅按平台分类展示，商品必须选择平台分类</div>
          </el-form-item>

          <el-form-item label="商品参数">
            <div class="product-param-panel">
              <template v-if="paramTemplateLoading">
                <el-skeleton :rows="2" animated />
              </template>
              <template v-else-if="paramRows.length">
                <div class="product-param-panel__meta">
                  <span class="product-param-panel__title">{{ (paramTemplate && paramTemplate.name) || '当前分类参数模板' }}</span>
                  <span class="product-param-panel__count">{{ paramRows.length }} 项</span>
                </div>
                <div class="product-param-panel__list">
                  <div v-for="row in paramRows" :key="row.row_key" class="product-param-row">
                    <div class="product-param-row__label">
                      <span>{{ row.name }}</span>
                      <el-tag v-if="Number(row.is_required) === 1" size="small" type="danger" effect="plain">必填</el-tag>
                    </div>
                    <el-select
                      v-model="row.selected_values"
                      multiple
                      filterable
                      allow-create
                      collapse-tags
                      collapse-tags-tooltip
                      clearable
                      class="product-param-row__select"
                      placeholder="请选择或输入参数值"
                    >
                      <el-option
                        v-for="value in row.values"
                        :key="value"
                        :label="value"
                        :value="value"
                      />
                    </el-select>
                  </div>
                </div>
              </template>
              <div v-else class="form-tip">请选择平台分类后自动加载参数模板</div>
            </div>
          </el-form-item>

          <el-form-item label="商户分类">
            <div class="category-field">
              <el-cascader
                v-model="merchantCategoryPaths"
                :options="merchantCategoryOptions"
                :props="{ multiple: true, checkStrictly: false, emitPath: true, value: 'value', label: 'label', children: 'children' }"
                placeholder="可选择商户自定义分类"
                clearable
                filterable
                class="category-select"
                @change="applyMerchantCategoryPaths"
              />
              <span class="category-field__quick">
                <a @click="openCategoryNew">新建</a>
                <el-divider direction="vertical" />
                <a @click="refreshCategory">刷新</a>
              </span>
            </div>
            <div class="form-tip">仅用于商户后台内部区分和筛选，不影响移动端平台分类展示</div>
          </el-form-item>

          <el-form-item label="上下架设置">
            <el-radio-group v-model="form.status">
              <el-radio :label="1">上架</el-radio>
              <el-radio :label="0">下架</el-radio>
            </el-radio-group>
          </el-form-item>

        </div>

        <!-- ============ 规格库存 ============ -->
        <div v-show="activeStep === 1" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />规格库存
          </div>

          <el-form-item label="规格类型">
            <div class="spec-stock-type">
              <template v-if="isLibraryMode">
                <el-tag :type="Number(currentSpecType) === 1 ? 'warning' : 'info'">
                  {{ Number(currentSpecType) === 1 ? '多规格' : '单规格' }}
                </el-tag>
                <span class="spec-stock-type__hint">由已选库商品数量自动判断，不能手动修改</span>
              </template>
              <el-radio-group
                v-else
                v-model="customSpecType"
                @change="handleSpecTypeChange"
              >
                <el-radio :label="0">单规格</el-radio>
                <el-radio :label="1">多规格</el-radio>
              </el-radio-group>
              <el-select
                v-if="!isLibraryMode && Number(customSpecType) === 1"
                v-model="selectedSpecTemplateId"
                clearable
                filterable
                placeholder="选择规格模板"
                class="spec-template-select"
                :loading="specTemplateLoading"
                @change="handleSpecTemplateChange"
              >
                <el-option
                  v-for="template in specTemplates"
                  :key="template.id"
                  :label="template.name"
                  :value="template.id"
                />
              </el-select>
              <el-button
                v-if="!isLibraryMode && Number(customSpecType) === 1"
                text
                type="primary"
                class="spec-template-link"
                @click="addCustomSpec"
              >
                添加规格
              </el-button>
              <span v-if="specSummaryText" class="spec-summary spec-summary--inline">{{ specSummaryText }}</span>
            </div>
          </el-form-item>

          <el-form-item
            v-if="(isLibraryMode && librarySelected.length > 1) || (!isLibraryMode && Number(customSpecType) === 1)"
            label="商品规格"
          >
            <div class="spec-stock-card-list">
              <template v-if="isLibraryMode && specStockCards.length">
                <div
                  v-for="(card, index) in specStockCards"
                  :key="card.key"
                  class="spec-stock-card"
                  :class="{ 'is-active': index === 0 }"
                >
                  <div class="spec-stock-card__name">{{ card.title }}</div>
                  <div class="spec-stock-card__values">
                    <el-tag
                      v-for="item in card.items"
                      :key="item.libraryId"

                      class="spec-stock-card__tag"
                    >
                      {{ item.name }}
                    </el-tag>
                  </div>
                  <span class="spec-stock-card__count">{{ card.items.length }}/30</span>
                  <span class="spec-stock-card__close" @click="removeSpecCardByDisplay(card)">
                    <LegacyIcon name="el-icon-close" />
                  </span>
                </div>
              </template>
              <template v-else-if="!isLibraryMode && Number(customSpecType) === 1">
                <div
                  v-for="(card, cardIndex) in customSpecs"
                  :key="card.rowKey"
                  class="spec-stock-card spec-stock-card--custom"
                >
                  <div class="spec-stock-card__name-wrap">
                    <el-input
                      v-model="card.name"
                      placeholder="规格名称"
                      maxlength="30"
                      show-word-limit
                      class="spec-stock-card__name-input"
                      @input="syncCustomSkuRows"
                    />
                  </div>
                  <span class="spec-stock-card__close" @click="removeCustomSpec(cardIndex)">
                    <LegacyIcon name="el-icon-close" />
                  </span>
                  <draggable
                    v-model="card.values"
                    item-key="rowKey"
                    handle=".custom-spec-value__drag"
                    class="spec-stock-card__values spec-stock-card__values--edit"
                    @end="syncCustomSkuRows"
                  >
                    <template #item="{ element: item }">
                      <div class="custom-spec-value">
                        <span class="custom-spec-value__drag">
                          <img :src="dragIcon" alt="" class="drag-icon-img">
                        </span>
                        <div class="custom-spec-value__field">
                          <el-input
                            v-model="item.value"
                            placeholder="请输入规格值"
                            maxlength="30"
                            show-word-limit
                            class="custom-spec-value__input"
                            @blur="handleCustomSpecValueBlur(card, item)"
                          />
                          <span class="custom-spec-value__del" @click="removeCustomSpecValue(card, item)">
                            <LegacyIcon name="el-icon-close" />
                          </span>
                        </div>
                      </div>
                    </template>
                  </draggable>
                </div>
              </template>
              <template v-else-if="!isLibraryMode">
                <div class="single-spec-hint">单规格无需配置规格值，直接在下方 SKU 表填写规格名称、价格和库存</div>
              </template>
              <el-empty v-else description="请先选择商品库商品，或切换到自定义规格" :image-size="56" />
              <div class="spec-stock-card-actions">
                <el-button v-if="librarySelected.length" text type="primary" @click="handleOpenSpecStockEditor">另存为模板</el-button>
              </div>
            </div>
          </el-form-item>

          <el-form-item label="商品属性">
            <div class="sku-block">
              <div v-if="!isEdit" class="sku-block__tip">
                {{ isLibraryMode ? 'SKU 由系统按库商品自动生成，价格、库存、重量、排序等信息可直接调整' : '新增商品时 SKU 由系统根据自定义规格自动生成；保存后会同步创建对应商品库单品' }}
              </div>
              <el-table
                :data="skuRowsForTable"
                :row-key="skuTableRowKey"
                :span-method="skuTableSpanMethod"
                border
                class="sku-table sku-table--matrix"
                max-height="480"
                table-layout="fixed"
              >
                <el-table-column
                  v-for="column in skuSpecColumns"
                  :key="column.key"
                  :prop="column.key"
                  :label="column.label"
                  :width="column.width"
                  align="center"
                  class-name="sku-table__spec-column"
                >
                  <template #default="scope">
                    <span v-if="scope.row.__isBatch" class="sku-table__batch-label">
                      {{ column.key === skuSpecColumns[0]?.key ? '批量设置' : '--' }}
                    </span>
                    <span v-else class="sku-table__spec-value">{{ getSkuSpecValue(scope.row, column) || '-' }}</span>
                  </template>
                </el-table-column>
                <el-table-column v-if="!skuSpecColumns.length" label="规格/文本" min-width="220">
                  <template #default="scope">
                    <span v-if="scope.row.__isBatch" class="sku-table__batch-label">批量设置</span>
                    <el-input v-else v-model="scope.row.sku" class="sku-table__input" />
                  </template>
                </el-table-column>
                <el-table-column label="图片" width="80" align="center">
                  <template #default="scope">
                    <div class="sku-image-picker">
                      <el-image
                        v-if="scope.row.pics"
                        :src="scope.row.pics"
                        :preview-src-list="[scope.row.pics]"
                        preview-teleported
                        fit="cover"
                        class="sku-image-picker__preview"
                      />
                      <el-button
                        v-else
                        class="sku-image-picker__button"
                        @click="handleSkuImageSelect(scope.row)"
                      >
                        <LegacyIcon name="el-icon-picture-outline" />
                      </el-button>
                      <button
                        v-if="scope.row.pics"
                        type="button"
                        class="sku-image-picker__remove"
                        title="移除图片"
                        @click.stop="scope.row.pics = ''"
                      >
                        <LegacyIcon name="el-icon-close" />
                      </button>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="价格（元）" width="110" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.price" :min="0" :precision="2" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="划线价" width="110" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.ot_price" :min="0" :precision="2" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="成本价" width="110" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.store_price" :min="0" :precision="2" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="库存" width="100" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.stock" :min="0" :precision="0" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="重量(g)" width="100" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.weight" :min="0" :precision="0" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="排序" width="90" align="center">
                  <template #default="scope">
                    <el-input-number v-model="scope.row.sort" :min="0" :precision="0" :controls="false" class="sku-table__number" />
                  </template>
                </el-table-column>
                <el-table-column label="默认" width="80" align="center">
                  <template #default="scope">
                    <span v-if="scope.row.__isBatch" class="sku-table__batch-placeholder">--</span>
                    <el-switch
                      v-else
                      v-model="scope.row.is_default"
                      :active-value="1"
                      :inactive-value="0"
                      @change="handleDefaultChange(scope.row)"
                    />
                  </template>
                </el-table-column>
                <el-table-column label="状态" width="80" align="center">
                  <template #default="scope">
                    <span v-if="scope.row.__isBatch" class="sku-table__batch-placeholder">--</span>
                    <el-switch
                      v-else
                      v-model="scope.row.status"
                      :active-value="1"
                      :inactive-value="0"
                      @change="handleSkuStatusChange(scope.row)"
                    />
                  </template>
                </el-table-column>
                <el-table-column v-if="hasBatchSkuRow || (isEdit && isLibraryMode)" label="操作" width="140" align="center">
                  <template #default="scope">
                    <template v-if="scope.row.__isBatch">
                      <a @click="applyBatchSku">批量修改</a>
                      <el-divider direction="vertical" />
                      <a @click="clearBatchSku">清空</a>
                    </template>
                    <template v-else-if="isEdit && isLibraryMode">
                      <el-button type="danger" text @click="handleDeleteSku(scope.row)">删除</el-button>
                    </template>
                  </template>
                </el-table-column>
              </el-table>
            </div>
          </el-form-item>

        </div>

        <!-- ============ 物流及配送 ============ -->
        <div v-show="activeStep === 2 && hasShippingStep" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />物流及配送
          </div>
          <el-form-item label="是否包邮">
            <el-radio-group v-model="form.is_free_shipping">
              <el-radio :label="1">包邮</el-radio>
              <el-radio :label="0">按模板</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="运费模板">
            <el-select v-model="form.freight_template_id" placeholder="请选择" :disabled="Number(form.is_free_shipping) === 1" clearable class="form-width">
              <el-option v-for="item in freightOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>

        </div>

        <!-- ============ 商品详情 ============ -->
        <div v-show="activeStep === 3" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />商品详情
          </div>
          <el-form-item label="商品简介">
            <el-input v-model="form.description" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="请输入商品简介" />
          </el-form-item>
          <el-form-item label="商品详情">
            <div class="editor-wrap">
              <Toolbar :editor="editor" :default-config="toolbarConfig" :mode="editorMode" class="editor-toolbar" />
              <Editor v-model="form.detail" :default-config="editorConfig" :mode="editorMode" style="height: 360px; overflow-y: hidden;" @onCreated="onEditorCreated" />
            </div>
          </el-form-item>
        </div>

        <!-- ============ 高级信息 ============ -->
        <div v-show="activeStep === 4" class="step-panel">
          <div class="step-panel__title">
            <span class="step-panel__bar" />高级信息
          </div>
          <el-form-item label="关键词">
            <el-input v-model="form.keyword" placeholder="关键词，多个用逗号分隔" maxlength="100" class="form-width" />
            <div class="form-tip">用于后台搜索和前台检索，建议填写商品核心词</div>
          </el-form-item>
          <el-form-item label="商品分组">
            <el-select v-model="form.group_id" placeholder="请选择分组" clearable class="form-width">
              <el-option v-for="item in groupOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
            <div class="form-tip">用于后台按业务分组管理和筛选展示</div>
          </el-form-item>
          <el-form-item label="服务保障">
            <el-select v-model="form.service_ids" multiple placeholder="请选择服务" clearable class="form-width">
              <el-option v-for="item in serviceOptions" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
            <div class="form-tip">用于商品详情页展示售后、发货、保障等服务标识</div>
          </el-form-item>
          <el-form-item label="赠送积分">
            <el-input-number v-model="form.gift_points" :min="0" :precision="0" class="advanced-number" />
            <div class="form-tip">用户下单并完成后发放的积分，常用于会员激励</div>
          </el-form-item>
          <el-form-item label="排序">
            <el-input-number v-model="form.sort" :min="0" :max="9999" class="advanced-number" />
            <div class="form-tip">数值越大越靠前，影响后台和前台列表排序</div>
          </el-form-item>
        </div>
      </el-form>
    </el-card>

    <!-- 选择库商品弹窗 -->
    <el-dialog
      v-model="pickDialogVisible"
      title="选择库商品"
      width="860px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <div class="pick-dialog">
        <el-form :model="pickSearch" inline class="pick-search" @submit.prevent>
          <el-form-item label="名称">
            <el-input v-model="pickSearch.name" clearable placeholder="请输入库商品名称" class="pick-search__input" @keyup.enter="loadPickList" />
          </el-form-item>
          <el-form-item label="分类">
            <el-cascader
              v-model="pickCategoryPath"
              :options="platformCategoryOptions"
              :props="{ checkStrictly: true, emitPath: true, value: 'value', label: 'label', children: 'children' }"
              placeholder="全部"
              clearable
              filterable
              class="pick-search__cascader"
              @change="loadPickList"
            />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="loadPickList">查询</el-button>
          </el-form-item>
        </el-form>
        <el-table
          v-loading="pickLoading"
          :data="pickList"
          row-key="id"
          class="pick-table"
          @selection-change="handlePickSelection"
        >
          <el-table-column type="selection" width="46" :selectable="row => !isAlreadySelected(row.id)" />
          <el-table-column label="封面" width="56" align="center">
            <template #default="scope">
              <el-image v-if="scope.row.cover" :src="scope.row.cover" fit="cover" class="pick-cover" />
              <span v-else class="empty-text">-</span>
            </template>
          </el-table-column>
          <el-table-column prop="name" label="名称" min-width="150" show-overflow-tooltip />
          <el-table-column prop="product_code" label="库编码" width="170" show-overflow-tooltip />
          <el-table-column label="规格值" width="110" align="center">
            <template #default="scope">
              <el-tag v-if="scope.row.spec_value_id" type="warning">{{ specValueName(scope.row.spec_value_id) }}</el-tag>
              <span v-else class="empty-text">自由SKU</span>
            </template>
          </el-table-column>
          <el-table-column prop="price" label="售价" width="80" align="center" />
          <el-table-column prop="stock" label="库存" width="80" align="center" />
          <el-table-column prop="unit" label="单位" width="60" align="center" />
        </el-table>
        <div class="pick-dialog__footer">
          <el-pagination
            v-if="pickTotal > 0"
            v-model:current-page="pickSearch.page"
            v-model:page-size="pickSearch.limit"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next"
            :total="pickTotal"
            @size-change="loadPickList"
            @current-change="loadPickList"
          />
          <span class="pick-dialog__count">已选 {{ pickTempSelected.length }} 项</span>
        </div>
      </div>
      <template #footer>
        <span class="cfg-footer">
          <el-button @click="pickDialogVisible = false">取 消</el-button>
          <el-button type="primary" @click="confirmPick">确认添加</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 商品规格编辑弹窗（规格模板：规格卡片 + 值 chips 拖拽排序） -->
    <el-dialog
      v-model="specEditorVisible"
      title="商品规格"
      width="720px"
      :close-on-click-modal="false"
      destroy-on-close
    >
      <div class="spec-editor">
        <el-form label-width="110px" class="spec-editor__form" @submit.prevent>
          <el-form-item label="规格模板名称" required>
            <el-input v-model="form.name" placeholder="请输入标题名称" maxlength="30" show-word-limit />
          </el-form-item>
        </el-form>
        <div
          v-for="card in specEditorCards"
          :key="card.isFree ? 'free' : `spec-${card.specId}`"
          class="spec-card"
          :class="{ 'is-free': card.isFree }"
        >
          <div class="spec-card__head">
            <span class="spec-card__title">{{ card.title }}</span>
            <span class="spec-card__count">{{ card.items.length }}/30</span>
            <span class="spec-card__action" @click="removeSpecCard(card)">
              <LegacyIcon name="el-icon-close" />
            </span>
          </div>
          <draggable
            v-model="card.items"
            item-key="rowKey"
            handle=".drag-handle"
            class="value-row-list"
          >
            <template #item="{ element }">
              <div class="value-row">
                <span class="drag-handle">
                  <LegacyIcon name="el-icon-rank" />
                </span>
                <span class="value-row__chip">{{ element.name }}</span>
                <span class="value-row__del" @click="removeSpecItem(card, element)">
                  <LegacyIcon name="el-icon-close" />
                </span>
              </div>
            </template>
          </draggable>
          <el-button text type="primary" class="value-row__add" @click="openPickDialog">
            添加规格值
          </el-button>
        </div>
        <el-button class="spec-editor__add" @click="openPickDialog">
          添加新规格
        </el-button>
        <div class="spec-editor__tip">拖拽排序 = SKU 顺序；规格值增删即库商品增删（重新选择）；规格名称来自规格管理，不可在商品编辑</div>
      </div>
      <template #footer>
        <span class="cfg-footer">
          <el-button @click="specEditorVisible = false">取 消</el-button>
          <el-button type="primary" @click="confirmSpecEditor">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 新建分类弹窗 -->
    <el-dialog
      v-model="categoryNewVisible"
      title="新建商户分类"
      width="440px"
      append-to-body
    >
      <el-form label-width="90px" @submit.prevent>
        <el-form-item label="上级分类">
          <el-cascader
            v-model="categoryNewPath"
            :options="categoryParentOptions"
            :props="categoryParentProps"
            placeholder="请选择上级分类（可不选）"
            clearable
            class="form-width"
            @change="handleCategoryParentChange"
          />
        </el-form-item>
        <el-form-item label="分类名称" required>
          <el-input v-model="categoryNewName" placeholder="请输入分类名称" maxlength="30" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="cfg-footer">
          <el-button @click="categoryNewVisible = false">取 消</el-button>
          <el-button type="primary" :loading="categoryNewSaving" @click="submitCategoryNew">确 定</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import '@wangeditor/editor/dist/css/style.css'
import draggable from 'vuedraggable'
import Upload from '@/components/Upload/index'
import BigFileUpload from '@/components/BigFileUpload/index'
import dragIcon from '@/assets/images/drag-icon.png'
import {
  getStoreProductRead,
  createStoreProduct,
  updateStoreProduct,
  getStoreProductSkuList,
  saveStoreProductSku,
  deleteStoreProductSku,
  setStoreProductSkuStatus,
  getProductLibraryList,
  getPlatformProductCategoryTree,
  getProductCategoryTree,
  createProductCategory,
  getBrandOptions,
  getGroupOptions,
  getSpecList,
  getSpecOptions,
  getServiceOptions,
  getFreightOptions,
  getAttributeTemplateByCategory
} from '@/api/product'

defineOptions({
  name: 'MerchantProductCreate'
})

const MAX_SPEC_COUNT = 200
const steps = ['基本信息', '规格库存', '物流及配送', '商品详情', '高级信息']

const typeOptions = [
  { value: 1, title: '普通商品', desc: '物流配送', tip: '实物商品，参与运费计算' },
  { value: 2, title: '虚拟商品', desc: '无需物流', tip: '虚拟商品不参与实物运费' }
]

const route = useRoute()
const router = useRouter()
const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const imgModal = proxy.$imgModal

const isEdit = computed(() => !!route.query.id)
const productId = computed(() => Number(route.query.id) || 0)
const activeStep = ref(0)

const formRef = ref(null)
const editor = ref(null)
const submitLoading = ref(false)
const skuSaving = ref(false)
const editorMode = 'default'

// 选品弹窗
const pickDialogVisible = ref(false)
const pickLoading = ref(false)
const pickList = ref([])
const pickTotal = ref(0)
const pickTempSelected = ref([])
const pickCategoryPath = ref([])
const pickSearch = reactive({ name: '', page: 1, limit: 10 })

// 新建分类弹窗
const categoryNewVisible = ref(false)
const categoryNewName = ref('')
const categoryNewPid = ref(0)
const categoryNewPath = ref([])
const categoryNewSaving = ref(false)

// 选项数据
const platformCategoryOptions = ref([])
const merchantCategoryOptions = ref([])
const brandOptions = ref([])
const groupOptions = ref([])
const serviceOptions = ref([])
const freightOptions = ref([])
const specValueMap = ref({})
const specTemplates = ref([])
const specTemplateLoading = ref(false)
const selectedSpecTemplateId = ref('')
const paramTemplate = ref(null)
const paramTemplateLoading = ref(false)
const paramRows = ref([])

// 已选库商品（行内可改价格库存作为继承默认值）
const librarySelected = ref([])
const nameTouched = ref(false)
const initialLibraryIds = ref([])
const customSpecType = ref(0)
const customSpecs = ref([])
const customSkuRows = ref([])
const removedLibraryProductIds = ref([])
let customRowKeySeed = 1

// 商品规格编辑弹窗
const specEditorVisible = ref(false)
const specEditorCards = ref([])
let specRowKeySeed = 1

const platformCategoryPath = ref([])
const merchantCategoryPaths = ref([])

const form = reactive(getDefaultForm())
const skuTable = ref([])
const batchSku = reactive(getDefaultBatchSku())

// 虚拟商品（goods_type=2）不参与物流及配送：步骤条隐藏该步，面板不展示
const hasShippingStep = computed(() => Number(form.goods_type) !== 2)
const visibleSteps = computed(() => steps
  .map((label, index) => ({ label, index }))
  .filter(step => step.index !== 2 || hasShippingStep.value))

watch(() => form.goods_type, val => {
  if (Number(val) === 2 && activeStep.value === 2) {
    activeStep.value = 3
  }
})

const isLibraryMode = computed(() => librarySelected.value.length > 0)
const currentSpecType = computed(() => (isLibraryMode.value ? (librarySelected.value.length > 1 ? 1 : 0) : customSpecType.value))

// 已选库商品名称（多选合并展示在商品库输入框）
const libraryNamesText = computed(() => {
  const names = librarySelected.value.map(item => item.name)
  return names.length ? names.join('、') : ''
})
const libraryTagValues = computed(() => librarySelected.value
  .map(item => item.name))
const skuRowsForDisplay = computed(() => (isLibraryMode.value ? skuTable.value : customSkuRows.value))
const hasBatchSkuRow = computed(() => Number(currentSpecType.value) === 1)
const skuRowsForTable = computed(() => (hasBatchSkuRow.value ? [batchSku, ...skuRowsForDisplay.value] : skuRowsForDisplay.value))
const skuWeightTotal = computed(() => skuRowsForDisplay.value.reduce((sum, row) => sum + (Number(row.weight) || 0), 0))
const skuSpecColumns = computed(() => {
  if (isLibraryMode.value && Number(currentSpecType.value) === 1) {
    return specStockCards.value.map(card => ({
      key: card.key,
      label: card.title,
      width: 90
    }))
  }
  if (!isLibraryMode.value && Number(customSpecType.value) === 1) {
    return customSpecs.value
      .filter(card => card.name.trim())
      .map(card => ({
        key: `custom-${card.rowKey}`,
        label: card.name,
        width: 90,
        rowKey: card.rowKey
      }))
  }
  return []
})

const rules = {
  name: [
    { required: true, message: '请输入商品名称', trigger: 'blur' },
    { max: 100, message: '商品名称最多100个字符', trigger: 'blur' }
  ],
  category_id: [
    {
      validator: (rule, value, callback) => {
        if (!Number(form.category_id)) {
          callback(new Error('请选择平台分类'))
          return
        }
        callback()
      },
      trigger: 'change'
    }
  ],
  brand_id: [
    {
      validator: (rule, value, callback) => {
        if (!Number(form.brand_id)) {
          callback(new Error('请选择商品品牌'))
          return
        }
        callback()
      },
      trigger: 'change'
    }
  ]
}

const toolbarConfig = {
  excludeKeys: ['uploadVideo', 'insertVideo', 'group-video']
}

const editorConfig = computed(() => {
  const config = { placeholder: '请输入商品详情内容...' }
  config.MENU_CONF = {
    uploadImage: {
      customBrowseAndUpload: (insertFn) => {
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
    }
  }
  return config
})

function getDefaultForm() {
  return {
    name: '',
    keyword: '',
    category_id: 0,
    category_ids: [],
    brand_id: '',
    group_id: '',
    goods_type: 1,
    cover: '',
    pics: [],
    video: '',
    description: '',
    detail: '',
    is_free_shipping: 0,
    service_ids: [],
    freight_template_id: '',
    gift_points: 0,
    first_commission_rate: 0,
    second_commission_rate: 0,
    status: 1,
    sort: 0,
    weight: 0
  }
}

function getDefaultBatchSku() {
  return {
    __isBatch: true,
    rowKey: '__batch__',
    pics: '',
    price: null,
    ot_price: null,
    store_price: null,
    stock: null,
    weight: null,
    sort: null
  }
}

function skuTableRowKey(row) {
  if (row.__isBatch) return row.rowKey
  return isLibraryMode.value ? row.library_product_id : row.rowKey
}

function getDefaultCustomSpecCard(extra = {}) {
  return {
    rowKey: customRowKeySeed++,
    name: '',
    values: [getDefaultCustomSpecValue()],
    ...extra
  }
}

function getDefaultCustomSpecValue(extra = {}) {
  return {
    rowKey: customRowKeySeed++,
    value: '',
    ...extra
  }
}

function getDefaultCustomSkuRow(extra = {}) {
  return {
    rowKey: customRowKeySeed++,
    key: '',
    sku: '',
    price: 0,
    ot_price: 0,
    store_price: 0,
    stock: 0,
    weight: 0,
    sort: 100,
    is_default: 0,
    status: 1,
    pics: '',
    library_product_id: 0,
    ...extra
  }
}

function getDefaultLibrarySkuRow(item, index, existing = null) {
  return {
    id: existing?.id || 0,
    library_product_id: Number(item.id) || 0,
    uniqid: existing?.uniqid || item.product_code || '',
    spec_value_ids: existing?.spec_value_ids ?? (item.spec_value_id ? String(item.spec_value_id) : ''),
    sku: existing?.sku ?? item.name ?? '',
    price: existing?.price ?? Number(item.price || 0),
    ot_price: existing?.ot_price ?? Number(item.ot_price || 0),
    store_price: existing?.store_price ?? Number(item.store_price || 0),
    stock: existing?.stock ?? Number(item.stock || 0),
    stock_version: existing?.stock_version,
    weight: existing?.weight ?? Number(item.weight || 0),
    sort: existing?.sort ?? (100 - index),
    is_default: existing?.is_default ?? (index === 0 ? 1 : 0),
    status: existing?.status ?? 1,
    pics: existing?.pics ?? item.cover ?? ''
  }
}

function syncLibrarySkuRows() {
  const existingMap = new Map()
  skuTable.value.forEach(row => {
    if (row.library_product_id) {
      existingMap.set(`id:${row.library_product_id}`, row)
    }
    if (row.uniqid) {
      existingMap.set(`uniqid:${row.uniqid}`, row)
    }
  })

  const rows = librarySelected.value.map((item, index) => {
    const existing = existingMap.get(`id:${item.id}`) || existingMap.get(`uniqid:${item.product_code}`)
    return getDefaultLibrarySkuRow(item, index, existing)
  })

  if (rows.length && !rows.some(row => Number(row.is_default) === 1)) {
    rows[0].is_default = 1
  }
  skuTable.value = rows
}

function ensureCustomSingleSpec() {
  if (!customSpecs.value.length) {
    customSpecs.value.push(getDefaultCustomSpecCard())
  }
}

function markRemovedCustomLibraryIds(oldRows, nextRows) {
  const nextKeys = new Set(nextRows.map(item => item.key))
  oldRows.forEach(item => {
    if (!nextKeys.has(item.key) && item.library_product_id) {
      removedLibraryProductIds.value.push(item.library_product_id)
    }
  })
}

function syncCustomSkuRows() {
  if (isLibraryMode.value) return

  const oldRows = customSkuRows.value.map(item => ({ ...item }))
  if (Number(customSpecType.value) === 0) {
    const skuName = customSkuRows.value[0]?.sku || '默认'
    const nextRows = [getDefaultCustomSkuRow({
      key: 'single',
      sku: skuName,
      price: customSkuRows.value[0]?.price ?? 0,
      ot_price: customSkuRows.value[0]?.ot_price ?? 0,
      store_price: customSkuRows.value[0]?.store_price ?? 0,
      stock: customSkuRows.value[0]?.stock ?? 0,
      stock_version: customSkuRows.value[0]?.stock_version,
      uniqid: customSkuRows.value[0]?.uniqid || '',
      weight: customSkuRows.value[0]?.weight ?? 0,
      sort: customSkuRows.value[0]?.sort ?? 100,
      library_product_id: customSkuRows.value[0]?.library_product_id || 0,
      is_default: 1,
      status: 1
    })]
    markRemovedCustomLibraryIds(oldRows, nextRows)
    customSkuRows.value = nextRows
    return
  }

  const dims = customSpecs.value
    .map(spec => ({
      name: spec.name.trim(),
      values: spec.values.filter(item => item.value.trim()).map(item => ({ ...item, value: item.value.trim() }))
    }))
    .filter(spec => spec.name && spec.values.length)

  if (!dims.length) {
    customSkuRows.value = []
    return
  }

  const combos = []
  const walk = (index, labels, keyParts) => {
    if (index >= dims.length) {
      combos.push({
        key: keyParts.join('||'),
        sku: labels.join(' / ')
      })
      return
    }
    dims[index].values.forEach(item => {
      walk(index + 1, [...labels, item.value], [...keyParts, item.rowKey])
    })
  }
  walk(0, [], [])

  const existingMap = new Map(customSkuRows.value.map(item => [item.key, item]))
  const nextRows = combos.map((combo, index) => {
    const existed = existingMap.get(combo.key)
    return {
      ...getDefaultCustomSkuRow(),
      ...(existed || {}),
      key: combo.key,
      sku: existed?.sku || combo.sku,
      price: existed?.price ?? 0,
      ot_price: existed?.ot_price ?? 0,
      store_price: existed?.store_price ?? 0,
      stock: existed?.stock ?? 0,
      weight: existed?.weight ?? 0,
      sort: existed?.sort ?? 100,
      is_default: index === 0 ? 1 : (existed?.is_default ?? 0),
      status: existed?.status ?? 1
    }
  })
  markRemovedCustomLibraryIds(oldRows, nextRows)
  customSkuRows.value = nextRows
}

function setCustomSingleSkuName() {
  if (Number(customSpecType.value) !== 0) return
  if (!customSkuRows.value.length) {
    customSkuRows.value = [getDefaultCustomSkuRow({ key: 'single', sku: '默认', is_default: 1 })]
  }
}

function handleStartCustomMode() {
  customSpecType.value = 0
  customSpecs.value = [getDefaultCustomSpecCard()]
  customSkuRows.value = []
  removedLibraryProductIds.value = []
  setCustomSingleSkuName()
  syncCustomSkuRows()
}

function handleSpecTypeChange(value) {
  if (isLibraryMode.value) return
  customSpecType.value = Number(value) || 0
  if (customSpecType.value === 0) {
    selectedSpecTemplateId.value = ''
    setCustomSingleSkuName()
  } else {
    ensureCustomSingleSpec()
  }
  syncCustomSkuRows()
}

function loadSpecTemplates() {
  specTemplateLoading.value = true
  getSpecList({ status: 1, page: 1, limit: 200 }).then(res => {
    const data = res.data || {}
    specTemplates.value = Array.isArray(data) ? data : (data.list || [])
  }).catch(() => {
    specTemplates.value = []
  }).finally(() => {
    specTemplateLoading.value = false
  })
}

function handleSpecTemplateChange(templateId) {
  if (!templateId) return
  const template = specTemplates.value.find(item => Number(item.id) === Number(templateId))
  if (!template || !Array.isArray(template.specs)) return
  const nextSpecs = template.specs
    .map(spec => ({
      rowKey: customRowKeySeed++,
      name: String(spec.name || '').trim(),
      values: (spec.values || [])
        .filter(item => Number(item.status ?? 1) === 1 && String(item.value || '').trim())
        .map(item => getDefaultCustomSpecValue({ value: String(item.value || '').trim() }))
    }))
    .filter(spec => spec.name && spec.values.length)
  if (!nextSpecs.length) {
    selectedSpecTemplateId.value = ''
    message.warning('该规格模板暂无可用规格值')
    return
  }
  const skuCount = nextSpecs.reduce((total, spec) => total * spec.values.length, 1)
  if (skuCount > MAX_SPEC_COUNT) {
    selectedSpecTemplateId.value = ''
    message.warning(`该模板将生成 ${skuCount} 个 SKU，最多支持 ${MAX_SPEC_COUNT} 个`)
    return
  }
  customSpecType.value = 1
  customSpecs.value = nextSpecs
  syncCustomSkuRows()
  message.success(`已应用规格模板「${template.name}」`)
}

function addCustomSpec() {
  customSpecs.value.push(getDefaultCustomSpecCard())
  customSpecType.value = 1
  syncCustomSkuRows()
}

function removeCustomSpec(index) {
  customSpecs.value.splice(index, 1)
  if (!customSpecs.value.length) {
    customSpecs.value.push(getDefaultCustomSpecCard())
  }
  syncCustomSkuRows()
}

function handleCustomSpecValueBlur(card, item) {
  item.value = item.value.trim()
  const last = card.values[card.values.length - 1]
  if (last && last.rowKey === item.rowKey && item.value) {
    card.values.push(getDefaultCustomSpecValue())
  }
  syncCustomSkuRows()
}

function removeCustomSpecValue(card, element) {
  card.values = card.values.filter(item => item.rowKey !== element.rowKey)
  if (!card.values.length) {
    card.values.push(getDefaultCustomSpecValue())
  }
  syncCustomSkuRows()
}

function removeCustomSkuRow(rowKey) {
  const row = customSkuRows.value.find(item => item.rowKey === rowKey)
  if (row?.library_product_id) {
    removedLibraryProductIds.value.push(row.library_product_id)
  }
  customSkuRows.value = customSkuRows.value.filter(item => item.rowKey !== rowKey)
}

const categoryParentOptions = computed(() => {
  const normalize = (nodes, level = 0) => (nodes || []).map(node => ({
    ...node,
    disabled: level >= 2,
    children: normalize(node.children, level + 1)
  }))
  return normalize(merchantCategoryOptions.value)
})

const categoryParentProps = {
  checkStrictly: true,
  emitPath: true,
  value: 'value',
  label: 'label',
  children: 'children'
}

const specSummaryText = computed(() => {
  if (!librarySelected.value.length) {
    return ''
  }
  const groupCount = new Set()
  let freeCount = 0
  librarySelected.value.forEach(item => {
    const info = specValueMap.value[item.spec_value_id]
    if (info) {
      groupCount.add(info.specId)
    } else {
      freeCount += 1
    }
  })
  const parts = [`规格维度 ${groupCount.size} 个`]
  if (freeCount) {
    parts.push(`自由SKU ${freeCount} 个`)
  }
  parts.push(`共 ${librarySelected.value.length} 个规格值（SKU 行）`)
  return parts.join(' · ')
})

const specStockCards = computed(() => {
  const map = new Map()
  librarySelected.value.forEach(item => {
    const info = specValueMap.value[item.spec_value_id]
    const key = info ? `spec-${info.specId}` : 'free'
    if (!map.has(key)) {
      map.set(key, {
        key,
        specId: info ? info.specId : 0,
        title: info ? info.specName : '自由SKU',
        isFree: !info,
        items: []
      })
    }
    map.get(key).items.push({
      libraryId: item.id,
      specValueId: item.spec_value_id,
      name: info ? info.valueName : item.name
    })
  })
  return [...map.values()]
})

function loadOptions() {
  getPlatformProductCategoryTree().then(res => {
    platformCategoryOptions.value = res.data || []
    restorePlatformCategoryPath()
  }).catch(() => {})
  getProductCategoryTree().then(res => {
    merchantCategoryOptions.value = res.data || []
    restoreMerchantCategoryPaths()
  }).catch(() => {})
  getBrandOptions().then(res => { brandOptions.value = res.data || [] }).catch(() => {})
  getGroupOptions().then(res => { groupOptions.value = res.data || [] }).catch(() => {})
  getServiceOptions().then(res => { serviceOptions.value = res.data || [] }).catch(() => {})
  getFreightOptions().then(res => { freightOptions.value = res.data || [] }).catch(() => {})
  getSpecOptions().then(res => {
    const map = {}
    const specs = res.data || []
    specs.forEach(spec => {
      const values = spec.values || []
      values.forEach(value => {
        map[value.id] = { specId: spec.id, specName: spec.name, valueName: value.value }
      })
    })
    specValueMap.value = map
  }).catch(() => {})
}

function specValueName(valueId) {
  const info = specValueMap.value[valueId]
  return info ? `${info.specName}/${info.valueName}` : '-'
}

function getSkuSpecValue(row, column) {
  if (column.rowKey) {
    const keyPart = String(row.key || '').split('||')[customSpecs.value.findIndex(item => item.rowKey === column.rowKey)]
    const card = customSpecs.value.find(item => item.rowKey === column.rowKey)
    return card?.values.find(item => String(item.rowKey) === keyPart)?.value || ''
  }
  const card = specStockCards.value.find(item => item.key === column.key)
  const rowValueIds = String(row.spec_value_ids || '').split(',').filter(Boolean)
  const cardValueIds = new Set((card?.items || []).map(item => String(item.specValueId)).filter(Boolean))
  const valueId = rowValueIds.find(item => cardValueIds.has(item))
  return valueId ? specValueMap.value[valueId]?.valueName || '' : ''
}

function skuTableSpanMethod({ row, column, rowIndex }) {
  if (row.__isBatch) {
    return { rowspan: 1, colspan: 1 }
  }
  if (!skuSpecColumns.value.length || !column.className?.includes('sku-table__spec-column')) {
    return { rowspan: 1, colspan: 1 }
  }
  const columnIndex = skuSpecColumns.value.findIndex(item => item.key === column.property)
  if (columnIndex < 0) return { rowspan: 1, colspan: 1 }
  if (columnIndex > 0) return { rowspan: 1, colspan: 1 }
  const skuRowIndex = hasBatchSkuRow.value ? rowIndex - 1 : rowIndex
  const currentValue = getSkuSpecValue(row, skuSpecColumns.value[columnIndex])
  if (!currentValue) return { rowspan: 1, colspan: 1 }
  const previousValue = skuRowIndex > 0
    ? getSkuSpecValue(skuRowsForDisplay.value[skuRowIndex - 1], skuSpecColumns.value[columnIndex])
    : ''
  if (previousValue === currentValue) return { rowspan: 0, colspan: 0 }
  let rowspan = 1
  for (let index = skuRowIndex + 1; index < skuRowsForDisplay.value.length; index += 1) {
    if (getSkuSpecValue(skuRowsForDisplay.value[index], skuSpecColumns.value[columnIndex]) !== currentValue) break
    rowspan += 1
  }
  return { rowspan, colspan: 1 }
}

// ==================== 分类：新建 / 刷新 ====================

function openCategoryNew() {
  categoryNewName.value = ''
  categoryNewPath.value = []
  categoryNewPid.value = 0
  categoryNewVisible.value = true
}

function handleCategoryParentChange(path) {
  categoryNewPid.value = Array.isArray(path) && path.length ? Number(path[path.length - 1]) || 0 : 0
}

function submitCategoryNew() {
  if (!categoryNewName.value.trim()) {
    message.warning('请输入分类名称')
    return
  }
  categoryNewSaving.value = true
  createProductCategory({
    name: categoryNewName.value.trim(),
    pid: categoryNewPid.value || 0,
    sort: 0,
    status: 1
  }).then(res => {
    message.success(res.message || '添加成功')
    categoryNewVisible.value = false
    refreshCategory()
  }).catch(() => {}).finally(() => {
    categoryNewSaving.value = false
  })
}

function refreshCategory() {
  getProductCategoryTree().then(res => {
    merchantCategoryOptions.value = res.data || []
    restoreMerchantCategoryPaths()
  }).catch(() => {})
}

function applyPlatformCategoryPath(path) {
  form.category_id = Array.isArray(path) && path.length ? Number(path[path.length - 1]) || 0 : 0
  loadParamTemplate(form.category_id)
  formRef.value?.validateField('category_id')
}

function applyMerchantCategoryPaths() {
  form.category_ids = (merchantCategoryPaths.value || [])
    .map(path => Number(path[path.length - 1]))
    .filter(Boolean)
}

// 在分类树中查找 id 的根到叶路径
function findCategoryPath(nodes, id) {
  for (const node of nodes || []) {
    if (Number(node.value) === Number(id)) {
      return [node.value]
    }
    if (node.children && node.children.length) {
      const sub = findCategoryPath(node.children, id)
      if (sub.length) {
        return [node.value, ...sub]
      }
    }
  }
  return []
}

function restorePlatformCategoryPath() {
  if (platformCategoryPath.value.length || !platformCategoryOptions.value.length || !form.category_id) {
    return
  }
  platformCategoryPath.value = findCategoryPath(platformCategoryOptions.value, form.category_id)
}

function restoreMerchantCategoryPaths() {
  if (merchantCategoryPaths.value.length || !merchantCategoryOptions.value.length) {
    return
  }
  const ids = (form.category_ids || []).map(Number).filter(Boolean)
  merchantCategoryPaths.value = [...new Set(ids)]
    .map(id => findCategoryPath(merchantCategoryOptions.value, id))
    .filter(path => path.length)
}

function getDefaultParamRow(extra = {}) {
  return {
    row_key: '',
    param_id: 0,
    name: '',
    is_required: 0,
    sort: 0,
    values: [],
    selected_values: [],
    ...extra
  }
}

function normalizeParamTemplateRows(template = {}, snapshot = []) {
  const rows = Array.isArray(template.params) ? template.params : []
  const snapshotMap = new Map()
  ;(Array.isArray(snapshot) ? snapshot : []).forEach((item) => {
    if (!item || typeof item !== 'object') return
    const key = String(item.row_key || item.key || item.name || '')
    if (!key) return
    snapshotMap.set(key, item)
  })

  return rows.map((item, index) => {
    const rowKey = String(item.row_key || item.key || `param_${index + 1}`)
    const source = snapshotMap.get(rowKey) || {}
    const values = Array.isArray(item.values) ? item.values.map(value => String(value).trim()).filter(Boolean) : []
    const selectedValues = Array.isArray(source.selected_values)
      ? source.selected_values.map(value => String(value).trim()).filter(Boolean)
      : Array.isArray(item.selected_values)
        ? item.selected_values.map(value => String(value).trim()).filter(Boolean)
        : []
    return getDefaultParamRow({
      row_key: rowKey,
      param_id: Number(item.param_id || item.attribute_id || source.param_id || 0),
      name: String(item.name || source.name || '').trim(),
      is_required: Number(item.is_required || source.is_required || 0),
      sort: Number(item.sort || source.sort || index),
      values,
      selected_values: [...new Set(selectedValues)]
    })
  }).filter(item => item.name)
}

async function loadParamTemplate(categoryId, snapshot = [], templateId = 0) {
  if (!Number(categoryId)) {
    paramTemplate.value = null
    paramRows.value = []
    return
  }

  paramTemplateLoading.value = true
  try {
    const res = await getAttributeTemplateByCategory(categoryId)
    const data = res.data || {}
    paramTemplate.value = data && data.id ? data : null
    const rows = Array.isArray(data.params) ? data.params : []
    const normalized = normalizeParamTemplateRows({ params: rows }, snapshot)
    if (normalized.length) {
      paramRows.value = normalized
      if (templateId) {
        paramTemplate.value = { ...data, id: templateId }
      }
    } else {
      paramRows.value = []
    }
  } catch (error) {
    paramTemplate.value = null
    paramRows.value = []
  } finally {
    paramTemplateLoading.value = false
  }
}

function buildParamSnapshot() {
  return paramRows.value
    .map(row => ({
      row_key: row.row_key,
      param_id: Number(row.param_id) || 0,
      name: String(row.name || '').trim(),
      is_required: Number(row.is_required) || 0,
      sort: Number(row.sort) || 0,
      selected_values: Array.isArray(row.selected_values)
        ? row.selected_values.map(value => String(value).trim()).filter(Boolean)
        : []
    }))
    .filter(row => row.name && row.selected_values.length)
}

// ==================== 选品 ====================

function openPickDialog() {
  pickTempSelected.value = []
  pickDialogVisible.value = true
  loadPickList()
}

function loadPickList() {
  pickLoading.value = true
  const params = { name: pickSearch.name, status: 1, page: pickSearch.page, limit: pickSearch.limit }
  if (pickCategoryPath.value.length) {
    params.category_id = pickCategoryPath.value[pickCategoryPath.value.length - 1]
  }
  getProductLibraryList(params).then(res => {
    const data = res.data || {}
    pickList.value = data.list || []
    pickTotal.value = data.total || 0
  }).finally(() => {
    pickLoading.value = false
  })
}

function isAlreadySelected(id) {
  return librarySelected.value.some(item => item.id === id)
}

function handlePickSelection(rows) {
  pickTempSelected.value = rows || []
}

function confirmPick() {
  const toAdd = pickTempSelected.value.filter(row => !isAlreadySelected(row.id))
  const restCount = MAX_SPEC_COUNT - librarySelected.value.length
  if (toAdd.length > restCount) {
    message.warning(`规格（库商品）不能超过 ${MAX_SPEC_COUNT} 个`)
    librarySelected.value.push(...toAdd.slice(0, restCount))
  } else {
    librarySelected.value.push(...toAdd)
  }
  syncLibrarySkuRows()
  pickDialogVisible.value = false
  applyFirstLibrary()
  if (specEditorVisible.value) {
    refreshSpecEditor()
  }
}

function removeLibraryById(id) {
  librarySelected.value = librarySelected.value.filter(item => item.id !== id)
  syncLibrarySkuRows()
}

function handleLibraryTagRemove(value) {
  const target = librarySelected.value.find(item => item.name === value)
  if (target) {
    removeLibraryById(target.id)
  }
}

function handleLibraryTagAdd(value) {
  const target = librarySelected.value.find(item => item.name === value)
  if (!target) {
    librarySelected.value = [...librarySelected.value]
  }
}

function clearLibraryProducts() {
  librarySelected.value = []
  syncLibrarySkuRows()
}

function applyFirstLibrary() {
  const first = librarySelected.value[0]
  if (first && !nameTouched.value) {
    form.name = first.name
  }
}

// ==================== 规格编辑器弹窗 ====================

function openSpecEditor() {
  refreshSpecEditor()
  specEditorVisible.value = true
}

function handleOpenSpecStockEditor() {
  if (!librarySelected.value.length) {
    message.warning('请先在第一步选择商品库商品')
    activeStep.value = 0
    return
  }
  openSpecEditor()
}

function refreshSpecEditor() {
  const map = new Map()
  librarySelected.value.forEach(item => {
    const info = specValueMap.value[item.spec_value_id]
    const key = info ? `spec-${info.specId}` : 'free'
    if (!map.has(key)) {
      map.set(key, {
        specId: info ? info.specId : 0,
        title: info ? info.specName : '自由SKU',
        isFree: !info,
        items: []
      })
    }
    map.get(key).items.push({
      libraryId: item.id,
      name: item.name,
      rowKey: specRowKeySeed++
    })
  })
  specEditorCards.value = [...map.values()]
}

function confirmSpecEditor() {
  const ordered = []
  specEditorCards.value.forEach(card => {
    card.items.forEach(item => {
      const row = librarySelected.value.find(r => r.id === item.libraryId)
      if (row) {
        ordered.push(row)
      }
    })
  })
  librarySelected.value = ordered
  syncLibrarySkuRows()
  specEditorVisible.value = false
}

function removeSpecItem(card, element) {
  card.items = card.items.filter(item => item.rowKey !== element.rowKey)
}

function removeSpecCard(card) {
  if (card.items.length) {
    confirm(`确定要移除「${card.title}」下全部 ${card.items.length} 个库商品吗？`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    }).then(() => {
      card.items = []
    }).catch(() => {})
  }
}

function removeSpecCardByDisplay(card) {
  if (!card || !card.items.length) return
  confirm(`确定要移除「${card.title}」下全部 ${card.items.length} 个库商品吗？`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    const ids = card.items.map(item => item.libraryId)
    librarySelected.value = librarySelected.value.filter(item => !ids.includes(item.id))
    syncLibrarySkuRows()
  }).catch(() => {})
}

// ==================== 编辑器 ====================

function onEditorCreated(instance) {
  editor.value = Object.seal(instance)
}

function previewVideo() {
  window.open(form.video, '_blank')
}

// ==================== SKU ====================

function handleSkuImageSelect(row) {
  imgModal((image) => {
    const selected = Array.isArray(image) ? image[0] : image
    const url = selected && (selected.path || selected.url || selected.att_dir || selected.src)
    if (url) {
      row.pics = url
    }
  }, 1)
}

function applyBatchSku() {
  if (!skuRowsForDisplay.value.length) {
    message.warning('暂无可批量修改的 SKU')
    return
  }
  const fields = ['pics', 'price', 'ot_price', 'store_price', 'stock', 'weight', 'sort']
  const updates = fields.reduce((result, field) => {
    if (batchSku[field] !== null && batchSku[field] !== '') {
      result[field] = batchSku[field]
    }
    return result
  }, {})
  if (!Object.keys(updates).length) {
    message.warning('请先填写需要批量修改的内容')
    return
  }
  skuRowsForDisplay.value.forEach(row => {
    Object.assign(row, updates)
  })
  message.success(`已批量修改 ${skuRowsForDisplay.value.length} 个 SKU`)
}

function clearBatchSku() {
  Object.assign(batchSku, getDefaultBatchSku())
}

function handleDefaultChange(row) {
  if (row.is_default === 1) {
    skuRowsForDisplay.value.forEach(item => {
      if (item !== row && item.is_default === 1) {
        item.is_default = 0
      }
    })
  }
}

function handleSkuStatusChange(row) {
  if (!isLibraryMode.value || !isEdit.value || !row.id) {
    return
  }
  setStoreProductSkuStatus(productId.value, row.id, row.status).then(res => {
    message.success(res.message || '修改成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  }).finally(() => {
    loadSkus()
  })
}

function handleDeleteSku(row) {
  confirm(`确定要删除该SKU「${row.sku || row.id}」吗？软删后不影响历史订单快照。`, '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteStoreProductSku(productId.value, row.id).then(res => {
      message.success(res.message || '删除成功')
      loadSkus()
    }).catch(() => {})
  }).catch(() => {})
}

function loadSkus() {
  return getStoreProductSkuList(productId.value).then(res => {
    skuTable.value = (res.data || []).map(item => ({ ...item }))
    const currentSkus = new Map(skuTable.value.map(item => [item.uniqid, item]))
    customSkuRows.value.forEach(row => {
      const current = currentSkus.get(row.uniqid)
      // 只给相同库存快照刷新版本，不能让旧库存搭配新版本绕过冲突校验。
      if (current && Number(row.stock) === Number(current.stock)) row.stock_version = current.stock_version
    })
  })
}

function submitSku() {
  if (!skuTable.value.length) {
    message.warning('暂无SKU可保存')
    return
  }
  skuSaving.value = true
  const skus = skuTable.value.map(item => ({
    id: item.id,
    uniqid: item.uniqid,
    sku: item.sku,
    spec_value_ids: item.spec_value_ids || '',
    pics: item.pics || '',
    price: item.price,
    ot_price: item.ot_price,
    store_price: item.store_price,
    stock: item.stock,
    stock_version: item.stock_version,
    weight: item.weight || 0,
    sort: item.sort,
    is_default: item.is_default,
    status: item.status
  }))
  saveStoreProductSku(productId.value, { skus }).then(res => {
    message.success(res.message || '保存成功')
    loadSkus()
  }).catch(() => {}).finally(() => {
    skuSaving.value = false
  })
}

// ==================== 提交 ====================

function buildPayload(withLibraryIds) {
  const cover = Array.isArray(form.pics) && form.pics.length ? form.pics[0] : (form.cover || '')
  const isCustomMode = !isLibraryMode.value
  const librarySkuPayload = !isCustomMode
    ? skuTable.value.map(item => ({
      library_product_id: item.library_product_id,
      sku: item.sku,
      price: item.price,
      ot_price: item.ot_price,
      store_price: item.store_price,
      stock: item.stock,
      stock_version: item.stock_version,
      weight: item.weight,
      sort: item.sort,
      is_default: item.is_default,
      status: item.status,
      pics: item.pics || ''
    }))
    : []
  const customSkuPayload = isCustomMode
    ? customSkuRows.value.map(item => ({
      rowKey: item.rowKey,
      key: item.key,
      sku: item.sku,
      price: item.price,
      ot_price: item.ot_price,
      store_price: item.store_price,
      stock: item.stock,
      stock_version: item.stock_version,
      weight: item.weight,
      sort: item.sort,
      is_default: item.is_default,
      status: item.status,
      pics: item.pics,
      library_product_id: item.library_product_id || 0,
      uniqid: item.uniqid || ''
    }))
    : []
  const payload = {
    name: form.name,
    keyword: form.keyword,
    category_id: form.category_id,
    brand_id: form.brand_id || 0,
    group_id: form.group_id || 0,
    goods_type: form.goods_type,
    cover,
    pics: Array.isArray(form.pics) ? form.pics : [],
    video: form.video,
    description: form.description,
    detail: form.detail,
    is_free_shipping: form.is_free_shipping,
    attr_values: [],
    param_attribute_id: Number(paramTemplate.value && paramTemplate.value.id) || 0,
    param_snapshot: buildParamSnapshot(),
    spec_mode: isCustomMode ? 'custom' : 'library',
    custom_spec_type: isCustomMode ? customSpecType.value : (librarySelected.value.length > 1 ? 1 : 0),
    custom_specs: isCustomMode && Number(customSpecType.value) === 1
      ? customSpecs.value.map(card => ({
        rowKey: card.rowKey,
        name: card.name,
        values: card.values
          .filter(item => String(item.value || '').trim())
          .map(item => ({ rowKey: item.rowKey, value: String(item.value || '').trim() }))
      }))
      : [],
    library_skus: librarySkuPayload,
    custom_skus: customSkuPayload,
    removed_library_product_ids: [...new Set(removedLibraryProductIds.value.map(Number).filter(Boolean))],
    service_ids: Array.isArray(form.service_ids) ? form.service_ids.join(',') : '',
    freight_template_id: form.freight_template_id || 0,
    gift_points: form.gift_points,
    first_commission_rate: form.first_commission_rate,
    second_commission_rate: form.second_commission_rate,
    status: form.status,
    audit_status: 1,
    sort: form.sort,
    category_ids: form.category_ids,
    weight: skuWeightTotal.value
  }
  if (isEdit.value) {
    payload.id = productId.value
  }
  if (withLibraryIds) {
    payload.library_product_ids = librarySelected.value.map(item => item.id)
  }
  return payload
}

function submitForm() {
  const missingParam = paramRows.value.find(row => Number(row.is_required) === 1 && (!Array.isArray(row.selected_values) || row.selected_values.length === 0))
  if (missingParam) {
    message.warning(`请填写参数「${missingParam.name}」`)
    activeStep.value = 0
    return
  }
  if (!Number(form.brand_id)) {
    message.warning('请选择商品品牌')
    activeStep.value = 0
    return
  }
  if (!Number(form.category_id)) {
    message.warning('请选择平台分类')
    activeStep.value = 0
    return
  }
  if (isLibraryMode.value && !librarySelected.value.length) {
    message.warning('请先选择商品库商品，或改用自定义规格')
    activeStep.value = 0
    return
  }
  if (!isLibraryMode.value && !customSkuRows.value.length) {
    setCustomSingleSkuName()
    syncCustomSkuRows()
  }
  if (!isLibraryMode.value && !customSkuRows.value.some(item => String(item.sku || '').trim())) {
    message.warning('请至少填写一个SKU名称')
    activeStep.value = 1
    return
  }
  formRef.value && formRef.value.validate(valid => {
    if (!valid) {
      activeStep.value = 0
      return
    }
    submitLoading.value = true
    const reselect = isEdit.value && isLibraryMode.value
      ? librarySelected.value.map(item => item.id).join(',') !== initialLibraryIds.value.join(',')
      : isLibraryMode.value || !isEdit.value
    const api = !isEdit.value
      ? createStoreProduct(buildPayload(true))
      : updateStoreProduct(productId.value, buildPayload(isLibraryMode.value ? reselect : false))
    api.then(res => {
      message.success(res.message || '保存成功')
      if (isEdit.value && reselect) {
        initialLibraryIds.value = librarySelected.value.map(item => item.id)
        Promise.all([loadSkus()]).then(() => {
          message.warning('已按新选品重新组合 SKU，请核对并保存 SKU 设置')
        })
      } else {
        goBack()
      }
    }).catch(() => {}).finally(() => {
      submitLoading.value = false
    })
  })
}

function goBack() {
  router.push('/product/list')
}

// ==================== 初始化 ====================

function initEdit() {
  Promise.all([getStoreProductRead(productId.value), loadSkus()]).then(([detailRes]) => {
    const data = detailRes.data || {}
    const specConfig = data.spec_config || {}
    Object.assign(form, {
      name: data.name || '',
      keyword: data.keyword || '',
      category_id: data.category_id || 0,
      category_ids: Array.isArray(data.category_ids) ? data.category_ids.map(Number).filter(Boolean) : [],
      brand_id: data.brand_id || '',
      group_id: data.group_id || '',
      goods_type: Number(data.goods_type) || 1,
      cover: data.cover || '',
      pics: Array.isArray(data.pics) ? data.pics : [],
      video: data.video || '',
      description: data.description || '',
      detail: data.detail || '',
      is_free_shipping: Number(data.is_free_shipping) || 0,
      service_ids: data.service_ids ? String(data.service_ids).split(',').filter(Boolean).map(Number) : [],
      freight_template_id: data.freight_template_id || '',
      gift_points: Number(data.gift_points) || 0,
      first_commission_rate: Number(data.first_commission_rate) || 0,
      second_commission_rate: Number(data.second_commission_rate) || 0,
      status: Number(data.status),
      sort: Number(data.sort) || 0,
      weight: Number(data.weight) || 0
    })
    restorePlatformCategoryPath()
    restoreMerchantCategoryPaths()
    loadParamTemplate(
      form.category_id,
      Array.isArray(specConfig.param_snapshot) ? specConfig.param_snapshot : [],
      Number(specConfig.param_attribute_id) || 0
    )
    if ((specConfig.spec_mode || '') === 'custom') {
      customSpecType.value = Number(specConfig.custom_spec_type) || 0
      customSpecs.value = Array.isArray(specConfig.custom_specs) && specConfig.custom_specs.length
        ? specConfig.custom_specs.map(card => ({
          rowKey: card.rowKey || customRowKeySeed++,
          name: card.name || '',
          values: Array.isArray(card.values) && card.values.length
            ? [
              ...card.values.map(item => ({
                rowKey: item.rowKey || customRowKeySeed++,
                value: item.value || ''
              })),
              getDefaultCustomSpecValue()
            ]
            : [getDefaultCustomSpecValue()]
        }))
        : [getDefaultCustomSpecCard()]
      customSkuRows.value = Array.isArray(specConfig.custom_skus) && specConfig.custom_skus.length
        ? specConfig.custom_skus.map(item => ({
          ...getDefaultCustomSkuRow(),
          rowKey: item.rowKey || customRowKeySeed++,
          key: item.key || '',
          sku: item.sku || '',
          price: Number(item.price) || 0,
          ot_price: Number(item.ot_price) || 0,
          store_price: Number(item.store_price) || 0,
          stock: Number(skuTable.value.find(sku => sku.uniqid === item.uniqid)?.stock ?? item.stock) || 0,
          stock_version: skuTable.value.find(sku => sku.uniqid === item.uniqid)?.stock_version,
          weight: Number(item.weight) || 0,
          sort: Number(item.sort) || 0,
          is_default: Number(item.is_default) || 0,
          status: item.status === undefined || item.status === null ? 1 : Number(item.status),
          pics: item.pics || '',
          library_product_id: Number(item.library_product_id) || 0,
          uniqid: item.uniqid || ''
        }))
        : []
      librarySelected.value = []
      removedLibraryProductIds.value = []
      initialLibraryIds.value = Array.isArray(specConfig.library_product_ids) ? specConfig.library_product_ids.map(Number).filter(Boolean) : []
      nameTouched.value = true
      if (!customSkuRows.value.length) {
        handleStartCustomMode()
      }
      return
    }

    // 已选库商品由 SKU 行推导（库名用 SKU 文本回显）
    const ids = []
    librarySelected.value = (skuTable.value || []).map(sku => {
      if (sku.library_product_id) ids.push(sku.library_product_id)
      return {
        id: sku.library_product_id,
        name: sku.sku,
        product_code: sku.uniqid,
        price: Number(sku.price) || 0,
        stock: Number(sku.stock) || 0,
        unit: sku.unit || '',
        cover: sku.pics || '',
        spec_value_id: Number(String(sku.spec_value_ids || '').split(',')[0]) || 0
      }
    })
    initialLibraryIds.value = [...new Set(ids)].filter(Boolean)
    nameTouched.value = true
  })
}

onBeforeMount(() => {
  loadOptions()
  loadSpecTemplates()
  if (!isEdit.value) {
    handleStartCustomMode()
  }
  if (isEdit.value) {
    initEdit()
  }
})
</script>

<style lang="scss" scoped>
.product-list-create-page {
  min-height: 100%;
}

.sticky {
  position: sticky;
  top: 0;
  z-index: 10;
  margin-bottom: 16px;
}

.create-header {
  border: 0;
  border-radius: 12px 12px 0 0;
  background: var(--ds-surface-card);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

  :deep(.el-card__body) {
    padding: 14px 24px;
  }

  &__inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__left {
    display: inline-flex;
    align-items: center;
    gap: 12px;
  }

  &__right {
    display: inline-flex;
    gap: 10px;
  }
}

.back-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  color: var(--ds-on-surface-variant);
  cursor: pointer;

  &:hover {
    background: var(--ds-surface-low);
  }
}

.create-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--ds-on-surface);
}

/* 步骤条：圆角集中在左右两端（胶囊形），各步骤 div 相邻交界处为直角 */
.steps-bar {
  display: flex;
  margin-bottom: 16px;
  overflow: hidden;
  border-radius: 0 0 22px 22px;
  background: var(--ds-surface-card);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);

  &__item {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    height: 44px;
    border-radius: 0;
    cursor: pointer;
    background: var(--ds-surface-low);
    transition: background 0.2s ease;

    &:hover {
      background: rgba(0, 87, 194, 0.06);
    }

    &.is-active {
      background: var(--ds-primary);

      .steps-bar__name {
        color: var(--ds-on-primary);
      }

      .steps-bar__arrow {
        border-left-color: var(--ds-primary);
      }

      &:hover {
        background: var(--ds-primary);
      }
    }

    &.is-done {
      background: rgba(0, 87, 194, 0.1);

      .steps-bar__name {
        color: var(--ds-primary);
      }

      .steps-bar__arrow {
        border-left-color: rgba(0, 87, 194, 0.1);
      }
    }
  }

  &__name {
    padding-left: 24px;
    color: var(--ds-on-surface-variant);
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  // 箭头跟随所属步骤的状态色：未选中为灰（与底色一致），选中/已完成为蓝；仅选中项亮起主色
  &__arrow {
    position: absolute;
    right: -12px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 12px solid var(--ds-surface-low);
    border-top: 22px solid transparent;
    border-bottom: 22px solid transparent;
    z-index: 2;
  }
}

.create-body {
  border: 0;
  border-radius: 12px;
  background: var(--ds-surface-card);
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--ds-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--ds-surface-card);
  color: var(--ds-primary);
  border: 1px solid var(--ds-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--ds-primary-container);
    border-color: var(--ds-primary-container);
  }
}

.create-form {
  padding: 8px 0;

  // 大屏（≥1280px）下表单项的直接输入控件最大宽度约为当前表单宽度一半；小屏不限制
  @media (min-width: 1280px) {
    :deep(.el-form-item__content > .el-input:not(.el-input--textarea)),
    :deep(.el-form-item__content > .el-select),
    :deep(.el-form-item__content > .el-cascader),
    :deep(.el-form-item__content > .el-input-number) {
      max-width: 50%;
    }
  }
}

.step-panel {
  padding: 20px 24px;
  min-height: 420px;

  &__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 15px;
    font-weight: 600;
    color: var(--prev-color-text-primary);
  }

  &__bar {
    width: 3px;
    height: 14px;
    border-radius: 2px;
    background: var(--prev-color-primary);
  }
}

/* 商品类型卡片 */
.type-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.type-card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  width: 120px;
  height: 72px;
  padding: 0 16px;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    border-color: var(--ds-primary);
  }

  &.is-selected {
    border: 1px solid var(--ds-primary);
    background: rgba(0, 87, 194, 0.04);
  }

  &__title {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--ds-on-surface);
    font-size: 14px;
    font-weight: 500;
  }

  &__q {
    color: #c0c4cc;
    font-size: 14px;
  }

  &__desc {
    color: var(--ds-on-surface-variant);
    font-size: 12px;
  }

  &__check {
    position: absolute;
    right: 0;
    bottom: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-top-left-radius: 6px;
    border-bottom-right-radius: 6px;
    color: var(--ds-on-primary);
    background: var(--ds-primary);
    font-size: 13px;
  }
}

.category-field {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;

  .category-select {
    width: 480px;
  }

  &__quick {
    display: inline-flex;
    align-items: center;
    color: var(--ds-primary);
    font-size: 13px;

    a {
      cursor: pointer;

      &:hover {
        color: var(--ds-primary-hover);
      }
    }
  }
}

.pick-block {
  width: 100%;
}

.library-pick-block {
  max-width: 980px;
}

/* 已选库商品标签 */
.library-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.library-tag {
  border-radius: 4px;
}

/* 商品库置顶：输入框 + 选择按钮 */
.library-pick-bar {
  display: flex;
  align-items: center;
  gap: 10px;

  .library-pick-input {
    width: 480px;
  }
}

.library-pick-actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.collect-dialog {
  &__tip {
    padding: 10px 12px;
    margin-bottom: 18px;
    border-radius: 4px;
    color: var(--ds-on-surface-variant);
    background: var(--ds-surface-container-low);
    font-size: 13px;
    line-height: 1.6;
  }
}

/* 商品视频上传区窄幅 300px */
.video-upload-wrap {
  width: 340px;
}

.pick-tip {
  padding: 8px 12px;
  margin-bottom: 12px;
  border-radius: 6px;
  background: rgba(243, 65, 0, 0.06);
  color: #f34100;
  font-size: 12px;
  line-height: 1.5;
}

.spec-summary {
  margin-bottom: 10px;
  color: var(--ds-on-surface-variant);
  font-size: 13px;

  &--inline {
    margin-bottom: 0;
  }
}

.pick-table {
  margin-top: 8px;
}

.pick-cover {
  width: 36px;
  height: 36px;
  border-radius: 4px;
}

.sku-block {
  margin-top: 16px;

  &__title {
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  &__tip {
    margin-bottom: 10px;
    font-size: 12px;
    color: #909399;
  }
}

.sku-table {
  margin-top: 4px;
  width: 100%;
  font-size: 12px;

  :deep(.el-table__header th) {
    height: 36px;
    padding: 0;
    background: #f5f7fa;
    color: #303133;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
  }

  :deep(.el-table__cell) {
    height: 40px;
    padding: 4px 6px;
    border-color: #ebeef5;
  }

  :deep(.el-table__body tr:hover > td) {
    background: #fafcff;
  }

  :deep(.el-input__wrapper) {
    min-height: 28px;
    padding: 0 8px;
    border-radius: 3px;
    box-shadow: 0 0 0 1px #dcdfe6 inset;
  }

  :deep(.el-input-number .el-input__inner) {
    height: 28px;
    font-size: 12px;
    text-align: center;
  }

  :deep(.el-table__body .sku-table__spec-column) {
    color: #303133;
    font-size: 12px;
  }

  &--matrix {
    :deep(.el-table__body tr:first-child td) {
      border-top-color: #ebeef5;
    }
  }
}

.sku-table__spec-value {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sku-table__batch-label {
  color: var(--ds-primary);
  font-weight: 500;
}

.sku-table__batch-placeholder {
  color: #909399;
}

.sku-image-picker {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;

  &__preview,
  &__button {
    width: 40px;
    height: 40px;
    border-radius: 3px;
  }

  &__preview {
    border: 1px solid #ebeef5;
  }

  &__button {
    padding: 0;
    border-style: dashed;
    color: #606266;
    font-size: 18px;
  }

  &__remove {
    position: absolute;
    top: -5px;
    right: -5px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: 1px solid #dcdfe6;
    border-radius: 50%;
    background: #fff;
    color: #606266;
    cursor: pointer;

    &:hover {
      border-color: #f56c6c;
      color: #f56c6c;
    }
  }
}

.sku-table__input {
  width: 100%;
}

.sku-table__number {
  width: 100%;
}

.sku-table__text {
  display: inline-block;
  width: 100%;
  color: var(--ds-on-surface);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.spec-stock-type {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 18px;
  min-height: 32px;

  &__hint {
    color: var(--ds-on-surface-variant);
    font-size: 12px;
  }
}

.spec-template-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
}

.spec-template-select {
  width: 220px;
}

.spec-stock-card-list {
  width: 100%;
}

.spec-stock-card {
  position: relative;
  min-height: 88px;
  margin-bottom: 12px;
  padding: 20px 48px 20px 54px;
  border-radius: 4px;
  background: #f8f8f8;

  &.is-active {
    background: #eaf2ff;
  }

  &__name {
    width: 200px;
    height: 32px;
    padding: 0 14px;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    background: #fff;
    color: #303133;
    font-size: 13px;
    line-height: 32px;
  }

  &__values {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
  }

  &__tag {
    min-width: 84px;
    height: 32px;
    justify-content: center;
    border-color: #dcdfe6;
    background: #fff;
    color: #606266;
  }

  &__count {
    position: absolute;
    top: 28px;
    right: 58px;
    color: #606266;
    font-size: 12px;
  }

  &__close {
    position: absolute;
    top: 14px;
    right: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #3a3f47;
    color: #fff;
    cursor: pointer;
    transition: background-color 0.2s ease;

    &:hover {
      background: #23262d;
    }
  }
}

.spec-stock-card--custom {
  padding-left: 40px;
}

.spec-stock-card__name-wrap {
  position: relative;
  display: inline-flex;
  width: 260px;
}

.spec-stock-card__name-input {
  width: 100%;
}

.spec-stock-card__values--edit {
  gap: 12px;
}

.custom-spec-value {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  // 鼠标移入规格值输入后显示删除按钮
  &:hover .custom-spec-value__del,
  &:focus-within .custom-spec-value__del {
    opacity: 1;
    pointer-events: auto;
  }

  &__drag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 32px;
    cursor: grab;

    &:active {
      cursor: grabbing;
    }
  }

  &__field {
    position: relative;
    width: 150px;
  }

  &__input {
    width: 100%;
  }

  &__del {
    position: absolute;
    top: -7px;
    right: -7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #3a3f47;
    color: #fff;
    cursor: pointer;
    opacity: 0;
    pointer-events: none;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
    transition: opacity 0.2s ease, background-color 0.2s ease;

    &:hover {
      background: #23262d;
    }
  }
}

.drag-icon-img {
  width: 10px;
  height: 18px;
  object-fit: contain;
}

.single-spec-hint {
  width: 100%;
  padding: 18px 20px;
  border-radius: 4px;
  background: #f8f8f8;
  color: #606266;
  font-size: 13px;
}

.shipping-weight {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.shipping-weight__value {
  color: var(--ds-on-surface);
  font-size: 14px;
  font-weight: 600;
}

.spec-stock-card-actions {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-top: 2px;
}

.advanced-number {
  width: 200px;
}

.form-width {
  width: 100%;

  :deep(.el-input-number) {
    width: 100%;

    .el-input__inner {
      text-align: left;
    }
  }
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.5;
  width: 100%;
}

.editor-wrap {
  width: 100%;
  border: 1px solid var(--prev-border-color-lighter);
  border-radius: 6px;
}

.editor-toolbar {
  border-bottom: 1px solid var(--prev-border-color-lighter);
}

.video-preview {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  color: var(--ds-primary);
  font-size: 13px;
  cursor: pointer;

  &:hover {
    color: var(--ds-primary-hover);
  }
}

/* 规格编辑器弹窗 */
.spec-editor {
  padding: 8px 8px 0;

  &__form {
    margin-bottom: 12px;
  }

  &__add {
    width: 100%;
    margin-top: 12px;
    border-style: dashed;
  }

  &__tip {
    margin-top: 12px;
    font-size: 12px;
    color: #909399;
    line-height: 1.5;
  }
}

.spec-card {
  margin-bottom: 16px;
  padding: 18px 20px;
  border-radius: 8px;
  background: rgba(0, 87, 194, 0.05);

  &.is-free {
    background: rgba(245, 108, 108, 0.05);
  }

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  &__title {
    font-size: 15px;
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  &__count {
    margin-left: 10px;
    color: var(--ds-on-surface-variant);
    font-size: 12px;
  }

  &__action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    color: var(--ds-on-primary);
    background: var(--ds-primary);
    cursor: pointer;

    &:hover {
      background: var(--ds-primary-hover);
    }
  }
}

.value-row-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 8px;
}

.value-row {
  display: flex;
  align-items: center;
  gap: 10px;

  &__chip {
    display: inline-flex;
    align-items: center;
    min-width: 120px;
    padding: 4px 12px;
    border-radius: 4px;
    background: var(--ds-surface-card);
    color: var(--ds-on-surface);
    font-size: 13px;
  }

  &__del {
    display: inline-flex;
    align-items: center;
    color: #f56c6c;
    font-size: 16px;
    cursor: pointer;

    &:hover {
      opacity: 0.8;
    }
  }

  &__add {
    margin-top: 12px;
    padding-left: 0;
  }
}

.drag-handle {
  display: inline-flex;
  align-items: center;
  color: var(--ds-on-surface-variant);
  cursor: grab;

  i {
    font-size: 14px;
    line-height: 1;
  }

  &:active {
    cursor: grabbing;
  }
}

/* 弹窗 */
.pick-dialog {
  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;

    .el-pagination {
      margin: 0;
    }
  }

  &__count {
    color: var(--ds-on-surface-variant);
    font-size: 13px;
  }
}

.pick-search {
  margin-bottom: 12px;

  &__input {
    width: 180px;
  }

  &__cascader {
    width: 200px;
  }
}

.cfg-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.empty-text {
  color: #909399;
  font-size: 12px;
}

.product-param-panel {
  width: 100%;

  &__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    color: var(--ds-on-surface-variant);
    font-size: 12px;
  }

  &__title {
    font-weight: 600;
    color: var(--ds-on-surface);
  }

  &__list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
}

.product-param-row {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
  gap: 12px;
  align-items: center;

  &__label {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    color: var(--ds-on-surface);
    font-size: 13px;
    font-weight: 500;
  }

  &__select {
    width: 100%;
  }
}
</style>
