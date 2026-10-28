<?php

namespace Tests\Unit;

use App\Nova\Dashboards\HetznerMonitoring;
use Tests\TestCase;

class HetznerMonitoringDashboardTest extends TestCase
{
    public function test_card_receives_censimento_url_from_config(): void
    {
        config(['hetzner.censimento_url' => 'https://claude.ai/artifact/esempio']);

        $card = (new HetznerMonitoring)->cards()[0]->jsonSerialize();

        $this->assertSame('https://claude.ai/artifact/esempio', $card['censimentoUrl']);
    }

    public function test_card_has_no_censimento_url_when_not_configured(): void
    {
        config(['hetzner.censimento_url' => null]);

        $card = (new HetznerMonitoring)->cards()[0]->jsonSerialize();

        $this->assertNull($card['censimentoUrl']);
    }
}
