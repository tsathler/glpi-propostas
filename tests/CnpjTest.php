<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Cnpj;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CnpjTest extends TestCase
{
    /**
     * CNPJs de exemplo, válidos só pelo cálculo dos dígitos.
     *
     * @return iterable<string, array{string}>
     */
    public static function validos(): iterable
    {
        yield 'numérico com pontuação' => ['11.222.333/0001-81'];
        yield 'numérico só dígitos' => ['11444777000161'];
        yield 'alfanumérico (exemplo oficial)' => ['12.ABC.345/01DE-35'];
        yield 'alfanumérico minúsculo' => ['12abc34501de35'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidos(): iterable
    {
        yield 'primeiro dígito errado' => ['11.222.333/0001-91'];
        yield 'segundo dígito errado' => ['11.222.333/0001-82'];
        yield 'alfanumérico com dígito errado' => ['12.ABC.345/01DE-36'];
        yield 'letra no dígito verificador' => ['12.ABC.345/01DE-3A'];
        yield 'todos iguais' => ['00.000.000/0000-00'];
        yield 'curto' => ['11.222.333/0001-8'];
        yield 'longo' => ['11.222.333/0001-810'];
        yield 'vazio' => [''];
    }

    #[DataProvider('validos')]
    public function testAceitaCnpjValido(string $cnpj): void
    {
        self::assertTrue(Cnpj::validar($cnpj));
    }

    #[DataProvider('invalidos')]
    public function testRecusaCnpjInvalido(string $cnpj): void
    {
        self::assertFalse(Cnpj::validar($cnpj));
    }

    public function testNormalizar(): void
    {
        self::assertSame('12ABC34501DE35', Cnpj::normalizar('12.abc.345/01de-35'));
        self::assertSame('11222333000181', Cnpj::normalizar(' 11.222.333/0001-81 '));
    }

    public function testFormatar(): void
    {
        self::assertSame('11.222.333/0001-81', Cnpj::formatar('11222333000181'));
        self::assertSame('12.ABC.345/01DE-35', Cnpj::formatar('12abc34501de35'));
        self::assertSame('', Cnpj::formatar('123'));
    }

    public function testDigitos(): void
    {
        self::assertSame('35', Cnpj::digitos('12ABC34501DE'));
        self::assertSame('81', Cnpj::digitos('112223330001'));
    }
}
