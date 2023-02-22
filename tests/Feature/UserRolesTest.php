<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserRolesTest extends TestCase
{

    /**
     * @test
     *  */
    public function no_roles_always_false() {
        $u = $this->u(0,0,0,0);
        $this->assertFalse($u->hasRole(UserRole::Admin));
        $this->assertFalse($u->hasRole(UserRole::Developer));
        $this->assertFalse($u->hasRole(UserRole::Manager));
        $this->assertFalse($u->hasRole(UserRole::Editor));
    }

    /**
     * @test
     *  */
    public function has_role_admin_true() {
        $this->assertTrue($this->u(1,0,0,0)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,1,0,0)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,0,1,0)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,0,0,1)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,1,1,0)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,1,0,1)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,0,1,1)->hasRole(UserRole::Admin));
        $this->assertTrue($this->u(1,1,1,1)->hasRole(UserRole::Admin));
    }

    /**
     * @test
     *  */
    public function has_role_admin_false() {
        $this->assertFalse($this->u(0,0,0,0)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,1,0,0)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,0,1,0)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,0,0,1)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,1,1,0)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,1,0,1)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,0,1,1)->hasRole(UserRole::Admin));
        $this->assertFalse($this->u(0,1,1,1)->hasRole(UserRole::Admin));
    }

    /**
     * @test
     *  */
    public function has_role_developer_true() {
        $this->assertTrue($this->u(0,1,0,0)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(1,1,0,0)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(0,1,1,0)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(0,1,0,1)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(0,1,1,0)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(1,1,0,1)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(0,1,1,1)->hasRole(UserRole::Developer));
        $this->assertTrue($this->u(1,1,1,1)->hasRole(UserRole::Developer));
    }

    /**
     * @test
     *  */
    public function has_role_developer_false() {
        $this->assertFalse($this->u(0,0,0,0)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(1,0,0,0)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(0,0,1,0)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(0,0,0,1)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(0,0,1,0)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(1,0,0,1)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(0,0,1,1)->hasRole(UserRole::Developer));
        $this->assertFalse($this->u(1,0,1,1)->hasRole(UserRole::Developer));
    }

    /**
     * Simplified user creator with proper roles
     *
     * @param integer $a Set as 1 do add admin role, 0 otherwise
     * @param integer $d Set as 1 do add developer role, 0 otherwise
     * @param integer $m Set as 1 do add manager role, 0 otherwise
     * @param integer $e Set as 1 do add editor role, 0 otherwise
     * @return User
     */
    private function u(int $a,int $d,int $m,int $e): User {
        $roles=[];
        if($a==1) $roles[]=UserRole::Admin;
        if($d==1) $roles[]=UserRole::Developer;
        if($m==1) $roles[]=UserRole::Manager;
        if($e==1) $roles[]=UserRole::Editor;
        $u=User::factory()->create(['roles'=>$roles]);
        return $u;
    }
}
