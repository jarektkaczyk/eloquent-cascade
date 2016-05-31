# Sofa/EloquentCascade

[![Downloads](https://poser.pugx.org/sofa/eloquent-cascade/downloads)](https://packagist.org/packages/sofa/eloquent-cascade) [![stable](https://poser.pugx.org/sofa/eloquent-cascade/v/stable.svg)](https://packagist.org/packages/sofa/eloquent-cascade)

Cascading (soft / hard) deletes for the [Eloquent ORM (Laravel 5.0+)](https://laravel.com/docs/eloquent).

* [simple usage](#simple)
* [using with `SoftDeletes`](#using-with-softdeletes)

## Installation

Package goes along with Laravel (Illuminate) versioning, in order to make it easy for you to pick appropriate version:

Laravel / Illuminate **5.2+**:

```
composer require sofa/eloquent-cascade:"~5.2"
```

Laravel / Illuminate **5.0/5.1**:

```
composer require sofa/eloquent-cascade:"~5.1"
```

## Usage

Use provided `CascadeDeletes` trait in your model and define relation to be deleted in cascade. Related models will be deleted automatically and appropriately, that is either `hard` or `soft` deleted, depending on the related model settings and delete method used:

#### tldr;

1. `$model->delete()` & `$query->delete()` w/o soft deletes
2. when using soft deletes ensure traits order: `use SoftDeletes, CascadeDeletes`
3. `$model->delete()` & `$query->delete()` with soft deletes
4. `$model->forceDelete()` **BUT `$query->forceDelete()` will not work**

#### simple: 

```php
<?php

namespace App;

use Sofa\EloquentCascade\CascadeDeletes;

class Product extends \Illuminate\Database\Eloquent\Model
{
    use CascadeDeletes;

    protected $deletesWith = ['types', 'photos'];

```

* `delete()` called on the model:

    ```php
    root@578687bd11c8:/var/www/html# php artisan tinker
    Psy Shell v0.7.2 (PHP 7.0.3 — cli) by Justin Hileman
    >>> DB::enableQueryLog()            
    => null
    >>> App\Product::find(200)->delete()
    => true
    >>> DB::getQueryLog()
    => [
         [
           "query" => "select * from `products` where `products`.`id` = ? limit 1",
           "bindings" => [200],
         ],
         [
           "query" => "delete from `products` where `id` = ?",
           "bindings" => [200],
         ],
         [
           "query" => "delete from `product_types` where `product_types`.`product_id` = ? and `product_types`.`product_id` is not null",
           "bindings" => [200],
         ],
         [
           "query" => "delete from `photos` where `photos`.`product_id` = ? and `photos`.`product_id` is not null",
           "bindings" => [200],
         ],
       ]

    ```

* `delete()` called on the eloquent query `Builder`:

    ```php
    >>> App\Product::whereIn('id', [202, 203])->delete()
    => 2
    >>> DB::getQueryLog()
    => [
         [
           "query" => "select * from `products` where `id` in (?, ?)",
           "bindings" => [202, 203],
         ],
         [
           "query" => "delete from `product_types` where `product_types`.`product_id` in (?, ?)",
           "bindings" => [202, 203],
         ],
         [
           "query" => "update `photos` set `deleted_at` = ?, `updated_at` = ? where `photos`.`product_id` in (?, ?) and `photos`.`deleted_at` is null",
           "bindings" => [
             "2016-05-31 09:44:41",
             "2016-05-31 09:44:41",
             202,
             203,
           ],
         ],
         [
           "query" => "delete from `products` where `id` in (?, ?)",
           "bindings" => [202, 203],
         ],
       ]

    ```


#### using with `SoftDeletes`

**NOTE** order of using traits matters, so make sure you use `SoftDeletes` before `CascadeDeletes`.

```php
<?php

namespace App;

use Sofa\EloquentCascade\CascadeDeletes;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends \Illuminate\Database\Eloquent\Model
{
    use SoftDeletes, CascadeDeletes;

    // related Photo model uses SoftDeletes as well, but Type does not
    protected $deletesWith = ['types', 'photos'];

```

* cascade with soft deletes - every model using `SoftDeletes` gets soft deleted, others are hard deleted

    ```php
    >>> App\Product::whereIn('id', [300, 301])->delete()
    => 2
    >>> DB::getQueryLog()
    => [
         [
           "query" => "select * from `products` where `id` in (?, ?) and `products`.`deleted_at` is null",
           "bindings" => [300, 301],
         ],
         [
           "query" => "delete from `product_types` where `product_types`.`product_id` in (?, ?)",
           "bindings" => [300, 301],
         ],
         [
           "query" => "update `photos` set `deleted_at` = ?, `updated_at` = ? where `photos`.`product_id` in (?, ?) and `photos`.`deleted_at` is null",
           "bindings" => [
             "2016-05-31 09:52:30",
             "2016-05-31 09:52:30",
             300,
             301,
           ],
         ],
         [
           "query" => "update `products` set `deleted_at` = ?, `updated_at` = ? where `id` in (?, ?) and `products`.`deleted_at` is null",
           "bindings" => [
             "2016-05-31 09:52:30",
             "2016-05-31 09:52:30",
             300,
             301,
           ],
         ],
       ]

    ```


* cascade with `forceDelete()` called on the model will hard-delete all the relations (**NOTE** due to the current implementation of forceDelete in laravel core, it will not work on the Builder)

    ```php
    >>> App\Product::find(302)->forceDelete()
    => true
    >>> DB::getQueryLog()
    => [
         [
           "query" => "select * from `products` where `products`.`id` = ? and `products`.`deleted_at` is null limit 1",
           "bindings" => [302],
         ],
         [
           "query" => "delete from `products` where `id` = ?",
           "bindings" => [302],
         ],
         [
           "query" => "delete from `product_types` where `product_types`.`product_id` = ? and `product_types`.`product_id` is not null",
           "bindings" => [302],
         ],
         [
           "query" => "delete from `photos` where `photos`.`product_id` = ? and `photos`.`product_id` is not null",
           "bindings" => [302],
         ],
       ]

    ```

## TODO

- [ ] cascade `restoring` soft deleted models

## Contribution

All contributions are welcome, PRs must be **PSR-2 compliant**.
