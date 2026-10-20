<script setup>
import { format } from 'date-fns'
import { Previewer } from 'pagedjs'

import { onMounted } from 'vue'

import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTitle from '@/components/TertiaryTitle.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import TitlePage from '@/components/TitlePage.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

import HarpDx from '@/pages/data-management/HarpDx.vue'
import PipelineDesign from '@/pages/data-management/PipelineDesign.vue'
import SurveillancePipelines from '@/pages/data-management/SurveillancePipelines.vue'
import RunbookSystem from '@/pages/data-management/runbook-system/Home.vue'
import Setup from '@/pages/data-management/setup/Home.vue'

const audience = [
  { id: 'Primary', value: 'Data Management Team, NHSSS Unit' },
  {
    id: 'Secondary',
    value:
      'Data managers; data analysts; monitoring and evaluation officers; system administrators; public health stakeholders involved in reporting, integration, or governance',
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

const date = format(new Date(), 'yyyyMMdd')
</script>

<template>
  <div ref="doc" class="flex h-full flex-col">
    <title>oh-pipeline-tech-manual_{{ date }}</title>
    <span class="title">
      <span class="text-[12pt] font-bold text-[#dcdcdc]">OHASIS DATA PIPELINE | </span>
      <span class="text-[12pt] font-medium text-[#a1a1a1]">TECHNICAL MANUAL</span>
    </span>
    <TitlePage
      client="National HIV/AIDS-STI Surveillance & Strategic Information Unit"
      date-submitted="January 15, 2026"
      project="One HIV/AIDS-STI Information System"
      submitted-by="John Benedict Palo (Data Specialist)"
      submitted-to="Noel Palaypayon (Unit Manager)"
      subtitle="Technical Manual"
      title="OHASIS Data Pipeline"
      version="1.0" />

    <div id="toc-page" class="toc-page flex h-full w-full flex-col gap-4">
      <div class="flex shrink-0 text-[14pt] font-bold text-bene-teal">TABLE OF CONTENTS</div>
    </div>

    <PrimaryTopic title="OVERVIEW">
      <Paragraph>
        The One HIV/AIDS‑STI Information System (OHASIS) is the Department of Health Epidemiology Bureau’s National
        HIV/AIDS‑STI Surveillance & Strategic Information Unit (NHSSS) primary data management and analytics platform
        for monitoring HIV and STI health indicators across the Philippines. This document defines the technical
        specifications and operational procedures that govern OHASIS data management and records the design decisions
        made to meet program needs and resolve identified issues.
      </Paragraph>
      <Paragraph>
        <TertiaryTitle>Scope</TertiaryTitle>
        This document covers system architecture, data flows, ingestion and transformation rules, validation and quality
        checks, access controls, backup and recovery procedures, and operational runbooks required to operate OHASIS
        reliably and securely. It also explains the rationale and trade‑offs behind key design choices so implementers
        and stakeholders understand constraints and assumptions.
      </Paragraph>
      <Paragraph>
        <TertiaryTitle>Intended audience</TertiaryTitle>
        <ConceptList :list="audience" />
      </Paragraph>
    </PrimaryTopic>

    <PipelineDesign />

    <Setup />

    <RunbookSystem />

    <PrimaryTopic title="HYDRATION PIPELINE">
      <Paragraph> Per surveillance schemas will be cataloged in their own sections.</Paragraph>
      <SecondaryTopic title="Workflow"></SecondaryTopic>
      <SecondaryTopic title="Data Lake">
        <TertiaryTopic title="patients"></TertiaryTopic>
        <TertiaryTopic title="px_demographics"></TertiaryTopic>
        <TertiaryTopic title="id_registry"></TertiaryTopic>
      </SecondaryTopic>
      <SecondaryTopic title="Data Warehouse">
        <TertiaryTopic title="form_a"></TertiaryTopic>
        <TertiaryTopic title="form_art"></TertiaryTopic>
        <TertiaryTopic title="form_hts"></TertiaryTopic>
      </SecondaryTopic>
    </PrimaryTopic>

    <SurveillancePipelines />

    <HarpDx />

    <PrimaryTopic title="HIV TREATMENT REGISTRY">
      <Paragraph>[description and case definition]</Paragraph>
      <SecondaryTopic title="Data Catalog">
        <TertiaryTopic title="Correction Tables">
          <QuaternaryTopic title="corr_reg"></QuaternaryTopic>
          <QuaternaryTopic title="corr_outcome"></QuaternaryTopic>
          <QuaternaryTopic title="corr_drop"></QuaternaryTopic>
          <QuaternaryTopic title="corr_defer"></QuaternaryTopic>
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
        <TertiaryTopic title="tx_new"></TertiaryTopic>
        <TertiaryTopic title="tx_curr"></TertiaryTopic>
      </SecondaryTopic>
      <SecondaryTopic title="Surveillance Validation"></SecondaryTopic>
    </PrimaryTopic>
    <PrimaryTopic title="MORTALITY REGISTRY">
      <Paragraph>[description and case definition]</Paragraph>
      <SecondaryTopic title="Data Catalog">
        <TertiaryTopic title="Correction Tables"></TertiaryTopic>
        <TertiaryTopic title="Reference Tables"></TertiaryTopic>
        <TertiaryTopic title="Final Tables"></TertiaryTopic>
      </SecondaryTopic>
      <SecondaryTopic title="load_reqs"></SecondaryTopic>
      <SecondaryTopic title="mortality"></SecondaryTopic>
    </PrimaryTopic>
    <PrimaryTopic title="HARP LINKED REGISTRY">
      <Paragraph>[description and case definition]</Paragraph>
      <SecondaryTopic title="Data Catalog">
        <TertiaryTopic title="Reference Tables"></TertiaryTopic>
        <TertiaryTopic title="Final Tables"></TertiaryTopic>
      </SecondaryTopic>
      <SecondaryTopic title="load_reqs"></SecondaryTopic>
      <SecondaryTopic title="mortality"></SecondaryTopic>
    </PrimaryTopic>
    <PrimaryTopic title="DATA INTEGRATIONS">
      <SecondaryTopic title="LoveYourself, Inc.">
        <TertiaryTopic title="Testing"></TertiaryTopic>
        <TertiaryTopic title="Treatment"></TertiaryTopic>
        <TertiaryTopic title="PrEP"></TertiaryTopic>
        <TertiaryTopic title="Viral Load"></TertiaryTopic>
      </SecondaryTopic>
      <SecondaryTopic title="Quezon City">
        <TertiaryTopic title="Testing"></TertiaryTopic>
        <TertiaryTopic title="Treatment"></TertiaryTopic>
      </SecondaryTopic>
      <SecondaryTopic title="General Imports">
        <TertiaryTopic title="Central Treatment"></TertiaryTopic>
        <TertiaryTopic title="Offline HTS"></TertiaryTopic>
      </SecondaryTopic>
    </PrimaryTopic>
    <PrimaryTopic title="DATA SHARING">
      <SecondaryTopic title="FHI 360 - EpiC-HIV"></SecondaryTopic>
      <SecondaryTopic title="ICAP"></SecondaryTopic>
      <SecondaryTopic title="PROTECTS-UPSCALE"></SecondaryTopic>
    </PrimaryTopic>
  </div>
</template>
