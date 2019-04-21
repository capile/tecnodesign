
Tecnodesign Studio 
==================

This is a framework developed by Tecnodesign to build web applications and websites. It comes with a powerful MVC structure and several additional modules.

## Quick Install (standalone)

A simple, standalone installation is possible using composer, just download, run `composer install --no-dev` and start using. You can also set the web interface by triggering PHP built-in web server by running `server.sh` and accessing on <http://localhost:9999/>

## Full Installation

In order to install the Tecnodesign application framework:

1. Copy all files to `[apps-dir]/lib/vendor/Tecnodesign/`
2. cd to `[apps-dir]/`
3. Run: `php lib/vendor/Tecnodesign/tdz.php install [project]`

Usually [apps-dir] is located outside the document root, for example:

     /var/www/sitename/
                |- [apps-dir]/
                |- www/
                |- www-static/

Installation script will prompt for additional modules and dependencies:

```
mkdir -p app/lib/vendor/
git init .
git submodule add https://github.com/capile/Tecnodesign.git app/lib/vendor/Tecnodesign
cd app
php lib/vendor/Tecnodesign/tdz.php install [project]
```

You can also use the installer for some specific components installation, for example:

- **Database**   
  To install only module database connection into your [apps-dir]:  
  `php lib/vendor/Tecnodesign/tdz.php install:database [project]`

- **Studio**   
  To install Studio CMS modules:   
  `php lib/vendor/Tecnodesign/tdz.php install:studio [project]`

- **Dependencies**   
  `php lib/vendor/tecnodesign/tdz.php install:deps [project]`   
  Note that dependencies might need to be set as submodules of your main project rspository.


## Configuration

Most of the applications and components of the framework can be configured using YAML or ini configuration files within the `[apps-dir]/config/` folder.


For example, to configure Studio's caching settings, templates or languages, we use public static variables in `Tecnodesign_Studio` class, that can be overwritten using a `[apps-dir]/config/autoload.Tecnodesign_Studio.yml` or a `[apps-dir]/config/autoload.Tecnodesign_Studio.ini` configuration file. Like this:

```
---
cacheTimeout: 3600
staticCache: 180
languages: [ en, pt ]
```

The database connections can also be defined by updating the `tdz::$database` variable the same way. Or, alternatively, write down a `[apps-dir]/config/databases.yml` configuration file with the specific environment as the master key. Like this:

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

