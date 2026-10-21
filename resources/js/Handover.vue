<script setup>
import { Previewer } from 'pagedjs'

import { onMounted } from 'vue'

import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTitle from '@/components/TertiaryTitle.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import TitlePage from '@/components/TitlePage.vue'
import BulletList from '@/components/lists/BulletList.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

import Infrastructure from '@/pages/handover/Infrastructure.vue'
import Services from '@/pages/handover/Services.vue'

const objectives = [
  'To provide a general framework for how the ecosystem works',
  'Detail references for new administrators to investigate',
  'Link to resources used in the development of the ecosystem',
  'Give a sense of the skill sets and manpower to keep the system operational',
]

const status = [
  {
    id: 'Systems',
    value: 'Production web and database servers for OHASIS and its supporting services are provisioned and running.',
  },
  {
    id: 'Deliverables handed over',
    value:
      'Server and service inventory, deployment notes, backup automation, high‑level runbooks, and references to codebase and storage locations.',
  },
  {
    id: 'Access ',
    value:
      'Core accounts consolidated; credentials stored in the a VeraCrypt encrypted file (sensitive details intentionally omitted from this document).',
  },
]

const risks = [
  {
    id: 'Single points of failure',
    value:
      'Key services and administrative access are concentrated on a small set of hosts and accounts; hardware or credential loss could cause prolonged outages.',
  },
  {
    id: 'Credential and access hygiene',
    value:
      'Administrative credentials need immediate review and rotation; lack of formalized access review cadence increases exposure.',
  },
  {
    id: 'Operational knowledge gap',
    value:
      'Several operational procedures and runbooks exist at a high level but require hands‑on validation and staff training to ensure continuity.',
  },
]

const recommendations = [
  {
    id: 'Authorize credential rotation',
    value:
      'Approve and fund an immediate rotation of all administrative credentials and MFA enforcement for critical accounts.',
  },
  {
    id: 'Assign redundancy owners',
    value:
      'Designate at least two named administrators for each critical service (web, DB, replication, backups) and confirm remote/on‑site access.',
  },
  {
    id: 'Optimize knowledge transfer',
    value: 'Standardize a knowledge transfer framework for existing staff and onboarding procedures of future staff.',
  },
  {
    id: 'Streamline teams',
    value: 'Re-organize structure to improve delineation between workstreams and scopes.',
  },
]

const paged = new Previewer()

const startPagedJs = () => {
  paged.preview().then(() => {
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
</script>

<template>
  <div ref="doc" class="flex h-full flex-col">
    <title>Work Outline for Endorsement</title>
    <span class="title">
      <span class="text-[12pt] font-bold text-[#dcdcdc]">OHASIS | </span>
      <span class="text-[12pt] font-medium text-[#a1a1a1]">HANDOVER DOCUMENT</span>
    </span>
    <TitlePage
      client="National HIV/AIDS-STI Surveillance & Strategic Information Unit"
      date-submitted="January 15, 2026"
      project="One HIV/AIDS-STI Information System"
      submitted-by="John Benedict Palo (Data Specialist)"
      submitted-to="Noel Palaypayon (Unit Manager)"
      subtitle="Handover Document"
      title="OHASIS"
      version="1.0" />

    <div id="toc-page" class="toc-page flex h-full w-full flex-col gap-4">
      <div class="flex shrink-0 text-[14pt] font-bold text-bene-teal">TABLE OF CONTENTS</div>
    </div>

    <PrimaryTopic title="OVERVIEW">
      <Paragraph>
        The One HIV/AIDS-STI Information System, or OHASIS, is the primary data management and analytics ecosystem
        utilized by the Department of Health - Epidemiology Bureau’s (DOH-EB) National HIV/AIDS-STI Surveillance &
        Strategic Information Unit (NHSSS) to fulfill its mandate of monitoring HIV & STI health data in the
        Philippines.
      </Paragraph>
      <Paragraph>
        First established in 2021, the system has grown into a complex and robust ecosystem of tools and products that
        all interconnect and play a major role in the various outputs of the NHSSS. This document will provide some
        guidance on these products and how each of them play with each other.
      </Paragraph>
      <Paragraph>
        OHASIS administration can be divided into four major umbrellas: Policy & Governance, Infrastructure, Data
        Security & Integrity, and Development & Programming. Each umbrella requires its own set of stratification that
        handles major features/elements that make the OHASIS ecosystem operational.
      </Paragraph>
    </PrimaryTopic>

    <PrimaryTopic title="OBJECTIVES">
      <Paragraph>
        The aim of this document can be outlined as follows:
        <BulletList :list="objectives" class="ml-2" />
      </Paragraph>
      <Paragraph>
        This document does not aim to be a comprehensive manual in the administration of the ecosystem. Any and all
        concerns regarding manpower and team composition should be addressed to DOH-EB.
      </Paragraph>
    </PrimaryTopic>
    <Infrastructure />

    <Services />

    <PrimaryTopic title="DOCUMENTS">
      <SecondaryTopic start="1" title="Codebase">
        <TertiaryTopic title="Local Files" />
        <TertiaryTopic title="Cloud Storage" />
        <TertiaryTopic title="Version Control" />
      </SecondaryTopic>
      <SecondaryTopic start="2" title="Applications" />
    </PrimaryTopic>
  </div>
</template>
