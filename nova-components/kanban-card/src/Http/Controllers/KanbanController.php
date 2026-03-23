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
        $statusFilterOverrides = $config['statusFilterOverrides'] ?? [];
        $statusColumnLimits = $config['statusColumnLimits'] ?? [];
        $excludedFieldValues = $config['excludedFieldValues'] ?? [];
        $googleCalendarTitleFormat = (bool) ($config['googleCalendarTitleFormat'] ?? false);
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

        $mapItem = function ($item) use ($titleField, $subtitleField, $statusField, $displayFields, $googleCalendarTitleFormat) {
            $data = [
                'id' => $item->id,
                'title' => $googleCalendarTitleFormat
                    ? $this->buildGoogleCalendarTitle($item, $titleField, $statusField)
                    : (data_get($item, $titleField) ?? '—'),
                'status' => $item->{$statusField},
            ];
            if ($subtitleField) {
                $data['subtitle'] = data_get($item, $subtitleField);
            }
            if (!empty($displayFields)) {
                $data['fields'] = [];
                foreach ($displayFields as $key => $label) {
                    $rawValue = data_get($item, $key);
                    if (is_array($rawValue)) {
                        $cleanValue = implode(', ', array_map(function ($v) {
                            if (is_string($v)) {
                                return trim(strip_tags($v));
                            }
                            return (string) $v;
                        }, $rawValue));
                    } elseif (is_string($rawValue)) {
                        $cleanValue = trim(strip_tags($rawValue));
                    } elseif ($rawValue === null) {
                        $cleanValue = '—';
                    } else {
                        $cleanValue = (string) $rawValue;
                    }
                    $data['fields'][] = [
                        'key' => $key,
                        'label' => $label,
                        'value' => $cleanValue,
                    ];
                }
            }
            return $data;
        };

        // Load more: single column, offset + limit
        if ($singleStatus !== null && $singleStatus !== '') {
            $query = $this->buildItemsQuery($modelClass, $withRelations, $filterField, $allowedFilterFields, $searchFields, $scopeName, $request, (string) $singleStatus, $statusFilterOverrides, $excludedFieldValues);
            if ($this->isTestedByOthersColumn((string) $singleStatus)) {
                $query->where($statusField, 'tested');
                $this->applyTestedByOthersConstraint($query, $request);
            } else {
                $query->where($statusField, $singleStatus);
            }
            $singleLimit = $this->statusLimitFor((string) $singleStatus, $statusColumnLimits);
            if ($singleLimit !== null) {
                $query->orderByDesc('updated_at')->orderByDesc((new $modelClass)->getKeyName());
                $items = $query->limit($singleLimit)->get();
            } else {
                $query->orderBy((new $modelClass)->getKeyName());
                $items = $query->offset($offset)->limit(max(1, min(50, $limit)))->get();
            }
            return response()->json(
                $items
                    ->map(function ($item) use ($mapItem, $singleStatus) {
                        $mapped = $mapItem($item);
                        if ($this->isTestedByOthersColumn((string) $singleStatus)) {
                            $mapped['status'] = 'tested_by_others';
                        }

                        return $mapped;
                    })
                    ->values()
                    ->all()
            );
        }

        // Initial load: up to limitPerColumn per status
        $allItems = [];
        foreach ($statusValues as $statusValue) {
            $query = $this->buildItemsQuery($modelClass, $withRelations, $filterField, $allowedFilterFields, $searchFields, $scopeName, $request, (string) $statusValue, $statusFilterOverrides, $excludedFieldValues);
            if ($this->isTestedByOthersColumn((string) $statusValue)) {
                $query->where($statusField, 'tested');
                $this->applyTestedByOthersConstraint($query, $request);
            } else {
                $query->where($statusField, $statusValue);
            }
            $statusLimit = $this->statusLimitFor((string) $statusValue, $statusColumnLimits);
            if ($statusLimit !== null) {
                $query->orderByDesc('updated_at')->orderByDesc((new $modelClass)->getKeyName());
                $chunk = $query->limit($statusLimit)->get();
            } else {
                $query->orderBy((new $modelClass)->getKeyName());
                $chunk = $query->limit($limitPerColumn)->get();
            }
            foreach ($chunk as $item) {
                $mapped = $mapItem($item);
                if ($this->isTestedByOthersColumn((string) $statusValue)) {
                    $mapped['status'] = 'tested_by_others';
                }
                $allItems[] = $mapped;
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
        Request $request,
        ?string $statusValue = null,
        array $statusFilterOverrides = [],
        array $excludedFieldValues = []
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

        if (! empty($excludedFieldValues) && is_array($excludedFieldValues)) {
            foreach ($excludedFieldValues as $field => $values) {
                if (! is_string($field) || ! preg_match('/^[a-zA-Z0-9_]+$/', $field) || ! is_array($values)) {
                    continue;
                }
                $filteredValues = array_values(array_filter($values, fn ($v) => $v !== null && $v !== ''));
                if (! empty($filteredValues)) {
                    $query->whereNotIn($field, $filteredValues);
                }
            }
        }

        $reqFilterField = $request->input('filterField');
        $reqFilterValue = $request->input('filterValue');
        $overrideFields = [];
        if (
            $statusValue !== null &&
            $statusValue !== '' &&
            is_array($statusFilterOverrides) &&
            isset($statusFilterOverrides[$statusValue])
        ) {
            $override = $statusFilterOverrides[$statusValue];
            if (is_string($override) && preg_match('/^[a-zA-Z0-9_]+$/', $override)) {
                $overrideFields = [$override];
            } elseif (is_array($override)) {
                $overrideFields = array_values(array_filter(
                    $override,
                    fn ($f) => is_string($f) && preg_match('/^[a-zA-Z0-9_]+$/', $f)
                ));
            }
        }

        if ($reqFilterField !== null && $reqFilterValue !== '') {
            if (! empty($overrideFields)) {
                if (count($overrideFields) === 1) {
                    $query->where($overrideFields[0], $reqFilterValue);
                } else {
                    $query->where(function ($q) use ($overrideFields, $reqFilterValue) {
                        foreach ($overrideFields as $idx => $field) {
                            if ($idx === 0) {
                                $q->where($field, $reqFilterValue);
                            } else {
                                $q->orWhere($field, $reqFilterValue);
                            }
                        }
                    });
                }
            } elseif (!empty($allowedFilterFields) && in_array($reqFilterField, $allowedFilterFields, true)) {
                $query->where($reqFilterField, $reqFilterValue);
            }
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
        $deniedRoles = $config['deniedUpdateRoles'] ?? [];
        $bypassRoles = $config['allowedUpdateRoles'] ?? [];

        if (!class_exists($modelClass)) {
            return response()->json(['error' => __('Model not found')], 404);
        }

        $user = $request->user();
        if ($user) {
            $hasBypass = false;
            if (!empty($bypassRoles)) {
                foreach ($bypassRoles as $role) {
                    if ($this->userHasRoleValue($user, $role)) {
                        $hasBypass = true;
                        break;
                    }
                }
            }
            if (!$hasBypass && !empty($deniedRoles)) {
                foreach ($deniedRoles as $role) {
                    if ($this->userHasRoleValue($user, $role)) {
                        return response()->json(['error' => __('Forbidden')], 403);
                    }
                }
            }
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
     * Check if user has a role by value (string) without calling hasRole(). Reads $user->roles when present.
     */
    protected function userHasRoleValue(object $user, string $roleValue): bool
    {
        if (! property_exists($user, 'roles')) {
            return false;
        }
        $roles = $user->roles;
        if (! is_iterable($roles)) {
            return false;
        }
        foreach ($roles as $r) {
            $v = $r instanceof \BackedEnum ? $r->value : (string) $r;
            if ($v === $roleValue) {
                return true;
            }
        }
        return false;
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

    /**
     * Get per-status visible limit when configured.
     */
    protected function statusLimitFor(string $statusValue, array $statusColumnLimits): ?int
    {
        if (! isset($statusColumnLimits[$statusValue])) {
            return null;
        }
        $n = (int) $statusColumnLimits[$statusValue];

        return $n > 0 ? min(500, $n) : null;
    }

    /**
     * Build title like calendar naming without technical prefixes:
     * {id}[SURNAME/INITIAL] {story_name}
     */
    protected function buildGoogleCalendarTitle(object $item, string $titleField, string $statusField): string
    {
        $id = (string) data_get($item, 'id', '');
        $name = (string) (data_get($item, $titleField) ?? '—');
        $creatorName = (string) (data_get($item, 'creator.name') ?? '');
        $creatorRoles = data_get($item, 'creator.roles');

        $creatorTag = '';
        if ($creatorName !== '') {
            $parts = preg_split('/\s+/', trim($creatorName)) ?: [];
            $isDeveloper = false;
            if (is_iterable($creatorRoles)) {
                foreach ($creatorRoles as $role) {
                    $v = $role instanceof \BackedEnum ? $role->value : (string) $role;
                    if ($v === 'developer') {
                        $isDeveloper = true;
                        break;
                    }
                }
            }
            if ($isDeveloper) {
                $source = $parts[1] ?? ($parts[0] ?? '');
                $creatorTag = strtoupper(substr($source, 0, 3));
            } else {
                $last = end($parts);
                $creatorTag = strtoupper((string) ($last === false ? '' : $last));
            }
        }

        $creatorChunk = $creatorTag !== '' ? '[' . $creatorTag . ']' : '';

        return $id . $creatorChunk . ' ' . $name;
    }

    /**
     * Virtual column used by Kanban dashboard:
     * tested stories where tester is different from current viewer.
     */
    protected function isTestedByOthersColumn(string $statusValue): bool
    {
        return $statusValue === 'tested_by_others';
    }

    /**
     * Apply constraint for virtual "tested_by_others" column.
     */
    protected function applyTestedByOthersConstraint($query, Request $request): void
    {
        $selectedViewerId = (string) $request->input('filterValue', '');
        if ($selectedViewerId === '' && $request->user()) {
            $selectedViewerId = (string) $request->user()->id;
        }

        if ($selectedViewerId !== '') {
            $query->whereNotNull('tester_id')->where('tester_id', '!=', $selectedViewerId);
        } else {
            $query->whereNotNull('tester_id');
        }
    }
}
