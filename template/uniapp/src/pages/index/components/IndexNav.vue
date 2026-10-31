<template>
	<div class="recommend">
		<div class="r-block" v-for="(item,index) in list" :key="index" @click="handleClick(item.grade_id, index)">
			<div class="r-img">
				<image :src="item.icon" mode=""></image>
			</div>
			<div>{{item.title}}</div>
		</div>
	</div>
</template>

<script setup>
	defineProps({
		list: {
			type: Array,
			default: () => []
		}
	})

	const emit = defineEmits(['click'])

	function handleClick(id, index) {
		emit('click', { id, index })
	}
</script>

<style scoped lang="scss">
	.recommend {
		width: 750rpx;
		display: flex;
		flex-wrap: wrap;

		.r-block {
			width: 150rpx;
			text-align: center;
			padding-top: 14rpx;
			padding-bottom: 14rpx;
			font-size: 24rpx;
			line-height: 30rpx;
			text-align: center;
			color: #666666;
			word-break: break-all;

			.r-img {
				image {
					width: 90rpx;
					height: 90rpx;
				}
			}
		}
	}
</style>
