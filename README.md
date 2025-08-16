# Introduction
This extension package provides Meilisearch integration similar to Laravel Scout for the Hyperf framework, supporting document indexing, searching, batch updates, and batch synchronization of index settings, etc.

- Supports custom index names
- Supports batch adding/updating/deleting documents
- Supports batch synchronization of index settings

# Environment Requirements
- PHP >= 8.1
- Hyperf >= 3.1

# Installation
```shell
composer require liangguifeng/hyperf-scout-meilisearch
```

# Configuration
## Add configurations in `config/autoload/scout.php`:

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
                    'filterableAttributes' => ['id', 'type', 'created_at'], // Filterable fields (custom)
                    'sortableAttributes' => ['id', 'sort', 'created_at'],   // Sortable fields (custom)
//                    'searchableAttributes' => [], // Searchable fields (all fields are searchable by default; configure if you need to specify)
                ],
            ]
        ],
    ],
];
```

## Add in the `.env` file
```shell
SCOUT_ENGINE=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=xxxxxxxxxxxxxxxxx
```

# Usage
## Synchronize search model content
```shell
php bin/hyperf.php scout:import "App\Model\CrawleSanyaService"
```

## Delete search model content
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

## Other usages
Please refer to the official Hyperf Scout documentation: [https://hyperf.wiki/3.1/#/en/scout](https://hyperf.wiki/3.1/#/en/scout)

# Notes
- The primary key must be a string to avoid loss of precision for large integers
- The primary key of an index can only be set once
- Do not use a dot (.) as a primary key field when adding documents

# Project supported by JetBrains
Many thanks to JetBrains for providing me with a license to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)

# License
MIT