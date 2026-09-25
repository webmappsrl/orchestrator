<?php

namespace Tests\Unit\Services;

use App\Services\Quotes\RichTextHtmlInspector;
use PHPUnit\Framework\TestCase;

class RichTextHtmlInspectorTest extends TestCase
{
    private function inspector(): RichTextHtmlInspector
    {
        return new RichTextHtmlInspector('orchestrator.example.it');
    }

    /** @test */
    public function html_prodotto_da_nova_non_ha_violazioni(): void
    {
        $html = '<p style="text-align: center;" dir="ltr">Testo <strong>forte</strong> <a href="https://webmapp.it" target="_blank" tt-mode="url">link</a></p>'
            . '<ul><li>20% alla firma</li></ul><div><span class="x">a</span></div>'
            . '<table><tbody><tr><td colspan="1" rowspan="1" colwidth="200">c</td></tr></tbody></table>'
            . '<img src="/storage/tiptap/logo.png" tt-mode="file" alt="logo" title="Logo">'
            . '<img src="https://orchestrator.example.it/storage/a.png"><font color="red">f</font><sup>1</sup>';

        $this->assertSame([], $this->inspector()->inspect($html));
    }

    /** @test */
    public function tag_pericolosi_sono_raggruppati_con_conteggio(): void
    {
        $violations = $this->inspector()->inspect('<script>a</script><p>x</p><script>b</script><iframe src="/x"></iframe>');

        $this->assertContains(['kind' => 'tag', 'name' => 'script', 'count' => 2], $violations);
        $this->assertContains(['kind' => 'tag', 'name' => 'iframe', 'count' => 1], $violations);
    }

    /** @test */
    public function handler_on_sono_rifiutati(): void
    {
        $this->assertSame(
            [['kind' => 'attribute', 'name' => 'onclick', 'count' => 2]],
            $this->inspector()->inspect('<p onclick="x()">a</p><span OnClick="y()">b</span>')
        );
    }

    /** @test */
    public function style_con_url_o_expression_e_rifiutato(): void
    {
        $violations = $this->inspector()->inspect('<p style="background: URL(http://evil.com/x.png)">a</p><p style="width: expression(alert(1))">b</p>');

        $this->assertContains(['kind' => 'style', 'name' => 'url(', 'count' => 1], $violations);
        $this->assertContains(['kind' => 'style', 'name' => 'expression(', 'count' => 1], $violations);
    }

    /** @test */
    public function href_javascript_e_rifiutato(): void
    {
        $this->assertSame(
            [['kind' => 'url', 'name' => 'href', 'count' => 1]],
            $this->inspector()->inspect('<a href=" JavaScript:alert(1)">x</a><a href="mailto:a@b.it">m</a><a href="#top">t</a>')
        );
    }

    /**
     * @test
     * @dataProvider srcIngannevoli
     */
    public function src_fuori_dall_host_dell_app_e_rifiutato(string $src): void
    {
        $this->assertSame(
            [['kind' => 'url', 'name' => 'src', 'count' => 1]],
            $this->inspector()->inspect('<img src="' . $src . '">')
        );
    }

    public static function srcIngannevoli(): array
    {
        return [
            'protocol-relative' => ['//evil.com/x.png'],
            'host con suffisso' => ['https://orchestrator.example.it.evil.com/x.png'],
            'credenziali'       => ['https://orchestrator.example.it@evil.com/x.png'],
            'data uri'          => ['data:image/png;base64,AAAA'],
            'host esterno'      => ['https://evil.com/x.png'],
        ];
    }

    /**
     * @test
     * @dataProvider styleAggirati
     */
    public function style_con_url_nascosto_da_commenti_o_escape_e_rifiutato(string $style): void
    {
        $violations = $this->inspector()->inspect('<p style="' . $style . '">a</p>');

        $this->assertNotSame([], $violations, "style non rifiutato: {$style}");
        $this->assertSame('style', $violations[0]['kind']);
    }

    public static function styleAggirati(): array
    {
        return [
            'commento fra url e parentesi' => ['background-image:url/**/(http://evil.com/x.png)'],
            'commento dentro url'          => ['background-image:u/**/rl(http://evil.com/x.png)'],
            'commento con testo'           => ['list-style-image:ur/*x*/l(http://evil.com/x.png)'],
            'escape css'                   => ['background:u\72l(http://evil.com/x.png)'],
            'image-set'                    => ['background-image:image-set("http://evil.com/x.png" 1x)'],
        ];
    }

    /**
     * @test
     * @dataProvider hrefJavascriptOffuscati
     */
    public function href_javascript_con_tab_newline_o_controlli_e_rifiutato(string $href): void
    {
        $this->assertSame(
            [['kind' => 'url', 'name' => 'href', 'count' => 1]],
            $this->inspector()->inspect('<a href="' . $href . '">x</a>')
        );
    }

    public static function hrefJavascriptOffuscati(): array
    {
        return [
            'tab'             => ["java\tscript:alert(1)"],
            'newline'         => ["java\nscript:alert(1)"],
            'entita tab'      => ['java&#9;script:alert(1)'],
            'controllo C0'    => ["\x01javascript:alert(1)"],
            'entita C0'       => ['&#1;javascript:alert(1)'],
        ];
    }

    /**
     * @test
     * @dataProvider srcAggirati
     */
    public function src_che_il_browser_leggerebbe_come_host_esterno_e_rifiutato(string $html): void
    {
        $violations = $this->inspector()->inspect($html);

        $this->assertNotSame([], $violations, "non rifiutato: {$html}");
        $this->assertSame('url', $violations[0]['kind']);
    }

    public static function srcAggirati(): array
    {
        return [
            'doppio backslash'      => ['<img src="\\\\evil.com/x.png">'],
            'slash backslash'       => ['<img src="/\\evil.com/x.png">'],
            'slash tab slash'       => ["<img src=\"/\t/evil.com/x.png\">"],
            'srcset'                => ['<img src="/storage/a.png" srcset="https://evil.com/x.png 1x">'],
            'stesso host fuori storage' => ['<img src="https://orchestrator.example.it/quote/218">'],
            'stesso host altra porta'   => ['<img src="https://orchestrator.example.it:6379/storage/a.png">'],
            'relativo fuori storage'    => ['<img src="/quote/218">'],
            'relativo con risalita'     => ['<img src="/storage/../quote/218">'],
            'background su tabella'     => ['<table background="https://evil.com/x.png"><tr><td>a</td></tr></table>'],
        ];
    }

    /** @test */
    public function tag_sconosciuto_innocuo_e_distinto_da_quello_pericoloso(): void
    {
        $violations = $this->inspector()->inspect('<o:p>a</o:p><script>b</script>');

        $this->assertContains(['kind' => 'tag', 'name' => 'o:p', 'count' => 1], $violations);
        $this->assertTrue(RichTextHtmlInspector::isDangerousTag('script'));
        $this->assertFalse(RichTextHtmlInspector::isDangerousTag('o:p'));
    }
}
