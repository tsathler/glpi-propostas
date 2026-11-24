<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Visibilidade as V;
use PHPUnit\Framework\TestCase;

final class VisibilidadeTest extends TestCase
{
    private const CONSULTOR_A = 10;
    private const CONSULTOR_B = 11;
    private const GESTOR      = 20;
    private const OUTRO       = 30;

    /** Membros das equipes que o gestor gere. */
    private const DA_EQUIPE = [self::CONSULTOR_A, self::CONSULTOR_B];

    public function testSemLeituraNaoVeNada(): void
    {
        $this->assertFalse(V::podeVer(0, self::CONSULTOR_A, self::CONSULTOR_A, []));
        $this->assertFalse(V::podeVer(2 /* UPDATE */, self::CONSULTOR_A, self::CONSULTOR_A, []));
    }

    public function testMinhasVeSoAsProprias(): void
    {
        $this->assertTrue(V::podeVer(V::MINHAS, self::CONSULTOR_A, self::CONSULTOR_A, []));
        $this->assertFalse(V::podeVer(V::MINHAS, self::CONSULTOR_A, self::CONSULTOR_B, []));
        // Sem o nível "da equipe", ser Responsável não abre as propostas da equipe.
        $this->assertFalse(V::podeVer(V::MINHAS, self::GESTOR, self::CONSULTOR_A, self::DA_EQUIPE));
    }

    public function testEquipeVeAsPropriasEAsDosMembros(): void
    {
        $direitos = V::MINHAS | V::EQUIPE;
        $this->assertTrue(V::podeVer($direitos, self::GESTOR, self::GESTOR, self::DA_EQUIPE));
        $this->assertTrue(V::podeVer($direitos, self::GESTOR, self::CONSULTOR_A, self::DA_EQUIPE));
        $this->assertFalse(V::podeVer($direitos, self::GESTOR, self::OUTRO, self::DA_EQUIPE));
    }

    public function testSoONivelEquipeJaInclusAsProprias(): void
    {
        $this->assertTrue(V::podeVer(V::EQUIPE, self::GESTOR, self::GESTOR, []));
    }

    public function testTodasVeTudo(): void
    {
        $this->assertTrue(V::podeVer(V::TODAS, self::OUTRO, self::CONSULTOR_A, []));
    }

    public function testFiltroDaBusca(): void
    {
        $this->assertNull(V::consultoresVisiveis(V::MINHAS | V::TODAS, self::OUTRO, []));
        $this->assertSame([self::CONSULTOR_A], V::consultoresVisiveis(V::MINHAS, self::CONSULTOR_A, self::DA_EQUIPE));
        $this->assertSame(
            [self::GESTOR, self::CONSULTOR_A, self::CONSULTOR_B],
            V::consultoresVisiveis(V::MINHAS | V::EQUIPE, self::GESTOR, self::DA_EQUIPE)
        );
    }

    public function testBloqueioDeQuemNaoTemEquipe(): void
    {
        $this->assertTrue(V::podeCriar(V::MINHAS, false, false), 'equipes desligadas: sem bloqueio');
        $this->assertFalse(V::podeCriar(V::MINHAS, true, false));
        $this->assertTrue(V::podeCriar(V::MINHAS, true, true));
        $this->assertTrue(V::podeCriar(V::MINHAS | V::TODAS, true, false), 'quem vê todas não é bloqueado');
    }

    public function testAtribuirComNivelMinhas(): void
    {
        $this->assertTrue(V::podeAtribuir(V::MINHAS, self::CONSULTOR_A, self::CONSULTOR_A, true, true, []));
        $this->assertFalse(V::podeAtribuir(V::MINHAS, self::CONSULTOR_A, self::CONSULTOR_B, true, true, []));
        $this->assertFalse(V::podeAtribuir(V::MINHAS, self::CONSULTOR_A, self::CONSULTOR_B, false, false, []));
        // Consultor que saiu da equipe não pode se pôr numa proposta nova.
        $this->assertFalse(V::podeAtribuir(V::MINHAS, self::CONSULTOR_A, self::CONSULTOR_A, true, false, []));
    }

