<script setup>
import Alert from '@/components/Alert.vue'
import CodeBlock from '@/components/CodeBlock.vue'
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import QuaternaryTopic from '@/components/QuaternaryTopic.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'

const environ = {
  mariadb: `DB_NAME=ohasis
DB_USER=user
DB_PASS=password
DB_HOST=192.168.193.22
DB_PORT=3306`,
  clickhouse: `LW_USER=user
LW_PASS=pass
LW_HOST=192.168.193.236
LW_PORT=9000`,
}
const config = {
  mariadb: `[ohasis-live]
database=ohasis
user=user
password=password
host=192.168.193.22
port=3306
`,
  clickhouse: `db: harp_dx
user: user
password: password
host: 192.168.193.236
port: 9000`,
}
</script>

<template>
  <SecondaryTopic start="2" title="User Access Control">
    <Paragraph>
      This section defines the access control objectives and high‑level requirements for MariaDB, ClickHouse, and the
      OHASIS EHR. It explains who may access each system, why access is needed, and the security and governance controls
      that ensure data confidentiality, integrity, and availability across the platform.
    </Paragraph>
    <TertiaryTopic title="MariaDB (OLTP)">
      <Paragraph>
        This interface provides direct access to the OHASIS EHR backend database and is intended primarily for querying,
        debugging, and reference. Data managers may use it to inspect records, validate transformations, and
        troubleshoot issues, but must not perform ad‑hoc updates via direct SQL unless explicitly authorized. Direct
        writes can bypass application‑level validations and business logic enforced by the EHR, risking data
        inconsistency and operational errors.
      </Paragraph>
      <Paragraph>
        The Hydration Pipeline connects to the EHR database using a dedicated service account, while each data manager
        is issued an individual user account for operational tasks. Analysts typically do not require direct database
        access; they should consume data through the OLAP.
      </Paragraph>
      <Paragraph>
        The relevant database to be queried is the
        <codetext>ohasis</codetext>
        schema under the
        <codetext>OHASIS-SU-002</codetext>
        connection.
      </Paragraph>
      <QuaternaryTopic title="Creating a User">
        <Paragraph>
          This section explains why and how to create a MariaDB user for the EHR. Take extreme care in provisioning an
          account, as these will have access to database resources of the platform.
        </Paragraph>
        <CodeBlock code="CREATE USER 'username'@'hostname' IDENTIFIED BY 'password';" lang="sql" />
        <Paragraph>
          Replace the
          <codetext>username</codetext>
          with the desired value for the new user. When creating a new MariaDB user, choose a clear, professional, and
          easily identifiable username (i.e.,
          <codetext>jrpalo.doh</codetext>
          ,
          <codetext>ohasis</codetext>
          ). Admins may use the email local‑part as a reference when appropriate (
          <codetext>[name]@doh.gov.ph</codetext>
          ).
        </Paragraph>
        <Paragraph>
          Set the
          <codetext>hostname</codetext>
          to the IP address from which the user or services will be connecting from. MariaDB validates the connecting
          client against this host value on every connection, so it must accurately reflect the client’s network
          address. For simplicity and reliability, use the client’s ZeroTier Node IP (i.e.,
          <codetext>192.168.193.1</codetext>
          ).
        </Paragraph>
        <Alert title="Warning" type="warn">
          Avoid wildcard host specifications such as
          <codetext>'user'@'%'</codetext>
          or
          <codetext>'user'@'192.168.193.%'</codetext>
          because they broaden the attack surface and weaken host‑level access controls. Prefer a single, specific IP
          that accurately reflects the client’s network address. Use wildcards only when absolutely necessary and only
          after implementing compensating controls such as network allowlists, short‑lived credentials, and enhanced
          monitoring.
        </Alert>
        <Paragraph>
          Choose a strong
          <codetext>password</codetext>
          , preferably at least 16 characters in length. Use a mix of alpha and numeric characters when creating your
          password.
        </Paragraph>
      </QuaternaryTopic>

      <QuaternaryTopic title="Provisioning Access">
        <Paragraph>
          Once a user account exists and is active, administrators should grant access to specific database resources
          only as required by the user’s role and responsibilities. Access must follow the principle of least privilege
          and be scoped to the smallest practical surface (schemas, tables, hosts, and environments).
        </Paragraph>
        <CodeBlock
          code="GRANT ALL PRIVILEGES ON *.* TO 'user'@'%' WITH GRANT OPTION;"
          label="Creating a super admin account"
          lang="sql" />
        <CodeBlock code="GRANT ALL PRIVILEGES ON *.* TO 'user'@'%';" label="Creating an admin account" lang="sql" />
        <Paragraph> When needed, read-only privileges may be granted to a user:</Paragraph>
        <CodeBlock code="GRANT SELECT ON *.* TO 'user'@'%';" label="Read-only on all schemas" lang="sql" />
        <CodeBlock
          code="GRANT SELECT ON `ohasis`.* TO 'user'@'%';"
          label="Read-only on all tables within a specific schema"
          lang="sql" />
        <CodeBlock
          code="GRANT SELECT ON `ohasis`.`px_record` TO 'user'@'%';"
          label="Read-only on a specific table within a schema"
          lang="sql" />
        <Paragraph> For most data managers, privileges for the basic CRUD operations should be sufficient:</Paragraph>
        <CodeBlock code="GRANT SELECT, INSERT, UPDATE, DELETE ON `ohasis`.* TO 'user'@'%';" lang="sql" />
        <Paragraph> For service accounts, DDL privileges must be granted as well:</Paragraph>
        <CodeBlock
          code="GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP ON `ohasis`.* TO 'user'@'%';"
          lang="sql" />
        <Paragraph>
          After changing user accounts or permissions, immediately refresh the server’s privilege cache so changes take
          effect and to avoid inconsistent access behavior. Run the command as a privileged account:
        </Paragraph>
        <CodeBlock code="FLUSH PRIVILEGES;" lang="sql" />
        <Alert title="TODO" type="success">
          As the team grows, adopt <b>role‑based access control</b> instead of issuing ad‑hoc privileges. Define
          reusable roles that encapsulate the minimum permissions required for common job functions (i.e.,
          <codetext>data_analyst</codetext>
          ,
          <codetext>data_manager</codetext>
          , etc.), then assign those roles to users. This approach simplifies provisioning, accelerates onboarding,
          reduces privilege creep, and makes periodic access reviews and revocations far more reliable.
        </Alert>
      </QuaternaryTopic>

      <QuaternaryTopic title="Modifying Access">
        <Paragraph>
          In the event of role changes or staff turnover, updates to user responsibilities must be reflected promptly in
          the DBMS. Following the principles of least privilege and data security, access rights should be adjusted or
          revoked to ensure that users retain only the permissions appropriate to their current duties.
        </Paragraph>
        <CodeBlock
          code="ALTER USER 'user'@'%' IDENTIFIED BY 'new_password';"
          label="Changing a user password"
          lang="sql" />
        <CodeBlock code="ALTER USER 'user'@'%' ACCOUNT LOCK;" label="Locking a user account" lang="sql" />
        <CodeBlock code="DROP USER 'user'@'%';" label="Permanently removing a user account" lang="sql" />
        <CodeBlock
          code="REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'user'@'%';"
          label="Removing access to all resources"
          lang="sql" />
        <Paragraph>
          As discussed in the section on provisioning access, the server's privilege cache must be refreshed for any
          changes to take effect:
        </Paragraph>
        <CodeBlock code="FLUSH PRIVILEGES;" lang="sql" />
      </QuaternaryTopic>

      <QuaternaryTopic title="Environment Variables">
        <Paragraph>
          Provisioned authentication credentials for data managers, as well as server configuration details, will be
          injected into the runbook environment as environment variables.
        </Paragraph>
        <CodeBlock :code="environ.mariadb" label=".Renviron" lang="ini" />
        <Paragraph> A configuration file (.cnf) is also needed for other service-type connections.</Paragraph>
        <CodeBlock :code="config.mariadb" label="my.cnf" lang="ini" />
      </QuaternaryTopic>
    </TertiaryTopic>
    <TertiaryTopic title="ClickHouse (OLAP)">
      <Paragraph>
        This interface grants NHSSS Unit staff controlled access to the Lake/Warehouse databases for analysis,
        reporting, and dashboard creation. It is intended for data analysts and data managers who need to explore
        schemas and tables to produce validated datasets and insights.
      </Paragraph>

      <Paragraph>
        ClickHouse supports two configuration approaches for access control: SQL‑driven (managed with
        <codetext>CREATE USER</codetext>
        /
        <codetext>GRANT</codetext>
        statements) and server configuration files (XML/YAML). This documentation covers the SQL‑driven workflow. Before
        proceeding, verify that the SQL workflow is enabled and that the
        <codetext>default</codetext>
        user has the necessary privileges and settings to perform user and role management.
      </Paragraph>
      <CodeBlock
        code="<access_management>1</access_management>
