<?php

/**
 * Propostas - equipes de consultores (grupos filhos de um grupo pai configurado) e o gestor
 * como observador nos chamados das propostas.
 *
 * Equipe = grupo filho direto do grupo pai escolhido em Configuração > Plug-ins > Propostas.
 * Gestor = membro marcado como Responsável no grupo (glpi_groups_users.is_manager = 1).
 * Sem grupo pai configurado, as regras de equipe ficam desligadas.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonITILActor;
use Session;

final class Equipe
{
    /** Observadores que o próprio plugin pôs nos chamados (só esses a sincronização remove). */
    public const TABELA_OBSERVADORES = 'glpi_plugin_propostas_observadores';

    /** @var list<int>|null */
    private static ?array $cache_equipes = null;

    public static function grupoPai(): int
    {
        return (int) (\Config::getConfigurationValue('plugin:propostas', 'equipes_groups_id') ?? 0);
    }

    public static function ativas(): bool
    {
        return self::grupoPai() > 0;
    }

    /** Limpa o cache da requisição (depois de mudar grupos ou a configuração). */
    public static function limparCache(): void
    {
        self::$cache_equipes = null;
    }

    /**
     * Ids dos grupos que são equipes (filhos diretos do grupo pai).
     *
     * @return list<int>
     */
    public static function equipes(): array
    {
        global $DB;

        if (self::$cache_equipes !== null) {
            return self::$cache_equipes;
        }
        $pai = self::grupoPai();
        if ($pai <= 0) {
            return self::$cache_equipes = [];
        }

        $ids = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => \Group::getTable(), 'WHERE' => ['groups_id' => $pai]]) as $row) {
            $ids[] = (int) $row['id'];
        }

        return self::$cache_equipes = $ids;
    }

    /**
     * Junta o filtro pedido com a restrição "só grupos que são equipes". Um groups_id pedido é
     * cruzado com as equipes (nunca trocado por elas: a união de arrays com + manteria só a
     * chave da esquerda e descartaria o filtro). Sem dependência do GLPI.
     *
     * @param list<int> $equipes
     * @param array<string, mixed> $where
     * @return array<string, mixed>|null null = nenhum grupo possível (consulta vazia)
     */
    public static function restringirAsEquipes(array $equipes, array $where): ?array
    {
        $grupos = array_values(array_unique(array_map('intval', $equipes)));
        if (array_key_exists('groups_id', $where)) {
            $pedidos = array_map('intval', (array) $where['groups_id']);
            $grupos  = array_values(array_intersect($grupos, $pedidos));
        }
        if ($grupos === []) {
            return null;
        }
        $where['groups_id'] = $grupos;

        return $where;
    }

    /**
     * Vínculos usuário/equipe.
     *
     * @param array<string, mixed> $where
     * @return list<array{users_id: int, groups_id: int, is_manager: bool}>
     */
    private static function vinculos(array $where = []): array
    {
        global $DB;

        $where = self::restringirAsEquipes(self::equipes(), $where);
        if ($where === null) {
            return [];
        }

        $linhas = [];
        foreach ($DB->request([
            'SELECT' => ['users_id', 'groups_id', 'is_manager'],
            'FROM'   => \Group_User::getTable(),
            'WHERE'  => $where,
        ]) as $row) {
            $linhas[] = [
                'users_id'   => (int) $row['users_id'],
                'groups_id'  => (int) $row['groups_id'],
                'is_manager' => (bool) $row['is_manager'],
            ];
        }

        return $linhas;
    }

    /**
     * Todos os membros (consultores e Responsáveis) de todas as equipes.
     *
     * @return list<int>
     */
    public static function membrosDasEquipes(): array
    {
        return array_values(array_unique(array_column(self::vinculos(), 'users_id')));
    }

    public static function usuarioEmEquipe(int $users_id): bool
    {
        return $users_id > 0 && self::vinculos(['users_id' => $users_id]) !== [];
    }

    /**
     * Equipes em que o usuário é Responsável.
     *
     * @return list<int>
     */
    public static function equipesGeridas(int $users_id): array
    {
        if ($users_id <= 0) {
            return [];
        }

        return array_values(array_unique(array_column(self::vinculos(['users_id' => $users_id, 'is_manager' => 1]), 'groups_id')));
    }

    /**
     * Membros (inclusive Responsáveis) das equipes que o usuário gere, sem ele mesmo.
     *
     * @return list<int>
     */
    public static function membrosGeridos(int $users_id): array
    {
        $equipes = self::equipesGeridas($users_id);
        if ($equipes === []) {
            return [];
        }
        $ids = array_column(self::vinculos(['groups_id' => $equipes]), 'users_id');

        return array_values(array_diff(array_unique($ids), [$users_id]));
    }

    /**
     * Gestores (Responsáveis) das equipes do consultor, sem ele mesmo.
     *
     * @return list<int>
     */
    public static function gestoresDoConsultor(int $users_id): array
    {
        if ($users_id <= 0) {
            return [];
        }
        $equipes = array_column(self::vinculos(['users_id' => $users_id]), 'groups_id');
        if ($equipes === []) {
            return [];
        }
        $ids = array_column(self::vinculos(['groups_id' => array_values(array_unique($equipes)), 'is_manager' => 1]), 'users_id');

        return array_values(array_diff(array_unique($ids), [$users_id]));
    }

    /** Direitos do perfil ativo no direito Propostas. */
    public static function direitosAtuais(): int
    {
        return (int) ($_SESSION['glpiactiveprofile'][Proposta::$rightname] ?? 0);
    }

    public static function usuarioAtual(): int
    {
        return (int) (Session::getLoginUserID() ?: 0);
    }

    /* ------------------------------------------------------------------
     * Avisos de cadastro (página de configuração)
     * ------------------------------------------------------------------ */

    /**
     * @return list<string>
     */
    public static function avisos(): array
    {
        if (!self::ativas()) {
            return [];
        }

        $avisos = [];
        $como_consultor = [];
        $gestores = [];
        foreach (self::vinculos() as $v) {
            if ($v['is_manager']) {
                $gestores[$v['groups_id']][] = $v['users_id'];
            } else {
                $como_consultor[$v['users_id']][] = $v['groups_id'];
            }
        }

        foreach ($como_consultor as $users_id => $grupos) {
            $grupos = array_values(array_unique($grupos));
            if (count($grupos) > 1) {
                $avisos[] = sprintf(
                    __('%1$s está como consultor em mais de uma equipe: %2$s.', 'propostas'),
                    getUserName($users_id),
                    implode(', ', array_map(static fn (int $g): string => \Dropdown::getDropdownName('glpi_groups', $g), $grupos))
                );
            }
        }
        foreach (self::equipes() as $groups_id) {
            $n = count(array_unique($gestores[$groups_id] ?? []));
            $nome = \Dropdown::getDropdownName('glpi_groups', $groups_id);
            if ($n === 0) {
                $avisos[] = sprintf(__('A equipe %s não tem Responsável (gestor).', 'propostas'), $nome);
            } elseif ($n > 1) {
                $avisos[] = sprintf(__('A equipe %s tem mais de um Responsável (gestor).', 'propostas'), $nome);
            }
        }

        return $avisos;
    }

    /* ------------------------------------------------------------------
     * Gestor como observador nos chamados das propostas
     * ------------------------------------------------------------------ */

    /**
     * Acerta os observadores postos pelo plugin nestes chamados. Chamados solucionados ou
     * fechados não mudam (histórico). Nunca remove um observador incluído à mão.
     *
     * @param list<int> $tickets
     */
    public static function sincronizarChamados(array $tickets): void
    {
        foreach (array_unique(array_filter(array_map('intval', $tickets), static fn (int $t): bool => $t > 0)) as $tickets_id) {
            try {
                self::sincronizarChamado($tickets_id);
            } catch (\Throwable $e) {
                \Toolbox::logInFile('propostas', sprintf("Sincronização de observadores do chamado %d falhou: %s\n", $tickets_id, $e->getMessage()));
            }
        }
    }

    /** Todos os chamados abertos de propostas e os que têm observador posto pelo plugin. */
    public static function sincronizarTudo(): void
    {
        global $DB;

        self::limparCache();
        $tickets = [];
        foreach ($DB->request([
            'SELECT'   => ['tickets_id'],
            'DISTINCT' => true,
            'FROM'     => Proposta::getTable(),
            'WHERE'    => ['tickets_id' => ['>', 0]],
        ]) as $row) {
            $tickets[] = (int) $row['tickets_id'];
        }
        if ($DB->tableExists(self::TABELA_OBSERVADORES)) {
            foreach ($DB->request(['SELECT' => ['tickets_id'], 'DISTINCT' => true, 'FROM' => self::TABELA_OBSERVADORES]) as $row) {
                $tickets[] = (int) $row['tickets_id'];
            }
        }
        if ($tickets === []) {
            return;
        }
        // Só os chamados em andamento (sincronizarChamado ignora os solucionados e fechados, que com o tempo são a maioria).
        $abertos = [];
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => \Ticket::getTable(),
            'WHERE'  => ['id' => array_values(array_unique($tickets)), 'NOT' => ['status' => array_merge(\Ticket::getSolvedStatusArray(), \Ticket::getClosedStatusArray())]],
        ]) as $row) {
            $abertos[] = (int) $row['id'];
        }
        self::sincronizarChamados($abertos);
    }

    private static function sincronizarChamado(int $tickets_id): void
    {
        global $DB;

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            $DB->delete(self::TABELA_OBSERVADORES, ['tickets_id' => $tickets_id]);
            return;
        }
        $encerrado = array_merge(\Ticket::getSolvedStatusArray(), \Ticket::getClosedStatusArray());
        if (in_array((int) $ticket->fields['status'], $encerrado, false)) {
            return;
        }

        // Quem deve observar: os gestores dos consultores de todas as propostas deste chamado.
        $desejados = [];
        if (self::ativas()) {
            foreach ($DB->request([
                'SELECT'   => ['users_id_consultor'],
                'DISTINCT' => true,
                'FROM'     => Proposta::getTable(),
                'WHERE'    => ['tickets_id' => $tickets_id],
            ]) as $row) {
                $desejados = array_merge($desejados, self::gestoresDoConsultor((int) $row['users_id_consultor']));
            }
        }
        $desejados = array_values(array_unique($desejados));

        $postos_pelo_plugin = array_map('intval', array_column(iterator_to_array($DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => self::TABELA_OBSERVADORES,
            'WHERE'  => ['tickets_id' => $tickets_id],
        ]), false), 'users_id'));

        $observadores = array_map('intval', array_column(iterator_to_array($DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => \Ticket_User::getTable(),
            'WHERE'  => ['tickets_id' => $tickets_id, 'type' => CommonITILActor::OBSERVER],
        ]), false), 'users_id'));

        foreach (array_diff($postos_pelo_plugin, $desejados) as $users_id) {
            (new \Ticket_User())->deleteByCriteria([
                'tickets_id' => $tickets_id,
                'users_id'   => $users_id,
                'type'       => CommonITILActor::OBSERVER,
            ], true);
            $DB->delete(self::TABELA_OBSERVADORES, ['tickets_id' => $tickets_id, 'users_id' => $users_id]);
        }

        foreach ($desejados as $users_id) {
            if (in_array($users_id, $observadores, true)) {
                continue; // já observa (posto pelo plugin ou à mão)
            }
            $ok = (new \Ticket_User())->add([
                'tickets_id'    => $tickets_id,
                'users_id'      => $users_id,
                'type'          => CommonITILActor::OBSERVER,
                '_disablenotif' => true,
            ]);
            if ($ok && !in_array($users_id, $postos_pelo_plugin, true)) {
                $DB->insert(self::TABELA_OBSERVADORES, [
                    'tickets_id'    => $tickets_id,
                    'users_id'      => $users_id,
                    'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /* ------------------------------------------------------------------
     * Ganchos de grupos (setup.php)
     * ------------------------------------------------------------------ */

    /** Entrada, saída ou mudança de Responsável numa equipe. */
    public static function aoMudarMembro(\CommonDBTM $item): void
    {
        // Alteração que não mexe em grupo nem em Responsável (ex.: "receber notificações") não muda observadores.
        if (!empty($item->updates) && array_intersect(['groups_id', 'is_manager'], $item->updates) === []) {
            return;
        }
        $grupo = (int) ($item->fields['groups_id'] ?? 0);
        $antes = (int) ($item->oldvalues['groups_id'] ?? $grupo);
        self::limparCache();
        $equipes = self::equipes();
        if (in_array($grupo, $equipes, true) || in_array($antes, $equipes, true)) {
            self::sincronizarTudo();
        }
    }

    /** Grupo que passa a ser, ou deixa de ser, equipe (pai trocado) ou equipe excluída. */
    public static function aoMudarGrupo(\CommonDBTM $item): void
    {
        $pai = self::grupoPai();
        if ($pai <= 0) {
            return;
        }
        $agora = (int) ($item->fields['groups_id'] ?? 0);
        $antes = array_key_exists('groups_id', $item->oldvalues) ? (int) $item->oldvalues['groups_id'] : $agora;
        if ($agora === $pai || $antes === $pai || (int) $item->getID() === $pai) {
            self::sincronizarTudo();
        }
    }
}
