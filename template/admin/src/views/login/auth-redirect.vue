<template>
  <div style="display: none" />
</template>

<script setup>
defineOptions({
  name: 'AuthRedirect'
})

const hash = window.location.search.slice(1)
if (window.localStorage) {
  window.localStorage.setItem('x-admin-oauth-code', hash)
  window.close()
}
</script>
