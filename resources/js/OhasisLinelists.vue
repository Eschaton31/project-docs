<script setup>
import { format } from 'date-fns'
import { Handler, Previewer } from 'pagedjs'

import { onMounted } from 'vue'

import { RepeatTableHeaderHandler } from '@/composables/functions.js'

import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTitle from '@/components/TertiaryTitle.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import TitlePage from '@/components/TitlePage.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

import HaspLinelistInventory from '@/pages/ohasis-linelists/HaspLinelistInventory.vue'
import ProfileAlgorithm from '@/pages/ohasis-linelists/ProfileAlgorithm.vue'
import RealTimeDataDictionary from '@/pages/ohasis-linelists/RealTimeDataDictionary.vue'
import RealTimeLinelistCatalog from '@/pages/ohasis-linelists/RealTimeLinelistCatalog.vue'

const audience = [
  { id: 'Primary', value: 'NHSSS Unit, RESU' },
  {
    id: 'Secondary',
    value:
      'Data managers; data analysts; monitoring and evaluation officers; system administrators; public health stakeholders involved in reporting, integration, or governance',
  },
]

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

const outputs = [
  {
    id: 'Surveillance Pipelines',
    value: 'Produces surveillance‑specific datasets and indicators used by NHSSS for monitoring and reporting.',
    children: [],
  },
  {
    id: 'Change Data Capture',
    value:
      'Tracks and propagates incremental changes from the EHR into the LW. Captures inserts/updates/deletes and enables low‑latency synchronization for near‑real‑time use cases.',
    children: [],
  },
]
</script>

<template>
  <div ref="doc" class="flex h-full flex-col">
    <title>oh-linelists-handbook_{{ date }}</title>
    <span class="title">
      <span class="text-[12pt] font-bold text-[#dcdcdc]">OHASIS LINELISTS | </span>
      <span class="text-[12pt] font-medium text-[#a1a1a1]">HANDBOOK</span>
    </span>
    <TitlePage
      client="National HIV/AIDS-STI Surveillance & Strategic Information Unit"
      date-submitted="September 4, 2026"
      project="One HIV/AIDS-STI Information System"
      submitted-by="John Benedict Palo (Data Specialist)"
      submitted-to="Noel Palaypayon (Unit Manager)"
      subtitle="Handbook"
      title="OHASIS Linelists"
      version="1.0" />

    <div id="toc-page" class="toc-page flex h-full w-full flex-col gap-4">
      <div class="flex shrink-0 text-[14pt] font-bold text-bene-teal">TABLE OF CONTENTS</div>
    </div>

    <PrimaryTopic title="OVERVIEW">
      <Paragraph>
        The One HIV/AIDS‑STI Information System (OHASIS) is the Department of Health Epidemiology Bureau’s National
        HIV/AIDS‑STI Surveillance & Strategic Information Unit (NHSSS) primary data management and analytics platform
        for monitoring HIV and STI health indicators across the Philippines. This document provides a handbook reference
        that can be used by data consumers in understanding and utilizing its various exportable linelists.
      </Paragraph>
      <Paragraph>
        <TertiaryTitle>Scope</TertiaryTitle>
        This document covers system architecture, data flows, variable definitions and quality checks required to
        understand the various linelists. It also explains the rationale and trade‑offs behind key design choices so
        implementers and stakeholders understand constraints and assumptions.
      </Paragraph>
      <Paragraph>
        <TertiaryTitle>Intended audience</TertiaryTitle>
        <ConceptList :list="audience" />
      </Paragraph>
    </PrimaryTopic>
    <PrimaryTopic title="WORKFLOW DESIGN">
      <Paragraph>
        This section outlines the systems and processes that enable the linelists to operate reliably. It provides a
        structured overview of the core technologies, data flows, and components that work together to support the
        generation, updating, and delivery of linelist outputs.
      </Paragraph>
      <SecondaryTopic title="Architecture">
        <Paragraph>
          The linelists are a core element of the OHASIS data‑pipeline architecture. They are generated and stored in
          the same databases that house outputs from the <u>Surveillance Pipelines</u> and the
          <u>Change Data Capture</u> processes, ensuring seamless alignment with the platform’s real‑time ingestion,
          transformation, and synchronization workflows. This integration also supports the NHSSS Unit’s surveillance
          data generation, enabling for a unified linelist production across the system.
          <ConceptList :list="outputs" class="mt-2" />
        </Paragraph>

        <Image
          description="Overview of the data warehousing architecture of the OHASIS Ecosystem"
          height="h-72"
          src="/img/diagrams/pipeline-architecture.png" />
      </SecondaryTopic>
      <SecondaryTopic start="2" title="Technology Stack">
        <Paragraph>
          The system is directly integrated with the ClickHouse (OLAP) databases that underpin the OHASIS platform. To
          support the real‑time generation of linelists, <b>PeerDB</b> serves as the intermediary responsible for
          executing the <i>Change Data Capture (CDC)</i> process, continuously streaming and replicating events from the
          Electronic Health Records (EHR) into the Lake/Warehouse (LW) with near‑real‑time latency. This architecture
          ensures timely, synchronized updates across all surveillance and reporting workflows.
        </Paragraph>
        <Image
          description="CDC replication allows for LW use within 30 seconds of record creation or updating"
          height="h-48"
          src="/img/screenshots/ohasis-linelists/peerdb-sync.png" />

        <TertiaryTopic title="Data Management Module">
          <Paragraph>
            The linelists serve as an extension of the existing Data Management module within OHASIS. They are fully
            integrated into the platform’s interface and accessible through the sidebar for all authorized OHASIS
            accounts, providing a seamless addition to the system’s core data export capabilities.
          </Paragraph>
          <Image
            description="Screenshot of the Data Management Module in the OHASIS EHR"
            height="h-[15rem]"
            src="/img/screenshots/ohasis-linelists/data-management-module.png" />
        </TertiaryTopic>
      </SecondaryTopic>
    </PrimaryTopic>

    <ProfileAlgorithm />

    <HaspLinelistInventory />

    <RealTimeDataDictionary />

    <RealTimeLinelistCatalog />
  </div>
</template>
