<script setup>
import SqlTable from '@/components/tables/SqlTable.vue'

import Alert from '@/components/Alert.vue'
import CodeBlock from '@/components/CodeBlock.vue'
import Paragraph from '@/components/Paragraph.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'

const newQb = `
conn <- connect('mariadb-lw') # always create a connection object
query <- QB$new(conn) # feed the connection to QB instance
`.trim()

const sample = {
  select: [
    {
      faci_id: '020115',
      faci_name: 'Cabatuan Super Health Center',
    },
    {
      faci_id: '050017',
      faci_name: 'Naga Imaging Center Cooperative Doctors Hospital',
    },
    {
      faci_id: '050019',
      faci_name: 'Legazpi City Social Hygiene Clinic -TB DOTS',
    },
    {
      faci_id: '060188',
      faci_name: 'Pontevedra Rural Health Unit',
    },
    {
      faci_id: '060233',
      faci_name: 'New Washington Rural Health Unit',
    },
  ],
  alias: [
    {
      id: '020115',
      name: 'Cabatuan Super Health Center',
    },
    {
      id: '050017',
      name: 'Naga Imaging Center Cooperative Doctors Hospital',
    },
    {
      id: '050019',
      name: 'Legazpi City Social Hygiene Clinic -TB DOTS',
    },
    {
      id: '060188',
      name: 'Pontevedra Rural Health Unit',
    },
    {
      id: '060233',
      name: 'New Washington Rural Health Unit',
    },
  ],
}

const selectRaw = {
  r: `facilities <- QB$new(conn)$
   select('faci_id as id', 'faci_name as name')$
   selectRaw("CONCAT_WS(', ', addr_nhsss_prov, addr_nhsss_munc) AS address")$
   from('ohasis_lake.ref_faci')$
   get()`,
  sql:
    'SELECT `faci_id`                                         AS `id`,\n' +
    '       `faci_name`                                       AS `name`,\n' +
    "       CONCAT_WS(', ', addr_nhsss_prov, addr_nhsss_munc) AS address\n" +
    'FROM `ohasis_lake`.`ref_faci`;\n',
  sample: [
    {
      id: '020115',
      name: 'Cabatuan Super Health Center',
      address: 'ISABELA, CABATUAN',
    },
    {
      id: '050017',
      name: 'Naga Imaging Center Cooperative Doctors Hospital',
      address: 'CAMARINES SUR, NAGA',
    },
    {
      id: '050019',
      name: 'Legazpi City Social Hygiene Clinic -TB DOTS',
      address: 'ALBAY, LEGAZPI',
    },
    {
      id: '060188',
      name: 'Pontevedra Rural Health Unit',
      address: 'CAPIZ, PONTEVEDRA',
    },
    {
      id: '060233',
      name: 'New Washington Rural Health Unit',
      address: 'AKLAN, NEW WASHINGTON',
    },
  ],
}

