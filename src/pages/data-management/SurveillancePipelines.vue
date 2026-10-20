<script setup>
import DataDictionary from '@/components/tables/DataDictionary.vue'
import SqlTable from '@/components/tables/SqlTable.vue'

import Alert from '@/components/Alert.vue'
import CodeBlock from '@/components/CodeBlock.vue'
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import ConceptList from '@/components/lists/ConceptList.vue'
import FileTree from '@/components/lists/FileTree.vue'

const sample = {
  corr_reg: [
    {
      period: '2025.08',
      idnum: 191316,
      variable: 'age',
      old_value: '31',
      new_value: '23',
      format: 'integer',
    },
    {
      period: '2025.08',
      idnum: 199694,
      variable: 'last',
      old_value: 'DUCUSIN',
      new_value: 'PINEDA',
      format: 'character',
    },
    {
      period: '2025.08',
      idnum: 199694,
      variable: 'middle',
      old_value: 'NMN',
      new_value: '',
      format: 'character',
    },
    {
      period: '2025.08',
      idnum: 200814,
      variable: 'hts_date',
      old_value: '2024-02-05',
      new_value: '2025-02-05',
      format: 'Date',
    },
  ],
}

const columns = {
  corr_reg: [
    { field: 'period', description: 'reporting period' },
    {
      field: '<i>{row_id}</i>',
      description: 'unique row identifier in the harp_dx dataset and is used to select the row to modify',
    },
    { field: 'variable', description: 'column to be updated' },
    { field: 'old_value', description: 'current value for auditability' },
    { field: 'new_value', description: 'replacement value' },
    {
      field: 'format',
      description:
        "(deprecated) instructs R how to transform the new value so it matches the target column's data type",
    },
  ],
  version: [
    {
      field: 'type',
      description: 'dataset information type',
      type: 'String',
      values: '<codetext>reg</codetext>, <codetext>outcome</codetext>',
    },
    {
      field: 'period',
      description: 'reporting period covered',
      type: 'String',
      values: ['Format: <codetext>yyyy.mm</codetext>', '<codetext>2025.07</codetext>'],
    },
    {
      field: 'last_update',
      description:
        'records the date/time when the dataset was upserted completely. In the process-commit model, this will remain unchanged when the dataset has been fully committed',
      type: 'DateTime64',
      values: ['Format: <codetext>yyyy-mm-dd hh:ii:ss</codetext>', '<codetext>2025-07-28 11:48:44</codetext>'],
    },
  ],
}

const dvc = {
  issues: [
    {
      id: 'Single source of truth risk',
      value:
        'Multiple distributed copies made it difficult to guarantee which file represented the authoritative dataset.',
    },
    {
      id: 'Security and control',
      value:
        'Copies circulated across users increased the risk of unauthorized access, accidental modification, or inconsistent versions.',
    },
    {
      id: 'Version uncertainty',
      value:
        'Recipients could be unsure whether their copy reflected the latest corrections, reconciliations, or schema changes.',
    },
  ],
  temporaryFixes: [
    {
      id: 'Version',
      value: 'Indicates the date when the dataset was generated. Takes the format <codetext>yyyymmdd</codetext>.',
    },
    {
      id: 'Dataset Label',
      value:
        'Nickname for the dataset and its primary contents (i.e., <codetext>reg</codetext> for the HIV Case Registry, <codetext>mort</codetext> for the Mortality Registry, etc.). ',
    },
    {
      id: 'Reporting Period',
      value:
        'The relevant reporting period for which the dataset is officially valid. Takes the format <codetext>yyyy-mm</codetext>.',
    },
  ],
  sampleTree: [
    {
      type: 'server',
      name: 'Warehouse',
      children: [
        {
          type: 'schema',
          name: 'harp_dx',
          children: [
            { type: 'table', name: 'reg_202201' },
            { type: 'table', name: '...' },
            { type: 'table', name: 'reg_202511' },
            { type: 'table', name: 'version' },
          ],
        },
        {
          type: 'schema',
          name: 'harp_tx',
          children: [
            { type: 'table', name: 'outcome_202201' },
            { type: 'table', name: '...' },
            { type: 'table', name: 'outcome_202511' },
            { type: 'table', name: 'version' },
          ],
        },
      ],
    },
  ],
  schema: [
    {
      id: 'Schema',
      value:
        'denotes the  coded name of the relevant surveillance. (i.e., <codetext>harp_dx</codetext> for HIV Case Registry, <codetext>harp_dead</codetext> for the Mortality Registry, etc.)',
    },
    {
      id: 'Table',
      value:
        'Indicates the type of dataset stored. Separated into correction tables, reference tables, and the surveillance tables.',
      children: [
        {
          id: 'Correction tables',
          value: 'prefixed by <codetext>corr_</codetext>',
        },
        {
          id: 'Reference tables',
          value:
            'various other information used as reference data for generation or general referencing (i.e., <codetext>version</codetext> )',
        },
        {
          id: 'Surveillance tables',
          value:
            'prefixed by either <codetext>reg_</codetext> and <codetext>outcome_</codetext>; suffixed by the related reporting period in <codetext>yyyymm</codetext> format',
        },
      ],
    },
  ],
}
</script>

