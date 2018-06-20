:fallen_leaf: McBumpface
========================

A simple tool to sync `composer.lock` and `composer.json` versions, 
resulting in a faster package dependencies resolution (I hope).

### Installing

```
composer require --dev malukenho/mcbumpface
```

### How it works?

By looking at the `composer.lock` file which is (re)generated during
`composer install` or `composer update` we can replace the required version
by the installed version.

### Example

###### composer.json (before)

```json
{
    "require": {
        "malukenho/docheader": "^1.0.1"
    }
}
```

After a `composer update`, composer have installed version `^1.0.4`, 
so my `composer.json` will be the follow:

###### composer.json (after)

```json
{
    "require": {
        "malukenho/docheader": "^1.0.4"
    }
}
```
