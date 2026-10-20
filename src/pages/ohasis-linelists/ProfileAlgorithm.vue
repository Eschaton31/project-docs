<script setup>
import ItemDescriptionTable from '@/components/tables/ItemDescriptionTable.vue'

import Alert from '@/components/Alert.vue'
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

const dataFilters = [
  {
    id: 'Period Type, Periods (Start & End)',
    value:
      'Denotes the scope/range of dates the will be used by the system to filter the relevant records. Period type has two (2) modes:',
    children: [
      {
        id: 'Date Range',
        value:
          'Useful for manually setting the <b>start</b> and <b>end</b> dates of the export. Also provides a filter type for which dates are to be referenced: <b>Visit Date</b> or <b>Encoding Date</b>',
        children: [],
      },
      {
        id: 'Month',
        value:
          'Period-based exporting that lets the system extrapolate the reporting period or the start/end dates from the year & month provided. i.e., for the period of <b>6/2026</b>:',
        children: [
          { id: 'Start', value: 'start of the month (6/1/2026)', children: [] },
          { id: 'End', value: 'end of the month (6/30/2026)', children: [] },
          { id: 'Period', value: 'period of reporting for HASP (202606)', children: [] },
        ],
      },
    ],
  },
  {
    id: 'Geographic Filters',
    value:
      'Controls the administrative scope used by the system to calculate the patient/record pool. This can be set to facility-based or area-based exports:',
    children: [
      {
        id: 'Facility Name',
        value:
          'To export data for per-site data. Choosing this option opens the <b>Site/Organization</b> filter to choose one (1) specific site.',
        children: [],
      },
      {
        id: 'Facility Location',
        value:
          'Gets the list of all sites within the specified area and then keeps those the user has access to (data sharing for non-RESU accounts). Choosing this option opens the <b>Region</b>, <b>Province</b>, and <b>City/Municipality</b> filters (at least one of these is required to be filled).',
        children: [],
      },
      {
        id: 'Permanent Residence',
        value:
          'For exporting of clients who have reported at least once that their permanent residence falls in the area specified. Based on records/forms that have the permanent residence fields. Choosing this option opens the <b>Region</b>, <b>Province</b>, and <b>City/Municipality</b> filters (at least one of these is required to be filled).',
        children: [],
      },
    ],
  },
]
const exportable = [
  {
    id: 'Export Type',
    value:
      'Determines the general structure of the final dataset to be exported, informing the system of how to calculate these records.',
    children: [
      {
        id: 'Forms',
        value:
          'Record-level data encoded into related DOH-EB form for the module (i.e., HTS Forms & Form As for Testing).',
        children: [],
      },
      {
        id: 'Services',
        value: 'Data categorized based on the service provided.',
        children: [
          { id: 'Wide', value: 'Service data regardless if DOH-EB form was used.', children: [] },
          { id: 'Long', value: 'For data like pharmacies where multiple medicines exist per record', children: [] },
        ],
      },
      { id: 'Linelists', value: 'Patient-level data imputed from all OHASIS datasets.', children: [] },
    ],
  },
  { id: 'Module', value: 'Specifies the umbrella module where the type of export falls.', children: [] },
  { id: 'Data', value: 'Dynamic set of templates that react to the selected export type and module.', children: [] },
]

const exportSettings = [
  {
    title: 'Convert Headers?',
    description: 'Controls whether the resulting column names (first row) are converted into human-readable format.',
    sample: [
      { item: 'sex', description: 'Sex (at birth)' },
      { item: 'central_id', description: 'Central ID' },
      { item: 'patient_id', description: 'Patient ID' },
      { item: 'curr_reg', description: 'Curr. Address: Region' },
      { item: 'perm_prov', description: 'Perm. Address: Region' },
    ],
  },
  {
    title: 'Convert Data?',
    description: 'Controls whether the resulting column values are converted into human-readable format.',
    sample: [
      { item: '1', description: 'Man' },
      { item: '2', description: 'Woman' },
      { item: '3', description: 'Others' },
    ],
  },
  {
    title: 'Include PII?',
    description:
      'Controls whether columns containing personally identifiable information (PII) are included. These include:',
    sample: [
      { item: 'philhealth_no', description: 'PhilHealth No.' },
      { item: 'philsys_id', description: 'PhilSys ID' },
      { item: 'first', description: 'First Name' },
      { item: 'middle', description: 'Middle Name' },
      { item: 'last', description: 'Last Name' },
      { item: 'suffix', description: 'Suffix (Jr., Sr., III, etc)' },
      { item: 'client_mobile', description: 'Mobile No.' },
      { item: 'client_email', description: 'E-mail Addresss' },
    ],
  },
  {
    title: 'Include Deleted?',
    description: 'Controls whether deleted records/forms are included. Only applicable for forms and services.',
  },
]
const realTimeLCoverage = [
  {
    id: 'During the period',
    value: 'Linelists that calculate the patient pool based on the period (Start and End dates) provided.',
    children: [],
  },
  {
    id: 'As of the end of the period',
    value:
      'Linelists that gathers all patients provided in the scope of export (Facilities or Residence) and takes their status/info as of export time.',
    children: [],
  },
]
</script>