<template>
  <PrimaryTopic title="SURVEILLANCE PIPELINES">
    <SecondaryTopic start="1" title="Data Version Control">
      <TertiaryTopic title="Origins and History (2022 and prior)">
        <Paragraph>
          During its inception and for most of its operational history, the NHSSS Unit has produced reports on a monthly
          cadence. Consequently, surveillance datasets have been generated on the same monthly schedule to align with
          reporting requirements and workflows.
        </Paragraph>
        <Paragraph>
          Historically, these monthly extracts were exported as Stata (.dta) files and distributed to surveillance
          officers for analysis and report preparation. This export-and-distribute model was the primary mechanism for
          sharing analytic data.
        </Paragraph>
        <Paragraph>
          <div>Naturally, this presented a host of issues, the most important of which are:</div>
          <ConceptList :list="dvc.issues" />
        </Paragraph>
        <Paragraph>
          <div>Some measures were introduced to reduce these risks and improve traceability and control.</div>
        </Paragraph>
        <Paragraph>
          <Image
            description="File naming scheme for surveillance datasets"
            src="/img/conventions/dataset-filenames.png" />
          <ConceptList :list="dvc.temporaryFixes" />
        </Paragraph>
        <Paragraph>
          <Image
            description="Sample email blast to NHSSS Unit members upon dataset completion."
            src="/img/screenshots/email-blast-registry.png" />
          <div>
            Upon completion of datasets, surveillance leads would upload the zipped Stata fila to the
            <codetext>hivregistry.nec@gmail.com</codetext>
            Dropbox account. An expiring link is generated for the uploaded file/s to limit long-term exposure. After
            which, team members receive an email announcement with the download link and a brief changelog or notes
            about the extract.
          </div>
        </Paragraph>
        <Paragraph>
          The backend processes required to prepare and generate these datasets were continuously improving, with a
          migration to an R pipeline completed in 2019. However, this would remain to be the primary method of data
          dissemination utilized by the unit for all surveillance registries in its scope.
        </Paragraph>
      </TertiaryTopic>
      <TertiaryTopic title="OHASIS Lake/Warehouse">
        <Paragraph>
          In 2022, a migration effort to SQL-based analytics was initiated when a dedicated MariaDB setup was
          provisioned for a more streamlined pipeline process. This led to the establishment of a versioning system for
          the surveillance systems that prioritizes ease of access for analysts and maintainability for data managers.
        </Paragraph>
        <Paragraph>
          Whereas the old system utilized flat files as the storage medium for the datasets with versioning and
          reporting periods implicit in the filenames, the new system made use of database schemas and tables to make
          scopes and versioning more explicit.
        </Paragraph>
        <Paragraph>
          <ConceptList :list="dvc.schema" />
        </Paragraph>
        <FileTree :list="dvc.sampleTree" description="Sample schema for the HIV Case and Treatment Registries" />
        <Paragraph>
          Versioning information has been stored in the
          <codetext>version</codetext>
          table enclosed in each surveillance' schema. Details recorded here are only relevant for the surveillance
          tables. The breakdown of columns is as follows:
        </Paragraph>
        `
        <DataDictionary :data="columns.version" />
        `
        <Paragraph>
          This ensures that a separate table exists for each reporting period, serving as the "official" version of that
          data. Then, the metadata on when each reporting period gets completed stays within
          <codetext>version</codetext>
          tables.
        </Paragraph>
        <Alert title="Note">
          The legacy Stata exports continue to be produced alongside the new pipeline. This dual output exists to
          accommodate staff who are still completing SQL training and to support non‑DAT teams that do not use SQL
          day‑to‑day but periodically require datasets for HIV passive surveillance analysis.
        </Alert>
      </TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="2" title="Table Types">
      <TertiaryTopic title="Correction Tables">
        <QuaternaryTopic title="corr_reg">
          <Paragraph>
            This table contains every variable update that must be applied to preexisting rows in the dataset. Changes
            are applied only for the reporting period indicated by the
            <codetext>period</codetext>
            identifier.
          </Paragraph>
          <SqlTable :data="sample.corr_reg" description="Sample data from harp_dx' corr_reg table" />
          <DataDictionary :data="columns.corr_reg" />
          <CodeBlock
            code="ALTER TABLE `ohasis_warehouse`.`harp_dx_old`
UPDATE `middle` = dictGet('harp_dx.corrections-middle', 'value', `idnum`)
WHERE dictHas('harp_dx.corrections-middle', `idnum`)"
            lang="clickhouse"
            title="ClickHouse" />
        </QuaternaryTopic>
        <QuaternaryTopic title="corr_drop"></QuaternaryTopic>
      </TertiaryTopic>
      <TertiaryTopic title="Registry Tables"></TertiaryTopic>
      <TertiaryTopic title="Outcome Tables"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="3" title="Data Hydration">
      <TertiaryTopic title="Lake & Warehouse"></TertiaryTopic>
      <TertiaryTopic title="Reference Tables"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="4" title="Steps">
      <TertiaryTopic title="Requirements Loading (Extraction)">
        <QuaternaryTopic title="Get Corrections"></QuaternaryTopic>
        <QuaternaryTopic title="Cleaning the Previous Dataset"></QuaternaryTopic>
        <QuaternaryTopic title="Updating Reference Tables"></QuaternaryTopic>
        <QuaternaryTopic title="Fetching of Source Data"></QuaternaryTopic>
      </TertiaryTopic>
      <TertiaryTopic title="Dataset Generation (Transformation)"></TertiaryTopic>
      <TertiaryTopic title="Database Upsert (Loading)"></TertiaryTopic>
      <TertiaryTopic title="Surveillance-specific Steps"></TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="45" title="Entity Resolution">
      <TertiaryTopic title="Standardization"></TertiaryTopic>
      <TertiaryTopic title="Deduplication">
        <QuaternaryTopic title="Deterministic"></QuaternaryTopic>
        <QuaternaryTopic title="Probabilistic"></QuaternaryTopic>
      </TertiaryTopic>
      <TertiaryTopic title="Record Linkage"></TertiaryTopic>
    </SecondaryTopic>
  </PrimaryTopic>
</template>
