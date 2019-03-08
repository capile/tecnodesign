<!--
---
title: Database Configuration
...
-->
# Database Configuration

Each database key should be defined at the `databases.yml` configuration file (in one of the `config-dir` folders). It requires a `dsn` entry with the connection string to use, and optionally a `username` and `password` entries. Setting the attribute `sync=true` enables this database to be reverse-engineered to have its schemas built automatically. Additional options for this connection should be set under `options`.

Alternatively this can be also set in `tdz::$databases`.

Examples:

```
---
studio:
  dsn: 'mysql:host=localhost;dbname=studio'
  username: robot-user
  password: 'r0b0tW31rdP655w0rd#'

studio-api:
  dsn: 'https://capile.studio/api/v1'
  options:
    certificate: robot.crt
    search: q
```
