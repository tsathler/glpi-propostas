<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Cpf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CpfTest extends TestCase
{
    /**
     * CPFs de exemplo, válidos só pelo cálculo dos dígitos (não são de pessoas).
     *
     * @return iterable<string, array{string}>
     */
    public static function validos(): iterable
    {
        yield 'com pontuação' => ['529.982.247-25'];
        yield 'só dígitos' => ['52998224725'];
        yield 'outro exemplo' => ['111.444.777-35'];
        yield 'com espaços' => [' 111 444 777 35 '];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidos(): iterable
    {
        yield 'primeiro dígito errado' => ['529.982.247-35'];
        yield 'segundo dígito errado' => ['529.982.247-26'];
        yield 'todos iguais' => ['111.111.111-11'];
        yield 'zeros' => ['000.000.000-00'];
        yield 'curto' => ['529.982.247-2'];
        yield 'longo' => ['529.982.247-250'];
        yield 'vazio' => [''];
        yield 'letras' => ['abc.def.ghi-jk'];
    }

    #[DataProvider('validos')]
    public function testAceitaCpfValido(string $cpf): void
    {
        self::assertTrue(Cpf::validar($cpf));
    }

    #[DataProvider('invalidos')]
    public function testRecusaCpfInvalido(string $cpf): void
    {
        self::assertFalse(Cpf::validar($cpf));
    }

    public function testSomenteDigitos(): void
    {
        self::assertSame('52998224725', Cpf::somenteDigitos('529.982.247-25'));
        self::assertSame('', Cpf::somenteDigitos('---'));
    }

    public function testFormatar(): void
    {
        self::assertSame('529.982.247-25', Cpf::formatar('52998224725'));
        self::assertSame('529.982.247-25', Cpf::formatar('529.982.247-25'));
        self::assertSame('', Cpf::formatar('123'));
    }

    public function testMascaraEscondeInicioEFim(): void
    {
        self::assertSame('***.982.247-**', Cpf::mascarar('529.982.247-25'));
        self::assertStringNotContainsString('529', Cpf::mascarar('52998224725'));
        self::assertStringNotContainsString('25', Cpf::mascarar('52998224725'));
        self::assertSame('', Cpf::mascarar('123'));
    }
}
