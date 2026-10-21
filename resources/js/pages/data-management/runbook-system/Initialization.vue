<script setup>
import Alert from '@/components/Alert.vue'
import CodeBlock from '@/components/CodeBlock.vue'
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import ConceptList from '@/components/lists/ConceptList.vue'
import FileTree from '@/components/lists/FileTree.vue'

defineProps({
  start: [Number, String],
})

const defaultEnvironment = [
  {
    type: 'environment',
    name: '.GlobalEnv',
    children: [
      { type: 'function', name: '{global functions}' },
      { type: 'environment', name: 'DB' },
      { type: 'environment', name: 'Dedup' },
      { type: 'environment', name: 'EpiCenter' },
      { type: 'object', name: 'harp_dead' },
      { type: 'object', name: 'harp_dx' },
      { type: 'object', name: 'harp_tx' },
      { type: 'object', name: 'ihbss' },
      { type: 'object', name: 'oh-live' },
      { type: 'object', name: 'oh-lw' },
      { type: 'object', name: 'pepfar' },
      { type: 'object', name: 'prep' },
      { type: 'environment', name: 'QB' },
      { type: 'object', name: 'recency' },
    ],
  },
]

const dependencies = [
  {
    title: 'Set Encoding to UTF-8',
    description:
      'This module defines the top‑level utility functions used by subsequent class definitions and pipeline workflows. These functions provide shared configuration, option handling, and common helpers that both the framework and data managers can call when authoring or running code.',
    code: `Sys.setlocale(category = "LC_ALL", locale = "en_US.utf8")
Sys.setlocale(category = "LC_CTYPE", locale = "en_US.utf8")`,
  },
  {
    title: 'Stata Callback',
    description:
      'To work with legacy Stata files and run Stata‑optimized scripts (i.e., <codetext>compress</codetext>, old surveillance scripts, data runs, etc.), the <codetext>RStata</codetext> package can be used. In order to do so, the runtime must be informed of the Stata settings using the <codetext>STATA_PATH</codetext> and <codetext>STATA_VER</codetext> environment variables.',
    code: `options(RStata.StataPath = Sys.getenv("STATA_PATH"))
options(RStata.StataVersion = Sys.getenv("STATA_VER"))`,
  },
  {
    title: 'Numeric Data Formatting',
    description:
      'R prints large numbers in scientific notation by default, which can make reports hard to read and lead to misinterpretation of results. Configure numeric formatting for presentation while keeping raw values numeric for calculations to preserve precision and avoid accidental data corruption.',
    code: `options(scipen = 999)`,
  },
  {
    title: 'Package Repositories',
    description:
      'The runbook configures additional package repositories at startup so required packages can be installed non‑interactively in addition to the default CRAN mirror. Setting repositories early prevents R from prompting the user to choose a download host during package installation and ensures reproducible, automated installs in interactive sessions.',
    code: `options(
    repos = c(
      ropensci = "https://ropensci.r-universe.dev",
      kwbr     = "https://kwb-r.r-universe.dev",
      CRAN     = "https://cloud.r-project.org"
    )
)`,
  },
]

const globalFns = [
  {
    id: 'API clients',
    value: 'wrappers for external services and standardized request/response handling.',
    children: [],
  },
  { id: 'Data‑wrangling helpers', value: 'tidy, testable transformations and validation routines.', children: [] },
  {
    id: 'Reporting helpers',
    value: 'formatting, templating, and export utilities for human‑facing outputs.',
    children: [],
  },
  {
    id: 'EHR integration',
    value:
      'connectors and adapters for electronic health record systems, including import/export and reconciliation logic.',
    children: [],
  },
]

const myCnf = `[ohasis-live]
database=ohasis
host=192.168.193.22
port=3306
user=
password=

[old-lw]
database=dashboard
host=192.168.193.228
port=3307
user=
password=`
</script>

