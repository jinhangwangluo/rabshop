<template>
  <div class="merchant-curator-page">
    <header class="page-curator-header">
      <div class="page-curator-header__text">
        <h1 class="page-curator-title">商户管理</h1>
        <p class="page-curator-subtitle">
          管理平台商户信息，包括审核、编辑、状态控制及一键登录商户后台。
        </p>
      </div>
      <el-button
        type="primary"
        class="btn-curator-primary"
        icon="el-icon-plus"
        @click="handleAdd"
      >
        添加商户
      </el-button>
    </header>

    <div class="merchant-work-surface">
      <!-- 搜索区域 -->
      <el-form ref="searchFormRef" :model="searchForm" inline class="search-form merchant-search-form" @submit.prevent>
        <el-form-item label="商户名称">
          <el-input
            v-model="searchForm.mer_name"
            clearable
            placeholder="请输入商户名称"
            class="search-input"
            prefix-icon="el-icon-search"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="手机号">
          <el-input
            v-model="searchForm.mer_phone"
            clearable
            placeholder="请输入手机号"
            class="search-input"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item label="商户状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="searchForm.audit_status" placeholder="全部" clearable class="search-select-sm">
            <el-option label="待审核" :value="0" />
            <el-option label="已通过" :value="1" />
            <el-option label="已驳回" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" class="btn-curator-primary" icon="el-icon-search" @click="handleSearch">查询</el-button>
          <el-button class="btn-curator-reset" icon="el-icon-refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 表格区域 -->
      <el-table v-loading="loading" :data="merchantList" class="merchant-curator-table">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="头像" width="70" align="center">
          <template #default="scope">
            <el-avatar :src="scope.row.mer_avatar || scope.row.logo" :size="38" icon="el-icon-s-shop" />
          </template>
        </el-table-column>
        <el-table-column prop="mer_name" label="商户名称" min-width="130" show-overflow-tooltip />
        <el-table-column label="联系人" min-width="120" show-overflow-tooltip>
          <template #default="scope">
            <div v-if="scope.row.contact_name">{{ scope.row.contact_name }}</div>
            <div style="color:#999;font-size:12px">{{ scope.row.contact_phone || scope.row.mer_phone || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="mer_address" label="地址" min-width="150" show-overflow-tooltip />
        <el-table-column label="抽佣比例" width="90" align="center">
          <template #default="scope">
            {{ scope.row.commission_rate }}%
          </template>
        </el-table-column>
        <el-table-column label="审核状态" width="100" align="center">
          <template #default="scope">
            <el-tag
              :type="scope.row.audit_status === 1 ? 'success' : scope.row.audit_status === 2 ? 'danger' : 'warning'"
            >
              {{ scope.row.audit_status === 1 ? '已通过' : scope.row.audit_status === 2 ? '已驳回' : '待审核' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="推荐" width="70" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.is_recommend === 1 ? 'success' : 'info'">
              {{ scope.row.is_recommend === 1 ? '是' : '否' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="营业状态" width="100" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.business_status === 1 ? 'success' : 'info'">
              {{ scope.row.business_status === 1 ? '营业中' : '休息中' }}
            </el-tag>
          </template>
        </el-table-column>
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
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="scope">
            <a @click="handleLoginAs(scope.row)">登录</a>
            <el-divider direction="vertical" />
            <a @click="handleEdit(scope.row.id)">编辑</a>
            <el-divider direction="vertical" />
            <a @click="handleDetail(scope.row.id)">详情</a>
            <el-divider direction="vertical" />
            <a class="danger-link" @click="handleDelete(scope.row.id)">删除</a>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrap">
        <el-pagination
          class="merchant-curator-pagination"
          :current-page="page"
          :page-sizes="[15, 20, 50, 100]"
          :page-size="limit"
          layout="total, sizes, prev, pager, next, jumper"
          :total="total"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </div>

    <!-- 添加/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="780px"
      :close-on-click-modal="false"
      @close="resetForm"
    >
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="110px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="商户名称" prop="mer_name">
              <el-input v-model="editForm.mer_name" placeholder="请输入商户名称" maxlength="50" show-word-limit />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商户电话" prop="mer_phone">
              <el-input v-model="editForm.mer_phone" placeholder="请输入商户电话" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系人姓名">
              <el-input v-model="editForm.contact_name" placeholder="请输入联系人姓名" maxlength="30" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系人电话">
              <el-input v-model="editForm.contact_phone" placeholder="请输入联系人电话" maxlength="20" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="商户地址">
              <el-input v-model="editForm.mer_address" placeholder="请输入商户地址" maxlength="255" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商户Logo">
              <Upload v-model="editForm.logo" :limit="1" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商户头像">
              <Upload v-model="editForm.mer_avatar" :limit="1" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商户店面图">
              <Upload v-model="editForm.mer_banner" :limit="1" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商户街景图">
              <Upload v-model="editForm.mer_street_view" :limit="1" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="商户描述">
              <el-input
                v-model="editForm.description"
                type="textarea"
                :rows="2"
                placeholder="请输入商户描述"
                maxlength="500"
                show-word-limit
              />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="店铺公告">
              <el-input
                v-model="editForm.mer_notice"
                type="textarea"
                :rows="2"
                placeholder="请输入店铺公告"
                maxlength="1000"
                show-word-limit
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="店铺关键字">
              <el-input
                v-model="editForm.mer_keyword"
                placeholder="请输入店铺关键字"
                maxlength="100"
                show-word-limit
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="纬度">
              <el-input-number
                v-model="editForm.lat"
                :precision="6"
                :step="0.000001"
                style="width:100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="经度">
              <el-input-number
                v-model="editForm.lng"
                :precision="6"
                :step="0.000001"
                style="width:100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="商品审核">
              <el-radio-group v-model="editForm.product_audit_mode">
                <el-radio :label="1">平台审核</el-radio>
                <el-radio :label="2">免审核</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="直播间审核">
              <el-radio-group v-model="editForm.live_audit_mode">
                <el-radio :label="1">平台审核</el-radio>
                <el-radio :label="2">免审核</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="直播商品审核">
              <el-radio-group v-model="editForm.live_product_audit_mode">
                <el-radio :label="1">平台审核</el-radio>
                <el-radio :label="2">免审核</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="内容审核">
              <el-radio-group v-model="editForm.content_audit_mode">
                <el-radio :label="1">平台审核</el-radio>
                <el-radio :label="2">免审核</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="营业时间">
              <div class="hours-editor">
                <div
                  v-for="(hours, index) in editForm.business_hours"
                  :key="index"
                  class="hours-item"
                >
                  <el-checkbox
                    v-model="hours.is_all_day"
                    @change="handleAllDayChange(index)"
                  >全日营业</el-checkbox>

                  <el-time-picker
                    v-model="hours.start"
                    format="HH:mm"
                    value-format="HH:mm"
                    placeholder="开始时间"
                    :disabled="hours.is_all_day"
                    class="hours-time"
                  />
                  <el-time-picker
                    v-model="hours.end"
                    format="HH:mm"
                    value-format="HH:mm"
                    placeholder="结束时间"
                    :disabled="hours.is_all_day"
                    class="hours-time"
                  />

                  <el-button
                    type="text"
                    icon="el-icon-delete"
                    class="hours-remove"
                    @click="removeBusinessHours(index)"
                  >删除</el-button>
                </div>

                <el-button
                  type="primary"
                  plain
                  icon="el-icon-plus"
                  size="mini"
                  @click="addBusinessHours"
                >添加营业时间段</el-button>
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="资质信息">
              <Upload v-model="editForm.qualification" :limit="10" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="抽佣比例(%)" prop="commission_rate">
              <el-input-number
                v-model="editForm.commission_rate"
                :min="0"
                :max="100"
                :precision="2"
                style="width:100%"
                placeholder="0.00"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结算周期(天)" prop="settlement_cycle">
              <el-input-number
                v-model="editForm.settlement_cycle"
                :min="1"
                :precision="0"
                style="width:100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序">
              <el-input-number v-model="editForm.sort" :min="0" :precision="0" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否推荐">
              <el-radio-group v-model="editForm.is_recommend">
                <el-radio :label="1">是</el-radio>
                <el-radio :label="0">否</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="营业状态">
              <el-radio-group v-model="editForm.business_status">
                <el-radio :label="1">营业中</el-radio>
                <el-radio :label="0">休息中</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态">
              <el-radio-group v-model="editForm.status">
                <el-radio :label="1">启用</el-radio>
                <el-radio :label="0">禁用</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <template v-if="!editForm.id">
            <el-col :span="12">
              <el-form-item label="登录账号" prop="account">
                <el-input v-model="editForm.account" placeholder="请输入登录账号" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="登录密码" prop="password">
                <el-input v-model="editForm.password" type="password" placeholder="请输入登录密码" show-password />
              </el-form-item>
            </el-col>
          </template>
        </el-row>
      </el-form>
      <template #footer>
        <span>
          <el-button @click="dialogVisible = false">取 消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
        </span>
      </template>
    </el-dialog>

    <!-- 商户详情抽屉 -->
    <el-drawer
      v-model="detailVisible"
      title="商户详情"
      direction="rtl"
      size="880px"
      :wrapper-closable="true"
      custom-class="merchant-drawer"
    >
      <div v-if="detailData" v-loading="detailLoading" class="merchant-detail">
        <!-- Hero：背景图 + 头像 + 状态标签 + 顶部操作 -->
        <div class="merchant-hero">
          <div
            class="merchant-hero__bg"
            :style="{ backgroundImage: heroBgUrl ? `url(${heroBgUrl})` : 'none' }"
          />
          <div class="merchant-hero__overlay" />

          <div class="merchant-hero__content">
            <div class="merchant-hero__left">
              <el-avatar
                :src="detailData.mer_avatar || detailData.logo"
                :size="44"
                icon="el-icon-s-shop"
                class="merchant-hero__avatar"
              />
              <div class="merchant-hero__text">
                <div class="merchant-hero__name">{{ detailData.mer_name }}</div>
                <div class="merchant-hero__tags">
                  <el-tag :type="detailData.status === 1 ? 'success' : 'danger'" size="mini">
                    {{ detailData.status === 1 ? '正常' : '禁用' }}
                  </el-tag>
                  <el-tag
                    :type="detailData.business_status === 1 ? 'success' : 'info'"
                    size="mini"
                    class="ml6"
                  >
                    {{ detailData.business_status === 1 ? '营业中' : '休息中' }}
                  </el-tag>
                  <el-tag
                    :type="detailData.audit_status === 1 ? 'success' : detailData.audit_status === 2 ? 'danger' : 'warning'"
                    size="mini"
                    class="ml6"
                  >
                    {{
                      detailData.audit_status === 1
                        ? '已通过'
                        : detailData.audit_status === 2
                          ? '已驳回'
                          : '待审核'
                    }}
                  </el-tag>
                </div>
              </div>
            </div>

            <div class="merchant-hero__right">
              <el-button
                type="primary"
                size="mini"
                icon="el-icon-edit"
                class="merchant-pwd-toggle-btn"
                @click="togglePwdCard"
              >
                修改密码
              </el-button>
            </div>
          </div>
        </div>

        <!-- 修改管理员密码（顶部按钮展开/收起） -->
        <transition name="merchant-pwd-fade">
          <div v-if="showPwdCard" class="merchant-pwd-card">
            <el-form ref="pwdFormRef" :model="pwdForm" :rules="pwdRules" label-width="90px">
              <el-form-item label="新密码" prop="new_password">
                <el-input
                  v-model="pwdForm.new_password"
                  type="password"
                  show-password
                  placeholder="请输入新密码（至少6位）"
                />
              </el-form-item>
              <el-form-item label="确认密码" prop="confirm_password">
                <el-input
                  v-model="pwdForm.confirm_password"
                  type="password"
                  show-password
                  placeholder="请再次输入新密码"
                />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="pwdLoading" @click="handleChangePassword">
                  确认修改
                </el-button>
              </el-form-item>
            </el-form>
          </div>
        </transition>

        <!-- 基础信息 -->
        <div class="merchant-section">
          <div class="merchant-section__title">
            <span class="merchant-section__marker" />
            基础信息
          </div>
          <div class="merchant-info-grid">
            <div class="merchant-info-item">
              <span class="merchant-info-label">商户全称</span>
              <span class="merchant-info-value">{{ detailData.mer_name || '-' }}</span>
            </div>
            <div class="merchant-info-item">
              <span class="merchant-info-label">登录账号</span>
              <span class="merchant-info-value">{{ detailData.account || '-' }}</span>
            </div>
            <div class="merchant-info-item">
              <span class="merchant-info-label">联系人</span>
              <span class="merchant-info-value">
                {{ detailData.contact_name || '-' }} {{ detailData.contact_phone || '' }}
              </span>
            </div>
            <div class="merchant-info-item">
              <span class="merchant-info-label">经营地址</span>
              <span class="merchant-info-value">{{ detailData.mer_address || '-' }}</span>
            </div>
            <div class="merchant-info-item merchant-info-item--full">
              <span class="merchant-info-label">店铺公告</span>
              <span class="merchant-info-value">{{ detailData.mer_notice || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- 业务详述 -->
        <div class="merchant-section">
          <div class="merchant-section__title">
            <span class="merchant-section__marker" />
            业务详述
          </div>
          <div class="merchant-description">
            {{ detailData.description || '-' }}
          </div>

          <el-row :gutter="12" class="merchant-metric-row">
            <el-col :span="8">
              <div class="merchant-metric">
                <div class="merchant-metric__label">注册资金</div>
                <div class="merchant-metric__value">
                  ¥{{ registerCapitalDisplay }}
                </div>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="merchant-metric">
                <div class="merchant-metric__label">在职员工</div>
                <div class="merchant-metric__value">
                  {{ detailData.total_orders || 0 }} 人
                </div>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="merchant-metric">
                <div class="merchant-metric__label">信用等级</div>
                <div class="merchant-metric__value">
                  <span class="merchant-stars">
                    <LegacyIcon
                      v-for="n in 5"
                      :key="n"
                      :name="n <= creditStars ? 'el-icon-star-on' : 'el-icon-star-off'"
                      :class="n <= creditStars ? 'merchant-star--on' : 'merchant-star--off'"
                    />
                  </span>
                </div>
              </div>
            </el-col>
          </el-row>
        </div>

        <!-- 资质与案例 -->
        <div class="merchant-section">
          <div class="merchant-section__title">
            <span class="merchant-section__marker" />
            资质与案例
          </div>
          <div class="merchant-cases-grid">
            <template v-if="qualificationImages.length">
              <div
                v-for="(img, idx) in qualificationImages.slice(0, 3)"
                :key="idx"
                class="merchant-case-card"
              >
                <img :src="img" class="merchant-case-img" alt="case">
              </div>
            </template>
            <div v-else class="merchant-empty">
              暂无资质/案例
            </div>
          </div>
        </div>

        <!-- 账户状态 -->
        <div class="merchant-bottom-grid">
          <div class="merchant-bottom-card">
            <div class="merchant-bottom-card__title">
              <span class="merchant-section__marker" />
              账户状态
            </div>
            <div class="merchant-bottom-card__content">
              <div class="merchant-status-row">
                <span class="merchant-status-dot" :class="{ 'merchant-status-dot--on': detailData.business_status === 1 }" />
                <span class="merchant-status-text">
                  {{ detailData.business_status === 1 ? '营业中' : '休息中' }}
                </span>
              </div>
              <div class="merchant-status-tags">
                <el-tag
                  :type="detailData.status === 1 ? 'success' : 'danger'"
                  size="mini"
                >
                  {{ detailData.status === 1 ? '启用' : '禁用' }}
                </el-tag>
                <el-tag
                  :type="detailData.audit_status === 1 ? 'success' : detailData.audit_status === 2 ? 'danger' : 'warning'"
                  size="mini"
                  class="ml6"
                >
                  {{
                    detailData.audit_status === 1
                      ? '已通过'
                      : detailData.audit_status === 2
                        ? '已驳回'
                        : '待审核'
                  }}
                </el-tag>
              </div>

              <div class="merchant-muted">
                审核时间：{{ detailData.audit_time || '-' }}；创建时间：{{ detailData.create_time || '-' }}
              </div>
              <div v-if="detailData.audit_remark" class="merchant-muted merchant-muted--remark">
                审核备注：{{ detailData.audit_remark }}
              </div>
            </div>
          </div>
        </div>

        <!-- 审核机制 -->
        <div class="merchant-section merchant-section--emphasis">
          <div class="merchant-section__title">
            <span class="merchant-section__marker" />
            审核机制
          </div>
          <div class="merchant-mechanism-grid">
            <div class="merchant-mechanism-item">
              <div class="merchant-mechanism-label">商品审核</div>
              <el-tag :type="(Number(detailData.product_audit_mode) || 1) === 1 ? 'success' : 'info'" size="mini">
                {{ (Number(detailData.product_audit_mode) || 1) === 1 ? '平台审核' : '免审核' }}
              </el-tag>
            </div>
            <div class="merchant-mechanism-item">
              <div class="merchant-mechanism-label">直播间审核</div>
              <el-tag :type="detailData.live_audit_mode === 1 ? 'success' : 'info'" size="mini">
                {{ detailData.live_audit_mode === 1 ? '平台审核' : '免审核' }}
              </el-tag>
            </div>
            <div class="merchant-mechanism-item">
              <div class="merchant-mechanism-label">直播商品审核</div>
              <el-tag :type="detailData.live_product_audit_mode === 1 ? 'success' : 'info'" size="mini">
                {{ detailData.live_product_audit_mode === 1 ? '平台审核' : '免审核' }}
              </el-tag>
            </div>
            <div class="merchant-mechanism-item">
              <div class="merchant-mechanism-label">内容审核</div>
              <el-tag :type="detailData.content_audit_mode === 1 ? 'success' : 'info'" size="mini">
                {{ detailData.content_audit_mode === 1 ? '平台审核' : '免审核' }}
              </el-tag>
            </div>
          </div>
        </div>

        <!-- 经营配置 -->
        <div class="merchant-section merchant-section--emphasis">
          <div class="merchant-section__title">
            <span class="merchant-section__marker" />
            经营配置
          </div>
          <div class="merchant-config-lines">
            <div class="merchant-line">
              <span>抽佣比例</span>
              <span class="merchant-line__value">{{ detailData.commission_rate }}%</span>
            </div>
            <div class="merchant-line">
              <span>结算周期</span>
              <span class="merchant-line__value">{{ detailData.settlement_cycle }} 天</span>
            </div>
            <div class="merchant-line">
              <span>是否推荐</span>
              <span class="merchant-line__value">
                <el-tag :type="detailData.is_recommend === 1 ? 'success' : 'info'" size="mini">
                  {{ detailData.is_recommend === 1 ? '是' : '否' }}
                </el-tag>
              </span>
            </div>
            <div class="merchant-line">
              <span>排序</span>
              <span class="merchant-line__value">{{ detailData.sort }}</span>
            </div>
            <div class="merchant-line merchant-line--wrap">
              <span>店铺关键字</span>
              <span class="merchant-line__value">{{ detailData.mer_keyword || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- 地址提示 -->
        <div class="merchant-address-tip">
          <div class="merchant-address-tip__row">
            <LegacyIcon name="el-icon-location-outline" class=" merchant-address-tip__icon" />
            <div class="merchant-address-tip__text">
              {{ detailData.mer_address || '-' }}
              <div v-if="detailData.lat && detailData.lng" class="merchant-address-tip__sub">
                坐标：{{ detailData.lat }}, {{ detailData.lng }}
              </div>
            </div>
            <el-button size="mini" type="text" class="merchant-address-tip__copy" @click="handleCopyAddress">
              复制
            </el-button>
          </div>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import setting from '@/settings'
import {
  getMerchantList,
  merchantRead,
  merchantSave,
  merchantUpdate,
  merchantDelete,
  merchantSetStatus,
  merchantLoginAs,
  merchantChangePassword
} from '@/api/merchant'

defineOptions({
  name: 'MerchantList'
})

const { proxy } = getCurrentInstance()
const message = proxy.$message
const confirm = proxy.$confirm
const route = useRoute()

const createSearchForm = () => ({
  mer_name: '',
  mer_phone: '',
  status: '',
  audit_status: ''
})

const createEditForm = () => ({
  id: 0,
  mer_name: '',
  mer_phone: '',
  mer_address: '',
  contact_name: '',
  contact_phone: '',
  logo: '',
  mer_avatar: '',
  mer_banner: '',
  mer_street_view: '',
  description: '',
  mer_notice: '',
  mer_keyword: '',
  lat: 0,
  lng: 0,
  mer_class: 2,
  product_audit_mode: 1,
  live_audit_mode: 1,
  live_product_audit_mode: 1,
  content_audit_mode: 1,
  business_hours: [],
  qualification: [],
  commission_rate: 0,
  settlement_cycle: 7,
  sort: 0,
  is_recommend: 0,
  business_status: 1,
  status: 1,
  account: '',
  password: ''
})

const createPwdForm = () => ({
  new_password: '',
  confirm_password: ''
})

const loading = ref(false)
const merchantList = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(15)
const searchFormRef = ref(null)
const searchForm = reactive(createSearchForm())
const dialogVisible = ref(false)
const dialogTitle = ref('添加商户')
const submitLoading = ref(false)
const editFormRef = ref(null)
const editForm = reactive(createEditForm())
const detailVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const routeDetailId = ref('')
const showPwdCard = ref(false)
const pwdLoading = ref(false)
const pwdFormRef = ref(null)
const pwdForm = reactive(createPwdForm())

const editRules = {
  mer_name: [{ required: true, message: '请输入商户名称', trigger: 'blur' }],
  mer_phone: [{ pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }],
  commission_rate: [{ type: 'number', min: 0, max: 100, message: '请输入0-100之间的数字', trigger: 'change' }],
  settlement_cycle: [{ type: 'number', min: 1, message: '结算周期至少1天', trigger: 'change' }],
  account: [{
    validator: (rule, value, callback) => {
      if (!editForm.id && !value) {
        callback(new Error('请输入登录账号'))
      } else {
        callback()
      }
    },
    trigger: 'blur'
  }],
  password: [{
    validator: (rule, value, callback) => {
      if (!editForm.id && !value) {
        callback(new Error('请输入登录密码'))
      } else if (!editForm.id && value.length < 6) {
        callback(new Error('密码至少6位'))
      } else {
        callback()
      }
    },
    trigger: 'blur'
  }]
}

const pwdRules = {
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于6位', trigger: 'blur' }
  ],
  confirm_password: [{
    validator: (rule, value, callback) => {
      if (!value) return callback(new Error('请再次输入密码'))
      if (value !== pwdForm.new_password) return callback(new Error('两次密码不一致'))
      callback()
    },
    trigger: 'blur'
  }]
}

const registerCapitalDisplay = computed(() => formatMoneyToW(detailData.value ? detailData.value.total_sales : 0))
const creditStars = computed(() => {
  const status = detailData.value ? Number(detailData.value.audit_status) : 0
  if (status === 1) return 5
  if (status === 2) return 2
  return 3
})
const qualificationImages = computed(() => {
  if (!detailData.value) return []
  return extractQualificationImages(detailData.value.qualification)
})
const heroBgUrl = computed(() => {
  if (!detailData.value) return ''
  return detailData.value.mer_banner || detailData.value.mer_street_view || ''
})

function getList() {
  loading.value = true
  getMerchantList({ page: page.value, limit: limit.value, ...searchForm })
    .then(res => {
      merchantList.value = res.data.list || []
      total.value = res.data.total || 0
    })
    .finally(() => {
      loading.value = false
    })
}

function handleSearch() {
  page.value = 1
  getList()
}

function handleReset() {
  Object.assign(searchForm, createSearchForm())
  page.value = 1
  searchFormRef.value?.clearValidate?.()
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
  dialogTitle.value = '添加商户'
  Object.assign(editForm, createEditForm())
  dialogVisible.value = true
}

function handleEdit(id) {
  merchantRead(id).then(res => {
    const d = res.data || {}
    Object.assign(editForm, createEditForm(), {
      id: d.id || 0,
      mer_name: d.mer_name || '',
      mer_phone: d.mer_phone || '',
      mer_address: d.mer_address || '',
      contact_name: d.contact_name || '',
      contact_phone: d.contact_phone || '',
      logo: d.logo || '',
      mer_avatar: d.mer_avatar || '',
      mer_banner: d.mer_banner || '',
      mer_street_view: d.mer_street_view || '',
      description: d.description || '',
      mer_notice: d.mer_notice || '',
      mer_keyword: d.mer_keyword || '',
      lat: Number(d.lat) || 0,
      lng: Number(d.lng) || 0,
      mer_class: Number(d.mer_class) || 2,
      product_audit_mode: Number(d.product_audit_mode) || 1,
      live_audit_mode: Number(d.live_audit_mode) || 1,
      live_product_audit_mode: Number(d.live_product_audit_mode) || 1,
      content_audit_mode: Number(d.content_audit_mode) || 1,
      business_hours: normalizeBusinessHours(safeParseJsonField(d.business_hours, [])),
      qualification: extractQualificationImages(d.qualification),
      commission_rate: Number(d.commission_rate) || 0,
      settlement_cycle: Number(d.settlement_cycle) || 7,
      sort: Number(d.sort) || 0,
      is_recommend: Number(d.is_recommend) || 0,
      business_status: Number(d.business_status) || 1,
      status: Number(d.status) || 0
    })
    dialogTitle.value = '编辑商户'
    dialogVisible.value = true
  })
}

function handleDetail(id) {
  detailLoading.value = true
  detailVisible.value = true
  detailData.value = null
  showPwdCard.value = false
  Object.assign(pwdForm, createPwdForm())
  merchantRead(id)
    .then(res => {
      detailData.value = res.data || null
    })
    .finally(() => {
      detailLoading.value = false
    })
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
  handleDetail(value)
}

function togglePwdCard() {
  const next = !showPwdCard.value
  showPwdCard.value = next
  if (next) {
    nextTick(() => {
      pwdFormRef.value?.clearValidate?.()
    })
  }
}

function handleCopyAddress() {
  const address = detailData.value && detailData.value.mer_address ? String(detailData.value.mer_address) : ''
  if (!address) {
    message.warning('地址为空，无法复制')
    return
  }

  const doFallbackCopy = () => {
    try {
      const textarea = document.createElement('textarea')
      textarea.value = address
      textarea.style.position = 'fixed'
      textarea.style.top = '-9999px'
      textarea.style.left = '-9999px'
      document.body.appendChild(textarea)
      textarea.focus()
      textarea.select()
      const res = document.execCommand('copy')
      document.body.removeChild(textarea)
      if (res) message.success('复制成功')
      else message.error('复制失败')
    } catch (e) {
      message.error('复制失败')
    }
  }

  if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(address).then(() => {
      message.success('复制成功')
    }).catch(() => {
      doFallbackCopy()
    })
  } else {
    doFallbackCopy()
  }
}

function handleLoginAs(row) {
  if (row.audit_status !== 1) {
    message.warning('该商户未通过审核，无法登录')
    return
  }
  if (!row.status) {
    message.warning('该商户已被禁用，无法登录')
    return
  }
  confirm(`确定要以「${row.mer_name}」商户身份登录商户后台吗？`, '一键登录', { type: 'info' }).then(() => {
    merchantLoginAs(row.id).then(res => {
      const merchantUrl = buildMerchantLoginUrl(res.data || {})
      if (merchantUrl) {
        window.open(merchantUrl, '_blank')
      }
    }).catch(() => {})
  }).catch(() => {})
}

function buildMerchantLoginUrl(data) {
  if (data.login_url) {
    return String(data.login_url)
  }

  const token = data.token || ''
  if (!token) {
    message.error('商户登录地址生成失败')
    return ''
  }

  const basePath = data.merchant_url || resolveMerchantBasePath(data.merchant_ui_prefix)
  return `${trimTrailingSlash(basePath)}/login?token=${encodeURIComponent(token)}`
}

function resolveMerchantBasePath(prefix) {
  const value = prefix || setting.merchantUrl || 'merchant'
  const text = String(value).trim()
  if (/^https?:\/\//i.test(text)) {
    return trimTrailingSlash(text)
  }

  const path = text.replace(/^\/+|\/+$/g, '') || 'merchant'
  return `/${path}`
}

function trimTrailingSlash(value) {
  return String(value || '').replace(/\/+$/, '')
}

async function handleChangePassword() {
  if (!pwdFormRef.value) return
  const valid = await pwdFormRef.value.validate().catch(() => false)
  if (!valid) return
  const merchantId = detailData.value && detailData.value.id
  if (!merchantId) {
    message.error('商户信息异常')
    return
  }
  pwdLoading.value = true
  merchantChangePassword(merchantId, { new_password: pwdForm.new_password })
    .then(res => {
      message.success(res.message || '密码修改成功')
      Object.assign(pwdForm, createPwdForm())
      pwdFormRef.value?.clearValidate?.()
    })
    .finally(() => {
      pwdLoading.value = false
    })
}

function formatMoneyToW(value) {
  const num = Number(value || 0)
  if (!Number.isFinite(num)) return '0'
  if (num < 10000) return `${num}`.replace(/\.0+$/, '')
  const w = num / 10000
  const fixed = Number.isInteger(w) ? 0 : 1
  return `${w.toFixed(fixed)}w`
}

function extractQualificationImages(qualification) {
  if (!qualification) return []

  if (typeof qualification === 'string') {
    try {
      return extractQualificationImages(JSON.parse(qualification))
    } catch (e) {
      return []
    }
  }

  if (Array.isArray(qualification)) {
    return qualification
      .filter(v => v !== null && v !== undefined && String(v).trim())
      .map(v => String(v))
  }

  if (typeof qualification === 'object') {
    const images = []
    const pushValue = v => {
      if (!v) return
      if (Array.isArray(v)) {
        v.forEach(pushValue)
        return
      }
      if (typeof v === 'string') {
        const t = v.trim()
        if (t) images.push(t)
        return
      }
      if (typeof v === 'object') {
        if (v.url) pushValue(v.url)
        if (v.path) pushValue(v.path)
      }
    }

    pushValue(qualification.business_license)
    pushValue(qualification.legal_person_idcard)
    pushValue(qualification.bank_account)
    pushValue(qualification.other_certs)

    Object.keys(qualification).forEach(k => {
      const v = qualification[k]
      if (typeof v === 'string' || Array.isArray(v)) pushValue(v)
    })

    return images
  }

  return []
}

function addBusinessHours() {
  if (!Array.isArray(editForm.business_hours)) {
    editForm.business_hours = []
  }
  editForm.business_hours.push({
    start: '09:00',
    end: '18:00',
    is_all_day: false
  })
}

function removeBusinessHours(index) {
  if (!Array.isArray(editForm.business_hours)) return
  editForm.business_hours.splice(index, 1)
}

function handleAllDayChange(index) {
  const item = editForm.business_hours && editForm.business_hours[index]
  if (!item) return

  if (item.is_all_day) {
    item.start = ''
    item.end = ''
  } else {
    if (!item.start) item.start = '09:00'
    if (!item.end) item.end = '18:00'
  }
}

function safeParseJsonField(value, fallback) {
  try {
    return parseJsonField(value, fallback)
  } catch (e) {
    return fallback
  }
}

function parseJsonField(value, fallback) {
  if (value === null || value === undefined) return fallback
  if (typeof value === 'object') return value
  const t = String(value).trim()
  if (!t) return fallback
  try {
    return JSON.parse(t)
  } catch (e) {
    throw new Error('invalid_json')
  }
}

function normalizeTimeValue(value) {
  if (value instanceof Date) {
    const pad = n => String(n).padStart(2, '0')
    return `${pad(value.getHours())}:${pad(value.getMinutes())}`
  }
  return value
}

function normalizeBusinessHours(businessHours) {
  if (!Array.isArray(businessHours)) return []
  return businessHours.map(item => {
    const h = item || {}
    const isAllDay = !!h.is_all_day
    if (isAllDay) {
      return { start: '', end: '', is_all_day: true }
    }
    return {
      start: normalizeTimeValue(h.start || ''),
      end: normalizeTimeValue(h.end || ''),
      is_all_day: false
    }
  })
}

async function handleSubmit() {
  if (!editFormRef.value) return
  const valid = await editFormRef.value.validate().catch(() => false)
  if (!valid) return
  submitLoading.value = true
  const submitData = { ...editForm }
  try {
    submitData.business_hours = parseJsonField(submitData.business_hours, [])
    submitData.qualification = extractQualificationImages(parseJsonField(submitData.qualification, []))
    submitData.business_hours = normalizeBusinessHours(submitData.business_hours)
  } catch (e) {
    submitLoading.value = false
    message.error('营业时间/资质信息格式不正确')
    return
  }
  if (submitData.id) {
    delete submitData.account
    delete submitData.password
  }
  const api = submitData.id
    ? merchantUpdate(submitData.id, submitData)
    : merchantSave(submitData)
  api.then(res => {
    message.success(res.message || '操作成功')
    dialogVisible.value = false
    getList()
  }).finally(() => {
    submitLoading.value = false
  })
}

function handleDelete(id) {
  confirm('确定要删除该商户吗？', '提示', { type: 'warning' }).then(() => {
    merchantDelete(id).then(res => {
      message.success(res.message || '删除成功')
      getList()
    })
  }).catch(() => {})
}

function handleStatusChange(row) {
  merchantSetStatus(row.id, row.status).then(res => {
    message.success(res.message || '状态更新成功')
  }).catch(() => {
    row.status = row.status === 1 ? 0 : 1
  })
}

function resetForm() {
  Object.assign(editForm, createEditForm())
  editFormRef.value?.clearValidate?.()
}

function handleRouteInit() {
  const { mer_name: routeMerName = '', audit_status: routeAuditStatus = '' } = route.query
  if (routeMerName) {
    searchForm.mer_name = String(routeMerName)
  }
  if (routeAuditStatus !== '') {
    searchForm.audit_status = Number(routeAuditStatus)
  }
  applyRouteDetailQuery(route.query.detail_id)
  getList()
}

watch(() => route.query.detail_id, value => {
  applyRouteDetailQuery(value)
})

onBeforeMount(() => {
  handleRouteInit()
})
</script>

<style lang="scss" scoped>
@import "@/styles/variables.scss";

// ===== 页面头部样式 =====
.merchant-curator-page {
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
  color: var(--curator-on-surface);
  letter-spacing: 0.02em;
}

.page-curator-subtitle {
  margin: 0;
  font-size: 0.875rem;
  color: var(--curator-on-surface-variant);
  line-height: 1.5;
  max-width: 520px;
}

.merchant-work-surface {
  background: var(--curator-surface-card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
}

:deep(.btn-curator-primary.el-button--primary) {
  border: none;
  border-radius: 6px;
  color: var(--curator-on-primary);
  font-weight: 500;
  padding-left: 18px;
  padding-right: 18px;
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.btn-curator-reset) {
  border-radius: 6px;
  background: var(--curator-surface-card);
  color: var(--curator-primary);
  border: 1px solid var(--curator-primary);

  &:hover,
  &:focus {
    background: rgba(0, 87, 194, 0.06);
    color: var(--curator-primary-container);
    border-color: var(--curator-primary-container);
  }
}

.merchant-search-form {
  margin-bottom: 8px;
}

.merchant-curator-table {
  margin-top: 8px;

  :deep(.el-table__header th) {
    color: var(--curator-on-surface-variant);
    font-weight: 500;
    background: rgba(242, 244, 247, 0.5);
  }

  :deep(.el-table__row:hover > td) {
    background: var(--curator-surface-low) !important;
  }
}

.merchant-curator-pagination {
  :deep(.el-pager li.active) {
    background: var(--curator-primary);
    color: var(--curator-on-primary);
    border-radius: 4px;
  }

  :deep(.btn-next),
  :deep(.btn-prev),
  :deep(.el-pager li) {
    &:hover {
      color: var(--curator-primary);
    }
  }
}

.search-input { width: 180px; }
.search-select-sm { width: 100px; }
.pagination-wrap { padding: 16px 0 0; display: flex; justify-content: flex-end; }
.danger-link { color: #f56c6c; &:hover { opacity: 0.8; } }
.ml6 { margin-left: 6px; }

// ===== Merchant Detail（按 material/stitch/DESIGN.md 的"无硬分割线"风格实现）=====
:deep(.merchant-drawer) {
  background: var(--curator-background) !important;

  .el-drawer__header {
    background: var(--curator-background);
    border-bottom: none !important;
    padding: 16px 20px;
    margin-bottom: 0;
    color: var(--curator-on-surface-variant);
  }

  .el-drawer__body {
    padding: 0;
    background: var(--curator-background);
    overflow-y: auto;
  }
}

.merchant-detail {
  padding: 16px 20px 24px;
  height: 100%;
  overflow-y: auto;
}

.merchant-hero {
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  margin-bottom: 12px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  background: var(--curator-surface-low);
}

.merchant-hero__bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  transform: scale(1.03);
  filter: saturate(1.05);
}

.merchant-hero__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    rgba(0, 0, 0, 0.14) 0%,
    rgba(0, 0, 0, 0.06) 35%,
    rgba(247, 249, 252, 0.35) 65%,
    rgba(247, 249, 252, 0.98) 100%
  );
}

.merchant-hero__content {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  padding: 12px 12px 10px;
}

.merchant-hero__left {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.merchant-hero__avatar {
  flex-shrink: 0;
  background: var(--curator-surface-card);
  box-shadow: 0 2px 12px rgba(0, 87, 194, 0.12);
}

.merchant-hero__text {
  min-width: 0;
}

.merchant-hero__name {
  font-size: 15px;
  font-weight: 700;
  color: var(--curator-on-surface);
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.merchant-hero__tags {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 2px;
}

.merchant-hero__right {
  flex-shrink: 0;
}

:deep(.merchant-pwd-toggle-btn.el-button--primary) {
  background: linear-gradient(180deg, var(--curator-primary-container) 0%, var(--curator-primary) 100%);
  border: none;
  border-radius: 8px;
  color: var(--curator-on-primary);
  box-shadow: 0 2px 8px rgba(0, 87, 194, 0.2);
}

:deep(.merchant-pwd-toggle-btn.el-button--mini) {
  height: 28px;
  padding: 5px 10px;
}

:deep(.merchant-hero__tags .el-tag--mini) {
  height: 18px;
  line-height: 16px;
  padding: 0 6px;
  font-size: 12px;
}

:deep(.merchant-pwd-toggle-btn.el-button--primary:hover),
:deep(.merchant-pwd-toggle-btn.el-button--primary:focus) {
  background: linear-gradient(180deg, var(--curator-primary-container) 0%, var(--curator-primary) 100%);
  opacity: 0.95;
}

// 修改密码展开/收起动画
.merchant-pwd-fade-enter-active,
.merchant-pwd-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.merchant-pwd-fade-enter,
.merchant-pwd-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.merchant-pwd-card {
  background: var(--curator-surface-card);
  border-radius: 16px;
  padding: 16px 20px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 16px;
}

.merchant-section {
  background: var(--curator-surface-card);
  border-radius: 16px;
  padding: 16px 20px;
  box-shadow: 0 1px 2px rgba(25, 28, 30, 0.04);
  margin-bottom: 14px;
}

.merchant-section__title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
  font-size: 13px;
  font-weight: 700;
  color: var(--curator-on-surface-variant);
}

.merchant-section__marker {
  width: 3px;
  height: 14px;
  border-radius: 6px;
  background: var(--curator-primary);
  flex-shrink: 0;
}

.merchant-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  column-gap: 24px;
  row-gap: 10px;
}

.merchant-info-item {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.merchant-info-label {
  font-size: 12px;
  color: var(--curator-on-surface-variant);
}

.merchant-info-value {
  margin-top: 6px;
  font-size: 13px;
  color: var(--curator-on-surface);
  word-break: break-word;
}

.merchant-info-item--full {
  grid-column: 1 / -1;
}

.merchant-description {
  font-size: 13px;
  color: var(--curator-on-surface);
  line-height: 1.7;
  margin-bottom: 14px;
}

.merchant-metric-row {
  margin-top: 2px;
}

.merchant-metric {
  background: var(--curator-surface-low);
  border-radius: 12px;
  padding: 14px 12px;
  text-align: center;
}

.merchant-metric__label {
  font-size: 12px;
  color: var(--curator-on-surface-variant);
  margin-bottom: 8px;
}

.merchant-metric__value {
  font-size: 14px;
  font-weight: 800;
  color: var(--curator-on-surface);
}

.merchant-stars {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
}

.merchant-star--on {
  color: $yellow;
}

.merchant-star--off {
  color: rgba(193, 198, 215, 0.75);
}

.merchant-cases-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.merchant-case-card {
  background: var(--curator-surface-low);
  border-radius: 12px;
  overflow: hidden;
  height: 126px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
}

.merchant-case-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 10px;
}

.merchant-cases-grid .merchant-empty {
  grid-column: 1 / -1;
}

.merchant-empty {
  background: var(--curator-surface-low);
  border-radius: 12px;
  padding: 18px;
  text-align: center;
  color: var(--curator-on-surface-variant);
  font-size: 13px;
}

.merchant-bottom-grid {
  margin-top: 2px;
}

.merchant-bottom-card {
  background: var(--curator-surface-low);
  border-radius: 16px;
  padding: 16px 16px;
}

.merchant-bottom-card__title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  font-weight: 700;
  color: var(--curator-on-surface-variant);
  margin-bottom: 12px;
}

.merchant-bottom-card__content {
  min-height: 56px;
}

.merchant-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: var(--curator-on-surface);
  margin-bottom: 10px;
}

.merchant-line:last-child {
  margin-bottom: 0;
}

.merchant-line__value {
  font-weight: 700;
}

.merchant-status-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.merchant-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(193, 198, 215, 0.9);
}

.merchant-status-dot--on {
  background: #67c23a;
  box-shadow: 0 0 10px rgba(103, 194, 58, 0.25);
}

.merchant-status-text {
  font-size: 13px;
  font-weight: 800;
}

.merchant-muted {
  margin-top: 12px;
  font-size: 12px;
  color: var(--curator-on-surface-variant);
}

.merchant-muted--remark {
  margin-top: 8px;
}

.merchant-status-tags {
  margin-top: 10px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.merchant-section--emphasis {
  margin-top: 14px;
}

.merchant-mechanism-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 12px;
}

.merchant-mechanism-item {
  background: rgba(242, 244, 247, 0.75);
  border-radius: 12px;
  padding: 12px 12px;
  min-width: 0;
}

.merchant-mechanism-label {
  font-size: 12px;
  color: var(--curator-on-surface-variant);
  margin-bottom: 8px;
}

.merchant-config-lines {
  margin-top: 6px;
}

.merchant-line--wrap {
  align-items: flex-start;
}

.merchant-address-tip__sub {
  margin-top: 6px;
  color: var(--curator-on-surface-variant);
  font-size: 12px;
}

.merchant-address-tip {
  background: var(--curator-surface-low);
  border-radius: 16px;
  padding: 14px 16px;
  margin-top: 14px;
}

.merchant-address-tip__row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.merchant-address-tip__icon {
  color: var(--curator-primary);
  font-size: 16px;
}

.merchant-address-tip__text {
  flex: 1;
  color: var(--curator-on-surface);
  font-size: 13px;
  word-break: break-word;
}

.merchant-address-tip__copy {
  color: var(--curator-primary) !important;
}

.search-card {
  margin-bottom: 16px;
  :deep(.el-card__body) { padding: 20px 20px 4px; }
}
.search-form {
  :deep(.el-form-item) { margin-bottom: 16px; margin-right: 16px; }
  .search-input { width: 180px; }
  .search-select-sm { width: 100px; }
}
.mt14 { margin-top: 14px; }
.pagination-wrap { padding: 16px 0 0; display: flex; justify-content: flex-end; }
.danger-link { color: #f56c6c; &:hover { opacity: 0.8; } }
.ml6 { margin-left: 6px; }

// 营业时间编辑器
.hours-editor {
  .hours-item {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
  }

  .hours-time {
    width: 160px;
  }

  .hours-remove {
    margin-left: 4px;
  }
}

// 抽屉详情
.detail-drawer__body {
  padding: 0 20px 24px;
  height: 100%;
  overflow-y: auto;
}

.detail-header {
  display: flex;
  align-items: center;
  padding: 20px 0 16px;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 16px;

  .detail-avatar { flex-shrink: 0; }

  &__info {
    margin-left: 16px;
    flex: 1;
    overflow: hidden;
  }

  .detail-name {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .detail-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
}

.stat-row {
  margin-bottom: 20px;

  .stat-block {
    text-align: center;
    padding: 14px 0;
    background: #f7f8fa;
    border-radius: 8px;

    .stat-num {
      font-size: 20px;
      font-weight: 700;
      color: #303133;
      line-height: 1.2;
      &.price { color: #f56c6c; }
    }

    .stat-name {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }
  }
}

.detail-section {
  margin-bottom: 20px;

  &__title {
    font-size: 13px;
    font-weight: 600;
    color: #606266;
    padding: 0 0 10px;
    border-bottom: 1px solid #f0f0f0;
    margin-bottom: 12px;
  }
}

.detail-item {
  display: flex;
  align-items: flex-start;
  padding: 7px 0;
  font-size: 13px;

  &__label {
    width: 80px;
    flex-shrink: 0;
    color: #909399;
  }

  &__value {
    flex: 1;
    color: #303133;
    word-break: break-all;
  }

  &--block {
    flex-direction: column;

    .detail-item__label { width: auto; margin-bottom: 4px; }
    .detail-item__value { line-height: 1.6; }
  }
}
</style>
