<script setup>
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'

import HivTesting from '@/pages/ohasis-linelists/real-time-data-dictionary/HivTesting.vue'
import PersonallyIdentifiableInformation from '@/pages/ohasis-linelists/real-time-data-dictionary/PersonallyIdentifiableInformation.vue'
</script>

<template>
  <PrimaryTopic title="REAL-TIME DATA DICTIONARY">
    <Paragraph>
      This section introduces the variable groups used across the real‑time linelists and explains how the system
      assembles each variable through its underlying generation process and algorithm.
    </Paragraph>

    <PersonallyIdentifiableInformation start="1" />

    <HivTesting start="2" />

    <SecondaryTopic start="3" title="HIV Confirmatory">
      <Image
        description="The HIV confirmatory results in the confirmatory module"
        height="h-[10rem]"
        src="/img/screenshots/ohasis-linelists/confirmatory-result-field.png" />
    </SecondaryTopic>
    <SecondaryTopic start="4" title="HIV Treatment"></SecondaryTopic>
    <SecondaryTopic start="5" title="HIV Prevention"></SecondaryTopic>
    <SecondaryTopic start="6" title="Behavioral Risk"></SecondaryTopic>
    <SecondaryTopic start="7" title="Key Population"></SecondaryTopic>
    <SecondaryTopic start="8" title="Other Services"></SecondaryTopic>
    <SecondaryTopic start="9" title="Reach Type"></SecondaryTopic>
    <SecondaryTopic start="10" title="Laboratories"></SecondaryTopic>
  </PrimaryTopic>
</template>
