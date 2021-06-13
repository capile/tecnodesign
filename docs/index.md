<!--
---
title: Tecnodesign Studio Documentation
...
-->
Tecnodesign Studio 
==================

![Build status](https://api.travis-ci.com/capile/tecnodesign.svg?branch=master)

Studio is both a product and a development framework built by Tecnodesign to produce web applications. It's simple, secure and self-contained.

## Installation

To ensure all dependencies are properly loaded, the installation should be done using composer.

You can quickly install and startup a new project using the built-in web server (`studio-server`) which can be accessed at <http://localhost:9999/>

## Configuration

There's an installation script that will configure the core modules and dependencies:

```
composer require capile/tecnodesign
vendor/bin/studio :config [project]
```

You can also use the installer for some specific components installation, for example:

- **Database**   
  To configure only module database connection into your [apps-dir]:  
  `vendor/bin/studio :config database [project]`

- **Studio**   
  To install Studio CMS modules:   
  `vendor/bin/studio :config studio [project]`

Dependencies need to be required on your main `composer.json` file.

### Post-Configuration

Most of the applications and components can be configured using YAML or ini configuration files within the configuration folder.

For example, to configure Studio's caching settings, templates or languages, we use public static variables in `Tecnodesign_Studio` class, that can be overwritten using a `autoload.Tecnodesign_Studio.yml` or a `autoload.Tecnodesign_Studio.ini` configuration file. Like this:

```
---
cacheTimeout: 3600
staticCache: 180
languages: [ en, pt ]
```

The database connections can also be defined by updating the `tdz::$database` variable the same way. Or, alternatively, write down a `databases.yml` configuration file with the specific environment as the master key. Like this:

```
---
all:
  myschema:
    dsn: mysql:host=db;dbname=myschema;charset=utf8mb4
    username: myschemauser
    password: mypassword
    sync: true
```

Alternatively, it could also be written within the `autoload.tdz.yml` file:

```
---
assetsUrl: /_
dateFormat: 'F j, Y'
timeFormat: H:i:s
logDir: syslog
database:
  myschema:
    dsn: mysql:host=db;dbname=myschema;charset=utf8mb4
    username: myschemauser
    password: mypassword
    sync: true
```

