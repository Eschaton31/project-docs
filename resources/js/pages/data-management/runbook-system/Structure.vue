<script setup>
import Paragraph from '@/components/Paragraph.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import ConceptList from '@/components/lists/ConceptList.vue'
import FileTree from '@/components/lists/FileTree.vue'

defineProps({
  start: [Number, String],
})

const structure = [
  {
    type: 'folder',
    name: 'ohasis-dqt',
    children: [
      { type: 'folder', name: '.secrets' },
      { type: 'folder', name: 'scratch' },
      {
        type: 'folder',
        name: 'src',
        children: [
          { type: 'folder', name: 'classes' },
          { type: 'folder', name: 'dependencies' },
          {
            type: 'folder',
            name: 'dsa',
            children: [
              { type: 'folder', name: 'pepfar' },
              { type: 'folder', name: 'protects-upscale' },
              { type: 'folder', name: 'recency' },
            ],
          },
          {
            type: 'folder',
            name: 'integration',
            children: [
              { type: 'folder', name: 'qc' },
              { type: 'folder', name: 'tly' },
            ],
          },
          {
            type: 'folder',
            name: 'official',
            children: [
              { type: 'folder', name: 'harp_dead' },
              { type: 'folder', name: 'harp_dx' },
              { type: 'folder', name: 'harp_linked' },
              { type: 'folder', name: 'harp_tx' },
              { type: 'folder', name: 'harp_vl' },
              { type: 'folder', name: 'prep' },
            ],
          },
          { type: 'folder', name: 'pipeline' },
        ],
      },
      { type: 'file', name: '.Renviron' },
      { type: 'file', name: '.Rprofile' },
      { type: 'r', name: 'autoload.R' },
      { type: 'text', name: 'requirements.txt' },
      { type: 'config', name: 'my.cnf' },
      { type: 'config', name: '{lw-connections}.yaml' },
    ],
  },
]

const files = [
  { value: 'Define environment variables used by R.', id: '.Renviron' },
  {
    value:
      'R startup scripts executed before base R; typically used to set the IDE library path when a non‑default library is required.',
    id: '.Rprofile',
  },
  {
    value: 'Central autoloader for the runbook system; discovers and registers components at startup.',
    id: 'autoload.R',
  },
  { value: 'List of packages required by the system.', id: 'requirements.txt' },
  { value: 'MariaDB client configuration for connections.', id: 'my.cnf' },
  { value: 'Per‑schema ClickHouse connection definitions.', id: '{lw-connections}.yaml' },
]

const folders = [
  {
    id: '.secrets',
    value:
      'Serves as the authentication cache for the system (i.e., Google API tokens and other runtime credentials). Treat this directory as sensitive (add <codetext>.secrets/</codetext> to <codetext>.gitignore</codetext>).',
  },
  {
    id: 'scratch',
    value:
      'A workspace for ad‑hoc files used by data managers that fall outside the normal pipeline flow. Files here are ephemeral and should be excluded from the canonical changelist (add <codetext>scratch/</codetext> to <codetext>.gitignore</codetext>); use it for temporary exports, local debugging artifacts, or one‑off processes.',
  },
  {
    id: 'src',
    value: 'Contains the framework’s core logic, modules, utilities, and tests.',
    children: [
      { id: 'classes', value: 'Reusable top‑level classes for workflows and domain models.', children: [] },
      { id: 'dependencies', value: 'System dependency helpers and shared utility functions.', children: [] },
      {
        id: 'integration',
        value: 'Third-party data import integrations. Contains the Import Pipelines.',
        children: [],
      },
      {
        id: 'official',
        value:
          'Production‑ready workflows; each subdirectory contains pipelines optimized for routine processing and predictable outputs. Contains most of the Surveillance Pipelines.',
        children: [],
      },
      {
        id: 'pipeline',
        value: 'Backend functions that implement custom <codetext>flow</codetext> templates and orchestration logic.',
        children: [],
      },
    ],
  },
]
</script>

<template>
  <SecondaryTopic :start title="Structure">
    <Paragraph>
      The runbook repository is organized for autoloading, composed of discrete steps that encapsulate specific backend
      requirements and workflows. These step modules are discovered and loaded automatically at runtime, then registered
      into a shared global environment so data managers can invoke, compose, and reuse them consistently.
    </Paragraph>
    <FileTree :list="structure" />
    <Paragraph>
      Root‑level files perform environment initialization and provide shared services—most notably authentication
      stores, configuration loaders, and common utilities—that functions call at runtime.
    </Paragraph>
    <ConceptList :list="files" em-dash highlight />
    <Paragraph>
      The repository separates runtime secrets, temporary artifacts, and application code into distinct locations to
      improve security, discoverability, and maintainability.
    </Paragraph>
    <ConceptList :list="folders" em-dash highlight />
  </SecondaryTopic>
</template>
