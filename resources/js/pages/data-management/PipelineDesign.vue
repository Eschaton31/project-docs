<script setup>
import TechStack from '@/components/tables/TechStack.vue'

import Alert from '@/components/Alert.vue'
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

const architectureLayers = [
  {
    id: 'EHR Layer',
    value:
      'Contains source systems and modules (patient records, testing, treatment, mortality, reference tables) that generate transactional events and master data.',
    children: [],
  },
  {
    id: 'LW Layer',
    value:
      'A purpose‑built storage tier for raw ingests, curated datasets, and analytics tables designed to support reporting, modeling, and downstream consumers without impacting live systems.',
    children: [],
  },
]
const pipelineTypes = [
  {
    id: 'Import Pipelines',
    value: 'Streams data into the EHR and captures inbound records from external systems.',
    children: [],
  },
  {
    id: 'Hydration Pipelines',
    value:
      'Normalizes raw ingests into canonical, analysis‑ready tables. Applies lookups, code mappings, type conversions and initial  denormalization of data.',
    children: [],
  },
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

const dbms = [
  {
    id: 'MariaDB',
    value:
      'Transactional source of record for the EHR and operational metadata. Used for OLTP workloads, form submissions, user management, reference tables, and any workflow that requires row‑level consistency.',
    children: [],
  },
  {
    id: 'ClickHouse',
    value:
      'Analytics and reporting engine for the Data Lake/Warehouse. Used for large‑scale aggregations, time‑series queries, ad‑hoc analytics, and surveillance extracts that require fast columnar scans.',
    children: [],
  },
]

const stack = {
  orchestration: [
    { component: 'Application Backend', tech: 'PHP', framework: ['Laravel'] },
    { component: 'Application Frontend', tech: 'Javascript', framework: ['Vue.js'] },
    { component: 'Status Logs', tech: 'WebSockets', framework: ['Reverb'] },
    { component: 'UI/UX', tech: ['Javascript', 'CSS'], framework: ['TailwindCSS'] },
    { component: 'OLTP Connector', tech: ['PHP'], framework: ['PDO'] },
    { component: 'OLAP Connector', tech: ['PHP'], framework: ['phpClickHouse'] },
  ],
  runbook: [
    { component: 'Application Backend', tech: 'R', framework: ['Tidyverse'] },
    { component: 'IDE', tech: ['Java', 'VSCode'], framework: ['PyCharm (R Plugin)', 'RStudio'] },
    { component: 'OLTP Connector', tech: ['RMariaDB'], framework: ['DBI', 'dbplyr'] },
    { component: 'OLAP Connector', tech: ['RClickHouse', 'ClickHouseHTTP'], framework: ['DBI', 'dbplyr'] },
    { component: 'Record Linkage', tech: ['R', 'Python'], framework: ['fastLink', 'splink'] },
  ],
}
</script>

<template>
  <PrimaryTopic title="PIPELINE DESIGN">
    <Paragraph>
      This section describes the design language that shapes the pipeline’s architecture. It gives a high‑level view of
      data and control flow across pipeline components, provides context to the guiding principles behind key design
      choices, and enumerates the technical requirements needed to operate and maintain the system reliably and
      securely.z
    </Paragraph>
    <SecondaryTopic title="Architecture">
      <Paragraph>
        The OHASIS information stream is organized into two primary layers: the Electronic Health Record (EHR) layer,
        which contains transactional patient and clinical systems, and the Data Lake/Warehouse (LW) layer, which stores
        consolidated, analysis‑ready data. Pipelines are the connectors that extract, validate, transform, and load data
        from the EHR into the LW.
        <ConceptList :list="architectureLayers" class="mt-2" />
      </Paragraph>

      <Image
        description="Overview of the data warehousing architecture of the OHASIS Ecosystem"
        height="h-72"
        src="/img/diagrams/pipeline-architecture.png" />
      <Paragraph>
        This system can be further divided into four (4) types: the Import Pipelines, Hydration Pipelines, Surveillance
        Pipelines, and the Change Data Capture (CDC). Only the Import Pipelines are concerned with streaming data into
        the EHR, with all others processing information into the buckets that make up the LW.
        <ConceptList :list="pipelineTypes" class="mt-2" />
      </Paragraph>
      <Paragraph>
        Analytics pipelines that produce dashboards, reports, and data exports are consumer‑driven: they are not a
        single pipeline type but a set of tailored processes built to meet the specific requirements of downstream
        systems and users.
      </Paragraph>
    </SecondaryTopic>
    <SecondaryTopic start="2" title="Technology Stack">
      <Paragraph>
        Two (2) Database Management Systems (DBMS) are in play in the operations of the data pipeline: MariaDB and
        ClickHouse. Each serves a distinct role in the architecture to balance transactional integrity, operational
        simplicity, and high‑performance analytics.
        <ConceptList :list="dbms" class="mt-2" />
      </Paragraph>
      <Paragraph>
        The pipeline is managed by two complementary systems: a UI‑based Data Orchestration Module and a Runbook‑driven
        operational system. Both use the same DBMS infrastructure but serve different roles, use different connectors,
        and scale according to distinct operational needs.
      </Paragraph>
      <TertiaryTopic title="Data Orchestration Module">
        <Paragraph>
          Introduced during the OHASIS v2.0 transition, the Data Orchestration Module is embedded directly in the EHR
          and accessible from the sidebar to OHASIS Super Admin accounts only. It centralizes backend management and
          provides a single, authoritative controller for pipeline processes.
        </Paragraph>
        <Image
          description="Screenshot of the Data Orchestration Module in the OHASIS EHR"
          height="h-[15rem]"
          src="/img/screenshots/data-orchestration.png" />
        <Paragraph>
          Since this module is embedded directly within the OHASIS EHR, it is built using the same core technology stack
          and application frameworks as the rest of the system. The backend is implemented in Laravel, while the
          frontend is developed using Vue.js, ensuring consistency, maintainability, and seamless integration across the
          platform.
        </Paragraph>
        <TechStack :data="stack.orchestration" />
      </TertiaryTopic>
      <TertiaryTopic title="Runbook System">
        <Paragraph>
          Originally implemented in 2022 as the primary pipeline for OHASIS v1.0, the runbook framework is a collection
          of interconnected R scripts and modules that encoded NHSSS business logic into executable processes and
          produced the bulk of the system’s data outputs.
        </Paragraph>
        <Image
          description="The runbook codebase hosted in Github"
          height="h-[15rem]"
          src="/img/screenshots/dqt-pipeline.png" />
        <Paragraph>
          The runbook framework ran on data managers’ workstations and servers, using local CPU and disk resources to
          execute pipeline tasks. Each runbook acted as a client: it requested source extracts from the operational
          servers, performed cleaning and transformation in R, and then wrote the finalized artifacts into the
          Lake/Warehouse. The codebase was modular: reusable R functions implemented common transformations, while
          higher‑level scripts orchestrated end‑to‑end workflows for specific surveillance datasets.
        </Paragraph>
        <TechStack :data="stack.runbook" />
        <Paragraph>
          With OHASIS v2.0, core responsibilities of the runbook framework were migrated into the Data Orchestration
          Module to improve synchronization, reliability, and observability.
        </Paragraph>
        <Alert title="Important Note" type="warn">
          The overall goal is to have the routine pipelines be migrated into the Data Orchestration Module to ensure
          consistency in management, and have the runbooks be in charge of ad hoc processes. However, full migration
          into this new system has yet to be completed, as such, the current pipeline makes use of both systems in its
          operation.
        </Alert>
      </TertiaryTopic>
    </SecondaryTopic>
  </PrimaryTopic>
</template>
