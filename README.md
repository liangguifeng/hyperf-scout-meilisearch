English | [中文](./README-CN.md)

[TOC]

# Introduction

This extension package provides a Meilisearch integration for the Hyperf framework similar to Laravel Scout. It supports document indexing, searching, batch updates, and batch synchronization of index settings.

* Supports custom index names
* Supports batch add/update/delete documents
* Supports batch synchronization of index settings

# Requirements

* PHP >= 8.1
* Hyperf >= 3.1

# Installation

```shell
composer require liangguifeng/hyperf-scout-meilisearch
```

# Configuration

## Add configuration in `config/autoload/scout.php`:

```php
use Hyperf\Scout\Provider\MeilisearchProvider;

return [
    'default' => env('SCOUT_ENGINE', 'meilisearch'),
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'prefix' => env('SCOUT_PREFIX', ''),
    'soft_delete' => false,
    'concurrency' => 100,
    'engine' => [
        'elasticsearch' => [
            'driver' => ElasticsearchProvider::class,
            'index' => null,
            'hosts' => [
                env('ELASTICSEARCH_HOST', 'http://127.0.0.1:9200'),
            ],
        ],
        'meilisearch' => [
            'driver' => MeilisearchProvider::class,
            'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'), // Your Meilisearch address
            'key' => env('MEILISEARCH_KEY', null), // Your Meilisearch key
            'index-settings' => [
                Article::class => [
                    'filterableAttributes' => ['id', 'type', 'created_at'], // Filterable fields (customizable)
                    'sortableAttributes' => ['id', 'sort', 'created_at'],   // Sortable fields (customizable)
//                    'searchableAttributes' => [], // Searchable fields (all fields by default, configure if needed)
                ],
            ]
        ],
    ],
];
```

## Add to `.env`

```shell
SCOUT_ENGINE=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=xxxxxxxxxxxxxxxxx
```

## Add `Scout` configuration in `Model`

```php
<?php

namespace App\Model;

use Hyperf\Scout\Searchable;

class Article extends Model
{
    use Searchable;

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'articles';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return $this->toArray();
    }

    /**
     * Get the key name used to index the model.
     *
     * @return mixed
     */
    public function getScoutKeyName()
    {
        return $this->getKeyName();
    }
}
```

**Notes**
The `getScoutKeyName` method must be added, because the default `getScoutKeyName` method in Hyperf is as follows:

```php
// \Hyperf\Database\Model\Model::getQualifiedKeyName

/**
 * Get the Scout index name used for the model.
 *
 * @return mixed
 */
public function getScoutKeyName()
{
    return $this->getQualifiedKeyName();
}

// \Hyperf\Database\Model\Model::getQualifiedKeyName
/**
 * Get the fully qualified index field name.
 *
 * @return string
 */
public function getQualifiedKeyName()
{
    return $this->qualifyColumn($this->getKeyName());
}

// \Hyperf\Database\Model\Model::qualifyColumn
/**
 * Qualify the given column name by the model's table.
 *
 * @param string $column
 * @return string
 */
public function qualifyColumn($column)
{
    if (Str::contains($column, '.')) {
        return $column;
    }

    return $this->getTable() . '.' . $column;
}
```

However, `meilisearch` does not support using a dot (`.`) as a primary key field. Please modify the `getScoutKeyName` method accordingly.

# Usage

## Import model data into the index

```shell
php bin/hyperf.php scout:import "App\Model\CrawleSanyaService"
```

## Flush model data from the index

```shell
php bin/hyperf.php scout:flush "App\Model\CrawleSanyaService"
```

## Create an index

```shell
php bin/hyperf.php meilisearch:index {name : The name of the index} {--k|key= : The name of the primary key}'
```

## Delete an index

```shell
php bin/hyperf.php meilisearch:delete-index {name : The name of the index}
```

## Synchronize index settings

```shell
php bin/hyperf.php meilisearch:sync-index-settings {name : The name of the index} {--k|key= : The name of the primary key}'
```

## For detailed `Scout` usage

Please refer to the official Hyperf Scout documentation: [https://hyperf.wiki/3.1/#/zh-cn/scout](https://hyperf.wiki/3.1/#/zh-cn/scout)

# Notes

* The primary key must be a string to avoid precision loss with large integers
* The primary key of an index can only be set once
* Do not use a dot (`.`) in primary key fields when adding documents

# JetBrains Supported Project

Many thanks to JetBrains for providing me with a license to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)

# License

MIT
