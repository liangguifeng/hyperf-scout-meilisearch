<?php

declare(strict_types=1);

namespace Laravel\Scout\Console;

use Exception;
use Hyperf\Command\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\Scout\Contracts\UpdatesIndexSettings;
use Hyperf\Scout\Engines\MeilisearchEngine;
use Hyperf\Stringable\Str;
use Meilisearch\Client;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\class_uses_recursive;

class IndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected ?string $signature = 'scout:index
            {name : The name of the index}
            {--k|key= : The name of the primary key}';

    /**
     * The console command description.
     */
    protected string $description = 'Create an index';

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
            $options = [];

            if ($this->option('key')) {
                $options = ['primaryKey' => $this->option('key')];
            }

            if (class_exists($modelName = $this->argument('name'))) {
                $model = new $modelName();
            }

            $name = $this->indexName($this->argument('name'));

            $this->createIndex($engine, $name, $options);

            if ($engine instanceof UpdatesIndexSettings) {
                $class = isset($model) ? get_class($model) : null;

                $settings = $this->config->get("scout.engine.{$driver}.index-settings." . $name, [])
                    ?? $this->config->get("scout.engine.{$driver}.index-settings." . $class, [])
                    ?? [];

                if (
                    isset($model)
                    && $this->config->get('scout.soft_delete', false)
                    && in_array(SoftDeletes::class, class_uses_recursive($model))
                ) {
                    $settings = $engine->configureSoftDeleteFilter($settings);
                }

                if ($settings) {
                    $engine->updateIndexSettings($name, $settings);
                }
            }

            $this->info('Synchronised index ["' . $name . '"] successfully.');
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Create a search index.
     *
     * @param MeilisearchEngine $engine
     * @param string $name
     * @param array $options
     */
    protected function createIndex(MeilisearchEngine $engine, $name, $options): void
    {
        try {
            $engine->createIndex($name, $options);
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
            return;
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
            return (new $name())->indexableAs();
        }

        $prefix = $this->config->get('scout.prefix');

        return !Str::startsWith($name, $prefix) ? $prefix . $name : $name;
    }
}
