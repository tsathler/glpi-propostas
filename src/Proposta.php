<?php

/**
 * Propostas - propostas comerciais no GLPI.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBTM;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Log;
use Session;

class Proposta extends CommonDBTM
{
    public static $rightname = 'plugin_propostas_proposta';

    public $dohistory = true;

    public const STATUS_RASCUNHO   = 'rascunho';
    public const STATUS_ENVIADA    = 'enviada';
    public const STATUS_VISUALIZADA = 'visualizada';
    public const STATUS_ACEITA     = 'aceita';
    public const STATUS_DUVIDA     = 'duvida';
    public const STATUS_EXPIRADA   = 'expirada';
    public const STATUS_CANCELADA  = 'cancelada';

    public static function getTypeName($nb = 0): string
    {
        return _n('Proposta', 'Propostas', $nb, 'propostas');
    }

    public static function getIcon(): string
    {
        return 'ti ti-file-dollar';
    }

    /* ------------------------------------------------------------------
     * Níveis de leitura: as próprias, as da equipe ou todas
     * ------------------------------------------------------------------ */

    public function getRights($interface = 'central')
    {
        $values = parent::getRights($interface);
        $values[Visibilidade::MINHAS] = [
            'short' => __('Ler as próprias', 'propostas'),
            'long'  => __('Ler as propostas em que é o consultor', 'propostas'),
        ];
        $values[Visibilidade::EQUIPE] = [
            'short' => __('Ler da equipe', 'propostas'),
            'long'  => __('Ler e agir nas propostas dos consultores das equipes em que é Responsável', 'propostas'),
        ];
        $values[Visibilidade::TODAS] = [
            'short' => __('Ler todas', 'propostas'),
            'long'  => __('Ler e agir em todas as propostas das entidades do perfil', 'propostas'),
        ];

        return $values;
    }

    public static function canView(): bool
    {
        return Session::haveRightsOr(self::$rightname, Visibilidade::LEITURA);
    }

    /** O usuário atual vê as propostas deste consultor? */
    public static function podeVerDoConsultor(int $consultor): bool
    {
        $direitos = Equipe::direitosAtuais();
        $usuario  = Equipe::usuarioAtual();
        // As equipes só são consultadas quando o nível "da equipe" decide.
        $membros  = ($direitos & Visibilidade::EQUIPE) !== 0 ? Equipe::membrosGeridos($usuario) : [];

        return Visibilidade::podeVer($direitos, $usuario, $consultor, $membros);
    }

    /**
     * Filtro da lista e da busca (hook addDefaultWhere). Vazio = sem filtro.
     *
     * @return array<string, mixed>
     */
    public static function criteriosVisibilidade(): array
    {
        $usuario = Equipe::usuarioAtual();
        $ids = Visibilidade::consultoresVisiveis(Equipe::direitosAtuais(), $usuario, Equipe::membrosGeridos($usuario));

        return $ids === null ? [] : [self::getTable() . '.users_id_consultor' => $ids];
    }

    /**
     * Ids das propostas que o usuário vê (nível de leitura + entidades ativas), como subconsulta,
     * para filtrar itens e blocos na busca e na API.
     */
    public static function subconsultaVisiveis(): \Glpi\DBAL\QuerySubQuery
    {
        return new \Glpi\DBAL\QuerySubQuery([
            'SELECT' => self::getTable() . '.id',
            'FROM'   => self::getTable(),
            'WHERE'  => self::criteriosVisibilidade() + getEntitiesRestrictCriteria(self::getTable(), '', '', true),
        ]);
    }

    /**
     * Validade vinda do formulário (quando veio): de 1 a 365 dias. Mensagem na tela se não.
     *
     * @param array<string, mixed> $input
     */
    public static function conferirValidade(array $input): bool
    {
        if (!array_key_exists('validade_dias', $input) || Calculo::validadeValida($input['validade_dias'])) {
            return true;
        }
        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('A validade deve ser de %1$d a %2$d dias.', 'propostas'),
            Calculo::VALIDADE_MINIMA,
            Calculo::VALIDADE_MAXIMA
        )), false, ERROR);

        return false;
    }

    /** Pode criar ou duplicar (com as equipes ligadas, só quem está numa equipe ou vê todas). */
    public static function podeCriarAgora(): bool
    {
        return Visibilidade::podeCriar(
            Equipe::direitosAtuais(),
            Equipe::ativas(),
            Equipe::usuarioEmEquipe(Equipe::usuarioAtual())
        );
    }

    private function visivelParaUsuario(): bool
    {
        return self::podeVerDoConsultor((int) ($this->fields['users_id_consultor'] ?? 0));
    }

    public function canViewItem(): bool
    {
        return parent::canViewItem() && $this->visivelParaUsuario();
    }

    public function canUpdateItem(): bool
    {
        return parent::canUpdateItem() && $this->visivelParaUsuario();
    }

    public function canDeleteItem(): bool
    {
        return parent::canDeleteItem() && $this->visivelParaUsuario();
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_RASCUNHO    => __('Rascunho', 'propostas'),
            self::STATUS_ENVIADA     => __('Enviada', 'propostas'),
            self::STATUS_VISUALIZADA => __('Visualizada', 'propostas'),
            self::STATUS_ACEITA      => __('Aceita', 'propostas'),
            self::STATUS_DUVIDA      => __('Com dúvida', 'propostas'),
            self::STATUS_EXPIRADA    => __('Expirada', 'propostas'),
            self::STATUS_CANCELADA   => __('Cancelada', 'propostas'),
        ];
    }

    public function defineTabs($options = []): array
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(PropostaItem::class, $tabs, $options);
        $this->addStandardTab(PropostaBloco::class, $tabs, $options);
        $this->addStandardTab(Resposta::class, $tabs, $options);
        $this->addStandardTab(Acesso::class, $tabs, $options);
        $this->addStandardTab(Log::class, $tabs, $options);

        return $tabs;
    }

    public function post_getEmpty(): void
    {
        // O GLPI não traz os DEFAULT do banco para o formulário de item novo.
        $this->fields['versao']             = 1;
        $this->fields['validade_dias']      = 15;
        $this->fields['aviso_previo_dias']  = 60;
        $this->fields['data_emissao']       = date('Y-m-d');
        $this->fields['users_id_consultor'] = Session::getLoginUserID();
        $this->fields['status']             = self::STATUS_RASCUNHO;
        $this->fields['entities_id']        = self::entidadeDeCriacao();
    }

    /** Ligado só por atualizarInterno(): libera os campos internos e a proposta travada. */
    private bool $acao_interna = false;

    /** Duplicação: a cópia leva as condições da proposta original, mesmo sem o campo no formulário. */
    private bool $copia_de_proposta = false;

    /**
     * Update feito pelas ações do próprio plugin (link, reabrir, cancelar, respostas, cron).
     *
     * @param array<string, mixed> $input
     */
    private function atualizarInterno(array $input): bool
    {
        $this->acao_interna = true;
        try {
            return $this->update($input);
        } finally {
            $this->acao_interna = false;
        }
    }

    /**
     * Roda $acao com a linha da proposta travada (SELECT ... FOR UPDATE) e os campos relidos do banco.
     * Duas requisições ao mesmo tempo (aceite e dúvida, aceite e reabrir, duplo clique em gerar link)
     * passam uma depois da outra, e a segunda já vê o status que a primeira gravou.
     *
     * @template T
     * @param callable(): T $acao
     * @return T
     */
    private function comTrava(callable $acao): mixed
    {
        global $DB;

        $DB->beginTransaction();
        try {
            $DB->doQuery(sprintf('SELECT `id` FROM `%s` WHERE `id` = %d FOR UPDATE', self::getTable(), $this->getID()));
            $this->getFromDB($this->getID());
            $resultado = $acao();
            $DB->commit();

            return $resultado;
        } catch (\Throwable $e) {
            $DB->rollBack();
            throw $e;
        }
    }

    /** Campos que só as ações do plugin alteram (nunca o formulário). */
    private const CAMPOS_INTERNOS = ['status', 'versao', 'token_hash', 'token_cifrado', 'token_expira'];

    /** Com a proposta travada (já enviada), o formulário só pode mudar estes campos. */
    private const CAMPOS_LIVRES_TRAVADA = ['id', 'comment'];

    public function prepareInputForAdd($input)
    {
        foreach (self::CAMPOS_INTERNOS as $campo) {
            unset($input[$campo]);
        }
        // Proposta nova nasce sem chamado: o plugin abre um ao gerar o link. Vincular um
        // chamado existente só depois da criação, pelo formulário da proposta.
        unset($input['tickets_id']);
        // Sem o campo na configuração, as condições vêm só do modelo.
        if (!self::pedeCondicoes() && !$this->copia_de_proposta) {
            unset($input['condicoes']);
        }
        if (empty($input['data_emissao'])) {
            $input['data_emissao'] = date('Y-m-d');
        }
        $input['status'] = self::STATUS_RASCUNHO;
        $input['versao'] = 1;

        if (!self::podeCriarAgora()) {
            Session::addMessageAfterRedirect(htmlescape(__('Você não está em nenhuma equipe de consultores e não pode criar propostas. Procure o administrador do GLPI.', 'propostas')), false, ERROR);
            return false;
        }
        if (!self::conferirValidade($input)) {
            return false;
        }
        if ((int) ($input['users_id_consultor'] ?? 0) <= 0) {
            $input['users_id_consultor'] = Equipe::usuarioAtual();
        }

        // Entidade padrão das propostas, quando o usuário tem acesso a ela.
        $padrao = (int) (\Config::getConfigurationValue('plugin:propostas', 'propostas_entities_id') ?? -1);
        if ($padrao >= 0 && Session::haveAccessToEntity($padrao) && (int) ($input['entities_id'] ?? -1) !== $padrao) {
            $input['entities_id']  = $padrao;
            $input['is_recursive'] = 0;
        }

        // Com modelo: validade, aviso prévio e condições vêm dele (itens e blocos em post_addItem).
        $modelos_id = (int) ($input['plugin_propostas_modelos_id'] ?? 0);
        if ($modelos_id > 0 && !$this->copia_de_proposta) {
            $modelo = new Modelo();
            $entidade = (int) ($input['entities_id'] ?? Session::getActiveEntity());
            if (
                !$modelo->getFromDB($modelos_id)
                || !$modelo->fields['is_active']
                || !self::visivelNaEntidade($modelo, $entidade)
            ) {
                Session::addMessageAfterRedirect(htmlescape(__('Modelo de proposta inválido ou inativo.', 'propostas')), false, ERROR);
                return false;
            }
            $input = $modelo->aplicarNoInput($input);
        }
        if (array_key_exists('exibicao', $input)) {
            $input['exibicao'] = Calculo::exibicaoValida($input['exibicao']);
        }

        $entidade = (int) ($input['entities_id'] ?? Session::getActiveEntity());
        if (!$this->validarVinculos($input, $entidade)) {
            return false;
        }

        $input = $this->validarEmpresa($input);

        return $input === false ? false : $this->aplicarNome($input);
    }

    /** O formulário pede o nome da proposta? (Configuração; padrão: sim.) */
    public static function pedeNome(): bool
    {
        return (string) (\Config::getConfigurationValue('plugin:propostas', 'campo_nome_ativo') ?? '1') !== '0';
    }

    /**
     * O formulário da proposta mostra o campo Condições comerciais para edição? (Configuração; padrão: sim.)
     * Desligado, a proposta só leva o texto do modelo.
     */
    public static function pedeCondicoes(): bool
    {
        return (string) (\Config::getConfigurationValue('plugin:propostas', 'campo_condicoes_ativo') ?? '1') !== '0';
    }

    /**
     * Sem o campo Nome no formulário, o nome sai sozinho ("{modelo} – {razão social}") e acompanha
     * a razão social. Com o campo, só preenche quando ele vem em branco.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function aplicarNome(array $input): array
    {
        $digitado = array_key_exists('name', $input) ? trim((string) $input['name']) : null;
        if (self::pedeNome() && $digitado !== '' && ($digitado !== null || !$this->isNewItem())) {
            return $input;
        }

        $modelos_id = (int) ($input['plugin_propostas_modelos_id'] ?? $this->fields['plugin_propostas_modelos_id'] ?? 0);
        $modelo = new Modelo();
        $nome_modelo = $modelos_id > 0 && $modelo->getFromDB($modelos_id) ? (string) $modelo->fields['name'] : '';
        $empresa = (string) ($input['empresa_razao_social'] ?? $this->fields['empresa_razao_social'] ?? '');
        $input['name'] = Calculo::nomeAutomatico($nome_modelo, $empresa);

        return $input;
    }

    /**
     * Campos padrão do formulário do GLPI: só o Nome (quando pedido na configuração). Os comentários
     * saem daqui e vão para o fim do formulário (proposta.form.html.twig), para o modelo ou o número
     * abrirem o formulário em vez de uma caixa de anotação.
     *
     * @return array<int|string, string>
     */
    public function getFormFields(): array
    {
        $fora = self::pedeNome() ? ['comment'] : ['comment', 'name'];

        return array_values(array_filter(parent::getFormFields(), static fn ($c): bool => !in_array($c, $fora, true)));
    }

    public function post_addItem(): void
    {
        $modelos_id = (int) ($this->fields['plugin_propostas_modelos_id'] ?? 0);
        if ($modelos_id > 0 && !$this->copia_de_proposta) {
            $modelo = new Modelo();
            if ($modelo->getFromDB($modelos_id)) {
                // Campos mostrados ao escolher o modelo: valor e desconto (%) de cada item.
                $valores   = $this->input['_modelo_valores'] ?? [];
                $descontos = $this->input['_modelo_descontos'] ?? [];
                $modelo->copiarParaProposta(
                    $this->getID(),
                    is_array($valores) ? $valores : [],
                    is_array($descontos) ? $descontos : []
                );
            }
        }
    }

    public function post_updateItem($history = true): void
    {
        // Chamado ou consultor mudou: acerta o gestor observador no chamado antigo e no novo.
        if (in_array('tickets_id', $this->updates, true) || in_array('users_id_consultor', $this->updates, true)) {
            Equipe::sincronizarChamados([
                (int) ($this->oldvalues['tickets_id'] ?? 0),
                (int) $this->fields['tickets_id'],
            ]);
        }
    }

    /**
     * CNPJ opcional no rascunho, mas, se informado, precisa ter dígitos válidos. Gravado formatado.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function validarEmpresa(array $input): array|false
    {
        if (array_key_exists('empresa_cnpj', $input) && trim((string) $input['empresa_cnpj']) !== '') {
            if (!Cnpj::validar((string) $input['empresa_cnpj'])) {
                Session::addMessageAfterRedirect(htmlescape(__('CNPJ inválido: confira os dígitos.', 'propostas')), false, ERROR);
                return false;
            }
            $input['empresa_cnpj'] = Cnpj::formatar((string) $input['empresa_cnpj']);
        }
        foreach (['empresa_razao_social', 'empresa_nome_fantasia'] as $campo) {
            if (isset($input[$campo])) {
                $input[$campo] = trim((string) $input[$campo]);
            }
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        // Ações do próprio plugin (gerar link, reabrir, cancelar, visualização...) passam direto.
        // O sinal é uma propriedade do objeto, nunca um campo vindo da requisição.
        if ($this->acao_interna) {
            return $input;
        }

        foreach (self::CAMPOS_INTERNOS as $campo) {
            unset($input[$campo]);
        }
        // O modelo de origem não muda depois da criação.
        unset($input['plugin_propostas_modelos_id']);
        // Sem o campo na configuração, as condições ficam as do modelo.
        if (!self::pedeCondicoes()) {
            unset($input['condicoes']);
        }
        if (array_key_exists('exibicao', $input)) {
            $input['exibicao'] = Calculo::exibicaoValida($input['exibicao']);
        }

        if (!$this->isEditavel()) {
            [$input, $ignorados] = Calculo::filtrarTravada($input, $this->fields, self::CAMPOS_LIVRES_TRAVADA);
            if ($ignorados !== []) {
                Session::addMessageAfterRedirect(
                    htmlescape(__('A proposta já foi enviada: só os comentários podem mudar. Para alterar valores ou condições, use "Reabrir para edição".', 'propostas')),
                    false,
                    WARNING
                );
            }

            return $input;
        }

        if (!self::conferirValidade($input)) {
            return false;
        }
        if (array_key_exists('users_id_consultor', $input) && (int) $input['users_id_consultor'] <= 0) {
            unset($input['users_id_consultor']);
        }
        // Mudar a entidade (ou a recursividade) só para uma entidade a que a pessoa tem acesso.
        if (array_key_exists('entities_id', $input) && (int) $input['entities_id'] !== (int) $this->fields['entities_id']
            && !Session::haveAccessToEntity((int) $input['entities_id'])) {
            Session::addMessageAfterRedirect(htmlescape(__('Você não tem acesso à entidade escolhida.', 'propostas')), false, ERROR);
            return false;
        }

        if (!$this->validarVinculos($input, (int) ($input['entities_id'] ?? $this->fields['entities_id']))) {
            return false;
        }

        $input = $this->validarEmpresa($input);

        return $input === false ? false : $this->aplicarNome($input);
    }

    /**
     * Item de entidade (modelo, bloco) que pode ser usado numa proposta desta entidade:
     * da mesma entidade, ou recursivo de uma entidade acima.
     */
    public static function visivelNaEntidade(\CommonDBTM $item, int $entidade): bool
    {
        $entidade_item = (int) $item->fields['entities_id'];
        if ($entidade_item === $entidade) {
            return true;
        }

        return !empty($item->fields['is_recursive'])
            && in_array($entidade_item, getAncestorsOf('glpi_entities', $entidade), false);
    }

    /**
     * Chamado vinculado e consultor vindos do formulário (ou de ação em massa). Só valida o que mudou.
     *
     * @param array<string, mixed> $input
     */
    private function validarVinculos(array $input, int $entidade): bool
    {
        $tickets_id = (int) ($input['tickets_id'] ?? 0);
        if ($tickets_id > 0 && $tickets_id !== (int) ($this->fields['tickets_id'] ?? 0)) {
            $ticket = new \Ticket();
            $mesmo_ramo = array_merge(
                getAncestorsOf('glpi_entities', $entidade),
                getSonsOf('glpi_entities', $entidade)
            );
            // O aceite e a expiração solucionam e fecham o chamado: só vale um chamado que a pessoa
            // pode editar, da entidade da proposta, e que não esteja ligado a outra proposta.
            if (
                !$ticket->getFromDB($tickets_id)
                || !$ticket->can($tickets_id, UPDATE)
                || !in_array((int) $ticket->fields['entities_id'], $mesmo_ramo, false)
            ) {
                Session::addMessageAfterRedirect(htmlescape(__('Chamado vinculado inválido: escolha um chamado que você pode editar, da entidade da proposta.', 'propostas')), false, ERROR);
                return false;
            }
            $outras = countElementsInTable(self::getTable(), [
                'tickets_id' => $tickets_id,
                'NOT'        => ['id' => (int) ($this->fields['id'] ?? 0)],
            ]);
            if ($outras > 0) {
                Session::addMessageAfterRedirect(htmlescape(__('Este chamado já está vinculado a outra proposta.', 'propostas')), false, ERROR);
                return false;
            }
        }

        $users_id = (int) ($input['users_id_consultor'] ?? 0);
        if ($users_id > 0 && $users_id !== (int) ($this->fields['users_id_consultor'] ?? 0)) {
            $user = new \User();
            if (
                !$user->getFromDB($users_id)
                || !$user->fields['is_active']
                || $user->fields['is_deleted']
                || !in_array($entidade, \Profile_User::getUserEntities($users_id, true), false)
            ) {
                Session::addMessageAfterRedirect(htmlescape(__('Consultor inválido: escolha um usuário ativo com acesso à entidade da proposta.', 'propostas')), false, ERROR);
                return false;
            }

            $usuario = Equipe::usuarioAtual();
            $direitos = Equipe::direitosAtuais();
            if (!Visibilidade::podeAtribuir(
                $direitos,
                $usuario,
                $users_id,
                Equipe::ativas(),
                Equipe::usuarioEmEquipe($users_id),
                ($direitos & Visibilidade::EQUIPE) !== 0 ? Equipe::membrosGeridos($usuario) : []
            )) {
                Session::addMessageAfterRedirect(htmlescape(__('Você não pode pôr este usuário como consultor: escolha você mesmo ou um consultor de uma equipe que você gere (o consultor precisa estar numa equipe).', 'propostas')), false, ERROR);
                return false;
            }
        }

        return true;
    }

    /**
     * Só o rascunho pode ter dados e itens alterados.
     */
    public function isEditavel(): bool
    {
        return ($this->fields['status'] ?? self::STATUS_RASCUNHO) === self::STATUS_RASCUNHO;
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        $pode_editar = !$this->isNewItem() && $this->can($this->getID(), UPDATE);

        TemplateRenderer::getInstance()->display('@propostas/proposta.form.html.twig', [
            'item'          => $this,
            'params'        => $options,
            'numero'        => $this->getNumero(),
            'status_label'  => self::getStatusList()[$this->fields['status']] ?? $this->fields['status'],
            'editavel'      => $this->isEditavel(),
            'totais'        => $this->isNewItem() ? null : $this->getTotaisFormatados(),
            // O link dá acesso de cliente (aceitar, mandar dúvida): só quem pode editar a proposta o vê.
            'link_publico'  => !$this->isNewItem() && $pode_editar ? $this->getLinkPublico() : null,
            'link_oculto'   => !$this->isNewItem() && !$pode_editar && !empty($this->fields['token_cifrado']),
            'link_expira'   => $this->fields['token_expira'] ?? null,
            'link_expirado' => $this->isLinkExpirado(),
            'pode_gerar'    => $pode_editar && $this->podeGerarLink(),
            'pode_reabrir'  => $pode_editar && $this->podeReabrir(),
            'pode_cancelar' => $pode_editar && $this->podeCancelar(),
            'pode_duplicar' => !$this->isNewItem() && Session::haveRight(self::$rightname, CREATE) && self::podeCriarAgora(),
            'modelo'        => $this->getModelo(),
            'cnpj_consulta' => (bool) (\Config::getConfigurationValue('plugin:propostas', 'cnpj_consulta_ativa') ?? false),
            'cnpj_url'      => \Plugin::getWebDir('propostas') . '/ajax/cnpj.php',
            'consultores'   => $this->opcoesConsultor(),
            // Só "Ler as próprias": o consultor é sempre o próprio usuário, então o campo vira texto fixo.
            'consultor_fixo' => (Equipe::direitosAtuais() & (Visibilidade::EQUIPE | Visibilidade::TODAS)) === 0,
            'consultor_id'   => $consultor_id = (int) ($this->fields['users_id_consultor'] ?: Session::getLoginUserID()),
            'consultor_nome' => getUserName($consultor_id),
            'modelo_url'    => \Plugin::getWebDir('propostas') . '/ajax/modelo.php',
            'exibicoes'     => Modelo::getOpcoesExibicao(),
            'pede_condicoes' => self::pedeCondicoes(),
        ]);

        return true;
    }

    /**
     * Entidade em que uma proposta nova vai ser criada: a entidade padrão do plugin, quando
     * configurada e acessível ao usuário; senão, a entidade ativa.
     */
    public static function entidadeDeCriacao(): int
    {
        $padrao = (int) (\Config::getConfigurationValue('plugin:propostas', 'propostas_entities_id') ?? -1);

        return $padrao >= 0 && Session::haveAccessToEntity($padrao) ? $padrao : (int) Session::getActiveEntity();
    }

    /**
     * Opções do campo Consultor com as equipes ligadas: só quem a regra de atribuição aceita,
     * ativo e com acesso à entidade da proposta, mais o consultor atual (para não sumir do campo).
     * Null com as equipes desligadas: o campo lista os usuários da entidade, como antes.
     *
     * @return array<int, string>|null
     */
    private function opcoesConsultor(): ?array
    {
        $usuario  = Equipe::usuarioAtual();
        $direitos = Equipe::direitosAtuais();
        $ids = Visibilidade::consultoresPermitidos(
            $direitos,
            $usuario,
            Equipe::ativas(),
            Equipe::usuarioEmEquipe($usuario),
            ($direitos & Visibilidade::TODAS) !== 0 ? Equipe::membrosDasEquipes() : [],
            ($direitos & Visibilidade::EQUIPE) !== 0 ? Equipe::membrosGeridos($usuario) : []
        );
        if ($ids === null) {
            return null;
        }

        // Proposta nova: a entidade em que ela vai ser criada.
        $entidade = $this->isNewItem() ? self::entidadeDeCriacao() : (int) $this->fields['entities_id'];

        $opcoes = [];
        $user = new \User();
        foreach ($ids as $id) {
            if (
                $user->getFromDB($id)
                && $user->fields['is_active']
                && !$user->fields['is_deleted']
                && in_array($entidade, \Profile_User::getUserEntities($id, true), false)
            ) {
                $opcoes[$id] = getUserName($id);
            }
        }
        $atual = (int) ($this->fields['users_id_consultor'] ?? 0);
        if ($atual > 0 && !isset($opcoes[$atual])) {
            $opcoes[$atual] = getUserName($atual);
        }
        asort($opcoes, SORT_NATURAL | SORT_FLAG_CASE);

        return $opcoes;
    }

    public function getModelo(): ?Modelo
    {
        $modelo = new Modelo();

        return (int) ($this->fields['plugin_propostas_modelos_id'] ?? 0) > 0
            && $modelo->getFromDB((int) $this->fields['plugin_propostas_modelos_id']) ? $modelo : null;
    }

    /* ------------------------------------------------------------------
     * Link público
     * ------------------------------------------------------------------ */

    public function podeGerarLink(): bool
    {
        return !$this->isNewItem() && $this->fields['status'] === self::STATUS_RASCUNHO;
    }

    public function podeReabrir(): bool
    {
        return !$this->isNewItem() && in_array($this->fields['status'], [
            self::STATUS_ENVIADA,
            self::STATUS_VISUALIZADA,
            self::STATUS_DUVIDA,
            self::STATUS_EXPIRADA,
            self::STATUS_CANCELADA,
        ], true);
    }

    public function podeCancelar(): bool
    {
        return !$this->isNewItem() && !in_array($this->fields['status'], [
            self::STATUS_ACEITA,
            self::STATUS_CANCELADA,
        ], true);
    }

    /* ------------------------------------------------------------------
     * Expiração automática (Configurar > Ações automáticas > expirar)
     * ------------------------------------------------------------------ */

    /** Status que ainda esperam resposta do cliente e, portanto, podem expirar. */
    private const STATUS_EM_ABERTO = [self::STATUS_ENVIADA, self::STATUS_VISUALIZADA, self::STATUS_DUVIDA];

    public static function cronInfo($name)
    {
        if ($name === 'expirar') {
            return ['description' => __('Marca como expiradas as propostas enviadas que passaram da validade', 'propostas')];
        }

        return [];
    }

    /**
     * Enviada / Visualizada / Com dúvida com o link vencido -> Expirada.
     * O link já para de funcionar no vencimento; aqui só o status acompanha.
     *
     * @return int 1 se expirou alguma proposta, 0 se não havia nada a fazer
     */
    public static function cronExpirar(\CronTask $task): int
    {
        global $DB;

        $vencidas = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'status'       => self::STATUS_EM_ABERTO,
                'NOT'          => ['token_expira' => null],
                'token_expira' => ['<', date('Y-m-d H:i:s')],
            ],
        ]);

        $expiradas = 0;
        foreach ($vencidas as $row) {
            $proposta = new self();
            if (
                $proposta->getFromDB((int) $row['id'])
                && $proposta->comTrava(static fn (): bool => in_array($proposta->fields['status'], self::STATUS_EM_ABERTO, true)
                    && $proposta->isLinkExpirado()
                    && $proposta->atualizarInterno(['id' => $proposta->getID(), 'status' => self::STATUS_EXPIRADA]))
            ) {
                $expiradas++;
                $proposta->adicionarAcompanhamento(sprintf(
                    '<p><strong>Proposta nº %s (versão %d) expirou</strong> sem resposta do cliente.</p>',
                    htmlescape($proposta->getNumero()),
                    (int) $proposta->fields['versao']
                ));
                $proposta->fecharChamado(sprintf(
                    '<p>Proposta nº %s (versão %d) expirou sem resposta do cliente.</p>',
                    htmlescape($proposta->getNumero()),
                    (int) $proposta->fields['versao']
                ));
                $proposta->notificar('expirada');
                $task->log(sprintf('Proposta %s (versão %d) expirada', $proposta->getNumero(), (int) $proposta->fields['versao']));
            }
        }
        $task->addVolume($expiradas);

        return $expiradas > 0 ? 1 : 0;
    }

    /* ------------------------------------------------------------------
     * Duplicar
     * ------------------------------------------------------------------ */

    /**
     * Cria um rascunho novo com os dados e os itens desta proposta.
     * Não copia status, versão, link, chamado vinculado, respostas nem acessos.
     *
     * @return int|false id da proposta nova
     */
    public function duplicar(): int|false
    {
        $nova = new self();
        $nova->copia_de_proposta = true;
        $entidade = $this->entidadeDeTrabalho();
        $id = $nova->add([
            'entities_id'        => $entidade,
            'is_recursive'       => $entidade === (int) $this->fields['entities_id'] ? $this->fields['is_recursive'] : 0,
            'name'               => sprintf(__('%s (cópia)', 'propostas'), $this->fields['name']),
            'solicitante'        => $this->fields['solicitante'],
            'empresa_razao_social'        => $this->fields['empresa_razao_social'],
            'empresa_nome_fantasia'       => $this->fields['empresa_nome_fantasia'],
            'empresa_cnpj'                => $this->fields['empresa_cnpj'],
            'plugin_propostas_modelos_id' => $this->fields['plugin_propostas_modelos_id'],
            'users_id_consultor' => Session::getLoginUserID() ?: $this->fields['users_id_consultor'],
            'data_emissao'       => date('Y-m-d'),
            'validade_dias'      => $this->fields['validade_dias'],
            'aviso_previo_dias'  => $this->fields['aviso_previo_dias'],
            'condicoes'          => $this->fields['condicoes'],
            'exibicao'           => $this->fields['exibicao'] ?? Calculo::EXIBICAO_TOTAL,
        ]);
        if (!$id) {
            return false;
        }

        self::copiarLinhas((int) $id, PropostaItem::getItensDaProposta($this->getID()), PropostaBloco::getBlocosDaProposta($this->getID()));

        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('Proposta duplicada a partir da nº %s. Confira os dados e os itens antes de gerar o link.', 'propostas'),
            $this->getNumero()
        )));

        return (int) $id;
    }

    /**
     * Copia itens e blocos para a proposta $destino (criação pelo modelo e duplicação).
     *
     * @param iterable<array<string, mixed>> $itens
     * @param iterable<array<string, mixed>> $blocos
     */
    public static function copiarLinhas(int $destino, iterable $itens, iterable $blocos): void
    {
        foreach ($itens as $item) {
            (new PropostaItem())->add([
                PropostaItem::$items_id => $destino,
                'tipo'                  => $item['tipo'],
                'name'                  => $item['name'],
                'quantidade'            => $item['quantidade'],
                'valor_unitario'        => $item['valor_unitario'],
                'desconto_pct'          => $item['desconto_pct'],
                'ordem'                 => $item['ordem'],
            ]);
        }
        foreach ($blocos as $bloco) {
            (new PropostaBloco())->add([
                PropostaBloco::$items_id => $destino,
                'name'                   => $bloco['name'],
                'conteudo'               => $bloco['conteudo'],
                'ordem'                  => $bloco['ordem'],
                'depois_dos_valores'     => (int) ($bloco['depois_dos_valores'] ?? 0),
            ]);
        }
    }

    /**
     * Gera o link público, trava a proposta e marca como enviada.
     * Um link anterior deixa de valer.
     */
    public function gerarLink(): bool
    {
        return $this->comTrava(fn (): bool => $this->gerarLinkTravado());
    }

    private function gerarLinkTravado(): bool
    {
        if (!$this->podeGerarLink()) {
            return false;
        }
        if (PropostaItem::getItensDaProposta($this->getID()) === []) {
            Session::addMessageAfterRedirect(htmlescape(__('Inclua ao menos um item antes de gerar o link.', 'propostas')), false, ERROR);
            return false;
        }
        if (trim((string) $this->fields['empresa_razao_social']) === '' || !Cnpj::validar((string) $this->fields['empresa_cnpj'])) {
            Session::addMessageAfterRedirect(htmlescape(__('Informe a razão social e um CNPJ válido da empresa antes de gerar o link.', 'propostas')), false, ERROR);
            return false;
        }

        $expira = $this->calcularExpiracao();
        if ($expira <= new \DateTimeImmutable()) {
            Session::addMessageAfterRedirect(htmlescape(__('A validade já terminou: ajuste a emissão ou a validade antes de gerar o link.', 'propostas')), false, ERROR);
            return false;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $ok = $this->atualizarInterno([
            'id'             => $this->getID(),
            'status'         => self::STATUS_ENVIADA,
            'token_hash'     => hash('sha256', $token),
            'token_cifrado'  => (new \GLPIKey())->encrypt($token),
            'token_expira'   => $expira->format('Y-m-d H:i:s'),
        ]);
        if ($ok) {
            Session::addMessageAfterRedirect(htmlescape(__('Link gerado. A proposta está travada para edição.', 'propostas')));
            // Chamado fechado (ex.: proposta expirada e reaberta) não recebe acompanhamentos: abre outro.
            if ((int) $this->fields['tickets_id'] > 0 && $this->chamadoFechado()) {
                $this->atualizarInterno(['id' => $this->getID(), 'tickets_id' => 0]);
            }
            if ((int) $this->fields['tickets_id'] <= 0) {
                $this->abrirChamado();
            }
            $this->adicionarAcompanhamento(sprintf(
                '<p><strong>Proposta nº %s (versão %d) enviada ao cliente.</strong></p><p>Link válido até %s.</p>',
                htmlescape($this->getNumero()),
                (int) $this->fields['versao'],
                htmlescape($expira->format('d/m/Y H:i'))
            ));
        }

        return $ok;
    }

    /**
     * Abre o chamado da proposta (quando nenhum foi vinculado à mão) e grava o vínculo.
     * Falha não impede o link: fica no log e o consultor recebe um aviso.
     */
    private function abrirChamado(): void
    {
        global $CFG_GLPI;

        $config = \Config::getConfigurationValues('plugin:propostas', ['chamado_itilcategories_id', 'chamado_groups_id']);
        $consultor = (int) $this->fields['users_id_consultor'] ?: (int) Session::getLoginUserID();
        $empresa = trim((string) $this->fields['empresa_razao_social']);

        $input = [
            'entities_id'       => $this->entidadeDeTrabalho(),
            'type'              => \Ticket::DEMAND_TYPE,
            'name'              => sprintf('Proposta nº %s - %s', $this->getNumero(), $empresa !== '' ? $empresa : (string) $this->fields['name']),
            'content'           => sprintf(
                '<p>Chamado aberto automaticamente pelo plugin Propostas.</p><p>Proposta: <a href="%s">nº %s - %s</a><br>Empresa: %s (CNPJ %s)</p>',
                htmlescape(rtrim((string) $CFG_GLPI['url_base'], '/') . self::getFormURLWithID($this->getID(), false)),
                htmlescape($this->getNumero()),
                htmlescape((string) $this->fields['name']),
                htmlescape($empresa),
                htmlescape((string) $this->fields['empresa_cnpj'])
            ),
            'itilcategories_id' => (int) ($config['chamado_itilcategories_id'] ?? 0),
        ];
        if ($consultor > 0) {
            $input['_users_id_requester'] = $consultor;
            $input['_users_id_assign']    = $consultor;
        }
        if ((int) ($config['chamado_groups_id'] ?? 0) > 0) {
            $input['_groups_id_assign'] = (int) $config['chamado_groups_id'];
        }

        try {
            $tickets_id = (int) (new \Ticket())->add($input);
        } catch (\Throwable $e) {
            \Toolbox::logInFile('propostas', sprintf("Abertura de chamado da proposta %d falhou: %s\n", $this->getID(), $e->getMessage()));
            $tickets_id = 0;
        }
        if ($tickets_id <= 0) {
            Session::addMessageAfterRedirect(htmlescape(__('Não foi possível abrir o chamado da proposta. Vincule um chamado manualmente.', 'propostas')), false, WARNING);
            return;
        }

        $this->atualizarInterno(['id' => $this->getID(), 'tickets_id' => $tickets_id]);
        Session::addMessageAfterRedirect(htmlescape(sprintf(__('Chamado %d aberto para esta proposta.', 'propostas'), $tickets_id)));
    }

    /**
     * Volta para rascunho como nova versão. O link anterior deixa de valer.
     */
    public function reabrir(): bool
    {
        return $this->comTrava(fn (): bool => $this->reabrirTravado());
    }

    private function reabrirTravado(): bool
    {
        if (!$this->podeReabrir()) {
            return false;
        }

        $ok = $this->atualizarInterno([
            'id'            => $this->getID(),
            'status'        => self::STATUS_RASCUNHO,
            'versao'        => (int) $this->fields['versao'] + 1,
            'token_hash'    => 'NULL',
            'token_cifrado' => 'NULL',
            'token_expira'  => 'NULL',
        ]);
        if ($ok) {
            Session::addMessageAfterRedirect(htmlescape(sprintf(
                __('Proposta reaberta como versão %d. O link anterior não funciona mais.', 'propostas'),
                (int) $this->fields['versao']
            )));
            if (!$this->chamadoFechado()) {
                $this->adicionarAcompanhamento(sprintf(
                    '<p><strong>Proposta nº %s reaberta para edição como versão %d.</strong> O link anterior deixou de funcionar.</p>',
                    htmlescape($this->getNumero()),
                    (int) $this->fields['versao']
                ));
            }
        }

        return $ok;
    }

    public function cancelar(): bool
    {
        return $this->comTrava(fn (): bool => $this->cancelarTravado());
    }

    private function cancelarTravado(): bool
    {
        if (!$this->podeCancelar()) {
            return false;
        }

        $ok = $this->atualizarInterno([
            'id'            => $this->getID(),
            'status'        => self::STATUS_CANCELADA,
            'token_hash'    => 'NULL',
            'token_cifrado' => 'NULL',
            'token_expira'  => 'NULL',
        ]);
        if ($ok) {
            $this->adicionarAcompanhamento(sprintf(
                '<p><strong>Proposta nº %s (versão %d) cancelada.</strong> O link deixou de funcionar.</p>',
                htmlescape($this->getNumero()),
                (int) $this->fields['versao']
            ));
            $this->fecharChamado(sprintf(
                '<p>Proposta nº %s (versão %d) cancelada.</p>',
                htmlescape($this->getNumero()),
                (int) $this->fields['versao']
            ));
        }

        return $ok;
    }

    /**
     * Fim do último dia de validade (emissão + validade em dias).
     */
    public function calcularExpiracao(): \DateTimeImmutable
    {
        $emissao = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->fields['data_emissao'])
            ?: new \DateTimeImmutable('today');

        return $emissao->modify(sprintf('+%d days', max(0, (int) $this->fields['validade_dias'])))->setTime(23, 59, 59);
    }

    public function isLinkExpirado(): bool
    {
        if (empty($this->fields['token_expira'])) {
            return false;
        }

        return new \DateTimeImmutable((string) $this->fields['token_expira']) <= new \DateTimeImmutable();
    }

    /**
     * Endereço que o cliente recebe. Usa a URL externa configurada no plugin ou, sem ela, a do GLPI.
     */
    public function getLinkPublico(): ?string
    {
        if (empty($this->fields['token_cifrado'])) {
            return null;
        }
        $token = (new \GLPIKey())->decrypt($this->fields['token_cifrado']);
        if ($token === null || $token === '') {
            return null;
        }

        return self::getUrlBasePublica() . '/plugins/propostas/p/' . $token;
    }

    public static function getUrlBasePublica(): string
    {
        global $CFG_GLPI;

        $url = (string) (\Config::getConfigurationValue('plugin:propostas', 'url_publica') ?? '');
        if ($url === '') {
            $url = (string) $CFG_GLPI['url_base'];
        }

        return rtrim($url, '/');
    }

    /**
     * Procura a proposta pelo token do link (compara só o hash).
     */
    public static function buscarPorToken(string $token): ?self
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
            return null;
        }
        $proposta = new self();
        if (!$proposta->getFromDBByCrit(['token_hash' => hash('sha256', $token)])) {
            return null;
        }

        return $proposta;
    }

    /**
     * Abertura do link. Quem está logado no GLPI (consultor conferindo o link) não conta.
     * Registra o acesso e, na primeira abertura, muda Enviada -> Visualizada e avisa o consultor.
     */
    public function registrarVisualizacao(string $ip, string $user_agent): void
    {
        if (Session::getLoginUserID() !== false) {
            return;
        }

        Acesso::registrar($this->getID(), (int) $this->fields['versao'], $ip, $user_agent);

        if ($this->fields['status'] !== self::STATUS_ENVIADA) {
            return;
        }
        $token_hash = (string) $this->fields['token_hash'];
        $mudou = $this->comTrava(fn (): bool => $this->fields['status'] === self::STATUS_ENVIADA
            && (string) $this->fields['token_hash'] === $token_hash
            && $this->atualizarInterno(['id' => $this->getID(), 'status' => self::STATUS_VISUALIZADA]));
        if ($mudou) {
            $this->notificar('aberta');
        }
    }

    /* ------------------------------------------------------------------
     * Respostas do cliente (aceite e dúvida)
     * ------------------------------------------------------------------ */

    /**
     * O cliente pode aceitar ou mandar dúvida enquanto a proposta está enviada e dentro da validade.
     */
    public function podeResponder(): bool
    {
        return in_array($this->fields['status'], [
            self::STATUS_ENVIADA,
            self::STATUS_VISUALIZADA,
            self::STATUS_DUVIDA,
        ], true) && !$this->isLinkExpirado();
    }

    /**
     * SHA-256 do que o cliente está aceitando: número, versão, título, datas, condições, itens e totais.
     * Gravado no aceite para provar depois que os valores não mudaram.
     */
    public function calcularHashValores(int $formato = Calculo::HASH_FORMATO_ATUAL, string $cargo = ''): string
    {
        $cabecalho = [
            'numero'        => $this->getNumero(),
            'versao'        => (int) $this->fields['versao'],
            'titulo'        => (string) $this->fields['name'],
            'emissao'       => (string) $this->fields['data_emissao'],
            'validade_dias' => (int) $this->fields['validade_dias'],
            'aviso_previo'  => (int) $this->fields['aviso_previo_dias'],
            'condicoes'     => (string) $this->fields['condicoes'],
        ];
        $itens = PropostaItem::getItensDaProposta($this->getID());

        if ($formato === 1) {
            return Calculo::hashValores($cabecalho, $itens);
        }

        return Calculo::hashValoresFormato2(
            $cabecalho + [
                'razao_social' => (string) $this->fields['empresa_razao_social'],
                'cnpj'         => (string) $this->fields['empresa_cnpj'],
            ],
            $itens,
            array_map(
                static fn (array $b): array => ['titulo' => (string) $b['name'], 'conteudo' => (string) $b['conteudo']],
                PropostaBloco::getBlocosDaProposta($this->getID())
            ),
            $cargo
        );
    }

    /** Dúvidas aceitas por proposta a cada 24 horas (evita encher o chamado e o e-mail do consultor). */
    private const DUVIDAS_POR_DIA = 5;

    /**
     * Ainda é a mesma versão/link que o cliente abriu e ela ainda aceita resposta.
     */
    private function mesmaVersaoRespondivel(string $token_hash, int $versao): bool
    {
        return $this->podeResponder()
            && (string) $this->fields['token_hash'] === $token_hash
            && (int) $this->fields['versao'] === $versao;
    }

    /**
     * @return list<string> mensagens de erro (vazio = aceite registrado)
     */
    public function aceitar(string $nome, string $cpf, string $cargo, bool $declaracao, string $ip, string $user_agent): array
    {
        $nome  = self::normalizarNome($nome);
        $cargo = self::normalizarNome($cargo);
        $erros = [];
        if (!$this->podeResponder()) {
            $erros[] = 'Esta proposta não pode mais ser aceita.';
        }
        if (!self::isNomeCompleto($nome)) {
            $erros[] = 'Informe o nome completo.';
        }
        if (!Cpf::validar($cpf)) {
            $erros[] = 'CPF inválido.';
        }
        if (mb_strlen($cargo) < 2) {
            $erros[] = 'Informe o seu cargo na empresa.';
        }
        if (!$declaracao) {
            $erros[] = 'Marque a declaração para confirmar o aceite.';
        }
        if ($erros !== []) {
            return $erros;
        }

        $digitos    = Cpf::somenteDigitos($cpf);
        $token_hash = (string) $this->fields['token_hash'];
        $versao     = (int) $this->fields['versao'];
        $registro   = $this->comTrava(function () use ($token_hash, $versao, $nome, $cargo, $digitos, $ip, $user_agent): ?array {
            // Relido sob trava: outra resposta, reabrir ou cancelar podem ter mudado a proposta.
            if (!$this->mesmaVersaoRespondivel($token_hash, $versao)) {
                return null;
            }
            $hash = $this->calcularHashValores(Calculo::HASH_FORMATO_ATUAL, $cargo);
            $id = Resposta::registrar([
                'plugin_propostas_propostas_id' => $this->getID(),
                'tipo'                          => Resposta::TIPO_ACEITE,
                'versao'                        => (int) $this->fields['versao'],
                'nome'                          => $nome,
                'cpf_cifrado'                   => (new \GLPIKey())->encrypt($digitos),
                'cpf_mascarado'                 => Cpf::mascarar($digitos),
                'cargo'                         => $cargo,
                'hash_valores'                  => $hash,
                'hash_formato'                  => Calculo::HASH_FORMATO_ATUAL,
                'ip'                            => mb_substr($ip, 0, 45),
                'user_agent'                    => mb_substr(mb_scrub($user_agent, 'UTF-8'), 0, 255),
            ]);
            $this->atualizarInterno([
                'id'            => $this->getID(),
                'status'        => self::STATUS_ACEITA,
            ]);

            return ['id' => $id, 'hash' => $hash];
        });
        if ($registro === null) {
            return ['Esta proposta não pode mais ser aceita.'];
        }
        ['id' => $id, 'hash' => $hash] = $registro;

        $codigo = Calculo::codigo($hash);
        $this->adicionarAcompanhamento(sprintf(
            '<p><strong>Proposta nº %s (versão %d) aceita pelo cliente.</strong></p><p>Empresa: %s (CNPJ %s)<br>Nome: %s<br>Cargo: %s<br>CPF: %s<br>Código de verificação: %s</p>',
            htmlescape($this->getNumero()),
            (int) $this->fields['versao'],
            htmlescape((string) $this->fields['empresa_razao_social']),
            htmlescape((string) $this->fields['empresa_cnpj']),
            htmlescape($nome),
            htmlescape($cargo),
            htmlescape(Cpf::mascarar($digitos)),
            htmlescape($codigo)
        ));
        $this->fecharChamado(sprintf(
            '<p>Proposta nº %s (versão %d) aceita pelo cliente. Código de verificação: %s.</p>',
            htmlescape($this->getNumero()),
            (int) $this->fields['versao'],
            htmlescape($codigo)
        ));
        $this->notificar('aceita', ['resposta_id' => $id, 'nome' => $nome, 'codigo' => $codigo, 'mensagem' => '']);

        return [];
    }

    /**
     * @return list<string> mensagens de erro (vazio = dúvida registrada)
     */
    public function registrarDuvida(string $nome, string $mensagem, string $ip, string $user_agent): array
    {
        $nome     = self::normalizarNome($nome);
        // Troca bytes que não são UTF-8 válido: senão o MySQL recusa o INSERT e a página dá erro 500.
        $mensagem = trim(str_replace("\r\n", "\n", mb_scrub($mensagem, 'UTF-8')));
        $erros = [];
        if (!$this->podeResponder()) {
            $erros[] = 'Esta proposta não aceita mais dúvidas por aqui. Fale com o seu consultor.';
        }
        if (mb_strlen($nome) < 3) {
            $erros[] = 'Informe o seu nome.';
        }
        if (mb_strlen($mensagem) < 5) {
            $erros[] = 'Escreva a sua dúvida.';
        } elseif (mb_strlen($mensagem) > 3000) {
            $erros[] = 'A dúvida pode ter até 3.000 caracteres.';
        }
        if ($erros !== []) {
            return $erros;
        }

        $token_hash = (string) $this->fields['token_hash'];
        $versao     = (int) $this->fields['versao'];
        $id = $this->comTrava(function () use ($token_hash, $versao, $nome, $mensagem, $ip, $user_agent): int|string {
            if (!$this->mesmaVersaoRespondivel($token_hash, $versao)) {
                return 'Esta proposta não aceita mais dúvidas por aqui. Fale com o seu consultor.';
            }
            if (countElementsInTable(Resposta::TABELA, [
                'plugin_propostas_propostas_id' => $this->getID(),
                'tipo'                          => Resposta::TIPO_DUVIDA,
                'date_creation'                 => ['>=', date('Y-m-d H:i:s', time() - DAY_TIMESTAMP)],
            ]) >= self::DUVIDAS_POR_DIA) {
                return 'Esta proposta já recebeu muitas dúvidas hoje. Fale direto com o seu consultor.';
            }
            $id = Resposta::registrar([
                'plugin_propostas_propostas_id' => $this->getID(),
                'tipo'                          => Resposta::TIPO_DUVIDA,
                'versao'                        => (int) $this->fields['versao'],
                'nome'                          => $nome,
                'mensagem'                      => $mensagem,
                'ip'                            => mb_substr($ip, 0, 45),
                'user_agent'                    => mb_substr(mb_scrub($user_agent, 'UTF-8'), 0, 255),
            ]);
            $this->atualizarInterno([
                'id'            => $this->getID(),
                'status'        => self::STATUS_DUVIDA,
            ]);

            return $id;
        });
        if (is_string($id)) {
            return [$id];
        }

        $this->adicionarAcompanhamento(sprintf(
            '<p><strong>Dúvida do cliente sobre a proposta nº %s (versão %d).</strong></p><p>De: %s</p><p>%s</p>',
            htmlescape($this->getNumero()),
            (int) $this->fields['versao'],
            htmlescape($nome),
            nl2br(htmlescape($mensagem))
        ));
        $this->notificar('duvida', ['resposta_id' => $id, 'nome' => $nome, 'codigo' => '', 'mensagem' => $mensagem]);

        return [];
    }

    /**
     * Acompanhamento privado no chamado vinculado, se houver (envio, aceite, dúvida, cancelamento
     * e expiração). Sem notificação do chamado: o consultor já recebe a do plugin.
     */
    private function adicionarAcompanhamento(string $html): void
    {
        $tickets_id = (int) $this->fields['tickets_id'];
        if ($tickets_id <= 0) {
            return;
        }

        try {
            (new \ITILFollowup())->add([
                'itemtype'      => \Ticket::class,
                'items_id'      => $tickets_id,
                'content'       => $html,
                'is_private'    => 1,
                '_disablenotif' => true,
            ]);
        } catch (\Throwable $e) {
            \Toolbox::logInFile('propostas', sprintf("Acompanhamento no chamado %d falhou: %s\n", $tickets_id, $e->getMessage()));
        }
    }

    /**
     * O chamado vinculado não serve mais? Fechado, na lixeira ou apagado: não recebe acompanhamentos,
     * então o próximo link abre outro chamado.
     */
    private function chamadoFechado(): bool
    {
        $tickets_id = (int) $this->fields['tickets_id'];
        if ($tickets_id <= 0) {
            return false;
        }
        $ticket = new \Ticket();

        return !$ticket->getFromDB($tickets_id)
            || !empty($ticket->fields['is_deleted'])
            || in_array((int) $ticket->fields['status'], $ticket->getClosedStatusArray(), false);
    }

    /**
     * Fecha o chamado vinculado (aceite e expiração): registra a solução e fecha, sem notificação do
     * chamado. Falha não quebra o aceite nem a expiração: fica no log do plugin.
     */
    private function fecharChamado(string $solucao): void
    {
        $tickets_id = (int) $this->fields['tickets_id'];
        $ticket = new \Ticket();
        if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id)
            || in_array((int) $ticket->fields['status'], $ticket->getClosedStatusArray(), false)) {
            return;
        }

        // O aceite chega pela página pública, sem usuário logado: sem o direito de atualizar chamados,
        // o GLPI recusa a mudança de status. O fechamento roda como sistema (Session::callAsSystem,
        // GLPI 11), só aqui. A expiração já roda como ação automática, onde o GLPI não confere direitos.
        $fechar = static function () use ($ticket, $tickets_id, $solucao): void {
            if (!in_array((int) $ticket->fields['status'], $ticket->getSolvedStatusArray(), false)) {
                (new \ITILSolution())->add([
                    'itemtype'      => \Ticket::class,
                    'items_id'      => $tickets_id,
                    'content'       => $solucao,
                    '_disablenotif' => true,
                ]);
            }
            $ticket->getFromDB($tickets_id);
            if (!in_array((int) $ticket->fields['status'], $ticket->getClosedStatusArray(), false)) {
                $ticket->update([
                    'id'            => $tickets_id,
                    'status'        => \CommonITILObject::CLOSED,
                    '_disablenotif' => true,
                ]);
            }
        };

        try {
            if (method_exists(Session::class, 'callAsSystem')) {
                Session::callAsSystem($fechar);
            } else {
                $fechar();
            }
            $ticket->getFromDB($tickets_id);
            if ((int) $ticket->fields['status'] !== \CommonITILObject::CLOSED) {
                \Toolbox::logInFile('propostas', sprintf("Chamado %d da proposta %d não foi fechado (status %d).\n", $tickets_id, $this->getID(), (int) $ticket->fields['status']));
            }
        } catch (\Throwable $e) {
            \Toolbox::logInFile('propostas', sprintf("Fechamento do chamado %d falhou: %s\n", $tickets_id, $e->getMessage()));
        }
    }

    /**
     * Dispara a notificação sem nunca quebrar a resposta do cliente.
     *
     * @param array<string, mixed> $resposta
     */
    private function notificar(string $evento, array $resposta = []): void
    {
        try {
            \NotificationEvent::raiseEvent($evento, $this, ['resposta' => $resposta]);
        } catch (\Throwable $e) {
            \Toolbox::logInFile('propostas', sprintf("Notificação '%s' da proposta %d falhou: %s\n", $evento, $this->getID(), $e->getMessage()));
        }
    }

    private static function normalizarNome(string $nome): string
    {
        // mb_scrub antes do preg_replace com /u, que devolve null para UTF-8 inválido.
        return mb_substr(trim(preg_replace('/\s+/u', ' ', mb_scrub($nome, 'UTF-8')) ?? ''), 0, 255);
    }

    /**
     * Nome e sobrenome, cada um com ao menos 2 letras.
     */
    private static function isNomeCompleto(string $nome): bool
    {
        $partes = array_filter(explode(' ', $nome), static fn (string $p): bool => mb_strlen($p) >= 2);

        return count($partes) >= 2;
    }

    /**
     * Dados da página pública, já formatados (a página não tem sessão nem preferências de usuário).
     *
     * @return array<string, mixed>
     */
    public function getDadosPublicos(): array
    {
        $moeda = static fn (float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
        $numero = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
        $cores = ['#2563eb', '#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#14b8a6', '#6366f1'];

        $totais = $this->getTotais();
        $grupos = [PropostaItem::TIPO_RECORRENTE => [], PropostaItem::TIPO_PONTUAL => []];
        foreach (PropostaItem::getItensDaProposta($this->getID()) as $i => $item) {
            $subtotal = Calculo::subtotal($item['quantidade'], $item['valor_unitario'], $item['desconto_pct']);
            $total_tipo = $totais[$item['tipo']] ?? 0.0;
            $grupos[$item['tipo']][] = [
                'nome'        => (string) $item['name'],
                'quantidade'  => $numero((float) $item['quantidade']),
                'unitario'    => $moeda((float) $item['valor_unitario']),
                'desconto'    => (float) $item['desconto_pct'] > 0 ? $numero((float) $item['desconto_pct']) . '%' : null,
                'subtotal'    => $moeda($subtotal),
                // Exibição por unidade: com quantidade diferente de 1, o cartão mostra a conta.
                'quantidade_um' => abs((float) $item['quantidade'] - 1.0) < 0.00001,
                // Exibição por unidade: o preço de uma unidade já com o desconto (de/por).
                'final'       => $moeda(Calculo::subtotal(1, $item['valor_unitario'], $item['desconto_pct'])),
                'percentual'  => $total_tipo > 0 ? round($subtotal / $total_tipo * 100, 2) : 0,
                'cor'         => $cores[$i % count($cores)],
            ];
        }

        $consultor = '';
        if ((int) $this->fields['users_id_consultor'] > 0) {
            $user = new \User();
            if ($user->getFromDB((int) $this->fields['users_id_consultor'])) {
                $consultor = $user->getFriendlyName();
            }
        }

        $emissao = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->fields['data_emissao']);

        $aceite = null;
        if ($this->fields['status'] === self::STATUS_ACEITA) {
            $row = Resposta::getAceite($this->getID(), (int) $this->fields['versao']);
            if ($row !== null) {
                $quando = new \DateTimeImmutable((string) $row['date_creation']);
                $aceite = [
                    'data'   => $quando->format('d/m/Y') . ' às ' . $quando->format('H:i'),
                    'codigo' => Calculo::codigo($row['hash_valores']),
                ];
            }
        }

        return [
            'status'        => (string) $this->fields['status'],
            'pode_responder'=> $this->podeResponder(),
            'aceite'        => $aceite,
            'numero'        => $this->getNumero(),
            'titulo'        => (string) $this->fields['name'],
            // Topo sem repetir a razão social: título sem ela no fim e nome fantasia só quando é outro nome.
            'subtitulo'     => Cabecalho::subtitulo((string) $this->fields['name'], (string) $this->fields['empresa_razao_social']),
            'empresa'       => [
                'razao_social'  => (string) $this->fields['empresa_razao_social'],
                'nome_fantasia' => Cabecalho::nomeFantasia(
                    (string) $this->fields['empresa_nome_fantasia'],
                    (string) $this->fields['empresa_razao_social']
                ),
                'cnpj'          => Cnpj::formatar((string) $this->fields['empresa_cnpj']),
            ],
            'blocos'        => array_map(
                static fn (array $b): array => [
                    'titulo'   => (string) $b['name'],
                    'conteudo' => (string) $b['conteudo'],
                    'depois'   => !empty($b['depois_dos_valores']),
                ],
                PropostaBloco::getBlocosDaProposta($this->getID())
            ),
            'versao'        => (int) $this->fields['versao'],
            'emissao'       => $emissao ? $emissao->format('d/m/Y') : '',
            'validade_dias' => (int) $this->fields['validade_dias'],
            'valida_ate'    => $this->calcularExpiracao()->format('d/m/Y'),
            'solicitante'   => (string) $this->fields['solicitante'],
            'consultor'     => $consultor,
            'aviso_previo'  => (int) $this->fields['aviso_previo_dias'],
            'condicoes'     => (string) $this->fields['condicoes'],
            'exibicao'      => Calculo::exibicaoValida($this->fields['exibicao'] ?? null),
            'recorrentes'   => $grupos[PropostaItem::TIPO_RECORRENTE],
            'pontuais'      => $grupos[PropostaItem::TIPO_PONTUAL],
            'total_mensal'  => $moeda($totais[PropostaItem::TIPO_RECORRENTE]),
            'total_unico'   => $moeda($totais[PropostaItem::TIPO_PONTUAL]),
        ];
    }

    /**
     * Número exibido ao cliente (ex.: 00069).
     */
    public function getNumero(): string
    {
        return $this->isNewItem() ? '' : sprintf('%05d', $this->getID());
    }

    /**
     * Soma os subtotais dos itens por tipo.
     *
     * @return array{recorrente: float, pontual: float}
     */
    public function getTotais(): array
    {
        return Calculo::totais(PropostaItem::getItensDaProposta($this->getID()));
    }

    /**
     * @return array{recorrente: string, pontual: string}
     */
    public function getTotaisFormatados(): array
    {
        return array_map(static fn (float $v): string => Html::formatNumber($v), $this->getTotais());
    }

    /**
     * Proposta com aceite não pode ser excluída: levaria junto a prova do aceite
     * (respostas, código de verificação, acessos e histórico).
     */
    public function canPurgeItem(): bool
    {
        if (!parent::canPurgeItem() || !$this->visivelParaUsuario()) {
            return false;
        }
        return !$this->temAceite();
    }

    public function temAceite(): bool
    {
        return countElementsInTable(Resposta::TABELA, [
            'plugin_propostas_propostas_id' => $this->getID(),
            'tipo'                          => Resposta::TIPO_ACEITE,
        ]) > 0;
    }

    /**
     * Entidade em que o usuário pode criar a cópia ou o chamado: a da proposta, se ele tem acesso
     * direto a ela; senão (proposta recursiva de uma entidade acima), a entidade ativa.
     */
    private function entidadeDeTrabalho(): int
    {
        $entidade = (int) $this->fields['entities_id'];

        return Session::haveAccessToEntity($entidade) ? $entidade : (int) Session::getActiveEntity();
    }

    public function post_purgeItem(): void
    {
        Equipe::sincronizarChamados([(int) ($this->fields['tickets_id'] ?? 0)]);
    }

    public function cleanDBonPurge(): void
    {
        global $DB;

        $this->deleteChildrenAndRelationsFromDb([PropostaItem::class, PropostaBloco::class]);
        foreach ([Resposta::TABELA, Acesso::TABELA] as $tabela) {
            $DB->delete($tabela, ['plugin_propostas_propostas_id' => $this->getID()]);
        }
    }

    public function rawSearchOptions(): array
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => __('Characteristics'),
        ];

        $tab[] = [
            'id'            => 1,
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Name'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 2,
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'         => 3,
            'table'      => self::getTable(),
            'field'      => 'status',
            'name'       => __('Status'),
            'datatype'   => 'specific',
            'searchtype' => ['equals', 'notequals'],
            // Só as ações da proposta mudam o status.
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => self::getTable(),
            'field'    => 'data_emissao',
            'name'     => __('Emissão', 'propostas'),
            'datatype' => 'date',
        ];

        $tab[] = [
            'id'        => 5,
            'table'     => 'glpi_users',
            'field'     => 'name',
            'linkfield' => 'users_id_consultor',
            'name'      => __('Consultor', 'propostas'),
            'datatype'  => 'dropdown',
            'right'     => 'all',
        ];

        $tab[] = [
            'id'        => 6,
            'table'     => 'glpi_tickets',
            'field'     => 'name',
            'linkfield' => 'tickets_id',
            'name'      => __('Chamado vinculado', 'propostas'),
            'datatype'  => 'dropdown',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => self::getTable(),
            'field'    => 'solicitante',
            'name'     => __('Solicitado por', 'propostas'),
            'datatype' => 'string',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => self::getTable(),
            'field'    => 'versao',
            'name'     => __('Versão', 'propostas'),
            'datatype' => 'integer',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => self::getTable(),
            'field'    => 'validade_dias',
            'name'     => __('Validade (dias)', 'propostas'),
            'datatype' => 'integer',
        ];

        $tab[] = ['id' => 10, 'table' => self::getTable(), 'field' => 'empresa_razao_social', 'name' => __('Razão social', 'propostas'), 'datatype' => 'string'];
        $tab[] = ['id' => 11, 'table' => self::getTable(), 'field' => 'empresa_cnpj', 'name' => __('CNPJ', 'propostas'), 'datatype' => 'string'];
        $tab[] = ['id' => 12, 'table' => self::getTable(), 'field' => 'empresa_nome_fantasia', 'name' => __('Nome fantasia', 'propostas'), 'datatype' => 'string'];
        $tab[] = [
            'id'            => 13,
            'table'         => Modelo::getTable(),
            'field'         => 'name',
            'linkfield'     => 'plugin_propostas_modelos_id',
            'name'          => Modelo::getTypeName(1),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 16,
            'table'    => self::getTable(),
            'field'    => 'comment',
            'name'     => __('Comments'),
            'datatype' => 'text',
        ];

        $tab[] = [
            'id'            => 19,
            'table'         => self::getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => 121,
            'table'         => self::getTable(),
            'field'         => 'date_creation',
            'name'          => __('Creation date'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 80,
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => \Entity::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'status') {
            return htmlescape(self::getStatusList()[$values[$field]] ?? (string) $values[$field]);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        if ($field === 'status') {
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getStatusList(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }
}
