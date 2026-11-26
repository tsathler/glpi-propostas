<?php

/**
 * Prazo de guarda de IP e navegador (LGPD): o que a configuração aceita.
 */

declare(strict_types=1);

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Acesso;
use PHPUnit\Framework\TestCase;

// Acesso estende uma classe do GLPI; nos testes basta uma casca vazia para carregar as constantes.
if (!class_exists('CommonGLPI')) {
    eval('class CommonGLPI {}');
}

final class AcessoTest extends TestCase
{
    public function testPreviaDeLinkEhRobo(): void
    {
        foreach ([
            'WhatsApp/2.23.20.0 A',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) SkypeUriPreview Preview/0.5',
            'facebookexternalhit/1.1',
            'Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)',
            'TelegramBot (like TwitterBot)',
            'curl/8.5.0',
            '',
        ] as $ua) {
            $this->assertTrue(Acesso::ehRobo($ua), $ua);
        }
    }

    public function testNavegadorDePessoaNaoEhRobo(): void
    {
        $this->assertFalse(Acesso::ehRobo('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'));
        $this->assertFalse(Acesso::ehRobo('Mozilla/5.0 (Linux; Android 14; CUBOT KINGKONG) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36'));
    }

    public function testRetencaoAceitaDiasDentroDosLimites(): void
    {
        $this->assertTrue(Acesso::retencaoValida('45'));
        $this->assertTrue(Acesso::retencaoValida(' 1 '));
        $this->assertTrue(Acesso::retencaoValida(3650));
    }

    public function testRetencaoRecusaForaDosLimitesOuNaoInteiro(): void
    {
        foreach (['', '0', '3651', '-5', '45.5', '45,0', 'abc', null] as $valor) {
            $this->assertFalse(Acesso::retencaoValida($valor), var_export($valor, true));
        }
    }
}
