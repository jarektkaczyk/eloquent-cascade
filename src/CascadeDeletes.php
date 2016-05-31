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
        return in_array(SoftDeletes::class, class_uses_recursive(get_class($model)))
                && $model->{$model->getDeletedAtColumn()};
    }
}
