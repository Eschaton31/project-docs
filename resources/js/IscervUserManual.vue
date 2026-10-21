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
      <span class="text-[12pt] font-bold text-[#dcdcdc]">iSCerv | </span>
      <span class="text-[12pt] font-medium text-[#a1a1a1]">USER MANUAL</span>
    </span>
    <TitlePage
      client="jhpiego"
      date-submitted="March 27, 2026"
      project="Centralized Laboratory Model for HPV DNA Testing (CLAMS)"
      submitted-by="John Benedict Palo (Consultant)"
      submitted-to="jhpiego Technical Team"
      subtitle="User Manual"
      title="iSCerv"
      version="3.0" />

    <div id="toc-page" class="toc-page flex h-full w-full flex-col gap-4">
      <div class="flex shrink-0 text-[14pt] font-bold text-bene-teal">TABLE OF CONTENTS</div>
    </div>

    <PrimaryTopic title="OVERVIEW">
      <Paragraph>
        The Information System on Cervical Cancer Screening (iSCerv) is a key component of Jhpiego–Philippines’
        Centralized Laboratory Model for HPV Screening (CLAMS) Project. Together with the Scale Up Cervical Cancer
        Elimination with Secondary Prevention Strategy (SUCCESS) Project, CLAMS forms a major part of Jhpiego’s broader
        commitment to advancing women’s and children’s health, particularly in underserved, conflict-affected, and
        disadvantaged regions of the Philippines.
      </Paragraph>
      <Paragraph>
        Jhpiego’s work strengthens both public and private health systems through provider training, improved
        utilization and enhancement of health facilities, and—critically—effective health systems management and
        coordination. Within this framework, the CLAMS Project aims to expand the testing capacity of health facilities
        through a centralized laboratory model, where iSCerv will play an essential role.
      </Paragraph>
      <Paragraph>
        iSCerv focuses on developing an information system dedicated to HPV and cervical cancer screening. It captures
        the full continuum of the current screening workflow—from history taking and physical examination to diagnostics
        and, when applicable, treatment. By standardizing and organizing data across institutions, the system supports
        interoperability and aligns with Jhpiego’s goals of improving data quality, consistency, and accessibility
        across both public and private sectors.
      </Paragraph>
      <Paragraph>
        The project includes the development of a web-based application accessible via computer or mobile devices with
        an internet connection. The application mirrors existing processes and practices within participating
        institutions, enabling smoother data migration and easier adaptation for healthcare providers.
      </Paragraph>
    </PrimaryTopic>
  </div>
</template>
