const MERCHANT_HOME_PATH = '/pages/merchant/index/index'

/**
 * 跳转商户首页。
 *
 * @param {number|string} merchantId 商户 ID。
 * @returns {boolean} 是否发起了页面跳转。
 */
export function navigateToMerchantHome(merchantId) {
  const id = Number(merchantId || 0)
  if (Number.isNaN(id) || id <= 0) {
    uni.showToast({ title: '平台自营暂无店铺主页', icon: 'none' })
    return false
  }

  uni.navigateTo({ url: `${MERCHANT_HOME_PATH}?merchant_id=${id}` })
  return true
}
