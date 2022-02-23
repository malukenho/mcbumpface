:fallen_leaf: McBumpface
========================

A simple tool to sync `composer.lock`  and `composer.json`  versions, resulting in a faster package resolutions.

### Upgrading to 2.0

If upgrading from 1.x, note the following changes:

- The extra field has changed from `mc-bumpface` to `mcbumpface`.
- The configuration option `stripVersionPrefixes` is now `stripVersionPrefix`, defaults to true and can be passed as a
  CLI option.
- This tool is no longer run on every update, to replicate v1 behaviour, add this tool to a `post-update-cmd` in
  your `composer.json`.

### Installation (global)

```
composer global require malukenho/mcbumpface
```

### Usage

```
composer mcbumpface [--stripVersionPrefix=false]
```

#### How it works?

By looking at the  `composer.lock` file we can replace the required version specified on `composer.json`  file by the
installed version specified on `composer.lock` file.

#### Example

###### composer.json (before)

```json
{
    "require": {
        "malukenho/docheader": "^1.0.1"
    }
}
```

If composer has installed version `^1.0.4`, after running `composer mcbumpface` my `composer.json` will looks like the
following:

###### composer.json (after)

```json
{
    "require": {
        "malukenho/docheader": "^1.0.4"
    }
}
```

### Configuration (optional)

By adding an extra configuration to the projects `composer.json`, you can configure different behavior of the version
bumping. The configuration can be added like this:

```json
{
    "extra": {
        "mcbumpface": {
            "stripVersionPrefix": false,
            "keepVersionConstraintPrefix": false
        }
    }
}
```

The following configurations are available:

- [stripVersionPrefix](#configuration-stripVersionPrefix)
- [keepVersionConstraintPrefix](#configuration-keepVersionConstraintPrefix)

###### stripVersionPrefix (default: true)

<a name="configuration-stripVersionPrefix"></a>
By setting this parameter to `false`, `mcbumpface` will not strip the `v` prefix from versions (in case they are tagged
like this).

###### keepVersionConstraintPrefix (default: false)
<a name="configuration-keepVersionConstraintPrefix"></a>
By setting this parameter to `true`, `mcbumpface` will NOT replace the version constraint prefix.

Having a required version `~2.0` and installed `2.0.20` will replace the version constraint to `^2.0.20`.
