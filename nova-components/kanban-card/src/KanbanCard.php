<?php

namespace Webmapp\KanbanCard;

use Illuminate\Support\Str;
use Laravel\Nova\Card;

class KanbanCard extends Card
{
    /** Default color for columns when not provided (reusable in general). */
    public const DEFAULT_COLOR = '#9CA3AF';

    /**
     * The width of the card (1/3, 1/2, full, etc.).
     */
    public $width = 'full';

    /**
     * The Eloquent model class to query.
     */
    public string $modelClass = '';

    /**
     * The database column that holds the status value.
     */
    public string $statusField = 'status';

    /**
     * The model field to display as card title.
     */
    public string $titleField = 'name';

    /**
     * Optional model field to display as card subtitle.
     */
    public ?string $subtitleField = null;

    /**
     * The Nova resource URI for linking to detail pages.
     */
    public ?string $resourceUri = null;

    /**
     * Columns definition: array of ['value' => ..., 'label' => ..., 'color' => ...].
     */
    public array $columnsConfig = [];

    /**
     * Column values to exclude from the board (e.g. ['cold', 'closed lost']).
     * Excluded columns are not shown and their items are not requested.
     *
     * @var array<string>
     */
    public array $excludedColumns = [];

    /**
     * Extra fields to display on each kanban item.
     * Format: ['relation.field' => 'Label'] or ['field' => 'Label'].
     */
    public array $displayFields = [];

    /**
     * Relations to eager-load when querying items.
     */
    public array $withRelations = [];

    /**
     * Optional filter: field name to filter by (e.g. 'customer_id').
     */
    public ?string $filterField = null;

    /**
     * Filter dropdown options: [['value' => id, 'label' => name, 'filterField' => optional], ...].
     * When addFilterOptions is used, each option has filterField so one dropdown can filter by different fields (e.g. customer_id or user_id).
     */
    public array $filterOptions = [];

    /**
     * When using addFilterOptions, list of allowed filter field names for request validation.
     *
     * @var array<string>
     */
    public array $allowedFilterFields = [];

    /**
     * Initial value for the filter (e.g. pre-select current user on Kanban dashboard).
     * When set, the filter is applied on first load and the combobox shows the matching option label.
     */
    public mixed $initialFilterValue = null;

    /**
     * Set the initial filter value so the card loads with this filter applied (e.g. current user id).
     */
    public function initialFilterValue(mixed $value): self
    {
        $this->initialFilterValue = $value;

        return $this;
    }

    /**
     * Fields to search in (e.g. ['name', 'title']). When set, search box is shown.
     */
    public array $searchFields = [];

    /**
     * Optional scope/filter closure key.
     */
    public ?string $scopeName = null;

    /**
     * Max items per column on first load. When a column has this many, "load more" is shown.
     */
    public int $limitPerColumn = 50;

    /**
     * When true, show a header with collapse/expand toggle (like menu items).
     * When false (default), the card is always open and no toggle is shown.
     */
    public bool $collapsible = false;

    /**
     * Role values (string) for user types that cannot update item status (drag & drop).
     * When empty, any Nova user can update. When set, users who have any of these roles cannot update.
     * Set via ->deniedToUpdateStatusForRoles([UserRole::Editor, UserRole::Developer]) (enum or string).
     *
     * @var array<string>
     */
    public array $deniedUpdateRoles = [];

    /**
     * Role values (string) for users who can update even if they have a denied role (bypass).
     *
     * @var array<string>
     */
    public array $allowedUpdateRoles = [];

    /**
     * Exclude from status update (drag & drop) users who have any of these roles.
     * Pass enum (e.g. UserRole::Editor) or strings; no Gate required.
     *
     * @param  array<\BackedEnum|string>  $roles
     */
    public function deniedToUpdateStatusForRoles(array $roles): self
    {
        $this->deniedUpdateRoles = array_values(array_map(
            fn ($r) => $r instanceof \BackedEnum ? $r->value : (string) $r,
            array_filter($roles, fn ($r) => $r !== null && $r !== '')
        ));

        return $this;
    }

    /**
     * Roles that bypass the denied list: if the user has any of these, they can update even when they have a denied role.
     *
     * @param  array<\BackedEnum|string>  $roles
     */
    public function allowedToUpdateStatusForRoles(array $roles): self
    {
        $this->allowedUpdateRoles = array_values(array_map(
            fn ($r) => $r instanceof \BackedEnum ? $r->value : (string) $r,
            array_filter($roles, fn ($r) => $r !== null && $r !== '')
        ));

        return $this;
    }

    /**
     * Set max items per column on first load (default 50). Use a lower value (e.g. 5) for testing "load more".
     */
    public function limitPerColumn(int $value): self
    {
        $this->limitPerColumn = max(1, $value);

        return $this;
    }

    /**
     * Enable collapse/expand: when true, a header with toggle button is shown.
     * When false or not set, the card is always expanded and the toggle is hidden.
     */
    public function collapsible(bool $value = true): self
    {
        $this->collapsible = $value;

        return $this;
    }

    /**
     * The component name registered in Nova.
     */
    public function component()
    {
        return 'kanban-card';
    }

