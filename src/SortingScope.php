<?php

declare(strict_types=1);

namespace Laratips\Sortable;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class SortingScope implements Scope
{
    use Sortable;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function apply(Builder $builder, Model $model, ?array $defaultSortParameters = null): void
    {
        $defaultSortParams = $defaultSortParameters ?? $model->defaultSort ?? null;
        $this->scopeSortable($builder, $defaultSortParams);
    }
}
