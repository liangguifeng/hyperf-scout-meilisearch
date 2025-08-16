<?php

declare(strict_types=1);

namespace Hyperf\Scout\Command;

use Exception;
use Hyperf\Command\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\Scout\Engines\MeilisearchEngine;
use Hyperf\Stringable\Str;
use Meilisearch\Client;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\class_uses_recursive;

class SyncIndexSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected ?string $signature = 'meilisearch:sync-index-settings';

    /**
     * The console command description.
     */
    protected string $description = 'Sync your configured index settings with your search engine (Meilisearch)';

    /**
     * Config.
     */
    protected ConfigInterface $config;

    /**
     * Constructor.
     */
    public function __construct(private ContainerInterface $container)
    {
        parent::__construct();
        $this->config = $this->container->get(ConfigInterface::class);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $driver = $this->config->get('scout.default');
        $client = new Client($this->config->get("scout.engine.{$driver}.host"), $this->config->get("scout.engine.{$driver}.key"));
        $engine = new MeilisearchEngine($client);

        try {
            $indexes = (array) $this->config->get("scout.engine.{$driver}.index-settings", []);

            if (count($indexes)) {
                foreach ($indexes as $name => $settings) {
                    if (!is_array($settings)) {
                        $name = $settings;

                        $settings = [];
                    }

                    if (class_exists($name)) {
                        $model = new $name();
                    }

                    if (
                        isset($model)
                        && $this->config->get('scout.soft_delete', false)
                        && in_array(SoftDeletes::class, class_uses_recursive($model))
                    ) {
                        $settings = $engine->configureSoftDeleteFilter($settings);
                    }

                    $engine->updateIndexSettings($indexName = $this->indexName($name), $settings);

                    $this->info('Settings for the [' . $indexName . '] index synced successfully.');
                }
            } else {
                $this->info('No index settings found for the "' . $driver . '" engine.');
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Get the fully-qualified index name for the given index.
     *
     * @param string $name
     * @return string
     */
    protected function indexName($name)
    {
        if (class_exists($name)) {
            return (new $name())->searchableAs();
        }

        $prefix = $this->config->get('scout.prefix');

        return !Str::startsWith($name, $prefix) ? $prefix . $name : $name;
    }
}
