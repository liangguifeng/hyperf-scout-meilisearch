<?php

declare(strict_types=1);

namespace Hyperf\Scout;

use Hyperf\Scout\Command\DeleteIndexCommand;
use Hyperf\Scout\Command\SyncIndexSettingsCommand;

class MeilisearchConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'commands' => [
                SyncIndexSettingsCommand::class,
                DeleteIndexCommand::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
