<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Calculo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModeloValoresTest extends TestCase
{
    /** @return array<string, array{mixed, ?float}> */
    public static function valores(): array
    {
        return [
            'ponto decimal'       => ['123.45', 123.45],
            'vírgula decimal'     => ['123,45', 123.45],
            'milhar brasileiro'   => ['1.234,56', 1234.56],
            'inteiro'             => ['50', 50.0],
            'número'              => [99.9, 99.9],
            'espaços'             => [' 10,5 ', 10.5],
            'zero'                => ['0', 0.0],
            'vazio'               => ['', null],
            'nulo'                => [null, null],
            'negativo'            => ['-5', null],
            'número negativo'     => [-1.0, null],
            'texto'               => ['abc', null],
            'dois separadores'    => ['1.2.3', null],
        ];
    }

    #[DataProvider('valores')]
    public function testLerValor(mixed $entrada, ?float $esperado): void
    {
        self::assertSame($esperado, Calculo::lerValor($entrada));
    }

    public function testPercentual(): void
    {
        self::assertSame(5.0, Calculo::percentual('5'));
        self::assertSame(5.5, Calculo::percentual('5,5'));
        self::assertSame(100.0, Calculo::percentual('150'));
        self::assertSame(0.0, Calculo::percentual('-3'));
        self::assertSame(0.0, Calculo::percentual(''));
    }

    public function testExibicao(): void
    {
        self::assertSame(Calculo::EXIBICAO_UNIDADE, Calculo::exibicaoValida('unidade'));
        self::assertSame(Calculo::EXIBICAO_TOTAL, Calculo::exibicaoValida('total'));
        self::assertSame(Calculo::EXIBICAO_TOTAL, Calculo::exibicaoValida('qualquer'));
        self::assertSame(Calculo::EXIBICAO_TOTAL, Calculo::exibicaoValida(null));
    }

    /** @return list<array<string, mixed>> */
    private static function itensModelo(): array
    {
        return [
            ['id' => 1, 'tipo' => 'recorrente', 'name' => 'Programa A', 'quantidade' => '1.00', 'valor_unitario' => '100.0000', 'desconto_pct' => '0.00'],
            ['id' => 2, 'tipo' => 'recorrente', 'name' => 'Programa B', 'quantidade' => '1.00', 'valor_unitario' => '200.0000', 'desconto_pct' => '2.00'],
            ['id' => 3, 'tipo' => 'pontual', 'name' => 'Taxa única', 'quantidade' => '1.00', 'valor_unitario' => '50.0000', 'desconto_pct' => '0.00'],
        ];
    }

    public function testCopiaSemValoresNemDescontoMantemOModelo(): void
    {
        self::assertSame(self::itensModelo(), Calculo::itensDaCopia(self::itensModelo(), [], null));
    }

    public function testValorDigitadoSubstituiOModelo(): void
    {
        $copia = Calculo::itensDaCopia(self::itensModelo(), [1 => '150,00', 2 => '', 3 => 'abc'], null);

        self::assertSame(150.0, $copia[0]['valor_unitario']);
        self::assertSame('200.0000', $copia[1]['valor_unitario'], 'vazio mantém o modelo');
        self::assertSame('50.0000', $copia[2]['valor_unitario'], 'inválido mantém o modelo');
        self::assertSame('2.00', $copia[1]['desconto_pct'], 'sem desconto especial, mantém o do item');
    }

    public function testDescontoEspecialValeParaTodosOsItens(): void
    {
        $copia = Calculo::itensDaCopia(self::itensModelo(), ['2' => '210.00'], 5.0);

        self::assertSame([5.0, 5.0, 5.0], array_column($copia, 'desconto_pct'));
        self::assertSame(199.5, Calculo::subtotal(1, $copia[1]['valor_unitario'], $copia[1]['desconto_pct']));
        self::assertSame(95.0, Calculo::subtotal(1, $copia[0]['valor_unitario'], $copia[0]['desconto_pct']));
    }

    public function testDescontoDaCriacao(): void
    {
        self::assertNull(Calculo::descontoDaCriacao(false, '8', 5.0), 'opção desmarcada');
        self::assertSame(8.0, Calculo::descontoDaCriacao(true, '8', 5.0), 'digitado vale no lugar do sugerido');
        self::assertSame(7.5, Calculo::descontoDaCriacao(true, '7,5', 5.0));
        self::assertSame(5.0, Calculo::descontoDaCriacao(true, '', 5.0), 'vazio usa o sugerido');
        self::assertSame(5.0, Calculo::descontoDaCriacao(true, null, 5.0));
        self::assertNull(Calculo::descontoDaCriacao(true, '0', 5.0), 'zero = sem desconto');
        self::assertNull(Calculo::descontoDaCriacao(true, '', 0.0), 'sem sugerido e sem digitado');
        self::assertSame(100.0, Calculo::descontoDaCriacao(true, '150', 5.0), 'limite de 100%');
    }
}
