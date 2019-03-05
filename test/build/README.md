Docker Images
-------------

This docker images should be used with PHPStorm or when you want to run a command 
in a specific php version


## Composer 

To run composer in php old version is advisable 
to get the correct constrains in packages and still be able to 
use a more recent PHP version. For example (based on php 5.4 docker-composer)

```bash
docker run --rm -v $(pwd):/opt/project -w /opt/project php54 composer install
```
