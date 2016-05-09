
# Installation

In order to install the Tecnodesign application framework:

1. Copy all files to [apps-dir]/lib/vendor/tecnodesign
2. cd to [apps-dir]
3. Run: $ php lib/vendor/tecnodesign/tdz.php install [project-name]

Usually [apps-dir] is located outside the document root, for example:

 /is/vhosts/sitename
             |- htdocs
             |- [apps-dir]

Simple configurable options:

```bash
mkdir -p app/lib/vendor/
git init .
git submodule add git@github.com:tecnodz/tdz.git app/lib/vendor/tecnodesign
cd app
php lib/vendor/tecnodesign/tdz.php install [project]
```
