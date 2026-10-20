<script setup>
import KeyValueTable from '@/components/tables/KeyValueTable.vue'

import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import BulletList from '@/components/lists/BulletList.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

let figures = [{ id: 0, src: '/img/conventions/server-naming.png' }]
figures = figures.map((item, index) => {
  return {
    ...item,
    id: index + 1,
  }
})

const serverNaming = [
  {
    id: 'Project / Software Use',
    value: 'project where the server was intended to be used',
    children: [
      { id: 'EHARP', value: 'procured specifically to host the eHARP software system' },
      { id: 'NHSSS', value: 'procured under IHBSS 2022 funding to be used for various NHSSS Unit roles' },
      { id: 'OHASIS', value: 'donated by DOH-EB (COVID) to host the OHASIS software system' },
    ],
  },
  { id: 'Server Unit', value: 'blanket term for the type of hardware' },
  {
    id: 'Control Number',
    value: 'incremental control number under the project; based on the procurement/receipt date to the unit',
  },
]

const remoteProtocols = [
  {
    id: 'Remote Desktop Protocol (RDP)',
    value:
      'the servers have the RDP port enabled to facilitate remote access management. Utilization of this protocol is recommended when updated the operating system, update/restarting applications, or general system-level maintenance.',
  },
  {
    id: 'Secure Shell (SSH)',
    value:
      'remote access to the servers via shell (see command-line for windows) using secure protocols. No GUI is provided for this method.',
  },
]

