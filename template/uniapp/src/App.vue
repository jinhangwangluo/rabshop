<script setup>
	import {
		onLaunch,
		onShow,
		onHide
	} from '@dcloudio/uni-app'
	import {
		captureSpreadFromLaunchOptions
	} from '@/utils/spread'
	import {
		configWechatJsSdk
	} from '@/utils/wechat_js_sdk'
	import {
		isWechatBrowser
	} from '@/utils/platform'
	import {
		syncThemeConfig
	} from '@/utils/theme'

	const brandConsoleLogo = `
               :==:              :-=:
             .#%@%%#:          :#%%@%#.
             *@%%%%%%-        -%%%%%%@*
            .%%%%%%%%%.      :%%%%%%%%%
            .%%%%%%%%%+      *%%%%%%%%%
             #%%%%%%%%%     .%%%%%%%%%*
             -%%%%%%%%%:    :%%%%%%%%%:
              +%%%%%%%%#****#%%%%%%%%=
              .#%%%%%@@@@@@@@@@%%%%%*
            .+#%%@%%*+=--::--=+*%%@%%%+.
           =%%%%%+:              :+%%%%%+
          *@%%@#.   :         .:   .#@%%@*
         +@%%@#.   #@#        #@*   .#@%%@+
        .%%%%=     *%*        *%+     =%%%%.
        :%%@+       .   :==:   .       +@%%:
        .%%%#.         :%@@#.         .#%%%
         :%%%%+:         ..         :+%%%%:
          .+%%@@%*+=-:::....::--=+*%@@%%+.
             :+*#%@@@@@@@@@@@@@@@@%#*+:
                 .::-===+++====-::.
`

	/**
	 * 公众号 H5 进入应用时初始化微信 JS-SDK 全局权限。
	 * @returns {void}
	 */
	function bootstrapWechatJsSdk() {
		if (!isWechatBrowser()) {
			return
		}

		configWechatJsSdk({
			showError: false
		})
	}

	/**
	 * 输出应用控制台品牌信息。
	 * @returns {void}
	 */
	function printBrandConsole() {
		console.log('%c%s', 'color:#ff5a1f;font-family:Menlo,Consolas,monospace;line-height:1.1;', brandConsoleLogo)
		console.log(
			'%c 西安锦航网络科技有限公司 %c 官网地址：www.rabshop.cn %c 温馨提示：代码正在值班，Bug 请先取号排队。',
			'background:#202b3c;color:#ffffff;padding:3px 8px;border-radius:3px 0 0 3px;font-weight:bold;',
			'background:#eef6ff;color:#1b6ebf;padding:3px 8px;font-weight:bold;',
			'background:#f5c542;color:#202b3c;padding:3px 8px;border-radius:0 3px 3px 0;'
		)
	}

	onLaunch((options) => {
		captureSpreadFromLaunchOptions(options)
		syncThemeConfig()
		printBrandConsole()
		console.log('App Launch')
	})

	onShow((options) => {
		captureSpreadFromLaunchOptions(options)
		bootstrapWechatJsSdk()
		console.log('App Show')
	})

	onHide(() => {
		console.log('App Hide')
	})
</script>

