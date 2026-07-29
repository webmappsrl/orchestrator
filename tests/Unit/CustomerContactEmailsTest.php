<?php

namespace Tests\Unit;

use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerContactEmailsTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function contact_emails_splitta_su_virgola_e_spazio_come_nova(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'l.bevilacqua@qzrstudio.com,stefan.guerra@lucense.it',
        ]);

        $this->assertEquals(
            ['l.bevilacqua@qzrstudio.com', 'stefan.guerra@lucense.it'],
            $customer->contact_emails
        );
    }

    /** @test */
    public function contact_emails_e_array_vuoto_se_email_e_null(): void
    {
        $customer = Customer::factory()->create(['email' => null]);

        $this->assertEquals([], $customer->contact_emails);
    }

    /** @test */
    public function vat_e_address_sono_scrivibili_e_nullable(): void
    {
        $customer = Customer::factory()->create(['vat' => '01234567890', 'address' => 'Via Roma 1, Pisa']);

        $this->assertEquals('01234567890', $customer->fresh()->vat);
        $this->assertEquals('Via Roma 1, Pisa', $customer->fresh()->address);
    }
}
