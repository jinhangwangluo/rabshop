import request from '@/utils/request'

/**
 * 获取门店列表
 * @param params
 * @returns {AxiosPromise}
 */
export function getStoreManageListApi(params) {
  return request({
    url: '/store/manage/list',
    method: 'get',
    params
  })
}

/**
 * 保存门店
 * @param data
 * @returns {AxiosPromise}
 */
export function saveStoreManageApi(data) {
  return request({
    url: '/store/manage/save',
    method: 'post',
    data
  })
}

/**
 * 同步商品
 * @param id
 * @returns {*}
 */
export function syncProductManageApi(id) {
  return request({
    url: '/store/manage/sync_product/' + id,
    method: 'post'
  })
}

/**
 * 同步商品到渠道
 * @param data
 * @returns {AxiosPromise}
 */
export function syncProductChannelApi(data) {
  return request({
    url: '/store/manage/sync_channel',
    method: 'post',
    data
  })
}

/**
 * 修改门店
 * @param id
 * @param data
 * @returns {AxiosPromise}
 */
export function editStoreManageApi(id, data) {
  return request({
    url: `/store/manage/edit/${id}`,
    method: 'put',
    data
  })
}

/**
 * 下拉筛选门店
 * @returns {AxiosPromise}
 */
export function getStoreManageOptionApi(params) {
  return request({
    url: '/store/manage/options',
    method: 'get',
    params
  })
}

/**
 * 下拉筛选门店
 * @returns {AxiosPromise}
 */
export function getStoreManageOptionAllApi() {
  return request({
    url: '/store/manage/options_all',
    method: 'get'
  })
}

/**
 * 获取门店详情
 * @param id
 * @returns {AxiosPromise}
 */
export function getStoreManageInfoApi(id) {
  return request({
    url: `/store/manage/show/${id}`,
    method: 'get'
  })
}
