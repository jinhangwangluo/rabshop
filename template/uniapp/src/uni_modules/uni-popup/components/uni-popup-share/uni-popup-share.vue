<template>
	<view class="uni-popup-share">
		<view class="uni-share-title"><text class="uni-share-title-text">{{shareTitleText}}</text></view>
		<view class="uni-share-content">
			<view class="uni-share-content-box">
				<view class="uni-share-content-item" v-for="(item,index) in bottomData" :key="index" @click.stop="select(item,index)">
					<image class="uni-share-image" :src="item.icon" mode="aspectFill"></image>
					<text class="uni-share-text">{{item.text}}</text>
				</view>

			</view>
		</view>
		<view class="uni-share-button-box">
			<button class="uni-share-button" @click="close">{{cancelText}}</button>
		</view>
	</view>
</template>

<script>
	import popup from '../uni-popup/popup.js'
	import {
	initVueI18n
	} from '@dcloudio/uni-i18n'
	import messages from '../uni-popup/i18n/index.js'
	const {	t	} = initVueI18n(messages)
	export default {
		name: 'UniPopupShare',
		mixins:[popup],
		emits:['select'],
		props: {
			title: {
				type: String,
				default: ''
			},
			beforeClose: {
				type: Boolean,
				default: false
			}
		},
		data() {
			return {
				// TODO 替换为自己的图标
				bottomData: [{
						text: '微信',
						icon: '/static/images/unicloudlogo.png',
						name: 'wx'
					},
					{
						text: '支付宝',
						icon: '/static/images/unicloudlogo.png',
						name: 'ali'
					},
					{
						text: 'QQ',
						icon: '/static/images/unicloudlogo.png',
						name: 'qq'
					},
					{
						text: '新浪',
						icon: '/static/images/unicloudlogo.png',
						name: 'sina'
					},
					// {
					// 	text: '百度',
					// 	icon: '/static/images/unicloudlogo.png',
					// 	name: 'copy'
					// },
					// {
					// 	text: '其他',
					// 	icon: '/static/images/unicloudlogo.png',
					// 	name: 'more'
					// }
				]
			}
		},
		created() {},
		computed: {
			cancelText() {
				return t("uni-popup.cancel")
			},
		shareTitleText() {
				return this.title || t("uni-popup.shareTitle")
			}
		},
		methods: {
			/**
			 * 选择内容
			 */
			select(item, index) {
				this.$emit('select', {
					item,
					index
				})
				this.close()

			},
			/**
			 * 关闭窗口
			 */
			close() {
				if(this.beforeClose) return
				this.popup.close()
			}
		}
	}
</script>
<style lang="scss" >
	.uni-popup-share {
		background-color: #fff;
		border-top-left-radius: 11px;
		border-top-right-radius: 11px;
	}
	.uni-share-title {
		/* #ifndef APP-NVUE */
		display: flex;
		/* #endif */
		flex-direction: row;
		align-items: center;
		justify-content: center;
		height: 40px;
	}
	.uni-share-title-text {
		font-size: 14px;
		color: #666;
	}
	.uni-share-content {
		/* #ifndef APP-NVUE */
		display: flex;
		/* #endif */
		flex-direction: row;
		justify-content: center;
		padding-top: 10px;
	}

	.uni-share-content-box {
		/* #ifndef APP-NVUE */
		display: flex;
		/* #endif */
		flex-direction: row;
		flex-wrap: wrap;
		width: 360px;
	}

	.uni-share-content-item {
		width: 90px;
		/* #ifndef APP-NVUE */
		display: flex;
		/* #endif */
		flex-direction: column;
		justify-content: center;
		padding: 10px 0;
		align-items: center;
	}

	.uni-share-content-item:active {
		background-color: #f5f5f5;
	}

	.uni-share-image {
		width: 30px;
		height: 30px;
	}

	.uni-share-text {
		margin-top: 10px;
		font-size: 14px;
		color: #3B4144;
	}

	.uni-share-button-box {
		/* #ifndef APP-NVUE */
		display: flex;
		/* #endif */
		flex-direction: row;
		padding: 10px 15px;
	}

	.uni-share-button {
		flex: 1;
		border-radius: 50px;
		color: #666;
		font-size: 16px;
	}

	.uni-share-button::after {
		border-radius: 50px;
	}
</style>