<template>
  <SecondaryTopic :start title="Initialization">
    <Paragraph>
      The autoloader registers components into a consistent runtime context so modules can be discovered and invoked
      without manual imports. This structure enables modular development, simplifies dependency management, and makes it
      easy to add, remove, or replace components without changing loader logic.
    </Paragraph>

    <Paragraph>
      The R startup sequence runs through a defined set of stages and timings which is bootstraped by the framework
      using the structured previously defined. The decision tree can be outlined as:
    </Paragraph>

    <Image
      description="R's startup sequence in the context of the runbook system"
      height="h-28"
      src="/img/diagrams/r-initialization.png" />
    <Alert title="Warning" type="warn">
      Note the autoloader sequence carefully: the staging order matters because later steps depend on objects and side
      effects attached by earlier steps via R’s startup and attachment semantics.
    </Alert>
    <Alert title="TODO" type="success">
      Restructure the autoloader so it can be invoked directly from
      <codetext>.Rprofile</codetext>
      at R startup. This ensures the runtime registry, environment initialization, and authentication are available
      immediately when the session begins.
    </Alert>
    <TertiaryTopic title="Autoloader">
      <Paragraph>
        The autoload process runs as a linear sequence of four stages: dependency loading, account authentication, class
        registration, and pipeline registration. Each stage depends on the artifacts produced by the previous one, so
        they are executed synchronously to ensure a consistent runtime context.
      </Paragraph>
      <CodeBlock code="source('autoload.R')" label="Using the autoloader for your own scripts" lang="r" />
      <Paragraph>
        Assuming a successful bootstrap by the autoloader, the following objects will be available in the global
        environment (<codetext>.GlobalEnv</codetext>), ready for use by the framework and interactive sessions.
      </Paragraph>
      <FileTree :list="defaultEnvironment" />
    </TertiaryTopic>

    <TertiaryTopic title="Dependency Loading">
      <Paragraph>
        This module defines the top‑level utility functions used by subsequent class definitions and pipeline workflows.
        These functions provide shared configuration, option handling, and common helpers that both the framework and
        data managers can call when authoring or running code.
      </Paragraph>

      <template v-for="{ title, description, code } in dependencies" :key="title">
        <QuaternaryTopic :title>
          <Paragraph v-html="description" />
          <CodeBlock :code lang="r" />
        </QuaternaryTopic>
      </template>

      <QuaternaryTopic title="Attaching Libraries">
        <Paragraph>
          This step implements automated package provisioning and loading for the project, combining base R utilities
          with <codetext>pacman</codetext> to simplify installation and namespace management. It also configures
          additional, non‑CRAN repositories used by the project so installs run non‑interactively and reproducibly.
        </Paragraph>
        <Alert title="TODO" type="success">
          Move from <codetext>pacman</codetext> to <codetext>renv</codetext> to improve reproducibility of the project
          environment for data managers. <codetext>renv</codetext> captures exact package versions, produces a lockfile,
          and integrates with CI so processes run identically across machines.
        </Alert>
        <Paragraph>
          The system uses the custom <codetext>nhsss</codetext> package (John Benedict Palo) for reusable helper
          functions shared across the surveillance pipeline. It also uses Lincoln Mullen’s <codetext>gender</codetext>
          package to infer likely gender from first names; these probabilistic inferences are used as validation checks
          of sex at birth.
        </Paragraph>
        <Paragraph>
          The runbooks follow the <codetext>tidyverse</codetext> design: clear verbs, pipeable workflows, and a
          consistent grammar for data transformation. This makes code easy to read, test, and reuse across the
          surveillance pipeline.
        </Paragraph>
        <Paragraph>
          We use <codetext>duckplyr</codetext> as a drop‑in replacement for most <codetext>dplyr</codetext> verbs so the
          same tidy code can run against DuckDB for much better performance on large or disk‑backed datasets.
        </Paragraph>
      </QuaternaryTopic>
      <QuaternaryTopic title="Global Function Definitions">
        <Paragraph>
          A set of scripts populates the project’s global environment (<codetext>.GlobalEnv</codetext>) with reusable
          core and helper functions. These utilities cover the common tasks needed across the pipeline so logic is
          implemented once and shared consistently. The following functions comprise those generated by this step:
        </Paragraph>
        <ConceptList :list="globalFns" em-dash />
      </QuaternaryTopic>
    </TertiaryTopic>

    <TertiaryTopic title="Account Authentication">
      <Paragraph>
        This step centralizes the logic for authentication for Google APIs used by the project. It connects to the
        <b>Google Drive</b> and <b>Google Docs</b> resources of the <codetext>nhsss@doh.gov.ph</codetext>,
        <codetext>ohasis@doh.gov.ph</codetext> and <codetext>eb@loveyourself.ph</codetext> accounts.
      </Paragraph>
      <Paragraph>
        An interactive session is opened in the web browser to proceed with authentication. Simplifies the auth steps
        for the
        <codetext>googlesheets4</codetext> and <codetext>googledrive</codetext> packages.
      </Paragraph>
      <Paragraph>
        The OAuth cache is stored in <codetext>.secrets</codetext> and reused across R sessions while tokens remain
        valid. The system uses a single active account for API calls at any one time; to make account changes explicit
        and safe, we expose a <codetext>google_account()</codetext> helper that switches the active credentials during
        runtime.
      </Paragraph>
      <CodeBlock
        code="# Use the primary account