<template>
  <PrimaryTopic title="PROFILE ALGORITHM">
    <Paragraph>
      This section explains the backend logic that powers the linelists module and outlines the different linelist types
      it can generate. It clarifies how various filters and parameters influence the resulting dataset, helping users
      understand how to tailor exports effectively and apply the linelists to their full potential.
    </Paragraph>
    <SecondaryTopic title="UI & Filters">
      <Paragraph>
        These filters determine which type of linelist the system generates, specifically by controlling the date
        coverage and administrative scope of the exported dataset. By adjusting these parameters, users can tailor the
        linelist to match the exact operational or reporting context they need.
      </Paragraph>
      <!-- Data Filters -->
      <TertiaryTopic title="Data Filters">
        <Paragraph> This filter group controls how the resulting dataset is scoped.</Paragraph>
        <Image
          description="Screenshot of the data filters section"
          height="h-[15rem]"
          src="/img/screenshots/ohasis-linelists/data-filters.png" />
        <ConceptList :list="dataFilters" class="mt-2" />
        <Alert type="warn"> The <b>Permanent Residence</b> filter is only enabled for RESU accounts.</Alert>
      </TertiaryTopic>

      <!-- Exportable -->
      <TertiaryTopic title="Exportable">
        <Paragraph> This filter group controls the format of the resulting dataset.</Paragraph>
        <Image
          description="Screenshot of the exportable section"
          height="h-[15rem]"
          src="/img/screenshots/ohasis-linelists/exportable.png" />
        <ConceptList :list="exportable" class="mt-2" />
      </TertiaryTopic>

      <!-- Export Settings -->
      <TertiaryTopic title="Export Settings">
        <Paragraph>
          This input group controls whether the exported dataset is normalized for analysis-ready formats or viewable
          formats.
        </Paragraph>
        <Image
          description="Screenshot of the export settings"
          height="h-[4rem]"
          src="/img/screenshots/ohasis-linelists/export-settings.png" />

        <QuaternaryTopic v-for="{ title, description, sample } in exportSettings" :key="title" :title>
          <Paragraph>{{ description }}</Paragraph>
          <ItemDescriptionTable
            v-if="sample"
            :data="sample"
            :description-name="title.startsWith('Convert') ? 'Converted' : null"
            :item-name="title.startsWith('Convert') ? 'Default' : null"
            description="Sample conversion" />
        </QuaternaryTopic>
      </TertiaryTopic>

      <Paragraph>
        All-in-all, OHASIS collates the filters specified in the above-mentioned sections and decides the dataset that
        will be outputed from the system.
      </Paragraph>
      <Image
        description="The backend decision flow for deciding the exportable data"
        height="h-[15rem]"
        src="/img/diagrams/ohasis-linelists.png" />
    </SecondaryTopic>
    <SecondaryTopic start="2" title="Linelist Types">
      <Paragraph> There are two (2) primary types of linelists available for end‑user download.</Paragraph>
      <TertiaryTopic title="HASP">
        <Paragraph>
          These linelists draw directly from the datasets produced by the DQT, specifically the
          <codetext>harp_full</codetext>, <codetext>harp_tx</codetext>, and <codetext>harp_dead</codetext> schemas,
          accessed through the Period filter. Because they are connected to the live schemas, the exported linelists
          always reflect the most current data available at the time of download, ensuring real‑time alignment with
          ongoing updates in the system.
        </Paragraph>

        <Image
          description="The error logs for when a period's datasets have yet to be generated"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/hasp-logs-error.png" />

        <Image
          description="The info logs for when a period's are already available/completed"
          height="h-[6rem]"
          src="/img/screenshots/ohasis-linelists/hasp-logs-success.png" />

        <Paragraph>
          Exporting these linelists generates logs that record the exact date and time the datasets were produced for
          the selected period. If the datasets for that period are not yet available, the system will display a warning
          log.
        </Paragraph>

        <Alert> These are stand-in replacements for the PAHI datasets previously being released by DOH-EB.</Alert>
        <Alert> These linelists are unaffected by export settings.</Alert>
        <Alert title="Official Version Warning!" type="warn">
          Because these linelists pull directly from DOH‑EB DQT’s in‑progress datasets, RESUs may export periods that
          are <b>not yet finalized</b>. Users must exercise caution and always confirm the period’s completion status in
          the logs before using, interpreting, or sharing the exported data.
        </Alert>
      </TertiaryTopic>
      <TertiaryTopic title="Real-time">
        <Paragraph>
          These linelists pull from the entire set of live OHASIS records at the moment of export and compute
          patient‑level indicators based on all valid, currently available data. Because they always reflect the
          system’s real‑time state, they are highly suited for operational monitoring.
        </Paragraph>
        <Paragraph>There are two (2) coverage types available:</Paragraph>
        <ConceptList :list="realTimeLCoverage" />

        <Image
          description="The info logs for real-time linelists"
          height="h-[18rem]"
          src="/img/screenshots/ohasis-linelists/real-time-logs.png" />
      </TertiaryTopic>
    </SecondaryTopic>
  </PrimaryTopic>
</template>
