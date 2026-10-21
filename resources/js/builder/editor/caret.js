/** Puts the caret in `element` where the user clicked (client coordinates), or at its end. */
export function placeCaret(element, coords) {
  const selection = window.getSelection()
  let range = null

  if (coords && document.caretRangeFromPoint) {
    range = document.caretRangeFromPoint(coords.x, coords.y)
  } else if (coords && document.caretPositionFromPoint) {
    const position = document.caretPositionFromPoint(coords.x, coords.y)
    if (position) {
      range = document.createRange()
      range.setStart(position.offsetNode, position.offset)
    }
  }
  if (!range || !element.contains(range.startContainer)) {
    range = document.createRange()
    range.selectNodeContents(element)
    range.collapse(false)
  }

  selection?.removeAllRanges()
  selection?.addRange(range)
}

const INLINE_TAGS = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'A', 'CODETEXT', 'BR'])
// by code, so formatters can't turn it into an invisible literal character
const NBSP = String.fromCharCode(0xa0)

/**
 * HTML typed into a contenteditable cell, reduced to the inline tags the pages use: blocks the browser
 * inserts become line breaks, other tags are unwrapped, attributes go (except a link's href/class), and
 * non-breaking spaces from typing become plain spaces.
 */
export function cleanInlineHtml(html) {
  const template = document.createElement('template')
  template.innerHTML = html.replaceAll('&nbsp;', ' ').replaceAll(NBSP, ' ')

  const walk = (parent) => {
    for (const node of [...parent.childNodes]) {
      if (node.nodeType === Node.COMMENT_NODE) {
        node.remove()
        continue
      }
      if (node.nodeType !== Node.ELEMENT_NODE) continue

      walk(node)
      if (node.tagName === 'DIV' || node.tagName === 'P') {
        if (node.previousSibling) node.before(document.createElement('br'))
        node.replaceWith(...node.childNodes)
      } else if (!INLINE_TAGS.has(node.tagName)) {
        node.replaceWith(...node.childNodes)
      } else {
        for (const { name, value } of [...node.attributes]) {
          const keep = node.tagName === 'A' && (name === 'href' || (name === 'class' && value === 'text-link'))
          if (!keep) node.removeAttribute(name)
        }
      }
    }
  }
  walk(template.content)

  return template.innerHTML
}
