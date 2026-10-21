/**
 * BulletList/NumberedList `list` values: strings (items, HTML allowed) and sub-lists. A sub-list is an
 * array, or { bold: true, list: [...] } when it's bold (the top-level list has its own `bold` prop).
 */
export const isSublist = (item) => Array.isArray(item) || Array.isArray(item?.list)
export const sublistItems = (item) => (Array.isArray(item) ? item : item.list)
export const sublistBold = (item) => !Array.isArray(item) && !!item.bold
export const makeSublist = (items, bold) => (bold ? { bold: true, list: items } : items)

/**
 * The list as rendered entries: a sub-list belongs to the item before it (['a', ['a.1'], 'b'] → a with
 * children [a.1], then b). A sub-list with no item before it (first, or right after another sub-list)
 * gets an entry of its own, without a value.
 *
 * @returns {{ key: number, path: number[] | null, value?: string, children: { list: Array, path: number[], bold: boolean } | null }[]}
 */
export function listEntries(list, path = []) {
  const entries = []

  ;(list ?? []).forEach((item, index) => {
    const at = [...path, index]
    const last = entries.at(-1)
    if (!isSublist(item)) return entries.push({ key: index, path: at, value: item, children: null })

    const children = { list: sublistItems(item), path: at, bold: sublistBold(item) }
    if (last && !last.children) last.children = children
    else entries.push({ key: index, path: null, children })
  })

  return entries
}
