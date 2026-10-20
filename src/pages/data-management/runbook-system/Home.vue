<script setup>
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'

import Initialization from '@/pages/data-management/runbook-system/Initialization.vue'
import QueryBuilder from '@/pages/data-management/runbook-system/QueryBuilder.vue'
import Structure from '@/pages/data-management/runbook-system/Structure.vue'
</script>

<template>
  <PrimaryTopic class="new-page" start="4" title="RUNBOOK SYSTEM">
    <Paragraph>
      This section describes the core concepts and processes that underpin the runbook system, explaining its overall
      structure, how callbacks operate, and the key functions that keep the system reliable and auditable. It will
      provide a high-level layout of the runbooks and its orchestration design, as well as the interactions invoked by
      the runbooks to the EHR and external services.
    </Paragraph>

    <Structure start="1" />

    <Initialization start="2" />

    <SecondaryTopic start="3" title="Classes & Wrappers">
      <Paragraph>
        This section describes the top‑level classes produced by the autoloader, explains the role of each class within
        the pipelines, and examines the prototype templating system that underpins existing workflows. It also offers
        practical recommendations for improving and migrating those prototypes into a more maintainable architecture.
      </Paragraph>
      <QueryBuilder />
      <TertiaryTopic title="DB"></TertiaryTopic>
      <TertiaryTopic title="flow"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="4" title="Important Functions"></SecondaryTopic>
    <SecondaryTopic start="5" title="Helper Functions"></SecondaryTopic>
  </PrimaryTopic>
</template>
