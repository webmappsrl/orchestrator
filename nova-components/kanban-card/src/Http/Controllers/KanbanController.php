<?php

namespace Webmapp\KanbanCard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KanbanController extends Controller
{
    /** Default items per column (first load) when not set in card config. */
    public const LIMIT_PER_COLUMN = 50;

    /** Items to load per "load more" request. */
    public const LOAD_MORE_STEP = 10;

    /**
     * Get items for the kanban board. Initial load: up to LIMIT_PER_COLUMN per status.
     * Load more: pass singleStatus + offset + limit to get next chunk for one column.
     */
    public function items(Request $request): JsonResponse
    {
        $config = $this->getConfigFromRequest($request);

        if (!$config) {
            return response()->json(['error' => __('Invalid configuration')], 400);
        }

        $modelClass = $config['model'];
        $statusField = $config['statusField'];
        $titleField = $config['titleField'];
        $subtitleField = $config['subtitleField'] ?? null;
        $withRelations = $config['with'] ?? [];
        $displayFields = $config['displayFields'] ?? [];
        $filterField = $config['filterField'] ?? null;
        $allowedFilterFields = $config['allowedFilterFields'] ?? [];
        $searchFields = $config['searchFields'] ?? [];
        $scopeName = $config['scopeName'] ?? null;
        $limitPerColumn = (int) ($config['limitPerColumn'] ?? self::LIMIT_PER_COLUMN);
        $limitPerColumn = max(1, min(500, $limitPerColumn));

        if (!class_exists($modelClass)) {
            return response()->json(['error' => __('Model not found')], 404);
        }

        $statuses = $request->input('statuses');
        $statusValues = $statuses ? (is_string($statuses) ? explode(',', $statuses) : $statuses) : [];

        $singleStatus = $request->input('singleStatus');
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', self::LOAD_MORE_STEP);

        $mapItem = function ($item) use ($titleField, $subtitleField, $statusField, $displayFields) {
            $data = [
                'id' => $item->id,
                'title' => data_get($item, $titleField) ?? '—',
                'status' => $item->{$statusField},
            ];
            if ($subtitleField) {
                $data['subtitle'] = data_get($item, $subtitleField);
            }
            if (!empty($displayFields)) {
                $data['fields'] = [];
                foreach ($displayFields as $key => $label) {
                    $data['fields'][] = [
                        'label' => $label,
                        'value' => data_get($item, $key) ?? '—',
                    ];
                }
            }
            return $data;
        };

        // Load more: single column, offset + limit
        if ($singleStatus !== null && $singleStatus !== '') {
            $query = $this->buildItemsQuery($modelClass, $withRelations, $filterField, $allowedFilterFields, $searchFields, $scopeName, $request);
            $query->where($statusField, $singleStatus);
            $query->orderBy((new $modelClass)->getKeyName());
            $items = $query->offset($offset)->limit(max(1, min(50, $limit)))->get();
            return response()->json($items->map($mapItem)->values()->all());
        }

        // Initial load: up to limitPerColumn per status
        $allItems = [];
        foreach ($statusValues as $statusValue) {
            $query = $this->buildItemsQuery($modelClass, $withRelations, $filterField, $allowedFilterFields, $searchFields, $scopeName, $request);
            $query->where($statusField, $statusValue);
            $query->orderBy((new $modelClass)->getKeyName());
            $chunk = $query->limit($limitPerColumn)->get();
            foreach ($chunk as $item) {
                $allItems[] = $mapItem($item);
            }
        }

        return response()->json($allItems);
    }

    /**
     * Build base query for items (with, filter, search, optional scope). Does not apply status.
     */
    protected function buildItemsQuery(
        string $modelClass,
        array $withRelations,
        ?string $filterField,
        array $allowedFilterFields,
        array $searchFields,
        ?string $scopeName,
        Request $request
    ) {
        $query = $modelClass::query();

        $model = new $modelClass;
        $scopeMethod = 'scope' . Str::studly($scopeName);
        if ($scopeName !== null && $scopeName !== '' && method_exists($model, $scopeMethod)) {
            $query->{$scopeName}();
        }

        if (!empty($withRelations)) {
            $query->with($withRelations);
        }

        $reqFilterField = $request->input('filterField');
        $reqFilterValue = $request->input('filterValue');
        if (!empty($allowedFilterFields) && $reqFilterField !== null && $reqFilterValue !== '' && in_array($reqFilterField, $allowedFilterFields, true)) {
            $query->where($reqFilterField, $reqFilterValue);
        } elseif ($filterField && $request->has($filterField) && $request->input($filterField) !== '') {
            $query->where($filterField, $request->input($filterField));
        }

        $search = $request->input('search');
        if ($search !== null && $search !== '' && !empty($searchFields)) {
            $searchPattern = '%' . $search . '%';
            $query->where(function ($q) use ($searchPattern, $searchFields) {
                foreach ($searchFields as $field) {
                    if (str_contains($field, '.')) {
                        $parts = explode('.', $field, 2);
                        $relation = $parts[0];
                        $column = $parts[1];
                        if (preg_match('/^[a-zA-Z0-9_]+$/', $relation) && preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                            $q->orWhereHas($relation, function ($sub) use ($column, $searchPattern) {
                                $sub->whereRaw($column . '::text ILIKE ?', [$searchPattern]);
                            });
                        }
                    } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                        $q->orWhereRaw($field . '::text ILIKE ?', [$searchPattern]);
                    }
                }
            });
        }

        return $query;
    }

    /**
     * Update the status of an item (called after drag & drop).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $config = $this->getConfigFromRequest($request);

        if (!$config) {
            return response()->json(['error' => __('Invalid configuration')], 400);
        }

        $modelClass = $config['model'];
        $statusField = $config['statusField'];

        if (!class_exists($modelClass)) {
            return response()->json(['error' => __('Model not found')], 404);
        }


        $newStatus = $request->input('status');

        if (!$newStatus) {
            return response()->json(['error' => __('Status is required')], 422);
        }

        $item = $modelClass::findOrFail($id);
        $item->{$statusField} = $newStatus;
        $item->save();

        Log::info("Kanban: updated {$modelClass} #{$id} status to '{$newStatus}'");

        return response()->json([
            'success' => true,
            'id' => $item->id,
            'status' => $item->{$statusField},
        ]);
    }

    /**
     * Read config from request (query or body). Routes are already protected by auth.
     */
    protected function getConfigFromRequest(Request $request): ?array
    {
        $config = $request->input('config');
        if (is_string($config)) {
            $config = json_decode($config, true);
        }
        if (!is_array($config) || empty($config['model']) || empty($config['statusField'])) {
            return null;
        }
        return $config;
    }
}
