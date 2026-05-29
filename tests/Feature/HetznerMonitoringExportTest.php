<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exports\HetznerActionableSheet;
use App\Exports\HetznerAllResourcesSheet;
use App\Exports\HetznerExport;
use App\Models\User;
use App\Services\HetznerApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class HetznerMonitoringExportTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeProjects(): array
    {
        return [
            [
                'slug'                  => 'testproj',
                'status'                => 'ok',
                'error'                 => null,
                'monthly_cost_estimate' => 10.0,
                'potential_savings'     => 3.5,
                'servers'               => [],
                'floating_ips'          => [
                    [
                        'id'              => 1,
                        'ip'              => '1.2.3.4',
                        'type'            => 'ipv4',
                        'description'     => 'unassigned',
                        'server_id'       => null,
                        'monthly_price'   => 3.5,
                        'action'          => 'Elimina: non assegnato',
                        'action_priority' => 'high',
                        'note'            => [
                            'text'       => 'Da rimuovere',
                            'user_name'  => 'Admin User',
                            'updated_at' => '2026-05-29T10:00:00+00:00',
                        ],
                    ],
                    [
                        'id'              => 2,
                        'ip'              => '5.6.7.8',
                        'type'            => 'ipv4',
                        'description'     => 'assigned',
                        'server_id'       => 99,
                        'monthly_price'   => 3.5,
                        'action'          => 'OK',
                        'action_priority' => 'ok',
                        'note'            => [
                            'text'       => 'Verificare se serve ancora',
                            'user_name'  => 'Manager',
                            'updated_at' => '2026-05-28T12:00:00+00:00',
                        ],
                    ],
                    [
                        'id'              => 3,
                        'ip'              => '9.9.9.9',
                        'type'            => 'ipv4',
                        'description'     => 'orphan no note',
                        'server_id'       => null,
                        'monthly_price'   => 3.5,
                        'action'          => 'Elimina: non assegnato',
                        'action_priority' => 'high',
                    ],
                ],
                'volumes'        => [],
                'load_balancers' => [],
                'snapshots'      => [],
            ],
        ];
    }

    /** @test */
    public function export_genera_file_xlsx_per_admin(): void
    {
        Excel::fake();

        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        $this->actingAs($user);

        $this->mock(HetznerApiService::class, function ($mock): void {
            $mock->shouldReceive('getAllProjectsData')
                ->once()
                ->andReturn($this->fakeProjects());
        });

        $response = $this->get('/nova-vendor/hetzner-monitoring/export');

        $response->assertOk();

        Excel::assertDownloaded('hetzner-monitoring.xlsx', function (HetznerExport $export): bool {
            return count($export->sheets()) === 8;
        });
    }

    /** @test */
    public function export_negato_a_utente_senza_ruolo_autorizzato(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        $this->actingAs($user);

        $response = $this->get('/nova-vendor/hetzner-monitoring/export');

        $response->assertForbidden();
    }

    /** @test */
    public function foglio_tutto_include_tutte_le_risorse_con_note(): void
    {
        $sheet = new HetznerAllResourcesSheet($this->fakeProjects());
        $rows  = $sheet->collection();

        $this->assertCount(3, $rows);
        $this->assertSame('Da rimuovere', $rows[0][6]);
        $this->assertSame('Admin User', $rows[0][7]);
    }

    /** @test */
    public function foglio_azioni_da_fare_include_priorita_non_ok_e_risorse_con_nota(): void
    {
        $sheet = new HetznerActionableSheet($this->fakeProjects());
        $rows  = $sheet->collection();

        $this->assertCount(3, $rows);
        $this->assertSame(1, $rows[0][2]);
        $this->assertSame('Da rimuovere', $rows[0][6]);
        $this->assertSame(3, $rows[1][2]);
        $this->assertSame('', $rows[1][6]);
        $this->assertSame(2, $rows[2][2]);
        $this->assertSame('ok', $rows[2][4]);
        $this->assertSame('Verificare se serve ancora', $rows[2][6]);
    }
}
