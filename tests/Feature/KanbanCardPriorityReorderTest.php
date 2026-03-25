<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Webmapp\KanbanCard\Http\Controllers\KanbanController;

class KanbanCardPriorityReorderTest extends TestCase
{
    /** @test */
    public function it_updates_priority_sequentially_when_reordering_a_status_column()
    {
        // Seed fake in-memory dataset to avoid real DB writes.
        FakeStoryModel::$data = [
            101 => ['id' => 101, 'status' => 'todo', 'user_id' => 1, 'priority' => 10],
            102 => ['id' => 102, 'status' => 'todo', 'user_id' => 1, 'priority' => 30],
            103 => ['id' => 103, 'status' => 'todo', 'user_id' => 1, 'priority' => 20],
        ];
        FakeStoryModel::$updatedCalls = [];

        // New desired order: 101 -> 103 -> 102.
        $orderedIds = [101, 103, 102];

        // Mock DB::transaction to run closure without touching any DB.
        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });

        $controller = app(KanbanController::class);

        $config = [
            'model' => FakeStoryModel::class,
            'statusField' => 'status',
            'titleField' => 'name',
            'subtitleField' => null,
            'with' => [],
            'displayFields' => [],
            'filterField' => null,
            'allowedFilterFields' => ['user_id'],
            'searchFields' => [],
            'scopeName' => null,
            'statusFilterOverrides' => [],
            'statusColumnLimits' => [],
            'selectOnly' => false,
            'excludedFieldValues' => [],
            'googleCalendarTitleFormat' => false,
            'priorityField' => 'priority',
            'enableIntraColumnReorder' => true,
            'deniedUpdateRoles' => [],
            'allowedUpdateRoles' => [],
        ];

        $reorderRequest = new Request([
            'status' => 'todo',
            'orderedIds' => $orderedIds,
            'config' => $config,
        ]);
        $reorderRequest->setUserResolver(fn () => (object) ['id' => 1]);

        $reorderResponse = $controller->reorder($reorderRequest);
        $this->assertSame(200, $reorderResponse->getStatusCode());
        $this->assertSame(true, $reorderResponse->getData(true)['success']);

        // Expected: priorities are normalized sequentially following orderedIds.
        $this->assertSame(0, FakeStoryModel::$data[$orderedIds[0]]['priority']);
        $this->assertSame(1, FakeStoryModel::$data[$orderedIds[1]]['priority']);
        $this->assertSame(2, FakeStoryModel::$data[$orderedIds[2]]['priority']);
    }
}

/**
 * Minimal in-memory model to unit-test KanbanController::reorder without DB.
 */
class FakeStoryModel
{
    /** @var array<int, array{ id:int, status:string, user_id:int, priority:int }> */
    public static array $data = [];

    /** @var array<int, array{ id:int, priority:int }> */
    public static array $updatedCalls = [];

    public function getKeyName(): string
    {
        return 'id';
    }

    public static function query(): FakeStoryQuery
    {
        return new FakeStoryQuery();
    }
}

/**
 * Minimal fluent query builder that supports the subset used by reorder().
 */
class FakeStoryQuery
{
    private ?string $whereStatus = null;
    private ?array $whereInIds = null;
    private ?int $whereId = null;

    public function where(string $column, $value): self
    {
        if ($column === 'status') $this->whereStatus = (string) $value;
        if ($column === 'id') $this->whereId = (int) $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if ($column === 'id') {
            $this->whereInIds = array_values(array_map(fn ($v) => (int) $v, $values));
        }
        return $this;
    }

    public function pluck(string $column): Collection
    {
        // reorder() calls pluck('id') only after where(status=...) and whereIn(id,...).
        $ids = array_keys(FakeStoryModel::$data);

        $filtered = array_filter($ids, function (int $id) {
            $row = FakeStoryModel::$data[$id] ?? null;
            if (! $row) return false;
            if ($this->whereStatus !== null && $row['status'] !== $this->whereStatus) return false;
            if ($this->whereInIds !== null && ! in_array($id, $this->whereInIds, true)) return false;
            return true;
        });

        $result = array_values(array_map(fn (int $id) => $id, $filtered));

        return collect($result);
    }

    public function update(array $attributes): int
    {
        // reorder() calls update(['priority' => $n]) after where(id = ...).
        if ($this->whereId === null) return 0;

        if (! isset(FakeStoryModel::$data[$this->whereId])) {
            return 0;
        }

        $priority = isset($attributes['priority']) ? (int) $attributes['priority'] : null;
        if ($priority !== null) {
            FakeStoryModel::$data[$this->whereId]['priority'] = $priority;
            FakeStoryModel::$updatedCalls[] = ['id' => $this->whereId, 'priority' => $priority];
        }

        return 1;
    }
}

