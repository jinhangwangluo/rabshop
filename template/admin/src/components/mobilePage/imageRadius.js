function toNumber(value) {
  const nextValue = Number(value)
  return Number.isFinite(nextValue) ? nextValue : 0
}

export function getImageRadiusStyle(styleConfig = {}) {
  if (!styleConfig || typeof styleConfig !== 'object') {
    return {}
  }

  if (styleConfig.image_radius_mode === 'each') {
    const lt = toNumber(styleConfig.image_radius_lt)
    const rt = toNumber(styleConfig.image_radius_rt)
    const rb = toNumber(styleConfig.image_radius_rb)
    const lb = toNumber(styleConfig.image_radius_lb)

    if (lt || rt || rb || lb) {
      return {
        borderRadius: `${lt}px ${rt}px ${rb}px ${lb}px`,
        overflow: 'hidden'
      }
    }

    return {}
  }

  const radius = toNumber(styleConfig.image_radius)
  if (radius > 0) {
    return {
      borderRadius: `${radius}px`,
      overflow: 'hidden'
    }
  }

  return {}
}
