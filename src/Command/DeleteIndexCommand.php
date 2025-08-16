<?php

declare(strict_types=1);

namespace Hyperf\Scout\Command;

use Exception;
use Hyperf\Command\Command;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Scout\Engines\MeilisearchEngine;
use Hyperf\Stringable\Str;
use Meilisearch\Client;
use Psr\Container\ContainerInterface;

class DeleteIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected ?string $signature = 'meilisearch:delete-index {name : The name of the index}';

    /**
     * The console command description.
     */
    protected string $description = 'Delete an index';

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
            $engine->deleteIndex($name = $this->indexName($this->argument('name')));

            $this->info('Index "' . $name . '" deleted.');
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
            return (new $name())->indexableAs();
        }

        $prefix = $this->config->get('scout.prefix');

        return !Str::startsWith($name, $prefix) ? $prefix . $name : $name;
    }
}
