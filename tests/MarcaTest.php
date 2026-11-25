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

    public function testTextoComLimiteProprio(): void
    {
        self::assertSame(Marca::PRIVACIDADE_MAXIMO, mb_strlen(Marca::textoRodape(str_repeat('á', 1500), Marca::PRIVACIDADE_MAXIMO)));
        self::assertSame('Linha um linha dois', Marca::textoRodape("Linha um

linha   dois", Marca::PRIVACIDADE_MAXIMO));
    }

    public function testPadroesSaoValidos(): void
    {
        self::assertSame(Marca::COR_PRINCIPAL_PADRAO, Marca::corValida(Marca::COR_PRINCIPAL_PADRAO));
        self::assertSame(Marca::COR_DESTAQUE_PADRAO, Marca::corValida(Marca::COR_DESTAQUE_PADRAO));
    }

    public function testArquivosEnviadosDeCampoMultiplo(): void
    {
        $campo = [
            'name'     => ['a.png', '', 'c.webp'],
            'type'     => ['image/png', '', 'image/webp'],
            'tmp_name' => ['/tmp/a', '', '/tmp/c'],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_INI_SIZE],
            'size'     => [10, 0, 0],
        ];
        $arquivos = Marca::arquivosEnviados($campo);

        self::assertCount(2, $arquivos, 'o campo vazio sai');
        self::assertSame(['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 10], $arquivos[0]);
        self::assertSame(UPLOAD_ERR_INI_SIZE, $arquivos[1]['error'], 'o erro segue para a validação');
    }

    public function testArquivosEnviadosDeCampoSimplesEVazio(): void
    {
        $simples = ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 10];

        self::assertSame([$simples], Marca::arquivosEnviados($simples));
        self::assertSame([], Marca::arquivosEnviados([]));
        self::assertSame([], Marca::arquivosEnviados(['name' => [''], 'type' => [''], 'tmp_name' => [''], 'error' => [UPLOAD_ERR_NO_FILE], 'size' => [0]]));
    }

    public function testContraste(): void
    {
        self::assertEqualsWithDelta(21.0, Marca::contraste('#000000', '#ffffff'), 0.01);
        self::assertEqualsWithDelta(1.0, Marca::contraste('#777777', '#777777'), 0.0001);
    }

    /** @return array<string, array{string}> */
    public static function coresParaTextoBranco(): array
    {
        return [
            'laranja'       => ['#f97316'],
            'amarelo'       => ['#facc15'],
            'branco'        => ['#ffffff'],
            'azul padrão'   => [Marca::COR_DESTAQUE_PADRAO],
            'escuro padrão' => [Marca::COR_PRINCIPAL_PADRAO],
        ];
    }

    #[DataProvider('coresParaTextoBranco')]
    public function testLegivelComBranco(string $cor): void
    {
        $legivel = Marca::legivelComBranco($cor);

        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $legivel);
        self::assertGreaterThanOrEqual(Marca::CONTRASTE_MINIMO, Marca::contraste($legivel, '#ffffff'));
        if (Marca::contraste($cor, '#ffffff') >= Marca::CONTRASTE_MINIMO) {
            self::assertSame($cor, $legivel, 'cor que já é legível não muda');
        }
    }

    public function testDimensoesAceitas(): void
    {
        self::assertTrue(Marca::dimensoesAceitas([800, 800]));
        self::assertTrue(Marca::dimensoesAceitas([5000, 5000]));
        self::assertFalse(Marca::dimensoesAceitas([20000, 20000]), '400 milhões de pixels');
        self::assertFalse(Marca::dimensoesAceitas([0, 10]));
        self::assertFalse(Marca::dimensoesAceitas(false));
    }

    public function testVagaLivre(): void
    {
        self::assertSame(1, Marca::vagaLivre([]));
        self::assertSame(2, Marca::vagaLivre([1, 3]));
        self::assertSame(4, Marca::vagaLivre([3, 1, 2]));
        self::assertNull(Marca::vagaLivre(range(1, Marca::DECORACAO_MAXIMO)));
    }
}
