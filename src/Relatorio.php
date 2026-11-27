<?php

/**
 * Propostas - relatório de eficiência dos consultores (Ferramentas > Relatório de propostas).
 *
 * Carrega as propostas que o usuário vê no formato de entrada de Eficiencia; a conta fica lá.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonGLPI;
use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;

final class Relatorio extends CommonGLPI
{
    public static $rightname = 'plugin_propostas_proposta';

    public static function getTypeName($nb = 0): string
    {
        return __('Relatório de propostas', 'propostas');
    }

    public static function getIcon(): string
    {
        return 'ti ti-chart-bar';
    }

    /** Qualquer nível de leitura de propostas; o que aparece segue a visibilidade. */
    public static function canView(): bool
    {
        return Proposta::canView();
    }

    /**
     * Propostas visíveis enviadas ou aceitas no período, no formato de entrada de Eficiencia.
     *
     * @return list<array>
     */
    public static function propostas(string $inicio, string $fim): array
    {
        global $DB;

        $tabela = Proposta::getTable();
        $aceites_no_periodo = new QuerySubQuery([
            'SELECT' => 'plugin_propostas_propostas_id',
            'FROM'   => Resposta::TABELA,
            'WHERE'  => [
                'tipo' => Resposta::TIPO_ACEITE,
                ['date_creation' => ['>=', $inicio . ' 00:00:00']],
                ['date_creation' => ['<=', $fim . ' 23:59:59']],
            ],
        ]);

        $propostas = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'users_id_consultor', 'status', 'versao', 'tickets_id', 'data_emissao', 'exibicao'],
            'FROM'   => $tabela,
            // Chaves numéricas: a restrição de entidades também pode vir com 'OR', e o + manteria só a primeira.
            'WHERE'  => Proposta::criteriosVisibilidade() + [
                getEntitiesRestrictCriteria($tabela, '', '', true),
                ['OR' => [
                    ['AND' => [
                        [$tabela . '.data_emissao' => ['>=', $inicio]],
                        [$tabela . '.data_emissao' => ['<=', $fim]],
                    ]],
                    [$tabela . '.id' => $aceites_no_periodo],
                ]],
            ],
        ]) as $row) {
            $propostas[(int) $row['id']] = [
                'consultor'    => (int) $row['users_id_consultor'],
                'status'       => (string) $row['status'],
                'versao'       => (int) $row['versao'],
                'tickets_id'   => (int) $row['tickets_id'],
                'data_emissao' => $row['data_emissao'] === null ? null : (string) $row['data_emissao'],
                'data_aceite'  => null,
                'por_unidade'  => ($row['exibicao'] ?? '') === Calculo::EXIBICAO_UNIDADE,
                'recorrente'   => 0.0,
                'pontual'      => 0.0,
            ];
        }

        $aceitas = array_keys(array_filter($propostas, static fn (array $p): bool => $p['status'] === Proposta::STATUS_ACEITA));
        if ($aceitas === []) {
            return array_values($propostas);
        }

        // O aceite que vale é o último: a proposta só continua aceita na versão aceita.
        foreach ($DB->request([
            'SELECT'  => ['plugin_propostas_propostas_id', new QueryExpression('MAX(' . $DB->quoteName('date_creation') . ') AS data_aceite')],
            'FROM'    => Resposta::TABELA,
            'WHERE'   => ['tipo' => Resposta::TIPO_ACEITE, 'plugin_propostas_propostas_id' => $aceitas],
            'GROUPBY' => 'plugin_propostas_propostas_id',
        ]) as $row) {
            $propostas[(int) $row['plugin_propostas_propostas_id']]['data_aceite'] = substr((string) $row['data_aceite'], 0, 10);
        }

        $com_valor = array_values(array_filter($aceitas, static fn (int $id): bool => !$propostas[$id]['por_unidade']));
        if ($com_valor !== []) {
            $itens = [];
            foreach ($DB->request([
                'SELECT' => ['plugin_propostas_propostas_id', 'tipo', 'quantidade', 'valor_unitario', 'desconto_pct'],
                'FROM'   => PropostaItem::getTable(),
                'WHERE'  => ['plugin_propostas_propostas_id' => $com_valor],
            ]) as $row) {
                $itens[(int) $row['plugin_propostas_propostas_id']][] = $row;
            }
            foreach ($itens as $id => $linhas) {
                $totais = Calculo::totais($linhas);
                $propostas[$id]['recorrente'] = $totais[Calculo::TIPO_RECORRENTE];
                $propostas[$id]['pontual']    = $totais[Calculo::TIPO_PONTUAL];
            }
        }

        return array_values($propostas);
    }

    /**
     * Equipes que o usuário pode ver no relatório: todas com o nível Todas, as que gere com o
     * nível da equipe, nenhuma com equipes desligadas ou só as próprias.
     *
     * @return list<int>
     */
    public static function equipesVisiveis(): array
    {
        if (!Equipe::ativas()) {
            return [];
        }
        $direitos = Equipe::direitosAtuais();
        if (($direitos & Visibilidade::TODAS) !== 0) {
            return Equipe::equipes();
        }
        if (($direitos & Visibilidade::EQUIPE) !== 0) {
            return Equipe::equipesGeridas(Equipe::usuarioAtual());
        }

        return [];
    }
}
