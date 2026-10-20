<script setup>
import { splitByPropertyMarkers } from '@/composables/functions.js'

import ItemDescriptionTable from '@/components/tables/ItemDescriptionTable.vue'

import Alert from '@/components/Alert.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'

const columns = {
  pahiDx1: [
    { item: 'central_id', description: 'Central ID' },
    { item: 'patient_id', description: 'Patient ID' },
    { item: 'uic', description: 'UIC' },
    { item: 'year_dx', description: 'Year recorded in the HIV Case Registry' },
    { item: 'month_dx', description: 'Month recorded in the HIV Case Registry' },
    { item: 'age_dx', description: 'Age recorded in the HIV Case Registry' },
    { item: 'agegrp', description: 'Age group recorded in the HIV Case Registry' },
    { item: 'curage', description: 'Age as of the end of the reporting period' },
    { item: 'sex', description: 'Sex (at birth)' },
    { item: 'mot', description: 'Mode of Transmission' },
    { item: 'msm', description: 'Males who have sex with males' },
    { item: 'symp', description: 'Advanced AHD (old classification)' },
    { item: 'symp2022', description: 'Advanced AHD (2022 WHO updated classification)' },
    { item: 'region', description: 'Region of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'province', description: 'Province of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'muncity', description: 'City/Municipality of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'hub', description: 'Official Treatment Hub where client last acccessed treatment' },
    { item: 'realhub', description: 'Dispensing Hub where client last acccessed ARVs' },
    { item: 'everonart', description: 'Client was enrolled to ART' },
    { item: 'onart', description: 'Client is currently alive on ARVs' },
    { item: 'artstart_year', description: 'Year when client was enrolled to ART' },
    { item: 'artstart_month', description: 'Month when client was enrolled to ART' },
    { item: 'art_outcome', description: 'Current ART Outcome' },
    { item: 'tx_munc', description: 'City/Municipality of the Treatment Hub' },
    { item: 'tx_prov', description: 'Province of the Treatment Hub' },
    { item: 'tx_reg', description: 'Region of the Treatment Hub' },
    { item: 'dead', description: 'Client was reported dead' },
    { item: 'death_year', description: 'Year when client was reported dead' },
    { item: 'death_month', description: 'Month when client was reported dead' },
    { item: 'dx_region', description: 'Region of the Diagnosing Laboratory' },
    { item: 'dx_province', description: 'Province of the Diagnosing Laboratory' },
    { item: 'dx_muncity', description: 'City/Municipality of the Diagnosing Laboratory' },
    {
      item: 'dxlab_standard',
      description:
        'Diagnosing Laboratory; Site/Organization that conducted the last HIV test prior to confirmatory testing',
    },
    { item: 'pubpriv', description: 'Ownership of the Diagnosing Laboratory' },
    { item: 'ofw', description: 'Client was an OFW' },
    { item: 'pregnant', description: 'Client was pregnant at the time of diagnosis' },
    { item: 'confirm_lab', description: 'HIV Confirmatory: Confirming Laboratory' },
    { item: 'rhivda_done', description: 'HIV Confirmatory of the client was done through CrCLs' },
    { item: 'vl_tested', description: "Client's latest viral load test was not a baseline test" },
    { item: 'vl_suppressed', description: "Client's latest viral load test suppressed" },
    { item: 'vl_date', description: "Client's latest viral load test date" },
    { item: 'vl_result', description: "Client's latest viral load test result" },
    { item: 'vlp12m', description: "Client's latest viral load test in the past 12 months" },
    { item: 'baseline_vl', description: "Client's latest viral load test is a baseline test" },
    { item: 't0_date', description: 'Record T0 Date prior to HIV Confirmatory' },
    { item: 't1_date', description: 'Record T1 Date for the HIV Confirmatory' },
    { item: 'transact_sex', description: 'Client engaged in transactional sex' },
  ],
  pahiDx2: [
    { item: 'central_id', description: 'Central ID' },
    { item: 'patient_id', description: 'Patient ID' },
    { item: 'uic', description: 'UIC' },
    { item: 'year_dx', description: 'Year recorded in the HIV Case Registry' },
    { item: 'month_dx', description: 'Month recorded in the HIV Case Registry' },
    { item: 'age_dx', description: 'Age recorded in the HIV Case Registry' },
    { item: 'agegrp', description: 'Age group recorded in the HIV Case Registry' },
    { item: 'curage', description: 'Age as of the end of the reporting period' },
    { item: 'cagegrp', description: 'Age group recorded in the HIV Case Registry' },
    { item: 'sex', description: 'Sex (at birth)' },
    { item: 'mot', description: 'Mode of Transmission' },
    { item: 'msm', description: 'Males who have sex with males' },
    { item: 'symp', description: 'Advanced AHD (old classification)' },
    { item: 'region', description: 'Region of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'province', description: 'Province of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'muncity', description: 'City/Municipality of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'hub', description: 'Official Treatment Hub where client last acccessed treatment' },
    { item: 'realhub', description: 'Dispensing Hub where client last acccessed ARVs' },
    { item: 'everonart', description: 'Client was enrolled to ART' },
    { item: 'onart', description: 'Client is currently alive on ARVs' },
    { item: 'artstart_year', description: 'Year when client was enrolled to ART' },
    { item: 'artstart_month', description: 'Month when client was enrolled to ART' },
    { item: 'art_outcome', description: 'Current ART Outcome' },
    { item: 'tx_munc', description: 'City/Municipality of the Treatment Hub' },
    { item: 'tx_prov', description: 'Province of the Treatment Hub' },
    { item: 'tx_reg', description: 'Region of the Treatment Hub' },
    { item: 'dead', description: 'Client was reported dead' },
    { item: 'death_year', description: 'Year when client was reported dead' },
    { item: 'death_month', description: 'Month when client was reported dead' },
    { item: 'dx_region', description: 'Region of the Diagnosing Laboratory' },
    { item: 'dx_province', description: 'Province of the Diagnosing Laboratory' },
    { item: 'dx_muncity', description: 'City/Municipality of the Diagnosing Laboratory' },
    {
      item: 'dxlab_standard',
      description:
        'Diagnosing Laboratory; Site/Organization that conducted the last HIV test prior to confirmatory testing',
    },
    { item: 'pubpriv', description: 'Ownership of the Diagnosing Laboratory' },
    { item: 'ofw', description: 'Client was an OFW' },
    { item: 'pregnant', description: 'Client was pregnant at the time of diagnosis' },
    { item: 'confirm_lab', description: 'HIV Confirmatory: Confirming Laboratory' },
    { item: 'rhivda_done', description: 'HIV Confirmatory of the client was done through CrCLs' },
    { item: 'vl_tested', description: "Client's latest viral load test was not a baseline test" },
    { item: 'vl_suppressed', description: "Client's latest viral load test suppressed" },
    { item: 'vl_date', description: "Client's latest viral load test date" },
    { item: 'vl_result', description: "Client's latest viral load test result" },
    { item: 'vlp12m', description: "Client's latest viral load test in the past 12 months" },
    { item: 'baseline_vl', description: "Client's latest viral load test is a baseline test" },
    { item: 't0_date', description: 'Record T0 Date prior to HIV Confirmatory' },
    { item: 't1_date', description: 'Record T1 Date for the HIV Confirmatory' },
    { item: 'transact_sex', description: 'Client engaged in transactional sex' },
    { item: 'confirm_date', description: 'Date confirmed HIV positive' },
    { item: 'specimen_receipt_date', description: 'Date when specimen was received by SACCL/CrCLs' },
    { item: 'artstart_date', description: 'Date when client was enrolled to ART' },
    { item: 'sample_source', description: 'Was the specimen walk-in or referral from laboratories' },
  ],
  pahiTx: [
    { item: 'central_id', description: 'Central ID' },
    { item: 'rec_id', description: 'Record ID' },
    { item: 'hub', description: 'Official Treatment Hub where client last acccessed treatment' },
    { item: 'realhub', description: 'Dispensing Hub where client last acccessed ARVs' },
    { item: 'sex', description: 'Sex (at birth)' },
    { item: 'curage', description: 'Age as of the end of the reporting period' },
    { item: 'cagegrp', description: 'Age group recorded in the HIV Case Registry' },
    { item: 'mot', description: 'Mode of Transmission' },
    { item: 'msm', description: 'Males who have sex with males' },
    { item: 'artstart_year', description: 'Year when client was enrolled to ART' },
    { item: 'artstart_month', description: 'Month when client was enrolled to ART' },
    { item: 'art_outcome', description: 'Current ART Outcome' },
    { item: 'onart', description: 'Client is currently alive on ARVs' },
    { item: 'tx_reg', description: 'Region of the Treatment Hub' },
    { item: 'tx_prov', description: 'Province of the Treatment Hub' },
    { item: 'tx_munc', description: 'City/Municipality of the Treatment Hub' },
    { item: 'region', description: 'Region of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'province', description: 'Province of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'muncity', description: 'City/Municipality of Permanent Residence recorded in the HIV Case Registry' },
    { item: 'dx_region', description: 'Region of the Diagnosing Laboratory' },
    { item: 'dx_province', description: 'Province of the Diagnosing Laboratory' },
    { item: 'dx_muncity', description: 'City/Municipality of the Diagnosing Laboratory' },
    {
      item: 'dxlab_standard',
      description:
        'Diagnosing Laboratory; Site/Organization that conducted the last HIV test prior to confirmatory testing',
    },
    { item: 'death_year', description: 'Year when client was reported dead' },
    { item: 'death_month', description: 'Month when client was reported dead' },
    { item: 'regimen', description: "Client's latest regimen" },
    { item: 'vl_tested', description: "Client's latest viral load test was not a baseline test" },
    { item: 'vl_suppressed', description: "Client's latest viral load test suppressed" },
    { item: 'vl_date', description: "Client's latest viral load test date" },
    { item: 'vl_result', description: "Client's latest viral load test result" },
    { item: 'vlp12m', description: "Client's latest viral load test in the past 12 months" },
    { item: 'baseline_vl', description: "Client's latest viral load test is a baseline test" },
  ],
  pahiDead: [
    { item: 'central_id', description: 'Central ID' },
    { item: 'patient_id', description: 'Patient ID' },
    { item: 'sex', description: 'Sex (at birth)' },
    { item: 'age_dx', description: 'Age recorded in the HIV Case Registry' },
    { item: 'year_dx', description: 'Year recorded in the HIV Case Registry' },
    { item: 'month_dx', description: 'Month recorded in the HIV Case Registry' },
    { item: 'age_death', description: 'Age at the time of death' },
    { item: 'death_year', description: 'Year when client was reported dead' },
    { item: 'death_month', description: 'Month when client was reported dead' },
    { item: 'death_reg', description: 'Region of Permanent Residence recorded in the HIV Mortality Registry' },
    { item: 'death_prov', description: 'Province of Permanent Residence recorded in the HIV Mortality Registry' },
    {
      item: 'death_muncity',
      description: 'City/Municipality of Permanent Residence recorded in the HIV Mortality Registry',
    },
    { item: 'immediate_cause', description: 'Immediate cause/s of death' },
    { item: 'antecedent_cause', description: 'Antecedent cause/s of death' },
    { item: 'underlying_cause', description: 'Underlying cause/s of death' },
    { item: 'report_notes', description: 'Additional notes on the nature of death' },
    { item: 'hiv_related_cause', description: 'Other HIV-related cause/s of death' },
  ],
}
</script>

<template>
  <PrimaryTopic title="HASP LINELIST CATALOG">
    <Paragraph>
      This section outlines the available HASP linelists that end users can export. It also specifies the required
      settings and permissions needed to enable each linelist type, ensuring users understand both the functional
      options and the access controls that govern them.
    </Paragraph>
    <Alert>
      These linelists are only available by setting the <b>period type</b> to <b>Month</b>, and choosing the
      <b>Regional HASP</b> data option under the relevant modules.
    </Alert>
    <Alert type="warn"> Only RESUs have access to these types of linelists.</Alert>

    <SecondaryTopic start="1" title="HIV Case Registry">
      <Paragraph>
        A case-based surveillance that holds the official record of HIV positive individuals that were confirmed by the
        National Reference Laboratory – San Lazaro Hospital STD AIDS Cooperative Central Laboratory (NRL-SLH/SACCL) or
        testing facilities that were accredited by NRL-SLH/SACCL as Certified Rapid HIV Confirmatory Laboratories
        (CrCL).
      </Paragraph>
      <Alert> Requires the <b>Export→Test→HASP</b> permission.</Alert>
      <Paragraph> Available for export in two (2) geographic filters:</Paragraph>
      <TertiaryTopic title="Permanent Residence">
        <Paragraph>
          Filtered using the final recorded permanent residence in the national HIV case registry. This filter is
          primarily used to compare <b>Diagnosed PLHIV</b> against the <b>Estimated PLHIV</b>.
        </Paragraph>

        <ItemDescriptionTable :data="columns.pahiDx1" description-name="Description" item-name="Column" mono />
      </TertiaryTopic>
      <TertiaryTopic title="Facility Location">
        <Paragraph>
          Filtered based on the site/organization that conducted the last HIV test prior to confirmatory testing. This
          filter is particularly useful for generating facility‑level cascades.
        </Paragraph>
        <ItemDescriptionTable :data="columns.pahiDx2" description-name="Description" item-name="Column" mono />
      </TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="2" title="HIV Treatment Registry">
      <Paragraph>
        A case‑based surveillance system that maintains the official records of individuals receiving antiretroviral
        therapy (ART) across all accredited treatment hubs nationwide. It also captures each client’s health outcomes
        and the latest available clinical information.
      </Paragraph>
      <Alert> Requires the <b>Export→Treat→HASP</b> permission.</Alert>
      <ItemDescriptionTable :data="columns.pahiTx" description-name="Description" item-name="Column" mono />
    </SecondaryTopic>
    <SecondaryTopic start="3" title="HIV Mortality Registry">
      <Paragraph>
        A case‑based surveillance system that maintains the official records of people with HIV who have died from any
        cause. The deaths reflected in this dataset are only those that have been confirmed and validated by RESUs,
        DOH‑EB, or the Philippine Statistics Authority (PSA).
      </Paragraph>
      <Alert> Requires the <b>Export→General→HASP</b> permission.</Alert>
      <ItemDescriptionTable :data="columns.pahiDead" description-name="Description" item-name="Column" mono />
    </SecondaryTopic>
  </PrimaryTopic>
</template>
