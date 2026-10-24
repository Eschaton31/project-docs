/**
 * The DOM Range covering `needle` in the text under `root`, or null. Runs of whitespace count as one space on both
 * sides, so text split across elements (bold, links, line breaks in the source) is still found.
 */
export function findTextRange(root, needle) {
  const wanted = (needle ?? '').replace(/\s+/g, ' ').trim()
  if (!root || !wanted) return null

  // the collapsed text, and for each of its characters the text node and offset it came from
  let flat = ''
  const at = []
  const walker = root.ownerDocument.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    for (let i = 0; i < node.data.length; i++) {
      const char = /\s/.test(node.data[i]) ? ' ' : node.data[i]
      if (char === ' ' && (flat === '' || flat.endsWith(' '))) continue
      flat += char
      at.push([node, i])
    }
  }

  const start = flat.indexOf(wanted)
  if (start === -1) return null

  const range = root.ownerDocument.createRange()
  const [startNode, startOffset] = at[start]
  const [endNode, endOffset] = at[start + wanted.length - 1]
  range.setStart(startNode, startOffset)
  range.setEnd(endNode, endOffset + 1)

  return range
}
