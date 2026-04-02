<?php

namespace App\Models\Concerns;

use App\Scopes\SiteScope;

trait HasSiteScope
{
    protected static function bootHasSiteScope(): void
    {
        static::addGlobalScope(new SiteScope());
    }
}
