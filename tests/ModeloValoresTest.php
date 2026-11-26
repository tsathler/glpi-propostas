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
            'milhar sem vírgula'  => ['1.500', 1500.0],
            'milhões sem vírgula' => ['12.000.000', 12000000.0],
            'três casas com zero' => ['0.125', 0.125],
            'ponto e duas casas'  => ['1500.00', 1500.0],
            'quatro casas'        => ['1.5000', 1.5],
        ];
    }

    public function testValidade(): void
    {
        self::assertTrue(Calculo::validadeValida('15'));
        self::assertTrue(Calculo::validadeValida(1));
        self::assertTrue(Calculo::validadeValida(' 365 '));
        self::assertFalse(Calculo::validadeValida('0'));
        self::assertFalse(Calculo::validadeValida('-5'));
        self::assertFalse(Calculo::validadeValida('366'));
        self::assertFalse(Calculo::validadeValida('4000'));
        self::assertFalse(Calculo::validadeValida('15.5'));
        self::assertFalse(Calculo::validadeValida(''));
        self::assertFalse(Calculo::validadeValida('abc'));
    }

    public function testNormalizarNumero(): void
    {
        self::assertSame('1500', Calculo::normalizarNumero(' 1.500 '));
        self::assertSame('1234.56', Calculo::normalizarNumero('1.234,56'));
        self::assertSame('0.125', Calculo::normalizarNumero('0.125'));
        self::assertSame('abc', Calculo::normalizarNumero('abc'), 'não valida: a validação recusa depois');
        self::assertSame('1,234.56', Calculo::normalizarNumero('1,234.56'), 'formato americano segue como veio');
        self::assertFalse(is_numeric(Calculo::normalizarNumero('1,234.56')), 'e a validação recusa');
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
        self::assertSame(self::itensModelo(), Calculo::itensDaCopia(self::itensModelo(), [], []));
    }

    public function testValorDigitadoSubstituiOModelo(): void
    {
        $copia = Calculo::itensDaCopia(self::itensModelo(), [1 => '150,00', 2 => '', 3 => 'abc'], []);

        self::assertSame(150.0, $copia[0]['valor_unitario']);
        self::assertSame('200.0000', $copia[1]['valor_unitario'], 'vazio mantém o modelo');
        self::assertSame('50.0000', $copia[2]['valor_unitario'], 'inválido mantém o modelo');
        self::assertSame('2.00', $copia[1]['desconto_pct'], 'sem desconto digitado, mantém o do item');
    }

    public function testDescontoPorItem(): void
    {
        $copia = Calculo::itensDaCopia(self::itensModelo(), ['2' => '210.00'], [1 => '5', 2 => '7,5', 3 => '']);

        self::assertSame(5.0, $copia[0]['desconto_pct']);
        self::assertSame(7.5, $copia[1]['desconto_pct']);
        self::assertSame('0.00', $copia[2]['desconto_pct'], 'vazio mantém o do modelo');
        self::assertSame(95.0, Calculo::subtotal(1, $copia[0]['valor_unitario'], $copia[0]['desconto_pct']));
        self::assertSame(194.25, Calculo::subtotal(1, $copia[1]['valor_unitario'], $copia[1]['desconto_pct']));
    }

    public function testDescontoPorItemZeroELimite(): void
    {
        $copia = Calculo::itensDaCopia(self::itensModelo(), [], [2 => '0', 3 => '150', 1 => 'abc']);

        self::assertSame('0.00', $copia[0]['desconto_pct'], 'inválido mantém o do modelo');
        self::assertSame(0.0, $copia[1]['desconto_pct'], 'zero tira o desconto do modelo');
        self::assertSame(100.0, $copia[2]['desconto_pct'], 'limite de 100%');
    }

    public function testCopiaMantemValorDeQuatroCasas(): void
    {
        $modelo = [['id' => 1, 'tipo' => 'recorrente', 'name' => 'Por unidade', 'quantidade' => '1.00', 'valor_unitario' => '0.0125', 'desconto_pct' => '0.00']];

        // O quadro da criação manda o valor do modelo sem arredondar (campo numérico, ponto decimal).
        self::assertSame(0.0125, Calculo::itensDaCopia($modelo, [1 => '0.0125'])[0]['valor_unitario']);
        self::assertSame(1.125, Calculo::itensDaCopia($modelo, [1 => '1.125'])[0]['valor_unitario'], 'campo numérico: ponto é decimal, não milhar');
        self::assertSame(1234.56, Calculo::itensDaCopia($modelo, [1 => '1.234,56'])[0]['valor_unitario'], 'texto com vírgula: formato brasileiro');
        self::assertSame('0.0125', Calculo::itensDaCopia($modelo, [1 => '-1'])[0]['valor_unitario'], 'negativo mantém o modelo');
    }

    public function testNomeAutomatico(): void
    {
        self::assertSame('Plano B – Empresa Exemplo Ltda', Calculo::nomeAutomatico('Plano B', 'Empresa Exemplo Ltda'));
        self::assertSame('Empresa Exemplo Ltda', Calculo::nomeAutomatico('', ' Empresa Exemplo Ltda '));
        self::assertSame('Plano B', Calculo::nomeAutomatico('Plano B', ''));
        self::assertSame('Proposta', Calculo::nomeAutomatico('', ''));
        self::assertSame(255, mb_strlen(Calculo::nomeAutomatico(str_repeat('a', 200), str_repeat('b', 200))));
    }
}
