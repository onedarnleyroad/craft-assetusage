<?php

namespace roelvanhintum\assetusage;

use Craft;
use craft\base\Plugin as CraftPlugin;
use craft\console\Application as ConsoleApplication;
use craft\controllers\ElementsController;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\events\CancelableEvent;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineElementEditorHtmlEvent;
use craft\events\RegisterElementTableAttributesEvent;
use roelvanhintum\assetusage\models\Settings;
use roelvanhintum\assetusage\services\Asset as AssetService;
use yii\base\Event;

/**
 * @method static Plugin getInstance()
 * @property-read \roelvanhintum\assetusage\services\Asset $asset
 */
class Plugin extends CraftPlugin
{
    public string $schemaVersion = '2.0.0';

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Plugin::$plugin
     */
    public static Plugin $plugin;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        if (!$this->isInstalled) {
            return;
        }

        // Register Components (Services)
        $this->setComponents([
            'asset' => AssetService::class,
        ]);

        // Add in our console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'roelvanhintum\assetusage\console\controllers';
        }

        $this->registerTableAttributes();

        if ($this->getSettings()->renderUsedByInAssetDetail) {
            $this->registerTemplateHooks();
        }
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    private function registerTemplateHooks(): void
    {
        Event::on(ElementsController::class, ElementsController::EVENT_DEFINE_EDITOR_CONTENT, function(DefineElementEditorHtmlEvent $event) {
            if ($event->element instanceof Asset) {
                /** @var Asset $asset */
                $asset = $event->element;
                $event->html .= Craft::$app->getView()->renderTemplate('assetusage/_hooks/asset-edit-details', [
                    'elements' => $this->asset->getUsedIn($asset),
                ]);
            }
        });
    }

    /**
     * Adds the following attributes to the asset fields in CMS
     * NOTE: You still need to select them with the 'gear'
     */
    private function registerTableAttributes(): void
    {
        Event::on(Asset::class, Asset::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['usage'] = [
                'label' => Craft::t('assetusage', 'Usage'),
            ];
        });

        // Cheaply capture the scoped asset IDs for the current index page.
        // No usage queries run here — we only materialise the ID list so it's
        // ready if EVENT_DEFINE_ATTRIBUTE_HTML actually needs it.
        Event::on(AssetQuery::class, AssetQuery::EVENT_AFTER_PREPARE, function(CancelableEvent $event) {
            /** @var AssetQuery $query */
            $query = $event->sender;
            $this->asset->captureIndexIds($query);
        });

        Event::on(Asset::class, Asset::EVENT_DEFINE_ATTRIBUTE_HTML, function(DefineAttributeHtmlEvent $event) {
            if ($event->attribute !== 'usage') {
                return;
            }

            /** @var Asset $asset */
            $asset = $event->sender;
            $event->html = $this->asset->getUsageCount($asset);
            $event->handled = true;
        });
    }
}