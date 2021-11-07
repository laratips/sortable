<?php

declare(strict_types=1);

namespace Laratips\Sortable;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SortingScope implements Scope
{
    use Sortable;

    /**
     * @throws Exception
     */
    public function apply(Builder $builder, Model $model, ?array $defaultSortParameters = null): void
    {
        $defaultSortParams = $defaultSortParameters ?? $model->defaultSort ?? null;
        $this->scopeSortable($builder, $defaultSortParams);
    }
}