<style lang="scss">
	/* 每个页面公共 css */
	@import url("@/styles/iconfont/iconfont.css");

	:root,
	page,
	uni-page-body {
		--theme-primary: #ea580c;
		--theme-secondary: #fb923c;
		--theme-gradient: linear-gradient(135deg, #ea580c 0%, #fb923c 100%);
		--brand-primary: #ea580c;
		--brand-primary-dark: #c2410c;
		--brand-primary-light: #fb923c;
		--brand-primary-pale: #fff7ed;
		--brand-shadow: rgba(234, 88, 12, 0.18);
		--brand-shadow-hover: rgba(234, 88, 12, 0.26);
		--brand-shadow-card: rgba(15, 23, 42, 0.06);
		--brand-focus-shadow: rgba(234, 88, 12, 0.16);
		--text-primary: #18181b;
		--text-regular: #3f3f46;
		--text-secondary: #71717a;
		--text-placeholder: #a1a1aa;
		--text-disabled: #d4d4d8;
		--text-link: #ea580c;
		--text-link-hover: #c2410c;
		--text-inverse: #ffffff;
		--text-inverse-soft: rgba(255, 255, 255, 0.8);
		--text-inverse-muted: rgba(255, 255, 255, 0.64);
		--text-title: #18181b;
		--text-subtitle: #52525b;
		--text-success: #16a34a;
		--text-warning: #f59e0b;
		--text-danger: #dc2626;
		--text-info: #2563eb;
		--surface-page: #f9f9f9;
		--surface-card: #ffffff;
		--surface-card-muted: #f3f4f6;
		--surface-card-soft: #f4f4f5;
		--surface-user-hero: #f7ebda;
		--surface-user-hero-start: #fff7ed;
		--surface-user-hero-end: #fed7aa;
		--border-light: #f4f4f5;
		--border-default: #e4e4e7;
		--mask-light: rgba(255, 255, 255, 0.4);
		--mask-dark: rgba(0, 0, 0, 0.1);
		--shadow-soft: rgba(0, 0, 0, 0.06);
		--shadow-card: rgba(15, 23, 42, 0.03);
		--vip-bg-start: #27272a;
		--vip-bg-end: #09090b;
		--vip-border: rgba(245, 158, 11, 0.28);
		--vip-icon: #fde68a;
		--vip-text: #fef3c7;
		--vip-button-start: #fde68a;
		--vip-button-end: #eab308;
		--vip-button-text: #18181b;
	}

	page,
	uni-page-body {
		background-color: var(--surface-page, #f9f9f9);
		color: var(--text-primary, #18181b);
	}

	.line {
		width: 750rpx;
		height: 16rpx;
		background-color: var(--surface-card-muted, #f3f4f6);
	}

	.hidden_one {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.hidden_more {
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.scrollbar-hidden {
		-ms-overflow-style: none;
		scrollbar-width: none;
	}

	.scrollbar-hidden::-webkit-scrollbar {
		display: none;
		width: 0;
		height: 0;
		background: transparent;
	}

	/* #ifdef H5 */
	.scrollbar-hidden,
	.scrollbar-hidden .uni-scroll-view,
	.scrollbar-hidden .uni-scroll-view-content,
	.scrollbar-hidden > div {
		-ms-overflow-style: none !important;
		scrollbar-width: none !important;
	}

	.scrollbar-hidden::-webkit-scrollbar,
	.scrollbar-hidden .uni-scroll-view::-webkit-scrollbar,
	.scrollbar-hidden .uni-scroll-view-content::-webkit-scrollbar,
	.scrollbar-hidden > div::-webkit-scrollbar {
		display: none !important;
		width: 0 !important;
		height: 0 !important;
		background: transparent !important;
	}
	/* #endif */

	.kaitong {
		width: 690rpx;
		height: 80rpx;
		background-color: var(--brand-primary, #ea580c);
		color: var(--text-inverse, #ffffff);
		text-align: center;
		line-height: 80rpx;
		border-radius: 40rpx;
		margin: 30rpx 0 0 30rpx;
	}

	.kaitong1 {
		width: 690rpx;
		height: 80rpx;
		background-color: var(--brand-primary, #ea580c);
		color: var(--text-inverse, #ffffff);
		text-align: center;
		line-height: 80rpx;
		border-radius: 10rpx;
	}

	.pop-btn {
		width: 300rpx;
		height: 80rpx;
		background-color: var(--brand-primary, #ea580c);
		text-align: center;
		line-height: 80rpx;
		color: var(--text-inverse, #ffffff);
		font-size: 28rpx;
		border-radius: 40rpx;
		margin: 50rpx auto 0;
	}

	.o-bot {
		width: 750rpx;
		height: 90rpx;
		background-color: var(--surface-card, #ffffff);
		position: fixed;
		bottom: 0;
		line-height: 90rpx;
		text-align: center;
	}

	.flex {
		display: flex;
		justify-content: space-between;
		flex-wrap: wrap;
	}

	.flex1 {
		display: flex;
	}

	.liji {
		width: 750rpx;
		height: 120rpx;
		position: fixed;
		bottom: 0;
		background-color: var(--surface-card, #ffffff);
		display: flex;
		justify-content: center;
		align-items: center;
	}
</style>
