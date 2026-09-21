import { useAppStore } from '@/store/modules/app'

const { body } = document
const WIDTH = 992 // refer to Bootstrap's responsive design

export default {
  watch: {
    $route() {
      const appStore = useAppStore()
      if (appStore.device === 'mobile' && appStore.sidebar.opened) {
        appStore.closeSideBar({ withoutAnimation: false })
      }
    }
  },
  beforeMount() {
    window.addEventListener('resize', this.$_resizeHandler)
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.$_resizeHandler)
  },
  mounted() {
    const isMobile = this.$_isMobile()
    if (isMobile) {
      const appStore = useAppStore()
      appStore.toggleDevice('mobile')
      appStore.closeSideBar({ withoutAnimation: true })
    }
  },
  methods: {
    // use $_ for mixins properties
    // https://vuejs.org/v2/style-guide/index.html#Private-property-names-essential
    $_isMobile() {
      const rect = body.getBoundingClientRect()
      return rect.width - 1 < WIDTH
    },
    $_resizeHandler() {
      if (!document.hidden) {
        const isMobile = this.$_isMobile()
        const appStore = useAppStore()
        appStore.toggleDevice(isMobile ? 'mobile' : 'desktop')

        if (isMobile) {
          appStore.closeSideBar({ withoutAnimation: true })
        }
      }
    }
  }
}
