<script setup>
import Paragraph from '@/components/Paragraph.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

const requirements = [
  {
    id: 'Minimum',
    value: '',
    children: [
      { id: 'PROCESSOR', value: 'Intel Core i5-8400 / AMD Ryzen 5 1600' },
      { id: 'MEMORY', value: '16 GB RAM' },
      { id: 'STORAGE', value: '500 GB HDD (7200 RPM)' },
    ],
  },
  {
    id: 'Recommended',
    value: '',
    children: [
      {
        id: 'PROCESSOR',
        value: 'Intel Core i7-10700K @ 3.8 GHz / AMD Ryzen 5 3600 @ 3.6 GHz',
      },
      { id: 'MEMORY', value: '32 GB RAM' },
      { id: 'STORAGE', value: '1 TB SSD' },
    ],
  },
  {
    id: 'Comfortable',
    value: '',
    children: [
      {
        id: 'PROCESSOR',
        value: 'Intel Core i7-12700K @ 3.6 GHz / AMD Ryzen 7 7700 @ 3.8 GHz',
      },
      { id: 'MEMORY', value: '64-128 GB RAM' },
      { id: 'STORAGE', value: '2 TB SSD' },
    ],
  },
]

const networking = [
  {
    id: 'Bandwidth',
    value: '70-100 Mbps (sustained)',
  },
  {
    id: 'ZeroTier Configuration',
    value: '',
    children: [
      {
        id: 'Network ID',
        value: '1d71939404a26dba',
      },
      { id: 'Node Latency', value: '1-100 ms' },
    ],
  },
]
</script>

<template>
  <SecondaryTopic title="Infrastructure">
    <Paragraph>
      This section outlines the baseline and recommended hardware specifications for machines used by data managers.
      These requirements apply to physical workstations, virtual machines, or cloud‑based instances. An equivalent or
      higher‑performance configuration is encouraged, especially for users handling large datasets or running multiple
      pipelines concurrently.
    </Paragraph>
    <TertiaryTopic title="Workstation">
      <Paragraph>
        A 64‑bit processor and operating system are required. The tiers below reflect hardware that has historically
        performed adequately; actual performance will vary depending on workload, background processes, and dataset
        size.
      </Paragraph>
      <ConceptList :list="requirements" />
    </TertiaryTopic>
    <TertiaryTopic title="Networking">
      <Paragraph>
        A stable, secure, and low‑latency network connection is essential for interacting with the EHR, database
        servers, and the Lake/Warehouse. ZeroTier connectivity is mandatory for accessing internal systems, and each
        participating node must meet the minimum performance requirements to ensure reliable pipeline execution and
        smooth debugging.
      </Paragraph>
      <Paragraph>
        The specifications below assume a per‑node configuration, meaning every node in the network should meet or
        exceed these conditions.
      </Paragraph>
      <ConceptList :list="networking" />
    </TertiaryTopic>
  </SecondaryTopic>
</template>
