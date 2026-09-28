<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/stories/{story}/status-history (oc:8636).
 *
 * I casi che dipendono dai dati usano ticket veri, copiati in
 * tests/Fixtures/story-status-history/ dal DB locale (log di stato fino a giugno 2025) e
 * ripuliti dai testi dei clienti: di ogni riga di log resta il valore di `status` e il solo nome
 * delle altre chiavi. I valori attesi sono calcolati a mano sulla storia reale.
 */
class StoryStatusHistoryApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['roles' => [UserRole::Developer]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Ricrea nel DB di test un ticket vero dalla sua fixture. Lo stato non si cambia mai con
     * save(): scriverebbe righe di log vere con l'ora attuale.
     */
    private function storyFromFixture(int $id): Story
    {
        $fixture = json_decode(
            file_get_contents(base_path("tests/Fixtures/story-status-history/oc-{$id}.json")),
            true
        );

        return $this->storyWithLogs(
            $fixture['story']['created_at'],
            $fixture['story']['status'],
            array_map(fn (array $log) => [$log['created_at'], $log['changes']], $fixture['logs'])
        );
    }

    /**
     * @param array<array{0: string, 1: array}> $logs coppie [created_at, changes]
     */
    private function storyWithLogs(string $createdAt, string $status, array $logs): Story
    {
        // user_id null: con uno sviluppatore assegnato Story::boot() trasforma new in assigned.
        $story = Story::factory()->create([
            'status'     => $status,
            'created_at' => $createdAt,
            'user_id'    => null,
        ]);

        foreach ($logs as [$at, $changes]) {
            $log = new StoryLog([
                'story_id'  => $story->id,
                'user_id'   => $this->user->id,
                'viewed_at' => $at,
                'changes'   => $changes,
            ]);
            // created_at non è in $fillable.
            $log->created_at = $at;
            $log->save();
        }

        return $story;
    }

    private function getHistory(Story $story, string $query = ''): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->user);

        return $this->getJson("/api/stories/{$story->id}/status-history{$query}");
    }

    private function exampleStory(): Story
    {
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00', 'Europe/Rome'));

        return $this->storyWithLogs('2026-09-21 08:00:00', 'progress', [
            ['2026-09-21 09:00:00', ['status' => 'progress']],
            ['2026-09-21 17:00:00', ['status' => 'waiting']],
            ['2026-09-22 10:00:00', ['status' => 'waiting']],
            ['2026-09-23 09:30:00', ['status' => 'progress']],
        ]);
    }

    /** @test */
    public function esempio_del_ticket_senza_filtro(): void
    {
        $story = $this->exampleStory();

        $response = $this->getHistory($story);

        $response->assertStatus(200);
        $this->assertSame([
            'story_id'       => $story->id,
            'timezone'       => 'Europe/Rome',
            'current_status' => 'progress',
            'intervals'      => [
                ['status' => 'new', 'from' => '2026-09-21T08:00:00+02:00', 'to' => '2026-09-21T09:00:00+02:00'],
                ['status' => 'progress', 'from' => '2026-09-21T09:00:00+02:00', 'to' => '2026-09-21T17:00:00+02:00'],
                ['status' => 'waiting', 'from' => '2026-09-21T17:00:00+02:00', 'to' => '2026-09-23T09:30:00+02:00'],
                ['status' => 'progress', 'from' => '2026-09-23T09:30:00+02:00', 'to' => null],
            ],
            'days' => [
                ['date' => '2026-09-21', 'statuses' => ['new' => 60, 'progress' => 480, 'waiting' => 420]],
                ['date' => '2026-09-22', 'statuses' => ['waiting' => 1440]],
                ['date' => '2026-09-23', 'statuses' => ['waiting' => 570, 'progress' => 150]],
            ],
        ], $response->json());
    }

    /** @test */
    public function esempio_del_ticket_con_filtro_progress(): void
    {
        $story = $this->exampleStory();

        $response = $this->getHistory($story, '?status=progress');

        $response->assertStatus(200);
        $this->assertSame([
            'story_id'       => $story->id,
            'timezone'       => 'Europe/Rome',
            'current_status' => 'progress',
            'intervals'      => [
                ['status' => 'progress', 'from' => '2026-09-21T09:00:00+02:00', 'to' => '2026-09-21T17:00:00+02:00'],
                ['status' => 'progress', 'from' => '2026-09-23T09:30:00+02:00', 'to' => null],
            ],
            'days' => [
                ['date' => '2026-09-21', 'statuses' => ['progress' => 480]],
                ['date' => '2026-09-23', 'statuses' => ['progress' => 150]],
            ],
        ], $response->json());
    }

    /** @test */
    public function oc_836_senza_log_di_stato_ha_un_solo_periodo_con_lo_stato_attuale(): void
    {
        Carbon::setTestNow(Carbon::parse('2023-05-10 12:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(836);

        $response = $this->getHistory($story);

        $response->assertStatus(200);
        $this->assertSame([
            ['status' => 'testing', 'from' => '2023-05-09T10:47:09+02:00', 'to' => null],
        ], $response->json('intervals'));
        $this->assertSame([
            ['date' => '2023-05-09', 'statuses' => ['testing' => 792]],
            ['date' => '2023-05-10', 'statuses' => ['testing' => 720]],
        ], $response->json('days'));
    }

    /** @test */
    public function oc_4043_promemoria_waiting_ripetuti_danno_un_solo_periodo(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-10-23 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(4043);

        $response = $this->getHistory($story);

        // La riga di creazione (status new, stesso istante di created_at) e i due promemoria
        // waiting delle 18:00 sono uguali allo stato aperto: ignorati.
        $this->assertSame([
            ['status' => 'new', 'from' => '2024-09-27T09:41:09+02:00', 'to' => '2024-09-27T12:30:43+02:00'],
            ['status' => 'waiting', 'from' => '2024-09-27T12:30:43+02:00', 'to' => '2024-10-22T12:16:18+02:00'],
            ['status' => 'backlog', 'from' => '2024-10-22T12:16:18+02:00', 'to' => null],
        ], $response->json('intervals'));
        $this->assertSame('backlog', $response->json('current_status'));
    }

    /** @test */
    public function oc_2685_righe_non_di_stato_non_spezzano_i_periodi(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-20 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(2685);

        $response = $this->getHistory($story);

        // parent_id e user_id alle 10:31 del 15/07 e parent_id il 07/08 non aprono periodi.
        $this->assertSame([
            ['status' => 'new', 'from' => '2024-02-21T15:28:21+01:00', 'to' => '2024-07-15T10:25:59+02:00'],
            ['status' => 'progress', 'from' => '2024-07-15T10:25:59+02:00', 'to' => '2024-07-26T06:58:31+02:00'],
            ['status' => 'testing', 'from' => '2024-07-26T06:58:31+02:00', 'to' => '2024-10-24T06:31:26+02:00'],
            ['status' => 'backlog', 'from' => '2024-10-24T06:31:26+02:00', 'to' => '2025-01-14T07:47:11+01:00'],
            ['status' => 'todo', 'from' => '2025-01-14T07:47:11+01:00', 'to' => '2025-01-17T08:06:30+01:00'],
            ['status' => 'released', 'from' => '2025-01-17T08:06:30+01:00', 'to' => null],
        ], $response->json('intervals'));

        // 00:00→10:25:59 = 625 min 59 s; 10:25:59→24:00 = 814 min 1 s.
        $this->assertSame(
            ['new' => 625, 'progress' => 814],
            $this->statusesOn($response->json('days'), '2024-07-15')
        );
    }

    /** @test */
    public function oc_3846_periodo_a_cavallo_della_mezzanotte(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-08-29 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(3846);

        $days = $this->getHistory($story)->json('days');

        // new dalle 23:08:28 alle 05:32:22: 51 min 32 s il 26, 332 min 22 s il 27.
        $this->assertSame(['new' => 51], $this->statusesOn($days, '2024-08-26'));
        $this->assertSame(['new' => 332, 'progress' => 1107], $this->statusesOn($days, '2024-08-27'));
    }

    /** @test */
    public function oc_2685_giorno_del_cambio_ora_di_ottobre_vale_1500_minuti(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-20 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(2685);

        $days = $this->getHistory($story)->json('days');

        $this->assertSame(['backlog' => 1500], $this->statusesOn($days, '2024-10-27'));
    }

    /** @test */
    public function oc_5090_giorno_del_cambio_ora_di_marzo_vale_1380_minuti(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-04-08 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(5090);

        $days = $this->getHistory($story)->json('days');

        $this->assertSame(['todo' => 1380], $this->statusesOn($days, '2025-03-30'));
    }

    /** @test */
    public function filtro_con_stato_mai_raggiunto_restituisce_liste_vuote(): void
    {
        $story = $this->exampleStory();

        $response = $this->getHistory($story, '?status=rejected');

        $response->assertStatus(200);
        $this->assertSame('progress', $response->json('current_status'));
        $this->assertSame([], $response->json('intervals'));
        $this->assertSame([], $response->json('days'));
    }

    /** @test */
    public function filtro_con_stato_non_valido_restituisce_422_con_i_valori_ammessi(): void
    {
        $story = $this->exampleStory();

        $response = $this->getHistory($story, '?status=pippo');

        $response->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Stato non valido. Valori ammessi: backlog, new, assigned, todo, progress, testing, tested, pending_release, waiting, done, rejected, released');
    }

    /** @test */
    public function senza_token_restituisce_401(): void
    {
        $story = $this->exampleStory();

        $this->getJson("/api/stories/{$story->id}/status-history")->assertStatus(401);
    }

    /** @test */
    public function story_inesistente_restituisce_404(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/stories/999999999/status-history')->assertStatus(404);
    }

    /** @test */
    public function oc_5642_stato_tenuto_meno_di_un_minuto_compare_con_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-06 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(5642);

        $days = $this->getHistory($story)->json('days');

        // testing dalle 09:23:47 alle 09:24:36: 49 secondi.
        $this->assertSame(
            ['waiting' => 563, 'testing' => 0, 'released' => 875],
            $this->statusesOn($days, '2025-06-05')
        );
    }

    /** @test */
    public function oc_3133_il_giorno_di_creazione_compare_sempre(): void
    {
        Carbon::setTestNow(Carbon::parse('2024-07-10 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(3133);

        $days = $this->getHistory($story)->json('days');

        // Creato il 24/04/2024 alle 08:56:48, primo cambio di stato il 04/07/2024.
        $this->assertSame(['date' => '2024-04-24', 'statuses' => ['new' => 903]], $days[0]);
    }

    /** @test */
    public function oc_4689_rientri_in_progress_e_limite_noto_sull_ultimo_periodo(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-23 00:00:00', 'Europe/Rome'));
        $story = $this->storyFromFixture(4689);

        $response = $this->getHistory($story, '?status=progress');

        $this->assertSame([
            ['status' => 'progress', 'from' => '2025-01-16T15:23:14+01:00', 'to' => '2025-01-16T18:00:14+01:00'],
            ['status' => 'progress', 'from' => '2025-01-17T10:08:04+01:00', 'to' => '2025-01-17T13:14:58+01:00'],
            ['status' => 'progress', 'from' => '2025-01-17T14:09:37+01:00', 'to' => '2025-01-17T16:23:40+01:00'],
        ], $response->json('intervals'));

        // Limite noto (overview, Rischi): l'ultimo log è released ma lo stato attuale è done,
        // perché prima di oc:8137 il passaggio automatico a done non scriveva log.
        $all = $this->getHistory($story)->json();
        $this->assertSame('done', $all['current_status']);
        $this->assertSame(
            ['status' => 'released', 'from' => '2025-01-22T08:11:23+01:00', 'to' => null],
            end($all['intervals'])
        );
    }

    private function statusesOn(array $days, string $date): ?array
    {
        foreach ($days as $day) {
            if ($day['date'] === $date) {
                return $day['statuses'];
            }
        }

        return null;
    }
}
