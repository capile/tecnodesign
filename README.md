
Tecnodesign Application Development Framework 
=============================================

This is a framework developed by Tecnodesign to build web applications and websites. It comes with a powerful MVC structure and several additional modules.


# Installation

In order to install the Tecnodesign application framework:

1. Copy all files to [apps-dir]/lib/vendor/tecnodesign
2. cd to [apps-dir]
3. Run: `php lib/vendor/tecnodesign/tdz.php install [project]`

Usually [apps-dir] is located outside the document root, for example:

     /var/www/sitename
                |- htdocs
                |- [apps-dir]

Installation script will prompt for additional modules and dependencies:

```bash
mkdir -p app/lib/vendor/
git init .
git submodule add git@github.com:tecnodz/tdz.git app/lib/vendor/tecnodesign
cd app
php lib/vendor/tecnodesign/tdz.php install [project]
```

## Database

To install only module database connection into your [apps-dir]:

```bash
php lib/vendor/tecnodesign/tdz.php install:database [project]
```

## E-studio

To install E-studio CMS modules:

```bash
php lib/vendor/tecnodesign/tdz.php install:studio [project]
```

## Dependencies

To Dependencies execute:

```bash
php lib/vendor/tecnodesign/tdz.php install:deps [project]
```
