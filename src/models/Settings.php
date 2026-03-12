<?php

namespace roelvanhintum\assetusage\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * Whether to include a content JSON scan (elements_sites.content) when
     * counting asset usage.
     *
     * This catches asset references stored outside of standard Craft relations
     * (e.g. hardcoded field handles in certain field types), but requires a
     * per-asset LIKE query against the elements_sites table and can be very
     * slow on large installs. Enabled by default.
     */
    public bool $includeContentSearch = true;

    /**
     * Whether to include revision (and draft) elements in the usage count.
     */
    public bool $includeRevisions = false;

    /**
     * Whether to show the "Used by" list in the asset editor panel.
     */
    public bool $renderUsedByInAssetDetail = true;

    public function defineRules(): array
    {
        return [
            [['includeContentSearch', 'includeRevisions', 'renderUsedByInAssetDetail'], 'bool'],
        ];
    }
}