    /**
     * Apply a named scope on the model query (e.g. 'customerStories' calls scopeCustomerStories on the model).
     */
    public function scope(string $name): self
    {
        $this->scopeName = $name;

        return $this;
    }

    /**
     * Set the Eloquent model and status field.
     */
    public function model(string $modelClass, string $statusField = 'status'): self
    {
        $this->modelClass = $modelClass;
        $this->statusField = $statusField;

        return $this;
    }

    /**
     * Set the field used as item title.
     */
    public function title(string $field): self
    {
        $this->titleField = $field;

        return $this;
    }

    /**
     * Set the field used as item subtitle.
     */
    public function subtitle(string $field): self
    {
        $this->subtitleField = $field;

        return $this;
    }

    /**
     * Set the Nova resource URI for detail links (e.g. 'quotes').
     */
    public function resourceUri(string $uri): self
    {
        $this->resourceUri = $uri;

        return $this;
    }

    /**
     * Define the kanban columns.
     *
     * Accepts either:
     * - Associative array: ['status_value' => 'Label', ...]
     * - Array of arrays: [['value' => '...', 'label' => '...', 'color' => '...'], ...]
     */
    public function columns(array $columns): self
    {
        $this->columnsConfig = $this->normalizeColumns($columns);

        return $this;
    }

    /**
     * Exclude one or more columns by value (e.g. ['cold', 'closed lost']).
     * Excluded columns are not shown and their items are not loaded.
     *
     * @param  array<string>  $values
     */
    public function excludedColumns(array $values): self
    {
        $this->excludedColumns = array_values(array_filter($values, 'is_string'));

        return $this;
    }

    /**
     * Set extra fields to display on each item card.
     * Format: ['relation.field' => 'Label'] or ['field' => 'Label'].
     */
    public function displayFields(array $fields): self
    {
        $this->displayFields = $fields;

        return $this;
    }

    /**
     * Set relations to eager-load.
     */
    public function with(array $relations): self
    {
        $this->withRelations = $relations;

        return $this;
    }

