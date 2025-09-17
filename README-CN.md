English | [中文](./README-CN.md)

[TOC]

# 简介
本扩展包为 Hyperf 框架 提供了类似 Laravel Scout 的 Meilisearch 集成，支持文档索引、搜索、批量更新和批量同步索引设置等。

- 支持自定义索引名
- 支持批量添加/更新/删除文档
- 支持批量同步索引设置

# 环境要求
- PHP >= 8.1
- Hyperf >= 3.1

# 安装
```shell
composer require liangguifeng/hyperf-scout-meilisearch
```

# 配置
## 在 `config/autoload/scout.php` 中增加配置：

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
            'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'), // 你的Meilisearch 地址
            'key' => env('MEILISEARCH_KEY', null), // 你的Meilisearch key
            'index-settings' => [
                Article::class => [
                    'filterableAttributes' => ['id', 'type', 'created_at'], // 筛选字段(自定义)
                    'sortableAttributes' => ['id', 'sort', 'created_at'],   // 排序字段(自定义)
//                    'searchableAttributes' => [], // 搜索字段(默认全部字段可搜索，若需要指定可配置)
                ],
            ]
        ],
    ],
];
```

## 在 `.env` 文件中添加
```shell
SCOUT_ENGINE=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=xxxxxxxxxxxxxxxxx
```
## 在 `Model` 中增加 `Scout` 配置
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

**注意事项**
`getScoutKeyName` 方法一定要添加，因为 hyperf 默认的 `getScoutKeyName` 方法如下：
```php
// \Hyperf\Database\Model\Model::getQualifiedKeyName

/**
 * 获取用于索引模型的 Scout 索引名称.
 *
 * @return mixed
 */
public function getScoutKeyName()
{
    return $this->getQualifiedKeyName();
}

// \Hyperf\Database\Model\Model::getQualifiedKeyName
/**
 * 获取标准的索引字段名称
 *
 * @return string
 */
public function getQualifiedKeyName()
{
    return $this->qualifyColumn($this->getKeyName());
}

// \Hyperf\Database\Model\Model::qualifyColumn
/**
 * 通过模型的表名设置给定的列名.
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
而 `meilisearch` 不支持适用点号（`.`）作为主键字段，请自行修改 `getScoutKeyName` 方法。
 
# 使用
## 同步搜索模型内容
```shell
php bin/hyperf.php scout:import "App\Model\CrawleSanyaService"
```

## 删除搜索模型内容
```shell
php bin/hyperf.php scout:flush "App\Model\CrawleSanyaService"
```

## 创建索引
```shell
php bin/hyperf.php meilisearch:index {name : The name of the index} {--k|key= : The name of the primary key}'
```

## 删除索引
```shell
php bin/hyperf.php meilisearch:delete-index {name : The name of the index}
```

## 同步索引设置
```shell
php bin/hyperf.php meilisearch:sync-index-settings {name : The name of the index} {--k|key= : The name of the primary key}'
```

## 具体 `Scout` 使用
请参考 hyperf scout 官方文档：[https://hyperf.wiki/3.1/#/zh-cn/scout](https://hyperf.wiki/3.1/#/zh-cn/scout)

# 注意事项
- 主键必须是字符串，避免大整数丢失精度
- 索引的 primary key 只能设置一次
- 添加文档时不要使用点号（.）作为主键字段

# 由 JetBrains 支持的项目
非常感谢 JetBrains 向我提供了执照，可以从事该项目和其他开源项目。

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/liangguifeng)

# License
MIT
