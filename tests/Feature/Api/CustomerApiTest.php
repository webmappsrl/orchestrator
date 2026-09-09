<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Http\Requests\Api\CustomerApiRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use DatabaseTransactions;

    private function validate(array $data, string $method = 'POST'): \Illuminate\Contracts\Validation\Validator
    {
        $request = new CustomerApiRequest();
        $request->setMethod($method);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    /** @test */
    public function regole_base_passano_con_campi_scrivibili_validi(): void
    {
        $validator = $this->validate([
            'name'         => 'cliente_test',
            'company_name' => 'Cliente Test SRL',
            'vat'          => '01164510503',
            'address'      => 'Via Roma 1',
            'phone'        => '+39 328 5360803',
            'status'       => CustomerStatus::Active->value,
            'notes'        => 'nota',
        ]);

        $this->assertFalse($validator->fails(), $validator->errors()->first());
    }

    /** @test */
    public function name_company_name_address_notes_sono_sempre_opzionali(): void
    {
        $validator = $this->validate([]);

        $this->assertFalse($validator->fails(), $validator->errors()->first());
    }

    /** @test */
    public function vat_deve_essere_11_cifre_numeriche(): void
    {
        $this->assertTrue($this->validate(['vat' => '123'])->fails());
        $this->assertTrue($this->validate(['vat' => 'ABCDEFGHIJK'])->fails());
        $this->assertFalse($this->validate(['vat' => '01164510503'])->fails());
    }

    /** @test */
    public function status_deve_essere_un_valore_enum_valido(): void
    {
        $this->assertTrue($this->validate(['status' => 'not_a_status'])->fails());
        $this->assertFalse($this->validate(['status' => CustomerStatus::Opportunity->value])->fails());
    }

    /** @test */
    public function contact_emails_valida_formato_di_ogni_indirizzo(): void
    {
        $this->assertTrue($this->validate(['contact_emails' => ['non-una-email']])->fails());
        $this->assertFalse($this->validate(['contact_emails' => ['a@example.com', 'b@example.com']])->fails());
    }

    /** @test */
    public function contact_emails_add_accetta_stringa_singola_o_array(): void
    {
        $this->assertFalse($this->validate(['contact_emails_add' => 'a@example.com'])->fails());
        $this->assertFalse($this->validate(['contact_emails_add' => ['a@example.com', 'b@example.com']])->fails());
        $this->assertTrue($this->validate(['contact_emails_add' => 'non-una-email'])->fails());
        $this->assertTrue($this->validate(['contact_emails_add' => ['a@example.com', 'non-una-email']])->fails());
    }

    /** @test */
    public function contact_emails_e_contact_emails_add_sono_mutuamente_esclusivi(): void
    {
        $validator = $this->validate([
            'contact_emails'     => ['a@example.com'],
            'contact_emails_add' => 'b@example.com',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('contact_emails_add', $validator->errors()->toArray());
    }

    /** @test */
    public function phone_valida_uno_o_piu_numeri_separati_da_virgola(): void
    {
        $this->assertFalse($this->validate(['phone' => '+39 328 5360803'])->fails());
        $this->assertFalse($this->validate(['phone' => '+39 328 5360803, +39 02 1234567'])->fails());
        $this->assertTrue($this->validate(['phone' => 'non un numero'])->fails());
        $this->assertTrue($this->validate(['phone' => '12345'])->fails());
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsCustomerRole(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsDeveloper(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    /** @test */
    public function ruolo_customer_non_puo_accedere(): void
    {
        $this->actingAsCustomerRole();

        $this->getJson('/api/customers')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_i_campi_attesi(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $customer = Customer::factory()->create([
            'name'      => 'acme_srl',
            'full_name' => 'Acme S.r.l.',
            'vat'       => '01234567890',
            'address'   => 'Via Roma 1, Pisa',
            'email'     => 'a@acme.it,b@acme.it',
            'phone'     => '0501234567',
            'status'    => 'active',
            'user_id'   => $owner->id,
            'notes'     => 'nota interna',
        ]);

        $response = $this->getJson('/api/customers')->assertStatus(200);

        $item = collect($response->json())->firstWhere('id', $customer->id);
        $this->assertEquals('acme_srl', $item['name']);
        $this->assertEquals('Acme S.r.l.', $item['company_name']);
        $this->assertEquals('01234567890', $item['vat']);
        $this->assertEquals('Via Roma 1, Pisa', $item['address']);
        $this->assertEquals(['a@acme.it', 'b@acme.it'], $item['contact_emails']);
        $this->assertEquals('0501234567', $item['phone']);
        $this->assertEquals('active', $item['status']);
        $this->assertEquals(['id' => $owner->id, 'name' => $owner->name], $item['owner']);
        $this->assertEquals('nota interna', $item['notes']);
    }

    /** @test */
    public function show_restituisce_il_singolo_customer(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create(['name' => 'progetto_x']);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $customer->id)
            ->assertJsonPath('name', 'progetto_x');
    }

    /** @test */
    public function filtro_per_status_funziona(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['status' => 'active']);
        Customer::factory()->create(['status' => 'lost']);

        $response = $this->getJson('/api/customers?status=active')->assertStatus(200);

        $this->assertTrue(collect($response->json())->every(fn($c) => $c['status'] === 'active'));
    }

    /** @test */
    public function ricerca_per_nome_sanitizza_i_caratteri_like(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['name' => 'acme_srl']);
        Customer::factory()->create(['name' => 'altro_cliente']);

        $response = $this->getJson('/api/customers?search=acme%25')->assertStatus(200);

        $this->assertCount(0, $response->json());
    }

    /** @test */
    public function utente_non_autenticato_su_store_ottiene_401(): void
    {
        $this->postJson('/api/customers', ['name' => 'test'])->assertStatus(401);
    }

    /** @test */
    public function developer_non_puo_creare_customer(): void
    {
        $this->actingAsDeveloper();

        $this->postJson('/api/customers', ['name' => 'test'])->assertStatus(403);
    }

    /** @test */
    public function store_crea_customer_con_name_esplicito(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', ['name' => 'mio_cliente'])->assertStatus(201);

        $response->assertJsonPath('name', 'mio_cliente');
        $this->assertDatabaseHas('customers', ['name' => 'mio_cliente']);
    }

    /** @test */
    public function store_genera_name_da_company_name_quando_omesso(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', ['company_name' => 'Parco Minerario Alta Valsugana'])
            ->assertStatus(201);

        $response->assertJsonPath('name', 'parco_minerario_alta_valsugana');
        $response->assertJsonPath('company_name', 'Parco Minerario Alta Valsugana');
    }

    /** @test */
    public function store_deduplica_name_generato_su_collisione(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['name' => 'acme']);

        $response = $this->postJson('/api/customers', ['company_name' => 'Acme'])->assertStatus(201);

        $response->assertJsonPath('name', 'acme_2');
    }

    /** @test */
    public function store_senza_name_ne_company_name_genera_uno_slug_di_fallback(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', [])->assertStatus(201);

        $this->assertNotEmpty($response->json('name'));
    }

    /** @test */
    public function store_scrive_vat_address_phone_status_notes(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', [
            'name'    => 'cliente_completo',
            'vat'     => '01164510503',
            'address' => 'Via Roma 1',
            'phone'   => '+39 328 5360803',
            'status'  => CustomerStatus::Active->value,
            'notes'   => 'Nota di test',
        ])->assertStatus(201);

        $response->assertJson([
            'vat'     => '01164510503',
            'address' => 'Via Roma 1',
            'phone'   => '+39 328 5360803',
            'status'  => CustomerStatus::Active->value,
            'notes'   => 'Nota di test',
        ]);
    }

    /** @test */
    public function store_ignora_campi_fuori_whitelist(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', [
            'name'  => 'test_whitelist',
            'hs_id' => 'HS-999',
        ])->assertStatus(201);

        $customer = Customer::where('name', 'test_whitelist')->first();
        $this->assertNull($customer->hs_id);
    }

    /** @test */
    public function store_restituisce_422_per_vat_non_valida(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', ['name' => 'test', 'vat' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vat']);
    }

    /** @test */
    public function store_restituisce_422_per_phone_non_valido(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', ['name' => 'test', 'phone' => 'non un numero'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function store_scrive_contact_emails_come_array(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', [
            'name'           => 'test_emails',
            'contact_emails' => ['a@example.com', 'b@example.com'],
        ])->assertStatus(201);

        $response->assertJsonPath('contact_emails', ['a@example.com', 'b@example.com']);
    }

    /** @test */
    public function store_segnala_vat_duplicata_senza_bloccare(): void
    {
        $this->actingAsAdmin();
        $existing = \App\Models\Customer::factory()->create(['name' => 'timesis', 'vat' => '01164510503']);

        $response = $this->postJson('/api/customers', [
            'name' => 'montepisano',
            'vat'  => '01164510503',
        ])->assertStatus(201);

        $response->assertJsonPath('warnings.0.type', 'duplicate_vat');
        $response->assertJsonPath('warnings.0.customers.0.id', $existing->id);
    }

    /** @test */
    public function store_non_include_warnings_senza_vat_duplicata(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', ['name' => 'test_no_dup', 'vat' => '01164510503'])
            ->assertStatus(201);

        $this->assertArrayNotHasKey('warnings', $response->json());
    }

    /** @test */
    public function update_modifica_parzialmente_un_customer(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create([
            'name'   => 'test_update',
            'status' => \App\Enums\CustomerStatus::Unknown->value,
        ]);

        $response = $this->patchJson("/api/customers/{$customer->id}", [
            'status' => \App\Enums\CustomerStatus::Active->value,
        ])->assertStatus(200);

        $response->assertJsonPath('status', \App\Enums\CustomerStatus::Active->value);
        $this->assertEquals('test_update', $customer->fresh()->name);
    }

    /** @test */
    public function update_sostituisce_contact_emails_con_replace_all(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_replace']);
        $customer->email = 'old@example.com';
        $customer->save();

        $this->patchJson("/api/customers/{$customer->id}", ['contact_emails' => ['new@example.com']])
            ->assertStatus(200)
            ->assertJsonPath('contact_emails', ['new@example.com']);
    }

    /** @test */
    public function update_aggiunge_email_con_contact_emails_add_senza_rimuovere_le_esistenti(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_add']);
        $customer->email = 'old@example.com';
        $customer->save();

        $response = $this->patchJson("/api/customers/{$customer->id}", ['contact_emails_add' => 'new@example.com'])
            ->assertStatus(200);

        $this->assertEqualsCanonicalizing(['old@example.com', 'new@example.com'], $response->json('contact_emails'));
    }

    /** @test */
    public function update_restituisce_404_per_customer_inesistente(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/customers/999999', ['status' => \App\Enums\CustomerStatus::Active->value])
            ->assertStatus(404);
    }

    /** @test */
    public function developer_non_puo_modificare_customer(): void
    {
        $this->actingAsDeveloper();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_dev']);

        $this->patchJson("/api/customers/{$customer->id}", ['notes' => 'x'])->assertStatus(403);
    }

    /**
     * Regressione: un phone non-stringa (es. array) crashava con TypeError
     * (500) invece di 422 — Laravel invoca la closure di validazione anche
     * quando la regola 'string' precedente è già fallita (nessun `bail`).
     */
    /** @test */
    public function store_restituisce_422_per_phone_non_stringa_invece_di_500(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', ['name' => 'phone_array_test', 'phone' => ['+39 328 5360803']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Regressione: un numero con NBSP (tipico da copia-incolla) era rifiutato
     * dall'API (422) pur essendo accettato da Nova, che normalizza il valore
     * prima di validarlo — stessa classe di bug già risolta una volta in oc:8412.
     */
    /** @test */
    public function store_normalizza_nbsp_nel_phone_prima_di_validare(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', [
            'name'  => 'phone_nbsp_test',
            'phone' => "+39\u{00A0}328\u{00A0}5360803",
        ])->assertStatus(201)->assertJsonPath('phone', '+39 328 5360803');
    }

    /**
     * Regressione: Nova rifiuta un `name` duplicato in creazione
     * (unique:customers,name) — l'API deve dare la stessa garanzia per un
     * `name` fornito esplicitamente (la deduplica automatica di resolveName()
     * copre solo il caso in cui `name` è omesso e generato da company_name).
     */
    /** @test */
    public function store_rifiuta_name_esplicito_duplicato(): void
    {
        $this->actingAsAdmin();
        \App\Models\Customer::factory()->create(['name' => 'nome_gia_usato']);

        $this->postJson('/api/customers', ['name' => 'nome_gia_usato'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function update_permette_di_confermare_lo_stesso_name_del_customer(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create(['name' => 'stesso_nome']);

        $this->patchJson("/api/customers/{$customer->id}", ['name' => 'stesso_nome', 'notes' => 'x'])
            ->assertStatus(200);
    }

    /** @test */
    public function update_rifiuta_name_duplicato_su_altro_customer(): void
    {
        $this->actingAsAdmin();
        \App\Models\Customer::factory()->create(['name' => 'nome_altro_customer']);
        $target = \App\Models\Customer::factory()->create(['name' => 'nome_target']);

        $this->patchJson("/api/customers/{$target->id}", ['name' => 'nome_altro_customer'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Regressione: contact_emails_add inviato esplicitamente a null passava
     * la validazione (nullable) e scriveva una virgola spuria nella colonna
     * email grezza (invisibile via API, ma sporca per chi legge la colonna
     * direttamente, es. AlignTagsCommand).
     */
    /** @test */
    public function update_con_contact_emails_add_null_non_scrive_virgola_spuria(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_add_null']);
        $customer->email = 'old@example.com';
        $customer->save();

        $this->patchJson("/api/customers/{$customer->id}", ['contact_emails_add' => null])
            ->assertStatus(200);

        $this->assertEquals('old@example.com', $customer->fresh()->getRawOriginal('email'));
    }
}
