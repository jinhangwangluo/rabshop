<template>
	<view class="circle-progress-bar"
	:style="{
		height: size + cunit,
		width: size + cunit,
	}">
		<view class="container" 
		:style="{
			background: border_back_color,
			height: size + cunit,
			width: size + cunit,
		}">
			<view style="width: 100%; height: 100%;"
			:style="{
				transform: `rotate(${start * 360}deg)`
			}">
				<view class="left"
				:style="{
					clip: `rect(0px, ${halfSize + cunit}, ${size + cunit}, 0px)`
				}">
					<view class="inner"
					:style="{
						background: border_color,
						clip: `rect(0px, ${size + cunit}, ${size + cunit}, ${halfSize + cunit})`,
						transform: `rotate(${left_deg}deg)`,
						transition: animate ? `all ${left_time}ms` : '',
						'transition-timing-function': (first == 'left' ? 'ease-in' : 'ease-out')
					}"></view>
				</view>
				<view class="right"
				:style="{
					clip: `rect(0px, ${size + cunit}, ${size + cunit}, ${halfSize + cunit})`
				}">
					<view class="inner"
					:style="{
						background: border_color,
						clip: `rect(0px, ${halfSize + cunit}, ${size + cunit}, 0)`,
						transform: `rotate(${right_deg}deg)`,
						transition: animate ? `all ${right_time}ms` : '',
						'transition-timing-function': (first == 'right' ? 'ease-in' : 'ease-out')
					}"></view>
				</view>
				<view class="point"
				:style="{
					width: border_width + cunit,
					height: border_width + cunit,
					left: `calc(50% - ${border_width / 2}${cunit})`,
					background: border_color,
				}"></view>
				<view class="point" 
				:style="{
					width: border_width + cunit,
					height: border_width + cunit,
					left: `calc(50% - ${border_width / 2}${cunit})`,
					transform: `rotate(${point_deg}deg)`,
					transition: animate ? `all ${cur_set == 'left' ? left_time : right_time}ms` : '',
					'transform-origin': `${border_width / 2}${cunit} ${size / 2}${cunit}`,
					'transition-timing-function': (cur_set == 'left' ? (first == 'left' ? 'ease-in' : 'ease-out') : (first == 'right' ? 'ease-in' : 'ease-out')),
					background: border_color,
				}"></view>
			</view>
			<view class="content abs-center"
			style="border-radius: 50%;"
			:style="{
				background: background,
				width: contentSize + cunit,
				height: contentSize + cunit
			}">
				<view class="abs-center">
					<slot :pro="pro"></slot>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
	import { computed, nextTick, onMounted, ref, watch } from 'vue'

	defineOptions({
		name: 'circle-progress-bar'
	})

	const props = defineProps({
		//进度 0-1
		pro: {
			type: Number,
			default: 0,
		},
		//起始位置 0-1
		start: {
			type: Number,
			default: 0,
		},
		//圆形大小
		size: {
			type: Number,
			default: 100
		},
		//线宽度
		border_width: {
			type: Number,
			default: 5
		},
		//线颜色
		border_color: {
			type: String,
			default: '#2c8eff',
		},
		//线背景色
		border_back_color: {
			type: String,
			default: '#DDD',
		},
		//中心内容背景色
		background: {
			type: String,
			default: '#FFF',
		},
		//单位
		unit: {
			type: String,
			default: 'rpx',
		},
		//是否启用动画
		animate:{
			type: Boolean,
			default: true,
		}
	})

	const firstLoad = ref(true)
	const cur_pro = ref(0)
	const left_deg = ref(0)
	const right_deg = ref(0)
	const point_deg = ref(0)
	const left_time = ref(0)
	const right_time = ref(0)
	const first = ref('')
	const cur_set = ref('')
	const tid = ref(0)

	const halfSize = computed(() => {
		return props.size / 2
	})

	const contentSize = computed(() => {
		return props.size - (props.border_width * 2)
	})

	const cunit = computed(() => {
		if(props.unit === 'upx') {
			return 'rpx'
		}
		return props.unit
	})

	watch(() => props.pro, (val) => {
		setPro(val)
	})

	onMounted(() => {
		setPro(props.pro)
	})

	function setPro(pro) {
		cur_set.value = '';

		nextTick(() => {
			rePro(pro)
		})
	}

	function rePro(pro) {
		const time = 300;
		let left = 0;
		let right = 0;
		let firstValue = cur_pro.value > 0.5 ? 'left' : 'right';
		if(cur_pro.value == 0.5) {
			firstValue = pro > 0.5 ? 'left' : 'right';
		}
		if(pro > 0.5) {
			left = (pro - 0.5) * 2 * 180;
			right = 180;
		}
		else{
			left = 0;
			right = (pro * 2) * 180;
		}

		let dis_left = left_deg.value - left;
		let dis_right = right_deg.value - right;
		let dis_all = Math.abs(dis_left) + Math.abs(dis_right)
		let leftTime = (Math.abs(dis_left) / dis_all) * time;
		let rightTime = (Math.abs(dis_right) / dis_all) * time;

		if(firstLoad.value || !props.animate) {
			firstLoad.value = false;
			first.value = firstValue;
			left_deg.value = left;
			right_deg.value = right;
			left_time.value = leftTime;
			right_time.value = rightTime;
			cur_pro.value = pro;
			rePointDeg();
			return;
		}

		//console.log('first', firstValue)
		//console.log('time', leftTime, rightTime)
		if(firstValue === 'left') {
			if(rightTime > 0) {
				cur_set.value = 'left';
				left_deg.value = left;
				rePointDeg();
				clearTimeout(tid.value)
				tid.value = setTimeout(() => {
					cur_set.value = 'right';
					right_deg.value = right;
					rePointDeg();
				}, leftTime)
			}
			else{
				cur_set.value = 'left';
				left_deg.value = left;
				right_deg.value = right;
				rePointDeg();
			}
		}
		else
		{
			if(leftTime > 0) {
				cur_set.value = 'right';
				right_deg.value = right;
				rePointDeg();
				clearTimeout(tid.value)
				tid.value = setTimeout(() => {
					cur_set.value = 'left';
					left_deg.value = left;
					rePointDeg();
				}, rightTime)
			}
			else
			{
				cur_set.value = 'right';
				left_deg.value = left;
				right_deg.value = right;
				rePointDeg();
			}
		}

		first.value = firstValue;
		left_time.value = leftTime;
		right_time.value = rightTime;
		cur_pro.value = pro;
	}

	function rePointDeg() {
		point_deg.value = left_deg.value + right_deg.value;
	}
</script>

<style lang="scss">
	$size: 200px;
	$half-size: $size / 2;
	.container {
		position: relative;
		border-radius: 50%;
	}

	.left, .right{
		position: absolute;
		width: 100%;
		height: 100%;
		
		.inner{
			position: absolute;
			width: 100%;
			height: 100%;
			background: #DDD;
			border-radius: 50%;
		}
	}
	
	.left {
		clip: rect(0px, $half-size, $size, 0px);
		.inner {
			clip: rect(0px, $size, $size, $half-size);
		}
	}
	.right {
		clip: rect(0px, $size, $size, $half-size);
		.inner {
			clip: rect(0px, $half-size, $size, 0);
		}
	}
	.point{
		z-index: 3;
		top: 0upx;
		border-radius: 50%;
		position: absolute;
	}
	.abs-center {
		position: absolute;
		left: 50%;
		top: 50%;
		transform: translate(-50%, -50%);
	}
</style>