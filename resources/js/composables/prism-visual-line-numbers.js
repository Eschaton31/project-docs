;(function () {
  if (typeof Prism === 'undefined') {
    return
  }

  var PLUGIN_CLASS = 'visual-line-numbers'
  var ROWS_CLASS = 'visual-line-numbers-rows'
  var OBSERVER_KEY = '__visualLineNumbersObserver'

  // Simple debounce to avoid thrashing on resize
  function debounce(fn, delay) {
    var timeout
    return function () {
      var ctx = this
      var args = arguments
      clearTimeout(timeout)
      timeout = setTimeout(function () {
        fn.apply(ctx, args)
      }, delay)
    }
  }

  // Core update function: recompute visual lines and rebuild rows
  function updateVisualLineNumbers(pre, code) {
    if (!pre || !code) return

    var style = window.getComputedStyle(code)

    // If line-height is "normal", fallback to font-size * 1.2 heuristic
    var lineHeightStr = style.lineHeight
    var lineHeight
    if (lineHeightStr === 'normal') {
      var fontSize = parseFloat(style.fontSize) || 16
      lineHeight = fontSize * 1.2
    } else {
      lineHeight = parseFloat(lineHeightStr)
    }

    if (!lineHeight || !isFinite(lineHeight)) return

    var codeRect = code.getBoundingClientRect()
    var codeHeight = codeRect.height

    if (!codeHeight || !isFinite(codeHeight)) return

    var visualLines = Math.max(1, Math.round(codeHeight / lineHeight))

    var container = pre.querySelector('.' + ROWS_CLASS)
    if (!container) {
      container = document.createElement('span')
      container.className = ROWS_CLASS
      pre.appendChild(container)
    }

    // Only rebuild if count actually changes
    var currentCount = container.childNodes.length
    if (currentCount === visualLines) return

    // Rebuild rows
    container.innerHTML = ''
    for (var i = 0; i < visualLines; i++) {
      container.appendChild(document.createElement('span'))
    }
  }

  function setupVisualLineNumbers(env) {
    var code = env.element
    var pre = code && code.parentNode

    if (!pre || !/pre/i.test(pre.nodeName)) return
    if (!pre.classList.contains(PLUGIN_CLASS)) return

    // Ensure pre has positioning for gutter
    if (!pre.style.position) {
      pre.style.position = 'relative'
    }

    var debouncedUpdate = debounce(function () {
      updateVisualLineNumbers(pre, code)
    }, 50)

    // Initial computation
    debouncedUpdate()

    // Cleanup old observer if any (in case of re-highlight)
    if (code[OBSERVER_KEY]) {
      try {
        code[OBSERVER_KEY].disconnect()
      } catch (e) {}
    }

    // Prefer ResizeObserver if available
    if (typeof ResizeObserver !== 'undefined') {
      var ro = new ResizeObserver(debouncedUpdate)
      ro.observe(code)
      code[OBSERVER_KEY] = ro
    } else {
      // Fallback: window resize listener (shared)
      // We don't attach per-element here; we just rely on Prism.hooks + resize
      if (!window.__visualLineNumbersResizeBound) {
        window.addEventListener('resize', function () {
          // On resize, re-run for all visual-line-numbers blocks
          var pres = document.querySelectorAll('pre.' + PLUGIN_CLASS + ' > code')
          for (var i = 0; i < pres.length; i++) {
            var c = pres[i]
            var p = c.parentNode
            updateVisualLineNumbers(p, c)
          }
        })
        window.__visualLineNumbersResizeBound = true
      }
    }
  }

  Prism.hooks.add('complete', function (env) {
    setupVisualLineNumbers(env)
  })
})()