const physical = [
  {
    name: 'EHARP-SU-001',
    purpose: ['Hosted the eHARP Webserver', 'Hosted the eHARP Database', 'Hosted the OHASIS v1.0 live platform'],
    specs: [
      { id: 'Install Date', value: '2017' },
      { id: 'Hardware', value: 'Dell PowerEdge R540' },
      { id: 'OS', value: 'Windows Server 2016 Standard' },
      { id: 'CPU', value: 'Intel(R) Xeon(R) Gold 6126 CPU @ 2.60GHz 2.59 GHz' },
      { id: 'RAM', value: '32 GB' },
      { id: 'Storage', value: ['Main Drive: 100 GB', 'Aux Drive: 3 TB '] },
      { id: 'Location', value: 'DOH-KMITS Data Center' },
      { id: 'Network', value: 'DOH-KMITS Data Center' },
      { id: 'Local IP', value: '192.168.9.225' },
      { id: 'ZeroTier IP', value: '192.168.193.232' },
    ],
  },
  {
    name: 'EHARP-SU-002',
    purpose: [
      'Hosted the rHIVda 20-minute automated deduplication',
      'Hosted the OHASIS v1.0 automated database backup system',
    ],
    specs: [
      { id: 'Install Date', value: '2017' },
      { id: 'Hardware', value: 'Dell PowerEdge R540' },
      { id: 'OS', value: 'Windows Server 2016 Standard' },
      { id: 'CPU', value: 'Intel(R) Xeon(R) Gold 6126 CPU @ 2.60GHz 2.59 GHz' },
      { id: 'RAM', value: '32 GB' },
      { id: 'Storage', value: ['Main Drive: 100 GB', 'Aux Drive: 3 TB '] },
      { id: 'Location', value: 'DOH-KMITS Data Center' },
      { id: 'Network', value: 'DOH-KMITS Data Center' },
      { id: 'Local IP', value: '192.168.9.149' },
      { id: 'ZeroTier IP', value: '192.168.193.131' },
    ],
  },
  {
    name: 'NHSSS-SU-001',
    purpose: [
      'Hosted the NHSSS’ knowledge base',
      'Hosted the IHBSS 2022 ODK Central',
      'Hosting the OHASIS v2.0 trial platform',
      'Hosting the OHASIS v2.0 Redis server',
    ],
    specs: [
      { id: 'Install Date', value: '2021' },
      { id: 'Hardware', value: 'HPE ProLiant DL20 Gen10' },
      { id: 'OS', value: 'Windows Server 2019 Standard' },
      { id: 'CPU', value: 'Intel(R) Xeon(R) E-2236 CPU @ 3.40GHz   3.41 GHz' },
      { id: 'RAM', value: '32 GB' },
      { id: 'Storage', value: ['Main Drive: 600 GB', 'Aux Drive: 2 TB '] },
      { id: 'Location', value: ['DOH-EB Room 209', 'Top Server'] },
      { id: 'Network', value: 'HIV-MeshNet (PLDT)' },
      { id: 'Local IP', value: '192.168.68.33' },
      { id: 'ZeroTier IP', value: '192.168.193.236' },
    ],
  },
  {
    name: 'NHSSS-SU-002',
    purpose: [
      'Hosting the OHASIS v1.0 trial platform',
      'Hosted the OHASIS v1.0 data warehouse',
      "Hosting the NHSSS' Superset instance",
    ],
    specs: [
      { id: 'Install Date', value: '2021' },
      { id: 'Hardware', value: 'HPE ProLiant DL20 Gen10' },
      { id: 'OS', value: 'Windows Server 2019 Standard' },
      { id: 'CPU', value: 'Intel(R) Xeon(R) E-2236 CPU @ 3.40GHz   3.41 GHz' },
      { id: 'RAM', value: '32 GB' },
      { id: 'Storage', value: ['Main Drive: 600 GB', 'Aux Drive: 2 TB '] },
      { id: 'Location', value: ['DOH-EB Room 209', 'Top Server'] },
      { id: 'Network', value: 'HIV-MeshNet (PLDT)' },
      { id: 'Local IP', value: '192.168.68.48' },
      { id: 'ZeroTier IP', value: '192.168.193.228' },
    ],
  },
  {
    name: 'OHASIS-SU-001',
    purpose: ['Hosting the OHASIS v2.0 Webserver'],
    specs: [
      { id: 'Install Date', value: '2025' },
      { id: 'Hardware', value: 'Dell PowerEdge T440' },
      { id: 'OS', value: 'Windows Server 2025 Standard' },
      { id: 'CPU', value: 'Intel(R) Xeon(R) Silver 4110 CPU @ 2.10GHz   2.10 GHz' },
      { id: 'RAM', value: '64 GB' },
      { id: 'Storage', value: ['Main Drive: 60 GB', 'Aux Drive: 4.962 TB '] },
      { id: 'Location', value: ['DOH-EB Room 209', 'Left Server'] },
      { id: 'Network', value: 'HIV-MeshNet (PLDT)' },
      { id: 'Local IP', value: '192.168.68.22' },
      { id: 'ZeroTier IP', value: '192.168.193.21' },
    ],
  },
  {
    name: 'OHASIS-SU-002',
    purpose: ['Hosting the OHASIS v2.0 database', 'Hosting the OHASIS v2.0 automated database backup system'],
    specs: [
      { id: 'Install Date', value: '2025' },
      { id: 'Hardware', value: 'Dell PowerEdge T440' },
      { id: 'OS', value: 'Windows Server 2025 Standard' },
      { id: 'CPU', value: 'Intel(R) Xeon(R) Silver 4110 CPU @ 2.10GHz   2.10 GHz' },
      { id: 'RAM', value: '64 GB' },
      { id: 'Storage', value: ['Main Drive: 127 GB', 'Aux Drive: 4.895 TB '] },
      { id: 'Location', value: ['DOH-EB Room 209', 'Right Server'] },
      { id: 'Network', value: 'HIV-MeshNet (PLDT)' },
      { id: 'Local IP', value: '192.168.68.36' },
      { id: 'ZeroTier IP', value: '192.168.193.22' },
    ],
  },
]
const vm = [
  {
    name: 'HARP Cloud',
    purpose: ['Hosting the NHSSS Cloud Storage platform'],
    specs: [
      { id: 'Install Date', value: '2020' },
      { id: 'Type', value: 'VMWare Workstation' },
      { id: 'Container Name', value: 'Ubuntu 64-bit' },
      { id: 'OS', value: 'Ubuntu 20.04 LTS' },
      { id: 'Host', value: 'EHARP-SU-001' },
      { id: 'Network', value: 'NAT via Host' },
      { id: 'Local IP', value: '192.168.32.128' },
      { id: 'ZeroTier IP', value: '192.168.193.80' },
    ],
  },
  {
    name: 'OHASIS Lake-Warehouse',
    purpose: ['Hosting the OHASIS v2.0 warehousing database'],
    specs: [
      { id: 'Install Date', value: '2025' },
      { id: 'Type', value: 'Docker via WSL2' },
      { id: 'Container Name', value: 'ohasis-lw' },
      { id: 'OS', value: 'Docker Image' },
      { id: 'Host', value: 'NHSSS-SU-001' },
      { id: 'Network', value: 'Host Network' },
    ],
  },
  {
    name: 'OHASIS Change Data Capture',
    purpose: ['Hosting the ClickHouse sink connector for the OHASIS v2.0 live database to the warehouse'],
    specs: [
      { id: 'Install Date', value: '2025' },
      { id: 'Type', value: 'Docker via WSL2' },
      { id: 'Container Name', value: 'ohasis-replicator' },
      { id: 'OS', value: 'Docker Image' },
      { id: 'Host', value: 'NHSSS-SU-001' },
      { id: 'Network', value: 'Host Network' },
    ],
  },
  {
    name: 'NHSSS Project Management',
    purpose: ['Hosting the NHSSS Ticketing system (Plane)'],
    specs: [
      { id: 'Install Date', value: '2025' },
      { id: 'Type', value: 'Docker via WSL2' },
      { id: 'Container Name', value: 'plane-app' },
      { id: 'OS', value: 'Docker Image' },
      { id: 'Host', value: 'NHSSS-SU-001' },
      { id: 'Network', value: 'Host Network' },
    ],
  },
]

const onSite = [
  { id: 'Rm. 209', value: '' },
  { id: 'DOH-KMITS Data Center', value: '' },
]
</script>