    /**
     * Enable a filter dropdown by field. Options are loaded from the given model.
     *
     * @param  string  $field  The model attribute to filter by (e.g. 'customer_id').
     * @param  string  $modelClass  Eloquent model class to load options from (e.g. Customer::class).
     * @param  string  $labelField  Model attribute to show as option label (e.g. 'name').
     * @param  callable|null  $queryCallback  Optional: receive the query builder, return it after applying scope (e.g. only developers).
     */
    public function filterBy(string $field, string $modelClass, string $labelField = 'name', ?callable $queryCallback = null): self
    {
        $this->filterField = $field;

        if (! class_exists($modelClass)) {
            $this->filterOptions = [];
            return $this;
        }

        $query = $modelClass::query();
        if ($queryCallback !== null) {
            $query = $queryCallback($query);
        }
        $this->filterOptions = $query
            ->orderBy($labelField)
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->getKey(),
                'label' => (string) data_get($row, $labelField),
                'filterField' => $field,
            ])
            ->values()
            ->toArray();
        $this->allowedFilterFields = array_unique(array_merge($this->allowedFilterFields, [$field]));

        return $this;
    }

    /**
     * Add more filter options to the same dropdown (e.g. owners alongside customers).
     * Each option will filter by $field. The dropdown will show all sources; selecting one applies that filter.
     *
     * @param  string  $field  Model attribute to filter by (e.g. 'user_id').
     * @param  string  $modelClass  Eloquent model class for options (e.g. User::class).
     * @param  string  $labelField  Model attribute used as option label (e.g. 'name').
     * @param  callable|null  $queryCallback  Optional: scope the query for options (e.g. only users who have quotes).
     */
    public function addFilterOptions(string $field, string $modelClass, string $labelField = 'name', ?callable $queryCallback = null): self
    {
        if (! class_exists($modelClass)) {
            return $this;
        }

        $query = $modelClass::query();
        if ($queryCallback !== null) {
            $query = $queryCallback($query);
        }
        $options = $query
            ->orderBy($labelField)
            ->get()
            ->map(fn ($row) => [
                'value' => (string) $row->getKey(),
                'label' => (string) data_get($row, $labelField),
                'filterField' => $field,
            ])
            ->values()
            ->toArray();

        $this->filterOptions = array_merge($this->filterOptions, $options);
        $this->allowedFilterFields = array_unique(array_merge($this->allowedFilterFields, [$field]));

        return $this;
    }

    /**
     * Enable search box. Items are filtered by LIKE on the given model attributes.
     *
     * @param  string|array  $fields  One or more attribute names (e.g. 'name' or ['name', 'title']).
     */
    public function searchBy($fields): self
    {
        $this->searchFields = is_array($fields) ? $fields : [$fields];

        return $this;
    }

    /**
     * Unified filter + search: one UI field for both filter (by field/model) and search (by fields).
     * Optionally add more filter sources (e.g. owners) so the same dropdown lists customers and owners.
     *
     * @param  string  $filterField  Model attribute to filter by (e.g. 'customer_id').
     * @param  string  $modelClass  Eloquent model class for filter options (e.g. Customer::class).
     * @param  string  $labelField  Model attribute used as option label (e.g. 'name').
     * @param  string|array  $searchFields  Model attributes to search in (e.g. ['customer.name', 'title']).
     * @param  callable|null  $queryCallback  Optional: receive the query builder for filter options, return it after applying scope.
     * @param  array  $additionalFilters  Optional: extra filter sources for the same dropdown. Each item: [field, modelClass, labelField, callable|null]. E.g. [['user_id', User::class, 'name', fn ($q) => $q->whereHas('quotes')]].
     */
    public function filterAndSearchBy(string $filterField, string $modelClass, string $labelField, $searchFields, ?callable $queryCallback = null, array $additionalFilters = []): self
    {
        $this->filterBy($filterField, $modelClass, $labelField, $queryCallback);
        foreach ($additionalFilters as $add) {
            $addField = $add[0];
            $addModel = $add[1];
            $addLabel = $add[2] ?? 'name';
            $addCallback = $add[3] ?? null;
            $this->addFilterOptions($addField, $addModel, $addLabel, $addCallback);
        }
        $this->searchBy($searchFields);

        return $this;
    }

    /**
     * Normalize columns to a consistent format.
     * When color is not provided or is empty, DEFAULT_COLOR is used.
     */
    protected function normalizeColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $normalized[] = [
                    'value' => $value['value'],
                    'label' => $value['label'] ?? ucfirst($value['value']),
                    'color' => ! empty($value['color']) ? $value['color'] : self::DEFAULT_COLOR,
                ];
            } else {
                $normalized[] = [
                    'value' => $key,
                    'label' => $value,
                    'color' => self::DEFAULT_COLOR,
                ];
            }
        }

        return $normalized;
    }

    /**
     * Config for the API (routes are already protected by auth). Returns array to send in request.
     */
    public function getApiConfig(): array
    {
        return [
            'model' => $this->modelClass,
            'statusField' => $this->statusField,
            'titleField' => $this->titleField,
            'subtitleField' => $this->subtitleField,
            'with' => $this->withRelations,
            'displayFields' => $this->displayFields,
            'filterField' => $this->filterField,
            'allowedFilterFields' => $this->allowedFilterFields,
            'searchFields' => $this->searchFields,
            'limitPerColumn' => $this->limitPerColumn,
            'deniedUpdateRoles' => $this->deniedUpdateRoles,
            'allowedUpdateRoles' => $this->allowedUpdateRoles,
            'scopeName' => $this->scopeName,
        ];
    }

    /**
     * Whether the current user is allowed to update item status (drag & drop).
     * If user has any allowedUpdateRoles they can. Else users with any deniedUpdateRoles cannot.
     * Uses user role values (does not call hasRole) so the package works when User::hasRole() only accepts enum.
     */
    public function userCanUpdateStatus(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return true;
        }
        if (! empty($this->allowedUpdateRoles)) {
            foreach ($this->allowedUpdateRoles as $role) {
                if ($this->userHasRoleValue($user, $role)) {
                    return true;
                }
            }
        }
        if (empty($this->deniedUpdateRoles)) {
            return true;
        }
        foreach ($this->deniedUpdateRoles as $role) {
            if ($this->userHasRoleValue($user, $role)) {
                return false;
            }
        }
        return true;
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
     * Serialize the card for the Nova frontend.
     */
    public function jsonSerialize(): array
    {
        $columns = array_values(array_filter(
            $this->columnsConfig,
            fn (array $col) => ! in_array($col['value'] ?? '', $this->excludedColumns, true)
        ));

        return array_merge(parent::jsonSerialize(), [
            'apiConfig' => $this->getApiConfig(),
            'columns' => $columns,
            'resourceUri' => $this->resourceUri,
            'filterField' => $this->filterField,
            'filterOptions' => $this->filterOptions,
            'initialFilterValue' => $this->initialFilterValue !== null && $this->initialFilterValue !== '' ? (string) $this->initialFilterValue : null,
            'searchFields' => $this->searchFields,
            'limitPerColumn' => $this->limitPerColumn,
            'collapsible' => $this->collapsible,
            'canUpdate' => $this->userCanUpdateStatus(),
            'translations' => [
                'loading' => __('Kanban Loading'),
                'noItems' => __('Kanban No items'),
                'errorLoading' => __('Kanban Error loading'),
                'errorUpdating' => __('Kanban Error updating'),
                'statusUpdated' => __('Kanban Status updated'),
                'filterLabel' => __('Kanban Filter Label'),
                'filterAll' => __('Kanban Filter All'),
                'searchPlaceholder' => __('Kanban Search Placeholder'),
                'expand' => __('Kanban Expand'),
                'collapse' => __('Kanban Collapse'),
                'collapseLabel' => __('Kanban Collapse Label'),
                'loadMore' => __('Kanban Load More'),
                'searchOrFilterPlaceholder' => __('Kanban Search Or Filter Placeholder'),
                'filterPlaceholder' => __('Kanban Filter Placeholder'),
                'noFilterMatch' => __('Kanban No Filter Match'),
            ],
        ]);
    }
}
