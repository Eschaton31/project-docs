<script setup>
import KeyValueTable from '@/components/tables/KeyValueTable.vue'

import CodeBlock from '@/components/CodeBlock.vue'
import Paragraph from '@/components/Paragraph.vue'
import PrimaryTopic from '@/components/PrimaryTopic.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import BulletList from '@/components/lists/BulletList.vue'
import ConceptList from '@/components/lists/ConceptList.vue'
import FileTree from '@/components/lists/FileTree.vue'

let figures = [{ id: 0, src: '/img/conventions/server-naming.png' }]

const google = [
  {
    email: 'ohasis@doh.gov.ph',
    password: '',
    description: [
      'Official email account for the app. It handles automated outbound messages—notifications, OTP challenges, password resets—and supports general end‑user communications and support workflows. The account’s shared drive also serves as the primary endpoint for datasets and file sharing with external staff; access and retention controls are managed separately.',
    ],
  },
  {
    email: 'eb@loveyourself.ph',
    password: '',
    description: [
      'Official email account provisioned by LoveYourself, Inc. (LY) for DOH‑EB to access the organization’s data system. It provides direct access to datasets maintained by LY providers—HIV testing, PrEP, ART, TB, and viral load—and is used for data access, reporting, and coordination. Access controls, credential management, and sharing permissions are managed separately under LY/DOH policies.',
    ],
  },
  {
    email: 'hivepicenter@gmail.com',
    password: '',
    description: [
      'Former official NHSSS Unit account used for all formal communications before the 2021 migration to the DOH domain. Retained as an archival resource, it contains a large collection of official documents, policies, and knowledge‑base material that served as the primary endpoint prior to migration. Access is preserved for documentation and reference purposes only; any operational or external communications have moved to the DOH domain.',
    ],
  },
  {
    email: 'hivregistry.nec@gmail.com',
    password: '',
    description: [
      'Former NHSSS Unit account used for national HIV registry reporting. It remains active solely as the official account for the Dropbox subscription and for storing/reporting registry files; it is not used for routine communications. Access is retained for data delivery and archival purposes and should be managed under existing access and retention policies.',
    ],
  },
]
const services = [
  {
    name: 'Caddy',
    details: [
      {
        component: 'Paragraph',
        value:
          'Caddy is a web server written in Go. It runs on all major platforms and can serve static files, act as a reverse proxy, or manage complex routing through its modular architecture.',
      },
      {
        component: 'Paragraph',
        value:
          'As a reverse proxy, Caddy forwards incoming client requests to backend services and returns their responses as if they came from Caddy itself (that is, the primary host). It automatically handles TLS certificates, HTTP/2/3 support, and secure defaults. ',
      },
      {
        component: 'Paragraph',
        value:
          'In OHASIS v2.0 interim setup, this application is in charge of routing traffic to the different servers, load balancing the backends and placing the secure HTTPS layer in front of internal services (attaching the necessary certificates).',
      },
      {
        component: 'Paragraph',
        value:
          'Currently, the only Caddy server deployed can be found in EHARP-SU-001. It is CRON job executed as a scheduled task during Windows startup. The relevant files are as follows:',
      },
      {
        component: 'FileTree',
        value: [
          {
            type: 'folder',
            name: 'C:/',
            children: [
              {
                type: 'folder',
                name: 'Certificate',
                children: [
                  {
                    type: 'folder',
                    name: '20230220',
                    children: [
                      { type: 'file', name: 'ServerCertificate.crt', note: 'TLS Certificate File' },
                      { type: 'file', name: 'active-doh.gov.ph.key', note: 'TLS Certificate Key File' },
                    ],
                  },
                ],
              },
              {
                type: 'folder',
                name: '_automation',
                children: [
                  { type: 'exe', name: 'nssm.exe', note: 'service manager' },
                  { type: 'file', name: 'oh2-caddy', note: 'configuration file for the service' },
                  {
                    type: 'terminal',
                    name: 'oh2-caddy.cmd',
                    note: 'Windows batch file executed by the scheduled task',
                  },
                ],
              },
            ],
          },
        ],
      },
      {
        component: 'Paragraph',
        value:
          'The settings used by the Caddy service can be found in the <u>oh2-caddy</u> file. It is currently using the caddyfile adapter. NHSSS may opt to convert it into the JSON specifications as provided by the Caddy team.',
      },
      {
        component: 'CodeBlock',
        value: {
          lang: 'caddyfile',
          label: 'oh2-caddy',
          code: `ebreports-shc.doh.gov.ph {
    tls "C:/Certificate/20230220/ServerCertificate.crt" "C:/Certificate/20230220/active-doh.gov.ph.key"

    handle_path /apps* {
      reverse_proxy ws://192.168.193.21:8080
    }

    handle_path /app* {
      reverse_proxy ws://192.168.193.21:8080
    }

    handle /* {
        reverse_proxy 119.93.237.254:8765
    }
}`,
        },
      },
      {
        component: 'Paragraph',
        value: 'Below is the Windows batch file routinely executed via Task Scheduler.',
      },
      {
        component: 'CodeBlock',
        value: {
          lang: 'command-line',
          label: 'oh2-caddy.cmd',
          code: `caddy run --config oh2-caddy --adapter caddyfile`,
        },
      },
    ],
  },
  {
    name: '*AMP',
    details: [
      {
        component: 'Paragraph',
        value:
          'WAMP or LAMP (Windows/Linux, Apache, MySQL, PHP) are software stacks used to host and run web applications. They provide a complete environment for serving dynamic websites, combining a web server, database engine, and scripting language. Deployment typically involves preparing the stack, configuring virtual hosts, uploading application files, setting correct permissions, and applying database migrations or imports.',
      },
      {
        component: 'Paragraph',
        value:
          'There are currently four active *AMP setups utilized by the unit, forming the main components used in their day-to-day activities.',
      },
    ],
  },
]
</script>

<template>
  <PrimaryTopic title="SERVICES">
    <Paragraph>
      A consolidated set of applications, background processes, infrastructure components, and access accounts that
      provide the runtime, security, and management functions for the OHASIS ecosystem. The document that follows is
      organized into two sections: <b>Accounts</b> and <b>Applications</b>.
    </Paragraph>
    <SecondaryTopic start="1" title="Accounts">
      <TertiaryTopic title="Google Workspace">
        <QuaternaryTopic v-for="{ email, password, description } in google" :key="email" :title="email">
          <Paragraph v-for="(text, index) in description" :key="index"><span v-html="text" /></Paragraph>
        </QuaternaryTopic>
      </TertiaryTopic>
    </SecondaryTopic>
    <SecondaryTopic start="2" title="Applications">
      <TertiaryTopic v-for="{ name, details } in services" :key="name" :title="name">
        <template v-for="({ component, value }, index) in details" :key="index">
          <Paragraph v-if="component === 'Paragraph'" v-html="value" />
          <FileTree v-if="component === 'FileTree'" :list="value" />
          <CodeBlock v-if="component === 'CodeBlock'" :code="value.code" :label="value.label" :lang="value.lang" />
        </template>
      </TertiaryTopic>
    </SecondaryTopic>
  </PrimaryTopic>
</template>

<style scoped></style>
