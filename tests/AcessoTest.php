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
