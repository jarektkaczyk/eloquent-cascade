<?php

namespace Sofa\EloquentCascade;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ScopeInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\Relation;

class CascadeDeletesExtension implements ScopeInterface
{
    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Eloquent $model)
    {
        $builder->onDelete(function (Builder $builder) {
            $model = $builder->getModel();

            if (count($model->deletesWith())) {
                $deleted = $builder->get()->all();

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
        if (count($builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedDeletedAtColumn();
        } else {
            return $builder->getModel()->getDeletedAtColumn();
        }
    }

    public function remove(Builder $builder, Eloquent $model)
    {
        //
    }
}
