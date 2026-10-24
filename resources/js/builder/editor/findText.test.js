import { describe, expect, it } from 'vitest'

import { findTextRange } from './findText.js'

const element = (html) => Object.assign(document.createElement('div'), { innerHTML: html })

describe('findTextRange', () => {
  it('finds a phrase inside one text node', () => {
    const root = element('<p>In order to install it, you need PHP.</p>')

    expect(findTextRange(root, 'you need PHP').toString()).toBe('you need PHP')
  })

  it('finds a phrase that spans formatting and odd whitespace', () => {
    const root = element('<p>Run <codetext>composer\n   install</codetext> to <b>set it</b> up.</p>')

    expect(findTextRange(root, 'Run composer install to set it up').toString()).toBe(
      'Run composer\n   install to set it up'
    )
  })

  it('returns null when the text is not there, or there is nothing to find', () => {
    const root = element('<p>Something else.</p>')

    expect(findTextRange(root, 'missing')).toBe(null)
    expect(findTextRange(root, '  ')).toBe(null)
    expect(findTextRange(null, 'x')).toBe(null)
  })
})
