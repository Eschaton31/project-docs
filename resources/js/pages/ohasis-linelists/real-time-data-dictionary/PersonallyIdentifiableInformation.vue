<script setup>
import ConversionDictionary from '@/components/tables/ConversionDictionary.vue'
import ItemDescriptionTable from '@/components/tables/ItemDescriptionTable.vue'
import SqlTable from '@/components/tables/SqlTable.vue'

import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'

defineProps({
  start: [String, Number],
})

const cleanPii = [
  {
    item: 'Non-alpha <codetext>[A-Z]</codetext> and non-numeric <codetext>[0-9]</codetext>',
    description: 'Removed from string',
  },
  {
    item: 'Starts with <codetext>AWAITING</codetext> or <codetext>PENDING</codetext>',
    description: 'Whole string is converted to <codetext>NULL</codetext>',
  },
  {
    item: 'Starts with <codetext>NOT</codetext> or <codetext>NO</codetext> followed by whitespace',
    description: 'Whole string is converted to <codetext>NULL</codetext>',
  },
  {
    item: 'Contains <codetext>CANNOT</codetext> and <codetext>PROVIDE</codetext>',
    description: 'Whole string is converted to <codetext>NULL</codetext>',
  },
  {
    item: 'Empty/blank strings',
    description: 'Whole string is converted to <codetext>NULL</codetext>',
  },
  {
    item: 'Value is: <codetext>NONE</codetext>, <codetext>N/A</codetext>, <codetext>NA</codetext>, <codetext>XXX</codetext>, <codetext>XX</codetext>, or <codetext>#NAME?</codetext>',
    description: 'Whole string is converted to <codetext>NULL</codetext>',
  },
]

const formatMobile = [
  {
    item: 'Character string with exactly 11 digits',
    description: 'Retain',
  },
  {
    item: 'Starts with <codetext>9</codetext>',
    description: 'Prepend with <codetext>0</codetext>',
  },
  {
    item: 'Starts with <codetext>63</codetext>',
    description: 'Replace starting <codetext>63</codetext> with <codetext>0</codetext>',
  },
]

const formatPhilhealth = [
  {
    item: 'Character string with exactly 12 digits',
    description: 'Retain',
  },
  {
    item: 'Character string with exactly 11 digits',
    description: 'Prepend with <codetext>0</codetext>',
  },
  {
    item: 'Everything else',
    description: 'Retain',
  },
]