    public function testAtribuirComNivelEquipe(): void
    {
        $direitos = V::MINHAS | V::EQUIPE;
        $this->assertTrue(V::podeAtribuir($direitos, self::GESTOR, self::CONSULTOR_A, true, true, self::DA_EQUIPE));
        $this->assertFalse(V::podeAtribuir($direitos, self::GESTOR, self::OUTRO, true, true, self::DA_EQUIPE), 'consultor de outra equipe');
    }

    public function testAtribuirComNivelTodas(): void
    {
        $direitos = V::MINHAS | V::TODAS;
        $this->assertTrue(V::podeAtribuir($direitos, self::OUTRO, self::CONSULTOR_A, true, true, []));
        $this->assertFalse(V::podeAtribuir($direitos, self::OUTRO, self::CONSULTOR_A, true, false, []), 'consultor sem equipe');
        $this->assertTrue(V::podeAtribuir($direitos, self::OUTRO, self::OUTRO, true, false, []), 'quem vê todas, em proposta própria');
        $this->assertTrue(V::podeAtribuir($direitos, self::OUTRO, self::CONSULTOR_A, false, false, []), 'equipes desligadas');
    }

    public function testAtribuirUsuarioInvalido(): void
    {
        $this->assertFalse(V::podeAtribuir(V::TODAS, self::OUTRO, 0, false, false, []));
        $this->assertFalse(V::podeAtribuir(0, self::OUTRO, self::OUTRO, false, false, []));
    }

    /** Todos os membros de todas as equipes (o gestor inclusive). */
    private const TODOS_MEMBROS = [self::CONSULTOR_A, self::CONSULTOR_B, self::GESTOR];

    public function testConsultoresSemEquipesNaoFiltra(): void
    {
        $this->assertNull(V::consultoresPermitidos(V::MINHAS, self::CONSULTOR_A, false, false, [], []));
    }

    public function testConsultoresPorNivel(): void
    {
        $this->assertSame([self::CONSULTOR_A], V::consultoresPermitidos(V::MINHAS, self::CONSULTOR_A, true, true, self::TODOS_MEMBROS, []));
        $this->assertSame([], V::consultoresPermitidos(V::MINHAS, self::OUTRO, true, false, self::TODOS_MEMBROS, []), 'sem equipe');
        $this->assertSame(
            [self::GESTOR, self::CONSULTOR_A, self::CONSULTOR_B],
            V::consultoresPermitidos(V::MINHAS | V::EQUIPE, self::GESTOR, true, true, self::TODOS_MEMBROS, self::DA_EQUIPE)
        );
        $this->assertSame(
            [self::OUTRO, self::CONSULTOR_A, self::CONSULTOR_B, self::GESTOR],
            V::consultoresPermitidos(V::MINHAS | V::TODAS, self::OUTRO, true, false, self::TODOS_MEMBROS, [])
        );
        $this->assertSame([], V::consultoresPermitidos(0, self::GESTOR, true, true, self::TODOS_MEMBROS, self::DA_EQUIPE), 'sem leitura');
    }

    /**
     * A lista do campo e a validação ao salvar não podem divergir: quem aparece pode ser escolhido,
     * e quem pode ser escolhido aparece.
     */
    public function testConsultoresBatemComPodeAtribuir(): void
    {
        $candidatos = [self::CONSULTOR_A, self::CONSULTOR_B, self::GESTOR, self::OUTRO];
        $niveis = [V::MINHAS, V::MINHAS | V::EQUIPE, V::MINHAS | V::TODAS, V::EQUIPE, V::TODAS];

        foreach ($niveis as $direitos) {
            foreach ($candidatos as $usuario) {
                $em_equipe = in_array($usuario, self::TODOS_MEMBROS, true);
                $geridos = $usuario === self::GESTOR ? self::DA_EQUIPE : [];
                $lista = V::consultoresPermitidos($direitos, $usuario, true, $em_equipe, self::TODOS_MEMBROS, $geridos);
                foreach ($candidatos as $alvo) {
                    $this->assertSame(
                        V::podeAtribuir($direitos, $usuario, $alvo, true, in_array($alvo, self::TODOS_MEMBROS, true), $geridos),
                        in_array($alvo, $lista, true),
                        sprintf('direitos %d, usuário %d, alvo %d', $direitos, $usuario, $alvo)
                    );
                }
            }
        }
    }
}
