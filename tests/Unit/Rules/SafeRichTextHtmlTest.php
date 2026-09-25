<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeRichTextHtml;
use App\Services\Quotes\QuoteRichText;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeRichTextHtmlTest extends TestCase
{
    private function errore(?string $html): ?string
    {
        App::setLocale('it');
        $validator = Validator::make(['payment_plan' => $html], ['payment_plan' => ['nullable', 'string', new SafeRichTextHtml()]]);
        return $validator->errors()->first('payment_plan') ?: null;
    }

    /** @test */
    public function html_ammesso_e_null_passano(): void
    {
        $this->assertNull($this->errore('<p><strong>20%</strong> alla firma</p>'));
        $this->assertNull($this->errore(null));
    }

    /** @test */
    public function il_messaggio_dice_cosa_e_sbagliato_e_cosa_fare(): void
    {
        $messaggio = $this->errore('<p onclick="x()">a</p><script>1</script><script>2</script>');

        $this->assertStringContainsString('payment plan', strtolower(str_replace('_', ' ', $messaggio)));
        $this->assertStringContainsString('tag <script> non ammesso (2 occorrenze)', $messaggio);
        $this->assertStringContainsString('rimuovilo', $messaggio);
        $this->assertStringContainsString('attributo onclick non ammesso (1 occorrenza)', $messaggio);
    }

    /** @test */
    public function oltre_dieci_tipi_il_messaggio_resta_corto(): void
    {
        $html = '';
        foreach (['script', 'iframe', 'object', 'embed', 'style', 'form', 'input', 'svg', 'math', 'base', 'meta', 'link'] as $tag) {
            $html .= str_repeat("<{$tag}></{$tag}>", 50);
        }

        $messaggio = $this->errore($html);

        $this->assertStringContainsString('e altri 2 tipi di elementi non ammessi', $messaggio);
        $this->assertSame(QuoteRichText::MAX_LISTED, substr_count($messaggio, '; '), 'dieci voci più la riga «…e altri N»');
    }

    /** @test */
    public function un_attributo_con_url_non_ammesso_e_indicato_col_proprio_nome(): void
    {
        $messaggio = $this->errore('<div data="x">a</div>');

        $this->assertStringContainsString('attributo data non ammesso (1 occorrenza)', $messaggio);
        $this->assertStringNotContainsString('immagine', $messaggio);
    }

    /** @test */
    public function il_messaggio_sulle_immagini_indica_host_e_porta_accettati(): void
    {
        config(['app.url' => 'https://h.example.it:8443']);

        $this->assertStringContainsString('h.example.it:8443', $this->errore('<img src="https://evil.com/x.png">'));
    }

    /** @test */
    public function il_messaggio_sui_link_cita_anche_i_percorsi_relativi(): void
    {
        $this->assertStringContainsString('percorso relativo', $this->errore('<a href="javascript:x()">a</a>'));
    }

    /** @test */
    public function un_attributo_di_presentazione_non_valido_spiega_il_valore_atteso(): void
    {
        $messaggio = $this->errore('<p align="left; background:url(http://evil.com/x)">a</p>');

        $this->assertStringContainsString('attributo align con valore non ammesso (1 occorrenza)', $messaggio);
        $this->assertStringContainsString('center', $messaggio);
    }
}
