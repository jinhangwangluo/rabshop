# bubble-address-picker
## 使用方法

**符合`easycom`组件模式, 导入 `uni_modules` 后直接使用即可 **

```html
<template>
  <view class="container">
    <bubble-address-picker ref="addressPicker" v-model="address" />
  </view>
</template>
<script>
  export default {
	data() {
		return {
			address: {}
		}
	},
	methods: {
		showAddressPicker() {
			this.$refs.addressPicker.showPicker()
		}
	}
}
</script>
<style lang="scss" scoped></style>
```

** 如果对包容量大小敏感的话可以将json文件存放在服务器后传入remoteUrl参数,并移除插件对本地json的引用 **

```html
<script>
// 注释这里
// import CityJsonData from '../../city.json'
export default {
	data() {
		return {
			address: {}
		}
	},
	methods: {
		
	}
}
</script>
<style lang="scss" scoped></style>
```

| 参数    		    | 类型   | 默认值    | 描述          |
| ---------------- | ------ | --------- | ------------- |
| remoteUrl		  | String |  '' | 远程数据地址 |
