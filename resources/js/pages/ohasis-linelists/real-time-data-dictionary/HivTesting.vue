<script setup>
import ConversionDictionary from '@/components/tables/ConversionDictionary.vue'
import ItemDescriptionTable from '@/components/tables/ItemDescriptionTable.vue'

import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import NumberedList from '@/components/lists/NumberedList.vue'

defineProps({
  start: [String, Number],
})

const columns = {
  hivTesting: [
    {
      item: '*_faci',
      description: ['Facility ID', 'Only available when <b>Convert Data</b> is off'],
      formats: ['String', 'See: OHASIS facilities dataset', 'Format: <codetext>######</codetext>'],
    },
    {
      item: '*_sub_faci',
      description: ['Sub-unit ID', 'Only available when <b>Convert Data</b> is off'],
      formats: ['String', 'See: OHASIS facilities dataset', 'Format: <codetext>######_####</codetext>'],
    },
    {
      item: '*_lab',
      description: [
        'Converted name from the facility and sub-unit ID',
        'Only available when <b>Convert Data</b> is on',
      ],
      formats: ['String', 'See: OHASIS facilities dataset'],
    },
    {
      item: '*_date',
      description: 'Date when test was conducted',
      formats: ['Date', 'Format <codetext>yyyy-mm-dd</codetext>'],
    },
    { item: '*_modality', description: 'HIV testing modality', formats: 'String' },
    { item: '*_result', description: 'HIV test result', formats: 'String' },
  ],
}

const sort = {
  result: ['Reactive', 'Non-reactive', 'Inconclusive', 'Missing (<codetext>NULL</codetext>) results'],
  modality: ['FBT', 'CBS', 'FBS', 'ST', 'Reach', 'Missing (<codetext>NULL</codetext>) modalities'],
}

const categories = {
  result: [
    { item: '1', description: 'Reactive' },
    { item: '2', description: 'Non-reactive' },
    { item: '3', description: 'Inconclusive' },
  ],
  modality: [
    {
      item: '101101',
      description: [
        'FBT',
        'Facility-based testing',
        'Testing conducted by a service provider in a licensed HIV testing facility',
      ],
    },
    { item: '101103', description: ['CBS', 'Community-based screening', 'HIV tests conducted by community partners'] },
    {
      item: '101104',
      description: ['FBS', 'Facility-based screening', 'HIV tests conducted by non-licensed HIV testing facilities'],
    },
    { item: '101105', description: ['ST', 'Self-testing', 'HIV Test that can be used at home or in private'] },
    {
      item: '101304',
      description: [
        'Reach',
        'Tests without a result or clients who declined testing',
        'HIV activities where risk assessment, HIV 101, condom/lube distribution is done (no test)',
      ],
    },
  ],
}

const hivTestPrefix = [
  { item: 'earliest', description: 'Earliest HIV Test' },
  { item: 'latest', description: 'Latest HIV Test' },
]

const screenshots = {
  hivTesting: [
    {
      src: '/img/screenshots/ohasis-linelists/t0-field.png',
      description: 'The T0 field found in the HIV Testing Forms or the HIV Screening module',
      height: 'h-[10rem]',
    },
    {
      src: '/img/screenshots/ohasis-linelists/t1-field.png',
      description: 'The T1 field found in the rHIVda module',
      height: 'h-[11rem]',
    },
    {
      src: '/img/screenshots/ohasis-linelists/sti-hiv-field.png',
      description: 'The HIV screening field found in the STI form',
      height: 'h-[5rem]',
    },
  ],
}
</script>

<template>
  <SecondaryTopic :start title="HIV Testing">
    <Image
      v-for="({ src, description, height }, index) in screenshots.hivTesting"
      :key="index"
      :description
      :height
      :src />
    <Paragraph>
      Derived from records containing a <b>T0</b> test, <b>T1</b> test, <b>HIV screening</b> test,
      <or></or>
      <b>HIV testing form</b>. These are organized into two (2) contexts: <b>earliest</b> and <b>latest</b>.
    </Paragraph>
    <ItemDescriptionTable :data="hivTestPrefix" description-name="Testing Context" item-name="Prefix " mono />

    <Paragraph> Each context comes with its corresponding facility, date, modality, and result.</Paragraph>
    <ConversionDictionary :data="columns.hivTesting" mono />

    <TertiaryTopic title="Prioritization Matrix">
      <Paragraph>
        Valid testing records are pre‑qualified through a prioritization algorithm that evaluates the test result and
        associated testing modality. Only records that pass this step proceed to the context‑specific selection process.
        The prioritization matrix is applied in the following order: <b>Result</b> → <b>Modality</b> → <b>Test Date</b>.
      </Paragraph>
      <Image
        description="Overview of the HIV test prioritization matrix"
        height="h-[15rem]"
        src="/img/diagrams/hiv-test-sort-2.png" />

      <QuaternaryTopic title="Result">
        <Paragraph>
          The <codetext>*_result</codetext> variables represent the client’s HIV test result for the given context.
          Because most analyses prioritize identifying reactive cases, records with a <b>Reactive</b> result are ranked
          highest during selection. The prioritization sequence is applied as follows:
          <NumberedList :list="sort.result" />
        </Paragraph>
        <Paragraph> These variables contain categorical values defined as follows:</Paragraph>
        <ItemDescriptionTable :data="categories.result" description-name="Converted" item-name="Default " mono />
      </QuaternaryTopic>

      <QuaternaryTopic title="Modality">
        <Paragraph>
          The <codetext>*_modality</codetext> variables represent the client’s HIV testing modality for the given
          context. When multiple records share the same test result within the defined scope, these fields are used to
          determine which testing mode should be prioritized for selection. The prioritization sequence is applied as
          follows:
          <NumberedList :list="sort.modality" />
        </Paragraph>
        <Paragraph> These variables contain categorical values defined as follows:</Paragraph>
        <ItemDescriptionTable :data="categories.modality" description-name="Converted" item-name="Default " mono />
      </QuaternaryTopic>
    </TertiaryTopic>
  </SecondaryTopic>
</template>
