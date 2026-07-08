<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserAccessNovaOverrideTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function orchestrator_users_always_have_access_nova_ability(): void
    {
        Permission::findOrCreate('access-nova');

        $user = User::factory()->create([
            'roles' => [UserRole::Customer],
        ]);

        $this->assertTrue($user->can('access-nova'));
    }
}
