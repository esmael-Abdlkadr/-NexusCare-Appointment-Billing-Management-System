<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SiteScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $user = request()->user();

        if (! $user) {
            return;
        }

        if ($user->role === 'administrator') {
            return;
        }

        $siteId = $user->site_id;

        if ($siteId === null) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->qualifyColumn('site_id'), $siteId);
    }
}
