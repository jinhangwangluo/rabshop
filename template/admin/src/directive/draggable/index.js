
export function on() {
  if (document.addEventListener) {
    return function(element, event, handler) {
      if (element && event && handler) {
        element.addEventListener(event, handler, false);
      }
    };
  } else {
    return function(element, event, handler) {
      if (element && event && handler) {
        element.attachEvent('on' + event, handler);
      }
    };
  }
}

export function off() {
  if (document.removeEventListener) {
    return function(element, event, handler) {
      if (element && event && handler) {
        element.removeEventListener(event, handler, false);
      }
    };
  } else {
    return function(element, event, handler) {
      if (element && event && handler) {
        element.detachEvent('on' + event, handler);
      }
    };
  }
}

export default {
  mounted: (el, binding) => {
    const triggerDom = document.querySelector(binding.value.trigger);
    const bodyDom = document.querySelector(binding.value.body);
    if (!triggerDom || !bodyDom) return

    triggerDom.style.cursor = 'move';
    let pageX = 0;
    let pageY = 0;
    let transformX = 0;
    let transformY = 0;
    let canMove = false;
    const handleMousedown = (e) => {
      let transform = /\(.*\)/.exec(bodyDom.style.transform);
      if (transform) {
        transform = transform[0].slice(1, transform[0].length - 1);
        const splitxy = transform.split('px, ');
        transformX = parseFloat(splitxy[0]);
        transformY = parseFloat(splitxy[1].split('px')[0]);
      }
      pageX = e.pageX;
      pageY = e.pageY;
      canMove = true;
    };
    const handleMousemove = (e) => {
      const xOffset = e.pageX - pageX + transformX;
      const yOffset = e.pageY - pageY + transformY;
      if (canMove) bodyDom.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
    };
    const handleMouseup = (e) => {
      canMove = false;
    };
    const addListener = on();
    addListener(triggerDom, 'mousedown', handleMousedown);
    addListener(document, 'mousemove', handleMousemove);
    addListener(document, 'mouseup', handleMouseup);
    el.__draggableState = {
      triggerDom,
      handleMousedown,
      handleMousemove,
      handleMouseup
    }
  },
  updated: (el, binding) => {
    if (!binding.value || !binding.value.recover) return;
    const bodyDom = document.querySelector(binding.value.body);
    if (bodyDom) {
      bodyDom.style.transform = '';
    }
  },
  unmounted: (el) => {
    const state = el.__draggableState
    if (!state) return

    const removeListener = off();
    removeListener(state.triggerDom, 'mousedown', state.handleMousedown);
    removeListener(document, 'mousemove', state.handleMousemove);
    removeListener(document, 'mouseup', state.handleMouseup);
    delete el.__draggableState
  }
};
