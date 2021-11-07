<?php

declare(strict_types=1);

namespace Laratips\Sortable;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

use function array_filter;
use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function in_array;
use function str_contains;
use function strtolower;
use function substr;

/**
 * Column sorting trait
 *
 * @method static Builder sortable(array|null $defaultSortParameters = null) apply sorting behavior to the model
 */
trait Sortable
{
    /**
     * @throws Exception
     */
    public function scopeSortable(Builder $query, ?array $defaultSortParameters = null): Builder
    {
        $params = Request::only([$this->getSortQueryParameterName(), $this->getDirectionQueryParameterName()]);

        // TODO: map query parameter names to 'sort' and 'direction' which are used in code
        if ($params !== null && count($params) > 0 && isset($params['sort'])) {
            return $this->queryOrderBuilder($query, array_filter($params));
        }

        if ($defaultSortParameters !== null && array_key_exists('sort', $defaultSortParameters)) {
            Request::merge($defaultSortParameters);
            return $this->queryOrderBuilder($query, $defaultSortParameters);
        }

        return $query;
    }

    /**
     * @throws Exception
     */
    private function queryOrderBuilder(Builder $query, array $params): Builder
    {
        $model = $query->getModel();
        $column = $params['sort'];
        $direction = (array_key_exists('direction', $params) && in_array(
                strtolower($params['direction']),
                ['asc', 'desc'],
                true
            ))
            ? $params['direction']
            : $this->getDefaultSortDirection();

        // Check if sort is done for related column
        while (str_contains($column, '.')) {
            $explodedResult = array_filter(explode('.', $column, 2), 'strlen');
            [$relationName, $column] = $explodedResult;

            //try {
            $relation = $model->newQuery()->getRelation($relationName);
            $query = $this->queryJoinBuilder($query, $relation);
            $model = $relation->getRelated();
            //} catch (RelationNotFoundException $e) {
            //
            //} catch (Exception $e) {
            //
            //}
        }

        if ($this->columnExists($model, $column)) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $query = $query->orderBy($model->qualifyColumn($column), $direction);
        } elseif ($this->columnAliasExists($model, $query, $column)) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $query = $query->orderBy($column, $direction);
        }

        return $query;
    }

    /**
     * @throws Exception
     */
    private function queryJoinBuilder(Builder $query, Relation $relation): Builder
    {
        $relatedTable = $relation->getRelated()->getTable();
        $parentTable = $relation->getParent()->getTable();

        if ($parentTable === $relatedTable) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $query = $query->from($parentTable, 'parent_' . $parentTable);
            $parentTable = 'parent_' . $parentTable;
            $relation->getParent()->setTable($parentTable);
        }

        if ($relation instanceof HasOne) {
            $relatedQualifiedKey = $relation->getQualifiedForeignKeyName();
            $parentQualifiedKey = $relation->getQualifiedParentKeyName();
        } elseif ($relation instanceof BelongsTo) {
            $relatedQualifiedKey = $relation->getQualifiedOwnerKeyName();
            $parentQualifiedKey = $relation->getQualifiedForeignKeyName();
        } else {
            // other relation types https://reinink.ca/articles/ordering-database-queries-by-relationship-columns-in-laravel
            throw new Exception('Relation ' . $relation::class . ' is not supported for the sort.');
        }

        // merge relation where constraints if there is any
        if (count($relation->getBaseQuery()->wheres) > 0) {
            $parentQualifiedKey = static function (JoinClause $join) use ($parentQualifiedKey, $relatedQualifiedKey, $relation) {
                $join->on($parentQualifiedKey, '=', $relatedQualifiedKey);
                $join->mergeWheres(
                    array_map(static function (array $where) use ($relation) {
                        $where['column'] = $relation->qualifyColumn($where['column']);
                        return $where;
                    }, $relation->getBaseQuery()->wheres),
                    $relation->getBindings(),
                );
            };
        }

        // qualify column names that might be ambiguous
        $query->toBase()->columns = $relation->getParent()->qualifyColumns($query->toBase()->columns);

        return $this->fromJoin($query, $relatedTable, $parentQualifiedKey, $relatedQualifiedKey);
    }

    /**
     * Builds join on the query based on the join type from configuration
     */
    private function fromJoin(Builder $query, string $relatedTable, string|Closure $parentKey, string $relatedKey): Builder
    {
        $joinType = 'leftJoin';
        // TODO: Read join type from configuration

        return $query->{$joinType}(
            $relatedTable,
            $parentKey,
            '=',
            $relatedKey
        );
    }

    /**
     * Checks if column exists on model, checking sortable property and falling back to DB check
     */
    private function columnExists(Model $model, string $column): bool
    {
        return isset($model->sortable) ? in_array($column, $model->sortable, true)
            : Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
    }

    /**
     * Checks if alias exists on model, checking sortableAliases property and falling back to query check
     */
    private function columnAliasExists(Model $model, Builder $query, string $column): bool
    {
        if (isset($model->sortableAliases)) {
            return in_array($column, $model->sortableAliases, true);
        }

        $aliasedColumns = array_map(
            static function (Expression $expression) {
                $explodedResult = explode('as', $expression->getValue());
                if (count($explodedResult) !== 2) {
                    return $explodedResult[0];
                }

                // remove space and ` surrounding alias name
                return substr($explodedResult[1], 2, -1);
            },
            array_filter($query->toBase()->columns, static fn ($queryColumn) => $queryColumn instanceof Expression)
        );

        return in_array($column, $aliasedColumns, true);
    }

    /**
     * Returns default sort direction from config
     */
    private function getDefaultSortDirection(): string
    {
        // TODO: read parameter name from configuration
        return 'asc';
    }

    /**
     * Returns sort query parameter name from config
     */
    private function getSortQueryParameterName(): string
    {
        // TODO: read parameter name from configuration
        return 'sort';
    }

    /**
     * Returns sort direction query parameter name from config
     */
    private function getDirectionQueryParameterName(): string
    {
        // TODO: read parameter name from configuration
        return 'direction';
    }
}
