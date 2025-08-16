<?php

declare(strict_types=1);

namespace Hyperf\Scout\Provider;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Scout\Engine\Engine;
use Hyperf\Scout\Engines\MeilisearchEngine;
use Meilisearch\Client;
use Psr\Container\ContainerInterface;

class MeilisearchProvider implements ProviderInterface
{
    /**
     * Constructor.
     */
    public function __construct(private ContainerInterface $container) {}

    /**
     * Make Engine.
     */
    public function make(string $name): Engine
    {
        $config = $this->container->get(ConfigInterface::class);
        $client = new Client($config->get("scout.engine.{$name}.host"), $config->get("scout.engine.{$name}.key"));
        return new MeilisearchEngine($client);
    }
}
