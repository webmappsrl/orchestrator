<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillCustomerVatFromHeadingTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function dry_run_non_scrive_vat_ma_salva_il_report(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "VIA DEI LIMONI N 23\n54100 MASSA (MS)\nPartita IVA 00660130451 C.F. 00660130451",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat')->assertExitCode(0);

        $this->assertNull($customer->fresh()->vat);
        $this->assertNotEmpty(Storage::disk('local')->allFiles('customer-vat-backfill'));
    }

    /** @test */
    public function apply_scrive_la_partita_iva_estratta(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "Via Placido Rizzotto, 90\n41126 Modena (MO)\nP.iva / CF 03880320365",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertEquals('03880320365', $customer->fresh()->vat);
    }

    /** @test */
    public function non_sovrascrive_vat_gia_presente(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "P.IVA 01164510503",
            'vat'     => '99999999999',
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertEquals('99999999999', $customer->fresh()->vat);
    }

    /** @test */
    public function non_estrae_da_codice_fiscale_alfanumerico_di_persona_fisica(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "Via San Costanzo 25, 80061 Massa Lubrense (NA)\nC.F. CCRSVT85H02I862Q",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertNull($customer->fresh()->vat);
    }

    /** @test */
    public function non_tronca_un_run_di_piu_di_11_cifre_senza_separatore_in_una_partita_iva_errata(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "Partita IVA 006601304511234",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertNull($customer->fresh()->vat);
    }
}
