<?php

declare(strict_types=1);

namespace Laratips\Sortable;

use Illuminate\Support\ServiceProvider;

final class SortableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sortable');
    }
}
