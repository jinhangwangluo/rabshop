const fs = require('fs')
const path = require('path')

function loadParser(uniappRoot) {
  const searchPaths = [uniappRoot, path.resolve(uniappRoot, '../admin'), path.resolve(__dirname, '../../admin'), __dirname]
  return require(require.resolve('@babel/parser', { paths: searchPaths }))
}

/** Evaluate declaration literals only: never execute a plugin entry during a build. */
function literal(node) {
  if (!node) throw new Error('插件页面必须是静态字面量')
  if (['StringLiteral', 'NumericLiteral', 'BooleanLiteral'].includes(node.type)) return node.value
  if (node.type === 'NullLiteral') return null
  if (node.type === 'ArrayExpression') return node.elements.map(literal)
  if (node.type === 'ObjectExpression') {
    return node.properties.reduce((result, property) => {
      if (property.type !== 'ObjectProperty' || property.computed || property.method) throw new Error('插件页面不允许动态属性')
      const key = property.key.name || property.key.value
      if (['__proto__', 'constructor', 'prototype'].includes(key)) throw new Error('插件页面属性不安全')
      result[key] = literal(property.value)
      return result
    }, {})
  }
  throw new Error('插件页面必须是静态字面量')
}

/** Collect precompiled native pages; runtime enable state is checked separately. */
function collectPluginPages(uniappRoot) {
  const pluginsRoot = path.resolve(uniappRoot, 'plugins')
  if (!fs.existsSync(pluginsRoot)) return []
  const parser = loadParser(uniappRoot)
  const pages = []
  const seen = new Set()
  for (const directory of fs.readdirSync(pluginsRoot, { withFileTypes: true }).sort((left, right) => left.name.localeCompare(right.name))) {
    if (!directory.isDirectory() || !/^[a-z][a-z0-9_]{0,63}$/.test(directory.name)) continue
    const code = directory.name
    const pluginRoot = path.join(pluginsRoot, code)
    const entry = path.join(pluginRoot, 'index.js')
    if (!fs.existsSync(entry)) continue
    if (fs.lstatSync(entry).isSymbolicLink()) throw new Error(`插件页面入口不允许软链接：${code}`)
    const ast = parser.parse(fs.readFileSync(entry, 'utf8'), { sourceType: 'module' })
    const declaration = ast.program.body.find(node => node.type === 'ExportNamedDeclaration' && node.declaration?.type === 'VariableDeclaration' && node.declaration.declarations.some(item => item.id.name === 'pages'))
    if (!declaration) continue
    const values = literal(declaration.declaration.declarations.find(item => item.id.name === 'pages').init)
    if (!Array.isArray(values)) throw new Error(`插件pages必须是数组：${code}`)
    for (const page of values) {
      if (!page || !new RegExp(`^plugins/${code}/pages/[a-z0-9_/]+$`).test(page.path) || page.path.includes('..')) throw new Error(`插件页面路径越界：${code}`)
      const absolute = path.resolve(uniappRoot, `${page.path}.vue`)
      if (!fs.existsSync(absolute) || fs.lstatSync(absolute).isSymbolicLink() || !fs.realpathSync(absolute).startsWith(`${fs.realpathSync(pluginRoot)}${path.sep}`)) throw new Error(`插件页面不存在或不安全：${page.path}`)
      if (seen.has(page.path)) throw new Error(`插件页面重复：${page.path}`)
      seen.add(page.path)
      pages.push({ path: page.path, style: page.style || { navigationBarTitleText: page.name || code } })
    }
  }
  return pages
}

/** Append to compiler input without rewriting the tracked pages.json. */
function mergePluginPages(uniappRoot, source) {
  const parser = loadParser(uniappRoot)
  const base = literal(parser.parseExpression(`(${source})`))
  if (!Array.isArray(base.pages)) throw new Error('pages.json缺少pages数组')
  const existing = new Set(base.pages.map(page => page.path))
  for (const page of collectPluginPages(uniappRoot)) {
    if (existing.has(page.path)) throw new Error(`宿主与插件页面重复：${page.path}`)
    base.pages.push(page)
    existing.add(page.path)
  }
  return JSON.stringify(base)
}

/** The UniApp native compiler loads pages-json-js before its platform transformer. */
function pluginPages(uniappRoot) {
  return {
    name: 'host:plugin-native-pages',
    enforce: 'pre',
    load(id) {
      if (!/(?:^|\/)pages-json-js(?:\?.*)?$/.test(id.replace(/\\/g, '/'))) return null
      const pagesFile = path.join(uniappRoot, 'pages.json')
      this.addWatchFile(pagesFile)
      for (const page of collectPluginPages(uniappRoot)) this.addWatchFile(path.join(uniappRoot, page.path + '.vue'))
      return mergePluginPages(uniappRoot, fs.readFileSync(pagesFile, 'utf8'))
    }
  }
}

module.exports = { collectPluginPages, mergePluginPages, pluginPages }
