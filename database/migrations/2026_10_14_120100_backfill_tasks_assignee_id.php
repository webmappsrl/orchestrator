<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Assegnatario dei task esistenti con la stessa regola del default
 * (Task::defaultAssigneeId(), oc:8722): owner della Quote se ha un ruolo
 * che accede ai task, altrimenti il creatore del task. I task senza owner
 * ammesso e senza creatore restano senza assegnatario.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ruoli che accedono ai task al momento della migrazione
        // (TaskPolicy::ROLES), scritti per esteso: la migration deve dare lo
        // stesso risultato anche se in futuro la lista cambia.
        $roles = ['admin', 'manager', 'developer'];
        $placeholders = implode(', ', array_fill(0, count($roles), '?'));

        DB::update(
            "UPDATE tasks SET assignee_id = COALESCE(
                (SELECT u.id FROM quotes q JOIN users u ON u.id = q.user_id
                 WHERE q.id = tasks.quote_id
                   AND jsonb_exists_any(u.roles::jsonb, ARRAY[{$placeholders}]::text[])),
                tasks.creator_id
            )
            WHERE assignee_id IS NULL",
            $roles
        );
    }

    public function down(): void
    {
        // La colonna la toglie la migration add_assignee_id_to_tasks_table.
    }
};
