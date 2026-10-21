import { mount } from '@vue/test-utils'
import { expect, it } from 'vitest'

import Paragraph from './Paragraph.vue'

it('indents the first line only when asked', () => {
  expect(mount(Paragraph).classes()).not.toContain('indent-[0.5in]')

  const indented = mount(Paragraph, { props: { indent: true } })
  expect(indented.classes()).toContain('indent-[0.5in]')
  // lists and other blocks inside don't inherit the indent
  expect(indented.classes()).toContain('[&>:not(.contents)]:indent-0')
})
