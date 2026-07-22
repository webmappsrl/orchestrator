<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class QuotePolicyTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function admin_non_puo_aggiornare_un_quote_chiuso_vinto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $this->assertFalse($admin->can('update', $quote));
    }

    /** @test */
    public function admin_non_puo_aggiornare_un_quote_chiuso_perso(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Lost->value]);

        $this->assertFalse($admin->can('update', $quote));
    }

    /** @test */
    public function admin_puo_aggiornare_un_quote_aperto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->assertTrue($admin->can('update', $quote));
    }

    /** @test */
    public function admin_non_puo_eliminare_un_quote_chiuso_vinto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $this->assertFalse($admin->can('delete', $quote));
    }

    /** @test */
    public function admin_puo_eliminare_un_quote_aperto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->assertTrue($admin->can('delete', $quote));
    }

    /** @test */
    public function customer_non_puo_aggiornare_nessun_quote(): void
    {
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);
        $quote    = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->assertFalse($customer->can('update', $quote));
    }
}
