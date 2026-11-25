<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Marca;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarcaTest extends TestCase
{
    /** @return array<string, array{mixed, ?string}> */
    public static function cores(): array
    {
        return [
            'com #'           => ['#1A2B3C', '#1a2b3c'],
            'sem #'           => ['1a2b3c', '#1a2b3c'],
            'curta'           => ['#abc', '#aabbcc'],
            'espaços'         => ['  #123456 ', '#123456'],
            'vazia'           => ['', null],
            'nula'            => [null, null],
            'letra inválida'  => ['#12345g', null],
            'tamanho errado'  => ['#1234', null],
            'injeção de CSS'  => ['#123456;}body{display:none', null],
            'nome de cor'     => ['red', null],
        ];
    }

    #[DataProvider('cores')]
    public function testCorValida(mixed $entrada, ?string $esperado): void
    {
        self::assertSame($esperado, Marca::corValida($entrada));
    }

    public function testTextoRodape(): void
    {
        self::assertSame('Copyright © 2026 Exemplo | Desenvolvido por TI', Marca::textoRodape("  Copyright © 2026 Exemplo |\n Desenvolvido   por TI  "));
        self::assertSame('', Marca::textoRodape(null));
        self::assertSame(Marca::RODAPE_MAXIMO, mb_strlen(Marca::textoRodape(str_repeat('é', 300))));
    }

    public function testPadroesSaoValidos(): void
    {
        self::assertSame(Marca::COR_PRINCIPAL_PADRAO, Marca::corValida(Marca::COR_PRINCIPAL_PADRAO));
        self::assertSame(Marca::COR_DESTAQUE_PADRAO, Marca::corValida(Marca::COR_DESTAQUE_PADRAO));
    }
}
