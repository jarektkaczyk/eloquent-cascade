<?php

namespace Sofa\EloquentCascade;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * This class provides Eloquent\Builder extensions for Cascading Deletes/Restores.
 *
 * @package sofa/eloquent-cascade
 * @author  Jarek Tkaczyk <jarek@softonsofa.com>
 */
class CascadeDeletesExtension implements Scope
{
    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        $this->registerDeletedHandler($builder);

        if ($this->usesSoftDeletes($builder)) {
            $this->registerRestoredHandler($builder);
        }
    }

    /**
     * Register handler for cascade restores.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @return void
     */
    protected function registerRestoredHandler(Builder $builder)
    {
        // Here we override restore macro in order to add required behaviour.
        $builder->macro('restore', function (Builder $builder) {
            $model = $builder->getModel();

            collect($model->deletesWith())
                ->filter(function ($relation_name) use ($model) {
                    return $this->usesSoftDeletes($model->{$relation_name}());
                })->each(function ($relation_name) use ($builder) {
                    // It is a bit tricky to achieve expected result which is restoring only those children that were
                    // delete along with the parent model (not before). We cannot easily achieve that on the query
                    // level, so we'll simply run N queries here. Should be fine as this is an edge case anyway.
                    $restored_models = $builder->onlyTrashed()->get();

                    foreach ($restored_models as $restored_model) {
                        $relation = $restored_model->{$relation_name}();
                        $related = $relation->getRelated();

                        $parent_deleted_at = $restored_model->getAttribute($restored_model->getDeletedAtColumn());

                        $relation
                            ->where($related->getQualifiedDeletedAtColumn(), '>=', $parent_deleted_at)
                            ->restore();
                    }
                });

            return $builder->update([$builder->getModel()->getDeletedAtColumn() => null]);
        });
    }

    /**
     * Register handler for cascade deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder [description]
     * @return void
     */
    protected function registerDeletedHandler(Builder $builder)
    {
        $builder->onDelete(function (Builder $builder) {
            $model = $builder->getModel();

            if (!empty($model->deletesWith())) {
                $deleted = $builder->get()->all();

                // In order to get relation query with correct constraint applied we have
                // to mimic eager loading 'where KEY in' behaviour rather than default
                // constraints for single model which would be invalid in this case.
                Relation::noConstraints(function () use ($model, $deleted) {
                    foreach ($model->deletesWith() as $relation) {
                        $query = $model->{$relation}();
                        $query->addEagerConstraints($deleted);
                        $query->delete();
                    }
                });
            }

            return $this->performDelete($builder);
        });
    }

    /**
     * Perform delete on the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @return integer
     */
    protected function performDelete($builder)
    {
        if ($this->usesSoftDeletes($builder)) {
            $column = $this->getDeletedAtColumn($builder);

            return $builder->update([
                $column => $builder->getModel()->freshTimestampString(),
            ]);
        }

        return $builder->toBase()->delete();
    }

    /**
     * Determine whether builder soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @return boolean
     */
    protected function usesSoftDeletes($builder)
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive(get_class($builder->getModel()))
        );
    }

    /**
     * Get the "deleted at" column for the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return string
     */
    protected function getDeletedAtColumn($builder)
    {
        if (!empty($builder->getQuery()->joins)) {
            return $builder->getModel()->getQualifiedDeletedAtColumn();
        } else {
            return $builder->getModel()->getDeletedAtColumn();
        }
    }

    /**
     * Nothing here, just to satisfy the interface.
     */
    public function apply(Builder $builder, Model $model)
    {
        //
    }
}
