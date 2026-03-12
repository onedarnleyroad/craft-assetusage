# Craft Asset Usage plugin

Adds a column to the assets overview to see which assets are used or unused. For Craft 5.

The "Usage" field shows all relations and the "Current Usage" field shows relations excluding revisions and deleted elements.

## Setup

- Go to `admin/assets`
- Click the "sprocket" icon
- Check the "Usage" or "Current Usage" column
- Save
- The assets table should now show a "Usage" or "Current Usage" column indicating usage

## Settings

The setting values can be overridden from a PHP file within your project's `config/` folder, named `assetusage.php`.

The file just needs to return an array with the overridden values:
```php
<?php

return [
    'includeRevisions' => false,
    'renderUsedByInAssetDetail' => true,
    'includeContentSearch' => true,
];
```

| Setting | Default | Description |
|---|---|---|
| `includeRevisions` | `false` | Whether to include draft and revision elements in the usage count |
| `renderUsedByInAssetDetail` | `true` | Whether to show the "Used by" list in the asset editor panel |
| `includeContentSearch` | `true` | Whether to scan `elements_sites.content` for asset references stored outside of standard Craft relations. Can be disabled for a performance improvement on large installs |

### Support

- Everything using an asset field or the relations table, including matrix fields
- SuperTable

### Does NOT support (assets not connected through relations table)

- LinkIt
- Redactor
- ether/seo

## Commandline usage
```sh
craft assetusage/default/delete-unused  # Deletes all unused assets.
craft assetusage/default/list-unused    # Lists all unused assets.
```