const sample = {
  piiRecords: {
    before: [
      {
        central_id: 'A',
        faci: 'PGH',
        first: 'JUAN',
        last: 'DELA CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'PGH17-03-JD',
        created_at: '2025-06-01 15:45:28',
      },
      {
        central_id: 'A',
        faci: 'FPO',
        first: 'JUAN MIGUEL',
        last: 'CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'FPO-004',
        created_at: '2024-05-23 21:27:07',
      },
      {
        central_id: 'A',
        faci: 'VSM',
        first: 'MIGUEL',
        last: 'DELA CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'MDC-17-001',
        created_at: '2025-08-08 13:11:53',
      },
      {
        central_id: 'A',
        faci: 'BIC',
        first: 'JUAN',
        last: 'CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'JC-040897',
        created_at: '2023-03-25 15:24:30',
      },
      {
        central_id: 'A',
        faci: 'RIT',
        first: 'JUAN MIGUEL',
        last: null,
        birthdate: '1997-08-04',
        patient_code: null,
        created_at: '2026-01-17 00:49:04',
      },
      {
        central_id: 'A',
        faci: 'RIT',
        first: 'JUAN MIGUEL',
        last: 'DELA CRUZ',
        birthdate: '1997-04-01',
        patient_code: 'R26-999',
        created_at: '2026-03-05 09:05:18',
      },
      {
        central_id: 'A',
        faci: 'VSM',
        first: 'MIGGY',
        last: 'DELA CRUZ',
        birthdate: null,
        patient_code: null,
        created_at: '2025-05-27 02:59:38',
      },
      {
        central_id: 'A',
        faci: 'PGH',
        first: 'JUAN',
        last: 'DELA CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'PGH17-03-JD',
        created_at: '2026-07-05 01:11:38',
      },
      {
        central_id: 'A',
        faci: 'PGH',
        first: 'JUAN',
        last: 'CRUZ',
        birthdate: '1997-04-01',
        patient_code: null,
        created_at: '2026-05-30 08:59:08',
      },
      {
        central_id: 'A',
        faci: 'VSM',
        first: 'MIGGY',
        last: null,
        birthdate: null,
        patient_code: 'MDC-17-001',
        created_at: '2025-11-27 09:09:55',
      },
    ],
    after: [
      {
        central_id: 'A',
        faci: 'BIC',
        first: 'JUAN',
        last: 'CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'JC-040897',
      },
      {
        central_id: 'A',
        faci: 'FPO',
        first: 'JUAN MIGUEL',
        last: 'CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'FPO-004',
      },
      {
        central_id: 'A',
        faci: 'PGH',
        first: 'JUAN',
        last: 'DELA CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'PGH17-03-JD',
      },
      {
        central_id: 'A',
        faci: 'RIT',
        first: 'JUAN MIGUEL',
        last: 'DELA CRUZ',
        birthdate: '1997-04-01',
        patient_code: 'R26-999',
      },
      {
        central_id: 'A',
        faci: 'VSM',
        first: 'MIGGY',
        last: 'DELA CRUZ',
        birthdate: '1997-04-08',
        patient_code: 'MDC-17-001',
      },
    ],
  },
  piiProfile: [
    {
      central_id: 'A',
      first: 'JUAN',
      last: 'DELA CRUZ',
      birthdate: '1997-04-08',
      patient_code: 'PGH17-03-JD',
    },
  ],
  names: {
    before: [
      {
        central_id: 'A',
        faci: 'BIC',
        first: 'JUAN',
        middle: 'P',
        last: 'CRUZ',
        suffix: null,
        created_at: '2023-03-25 15:24:30',
      },
      {
        central_id: 'A',
        faci: 'FPO',
        first: 'JUAN MIGUEL',
        middle: 'PROCOPIO',
        last: 'DELA CRUZ',
        suffix: null,
        created_at: '2024-05-23 21:27:07',
      },
      {
        central_id: 'A',
        faci: 'PGH',
        first: 'JUAN MIGUEL',
        middle: 'P',
        last: 'DELA CRUZ',
        suffix: null,
        created_at: '2026-03-05 09:05:18',
      },
      {
        central_id: 'A',
        faci: 'VSM',
        first: 'MIGGY',
        middle: null,
        last: 'CRUZ',
        suffix: 'III',
        created_at: '2026-07-05 01:11:38',
      },
    ],
    after: [
      {
        central_id: 'A',
        first: 'MIGGY',
        middle: 'P',
        last: 'CRUZ',
        suffix: 'III',
      },
    ],
  },
  selfIdent: {
    before: [
      {
        central_id: 'A',
        self_ident: 'Man',
        self_ident_other: null,
        created_at: '2024-05-23 21:27:07',
      },
      {
        central_id: 'A',
        self_ident: 'Woman',
        self_ident_other: null,
        created_at: '2026-07-05 01:11:38',
      },
      {
        central_id: 'A',
        self_ident: 'Other',
        self_ident_other: 'QUEER',
        created_at: '2026-03-05 09:05:18',
      },
      {
        central_id: 'A',
        self_ident: null,
        self_ident_other: null,
        created_at: '2025-08-08 13:11:53',
      },
    ],
    after: [
      {
        central_id: 'A',
        self_ident: 'Woman',
        self_ident_other: null,
      },
    ],
  },
  address: {
    before: [
      {
        central_id: 'A',
        curr_reg: '1',
        curr_prov: 'ILOCOS NORTE',
        curr_munc: 'BACARRA',
        curr_brgy: null,
        created_at: '2024-05-23 21:27:07',
      },
      {
        central_id: 'A',
        curr_reg: '2',
        curr_prov: 'CAGAYAN',
        curr_munc: null,
        curr_brgy: null,
        created_at: '2026-07-05 01:11:38',
      },
      {
        central_id: 'A',
        curr_reg: '3',
        curr_prov: null,
        curr_munc: null,
        curr_brgy: null,
        created_at: '2026-03-05 09:05:18',
      },
      {
        central_id: 'A',
        curr_reg: 'NCR',
        curr_prov: 'NCR',
        curr_munc: 'QUEZON',
        curr_brgy: 'SALVACION',
        created_at: '2025-08-08 13:11:53',
      },
    ],
    after: [
      {
        central_id: 'A',
        curr_reg: '2',
        curr_prov: 'CAGAYAN',
        curr_munc: null,
        curr_brgy: null,
      },
    ],
  },
}

