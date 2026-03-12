<?php

namespace roelvanhintum\assetusage\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset as AssetElement;
use craft\elements\db\AssetQuery;
use craft\helpers\ElementHelper;
use roelvanhintum\assetusage\Plugin;

class Asset extends Component
{
    /**
     * Request-scoped list of asset IDs visible on the current index page.
     * Captured cheaply in EVENT_AFTER_PREPARE regardless of visible columns.
     */
    private ?array $indexAssetIds = null;

    /**
     * Request-scoped cache: assetId => formatted usage string.
     * null = not yet primed. Only populated when the usage column is rendered.
     */
    private ?array $usageCountCache = null;

    /**
     * Captures the scoped asset ID list from the prepared query.
     * Called from EVENT_AFTER_PREPARE — no usage queries run here.
     */
    public function captureIndexIds(AssetQuery $query): void
    {
        if ($this->indexAssetIds !== null) {
            return;
        }

        $this->indexAssetIds = (clone $query->subQuery)
            ->select(['elements.id'])
            ->limit($query->limit)
            ->offset($query->offset)
            ->column();
    }

    /**
     * Count the number of times an asset is used and return a formatted string.
     * e.g. Used {count} times
     *
     * When index IDs have been captured, primes the full cache on first call
     * so all subsequent calls in the same request are free array lookups.
     * Falls back to a direct per-asset query outside the index context
     * (editor panel, CLI, etc.).
     *
     * @param  AssetElement $asset
     * @return string
     */
    public function getUsageCount(AssetElement $asset): string
    {
        if ($this->indexAssetIds !== null) {
            $this->primeUsageCache();
            return $this->usageCountCache[$asset->id] ?? $this->formatResults(0);
        }

        // Fallback: per-asset query path for use outside the index context.
        $relations = $this->queryRelations($asset);

        if (Plugin::getInstance()->settings->includeContentSearch) {
            $relations = array_merge($relations, $this->queryContents($asset->id));
        }

        return $this->formatResults(count($relations));
    }

    /**
     * Get all elements related to the asset.
     *
     * @param  AssetElement $asset
     * @return array
     */
    public function getUsedIn(AssetElement $asset): array
    {
        $relations = $this->queryRelations($asset);

        if (Plugin::getInstance()->settings->includeContentSearch) {
            $relations = array_merge($relations, $this->queryContents($asset->id));
        }

        $elements = [];

        foreach ($relations as $relation) {
            try {
                $element = Craft::$app->elements->getElementById($relation['id'], null, $relation['siteId']);
                $root = ElementHelper::rootElement($element);

                if ($root) {
                    $elements[$root->id] = $root;
                }
            } catch (\Throwable $e) {
                // let it slide...
            }
        }

        return array_values($elements);
    }

    /**
     * Primes $usageCountCache with a single batch query scoped to $indexAssetIds.
     */
    private function primeUsageCache(): void
    {
        if ($this->usageCountCache !== null) {
            return;
        }

        $counts = array_fill_keys($this->indexAssetIds ?? [], 0);

        if (empty($counts)) {
            $this->usageCountCache = $counts;
            return;
        }

        $query = (new Query())
            ->select(['targetId'])
            ->from(Table::RELATIONS)
            ->where(['targetId' => $this->indexAssetIds]);

        if (!Plugin::getInstance()->settings->includeRevisions) {
            $query
                ->innerJoin(Table::ELEMENTS, '[[elements.id]] = [[relations.sourceId]]')
                ->andWhere(['elements.draftId' => null])
                ->andWhere(['elements.revisionId' => null]);
        }

        foreach ($query->all() as $row) {
            $counts[(int)$row['targetId']]++;
        }

        if (Plugin::getInstance()->settings->includeContentSearch) {
            foreach ($this->indexAssetIds as $assetId) {
                $counts[$assetId] += count($this->queryContents($assetId));
            }
        }

        $this->usageCountCache = [];
        foreach ($counts as $assetId => $count) {
            $this->usageCountCache[(int)$assetId] = $this->formatResults($count);
        }
    }

    private function queryRelations(AssetElement $asset): array
    {
        $query = (new Query())
            ->select(['sourceId as id', 'sourceSiteId as siteId'])
            ->from(Table::RELATIONS)
            ->where(['targetId' => $asset->id]);

        if (!Plugin::getInstance()->settings->includeRevisions) {
            $query
                ->innerJoin(Table::ELEMENTS, '[[elements.id]] = [[relations.sourceId]]')
                ->andWhere(['elements.draftId' => null])
                ->andWhere(['elements.revisionId' => null]);
        }

        return $query->all();
    }

    private function queryContents(int $assetId): array
    {
        $query = (new Query())
            ->select(['elementId as id', 'siteId'])
            ->from(Table::ELEMENTS_SITES);

        if (!Plugin::getInstance()->settings->includeRevisions) {
            $query
                ->innerJoin(Table::ELEMENTS, '[[elements.id]] = [[elements_sites.elementId]]')
                ->andWhere(['elements.draftId' => null])
                ->andWhere(['elements.revisionId' => null]);
        }

        // PostgreSQL requires explicit casting for JSONB columns
        if (Craft::$app->getDb()->getIsPgsql()) {
            $query->andWhere(['or',
                ['like', 'CAST(content AS TEXT)', "asset:{$assetId}@"],
                ['like', 'CAST(content AS TEXT)', "\"imageId\": \"{$assetId}\""],
            ]);
        } else {
            $query->andWhere(['or',
                ['like', 'content', "asset:{$assetId}@"],
                ['like', 'content', "\"imageId\": \"{$assetId}\""],
            ]);
        }

        return $query->all();
    }

    /**
     * Format the count into a string.
     * e.g. Used {count} times
     *
     * @param  int $count
     * @return string
     */
    private function formatResults($count): string
    {
        if ($count === 1) {
            return Craft::t('assetusage', 'Used {count} time', ['count' => $count]);
        } elseif ($count > 1) {
            return Craft::t('assetusage', 'Used {count} times', ['count' => $count]);
        }

        return '<span style="color: #da5a47;">' . Craft::t('assetusage', 'Unused') . '</span>';
    }
}