<named_collection_control>1</named_collection_control>
<show_named_collections>1</show_named_collections>
<show_named_collections_secrets>1</show_named_collections_secrets>"
        label="users.xml"
        lang="xml" />

      <QuaternaryTopic title="Creating a User">
        <Paragraph>
          This section explains why and how to create a ClickHouse user for the Lake/Warehouse. Because the system hosts
          multiple schemas and sensitive datasets, access must be curated by analysis and use case to minimize exposure
          and preserve data integrity.
        </Paragraph>
        <CodeBlock code="CREATE USER username IDENTIFIED BY 'password' HOST { IP 'hostname' };" lang="sql" />
        <Paragraph>
          Replace the
          <codetext>username</codetext>
          with the desired value for the new user. When creating a new ClickHouse user, choose a clear, professional,
          and easily identifiable username (i.e.,
          <codetext>jrpalo.doh</codetext>
          ,
          <codetext>ohasis</codetext>
          ). Admins may use the email local‑part as a reference when appropriate (
          <codetext>[name]@doh.gov.ph</codetext>
          ).
        </Paragraph>
        <Paragraph>
          Set the
          <codetext>hostname</codetext>
          to the IP address from which the user or services will be connecting from. ClickHouse validates the connecting
          client against this host value on every connection, so it must accurately reflect the client’s network
          address. For simplicity and reliability, use the client’s ZeroTier Node IP (i.e.,
          <codetext>192.168.193.1</codetext>
          ).
        </Paragraph>
        <Alert title="Warning" type="warn">
          Avoid the
          <codetext>ANY</codetext>
          host specification because they broaden the attack surface and weaken host‑level access controls. Prefer a
          single, specific IP that accurately reflects the client’s network address.
        </Alert>
        <Paragraph>
          Choose a strong
          <codetext>password</codetext>
          , preferably at least 16 characters in length. Use a mix of alpha and numeric characters when creating your
          password.
        </Paragraph>
      </QuaternaryTopic>

      <QuaternaryTopic title="Provisioning Access">
        <Paragraph>
          Once a user account exists and is active, administrators should grant access to specific database resources
          only as required by the user’s role and responsibilities. Access must follow the principle of least privilege
          and be scoped to the smallest practical surface (schemas, tables, hosts, and environments).
        </Paragraph>
        <CodeBlock
          code="GRANT ALL ON *.* TO user WITH GRANT OPTION;"
          label="Creating a super admin account"
          lang="sql" />
        <CodeBlock code="GRANT ALL ON *.* TO user;" label="Creating an admin account" lang="sql" />
        <Paragraph> When needed, read-only privileges may be granted to a user:</Paragraph>
        <CodeBlock code="GRANT SELECT ON *.* TO user;" label="Read-only on all schemas" lang="sql" />
        <CodeBlock
          code="GRANT SELECT ON `ohasis_lake`.* TO user;"
          label="Read-only on all tables within a specific schema"
          lang="sql" />
        <CodeBlock
          code="GRANT SELECT ON `ohasis_warehouse`.`form_hts` TO user;"
          label="Read-only on a specific table within a schema"
          lang="sql" />
        <Paragraph>
          For the runbook pipelines, these privileges on each relevant schema should be sufficient:
        </Paragraph>
        <CodeBlock
          code="GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP ON `ohasis_lake`.* TO user;"
          lang="sql" />
        <Alert title="TODO" type="success">
          As the team grows, adopt <b>role‑based access control</b> instead of issuing ad‑hoc privileges. Define
          reusable roles that encapsulate the minimum permissions required for common job functions (i.e.,
          <codetext>data_analyst</codetext>
          ,
          <codetext>data_manager</codetext>
          , etc.), then assign those roles to users. This approach simplifies provisioning, accelerates onboarding,
          reduces privilege creep, and makes periodic access reviews and revocations far more reliable.
        </Alert>
      </QuaternaryTopic>

      <QuaternaryTopic title="Modifying Access">
        <Paragraph>
          In the event of role changes or staff turnover, updates to user responsibilities must be reflected promptly in
          the DBMS. Following the principles of least privilege and data security, access rights should be adjusted or
          revoked to ensure that users retain only the permissions appropriate to their current duties.
        </Paragraph>
        <CodeBlock code="ALTER USER user IDENTIFIED BY 'new_password';" label="Changing a user password" lang="sql" />
        <CodeBlock code="ALTER USER user DISABLE;" label="Locking a user account" lang="sql" />
        <CodeBlock code="DROP USER user;" label="Permanently removing a user account" lang="sql" />
        <CodeBlock
          code="REVOKE ALL PRIVILEGES, GRANT OPTION FROM user;"
          label="Removing access to all resources"
          lang="sql" />
      </QuaternaryTopic>

      <QuaternaryTopic title="Environment Variables">
        <Paragraph>
          Provisioned authentication credentials for data managers, as well as server configuration details, will be
          injected into the runbook environment as environment variables.
        </Paragraph>
        <CodeBlock :code="environ.clickhouse" label=".Renviron" lang="ini" />
        <Paragraph> ClickHouse configuration files (.yaml) are also needed for per schema connections.</Paragraph>
        <CodeBlock :code="config.clickhouse" label="harp_dx.yaml" lang="yaml" />
      </QuaternaryTopic>
    </TertiaryTopic>
    <TertiaryTopic title="OHASIS EHR">
      <Paragraph>
        Runbooks may perform callbacks that write to the live EHR database during import pipelines and other operational
        procedures. These operations replicate EHR application logic during modification of production data, and as such
        requires EHR account information in order to retain traceability of transactions and maintain consistency in its
        logic tree. At a minimum, all data manager accounts should use the
        <b>OHASIS Super Admin</b> role.
      </Paragraph>
      <Image
        description="Account assigned with the OHASIS Super Admin role"
        height="h-48"
        src="/img/screenshots/ohasis-super-admin.png" />
      <Paragraph>
        The relevant information required is the OHASIS User ID, which can be found in the URI, or copied from the staff
        list under the DOH-EB Health Unit.
      </Paragraph>
      <CodeBlock code="https://ebreports-shc.doh.gov.ph/staff/{user_id}" lang="ini" />
      <Image
        description="Button to copy the row's User ID into the clipboard"
        height="h-[23rem]"
        src="/img/screenshots/copy-user-id.png" />
      <Paragraph>
        The User ID should then be stored as the
        <codetext>OH_USER_ID</codetext>
        environment variable, to be called back during import pipelines.
      </Paragraph>
      <CodeBlock code="OH_USER_ID=1300000048" label=".Renviron" lang="r" />
    </TertiaryTopic>
  </SecondaryTopic>
</template>