const columns = {
  names: [
    { item: 'first', description: 'First/Given name', formats: 'String' },
    { item: 'middle', description: "Middle name or the family name of the client's mother", formats: 'String' },
    { item: 'last', description: 'Last/Family Name', formats: 'String' },
    { item: 'suffix', description: 'Suffix (Jr., Sr., III, etc)', formats: 'String' },
  ],
  contactDetails: [
    {
      item: 'client_mobile',
      description: 'Mobile number',
      formats: ['String', 'Format: <codetext>09## ### ####</codetext>'],
    },
    { item: 'client_email', description: 'E-mail address', formats: ['String', 'Format: <codetext>*@*</codetext>'] },
  ],
  governmentIds: [
    {
      item: 'philhealth_no',
      description: 'PhilHealth No.',
      formats: ['String', 'Format: <codetext>##-#########-#</codetext>'],
    },
    {
      item: 'philsys_id',
      description: 'PhilSys ID',
      formats: ['String', 'Format: <codetext>####-####-####-####</codetext>'],
    },
  ],
  anonymousIds: [
    {
      item: 'confirmatory_code',
      description: 'The assigned HIV confirmatory code (for those that have been confirmed through NRL-SACCL or CRCLs)',
      formats: 'String',
    },
    {
      item: 'patient_code',
      description: 'Unique code assigned by the site/organization per client they serve',
      formats: 'String',
    },
    { item: 'uic', description: "National HIV program's Unique Identifier Code (UIC) system", formats: 'String' },
    {
      item: 'birthdate',
      description: "Client's date of birth",
      formats: ['Date', 'Format: <codetext>yyyy-mm-dd</codetext>'],
    },
  ],
  sexGender: [
    { item: 'sex', description: 'Sex assigned at birth', formats: ['Integer (default)', 'String (converted)'] },
    {
      item: 'self_ident',
      description: 'How the client identifies as based on the SOGIESC framework',
      formats: ['Integer (default)', 'String (converted)'],
    },
    {
      item: 'self_ident_other',
      description: 'How the client identifies as based on the SOGIESC framework (free-text)',
      formats: 'String',
    },
  ],
  demographicProfile: [
    {
      item: 'nationality',
      description: "Client's official nationality",
      formats: ['String', 'See: OHASIS country codes'],
    },
    {
      item: 'educ_level',
      description: 'Highest educational attainment',
      formats: ['Integer (default)', 'String (converted)'],
    },
    {
      item: 'civil_status',
      description: 'Official civil status',
      formats: ['Integer (default)', 'String (converted)'],
    },
  ],
  address: [
    {
      item: '*_reg',
      description: "Philippines' administrative region",
      formats: ['String', 'See: OHASIS/PSA PSGC'],
    },
    {
      item: '*_prov',
      description: "Philippines' administrative province",
      formats: ['String', 'See: OHASIS/PSA PSGC'],
    },
    {
      item: '*_munc',
      description: "Philippines' administrative city/municipality",
      formats: ['String', 'See: OHASIS/PSA PSGC'],
    },
    {
      item: '*_brgy',
      description: "Philippines' administrative barangay",
      formats: ['String', 'See: OHASIS/PSA PSGC'],
    },
  ],
}

