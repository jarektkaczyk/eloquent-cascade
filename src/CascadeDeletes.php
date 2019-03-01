<?php

namespace Sofa\EloquentCascade;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * This trait provides Cascading Deletes/Restores feature to Eloquent models.
 *
 * *NOTE* If you use SoftDeletes as well, then make sure to use it first, eg.:
 * 'use SoftDeletes, CascadeDeletes;'
 *
 * @package sofa/eloquent-cascade
 * @author  Jarek Tkaczyk <jarek@softonsofa.com>
 */
trait CascadeDeletes
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    protected static function bootCascadeDeletes()
    {
        static::addGlobalScope(new CascadeDeletesExtension);

        static::registerDeletedHandler();

        if (static::usesSoftDeletes()) {
            static::registerRestoredHandler();
        }
    }

    /**
     * Register handler for cascade deletes.
     *
     * @return void
     */
    protected static function registerDeletedHandler()
    {
        static::deleted(function ($model) {
            $action = self::wasSoftDeleted($model) ? 'delete' : 'forceDelete';

            foreach ($model->deletesWith() as $relation) {
                $model->{$relation}()->get()->each(function ($related) use ($action) {
                    $related->{$action}();
                });
            }
        });
    }

    /**
     * Register handler for cascade restores.
     *
     * @return void
     */
    protected static function registerRestoredHandler()
    {
        static::restored(function ($model) {
            foreach ($model->deletesWith() as $relation_name) {
                $relation = $model->{$relation_name}();

                if ($relation->getMacro('onlyTrashed')) {
                    $related = $relation->getRelated();

                    $parent_deleted_at = $model->getAttribute($model->getDeletedAtColumn());

                    $relation->onlyTrashed()
                        // This will ensure we don't restore models that had been deleted before this model
                        ->where($related->getQualifiedDeletedAtColumn(), '>=', $parent_deleted_at)
                        ->get()
                        ->each(function ($related) {
                            $related->restore();
                        });
                }
            }
        });
    }

    /**
     * Get array of relations to delete along with this model.
     *
     * @return array
     */
    public function deletesWith()
    {
        return property_exists($this, 'deletesWith') ? $this->deletesWith : [];
    }

    /**
     * Determine whether the model was soft deleted.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return boolean
     */
    protected static function wasSoftDeleted($model)
    {
        return static::usesSoftDeletes() && $model->{$model->getDeletedAtColumn()};
    }

    /**
     * Determine whether the model uses soft deletes.
     *
     * @return boolean
     */
    protected static function usesSoftDeletes()
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class));
    }
}
