<?php

namespace Tests\Unit\Rules;

use App\Rules\SafeRichTextHtml;
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
        $this->assertLessThan(1500, strlen($messaggio));
    }

    /** @test */
    public function un_tag_non_supportato_non_viene_descritto_come_pericoloso(): void
    {
        $messaggio = $this->errore('<p>a<o:p></o:p></p>');

        $this->assertStringContainsString('tag <o:p> non supportato', $messaggio);
        $this->assertStringNotContainsString('eseguire codice', $messaggio);
    }
}
