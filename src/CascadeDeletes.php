<?php

namespace Sofa\EloquentCascade;

use Illuminate\Database\Eloquent\SoftDeletes;

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
            if ($relations = $model->deletesWith()) {
                $action = self::wasSoftDeleted($model) ? 'delete' : 'forceDelete';

                foreach ($relations as $relation) {
                    $model->{$relation}()->{$action}();
                }
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
            foreach ($model->deletesWith() as $relation) {
                $model->{$relation}()->onlyTrashed()->get()->each(function ($related) {
                    $related->restore();
                });
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
        return in_array(SoftDeletes::class, class_uses_recursive(self::class));
    }
}
