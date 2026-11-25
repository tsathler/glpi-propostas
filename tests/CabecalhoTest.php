<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Cabecalho;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CabecalhoTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function subtitulos(): array
    {
        return [
            'nome automático'          => ['Plano B – Empresa Exemplo Ltda', 'Empresa Exemplo Ltda', 'Plano B'],
            'hífen simples'            => ['Plano B - Empresa Exemplo Ltda', 'Empresa Exemplo Ltda', 'Plano B'],
            'maiúsculas diferentes'    => ['Plano B – EMPRESA EXEMPLO LTDA', 'Empresa Exemplo Ltda', 'Plano B'],
            'título digitado'          => ['Renovação 2027', 'Empresa Exemplo Ltda', 'Renovação 2027'],
            'igual à razão social'     => ['Empresa Exemplo Ltda', 'Empresa Exemplo Ltda', ''],
            'igual, com outra caixa'   => ['EMPRESA EXEMPLO LTDA.', 'Empresa Exemplo Ltda', ''],
            'sem separador não corta'  => ['Plano Empresa Exemplo Ltda', 'Empresa Exemplo Ltda', 'Plano Empresa Exemplo Ltda'],
            'empresa no começo'        => ['Empresa Exemplo Ltda – Plano B', 'Empresa Exemplo Ltda', 'Empresa Exemplo Ltda – Plano B'],
            'sem razão social'         => ['Plano B', '', 'Plano B'],
            'sem título'               => ['', 'Empresa Exemplo Ltda', ''],
        ];
    }

    #[DataProvider('subtitulos')]
    public function testSubtitulo(string $titulo, string $razao, string $esperado): void
    {
        self::assertSame($esperado, Cabecalho::subtitulo($titulo, $razao));
    }

    /** @return array<string, array{string, string, string}> */
    public static function fantasias(): array
    {
        return [
            'diferente'              => ['Padaria Pão Bom', 'J. Silva Comércio Ltda', 'Padaria Pão Bom'],
            'igual'                  => ['Empresa Exemplo Ltda', 'EMPRESA EXEMPLO LTDA', ''],
            'cortado'                => ['ASSOCIACAO DOS MORADORES DO BAIRRO NOVA ESPE', 'Associação dos Moradores do Bairro Nova Esperança', ''],
            'cortado com letra trocada' => ['ASSOCIACAO DO MORADORES DO BAIRRO NOVA E', 'Associação dos Moradores do Bairro Nova Esperança', ''],
            'razão social abreviada' => ['Empresa Exemplo', 'Empresa Exemplo Ltda', ''],
            'fantasia mais longa'    => ['Empresa Exemplo Ltda Filial Centro', 'Empresa Exemplo Ltda', ''],
            'vazio'                  => ['', 'Empresa Exemplo Ltda', ''],
            'sem razão social'       => ['Padaria Pão Bom', '', 'Padaria Pão Bom'],
            'começo parecido'        => ['Empresa Estrela', 'Empresa Exemplo Ltda', 'Empresa Estrela'],
        ];
    }

    #[DataProvider('fantasias')]
    public function testNomeFantasia(string $fantasia, string $razao, string $esperado): void
    {
        self::assertSame($esperado, Cabecalho::nomeFantasia($fantasia, $razao));
    }
}