const whereRaw = {
  r: `facilities <- QB$new(conn)$
   select('faci_id as id', 'faci_name as name', 'addr_name_reg')$
   from('ohasis_lake.ref_faci')$
   whereRaw("LEFT(\`addr_psgc_reg\`, 2) = '13'")$
   get()`,
  sql:
    'SELECT `faci_id`   AS `id`,\n' +
    '       `faci_name` AS `name`,\n' +
    '       `addr_name_reg`\n' +
    'FROM `ohasis_lake`.`ref_faci`\n' +
    "WHERE (LEFT(`addr_psgc_reg`, 2) = '13')",
  sample: [
    {
      id: '131084',
      name: 'West Rembo Health Center',
      addr_name_reg: 'National Capital Region (NCR)',
    },
    {
      id: '131103',
      name: 'Quezon City Health Department (QCHD)',
      addr_name_reg: 'National Capital Region (NCR)',
    },
    {
      id: '131104',
      name: 'My Hub Cares Inc. - Fairview',
      addr_name_reg: 'National Capital Region (NCR)',
    },
    {
      id: '040200',
      name: 'Save And Improve Lives (SAIL) Healthcare - Makati',
      addr_name_reg: 'National Capital Region (NCR)',
    },
    {
      id: '040200',
      name: 'Save And Improve Lives (SAIL) Healthcare - Makati',
      addr_name_reg: 'National Capital Region (NCR)',
    },
  ],
}
const innerJoin = {
  r: `confirm <- QB$new(conn)$
   select('test.rec_id', 'test.confirm_code', 'pii.sex', 'test.date_confirm', 'test.confirm_result')$
   from("ohasis_lake.px_hiv_confirmatory AS test")$
   join("ohasis_lake.px_demographics AS pii", "test.rec_id", "=", "pii.rec_id")$
   get()`,
  sql:
    'SELECT `test`.`rec_id`,\n' +
    '       `test`.`confirm_code`,\n' +
    '       `pii`.`sex`,\n' +
    '       `test`.`date_confirm`,\n' +
    '       `test`.`confirm_result`\n' +
    'FROM `ohasis_lake`.`px_hiv_confirmatory` AS `test`\n' +
    '         JOIN `ohasis_lake`.`px_demographics` AS `pii` USING (`rec_id`);',
  sample: [
    {
      rec_id: '20210210134040_0300060001',
      confirm_code: 'BUL21-01-00001',
      sex: '1_Male',
      date_confirm: '2021-02-10 13:00:00',
      confirm_result: '1_Positive',
    },
    {
      rec_id: '20210210141129_0300060004',
      confirm_code: 'BUL21-01-00002',
      sex: '1_Male',
      date_confirm: '2021-02-10 13:00:00',
      confirm_result: '1_Positive',
    },
    {
      rec_id: '20210210142453_0300060004',
      confirm_code: 'BUL21-01-00003',
      sex: '1_Male',
      date_confirm: '2021-02-10 13:00:00',
      confirm_result: '1_Positive',
    },
    {
      rec_id: '20210210143636_0300060004',
      confirm_code: 'BUL21-01-00004',
      sex: '1_Male',
      date_confirm: '2021-02-10 13:00:00',
      confirm_result: '1_Positive',
    },
    {
      rec_id: '20210210145352_1300040001',
      confirm_code: 'KBS21-02-00025',
      sex: '1_Male',
      date_confirm: '2021-02-10 10:00:00',
      confirm_result: '1_Positive',
    },
  ],
}
const outerJoin = `
confirm <- QB$new(conn)$
   select('test.rec_id', 'test.confirm_code', 'pii.sex', 'test.date_confirm', 'test.confirm_result')$
   from("ohasis_lake.px_hiv_confirmatory AS test")$
   rightJoin("ohasis_lake.px_demographics AS pii", "test.rec_id", "=", "pii.rec_id")$
   get()

confirm <- QB$new(conn)$
   select('test.rec_id', 'test.confirm_code', 'pii.sex', 'test.date_confirm', 'test.confirm_result')$
   from("ohasis_lake.px_hiv_confirmatory AS test")$
   leftJoin("ohasis_lake.px_demographics AS pii", "test.rec_id", "=", "pii.rec_id")$
   get()
 `.trim()

