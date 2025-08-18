<?php

declare(strict_types=1);

namespace Hyperf\Scout\Engines;

use Hyperf\Collection\Collection as BaseCollection;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\SoftDeletes;
use Hyperf\Scout\Builder;
use Hyperf\Scout\Contracts\UpdatesIndexSettings;
use Hyperf\Scout\Engine\Engine;
use Hyperf\Scout\Engines\Traits\UpdatesIndexSettingsTrait;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Contracts\IndexesQuery;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Search\SearchResult;

use function Hyperf\Collection\collect;
use function Hyperf\Support\class_uses_recursive;

class MeilisearchEngine extends Engine implements UpdatesIndexSettings
{
    use UpdatesIndexSettingsTrait;

    /**
     * Create a new engine instance.
     * @param mixed $softDelete
     */
    public function __construct(protected MeilisearchClient $meilisearch, protected $softDelete = false)
    {
    }

    /**
     * Dynamically call the Meilisearch client instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->meilisearch->{$method}(...$parameters);
    }

    /**
     * Update the given model in the index.
     */
    public function update(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $index = $this->meilisearch->index($models->first()->searchableAs());

        if ($this->usesSoftDelete($models->first()) && $this->softDelete) {
            $models->each->pushSoftDeleteMetadata();
        }

        $objects = $models->map(function ($model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }

            return array_merge(
                $searchableData,
                $model->scoutMetadata(),
                [$model->getScoutKeyName() => (string)$model->getScoutKey()],
            );
        })->filter()->values()->all();

        if (!empty($objects)) {
            $index->addDocuments($objects, $models->first()->getScoutKeyName());
        }
    }

    /**
     * Remove the given model from the index.
     */
    public function delete(Collection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $index = $this->meilisearch->index($models->first()->searchableAs());

        $keys = $models->map->getScoutKey();

        $index->deleteDocuments($keys->values()->all());
    }

    /**
     * Perform the given search on the engine.
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'filter' => $this->filters($builder),
            'hitsPerPage' => $builder->limit,
            'sort' => $this->buildSortFromOrderByClauses($builder),
        ]));
    }

    /**
     * Perform the given search on the engine.
     */
    public function paginate(Builder $builder, int $perPage, int $page)
    {
        return $this->performSearch($builder, array_filter([
            'filter' => $this->filters($builder),
            'hitsPerPage' => $perPage,
            'page' => $page,
            'sort' => $this->buildSortFromOrderByClauses($builder),
        ]));
    }

    /**
     * Pluck and return the primary keys of the given results.
     * @param mixed $results
     */
    public function mapIds($results): BaseCollection
    {
        if (count($results['hits']) === 0) {
            return collect();
        }

        $hits = collect($results['hits']);

        $key = key((array)$hits->first());

        return $hits->pluck($key)->values();
    }

    /**
     * Map the given results to instances of the given model.
     * @param mixed $results
     */
    public function map(Builder $builder, $results, Model $model): Collection
    {
        if (is_null($results) || $this->getTotalCount($results) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits'])->pluck($model->getScoutKeyName())->values()->all();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder,
            $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->map(function ($model) use ($results, $objectIdPositions) {
            $result = $results['hits'][$objectIdPositions[$model->getScoutKey()]] ?? [];

            foreach ($result as $key => $value) {
                if (substr($key, 0, 1) === '_') {
                    $model->withScoutMetadata($key, $value);
                }
            }

            return $model;
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     * @param mixed $results
     */
    public function getTotalCount($results): int
    {
        return $results['totalHits'] ?? $results['estimatedTotalHits'];
    }

    /**
     * Flush all of the model's records from the engine.
     */
    public function flush(Model $model): void
    {
        $index = $this->meilisearch->index($model->searchableAs());

        $index->deleteAllDocuments();
        $index->delete();
    }

    /**
     * Create a search index.
     *
     * @param string $name
     * @return mixed
     *
     * @throws ApiException
     */
    public function createIndex($name, array $options = [])
    {
        try {
            $index = $this->meilisearch->getIndex($name);
        } catch (ApiException $e) {
            $index = null;
        }

        if ($index?->getUid() !== null) {
            return $index;
        }

        return $this->meilisearch->createIndex($name, $options);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     * @return mixed
     *
     * @throws ApiException
     */
    public function deleteIndex($name)
    {
        return $this->meilisearch->deleteIndex($name);
    }

    /**
     * Delete all search indexes.
     *
     * @return mixed
     */
    public function deleteAllIndexes()
    {
        $tasks = [];
        $limit = 1000000;

        $query = new IndexesQuery();
        $query->setLimit($limit);

        $indexes = $this->meilisearch->getIndexes($query);

        foreach ($indexes->getResults() as $index) {
            $tasks[] = $index->delete();
        }

        return $tasks;
    }

    /**
     * Perform the given search on the engine.
     *
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $searchParams = [])
    {
        $meilisearch = $this->meilisearch->index($builder->index ?: $builder->model->searchableAs());

        if (array_key_exists('attributesToRetrieve', $searchParams)) {
            $searchParams['attributesToRetrieve'] = array_merge(
                [$builder->model->getScoutKeyName()],
                $searchParams['attributesToRetrieve'],
            );
        }

        if ($builder->callback) {
            $result = call_user_func(
                $builder->callback,
                $meilisearch,
                $builder->query,
                $searchParams
            );

            $searchResultClass = SearchResult::class;

            return $result instanceof $searchResultClass ? $result->getRaw() : $result;
        }

        return $meilisearch->rawSearch($builder->query, $searchParams);
    }

    /**
     * Get the filter array for the query.
     *
     * @return string
     */
    protected function filters(Builder $builder)
    {
        $filters = collect($builder->wheres)->map(function ($value, $key) {
            if (is_bool($value)) {
                return sprintf('%s=%s', $key, $value ? 'true' : 'false');
            }

            if (is_null($value)) {
                return sprintf('%s %s', $key, 'IS NULL');
            }

            return is_numeric($value)
                ? sprintf('%s=%s', $key, $value)
                : sprintf('%s="%s"', $key, $value);
        });

        $whereInOperators = [
            'whereIns' => 'IN',
            'whereNotIns' => 'NOT IN',
        ];

        foreach ($whereInOperators as $property => $operator) {
            if (property_exists($builder, $property)) {
                foreach ($builder->{$property} as $key => $values) {
                    $filters->push(sprintf('%s %s [%s]', $key, $operator, collect($values)->map(function ($value) {
                        if (is_bool($value)) {
                            return sprintf('%s', $value ? 'true' : 'false');
                        }

                        return filter_var($value, FILTER_VALIDATE_INT) !== false
                            ? sprintf('%s', $value)
                            : sprintf('"%s"', $value);
                    })->values()->implode(', ')));
                }
            }
        }

        return $filters->values()->implode(' AND ');
    }

    /**
     * Get the sort array for the query.
     */
    protected function buildSortFromOrderByClauses(Builder $builder): array
    {
        return collect($builder->orders)->map(function (array $order) {
            return $order['column'] . ':' . $order['direction'];
        })->toArray();
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param mixed $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
