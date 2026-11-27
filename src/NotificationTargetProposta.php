<?php

/**
 * Propostas - notificações (Configurar > Notificações).
 *
 * Eventos: aberta (cliente viu pela primeira vez), aceita, duvida, expirada e os lembretes nao_vista e vencendo.
 * Destinatários próprios: o consultor da proposta e o gestor da equipe atual do consultor.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use Notification;
use Notification_NotificationTemplate;
use NotificationTarget;
use NotificationTemplate;
use NotificationTemplateTranslation;

class NotificationTargetProposta extends NotificationTarget
{
    /** Destinatário próprio: o usuário do campo "Consultor". */
    public const CONSULTOR = 7801;

    /** Destinatário próprio: o(s) Responsável(is) da equipe atual do consultor. */
    public const GESTOR = 7802;

    private const NOME_MODELO = 'Propostas';

    public function getEvents()
    {
        return [
            'aberta'   => __('Proposta aberta pelo cliente', 'propostas'),
            'aceita'   => __('Proposta aceita pelo cliente', 'propostas'),
            'duvida'   => __('Dúvida do cliente sobre a proposta', 'propostas'),
            'expirada' => __('Proposta expirada', 'propostas'),
            'nao_vista' => __('Proposta ainda não vista pelo cliente', 'propostas'),
            'vencendo'  => __('Proposta perto de vencer', 'propostas'),
        ];
    }

    public function addAdditionalTargets($event = '')
    {
        $this->addTarget(self::CONSULTOR, __('Consultor da proposta', 'propostas'));
        $this->addTarget(self::GESTOR, __('Gestor da equipe do consultor', 'propostas'));
    }

    public function addSpecificTargets($data, $options)
    {
        if ((int) $data['items_id'] === self::CONSULTOR) {
            $this->addUserByField('users_id_consultor');
        } elseif ((int) $data['items_id'] === self::GESTOR) {
            $this->adicionarGestores();
        }
    }

    /** Gestores calculados na hora do envio, pela equipe atual do consultor. */
    private function adicionarGestores(): void
    {
        global $DB;

        $gestores = Equipe::gestoresDoConsultor((int) $this->obj->getField('users_id_consultor'));
        if ($gestores === []) {
            return;
        }
        $criteria = $this->getDistinctUserCriteria() + $this->getProfileJoinCriteria();
        $criteria['FROM'] = \User::getTable();
        $criteria['WHERE'][\User::getTable() . '.id'] = $gestores;
        foreach ($DB->request($criteria) as $data) {
            $this->addToRecipientsList($data);
        }
    }

    public function addDataForTemplate($event, $options = [])
    {
        global $CFG_GLPI;

        $eventos  = $this->getAllEvents();
        $proposta = $this->obj;
        $resposta = $options['resposta'] ?? [];
        $status   = Proposta::getStatusList()[$proposta->fields['status']] ?? $proposta->fields['status'];

        $this->data['##lang.proposta.title##']    = $eventos[$event] ?? '';
        $this->data['##lang.proposta.numero##']   = __('Número', 'propostas');
        $this->data['##lang.proposta.titulo##']   = __('Título', 'propostas');
        $this->data['##lang.proposta.versao##']   = __('Versão', 'propostas');
        $this->data['##lang.proposta.status##']   = __('Status');
        $this->data['##lang.proposta.cliente##']  = __('Cliente', 'propostas');
        $this->data['##lang.proposta.mensagem##'] = __('Mensagem', 'propostas');
        $this->data['##lang.proposta.codigo##']   = __('Código de verificação', 'propostas');
        $this->data['##lang.proposta.url##']      = __('Abrir no GLPI', 'propostas');

        $this->data['##proposta.numero##']   = $proposta->getNumero();
        $this->data['##proposta.titulo##']   = (string) $proposta->fields['name'];
        $this->data['##proposta.versao##']   = (string) $proposta->fields['versao'];
        $this->data['##proposta.status##']   = (string) $status;
        // Texto digitado pelo cliente: sem "<" e ">" o GLPI trata como texto puro, não como HTML
        // (evita link ou imagem externa enviados pela página pública dentro do e-mail).
        $this->data['##proposta.cliente##']  = self::textoPuro((string) ($resposta['nome'] ?? ''));
        $this->data['##proposta.mensagem##'] = self::textoPuro((string) ($resposta['mensagem'] ?? ''));
        $this->data['##proposta.codigo##']   = (string) ($resposta['codigo'] ?? '');
        $this->data['##proposta.url##']      = rtrim((string) $CFG_GLPI['url_base'], '/')
            . Proposta::getFormURLWithID($proposta->getID(), false);

        $this->getTags();
        foreach ($this->tag_descriptions[NotificationTarget::TAG_LANGUAGE] as $tag => $values) {
            if (!isset($this->data[$tag])) {
                $this->data[$tag] = $values['label'];
            }
        }
    }

    private static function textoPuro(string $texto): string
    {
        return str_replace(['<', '>'], ['‹', '›'], $texto);
    }

    public function getTags()
    {
        $tags = [
            'proposta.numero'   => __('Número', 'propostas'),
            'proposta.titulo'   => __('Título', 'propostas'),
            'proposta.versao'   => __('Versão', 'propostas'),
            'proposta.status'   => __('Status'),
            'proposta.cliente'  => __('Cliente', 'propostas'),
            'proposta.mensagem' => __('Mensagem', 'propostas'),
            'proposta.codigo'   => __('Código de verificação', 'propostas'),
            'proposta.url'      => __('Abrir no GLPI', 'propostas'),
        ];
        foreach ($tags as $tag => $label) {
            $this->addTagToList(['tag' => $tag, 'label' => $label, 'value' => true]);
            $this->addTagToList(['tag' => $tag, 'label' => $label, 'value' => false, 'lang' => true]);
        }
        $this->addTagToList([
            'tag'   => 'proposta.title',
            'label' => __('Nome do evento', 'propostas'),
            'value' => false,
            'lang'  => true,
        ]);

        asort($this->tag_descriptions);
    }

    /**
     * Cria modelo, tradução, as 3 notificações e o destinatário "Consultor".
     * Só cria o que ainda não existir (seguro para reinstalar).
     */
    public static function instalar(): void
    {
        global $DB;

        // Só na primeira instalação: reinstalar (--force) não recria notificação apagada nem
        // duplica o modelo renomeado pelo administrador.
        $marca = \Config::getConfigurationValues('plugin:propostas', ['instalado_notificacoes', 'instalado_notificacoes_equipes', 'instalado_notificacoes_lembretes']);
        if (!empty($marca['instalado_notificacoes'])) {
            if (empty($marca['instalado_notificacoes_equipes'])) {
                self::instalarEquipes();
            }
            if (empty($marca['instalado_notificacoes_lembretes'])) {
                self::instalarLembretes();
            }
            return;
        }
        $itemtype = Proposta::class;
        $agora    = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $modelo = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => NotificationTemplate::getTable(),
            'WHERE'  => ['itemtype' => $itemtype, 'name' => self::NOME_MODELO],
        ])->current();

        $templates_id = $modelo['id'] ?? (new NotificationTemplate())->add([
            'name'     => self::NOME_MODELO,
            'itemtype' => $itemtype,
            'date_mod' => $agora,
            'comment'  => '',
            'css'      => '',
        ]);
        if (!$templates_id) {
            // Sem marcador: a próxima instalação (--force) tenta de novo.
            return;
        }

        if (countElementsInTable(NotificationTemplateTranslation::getTable(), ['notificationtemplates_id' => $templates_id]) === 0) {
            (new NotificationTemplateTranslation())->add([
                'notificationtemplates_id' => $templates_id,
                'language'                 => '',
                'subject'                  => '##lang.proposta.title## - nº ##proposta.numero## ##proposta.titulo##',
                'content_text'             => self::conteudoTexto(),
                'content_html'             => self::conteudoHtml(),
            ]);
        }

        foreach (self::NOMES as $evento => $nome) {
            self::criarNotificacao($evento, $nome, (int) $templates_id);
        }
        // Notificações que já existiam (atualização de versão sem marcador) recebem o Gestor também.
        // Marcadores só depois de tudo criado.
        self::instalarEquipes();
        \Config::setConfigurationValues('plugin:propostas', ['instalado_notificacoes' => '1', 'instalado_notificacoes_equipes' => '1', 'instalado_notificacoes_lembretes' => '1']);
    }

    /** Nomes das notificações criadas na instalação (um por evento). */
    private const NOMES = [
        'aberta'   => 'Proposta aberta pelo cliente',
        'aceita'   => 'Proposta aceita pelo cliente',
        'duvida'   => 'Dúvida do cliente sobre a proposta',
        'expirada' => 'Proposta expirada',
        'nao_vista' => 'Proposta ainda não vista pelo cliente',
        'vencendo'  => 'Proposta perto de vencer',
    ];

    /**
     * Notificação do evento com os destinatários Consultor e Gestor, se ainda não existir.
     */
    private static function criarNotificacao(string $evento, string $nome, int $templates_id): void
    {
        global $DB;

        $itemtype = Proposta::class;
        if ($templates_id <= 0 || countElementsInTable(Notification::getTable(), ['itemtype' => $itemtype, 'event' => $evento]) > 0) {
            return;
        }
        $notifications_id = (new Notification())->add([
            'name'         => $nome,
            'entities_id'  => 0,
            'is_recursive' => 1,
            'is_active'    => 1,
            'itemtype'     => $itemtype,
            'event'        => $evento,
            'comment'      => '',
            'date_mod'     => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
        if (!$notifications_id) {
            return;
        }
        (new Notification_NotificationTemplate())->add([
            'notifications_id'         => $notifications_id,
            'notificationtemplates_id' => $templates_id,
            'mode'                     => 'mailing',
        ]);
        foreach ([self::CONSULTOR, self::GESTOR] as $alvo) {
            $DB->insert('glpi_notificationtargets', [
                'notifications_id' => $notifications_id,
                'type'             => Notification::USER_TYPE,
                'items_id'         => $alvo,
            ]);
        }
    }

    /**
     * Atualização para as equipes, uma vez só: a notificação "expirada" e o destinatário
     * Gestor nas notificações do plugin que já existem. Não recria o que foi apagado.
     */
    private static function instalarEquipes(): void
    {
        global $DB;

        \Config::setConfigurationValues('plugin:propostas', ['instalado_notificacoes_equipes' => '1']);
        $itemtype = Proposta::class;

        self::criarNotificacao('expirada', self::NOMES['expirada'], self::modeloExistente());

        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => Notification::getTable(), 'WHERE' => ['itemtype' => $itemtype]]) as $row) {
            $ja_tem = countElementsInTable('glpi_notificationtargets', [
                'notifications_id' => $row['id'],
                'type'             => Notification::USER_TYPE,
                'items_id'         => self::GESTOR,
            ]) > 0;
            if (!$ja_tem) {
                $DB->insert('glpi_notificationtargets', [
                    'notifications_id' => $row['id'],
                    'type'             => Notification::USER_TYPE,
                    'items_id'         => self::GESTOR,
                ]);
            }
        }
    }

    /**
     * Atualização para a 1.10.0, uma vez só: as notificações dos lembretes, com o modelo do plugin.
     * Não recria o que foi apagado.
     */
    private static function instalarLembretes(): void
    {
        \Config::setConfigurationValues('plugin:propostas', ['instalado_notificacoes_lembretes' => '1']);
        foreach (['nao_vista', 'vencendo'] as $evento) {
            self::criarNotificacao($evento, self::NOMES[$evento], self::modeloExistente());
        }
    }

    /** Primeiro modelo de notificação do plugin (o administrador pode tê-lo renomeado). */
    private static function modeloExistente(): int
    {
        global $DB;

        $modelo = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => NotificationTemplate::getTable(),
            'WHERE'  => ['itemtype' => Proposta::class],
            'ORDER'  => 'id',
            'LIMIT'  => 1,
        ])->current();

        return (int) ($modelo['id'] ?? 0);
    }

    public static function desinstalar(): void
    {
        global $DB;

        $itemtype = Proposta::class;

        $notificacoes = array_column(iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => Notification::getTable(),
            'WHERE'  => ['itemtype' => $itemtype],
        ]), false), 'id');
        if ($notificacoes !== []) {
            $DB->delete('glpi_notificationtargets', ['notifications_id' => $notificacoes]);
            $DB->delete(Notification_NotificationTemplate::getTable(), ['notifications_id' => $notificacoes]);
            $DB->delete(Notification::getTable(), ['id' => $notificacoes]);
        }

        $modelos = array_column(iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => NotificationTemplate::getTable(),
            'WHERE'  => ['itemtype' => $itemtype],
        ]), false), 'id');
        if ($modelos !== []) {
            $DB->delete(NotificationTemplateTranslation::getTable(), ['notificationtemplates_id' => $modelos]);
            $DB->delete(NotificationTemplate::getTable(), ['id' => $modelos]);
        }

        $DB->delete('glpi_queuednotifications', ['itemtype' => $itemtype]);
    }

    private static function conteudoTexto(): string
    {
        return "##lang.proposta.title##\n\n"
            . "##lang.proposta.numero##: ##proposta.numero## (##lang.proposta.versao## ##proposta.versao##)\n"
            . "##lang.proposta.titulo##: ##proposta.titulo##\n"
            . "##lang.proposta.status##: ##proposta.status##\n"
            . "##IFproposta.cliente####lang.proposta.cliente##: ##proposta.cliente##\n##ENDIFproposta.cliente##"
            . "##IFproposta.codigo####lang.proposta.codigo##: ##proposta.codigo##\n##ENDIFproposta.codigo##"
            . "##IFproposta.mensagem##\n##lang.proposta.mensagem##:\n##proposta.mensagem##\n##ENDIFproposta.mensagem##"
            . "\n##lang.proposta.url##: ##proposta.url##\n";
    }

    private static function conteudoHtml(): string
    {
        return '<p><strong>##lang.proposta.title##</strong></p>'
            . '<p>##lang.proposta.numero##: ##proposta.numero## (##lang.proposta.versao## ##proposta.versao##)<br>'
            . '##lang.proposta.titulo##: ##proposta.titulo##<br>'
            . '##lang.proposta.status##: ##proposta.status##<br>'
            . '##IFproposta.cliente####lang.proposta.cliente##: ##proposta.cliente##<br>##ENDIFproposta.cliente##'
            . '##IFproposta.codigo####lang.proposta.codigo##: ##proposta.codigo##<br>##ENDIFproposta.codigo##'
            . '</p>'
            . '##IFproposta.mensagem##<p>##lang.proposta.mensagem##:<br>##proposta.mensagem##</p>##ENDIFproposta.mensagem##'
            . '<p><a href="##proposta.url##">##lang.proposta.url##</a></p>';
    }
}
