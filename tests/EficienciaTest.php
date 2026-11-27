<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Eficiencia as E;
use PHPUnit\Framework\TestCase;

final class EficienciaTest extends TestCase
{
    /** @param array<string, mixed> $campos */
    private function p(array $campos): array
    {
        return $campos + [
            'consultor'    => 10,
            'versao'       => 1,
            'tickets_id'   => 0,
            'data_aceite'  => null,
            'por_unidade'  => false,
            'recorrente'   => 0.0,
            'pontual'      => 0.0,
        ];
    }

    public function testConversaoEFechamentoDoConsultor(): void
    {
        $r = E::porConsultor([
            $this->p(['consultor' => 10, 'status' => 'aceita', 'versao' => 1, 'tickets_id' => 5, 'data_emissao' => '2026-11-03', 'data_aceite' => '2026-11-05', 'recorrente' => 1000.0, 'pontual' => 500.0]),
            $this->p(['consultor' => 10, 'status' => 'visualizada', 'tickets_id' => 6, 'data_emissao' => '2026-11-10']),
            $this->p(['consultor' => 10, 'status' => 'expirada', 'tickets_id' => 7, 'data_emissao' => '2026-11-02']),
        ], '2026-11-01', '2026-11-30');
        $this->assertSame(['enviadas' => 3, 'aceitas_envio' => 1, 'em_aberto' => 1, 'taxa' => 1 / 3, 'aceitas' => 1, 'recorrente' => 1000.0, 'pontual' => 500.0, 'por_unidade' => 0], $r[10]);
    }

    public function testRascunhoNuncaEnviadoNaoConta(): void
    {
        $this->assertFalse(E::enviada($this->p(['status' => 'rascunho', 'versao' => 1, 'data_emissao' => '2026-11-03'])));
        $this->assertTrue(E::enviada($this->p(['status' => 'rascunho', 'versao' => 2, 'data_emissao' => '2026-11-03'])));
    }

    public function testCanceladaSemEnvioNaoContaComoEnviada(): void
    {
        $this->assertFalse(E::enviada($this->p(['status' => 'cancelada', 'versao' => 1, 'tickets_id' => 0, 'data_emissao' => '2026-11-03'])));
        $this->assertTrue(E::enviada($this->p(['status' => 'cancelada', 'versao' => 1, 'tickets_id' => 9, 'data_emissao' => '2026-11-03'])));
    }

    public function testAceiteNoPeriodoDeEnvioAnterior(): void
    {
        $r = E::porConsultor([$this->p(['consultor' => 10, 'status' => 'aceita', 'tickets_id' => 5, 'data_emissao' => '2026-10-28', 'data_aceite' => '2026-11-02', 'recorrente' => 800.0])], '2026-11-01', '2026-11-30');
        $this->assertSame(0, $r[10]['enviadas']);
        $this->assertNull($r[10]['taxa']);
        $this->assertSame(1, $r[10]['aceitas']);
        $this->assertSame(800.0, $r[10]['recorrente']);
    }

    public function testPorUnidadeNaoSomaValor(): void
    {
        $r = E::porConsultor([$this->p(['consultor' => 10, 'status' => 'aceita', 'tickets_id' => 5, 'data_emissao' => '2026-11-03', 'data_aceite' => '2026-11-04', 'por_unidade' => true, 'recorrente' => 12.0])], '2026-11-01', '2026-11-30');
        $this->assertSame(1, $r[10]['aceitas']);
        $this->assertSame(0.0, $r[10]['recorrente']);
        $this->assertSame(1, $r[10]['por_unidade']);
    }

    public function testEquipeSomaMembrosERecalculaTaxa(): void
    {
        $linha = static fn (int $env, int $ac): array => ['enviadas' => $env, 'aceitas_envio' => $ac, 'em_aberto' => 0, 'taxa' => E::taxa($ac, $env), 'aceitas' => $ac, 'recorrente' => 100.0 * $ac, 'pontual' => 0.0, 'por_unidade' => 0];
        $r = E::porEquipe([10 => $linha(40, 12), 11 => $linha(10, 6)], [1 => [10, 11], 2 => [11], 3 => [99]]);
        $this->assertSame(50, $r[1]['enviadas']);
        $this->assertSame(18 / 50, $r[1]['taxa']);
        $this->assertSame(1800.0, $r[1]['recorrente']);
        $this->assertSame(10, $r[2]['enviadas']);  // consultor em duas equipes entra nas duas
        $this->assertSame(0, $r[3]['enviadas']);   // equipe sem linhas aparece zerada
        $this->assertNull($r[3]['taxa']);
    }

    public function testPeriodoInvalidoVoltaAoMesCorrente(): void
    {
        $hoje = new \DateTimeImmutable('2026-11-27');
        $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo(null, null, $hoje));
        $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo('abc', '2026-11-30', $hoje));
        $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo('2026-12-01', '2026-11-01', $hoje));
        $this->assertSame(['2026-11-01', '2026-11-30'], E::periodo('2026-02-30', '2026-11-30', $hoje)); // data inexistente
        $this->assertSame(['2026-10-01', '2026-10-31'], E::periodo('2026-10-01', '2026-10-31', $hoje));
    }

    public function testCelulaCsvNeutralizaFormula(): void
    {
        $this->assertSame("'=SOMA(A1)", E::celulaCsv('=SOMA(A1)'));
        $this->assertSame("'-1", E::celulaCsv('-1'));
        $this->assertSame('Equipe A', E::celulaCsv('Equipe A'));
    }

    public function testLinhasDeExportacaoSeguemOCabecalho(): void
    {
        $linhas = E::linhasExportacao([
            ['nome' => 'Ana', 'enviadas' => 10, 'aceitas_envio' => 6, 'em_aberto' => 1, 'taxa' => 0.6, 'aceitas' => 5, 'recorrente' => 18400.0, 'pontual' => 2000.5, 'por_unidade' => 1],
            ['nome' => 'Bruno', 'enviadas' => 0, 'aceitas_envio' => 0, 'em_aberto' => 0, 'taxa' => null, 'aceitas' => 0, 'recorrente' => 0.0, 'pontual' => 0.0, 'por_unidade' => 0],
        ]);

        $this->assertCount(count(E::CABECALHO_EXPORTACAO), $linhas[0]);
        // Valores crus: quem formata é cada formato de saída (a planilha precisa de números).
        $this->assertSame(['Ana', 10, 6, 1, 0.6, 5, 18400.0, 2000.5, 1], $linhas[0]);
        $this->assertSame(['Bruno', 0, 0, 0, null, 0, 0.0, 0.0, 0], $linhas[1]);
        $this->assertSame(E::COLUNA_TAXA, array_search(0.6, $linhas[0], true));
    }
}
