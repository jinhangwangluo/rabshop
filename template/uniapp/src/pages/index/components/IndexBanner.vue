<template>
	<swiper class="swiper-class" :indicator-dots="true" :autoplay="true" :interval="3000" :duration="1000"
		indicator-active-color="#007aff" indicator-color="#B4B4B4">
		<swiper-item v-for="(item,index) in list" :key="index" @click="handleClick(item.url)">
			<image :src="item.pic" mode="scaleToFill"></image>
		</swiper-item>
	</swiper>
</template>

<script setup>
	defineProps({
		list: {
			type: Array,
			default: () => []
		}
	})

	const emit = defineEmits(['click'])

	function handleClick(url) {
		emit('click', url)
	}
</script>

<style scoped lang="scss">
	.swiper-class {
		width: 730rpx;
		height: 324rpx;
		margin-left: 10rpx;

		image {
			width: 100%;
			height: 100%;
		}
	}
</style>
