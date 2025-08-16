<?php

declare(strict_types=1);

namespace Hyperf\Scout\Contracts;

interface UpdatesIndexSettings
{
    /**
     * Update the index settings for the given index.
     */
    public function updateIndexSettings(string $name, array $settings = []);

    /**
     * Configure the soft delete filter within the given settings.
     *
     * @return array
     */
    public function configureSoftDeleteFilter(array $settings = []);
}