<template>
  <PrimaryTopic title="INFRASTRUCTURE">
    <Paragraph>
      The OHASIS stack runs on a combination of physical servers, virtual machines (VMs), and supporting network and
      security infrastructure. Physical servers provide the underlying compute, storage, and networking resources, while
      VMs run isolated workloads on top of this hardware. The network layer connects all components and ensures reliable
      routing between clients, application services, and backend systems. Security spans every layer of the
      infrastructure. Servers must be protected against unauthorized access, data breaches, and service disruptions
      through a combination of physical safeguards, OS hardening, network controls, and strict access management.
    </Paragraph>
    <Paragraph>
      Together, these components form the operational foundation that keeps OHASIS stable, secure, maintainable, and
      available for use by end-users, data managers, analysts, and stakeholders.
    </Paragraph>
    <!-- Physical Servers -->
    <SecondaryTopic start="1" title="Physical Servers">
      <Paragraph>
        A physical server (also called a bare-metal server or an on-premise server) is a dedicated hardware machine (see
        computer). It runs an operating system directly on its hardware having full and exclusive access to all
        resources. Ideal for high‑performance workloads, strict security requirements, and scenarios where predictable,
        non‑shared compute power is essential.
      </Paragraph>
      <Paragraph>
        The unit currently maintains several servers that host the different components of the OHASIS ecosystem. This
        section will detail what those servers are, their location, access credentials, and overall impact to security
        and maintenance of the platform.
      </Paragraph>
      <Paragraph>
        The servers are referred to by the following convention:
        <div class="w-full text-center">
          <img :src="figures[0].src" alt="" class="mx-auto my-4 h-48 w-auto" />
        </div>
        <ConceptList :list="serverNaming" />
      </Paragraph>
      <TertiaryTopic title="Configurations">
        <Paragraph>
          Hardware configuration of the existing servers as of writing. Includes upgrades, if any, provided to the
          servers.
        </Paragraph>
        <template v-for="{ name, purpose, specs } in physical" :key="name">
          <QuaternaryTopic :title="name">
            <BulletList :list="purpose" />
            <KeyValueTable :table="specs" class="mt-4" />
          </QuaternaryTopic>
        </template>
      </TertiaryTopic>

      <TertiaryTopic title="Server Access">
        <Paragraph>
          System administrators can access servers in two primary ways to perform updates and routine maintenance.
        </Paragraph>
        <QuaternaryTopic title="On-site">
          <Paragraph>
            On‑site access involves physically entering the server room or data center to interact directly with the
            hardware or console, allowing administrators to perform tasks that require physical presence, such as
            hardware checks, cable management, or recovery procedures.
          </Paragraph>
          <Paragraph>
            Protocols for on-site server access differ per data center involved. Generally, access is regulated by a
            designated custodian, and is approved on a use-case basis (see <i>Data Centers</i>).
          </Paragraph>
        </QuaternaryTopic>
        <QuaternaryTopic title="Remote Access">
          <Paragraph>
            Remote access is typically done through secure protocols such as SSH and RDP, enabling administrators to
            manage servers from any authorized location while maintaining encrypted communication and strict
            authentication controls.
          </Paragraph>
          <ConceptList :list="remoteProtocols" />
        </QuaternaryTopic>
      </TertiaryTopic>
    </SecondaryTopic>

    <!-- Virtual Machines -->
    <SecondaryTopic start="2" title="Virtual Machines (VM)">
      <Paragraph>
        Software-based computers that emulate the functionality of a physical machine, often with its own operating
        system and applications in an isolated environment. It uses virtualization technology to allocate CPU, memory,
        storage, and other resources from a physical host, allowing multiple independent “guest” systems to run on the
        same hardware.
      </Paragraph>
      <Paragraph>
        A number of virtual machines are deployed within the previously listed physical servers, accomplishing various
        functions that cannot be fulfilled by the Windows-based hosts.
      </Paragraph>
      <Paragraph>
        These servers do not follow a set naming convention as they are provisioned to serve a single function. New
        containers are created and deployed as needed.
      </Paragraph>
      <TertiaryTopic title="Containers">
        <Paragraph>
          Currently in-use containers and their respective functions, as well as details on their deployment.
        </Paragraph>
        <template v-for="{ name, purpose, specs } in vm" :key="name">
          <QuaternaryTopic :title="name">
            <BulletList :list="purpose" />
            <KeyValueTable :table="specs" class="mt-4" />
          </QuaternaryTopic>
        </template>
      </TertiaryTopic>
    </SecondaryTopic>

    <!-- Networking -->
    <SecondaryTopic start="4" title="Networking">
      <Paragraph>
        The NHSSS unit currently maintains two primary network providers for internet connection to unit members. These
        are funded under two different mechanisms, and are used for some of the systems deployed. The OHASIS EHR system
        is under the DOH Data Center network, and is maintained by DOH-KMITS.
      </Paragraph>
    </SecondaryTopic>

    <!-- Data Centers -->
    <SecondaryTopic start="3" title="Data Centers">
      <Paragraph>
        These consist of a variety of software installations that run as either services or scheduled jobs in the
        various servers (both physical and virtual) which form the backbone of the various OHASIS components.
      </Paragraph>
    </SecondaryTopic>
  </PrimaryTopic>
</template>

<style scoped></style>
