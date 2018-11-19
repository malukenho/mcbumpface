:fallen_leaf: McBumpface
========================

A  simple tool  to  sync `composer.lock`  and `composer.json`  versions,
resulting in a faster package dependencies resolution.

### Installing

```
composer require --dev malukenho/mcbumpface
```

### How it works?

By looking  at the  `composer.lock` file  which is  (re)generated during
`composer  install` or  `composer update`  we can  replace the  required
version  specified  on `composer.json`  file  by  the installed  version
specified on `composer.lock` file.

### Example

###### composer.json (before)

```json
{
    "require": {
        "malukenho/docheader": "^1.0.1"
    }
}
```

After a `composer update`, composer  have installed version `^1.0.4`, so
my `composer.json` will looks like the following:

###### composer.json (after)

```json
{
    "require": {
        "malukenho/docheader": "^1.0.4"
    }
}
```
