<script setup>
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'

import Infrastructure from '@/pages/data-management/setup/Infrastructure.vue'
import UserAccessControl from '@/pages/data-management/setup/UserAccessControl.vue'
import VersionControl from '@/pages/data-management/setup/VersionControl.vue'
</script>

<template>
  <PrimaryTopic title="SET-UP">
    <Paragraph>
      This section describes the baseline requirements that must be met for the data management systems to operate
      reliably, securely, and predictably. It covers requirements that apply to both the EHR‑side and the
      Lake/Warehouse‑side; system‑specific needs are documented in their respective sections.
    </Paragraph>
    <Paragraph>
      These baseline requirements form the foundation for reliable operations; subsequent sections provide
      implementation details and environment‑specific constraints.
    </Paragraph>

    <Infrastructure />

    <UserAccessControl />

    <VersionControl />
  </PrimaryTopic>
</template>
