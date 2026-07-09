<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Paginate an Eloquent query into the DRF envelope the frontend expects:
 * { count, next, previous, results }.
 */
class DrfPagination
{
    public static function paginate(Builder $query, Request $request, callable $transform): array
    {
        $pageSize = min((int) $request->query('page_size', 20), 100);
        $paginator = $query->paginate($pageSize)->appends($request->query());

        return [
            'count' => $paginator->total(),
            'next' => $paginator->nextPageUrl(),
            'previous' => $paginator->previousPageUrl(),
            'results' => collect($paginator->items())->map($transform)->values()->all(),
        ];
    }
}