google_account('nhsss@doh.gov.ph')

# Switch to an integration account
google_account('eb@loveyourself.ph')"
        lang="r" />
    </TertiaryTopic>

    <TertiaryTopic title="Class Registration">
      <Paragraph>
        Classes encapsulate core logic and functionality so behavior can be reused and instantiated consistently across
        the runbook. Each class models a coherent unit of work in a workflow, exposing methods that perform discrete
        steps and manage internal state where appropriate. The runbook itself makes use of <b>R6</b> classes in R,
        enabling an abstraction workflow similar to other programming languages.
      </Paragraph>
    </TertiaryTopic>

    <TertiaryTopic title="Pipeline Registration">
      <Paragraph>
        This component implements the pipeline’s original registration prototype and remains the canonical bootstrap for
        the data pipeline. It initializes a suite of reusable functions that conform to the custom
        <codetext>flow</codetext> template, so downstream runbooks can rely on a consistent API for routine tasks.
      </Paragraph>
      <Paragraph>
        Also contains a number of different function for processing core parts of the surveillance systems that have yet
        to be abstracted into their own R classes or into the <codetext>nhsss</codetext> package.
      </Paragraph>
      <Paragraph> This will be discussed in detail under the <b>Classes & Wrappers</b> section.</Paragraph>
      <Alert title="TODO" type="success">
        Move each flow pipeline into its own R6 class so workflows become explicit, testable, and composable.
        Encapsulating pipeline logic in classes replaces ad hoc, prototype‑style scripts with a predictable object
        interface: initialize, run, inspect, and clean up.
      </Alert>
    </TertiaryTopic>
    <TertiaryTopic title="Database Connections">
      <Paragraph>
        The runbooks use <codetext>DBI</codetext>‑compatible packages to connect to the EHR and LW layers, selecting
        drivers by DBMS compatibility and their handling of type conversions. Drivers are chosen for correct type
        mapping, predictable coercion rules, transaction and concurrency behavior, and performance characteristics when
        moving data between R and the database.
      </Paragraph>
      <Paragraph>
        A global <codetext>connect()</codetext> function is exposed that returns a <codetext>DBI</codetext> connection
        for database. The function selects the appropriate <codetext>DBI</codetext> driver based on the
        <codetext>group</codetext> argument, using a convention‑based mapping to files in the project root. It
        centralizes connection logic, credential lookup, and common options so callers get a ready‑to‑use
        <codetext>DBIConnection</codetext> without duplicating driver selection or config parsing
      </Paragraph>
      <QuaternaryTopic title="RMariaDB">
        <Paragraph>
          Used for interacting with MariaDB / MySQL databases, primarily the EHR database. Takes a MariaDB configuration
          file (<codetext>my.cnf</codetext>) in the root directory, with at minimum the following entries:
        </Paragraph>
        <CodeBlock :code="myCnf" lang="ini" />
        <Paragraph> Here is the sample syntax used in generating a connection:</Paragraph>
        <CodeBlock
          code="DBI::dbConnect(RMariaDB::MariaDB(), group = 'ohasis-live', default.file = 'my.cnf')"
          lang="r" />
      </QuaternaryTopic>
      <QuaternaryTopic title="ClickHouseHTTP">
        <Paragraph>
          This driver uses ClickHouse’s HTTP interface as an alternative to the <codetext>RClickHouse</codetext>
          client when that client produces unreliable type conversions or problematic statement execution. It talks to
          ClickHouse over the HTTP port and provides a simpler, more predictable path for query execution and result
          parsing.
        </Paragraph>
        <CodeBlock
          code="DBI::dbConnect(
  ClickHouseHTTP::ClickHouseHTTP(),
  host = Sys.getenv('LW_HOST'),
  port = Sys.getenv('LW_PORT'),
  password = Sys.getenv('LW_PASS'),
  encoding = 'UTF-8'
)"
          lang="r" />
      </QuaternaryTopic>
      <QuaternaryTopic title="RClickHouse">
        <Paragraph>
          This driver connects to ClickHouse over its native TCP interface and is the preferred path when using the
          <codetext>dbx</codetext>-style workflow functions. It offers lower latency and tighter integration for
          transactional workloads compared with HTTP, but it currently has known type‑support gaps (no
          <codetext>Date32</codetext> and <codetext>DateTime64</codetext> support).
        </Paragraph>
        <CodeBlock code="DBI::dbConnect(RClickhouse::clickhouse(), config_paths = 'harp_dx.yaml')" lang="r" />
      </QuaternaryTopic>
    </TertiaryTopic>
  </SecondaryTopic>
</template>
