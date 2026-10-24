/**
 * Word-level diff for showing a suggested rewording: words, runs of spaces and single punctuation marks are
 * the units, so a change reads like an editor's markup rather than a character soup.
 *
 * @returns {{ type: 'same' | 'del' | 'ins', text: string }[]}
 */
export function wordDiff(before, after) {
  const a = tokens(before)
  const b = tokens(after)

  // longest common subsequence, filled from the end so the walk below goes forwards
  const lcs = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0))
  for (let i = a.length - 1; i >= 0; i--) {
    for (let j = b.length - 1; j >= 0; j--) {
      lcs[i][j] = a[i] === b[j] ? lcs[i + 1][j + 1] + 1 : Math.max(lcs[i + 1][j], lcs[i][j + 1])
    }
  }

  const parts = []
  let i = 0
  let j = 0
  while (i < a.length || j < b.length) {
    if (i < a.length && j < b.length && a[i] === b[j]) {
      parts.push({ type: 'same', text: a[i++] })
      j++
    } else if (j < b.length && (i === a.length || lcs[i][j + 1] >= lcs[i + 1][j])) {
      parts.push({ type: 'ins', text: b[j++] })
    } else {
      parts.push({ type: 'del', text: a[i++] })
    }
  }

  return groupChanges(parts)
}

/** Text of stored inline HTML, for reading and diffing (the markup itself is applied unchanged). */
export function plainText(html) {
  const entities = { amp: '&', lt: '<', gt: '>', quot: '"', '#39': "'", nbsp: ' ' }

  // anything but a string (a bad payload) reads as empty rather than breaking the whole panel
  return (typeof html === 'string' ? html : '')
    .replace(/<br\s*\/?>/gi, ' ')
    .replace(/<[^>]*>/g, '')
    .replace(/&(amp|lt|gt|quot|#39|nbsp);/g, (_, name) => entities[name])
    .replace(/\s+/g, ' ')
    .trim()
}

function tokens(text) {
  return (text ?? '').match(/\s+|[\p{L}\p{N}_'’-]+|[^\s\p{L}\p{N}]/gu) ?? []
}

/**
 * Reads like "In order to → To" rather than alternating fragments: a space between two changes joins them,
 * and each run of changes becomes one deletion followed by one insertion.
 */
function groupChanges(parts) {
  const joined = parts.flatMap((part, k) => {
    const between =
      part.type === 'same' && /^\s+$/.test(part.text) && parts[k - 1]?.type !== 'same' && parts[k + 1]?.type !== 'same'

    return between && k > 0 && k < parts.length - 1
      ? [
          { type: 'del', text: part.text },
          { type: 'ins', text: part.text },
        ]
      : [part]
  })

  const result = []
  let run = null
  const endRun = () => {
    if (run?.del) result.push({ type: 'del', text: run.del })
    if (run?.ins) result.push({ type: 'ins', text: run.ins })
    run = null
  }
  for (const part of joined) {
    if (part.type !== 'same') {
      run ??= { del: '', ins: '' }
      run[part.type] += part.text
      continue
    }
    endRun()
    result.at(-1)?.type === 'same' ? (result.at(-1).text += part.text) : result.push({ ...part })
  }
  endRun()

  return result
}
