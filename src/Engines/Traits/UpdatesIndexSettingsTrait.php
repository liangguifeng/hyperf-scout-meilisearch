<?php

declare(strict_types=1);

namespace Hyperf\Scout\Engines\Traits;

use Hyperf\Collection\Arr;

trait UpdatesIndexSettingsTrait
{
    /**
     * Update the index settings for the given index.
     *
     * @param mixed $name
     */
    public function updateIndexSettings($name, array $settings = [])
    {
        $index = $this->meilisearch->index($name);

        $index->updateSettings(Arr::except($settings, 'embedders'));

        if (!empty($settings['embedders'])) {
            $index->updateEmbedders($settings['embedders']);
        }
    }

    /**
     * Configure the soft delete filter within the given settings.
     *
     * @return array
     */
    public function configureSoftDeleteFilter(array $settings = [])
    {
        $settings['filterableAttributes'][] = '__soft_deleted';

        return $settings;
    }
}