const where = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status')$
   from("ohasis_lake.px_demographics")$
   where("age", ">", "15")$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`age` > '15')",
  sample: [
    {
      patient_id: '20250526010001L069',
      sex: '1_Male',
      age: '34',
      educ_level: '4_College',
      civil_status: '1_Single',
    },
    {
      patient_id: '20250820010001M157',
      sex: '1_Male',
      age: '46',
      educ_level: '4_College',
      civil_status: '1_Single',
    },
    {
      patient_id: '20250730010003784E',
      sex: '1_Male',
      age: '25',
      educ_level: '3_High School',
      civil_status: '1_Single',
    },
    {
      patient_id: '202511040100033P16',
      sex: '1_Male',
      age: '36',
      educ_level: '4_College',
      civil_status: '1_Single',
    },
    {
      patient_id: '2025112801000483B8',
      sex: '1_Male',
      age: '26',
      educ_level: '4_College',
      civil_status: '1_Single',
    },
  ],
}
const whereEquals = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status')$
   from("ohasis_lake.px_demographics")$
   where('sex', "2_Female")$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`sex` = '2_Female')",
  sample: [
    {
      patient_id: '202506041300244G37',
      sex: '2_Female',
      age: '37',
      educ_level: '3_High School',
      civil_status: '1_Single',
    },
    {
      patient_id: '2022070611000212U3',
      sex: '2_Female',
      age: '30',
      educ_level: '3_High School',
      civil_status: '1_Single',
    },
    {
      patient_id: '202507231100022T02',
      sex: '2_Female',
      age: '27',
      educ_level: '3_High School',
      civil_status: '1_Single',
    },
    {
      patient_id: '20250722110002015S',
      sex: '2_Female',
      age: '24',
      educ_level: '4_College',
      civil_status: '1_Single',
    },
    {
      patient_id: '2022072111000233E5',
      sex: '2_Female',
      age: '36',
      educ_level: '4_College',
      civil_status: '1_Single',
    },
  ],
}
const whereOr = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status')$
   from("ohasis_lake.px_demographics")$
   where('sex', "2_Female", "or")$
   where('sex', "1_Male", "or")$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`sex` = '2_Female') OR (`sex` = '1_Male')",
}
const whereIn = {
  r: `sexes <- c('1_Male', '2_Female')

data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status')$
   from("ohasis_lake.px_demographics")$
   whereIn('sex', sexes)$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`sex` IN ('1_Male', '2_Female'))",
}
const whereNotIn = {
  r: `sexes <- c('1_Male', '2_Female')

data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status')$
   from("ohasis_lake.px_demographics")$
   whereNotIn('sex', sexes)$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`sex` NOT IN ('1_Male', '2_Female'))",
}
const whereBetween = {
  r: `range <- c('2025-01-01', '2025-12-31')

data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status', 'record_date')$
   from("ohasis_lake.px_demographics")$
   whereBetween('record_date', range)$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`,\n' +
    '       `record_date`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`record_date` BETWEEN '2025-01-01' and '2025-12-31')",
}
const whereNotBetween = {
  r: `range <- c('2025-01-01', '2025-12-31')

data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status', 'record_date')$
   from("ohasis_lake.px_demographics")$
   whereNotBetween('record_date', range)$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`,\n' +
    '       `record_date`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    "WHERE (`record_date` NOT BETWEEN '2025-01-01' and '2025-12-31')",
}
const whereNull = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status', 'deleted_at')$
   from("ohasis_lake.px_demographics")$
   whereNull('deleted_at')$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`,\n' +
    '       `deleted_at`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    'WHERE (`deleted_at` IS NULL)',
}
const whereNotNull = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status', 'deleted_at')$
   from("ohasis_lake.px_demographics")$
   whereNotNull('deleted_at')$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`,\n' +
    '       `deleted_at`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    'WHERE (`deleted_at` IS NOT NULL)',
}
const limit = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status', 'deleted_at')$
   from("ohasis_lake.px_demographics")$
   limit(5)$
   get()`,
  sql:
    'SELECT `patient_id`,\n' +
    '       `sex`,\n' +
    '       `age`,\n' +
    '       `educ_level`,\n' +
    '       `civil_status`,\n' +
    '       `deleted_at`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n' +
    'LIMIT 5',
}
const distinct = {
  r: `data <- QB$new(conn)$
   select('patient_id', 'sex', 'age', 'educ_level', 'civil_status', 'deleted_at')$
   from("ohasis_lake.px_demographics")$
   distinct()$
   get()`,
  sql:
    'SELECT DISTINCT `patient_id`,\n' +
    '                `sex`,\n' +
    '                `age`,\n' +
    '                `educ_level`,\n' +
    '                `civil_status`,\n' +
    '                `deleted_at`\n' +
    'FROM `ohasis_lake`.`px_demographics`\n',
}
</script>

<template>
  <TertiaryTopic title="QB">
    <Paragraph>
      The runbook system includes a query builder that provides a concise, programmatic interface for composing and
      executing SQL across multiple DBMS. It simplifies common <codetext>SELECT</codetext> workflows so data engineers
      and analysts can fetch data consistently without rewriting boilerplate connection and execution code. The builder
      returns results in R‑friendly formats and integrates with the project’s connection and logging conventions.
    </Paragraph>
    <Alert title="Warning" type="warn">
      The current query builder is DBMS‑agnostic and convenient for composing SQL across different back ends, but it
      does not use parameter binding and therefore can be vulnerable to SQL injection. Treat this component as
      sensitive: restrict its use, review generated SQL before execution, and harden it before wider adoption.
    </Alert>
    <Alert title="TODO" type="success">
      Implement parameter binding by detecting the connection’s capabilities and then using the binding mechanism that
      the DBMS/driver supports. The goal is to make query execution safe, portable, and predictable: prefer DBI
      parameterization where available, fall back to driver‑specific binding templates when necessary, and only use
      string interpolation after strict validation and whitelisting.
    </Alert>
    <QuaternaryTopic title="Instantiation">
      <Paragraph>
        Create a <codetext>QB</codetext> object by calling its <codetext>new</codetext> constructor and passing a valid
        <codetext>DBI</codetext> connection. The <codetext>QB</codetext> instance will use that connection for
        compiling, binding, and executing queries.
      </Paragraph>
      <CodeBlock :code="newQb" lang="r" />
    </QuaternaryTopic>
    <QuaternaryTopic title="Retrieving All Rows From a Table">
      <Paragraph>
        Use the from method on a <codetext>QB</codetext> instance to begin a query scoped to a specific table.
        <codetext>from</codetext> returns a query‑builder object that supports fluent chaining (filters, projections,
        ordering, limits). Call <codetext>get()</codetext> at the end to execute the compiled query and return results
        as a <b>tibble</b>.
      </Paragraph>
      <CodeBlock code="df <- QB$new(conn)$from('ohasis_lake.ref_faci')$get()" lang="r" />
      <CodeBlock code="SELECT * FROM `ohasis_lake`.`ref_faci`;" lang="sql" />
      <Alert title="Note" type="info">
        The value passed to <codetext>from</codetext> is interpreted in the context of the provided
        <b>DB</b> connection. If that connection does not include a default database/schema, the call will fail because
        the query lacks the necessary context to resolve the table name. It is recommended to always provide a fully
        qualified identifier when the connection has no default schema: use a dot‑separated schema.table string (for
        example, <codetext>ohasis_lake.ref_faci</codetext>) to avoid ambiguity.
      </Alert>
    </QuaternaryTopic>
    <QuaternaryTopic title="Select Statements">
      <Paragraph>
        The <codetext>select</codetext> method lets you choose which columns to return instead of returning all columns
        from a table. This reduces data transfer, improves query performance, and makes downstream code clearer about
        the fields it expects.
      </Paragraph>
      <CodeBlock
        code="facilities <- QB$new(conn)$select('faci_id', 'faci_name')$from('ohasis_lake.ref_faci')$get()"
        lang="r" />
      <CodeBlock code="SELECT `faci_id`, `faci_name` FROM `ohasis_lake`.`ref_faci`;" lang="sql" />
      <SqlTable :data="sample.select" />
      <Paragraph>
        Prefer fully qualified identifiers in <codetext>select</codetext> clauses to make queries explicit and portable
        across environments. Fully qualifying columns with <codetext>schema.table.column</codetext> or
        <codetext>table.column</codetext> prevents ambiguity, avoids accidental cross‑schema reads, and makes intent
        clear when queries run against different connections.
      </Paragraph>
      <CodeBlock
        code="facilities <- QB$new(conn)$select('faci_id as id', 'faci_name as name')$from('ohasis_lake.ref_faci')$get()"
        lang="r" />
      <CodeBlock code="SELECT `faci_id` AS `id`, `faci_name` AS `name` FROM `ohasis_lake`.`ref_faci`;" lang="sql" />
      <SqlTable :data="sample.alias" />
      <Paragraph>
        The <codetext>distinct</codetext> method allows you to force the query to return distinct results:
      </Paragraph>
      <CodeBlock
        code="facilities <- QB$new(conn)$select('faci_id as id, faci_name as name')$from('ohasis_lake.ref_faci')$distinct()$get()"
        lang="r" />
      <CodeBlock
        code="SELECT DISTINCT `faci_id` AS `id`, `faci_name` AS `name` FROM `ohasis_lake`.`ref_faci`;"
        lang="sql" />
    </QuaternaryTopic>
    <QuaternaryTopic title="Raw Methods">
      <Paragraph>
        Sometimes you need to inject an arbitrary SQL fragment into a query, such as with complex expressions or custom
        literals. Use the <codetext>selectRaw</codetext> and <codetext>whereRaw</codetext> methods to insert those
        fragments directly into the compiled SQL.
      </Paragraph>
      <Paragraph>
        The <codetext>selectRaw</codetext> method lets you inject one or more raw SQL expressions directly into the
        <codetext>SELECT</codetext> clause.
      </Paragraph>
      <CodeBlock :code="selectRaw.r" lang="r" />
      <CodeBlock :code="selectRaw.sql" lang="sql" />
      <SqlTable :data="selectRaw.sample" />
      <Paragraph>
        The <codetext>whereRaw</codetext> method lets you inject one or more raw SQL expressions directly into the
        <codetext>WHERE</codetext> clause.
      </Paragraph>
      <CodeBlock :code="whereRaw.r" lang="r" />
      <CodeBlock :code="whereRaw.sql" lang="sql" />
      <SqlTable :data="whereRaw.sample" />
    </QuaternaryTopic>

    <QuaternaryTopic title="Joins">
      <Paragraph>
        The query builder supports adding SQL <codetext>JOIN</codetext> clauses so you can combine rows from multiple
        tables. Use the <codetext>join</codetext> method on a query‑builder instance to add an inner join; the first
        argument is the table to join and the remaining arguments define the join condition(s). You can chain multiple
        <codetext>join</codetext> calls to compose complex multi‑table queries.
      </Paragraph>
      <CodeBlock :code="innerJoin.r" lang="r" />
      <CodeBlock :code="innerJoin.sql" lang="sql" />
      <SqlTable :data="innerJoin.sample" />
      <Paragraph>
        Use <codetext>leftJoin</codetext> or <codetext>rightJoin</codetext> when you need outer join semantics instead
        of the default inner join. Both methods accept the same arguments as join: the table to join followed by the
        join condition(s). They return the same query‑builder object so you can chain further constraints before calling
        <codetext>get()</codetext> to execute.
      </Paragraph>
      <CodeBlock :code="outerJoin" lang="r" />
    </QuaternaryTopic>

    <QuaternaryTopic title="Where Clauses">
      <Paragraph>
        Use the query builder’s <codetext>where</codetext> method to add <codetext>WHERE</codetext> predicates to a
        query. The simplest form takes three arguments: column, operator, and value.
      </Paragraph>
      <CodeBlock :code="where.r" lang="r" />
      <CodeBlock :code="where.sql" lang="sql" />
      <SqlTable :data="where.sample" />
      <Paragraph>
        For convenience, if you pass only <codetext>column</codetext> and <codetext>value</codetext> to
        <codetext>where</codetext>, the builder assumes the <codetext>=</codetext> operator.
      </Paragraph>
      <CodeBlock :code="whereEquals.r" lang="r" />
      <CodeBlock :code="whereEquals.sql" lang="sql" />
      <SqlTable :data="whereEquals.sample" />
      <Paragraph>
        By default, successive calls to the query builder’s <codetext>where</codetext> method are combined with
        <codetext>AND</codetext>, requiring all expressions to be true. When you need an
        <codetext>OR</codetext> relationship, the <codetext>where</codetext> method exposes a
        <codetext>boolean</codetext> parameter you can set to change how the new predicate is joined to the existing
        ones.
      </Paragraph>
      <CodeBlock :code="whereOr.r" lang="r" />
      <CodeBlock :code="whereOr.sql" lang="sql" />
      <Paragraph>
        The <codetext>whereIn</codetext> method verifies that a given column's value is contained within the given
        vector:
      </Paragraph>
      <CodeBlock :code="whereIn.r" lang="r" />
      <CodeBlock :code="whereIn.sql" lang="sql" />
      <Paragraph>
        The <codetext>whereNotIn</codetext> method verifies that a given column's value is contained within the given
        vector:
      </Paragraph>
      <CodeBlock :code="whereNotIn.r" lang="r" />
      <CodeBlock :code="whereNotIn.sql" lang="sql" />
      <Paragraph>
        The <codetext>whereBetween</codetext> method verifies that a column's value is between two values:
      </Paragraph>
      <CodeBlock :code="whereBetween.r" lang="r" />
      <CodeBlock :code="whereBetween.sql" lang="sql" />
      <Paragraph>
        The <codetext>whereNotBetween</codetext> method verifies that a column's value lies outside of two values:
      </Paragraph>
      <CodeBlock :code="whereNotBetween.r" lang="r" />
      <CodeBlock :code="whereNotBetween.sql" lang="sql" />
      <Paragraph>
        The <codetext>whereNull</codetext> method verifies that the value of the given column is
        <codetext>NULL</codetext>:
      </Paragraph>
      <CodeBlock :code="whereNull.r" lang="r" />
      <CodeBlock :code="whereNull.sql" lang="sql" />
      <Paragraph>
        The <codetext>whereNotNull</codetext> method verifies that the column's value is not <codetext>NULL</codetext>:
      </Paragraph>
      <CodeBlock :code="whereNotNull.r" lang="r" />
      <CodeBlock :code="whereNotNull.sql" lang="sql" />
    </QuaternaryTopic>

    <QuaternaryTopic title="Other Clauses">
      <Paragraph>
        You may use the <codetext>limit</codetext> method to limit the number of rows returned by the query:
      </Paragraph>
      <CodeBlock :code="limit.r" lang="r" />
      <CodeBlock :code="limit.sql" lang="sql" />
      <Paragraph>
        The <codetext>distinct</codetext> method allows you to force the query to return distinct results:
      </Paragraph>
      <CodeBlock :code="distinct.r" lang="r" />
      <CodeBlock :code="distinct.sql" lang="sql" />
    </QuaternaryTopic>
  </TertiaryTopic>
</template>

<style scoped></style>
