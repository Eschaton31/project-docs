/**
 * Scrolls a rendered block into view. RichText blocks are display: contents (no box of their own, so
 * scrollIntoView does nothing on them); scroll to their first element, or the block around them.
 */
export function scrollToBlock(root, id, options) {
  const element = root?.querySelector(`[data-block-id="${CSS.escape(id)}"]`)
  if (!element) return

  const target = element.classList.contains('contents') ? (element.firstElementChild ?? element.parentElement) : element
  target?.scrollIntoView(options)
}
