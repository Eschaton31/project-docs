import { describe, expect, it } from 'vitest'

import { plainText, wordDiff } from './proseDiff.js'

/** What each side reads as, rebuilt from the parts. */
const sides = (parts) => ({
  before: parts
    .filter((part) => part.type !== 'ins')
    .map((part) => part.text)
    .join(''),
  after: parts
    .filter((part) => part.type !== 'del')
    .map((part) => part.text)
    .join(''),
})

describe('wordDiff', () => {
  it('shows a reworded phrase as one deletion then one insertion', () => {
    const parts = wordDiff('In order to install it', 'To install it')

    expect(sides(parts)).toEqual({ before: 'In order to install it', after: 'To install it' })
    expect(parts.map((part) => part.type)).toEqual(['del', 'ins', 'same'])
    expect(parts[0].text.trim()).toBe('In order to')
    expect(parts[1].text.trim()).toBe('To')
    expect(parts[2].text.trim()).toBe('install it')
  })

  it('treats punctuation as its own token', () => {
    expect(wordDiff('a database and Chrome', 'a database, and Chrome')).toEqual([
      { type: 'same', text: 'a database' },
      { type: 'ins', text: ',' },
      { type: 'same', text: ' and Chrome' },
    ])
  })

  it('keeps separate changes apart', () => {
    const parts = wordDiff('The report is generated daily by the user.', 'You generate the report daily.')

    expect(sides(parts)).toEqual({
      before: 'The report is generated daily by the user.',
      after: 'You generate the report daily.',
    })
    expect(parts.some((part) => part.type === 'same' && part.text.includes('daily'))).toBe(true)
  })

  it('handles identical and empty text', () => {
    expect(wordDiff('Same.', 'Same.')).toEqual([{ type: 'same', text: 'Same.' }])
    expect(wordDiff('', 'New')).toEqual([{ type: 'ins', text: 'New' }])
  })
})

describe('plainText', () => {
  it('drops tags and decodes entities', () => {
    expect(plainText('Run <codetext>a &amp; b</codetext>&nbsp;now<br>')).toBe('Run a & b now')
  })

  it('reads anything but a string as empty', () => {
    expect(plainText(null)).toBe('')
    expect(plainText({ id: 1 })).toBe('')
  })
})
