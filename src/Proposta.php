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
        if (empty($input['data_emissao'])) {
            $input['data_emissao'] = date('Y-m-d');
        }
        $input['status'] = self::STATUS_RASCUNHO;
        $input['versao'] = 1;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        // Ações do próprio plugin (gerar link, reabrir, cancelar, visualização) passam direto.
        if (!empty($input['_acao_interna'])) {
            unset($input['_acao_interna']);
            return $input;
        }

        foreach (self::CAMPOS_INTERNOS as $campo) {
            unset($input[$campo]);
        }

        if (!$this->isEditavel()) {
            $ignorados = [];
            foreach ($input as $campo => $valor) {
                if (str_starts_with($campo, '_') || in_array($campo, self::CAMPOS_LIVRES_TRAVADA, true)) {
                    continue;
                }
                if (array_key_exists($campo, $this->fields) && (string) $this->fields[$campo] !== (string) $valor) {
                    $ignorados[] = $campo;
                }
                unset($input[$campo]);
            }
            if ($ignorados !== []) {
                Session::addMessageAfterRedirect(
                    htmlescape(__('A proposta já foi enviada: só os comentários podem mudar. Para alterar valores ou condições, use "Reabrir para edição".', 'propostas')),
                    false,
                    WARNING
                );
            }
        }

        return $input;
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

        TemplateRenderer::getInstance()->display('@propostas/proposta.form.html.twig', [
            'item'          => $this,
            'params'        => $options,
            'numero'        => $this->getNumero(),
            'status_label'  => self::getStatusList()[$this->fields['status']] ?? $this->fields['status'],
            'editavel'      => $this->isEditavel(),
            'totais'        => $this->isNewItem() ? null : $this->getTotaisFormatados(),
            'link_publico'  => $this->isNewItem() ? null : $this->getLinkPublico(),
            'link_expira'   => $this->fields['token_expira'] ?? null,
            'link_expirado' => $this->isLinkExpirado(),
            'pode_gerar'    => $this->podeGerarLink(),
            'pode_reabrir'  => $this->podeReabrir(),
            'pode_cancelar' => $this->podeCancelar(),
            'pode_duplicar' => !$this->isNewItem() && Session::haveRight(self::$rightname, CREATE),
        ]);

        return true;
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
                && $proposta->update(['id' => $proposta->getID(), '_acao_interna' => true, 'status' => self::STATUS_EXPIRADA])
            ) {
                $expiradas++;
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
        $id = $nova->add([
            'entities_id'        => $this->fields['entities_id'],
            'is_recursive'       => $this->fields['is_recursive'],
            'name'               => sprintf(__('%s (cópia)', 'propostas'), $this->fields['name']),
            'solicitante'        => $this->fields['solicitante'],
            'users_id_consultor' => Session::getLoginUserID() ?: $this->fields['users_id_consultor'],
            'tickets_id'         => 0,
            'data_emissao'       => date('Y-m-d'),
            'validade_dias'      => $this->fields['validade_dias'],
            'aviso_previo_dias'  => $this->fields['aviso_previo_dias'],
            'condicoes'          => $this->fields['condicoes'],
        ]);
        if (!$id) {
            return false;
        }

        foreach (PropostaItem::getItensDaProposta($this->getID()) as $item) {
            (new PropostaItem())->add([
                PropostaItem::$items_id => $id,
                'tipo'                  => $item['tipo'],
                'name'                  => $item['name'],
                'quantidade'            => $item['quantidade'],
                'valor_unitario'        => $item['valor_unitario'],
                'desconto_pct'          => $item['desconto_pct'],
                'ordem'                 => $item['ordem'],
            ]);
        }

        Session::addMessageAfterRedirect(htmlescape(sprintf(
            __('Proposta duplicada a partir da nº %s. Confira os dados e os itens antes de gerar o link.', 'propostas'),
            $this->getNumero()
        )));

        return (int) $id;
    }

    /**
     * Gera o link público, trava a proposta e marca como enviada.
     * Um link anterior deixa de valer.
     */
    public function gerarLink(): bool
    {
        if (!$this->podeGerarLink()) {
            return false;
        }
        if (PropostaItem::getItensDaProposta($this->getID()) === []) {
            Session::addMessageAfterRedirect(htmlescape(__('Inclua ao menos um item antes de gerar o link.', 'propostas')), false, ERROR);
            return false;
        }

        $expira = $this->calcularExpiracao();
        if ($expira <= new \DateTimeImmutable()) {
            Session::addMessageAfterRedirect(htmlescape(__('A validade já terminou: ajuste a emissão ou a validade antes de gerar o link.', 'propostas')), false, ERROR);
            return false;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $ok = $this->update([
            'id'             => $this->getID(),
            '_acao_interna'  => true,
            'status'         => self::STATUS_ENVIADA,
            'token_hash'     => hash('sha256', $token),
            'token_cifrado'  => (new \GLPIKey())->encrypt($token),
            'token_expira'   => $expira->format('Y-m-d H:i:s'),
        ]);
        if ($ok) {
            Session::addMessageAfterRedirect(htmlescape(__('Link gerado. A proposta está travada para edição.', 'propostas')));
        }

        return $ok;
    }

    /**
     * Volta para rascunho como nova versão. O link anterior deixa de valer.
     */
    public function reabrir(): bool
    {
        if (!$this->podeReabrir()) {
            return false;
        }

        $ok = $this->update([
            'id'            => $this->getID(),
            '_acao_interna' => true,
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
        }

        return $ok;
    }

    public function cancelar(): bool
    {
        if (!$this->podeCancelar()) {
            return false;
        }

        return $this->update([
            'id'            => $this->getID(),
            '_acao_interna' => true,
            'status'        => self::STATUS_CANCELADA,
            'token_hash'    => 'NULL',
            'token_cifrado' => 'NULL',
            'token_expira'  => 'NULL',
        ]);
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
        $this->update([
            'id'            => $this->getID(),
            '_acao_interna' => true,
            'status'        => self::STATUS_VISUALIZADA,
        ]);
        $this->notificar('aberta');
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
    public function calcularHashValores(): string
    {
        return Calculo::hashValores([
            'numero'        => $this->getNumero(),
            'versao'        => (int) $this->fields['versao'],
            'titulo'        => (string) $this->fields['name'],
            'emissao'       => (string) $this->fields['data_emissao'],
            'validade_dias' => (int) $this->fields['validade_dias'],
            'aviso_previo'  => (int) $this->fields['aviso_previo_dias'],
            'condicoes'     => (string) $this->fields['condicoes'],
        ], PropostaItem::getItensDaProposta($this->getID()));
    }

    /**
     * @return list<string> mensagens de erro (vazio = aceite registrado)
     */
    public function aceitar(string $nome, string $cpf, bool $declaracao, string $ip, string $user_agent): array
    {
        $nome = self::normalizarNome($nome);
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
        if (!$declaracao) {
            $erros[] = 'Marque a declaração para confirmar o aceite.';
        }
        if ($erros !== []) {
            return $erros;
        }

        $digitos = Cpf::somenteDigitos($cpf);
        $hash    = $this->calcularHashValores();
        $id = Resposta::registrar([
            'plugin_propostas_propostas_id' => $this->getID(),
            'tipo'                          => Resposta::TIPO_ACEITE,
            'versao'                        => (int) $this->fields['versao'],
            'nome'                          => $nome,
            'cpf_cifrado'                   => (new \GLPIKey())->encrypt($digitos),
            'cpf_mascarado'                 => Cpf::mascarar($digitos),
            'hash_valores'                  => $hash,
            'ip'                            => mb_substr($ip, 0, 45),
            'user_agent'                    => mb_substr(mb_scrub($user_agent, 'UTF-8'), 0, 255),
        ]);

        $this->update([
            'id'            => $this->getID(),
            '_acao_interna' => true,
            'status'        => self::STATUS_ACEITA,
        ]);

        $codigo = Calculo::codigo($hash);
        $this->adicionarAcompanhamento(sprintf(
            '<p><strong>Proposta nº %s (versão %d) aceita pelo cliente.</strong></p><p>Nome: %s<br>CPF: %s<br>Código de verificação: %s</p>',
            htmlescape($this->getNumero()),
            (int) $this->fields['versao'],
            htmlescape($nome),
            htmlescape(Cpf::mascarar($digitos)),
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

        $id = Resposta::registrar([
            'plugin_propostas_propostas_id' => $this->getID(),
            'tipo'                          => Resposta::TIPO_DUVIDA,
            'versao'                        => (int) $this->fields['versao'],
            'nome'                          => $nome,
            'mensagem'                      => $mensagem,
            'ip'                            => mb_substr($ip, 0, 45),
            'user_agent'                    => mb_substr(mb_scrub($user_agent, 'UTF-8'), 0, 255),
        ]);

        $this->update([
            'id'            => $this->getID(),
            '_acao_interna' => true,
            'status'        => self::STATUS_DUVIDA,
        ]);

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
     * Acompanhamento privado no chamado vinculado, se houver. Sem notificação do chamado:
     * o consultor já recebe a do plugin.
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
            $subtotal = PropostaItem::calcularSubtotal($item);
            $total_tipo = $totais[$item['tipo']] ?? 0.0;
            $grupos[$item['tipo']][] = [
                'nome'        => (string) $item['name'],
                'quantidade'  => $numero((float) $item['quantidade']),
                'unitario'    => $moeda((float) $item['valor_unitario']),
                'desconto'    => (float) $item['desconto_pct'] > 0 ? $numero((float) $item['desconto_pct']) . '%' : null,
                'subtotal'    => $moeda($subtotal),
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
                    'nome'   => (string) $row['nome'],
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
            'versao'        => (int) $this->fields['versao'],
            'emissao'       => $emissao ? $emissao->format('d/m/Y') : '',
            'validade_dias' => (int) $this->fields['validade_dias'],
            'valida_ate'    => $this->calcularExpiracao()->format('d/m/Y'),
            'solicitante'   => (string) $this->fields['solicitante'],
            'consultor'     => $consultor,
            'aviso_previo'  => (int) $this->fields['aviso_previo_dias'],
            'condicoes'     => (string) $this->fields['condicoes'],
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

    public function cleanDBonPurge(): void
    {
        global $DB;

        $this->deleteChildrenAndRelationsFromDb([PropostaItem::class]);
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
        ];

        $tab[] = [
            'id'       => 9,
            'table'    => self::getTable(),
            'field'    => 'validade_dias',
            'name'     => __('Validade (dias)', 'propostas'),
            'datatype' => 'integer',
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
