import SvgIcon from '@/components/SvgIcon'
import LegacyIcon from '@/components/LegacyIcon'
import { legacyIconComponents } from '@/utils/legacyIcons'

const svgModules = import.meta.glob('./svg/*.svg', {
  eager: true,
  query: '?raw',
  import: 'default'
})

function createSvgSprite() {
  const sprite = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  sprite.setAttribute('aria-hidden', 'true')
  sprite.style.position = 'absolute'
  sprite.style.width = '0'
  sprite.style.height = '0'
  sprite.style.overflow = 'hidden'

  Object.entries(svgModules).forEach(([filePath, content]) => {
    const name = filePath.split('/').pop().replace(/\.svg$/, '')
    const wrapper = document.createElement('div')
    wrapper.innerHTML = content
    const source = wrapper.querySelector('svg')
    if (!source) {
      return
    }

    const symbol = document.createElementNS('http://www.w3.org/2000/svg', 'symbol')
    symbol.setAttribute('id', `icon-${name}`)
    symbol.setAttribute('viewBox', source.getAttribute('viewBox') || '0 0 1024 1024')
    symbol.innerHTML = source.innerHTML
    sprite.appendChild(symbol)
  })

  document.body.prepend(sprite)
}

export default {
  install(app) {
    app.component('SvgIcon', SvgIcon)
    app.component('svg-icon', SvgIcon)
    app.component('LegacyIcon', LegacyIcon)
    app.component('legacy-icon', LegacyIcon)

    Object.entries(legacyIconComponents).forEach(([name, component]) => {
      app.component(name, component)
    })

    createSvgSprite()
  }
}
