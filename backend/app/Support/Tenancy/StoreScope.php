<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * M4 stage 2: `where <table>.store_id = ?` while a tenant is bound (TenantContext), nothing otherwise. Applied when the
 * query runs, so the store bound at that moment counts. Saving, deleting or refreshing a loaded model does not go
 * through global scopes (Eloquent's newModelQuery / newQueryWithoutScopes): the scope filters reads and mass
 * updates/deletes through the model's query builder only.
 */
final class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = app(TenantContext::class)->storeId();
        if ($storeId !== null) {
            $builder->where($model->qualifyColumn('store_id'), $storeId);
        }
    }
}