const categories = {
  sex: [
    { item: '1', description: 'Male' },
    { item: '2', description: 'Female' },
  ],
  selfIdent: [
    { item: '1', description: 'Man' },
    { item: '2', description: 'Woman' },
    { item: '3', description: 'Other' },
  ],
  educLevel: [
    { item: '1', description: 'None' },
    { item: '2', description: 'Elementary' },
    { item: '3', description: 'High ' },
    { item: '4', description: 'College' },
    { item: '5', description: 'Vocational' },
    { item: '6', description: 'Post-graduate' },
    { item: '7', description: 'Pre-school' },
  ],
  civilStatus: [
    { item: '1', description: 'Single' },
    { item: '2', description: 'Married' },
    { item: '3', description: 'Separated ' },
    { item: '4', description: 'Widowed' },
    { item: '5', description: 'Common-law' },
  ],
}

const addrPrefix = [
  { item: 'curr', description: 'Current address' },
  { item: 'perm', description: 'Permanent address' },
  { item: 'birth', description: 'Birth address ' },
]
</script>

<template>
  <SecondaryTopic :start title="Personally Identifiable Information (PII)">
    <Paragraph>
      OHASIS uses two (2) scopes of PII sources when determining the final PII values: <i>PII Records</i> and
      <i>PII Profiles</i>. For both scopes, the system orders entries by their most recent update
      (<codetext>updated_at</codetext>, <codetext>created_at</codetext>, in that order) and selects the latest
      non‑<codetext>NULL</codetext> value available per <b>Central ID</b>.
    </Paragraph>

    <Paragraph>
      Before sorting, all candidate records undergo initial sanitization, which removes invalid values and standardizes
      the remaining data. For string‑based PII fields, OHASIS applies a cleaning algorithm that strips unwanted
      characters, normalizes formatting, and nullifies values that fail validation. This ensures that only clean, usable
      PII enters the final selection process.
    </Paragraph>

    <Paragraph>
      The string‑cleaning algorithm begins by trimming leading and trailing whitespace, collapsing multiple consecutive
      spaces into a single space, and converting all characters to uppercase. After this normalization step, each value
      undergoes a final sanitization pass based on the following rules:
    </Paragraph>

    <ItemDescriptionTable
      :data="cleanPii"
      :widths="['w-[50%]', 'w-[50%]']"
      description-name="Sanitization"
      item-name="Condition"
      left />

    <Paragraph>
      Only the sanitized values proceed to the sorting step, where OHASIS determines the final PII to use for each
      patient.
    </Paragraph>

    <TertiaryTopic title="Sorting Algorithms">
      <QuaternaryTopic title="PII Records">
        <Paragraph>
          Latest PII data per facility that reported it. Because clients provide PII to the facilities they visit, some
          information may be incomplete or may differ across facilities. To account for this variation, the profile
          scope ensures that PII reported within your administrative coverage is retained and used for those facilities,
          preserving the most relevant and context‑appropriate values.
        </Paragraph>

        <Paragraph>
          For the patient with central ID <codetext>A</codetext>, the following table shows an example of its cleaned
          PII records after the sanitization and normalization steps have been applied.
        </Paragraph>
        <SqlTable :data="sample.piiRecords.before" dark>
          <colgroup class="w-[13%]" />
          <colgroup class="w-[7%]" />
          <colgroup class="w-[15%]" />
          <colgroup class="w-[14%]" />
          <colgroup class="w-[14%]" />
          <colgroup class="w-[15%]" />
          <colgroup class="w-[23%]" />
        </SqlTable>

        <Paragraph>
          From the example above, applying the PII Records sorting algorithm produces the following ordered set of
          cleaned records.
        </Paragraph>
        <SqlTable :data="sample.piiRecords.after" dark>
          <colgroup class="w-[14%]" />
          <colgroup class="w-[7%]" />
          <colgroup class="w-[20%]" />
          <colgroup class="w-[20%]" />
          <colgroup class="w-[19%]" />
          <colgroup class="w-[20%]" />
        </SqlTable>
      </QuaternaryTopic>
      <QuaternaryTopic title="PII Profiles">
        <Paragraph>
          Latest PII data across all patient records. Unlike the previous scope, this one considers every available
          record for the client and selects the most recently updated non‑<codetext>NULL</codetext>
          values across the entire dataset. This ensures that the final PII reflects the latest information reported for
          the patient, regardless of which facility provided it.
        </Paragraph>

        <Paragraph>
          Using the same set of sanitized records shown in the PII Records example, applying this sorting algorithm
          produces the following final set of values.
        </Paragraph>
        <SqlTable :data="sample.piiProfile" dark>
          <colgroup class="w-[14%]" />
          <colgroup class="w-[23%]" />
          <colgroup class="w-[23%]" />
          <colgroup class="w-[17%]" />
          <colgroup class="w-[23%]" />
        </SqlTable>
      </QuaternaryTopic>
    </TertiaryTopic>

    <Paragraph>
      During the linelist build process, <b>PII Records are fetched and pre‑sorted</b> to identify the latest
      facility‑level record that falls within your scope. This step is particularly relevant for users operating at the
      <b>city/municipality level and above</b>, where multiple facilities may contribute PII for the same patient.
    </Paragraph>

    <Image
      description="The algorithm for finalizing the PII values"
      height="h-[10rem]"
      src="/img/diagrams/pii-algorithm.png" />

    <Paragraph>
      The PII variable set can be further divided into two major categories: <b>Sensitive</b> and
      <b>Non‑sensitive</b>
      data.
    </Paragraph>
    <TertiaryTopic title="Sensitive Data">
      <Paragraph>
        These are information that can directly and uniquely identify a client. These cover both
        <b>Personal Information (PI)</b> and/or <b>Sensitive Personal Information (SPI)</b>, as defined in the Data
        Privacy Act of 2012.
      </Paragraph>

      <QuaternaryTopic title="Names">
        <Image
          description="The name fields in the OHASIS client registration form"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/name-fields.png" />
        <Paragraph>
          Contains the primary registered names associated with each client. Values are stored as an ordered set of
          uppercase strings. These fields can be primarily used for case management, patient tracking, and
          deduplication.
        </Paragraph>
        <ConversionDictionary :data="columns.names" mono />
      </QuaternaryTopic>

      <QuaternaryTopic title="Contact Details">
        <Image
          description="The contact details in the OHASIS client registration form"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/contact-fields.png" />
        <Paragraph>
          Provides the client’s available contact information, including channels through which the client can be
          reached or communicated with.
        </Paragraph>
        <ConversionDictionary :data="columns.contactDetails" mono />

        <!-- Mobile -->
        <Paragraph>
          The <b>mobile number</b> provided undergoes an additional sanitization layer. All non‑digit characters
          <codetext>[0–9]</codetext> are first stripped from the string. After this initial normalization, the value
          proceeds through the following cleaning steps:
        </Paragraph>
        <ItemDescriptionTable
          :data="formatMobile"
          :widths="['w-[50%]', 'w-[50%]']"
          description-name="Sanitization"
          item-name="Condition"
          left />
        <Paragraph>
          The final cleaned values are reformatted into the standard <codetext>09## ### ####</codetext> pattern when the
          string contains exactly 11 digits. If the cleaned value does not meet this length requirement, the original
          (pre‑sanitization) value is retained.
        </Paragraph>

        <!-- Email -->
        <Paragraph>
          The <b>emails</b> are just converted to lower-case characters after trimming whitespaces.
        </Paragraph>
      </QuaternaryTopic>

      <QuaternaryTopic title="Government IDs">
        <Image
          description="The government ID fields in the OHASIS client registration form"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/government-id-fields.png" />
        <Paragraph>
          Captures government‑issued identification numbers and documents that can be directly associated with an
          individual.
        </Paragraph>
        <ConversionDictionary :data="columns.governmentIds" mono />

        <!-- PhilHealth -->
        <Paragraph>
          The <b>PhilHealth number</b> provided undergoes an additional sanitization layer. All non‑digit characters
          <codetext>[0–9]</codetext> are first stripped from the string. After this initial normalization, the value
          proceeds through the following cleaning steps:
        </Paragraph>
        <ItemDescriptionTable
          :data="formatPhilhealth"
          :widths="['w-[50%]', 'w-[50%]']"
          description-name="Sanitization"
          item-name="Condition"
          left />
        <Paragraph>
          The final cleaned values are reformatted into the standard
          <codetext>##-#########-#</codetext> pattern when the string contains exactly 12 digits. If the cleaned value
          does not meet this length requirement, the original (pre‑sanitization) value is retained.
        </Paragraph>

        <!-- PhilSys -->
        <Paragraph>
          The <b>PhilSys ID</b> provided has all non‑digit characters <codetext>[0–9]</codetext> are first stripped from
          the string. The cleaned values are reformatted into the standard <codetext>####-####-####-####</codetext>
          pattern when the string contains exactly 16 digits. If the cleaned value does not meet this length
          requirement, the original (pre‑sanitization) value is retained.
        </Paragraph>
      </QuaternaryTopic>

      <Paragraph>
        Because the algorithm sorts strictly by recency, it does not evaluate whether a given row or report is more
        “complete” for the client. The selection logic always prioritizes the latest non‑<codetext>NULL</codetext>
        value, even when earlier records contain longer, richer, or more complete information. For example:
      </Paragraph>

      <SqlTable
        :data="sample.names.before"
        dark
        description="A sample set of records for central ID <codetext>A</codetext>">
        <colgroup class="w-[14%]" />
        <colgroup class="w-[7%]" />
        <colgroup class="w-[15%]" />
        <colgroup class="w-[15%]" />
        <colgroup class="w-[15%]" />
        <colgroup class="w-[11%]" />
        <colgroup class="w-[23%]" />
      </SqlTable>

      <SqlTable
        :data="sample.names.after"
        dark
        description="The final set of names after applying the sorting algorithm for central ID <codetext>A</codetext>">
        <colgroup class="w-[20%]" />
        <colgroup class="w-[20%]" />
        <colgroup class="w-[20%]" />
        <colgroup class="w-[20%]" />
        <colgroup class="w-[20%]" />
      </SqlTable>
    </TertiaryTopic>

    <TertiaryTopic title="Non-sensitive Data">
      <Paragraph>
        Information that can be used as categorical variables for analysis or coded identifiers. These values do not
        directly identify individuals and are generally safe for broader data consumption and statistical use.
      </Paragraph>

      <QuaternaryTopic title="Anonymous Identifiers">
        <Image
          description="The coded ID fields in the OHASIS client registration form"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/anonymous-id-fields.png" />
        <Image
          description="The UIC field in the OHASIS client registration form"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/uic-field.png" />
        <Paragraph>
          Contains anonymized identifiers individuals have or were assigned to them by organizations. These IDs provide
          a non‑PII linkage between client records and external data.
        </Paragraph>
        <ConversionDictionary :data="columns.anonymousIds" mono />
      </QuaternaryTopic>

      <QuaternaryTopic title="Sex & Gender Identity">
        <Image
          description="The sex and gender identity fields in the OHASIS client registration form"
          height="h-[3rem]"
          src="/img/screenshots/ohasis-linelists/gender-fields.png" />
        <Paragraph>
          Captures information related to a client’s sex assigned at birth and their self‑identified gender.
        </Paragraph>
        <ConversionDictionary :data="columns.sexGender" mono />

        <Paragraph>
          The <codetext>sex</codetext> variable represents the client’s sex assigned at birth. This variable contains
          categorical values defined as follows:
        </Paragraph>
        <ItemDescriptionTable :data="categories.sex" description-name="Converted" item-name="Default " mono />

        <Paragraph>
          The client’s self‑identified gender is captured across two fields:
          <codetext>self_ident</codetext> and <codetext>self_ident_other</codetext>. Together, they record how clients
          describe their own gender identity. The <codetext>self_ident</codetext>
          variable contains categorical values defined as follows:
        </Paragraph>
        <ItemDescriptionTable :data="categories.selfIdent" description-name="Converted" item-name="Default" mono />

        <Paragraph>
          During the selection process, the sorting algorithm treats <codetext>self_ident</codetext> and
          <codetext>self_ident_other</codetext> as a paired group. This ensures that both fields are evaluated together,
          preserving consistency in how a client’s gender identity is represented.
        </Paragraph>

        <SqlTable
          :data="sample.selfIdent.before"
          dark
          description="A sample set of records for central ID <codetext>A</codetext>" />

        <SqlTable
          :data="sample.selfIdent.after"
          dark
          description="The final set of gender identity after applying the sorting algorithm for central ID <codetext>A</codetext>" />
      </QuaternaryTopic>

      <QuaternaryTopic title="Demographic Profile">
        <Image
          description="The demographic profile fields in the OHASIS client registration form"
          height="h-[5rem]"
          src="/img/screenshots/ohasis-linelists/demographic-fields.png" />
        <Paragraph> Provides additional demographic information relevant to client profiling and analysis.</Paragraph>
        <ConversionDictionary :data="columns.demographicProfile" mono />

        <Paragraph>
          The <codetext>educ_level</codetext> variable contains various educational levels in OHASIS. This variable
          contains categorical values defined as follows:
        </Paragraph>
        <ItemDescriptionTable :data="categories.educLevel" description-name="Converted" item-name="Default " mono />

        <Paragraph>
          The <codetext>civil status</codetext> variable contains official civil statuses defined by the Philippine
          government. This variable contains categorical values defined as follows:
        </Paragraph>
        <ItemDescriptionTable :data="categories.civilStatus" description-name="Converted" item-name="Default " mono />
      </QuaternaryTopic>

      <QuaternaryTopic title="Addresses">
        <Image
          description="The address fields in the OHASIS client registration form"
          height="h-[15rem]"
          src="/img/screenshots/ohasis-linelists/address-fields.png" />
        <Paragraph>
          Contains geographic information associated with the client. This section is organized into three address
          types—<b>Permanent</b>, <b>Current</b>, and <b>Birth</b>.
        </Paragraph>
        <ItemDescriptionTable :data="addrPrefix" description-name="Address Type" item-name="Prefix " mono />

        <Paragraph>
          Each address type comes with its corresponding region, province, city/municipality, and barangay fields.
        </Paragraph>
        <ConversionDictionary :data="columns.address" mono />

        <Paragraph>
          During the selection process, the sorting algorithm treats <codetext>*_reg</codetext>,
          <codetext>*_prov</codetext>, <codetext>*_munc</codetext>, and <codetext>*_brgy</codetext> as a paired group.
          This ensures that all four (4) fields are evaluated together, preserving consistency in how a client’s address
          is represented.
        </Paragraph>

        <SqlTable
          :data="sample.address.before"
          dark
          description="A sample set of records for central ID <codetext>A</codetext>">
          <colgroup class="w-[14%]" />
          <colgroup class="w-[12%]" />
          <colgroup class="w-[16%]" />
          <colgroup class="w-[16%]" />
          <colgroup class="w-[16%]" />
          <colgroup class="w-[26%]" />
        </SqlTable>

        <SqlTable
          :data="sample.address.after"
          dark
          description="The final set of address after applying the sorting algorithm for central ID <codetext>A</codetext>" />
      </QuaternaryTopic>
    </TertiaryTopic>
  </SecondaryTopic>
</template>
