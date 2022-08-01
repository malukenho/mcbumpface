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

### Configuration (optional)

By adding an extra configuration to the projects `composer.json`, you can configure different behavior of the version bumping.
The configuration can be added like this:
```json
{
    "extra": {
        "mc-bumpface": {
            "stripVersionPrefixes": false,
            "keepVersionConstraintPrefix": false
        }
    }
}
```

The following configurations are available:

- [stripVersionPrefixes](#configuration-stripVersionPrefixes)
- [keepVersionConstraintPrefix](#configuration-keepVersionConstraintPrefix)

###### stripVersionPrefixes (default: false)
<a name="configuration-stripVersionPrefixes"></a> 
By setting this parameter to `true`, `mcbumpface` will strip the `v` prefix from versions (in case they are tagged like this).  

###### keepVersionConstraintPrefix (default: false)
<a name="configuration-keepVersionConstraintPrefix"></a>
By setting this parameter to `true`, `mcbumpface` will NOT replace the version constraint prefix.

Having a required version `~2.0` and installed `2.0.20` will replace the version constraint to `^2.0.20`.
