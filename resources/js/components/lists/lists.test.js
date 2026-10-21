import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

import BulletList from './BulletList.vue'
import NumberedList from './NumberedList.vue'

describe.each([
  ['BulletList', BulletList],
  ['NumberedList', NumberedList],
])('%s', (_, List) => {
  it('renders a sub-list inside the item before it', () => {
    const wrapper = mount(List, { props: { list: ['First', ['First.1', 'First.2'], 'Second'] } })
    const items = wrapper.findAll(':scope > li')

    expect(items).toHaveLength(2)
    expect(items[0].find('li').text()).toBe('First.1')
    expect(items[0].findAll('li').map((li) => li.text())).toEqual(['First.1', 'First.2'])
    expect(items[1].text()).toBe('Second')
  })

  it('bolds the top-level items only when asked', () => {
    expect(mount(List, { props: { list: ['a'] } }).classes()).not.toContain('font-bold')

    const wrapper = mount(List, { props: { list: ['a', ['b']], bold: true } })
    expect(wrapper.classes()).toContain('font-bold')
    // the sub-list doesn't inherit it
    expect(wrapper.find('li > ul, li > ol').classes()).toEqual(expect.arrayContaining(['font-normal']))
  })

  it('bolds a sub-list on its own', () => {
    const wrapper = mount(List, { props: { list: ['a', { bold: true, list: ['b', ['c']] }] } })
    const [inner, innermost] = wrapper.findAll('li > ul, li > ol')

    expect(wrapper.classes()).not.toContain('font-bold')
    expect(inner.classes()).toContain('font-bold')
    expect(inner.find('li > span').text()).toBe('b')
    expect(innermost.classes()).toContain('font-normal')
  })
})

it('numbers 1 2 3, then a b c, then i ii iii, then starts over', () => {
  const wrapper = mount(NumberedList, { props: { list: ['one', ['a', ['i', ['deeper']]]] } })
  const markers = [wrapper, ...wrapper.findAll('li > ol')].map((list) =>
    list.classes().find((name) => name.startsWith('list-') && name !== 'list-outside')
  )

  expect(markers).toEqual(['list-decimal', 'list-[lower-alpha]', 'list-[lower-roman]', 'list-decimal'])
})

it('numbers items past their sub-lists', () => {
  const wrapper = mount(NumberedList, { props: { list: [['orphan'], 'one', ['one.1'], 'two'] } })

  expect(wrapper.findAll(':scope > li').map((li) => li.attributes('value'))).toEqual([undefined, '1', '2'])
})
