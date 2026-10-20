<script setup>
import { format } from 'date-fns'
import { Previewer } from 'pagedjs'

import { onMounted } from 'vue'

import { RepeatTableHeaderHandler } from '@/composables/functions.js'

import ItemDescriptionTable from '@/components/tables/ItemDescriptionTable.vue'

import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTitle from '@/components/TertiaryTitle.vue'
import TitlePage from '@/components/TitlePage.vue'
import ConceptList from '@/components/lists/ConceptList.vue'
import NumberedList from '@/components/lists/NumberedList.vue'

const audience = [{ id: 'Primary', value: 'Data Quality Team (NHSSS Unit)' }]

const previewer = new Previewer()
previewer.registerHandlers(RepeatTableHeaderHandler)

const startPagedJs = () => {
  previewer.preview().then(() => {
    const savedPosition = localStorage.getItem('scrollPosition')
    if (savedPosition) {
      window.scrollTo(0, parseInt(savedPosition))
      localStorage.removeItem('scrollPosition') // Optional: clear after use
    }
  })
}

onMounted(() => {
  window.addEventListener('load', function () {
    startPagedJs()
  })
  window.addEventListener('hmr-reload', () => {
    localStorage.setItem('scrollPosition', window.scrollY)
    location.reload()
  })
})

const date = format(new Date(), 'yyyyMMdd')

const migrationSteps = [
  'Offline of the current OHASIS live servers',
  'Data & system migration to the new servers',
  'Integrity checks and validations that the migrated system is working as intended',
  'Reconfiguration of the existing server infrastructure',
  'Installation of the identified servers to KMITS',
  'Go live with the migrated infrastructure',
]

const timeline = [
  { item: 'September 7, 2026', description: ['Installation at DOH-EB', 'Initial planning'] },
  {
    item: 'September 10, 2026',
    description: ['Routing of service request form (SRF) to KMITS', 'Advisory from DOH-EB regarding OHASIS down-time'],
  },
  { item: 'September 16, 2026', description: 'OHASIS servers will be turned off' },
  { item: 'September 16-17, 2026', description: 'Migration proper' },
  { item: 'September 18, 2026', description: 'KMITS installation & Go Live' },
  { item: 'September 21, 2026', description: 'Advisory from DOH-EB regarding new link and Go Live' },
]
</script>

<template>
  <div ref="doc" class="flex h-full flex-col">
    <title>oh-server-migration-2026_{{ date }}</title>
    <span class="title">
      <span class="text-[12pt] font-bold text-[#dcdcdc]">OHASIS SERVERS | </span>
      <span class="text-[12pt] font-medium text-[#a1a1a1]">MIGRATION PLAN</span>
    </span>
    <TitlePage
      client="National HIV/AIDS-STI Surveillance & Strategic Information Unit"
      date-submitted="September 8, 2026"
      project="One HIV/AIDS-STI Information System"
      submitted-by="John Benedict Palo (Data Specialist)"
      submitted-to="Noel Palaypayon (Unit Manager)"
      subtitle="Migration Plan"
      title="OHASIS Servers"
      version="1.0" />

    <div id="toc-page" class="toc-page flex h-full w-full flex-col gap-4">
      <div class="flex shrink-0 text-[14pt] font-bold text-bene-teal">TABLE OF CONTENTS</div>
    </div>

    <PrimaryTopic title="OVERVIEW">
      <Paragraph>
        The One HIV/AIDS‑STI Information System (OHASIS) is the Department of Health Epidemiology Bureau’s National
        HIV/AIDS‑STI Surveillance & Strategic Information Unit (NHSSS) primary data management and analytics platform
        for monitoring HIV and STI health indicators across the Philippines.
      </Paragraph>
      <Paragraph>
        With the recent expansion of hosting hardware and infrastructure, this document outlines the overall migration
        plan and timeline for transitioning OHASIS to its upgraded environment. It also details the corresponding
        changes in application hosting architecture and system nomenclature to ensure a smooth and well‑coordinated
        transition.
      </Paragraph>
      <Paragraph>
        <TertiaryTitle>Intended audience</TertiaryTitle>
        <ConceptList :list="audience" />
      </Paragraph>
    </PrimaryTopic>

    <PrimaryTopic title="TIMELINE & PLANS">
      <SecondaryTopic start="1" title="Installation">
        <Paragraph>
          The servers donated by the EpiC‑HIV Project of FHI 360 were delivered and installed at the Department of
          Health – Epidemiology Bureau (DOH‑EB) on <u>September 7, 2026</u>. The units were deployed in the National
          HIV/AIDS‑STI Surveillance & Strategic Information (NHSSS) Unit’s data center.
        </Paragraph>
        <Paragraph>
          The installation covered the successful delivery of two (2) HPE ProLiant DL20 Gen11 servers, mounting both
          units into the NHSSS server rack, and completing initial power‑on and hardware verification. The servers are
          now physically integrated into the unit’s infrastructure and ready for configuration and migration activities.
        </Paragraph>
      </SecondaryTopic>
      <SecondaryTopic start="2" title="Planning">
        <Paragraph>
          Following the completion of the initial migration activities, a planning session was convened with the Data
          Quality Team (DQT) of the NHSSS Unit to formally begin the server migration process. During this session, the
          team identified the required activities and established a timeline for completing the full migration.
          <NumberedList :list="migrationSteps" />
        </Paragraph>
        <Paragraph>
          With the addition of the two (2) new servers, the NHSSS Unit now maintains a total of eight (8) operational
          servers. As part of the migration process, server naming conventions and ZeroTier IP assignments will be
          reorganized and standardized to improve manageability, consistency, and ease of identification across the
          environment.
        </Paragraph>
        <Image
          description="The final configuration of the eight NHSSS Unit servers"
          height="h-[20rem]"
          src="/img/diagrams/epic-server-migration.png" />
        <Paragraph>
          A dedicated planning session will be scheduled to finalize the hosting architecture for all applications used
          by the NHSSS Unit. This session will also determine the configuration of the web servers, database servers,
          and the data warehouse to ensure a consistent, optimized, and fully aligned hosting environment.
        </Paragraph>
      </SecondaryTopic>
      <SecondaryTopic start="3" title="KMITS">
        <Paragraph>
          Installation at the DOH Data Center, in coordination with the Knowledge Management & Information Technology
          Service (KMITS), will cover the deployment of four (4) servers: the two (2) units donated by the EpiC‑HIV
          Project and the two (2) servers currently hosted in the NHSSS Unit’s data center. The activity will also
          include the pull‑out of the two (2) existing servers presently housed in KMITS.
        </Paragraph>
        <Paragraph>
          KMITS will oversee the physical installation within the data center and manage all required network
          configuration tasks. As part of the transition, a new domain name (<codetext>ohasis.doh.gov.ph</codetext>)
          will be assigned, replacing the current (<codetext>ebreports‑shc.doh.gov.ph</codetext>) domain.
        </Paragraph>
      </SecondaryTopic>
      <SecondaryTopic start="4" title="Timeline">
        <Paragraph> The timeline of activities for the migration and installation process is outlined below:</Paragraph>
        <ItemDescriptionTable :data="timeline" description-name="Activities" item-name="Dates" />
      </SecondaryTopic>
    </PrimaryTopic>
  </div>
</template>
