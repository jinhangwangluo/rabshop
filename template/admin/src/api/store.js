import request from '@/utils/request'

// Category
export function getCategoryList(params) {
  return request({
    url: '/admin/store/store_category/category_list',
    method: 'get',
    params
  })
}

export function deleteCategory(params) {
  return request({
    url: '/admin/store/store_category/delete',
    method: 'get',
    params
  })
}

export function setCategoryShow(params) {
  return request({
    url: '/admin/store/store_category/set_show',
    method: 'get',
    params
  })
}

// Product
export function getProductList(params) {
  return request({
    url: '/admin/store/store_product/product_ist',
    method: 'get',
    params
  })
}

export function deleteProduct(params) {
  return request({
    url: '/admin/store/store_product/delete',
    method: 'get',
    params
  })
}

export function productShow(data) {
  return request({
    url: '/admin/store/store_product/product_show',
    method: 'post',
    data
  })
}

export function cancelRecommendation(params) {
  return request({
    url: '/admin/store/store_product/cancel_recommendation',
    method: 'get',
    params
  })
}

export function setProduct(params) {
  return request({
    url: '/admin/store/store_product/set_product',
    method: 'get',
    params
  })
}

export function setProductShow(params) {
  return request({
    url: '/admin/store/store_product/set_show',
    method: 'get',
    params
  })
}

// Reply
export function getReplyList(params) {
  return request({
    url: '/admin/store/store_product_reply/productReplyList',
    method: 'get',
    params
  })
}

export function setReplyValue(params) {
  return request({
    url: '/admin/store/store_product_reply/set_value',
    method: 'get',
    params
  })
}

export function deleteReply(params) {
  return request({
    url: '/admin/store/store_product_reply/delete',
    method: 'get',
    params
  })
}

export function setReply(data) {
  return request({
    url: '/admin/store/store_product_reply/set_reply',
    method: 'post',
    data
  })
}
