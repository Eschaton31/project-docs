<script setup>
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
</script>

<template>
  <PrimaryTopic title="HIV CASE REGISTRY">
    <Paragraph>
      A case based surveillance that holds the official record of HIV positive individuals that were confirmed by the
      National Reference Laboratory – San Lazaro Hospital STD AIDS Cooperative Central Laboratory (NRL-SLH/SACCL) or
      testing facilities that were accredited by NRL-SLH/SACCL as a Certified Rapid HIV Confirmatory Laboratories
      (CrCL).
    </Paragraph>
    <SecondaryTopic title="Data Catalog">
      <TertiaryTopic title="Correction Tables">
        <QuaternaryTopic title="corr_reg"></QuaternaryTopic>
        <QuaternaryTopic title="corr_drop"></QuaternaryTopic>
        <QuaternaryTopic title="corr_defer"></QuaternaryTopic>
        <QuaternaryTopic title="corr_classd"></QuaternaryTopic>
        <QuaternaryTopic title="corr_dxlab"></QuaternaryTopic>
      </TertiaryTopic>
      <TertiaryTopic title="Reference Tables"></TertiaryTopic>
      <TertiaryTopic title="Final Tables"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic title="Set-up">
      <TertiaryTopic title="Installation"></TertiaryTopic>
      <TertiaryTopic title="Source Files"></TertiaryTopic>
      <TertiaryTopic title="Endpoints"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic title="Pipeline">
      <TertiaryTopic title="load_reqs"></TertiaryTopic>
      <TertiaryTopic title="hts_tst_pos"></TertiaryTopic>
      <TertiaryTopic title="logsheet_saccl"></TertiaryTopic>
      <TertiaryTopic title="logsheet_recency"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic title="Surveillance Validation"></SecondaryTopic>
  </PrimaryTopic>
</template>
