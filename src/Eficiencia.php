<?php

/**
 * Propostas - conta do relatório de eficiência (conversão e fechamento por consultor e equipe).
 *
 * Sem dependência do GLPI: recebe as propostas já carregadas (testável sozinho).
 *
 * Linha de proposta (entrada): consultor int, status string, versao int, tickets_id int,
 * data_emissao 'Y-m-d', data_aceite ?'Y-m-d', por_unidade bool, recorrente float, pontual float.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Eficiencia
{
    // Os mesmos valores de Proposta::STATUS_* (Proposta depende do GLPI e não entra nos testes).
    private const RASCUNHO  = 'rascunho';
    private const ACEITA    = 'aceita';
    private const CANCELADA = 'cancelada';
    private const EM_ABERTO = ['enviada', 'visualizada', 'duvida', 'rascunho'];

    private const VAZIA = [
        'enviadas'      => 0,
        'aceitas_envio' => 0,
        'em_aberto'     => 0,
        'taxa'          => null,
        'aceitas'       => 0,
        'recorrente'    => 0.0,
        'pontual'       => 0.0,
        'por_unidade'   => 0,
    ];

    /**
     * A proposta chegou a ser enviada? Todo rascunho nasce com data de emissão, então ela não basta.
     * Rascunho só conta reaberto (versão > 1); cancelada, se teve chamado (aberto ao gerar o link)
     * ou passou de uma versão.
     */
    public static function enviada(array $p): bool
    {
        // shortcut: rascunho com chamado vinculado à mão e cancelado sem envio conta como enviado, e enviada cuja abertura de chamado falhou e foi cancelada na versão 1 não conta; guardar a data de envio numa coluna se isso incomodar
        return match ($p['status']) {
            self::RASCUNHO  => (int) $p['versao'] > 1,
            self::CANCELADA => (int) $p['tickets_id'] > 0 || (int) $p['versao'] > 1,
            default         => true,
        };
    }

    /**
     * Conversão pela data de envio e fechamento (com valor) pela data do aceite, ambos em [inicio, fim].
     *
     * @param list<array> $propostas
     * @return array<int, array> chave = id do consultor
     */
    public static function porConsultor(array $propostas, string $inicio, string $fim): array
    {
        $linhas = [];
        foreach ($propostas as $p) {
            $l = $linhas[(int) $p['consultor']] ?? self::VAZIA;

            if (self::enviada($p) && self::dentro($p['data_emissao'] ?? null, $inicio, $fim)) {
                $l['enviadas']++;
                if ($p['status'] === self::ACEITA) {
                    $l['aceitas_envio']++;
                } elseif (in_array($p['status'], self::EM_ABERTO, true)) {
                    $l['em_aberto']++;
                }
            }

            if ($p['status'] === self::ACEITA && self::dentro($p['data_aceite'] ?? null, $inicio, $fim)) {
                $l['aceitas']++;
                if ($p['por_unidade']) {
                    $l['por_unidade']++;
                } else {
                    $l['recorrente'] += (float) $p['recorrente'];
                    $l['pontual']    += (float) $p['pontual'];
                }
            }

            $linhas[(int) $p['consultor']] = $l;
        }

        foreach ($linhas as &$l) {
            $l['taxa'] = self::taxa($l['aceitas_envio'], $l['enviadas']);
        }

        return $linhas;
    }

    /**
     * Soma as linhas dos membros de cada equipe (consultor em duas equipes entra nas duas).
     *
     * @param array<int, array> $por_consultor
     * @param array<int, list<int>> $membros_por_equipe
     * @return array<int, array> chave = id do grupo
     */
    public static function porEquipe(array $por_consultor, array $membros_por_equipe): array
    {
        $equipes = [];
        foreach ($membros_por_equipe as $grupo => $membros) {
            $soma = self::VAZIA;
            foreach (array_unique($membros) as $id) {
                if (!isset($por_consultor[$id])) {
                    continue;
                }
                foreach (['enviadas', 'aceitas_envio', 'em_aberto', 'aceitas', 'recorrente', 'pontual', 'por_unidade'] as $campo) {
                    $soma[$campo] += $por_consultor[$id][$campo];
                }
            }
            $soma['taxa'] = self::taxa($soma['aceitas_envio'], $soma['enviadas']);
            $equipes[$grupo] = $soma;
        }

        return $equipes;
    }

    /** Fração 0–1; null sem nenhuma enviada. */
    public static function taxa(int $aceitas, int $enviadas): ?float
    {
        return $enviadas === 0 ? null : $aceitas / $enviadas;
    }

    /**
     * Período pedido, se as duas datas forem válidas e em ordem; senão, o mês de $hoje.
     *
     * @return array{0: string, 1: string}
     */
    public static function periodo(?string $inicio, ?string $fim, \DateTimeImmutable $hoje): array
    {
        if (self::dataValida($inicio) && self::dataValida($fim) && $inicio <= $fim) {
            return [$inicio, $fim];
        }

        return [$hoje->format('Y-m-01'), $hoje->format('Y-m-t')];
    }

    /** Impede que a planilha leia a célula como fórmula. */
    public static function celulaCsv(string $valor): string
    {
        return $valor !== '' && str_contains("=+-@\t\r", $valor[0]) ? "'" . $valor : $valor;
    }

    /** Colunas de todos os formatos de exportação, na ordem de linhasExportacao(). */
    public const CABECALHO_EXPORTACAO = [
        'Nome', 'Enviadas', 'Aceitas (das enviadas)', 'Em aberto', 'Taxa',
        'Aceitas no período', 'R$ mensal', 'R$ pontual', 'Por unidade',
    ];

    /** Índices (base 0) das colunas que cada formato precisa formatar. */
    public const COLUNA_TAXA = 4;
    public const COLUNAS_VALOR = [6, 7];

    /**
     * Linhas nomeadas (linha de resultado + 'nome') na ordem do cabeçalho, com valores crus:
     * taxa em fração 0–1 ou null, valores em float. Cada formato de saída formata do seu jeito.
     *
     * @param list<array> $linhas
     * @return list<list<mixed>>
     */
    public static function linhasExportacao(array $linhas): array
    {
        return array_map(static fn (array $l): array => [
            $l['nome'], $l['enviadas'], $l['aceitas_envio'], $l['em_aberto'], $l['taxa'],
            $l['aceitas'], $l['recorrente'], $l['pontual'], $l['por_unidade'],
        ], $linhas);
    }

    private static function dataValida(?string $data): bool
    {
        if ($data === null) {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $data);

        return $d !== false && $d->format('Y-m-d') === $data;
    }

    private static function dentro(?string $data, string $inicio, string $fim): bool
    {
        return $data !== null && $data !== '' && $data >= $inicio && $data <= $fim;
    }
}
