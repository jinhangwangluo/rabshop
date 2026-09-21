<template>
  <div class="special-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">专题管理</h1>
        <p class="page-curator-subtitle">
          支持通过顶部 Tab 快速切换全部、商户和平台专题，并可直接处理商户提交专题审核。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        :icon="Plus"
        @click="handleAdd"
      >
        添加专题
      </el-button>
    </header>

    <div class="special-work-surface">
      <div class="special-source-tabs-wrap">
        <el-tabs
          v-model="activeTab"
          class="special-source-tabs"
          @tab-click="handleTabClick"
        >
          <el-tab-pane name="all">
            <template #label>
              <span>全部</span>
            </template>
          </el-tab-pane>
          <el-tab-pane name="merchant">
            <template #label>
              <span class="special-source-tab-label">
                <span>商户</span>
                <el-badge
                  v-if="pendingTabs.merchant > 0"
                  :value="pendingTabs.merchant"
                  :max="99"
                  class="special-source-tab-badge"
                />
              </span>
            </template>
          </el-tab-pane>
          <el-tab-pane name="platform">
            <template #label>
              <span>平台</span>
            </template>
          </el-tab-pane>
        </el-tabs>
      </div>

      <el-form
        ref="searchForm"
        :model="searchForm"
        inline
        class="search-form special-search-form"
        @submit.prevent
      >
        <el-form-item v-if="isMerchantSource" label="审核状态">
          <el-select
            v-model="searchForm.audit_status"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="待审核" :value="0" />
            <el-option label="审核通过" :value="1" />
            <el-option label="审核驳回" :value="2" />
            <el-option label="已下架" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="专题标题">
          <el-input
            v-model="searchForm.title"
            clearable
            placeholder="请输入专题标题"
            class="search-input"
            :prefix-icon="Search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="专题类型">
          <el-select
            v-model="searchForm.special_type"
            placeholder="全部类型"
            clearable
            class="search-select-sm"
          >
            <el-option label="免费" :value="0" />
            <el-option label="付费" :value="1" />
          </el-select>
        </el-form-item>
        <el-form-item label="热门状态">
          <el-select
            v-model="searchForm.is_hot"
            placeholder="全部"
            clearable
            class="search-select-sm"
          >
            <el-option label="热门" :value="1" />
            <el-option label="普通" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="显示状态">
          <el-select
            v-model="searchForm.status"
            placeholder="全部状态"
            clearable
            class="search-select-sm"
          >
            <el-option label="显示" :value="1" />
            <el-option label="隐藏" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            class="btn-curator-primary"
            :icon="Search"
            @click="handleSearch"
          >
            查询
          </el-button>
          <el-button
            class="btn-curator-reset"
            :icon="Refresh"
            @click="handleReset"
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>

      <el-table
        v-loading="loading"
        :data="specialList"
        row-key="id"
        class="special-curator-table"
      >
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="封面" width="80" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              :preview-src-list="[scope.row.cover]"
              class="cover-img"
              fit="cover"
            />
            <span v-else class="no-cover">暂无</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="专题标题" min-width="180" show-overflow-tooltip />
        <el-table-column label="标签" min-width="170">
          <template #default="scope">
            <div class="tag-cell">
              <TagPreview
                v-for="tag in scope.row.tag_list || []"
                :key="tag.id"
                :tag="tag"
              />
              <span v-if="!scope.row.tag_list || !scope.row.tag_list.length" class="empty-text">-</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="来源" width="100" align="center">
          <template #default="scope">
            <el-tag :type="Number(scope.row.merchant_id) > 0 ? 'warning' : 'info'">
              {{ scope.row.source_text || getSourceLabel(scope.row.merchant_id) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          v-if="isMerchantSource"
          label="所属商户"
          min-width="180"
          show-overflow-tooltip
        >
          <template #default="scope">
            <span class="merchant-name">{{ formatMerchantName(scope.row) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="80" align="center">
          <template #default="scope">
            <el-tag :type="Number(scope.row.special_type) === 1 ? 'danger' : 'success'">
              {{ Number(scope.row.special_type) === 1 ? '付费' : '免费' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="100" align="center">
          <template #default="scope">
            <span v-if="Number(scope.row.special_type) === 1 && Number(scope.row.price) > 0" class="price-text">
              ¥{{ scope.row.price }}
            </span>
            <span v-else class="free-text">-</span>
          </template>
        </el-table-column>
        <el-table-column label="内容数" width="110" align="center" class-name="content-count-cell">
          <template #default="scope">
            <div class="content-badge-wrap">
              <el-badge
                :value="scope.row.content_count || 0"
                :max="999"
                class="content-badge"
              >
                <a
                  href="javascript:;"
                  class="table-action-link"
                  @click.prevent="handleViewContents(scope.row.id)"
                >查看</a>
              </el-badge>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="热门" width="70" align="center">
          <template #default="scope">
            <el-tag v-if="scope.row.is_hot" type="warning">热门</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column
          v-if="isMerchantSource"
          label="审核状态"
          width="100"
          align="center"
        >
          <template #default="scope">
            <el-tag :type="getAuditTagType(scope.row.audit_status)">
              {{ scope.row.audit_status_text || getAuditLabel(scope.row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column
          v-if="isMerchantSource"
          label="审核备注"
          min-width="160"
          show-overflow-tooltip
        >
          <template #default="scope">
            <span class="audit-remark">{{ scope.row.audit_remark || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="sort" label="排序" width="70" align="center" />
        <el-table-column label="状态" width="90" align="center">
          <template #default="scope">
            <el-switch
              v-model="scope.row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(scope.row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="160" align="center" />
        <el-table-column :width="isMerchantSource ? 230 : 190" label="操作" fixed="right" align="center">
          <template #default="scope">
            <div class="table-action-group">
              <a
                href="javascript:;"
                class="table-action-link"
                @click.prevent="handleViewDetail(scope.row.id)"
              >详情</a>
              <template v-if="showAuditAction(scope.row)">
                <el-divider direction="vertical" />
                <a
                  href="javascript:;"
                  class="table-action-link"
                  @click.prevent="handleAudit(scope.row)"
                >审核</a>
              </template>
              <el-divider direction="vertical" />
              <a
                href="javascript:;"
                class="table-action-link"
                @click.prevent="handleEdit(scope.row.id)"
              >编辑</a>
              <el-divider direction="vertical" />
              <el-dropdown
                trigger="click"
                class="table-action-more"
                @command="command => handleMoreCommand(command, scope.row)"
              >
                <a href="javascript:;" class="table-action-link" @click.prevent>
                  更多<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                </a>
                <template #dropdown>
                  <el-dropdown-menu popper-class="special-action-dropdown">
                    <el-dropdown-item
                      v-if="Number(scope.row.merchant_id || 0) === 0"
                      command="tag"
                    >编辑标签</el-dropdown-item>
                    <el-dropdown-item
                      v-if="showForceOfflineAction(scope.row)"
                      command="force_offline"
                      class="dropdown-danger"
                    >强制下架</el-dropdown-item>
                    <el-dropdown-item command="delete" class="dropdown-danger">删除</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          :current-page="page"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <el-dialog
      v-model="auditVisible"
      title="审核商户专题"
      width="520px"
      :close-on-click-modal="false"
    >
      <el-form :model="auditForm" label-width="90px">
        <el-form-item label="专题标题">
          <span>{{ auditTarget.title || '-' }}</span>
        </el-form-item>
        <el-form-item label="所属商户">
          <span>{{ formatMerchantName(auditTarget) }}</span>
        </el-form-item>
        <el-form-item label="审核结果">
          <el-radio-group v-model="auditForm.audit_status">
            <el-radio :label="1">审核通过</el-radio>
            <el-radio :label="2">审核驳回</el-radio>
            <el-radio :label="3">违规下架</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="auditForm.audit_status === 2 || auditForm.audit_status === 3" label="审核备注">
          <el-input
            v-model="auditForm.audit_remark"
            type="textarea"
            :rows="3"
            placeholder="请填写审核备注（必填）"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="auditVisible = false">取 消</el-button>
          <el-button type="primary" :loading="auditLoading" @click="submitAudit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <TagSelector
      ref="tagQuickSelectorRef"
      v-model="tagEditIds"
      class="hidden-tag-selector"
      :tag-type="1"
      :show-quick-add="false"
      @confirm="submitTagEdit"
    />

    <el-drawer
      v-model="detailDrawerVisible"
      title="专题详情"
      direction="rtl"
      size="880px"
      :wrapper-closable="true"
      @close="handleDetailDrawerClose"
    >
      <div v-loading="detailLoading" class="special-detail-drawer">
        <template v-if="detailData.id">
          <div class="special-detail-hero">
            <el-image
              v-if="detailData.cover"
              :src="detailData.cover"
              :preview-src-list="[detailData.cover]"
              class="special-detail-hero__cover"
              fit="cover"
            />
            <div v-else class="special-detail-hero__cover special-detail-hero__cover--empty">
              <el-icon><Picture /></el-icon>
            </div>
            <div class="special-detail-hero__main">
              <div class="special-detail-hero__title">{{ detailData.title || '-' }}</div>
              <div class="special-detail-hero__tags">
                <el-tag :type="Number(detailData.special_type) === 1 ? 'danger' : 'success'">
                  {{ Number(detailData.special_type) === 1 ? '付费专题' : '免费专题' }}
                </el-tag>
                <el-tag :type="Number(detailData.merchant_id) > 0 ? 'warning' : 'info'">
                  {{ detailData.source_text || getSourceLabel(detailData.merchant_id) }}
                </el-tag>
                <el-tag v-if="detailData.is_hot" type="warning">热门推荐</el-tag>
                <el-tag :type="getAuditTagType(detailData.audit_status)">
                  {{ detailData.audit_status_text || getAuditLabel(detailData.audit_status) }}
                </el-tag>
                <el-tag :type="Number(detailData.status) === 1 ? 'success' : 'info'">
                  {{ Number(detailData.status) === 1 ? '显示中' : '已隐藏' }}
                </el-tag>
              </div>
              <div class="special-detail-hero__desc">{{ detailData.description || '暂无专题简介' }}</div>
            </div>
          </div>

          <el-tabs v-model="detailActiveTab" class="special-detail-tabs">
            <el-tab-pane label="基本信息" name="base">
              <div class="special-detail-sections">
                <section class="special-detail-section">
                  <div class="special-detail-section__title">基础信息</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题ID</span>
                      <span class="special-detail-item__value">{{ detailData.id || '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题标题</span>
                      <span class="special-detail-item__value">{{ detailData.title || '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题来源</span>
                      <span class="special-detail-item__value">{{ detailData.source_text || getSourceLabel(detailData.merchant_id) }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">所属商户</span>
                      <span class="special-detail-item__value">{{ formatMerchantName(detailData) }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">专题类型</span>
                      <span class="special-detail-item__value">{{ Number(detailData.special_type) === 1 ? '付费' : '免费' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">所属分类</span>
                      <span class="special-detail-item__value">{{ formatCategoryName(detailData) }}</span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">专题标签</span>
                      <span class="special-detail-item__value special-detail-tags">
                        <TagPreview
                          v-for="tag in detailData.tag_list || []"
                          :key="tag.id"
                          :tag="tag"
                        />
                        <span v-if="!detailData.tag_list || !detailData.tag_list.length">-</span>
                      </span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">关键词</span>
                      <span class="special-detail-item__value">{{ detailData.keywords || '-' }}</span>
                    </div>
                  </div>
                </section>

                <section class="special-detail-section">
                  <div class="special-detail-section__title">售卖与数据</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">售价</span>
                      <span class="special-detail-item__value price-text">{{ Number(detailData.special_type) === 1 ? `¥${detailData.price || 0}` : '免费' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">原价</span>
                      <span class="special-detail-item__value">{{ Number(detailData.original_price) > 0 ? `¥${detailData.original_price}` : '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">内容数</span>
                      <span class="special-detail-item__value">{{ detailData.content_count || (detailData.contents || []).length || 0 }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">排序</span>
                      <span class="special-detail-item__value">{{ detailData.sort || 0 }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">浏览量</span>
                      <span class="special-detail-item__value">{{ detailData.view_count || detailData.visit || 0 }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">购买量</span>
                      <span class="special-detail-item__value">{{ detailData.buy_count || detailData.pay_count || 0 }}</span>
                    </div>
                  </div>
                </section>

                <section class="special-detail-section">
                  <div class="special-detail-section__title">审核与状态</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">审核状态</span>
                      <span class="special-detail-item__value">{{ detailData.audit_status_text || getAuditLabel(detailData.audit_status) }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">热门状态</span>
                      <span class="special-detail-item__value">{{ detailData.is_hot ? '热门推荐' : '普通专题' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">显示状态</span>
                      <span class="special-detail-item__value">{{ Number(detailData.status) === 1 ? '显示' : '隐藏' }}</span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">审核备注</span>
                      <span class="special-detail-item__value">{{ detailData.audit_remark || '-' }}</span>
                    </div>
                  </div>
                </section>

                <section class="special-detail-section">
                  <div class="special-detail-section__title">时间与简介</div>
                  <div class="special-detail-grid">
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">创建时间</span>
                      <span class="special-detail-item__value">{{ detailData.create_time || '-' }}</span>
                    </div>
                    <div class="special-detail-item">
                      <span class="special-detail-item__label">更新时间</span>
                      <span class="special-detail-item__value">{{ detailData.update_time || '-' }}</span>
                    </div>
                    <div class="special-detail-item special-detail-item--full">
                      <span class="special-detail-item__label">专题简介</span>
                      <span class="special-detail-item__value special-detail-item__value--desc">{{ detailData.description || '-' }}</span>
                    </div>
                  </div>
                </section>
              </div>
            </el-tab-pane>
            <el-tab-pane :label="`内容列表（${(detailData.contents || []).length}）`" name="contents">
              <div class="special-detail-content-toolbar">
                <div class="special-detail-content-toolbar__text">
                  已关联 {{ (detailData.contents || []).length }} 个内容
                </div>
                <el-button
                  type="primary"

                  :icon="Plus"
                  class="btn-curator-primary"
                  @click="openContentSelector"
                >添加内容</el-button>
              </div>
              <el-empty v-if="!(detailData.contents || []).length" description="暂无内容" />
              <el-table
                v-else
                :data="detailData.contents"
                class="special-detail-content-table"

                row-key="article_id"
              >
                <el-table-column prop="sort" label="排序" width="60" align="center" />
                <el-table-column label="封面" width="70" align="center">
                  <template #default="scope">
                    <el-image
                      v-if="scope.row.article && scope.row.article.cover"
                      :src="scope.row.article.cover"
                      class="mini-cover"
                      fit="cover"
                    />
                    <span v-else>-</span>
                  </template>
                </el-table-column>
                <el-table-column label="内容标题" min-width="130" show-overflow-tooltip>
                  <template #default="scope">
                    {{ scope.row.article ? scope.row.article.title : (scope.row.title || '-') }}
                  </template>
                </el-table-column>
                <el-table-column label="类型" width="80" align="center">
                  <template #default="scope">
                    <el-tag
                      :type="getTypeTagColor(scope.row.article ? scope.row.article.content_type : scope.row.type)"
                      size="mini"
                    >
                      {{ getTypeName(scope.row.article ? scope.row.article.content_type : scope.row.type) }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="价格" width="90" align="center">
                  <template #default="scope">
                    <span v-if="scope.row.article && Number(scope.row.article.price) > 0" class="price-text">
                      ¥{{ scope.row.article.price }}
                    </span>
                    <el-tag v-else type="success" size="mini">免费</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="操作" width="80" fixed="right" align="center">
                  <template #default="scope">
                    <a
                      class="remove-btn"
                      @click="removeDetailContent(scope.$index)"
                    >移除</a>
                  </template>
                </el-table-column>
              </el-table>
            </el-tab-pane>
          </el-tabs>
        </template>
        <el-empty v-else-if="!detailLoading" description="暂无详情数据" />
      </div>
    </el-drawer>
    <el-dialog
      v-model="selectorVisible"
      title="选择内容"
      width="900px"
      :close-on-click-modal="false"
      append-to-body
      @open="onSelectorOpen"
      @close="onSelectorClose"
    >
      <div class="selector-search">
        <el-form
          ref="selectorSearchForm"
          :model="selectorSearch"
          inline
          class="search-form"
          @submit.prevent
        >
          <el-form-item label="内容标题">
            <el-input
              v-model="selectorSearch.title"
              placeholder="搜索内容标题"
              clearable
              :prefix-icon="Search"
              class="selector-input"
              @keyup.enter="loadSelectorList"
            />
          </el-form-item>
          <el-form-item label="内容类型">
            <el-select
              v-model="selectorSearch.content_type"
              placeholder="全部类型"
              clearable
              class="selector-select"
            >
              <el-option
                v-for="item in contentTypeOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadSelectorList">搜索</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table
        ref="selectorTable"
        v-loading="selectorLoading"
        :data="selectorList"

        row-key="id"
        @selection-change="handleSelectorSelection"
      >
        <el-table-column type="selection" width="45" align="center" :selectable="isSelectable" />
        <el-table-column prop="id" label="ID" width="60" align="center" />
        <el-table-column label="封面" width="65" align="center">
          <template #default="scope">
            <el-image
              v-if="scope.row.cover"
              :src="scope.row.cover"
              class="mini-cover"
              fit="cover"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="160" show-overflow-tooltip />
        <el-table-column label="类型" width="75" align="center">
          <template #default="scope">
            <el-tag :type="getTypeTagColor(scope.row.content_type)" size="mini">
              {{ getTypeName(scope.row.content_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="90" align="center">
          <template #default="scope">
            <span v-if="scope.row.price > 0" class="price-text">¥{{ scope.row.price }}</span>
            <el-tag v-else type="success" size="mini">免费</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="scope">
            <el-tag v-if="isAlreadySelected(scope.row.id)" type="info" size="mini">已选</el-tag>
            <el-tag v-else type="success" size="mini">可选</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <div class="selector-page">
        <el-pagination
          :current-page="selectorPage"
          :page-sizes="[10, 20]"
          :page-size="selectorLimit"
          layout="total, sizes, prev, pager, next"
          :total="selectorTotal"
          @size-change="handleSelectorSizeChange"
          @current-change="handleSelectorPageChange"
        />
      </div>

      <template #footer>
        <div>
          <span class="selector-tip">已选 {{ tempSelected.length }} 项</span>
          <el-button @click="selectorVisible = false">取消</el-button>
          <el-button type="primary" :loading="contentSaving" @click="confirmContentSelection">确认添加</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowDown, Plus, Refresh, Search, Picture } from '@element-plus/icons-vue'
import {
  getSpecialList,
  specialRead,
  specialDelete,
  specialSetShow,
  specialSave,
  auditSpecial,
  getSpecialPendingCount,
  searchSpecialContent
} from '@/api/special'
import { saveTagRelation } from '@/api/tag'
import TagSelector from '@/components/TagSelector/index'
import TagPreview from '@/components/TagPreview/index'

defineOptions({ name: 'SpecialManage' })

const CONTENT_TYPE_MAP = {
  1: { label: '图文', color: 'primary' },
  2: { label: '文字', color: '' },
  3: { label: '小说', color: 'warning' },
  4: { label: '图片', color: 'success' },
  5: { label: '音频', color: 'danger' },
  6: { label: '视频', color: 'info' }
}

const TAB_STORAGE_KEY = 'admin.special.list.activeTab'
const VALID_TABS = ['all', 'merchant', 'platform']

const route = useRoute()
const router = useRouter()

const tagQuickSelectorRef = ref(null)

const loading = ref(false)
const activeTab = ref('all')
const pendingTabs = reactive({
  merchant: 0
})
const specialList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const searchForm = reactive(getDefaultSearchForm())
const auditVisible = ref(false)
const auditLoading = ref(false)
const auditTarget = ref({})
const auditForm = reactive({
  audit_status: 1,
  audit_remark: ''
})
const detailDrawerVisible = ref(false)
const detailLoading = ref(false)
const detailActiveTab = ref('base')
const detailData = reactive(getDefaultDetailData())
const tagEditLoading = ref(false)
const tagEditTarget = ref({})
const tagEditIds = ref([])
const contentTypeOptions = Object.entries(CONTENT_TYPE_MAP).map(([value, info]) => ({
  value: Number(value),
  label: info.label
}))
const selectorVisible = ref(false)
const selectorLoading = ref(false)
const contentSaving = ref(false)
const selectorList = ref([])
const selectorTotal = ref(0)
const selectorPage = ref(1)
const selectorLimit = ref(10)
const selectorSearch = reactive({
  title: '',
  content_type: ''
})
const tempSelected = ref([])
const routeDetailId = ref('')

const isMerchantSource = computed(() => activeTab.value === 'merchant')

function getDefaultSearchForm(contentSource = 'all') {
  return {
    title: '',
    special_type: '',
    is_hot: '',
    status: '',
    audit_status: '',
    content_source: contentSource
  }
}

function getDefaultDetailData() {
  return {
    id: 0,
    title: '',
    cover: '',
    special_type: 0,
    merchant_id: 0,
    merchant: {},
    source_text: '',
    description: '',
    tag_list: [],
    category_id: 0,
    category_name: '',
    keywords: '',
    price: 0,
    original_price: 0,
    sort: 0,
    is_hot: 0,
    view_count: 0,
    visit: 0,
    buy_count: 0,
    content_count: 0,
    contents: [],
    audit_status: 0,
    audit_status_text: '',
    audit_remark: '',
    status: 0,
    create_time: '',
    update_time: ''
  }
}

function initData() {
  return Promise.all([
    getList(),
    fetchPendingCount()
  ])
}

function applyRouteDetailQuery(detailId) {
  const value = String(detailId || '').trim()
  if (!value) {
    routeDetailId.value = ''
    return
  }
  if (value === routeDetailId.value) {
    return
  }

  routeDetailId.value = value
  handleViewDetail(value)
}

function resolveInitialTab() {
  const routeTab = route.query.tab || route.query.content_source
  if (VALID_TABS.includes(routeTab)) {
    return routeTab
  }
  try {
    const cachedTab = window.localStorage.getItem(TAB_STORAGE_KEY)
    if (VALID_TABS.includes(cachedTab)) {
      return cachedTab
    }
  } catch (error) {
    console.error(error)
  }
  return 'all'
}

function persistActiveTab(tab) {
  try {
    window.localStorage.setItem(TAB_STORAGE_KEY, tab)
  } catch (error) {
    console.error(error)
  }
}

function fetchPendingCount() {
  return getSpecialPendingCount()
    .then(res => {
      const data = res.data || {}
      pendingTabs.merchant = Number(data.special || 0)
    })
    .catch(error => {
      pendingTabs.merchant = 0
      console.error(error)
    })
}

function getList() {
  loading.value = true
  const params = {
    page: page.value,
    limit: limit.value,
    ...searchForm
  }
  Object.keys(params).forEach(key => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) {
      delete params[key]
    }
  })
  return getSpecialList(params)
    .then(res => {
      const data = res.data || {}
      specialList.value = data.list || []
      total.value = data.total || data.count || 0
    })
    .finally(() => {
      loading.value = false
    })
}

function handleTabClick(tab) {
  handleTabChange(tab.name)
}

function handleTabChange(tab) {
  activeTab.value = VALID_TABS.includes(tab) ? tab : 'all'
  searchForm.content_source = activeTab.value
  if (!isMerchantSource.value) {
    searchForm.audit_status = ''
  }
  page.value = 1
  persistActiveTab(activeTab.value)
  getList()
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, getDefaultSearchForm(activeTab.value))
  page.value = 1
  getList()
}

function handleSizeChange(val) {
  limit.value = val
  page.value = 1
  getList()
}

function handleCurrentChange(val) {
  page.value = val
  getList()
}

function handleAdd() {
  router.push('/special/manage/create')
}

function handleEdit(id) {
  router.push(`/special/manage/create?id=${id}`)
}

function handleMoreCommand(command, row) {
  if (command === 'tag') {
    openTagEdit(row)
    return
  }
  if (command === 'delete') {
    handleDelete(row.id)
  }
  if (command === 'force_offline') {
    handleForceOffline(row)
  }
}

function openTagEdit(row) {
  tagEditTarget.value = row || {}
  tagEditIds.value = Array.isArray(row.tag_ids) ? row.tag_ids.slice() : []
  nextTick(() => {
    tagQuickSelectorRef.value?.openSelectDialog?.()
  })
}

function submitTagEdit(tagIds = tagEditIds.value) {
  if (!tagEditTarget.value.id) return
  tagEditLoading.value = true
  saveTagRelation({
    relation_type: 'special',
    relation_id: tagEditTarget.value.id,
    tag_type: 1,
    tag_ids: Array.isArray(tagIds) ? tagIds : tagEditIds.value
  }).then(res => {
    ElMessage.success(res.message || '保存成功')
    getList()
  }).finally(() => {
    tagEditLoading.value = false
  })
}

function handleViewDetail(id, activeTab = 'base') {
  detailDrawerVisible.value = true
  detailLoading.value = true
  detailActiveTab.value = activeTab
  Object.assign(detailData, getDefaultDetailData())
  return specialRead(id)
    .then(res => {
      const data = res.data || {}
      Object.assign(detailData, getDefaultDetailData(), data, {
        contents: Array.isArray(data.contents) ? data.contents : []
      })
    })
    .finally(() => {
      detailLoading.value = false
    })
}

function handleViewContents(id) {
  handleViewDetail(id, 'contents')
}

function handleDetailDrawerClose() {
  detailActiveTab.value = 'base'
  Object.assign(detailData, getDefaultDetailData())
  selectorVisible.value = false
}

function getContentArticleId(item = {}) {
  return Number(item.article_id || (item.article && item.article.id) || item.id || 0)
}

function normalizeDetailContents(contents = detailData.contents) {
  const normalized = (contents || []).map((item, index) => ({
    ...item,
    article_id: getContentArticleId(item),
    type: item.type || (item.article && item.article.content_type) || 1,
    sort: index + 1
  }))
  Object.assign(detailData, {
    contents: normalized,
    content_count: normalized.length
  })
  return normalized
}

function buildDetailSubmitData(contents) {
  return {
    title: detailData.title || '',
    merchant_id: Number(detailData.merchant_id || 0),
    category_id: Number(detailData.category_id || 0),
    keywords: detailData.keywords || '',
    description: detailData.description || '',
    cover: detailData.cover || '',
    special_type: Number(detailData.special_type || 0),
    price: detailData.price || 0,
    original_price: detailData.original_price || 0,
    sort: Number(detailData.sort || 0),
    is_hot: Number(detailData.is_hot || 0),
    status: Number(detailData.status || 0),
    contents: contents.map((item, index) => ({
      article_id: getContentArticleId(item),
      type: item.type || (item.article && item.article.content_type) || 1,
      sort: item.sort !== undefined ? item.sort : index + 1
    }))
  }
}

function saveDetailContents(contents, successMessage) {
  if (!detailData.id) return Promise.resolve()
  const normalized = normalizeDetailContents(contents)
  contentSaving.value = true
  return specialSave(detailData.id, buildDetailSubmitData(normalized))
    .then(() => {
      ElMessage.success(successMessage)
      return Promise.all([
        handleViewDetail(detailData.id, 'contents'),
        getList()
      ])
    })
    .finally(() => {
      contentSaving.value = false
    })
}

function removeDetailContent(index) {
  ElMessageBox.confirm('确定要从该专题中移除这个内容吗？', '移除确认', {
    confirmButtonText: '确定移除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    const contents = [...(detailData.contents || [])]
    contents.splice(index, 1)
    saveDetailContents(contents, '移除成功')
  }).catch(() => {})
}

function openContentSelector() {
  selectorVisible.value = true
}

function onSelectorOpen() {
  Object.assign(selectorSearch, { title: '', content_type: '' })
  selectorPage.value = 1
  tempSelected.value = []
  loadSelectorList()
}

function onSelectorClose() {
  tempSelected.value = []
  selectorList.value = []
}

function loadSelectorList() {
  selectorLoading.value = true
  const params = {
    page: selectorPage.value,
    limit: selectorLimit.value
  }
  if (selectorSearch.title) params.title = selectorSearch.title
  if (selectorSearch.content_type !== '') params.content_type = selectorSearch.content_type

  searchSpecialContent(params)
    .then(res => {
      const data = res.data || {}
      selectorList.value = data.list || []
      selectorTotal.value = data.total || data.count || 0
    })
    .finally(() => {
      selectorLoading.value = false
    })
}

function handleSelectorSizeChange(val) {
  selectorLimit.value = val
  selectorPage.value = 1
  loadSelectorList()
}

function handleSelectorPageChange(val) {
  selectorPage.value = val
  loadSelectorList()
}

function handleSelectorSelection(selection) {
  tempSelected.value = selection
}

function isAlreadySelected(articleId) {
  const targetId = Number(articleId)
  return (detailData.contents || []).some(item => getContentArticleId(item) === targetId)
}

function isSelectable(row) {
  return !isAlreadySelected(row.id)
}

function confirmContentSelection() {
  if (tempSelected.value.length === 0) {
    ElMessage.warning('请先选择内容')
    return
  }
  const currentIds = new Set((detailData.contents || []).map(item => getContentArticleId(item)))
  const contents = [...(detailData.contents || [])]
  let addCount = 0
  tempSelected.value.forEach(article => {
    if (!currentIds.has(Number(article.id))) {
      contents.push({
        article_id: article.id,
        article: {
          id: article.id,
          title: article.title || '',
          cover: article.cover || '',
          content_type: article.content_type || 1,
          price: article.price || 0
        },
        type: article.content_type || 1,
        sort: contents.length + 1
      })
      currentIds.add(Number(article.id))
      addCount++
    }
  })
  if (addCount === 0) {
    ElMessage.info('所选内容均已在专题中')
    return
  }
  saveDetailContents(contents, `已添加 ${addCount} 个内容`).then(() => {
    selectorVisible.value = false
  })
}

function handleStatusChange(row) {
  specialSetShow(row.id)
    .then(() => {
      ElMessage.success('状态更新成功')
    })
    .catch(error => {
      row.status = row.status === 1 ? 0 : 1
      ElMessage.error(error.message || '状态更新失败')
    })
}

function handleDelete(id) {
  ElMessageBox.confirm('确定要删除该专题吗？删除后将同时移除关联内容，且无法恢复！', '删除确认', {
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    specialDelete(id).then(() => {
      ElMessage.success('删除成功')
      if (specialList.value.length === 1 && page.value > 1) {
        page.value -= 1
      }
      getList()
      fetchPendingCount()
    })
  }).catch(() => {})
}

function showAuditAction(row) {
  return isMerchantSource.value && Number(row.merchant_id) > 0 && Number(row.audit_status) === 0
}

function showForceOfflineAction(row) {
  return isMerchantSource.value && Number(row.merchant_id) > 0 && Number(row.audit_status) === 1
}

function handleForceOffline(row) {
  ElMessageBox.prompt('强制下架后，该专题将从用户端隐藏。请填写下架原因。', '强制下架确认', {
    confirmButtonText: '确定下架',
    cancelButtonText: '取消',
    inputType: 'textarea',
    inputPlaceholder: '请输入下架原因',
    inputValidator: value => {
      if (!value || !value.trim()) {
        return '请填写下架原因'
      }
      return true
    }
  }).then(({ value }) => {
    return auditSpecial(row.id, {
      audit_status: 3,
      audit_remark: value.trim()
    }).then(() => {
      ElMessage.success('强制下架成功')
      return Promise.all([
        getList(),
        fetchPendingCount()
      ])
    })
  }).catch(() => {})
}

function handleAudit(row) {
  auditTarget.value = row || {}
  Object.assign(auditForm, {
    audit_status: 1,
    audit_remark: ''
  })
  auditVisible.value = true
}

function submitAudit() {
  if ((auditForm.audit_status === 2 || auditForm.audit_status === 3) && !auditForm.audit_remark.trim()) {
    ElMessage.warning('驳回或下架时请填写审核备注')
    return
  }
  auditLoading.value = true
  auditSpecial(auditTarget.value.id, auditForm)
    .then(() => {
      ElMessage.success('审核操作成功')
      auditVisible.value = false
      return Promise.all([
        getList(),
        fetchPendingCount()
      ])
    })
    .finally(() => {
      auditLoading.value = false
    })
}

function formatMerchantName(row = {}) {
  const merchantId = Number(row.merchant_id || 0)
  if (!merchantId) {
    return '平台'
  }
  const merchant = row.merchant || {}
  if (merchant.mer_name) {
    return `${merchant.mer_name}（ID：${merchantId}）`
  }
  return `商户ID：${merchantId}`
}

function formatCategoryName(row = {}) {
  const categoryId = Number(row.category_id || 0)
  const categoryName = row.category_name || (row.category && row.category.name) || ''
  if (!categoryId) {
    return '-'
  }
  return categoryName ? `${categoryName}（ID：${categoryId}）` : `ID：${categoryId}`
}

function getSourceLabel(merchantId) {
  return Number(merchantId) > 0 ? '商户发布' : '平台发布'
}

function getAuditLabel(status) {
  const map = { 0: '待审核', 1: '已通过', 2: '已驳回', 3: '已下架' }
  return map[Number(status)] || '未知'
}

function getAuditTagType(status) {
  const map = { 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }
  return map[Number(status)] || 'info'
}

function getTypeTagColor(type) {
  return (CONTENT_TYPE_MAP[type] || {}).color || ''
}

function getTypeName(type) {
  return (CONTENT_TYPE_MAP[type] || {}).label || '未知'
}

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

onBeforeMount(() => {
  const initialTab = resolveInitialTab()
  activeTab.value = initialTab
  Object.assign(searchForm, getDefaultSearchForm(initialTab))
  persistActiveTab(initialTab)
  applyRouteDetailQuery(route.query.detail_id)
  initData()
})
</script>

<style lang="scss" scoped>
.special-curator-page {
  min-height: 100%;
  box-sizing: border-box;
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
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  max-width: 560px;
  font-size: 0.875rem;
  color: var(--ds-on-surface-variant);
  line-height: 1.5;
}

.special-work-surface {
  background: var(--ds-surface-card);
  border-radius: 12px;
  padding: 16px;
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

.special-source-tabs-wrap {
  margin-bottom: 12px;
}

.special-source-tab-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.special-source-tab-badge {
  display: inline-flex;
}

:deep(.special-source-tabs .el-tabs__header) {
  margin-bottom: 12px;
}

:deep(.special-source-tabs .el-tabs__item) {
  height: 40px;
  line-height: 40px;
  font-weight: 500;
}

:deep(.special-source-tabs .el-badge__content.is-fixed) {
  top: 10px;
  right: -12px;
}

.special-search-form {
  margin-bottom: 8px;
}

.special-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--ds-on-surface-variant);
    font-weight: 500;
    background: rgba(var(--ds-surface-low-rgb), 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--ds-surface-low) !important;
  }

  :deep(.content-count-cell .cell) {
    overflow: visible;
  }
}

.search-input {
  width: 220px;
}

.search-select-sm {
  width: 120px;
}

.cover-img {
  width: 50px;
  height: 50px;
  border-radius: 4px;
  display: block;
  margin: 0 auto;
}

.no-cover {
  color: var(--text-disabled, #c0c4cc);
  font-size: 12px;
}

.price-text {
  color: var(--text-danger, #f56c6c);
  font-weight: 600;
}

.free-text {
  color: var(--text-secondary, #909399);
}

.merchant-name,
.audit-remark {
  color: var(--ds-on-surface);
}

.content-badge-wrap {
  display: flex;
  justify-content: center;
  padding-right: 14px;
}

.content-badge {
  display: inline-flex;
  align-items: center;

  :deep(.el-badge__content) {
    background-color: var(--ds-primary);
    color: var(--ds-on-primary);
    border-color: var(--ds-surface-card);
    box-shadow: 0 4px 10px rgba(243, 65, 0, 0.18);
  }
}

.table-action-group {
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;

  :deep(.el-divider--vertical) {
    margin: 0 8px;
  }
}

.table-action-more {
  display: inline-flex;
  align-items: center;
}

.table-action-link {
  color: var(--ds-primary);
  font-size: 13px;
  line-height: 1;
  text-decoration: none;
  cursor: pointer;

  &:hover {
    color: var(--ds-primary-container);
  }

  &--danger {
    color: var(--text-danger, #f56c6c);

    &:hover {
      color: var(--text-danger, #f56c6c);
      opacity: 0.8;
    }
  }
}

.hidden-tag-selector {
  display: none;
}

.pagination-wrap {
  padding: 16px 24px;
  display: flex;
  justify-content: flex-end;
}

.mini-cover {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  display: block;
  margin: 0 auto;
}

:deep(.el-drawer__header) {
  margin-bottom: 0;
  padding: 20px 24px 12px;
  color: var(--ds-on-surface, #303133);
  font-size: 16px;
  font-weight: 600;
}

.special-detail-drawer {
  height: 100%;
  padding: 0 24px 24px;
  box-sizing: border-box;
  overflow-y: auto;
  background: var(--ds-surface-card, #fff);
}

.special-detail-hero {
  display: flex;
  gap: 16px;
  padding: 4px 0 20px;
}

.special-detail-hero__cover {
  width: 120px;
  height: 120px;
  border-radius: 10px;
  flex-shrink: 0;
  overflow: hidden;
}

.special-detail-hero__cover--empty {
  background: var(--ds-surface-low, #f5f7fa);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-disabled, #c0c4cc);
  font-size: 34px;
}

.special-detail-hero__main {
  flex: 1;
  min-width: 0;
}

.special-detail-hero__title {
  margin-bottom: 12px;
  color: var(--ds-on-surface, #303133);
  font-size: 22px;
  font-weight: 600;
  line-height: 1.4;
}

.special-detail-hero__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.special-detail-hero__desc {
  color: var(--ds-on-surface-variant, #606266);
  font-size: 14px;
  line-height: 1.8;
}

.special-detail-tabs {
  margin-top: 4px;

  :deep(.el-tabs__header) {
    margin-bottom: 18px;
  }
}

.special-detail-sections {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.special-detail-section {
  padding: 18px 20px;
  border-radius: 12px;
  background: var(--ds-surface-low, #f7f8fa);
}

.special-detail-section__title {
  display: flex;
  align-items: center;
  margin-bottom: 14px;
  color: var(--ds-on-surface, #303133);
  font-size: 15px;
  font-weight: 600;
  line-height: 1;

  &::before {
    content: '';
    width: 3px;
    height: 14px;
    margin-right: 8px;
    border-radius: 999px;
    background: var(--ds-primary, #0057c2);
  }
}

.special-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 24px;
}

.special-detail-item {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  line-height: 1.6;

  &--full {
    grid-column: 1 / -1;
  }
}

.special-detail-item__label {
  width: 86px;
  flex-shrink: 0;
  color: var(--ds-on-surface-variant, #909399);
  font-size: 13px;
}

.special-detail-item__value {
  flex: 1;
  min-width: 0;
  color: var(--ds-on-surface, #303133);
  font-size: 13px;
  word-break: break-all;
}

.special-detail-content-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 12px;
}

.special-detail-content-toolbar__text {
  color: var(--ds-on-surface-variant, #606266);
  font-size: 13px;
}

.special-detail-content-table {
  background: transparent;
  color: var(--ds-on-surface, #303133);

  &::before,
  :deep(.el-table__fixed-right::before),
  :deep(.el-table__fixed::before) {
    background: var(--ds-outline-variant, #ebeef5);
  }

  :deep(th.el-table__cell) {
    color: var(--ds-on-surface-variant, #606266);
    font-weight: 600;
    background: var(--ds-surface-low, #f7f8fa);
    border-bottom-color: var(--ds-outline-variant, #ebeef5);
  }

  :deep(tr),
  :deep(td.el-table__cell) {
    background: var(--ds-surface-card, #fff);
    border-bottom-color: var(--ds-outline-variant, #ebeef5);
  }

  :deep(.el-table__fixed-right-patch),
  :deep(.el-table__fixed-right th.el-table__cell),
  :deep(.el-table__fixed-right td.el-table__cell) {
    background: var(--ds-surface-card, #fff);
  }

  :deep(.el-table__fixed-right th.el-table__cell) {
    background: var(--ds-surface-low, #f7f8fa);
  }

  :deep(.el-table__row:hover > td.el-table__cell),
  :deep(.el-table__body tr.hover-row > td.el-table__cell) {
    background: var(--ds-surface-low, #f7f8fa) !important;
  }
}

.remove-btn {
  color: var(--text-danger, #f56c6c);
  font-size: 12px;
}

.selector-search {
  margin-bottom: 12px;
}

.selector-input {
  width: 220px;
}

.selector-select {
  width: 140px;
}

.selector-page {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.selector-tip {
  margin-right: 16px;
  color: var(--ds-on-surface-variant, #606266);
  font-size: 13px;
}

.tag-cell {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
}

.empty-text {
  color: var(--text-secondary, #909399);
  font-size: 12px;
}

:deep(.dropdown-danger) {
  color: var(--text-danger, #f56c6c);
}
</style>

<style lang="scss">
.special-action-dropdown {
  background: var(--ds-surface-card, #fff);
  border-color: var(--ds-outline-variant, #dcdfe6);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);

  &[x-placement^="bottom"] .popper__arrow {
    border-bottom-color: var(--ds-outline-variant, #dcdfe6);

    &::after {
      border-bottom-color: var(--ds-surface-card, #fff);
    }
  }

  &[x-placement^="top"] .popper__arrow {
    border-top-color: var(--ds-outline-variant, #dcdfe6);

    &::after {
      border-top-color: var(--ds-surface-card, #fff);
    }
  }

  .el-dropdown-menu__item {
    color: var(--ds-on-surface, #303133);

    &:focus,
    &:not(.is-disabled):hover {
      color: var(--ds-primary, #0057c2);
      background: var(--ds-surface-low, #f7f8fa);
    }
  }

  .el-dropdown-menu__item.dropdown-danger {
    color: var(--text-danger, #f56c6c);

    &:focus,
    &:not(.is-disabled):hover {
      color: var(--text-danger, #f56c6c);
      background: rgba(245, 108, 108, 0.12);
    }
  }
}
</style>
