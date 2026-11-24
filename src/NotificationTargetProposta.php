<?php

/**
 * Propostas - notificações (Configurar > Notificações).
 *
 * Eventos: aberta (primeira abertura pelo cliente), aceita, duvida.
 * Destinatário padrão: o consultor da proposta.
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

    private const NOME_MODELO = 'Propostas';

    public function getEvents()
    {
        return [
            'aberta' => __('Proposta aberta pelo cliente', 'propostas'),
            'aceita' => __('Proposta aceita pelo cliente', 'propostas'),
            'duvida' => __('Dúvida do cliente sobre a proposta', 'propostas'),
        ];
    }

    public function addAdditionalTargets($event = '')
    {
        $this->addTarget(self::CONSULTOR, __('Consultor da proposta', 'propostas'));
    }

    public function addSpecificTargets($data, $options)
    {
        if ((int) $data['items_id'] === self::CONSULTOR) {
            $this->addUserByField('users_id_consultor');
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
        $marca = \Config::getConfigurationValues('plugin:propostas', ['instalado_notificacoes']);
        if (!empty($marca['instalado_notificacoes'])) {
            return;
        }
        \Config::setConfigurationValues('plugin:propostas', ['instalado_notificacoes' => '1']);

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

        $nomes = [
            'aberta' => 'Proposta aberta pelo cliente',
            'aceita' => 'Proposta aceita pelo cliente',
            'duvida' => 'Dúvida do cliente sobre a proposta',
        ];
        foreach ($nomes as $evento => $nome) {
            if (countElementsInTable(Notification::getTable(), ['itemtype' => $itemtype, 'event' => $evento]) > 0) {
                continue;
            }
            $notifications_id = (new Notification())->add([
                'name'         => $nome,
                'entities_id'  => 0,
                'is_recursive' => 1,
                'is_active'    => 1,
                'itemtype'     => $itemtype,
                'event'        => $evento,
                'comment'      => '',
                'date_mod'     => $agora,
            ]);
            if (!$notifications_id) {
                continue;
            }
            (new Notification_NotificationTemplate())->add([
                'notifications_id'         => $notifications_id,
                'notificationtemplates_id' => $templates_id,
                'mode'                     => 'mailing',
            ]);
            $DB->insert('glpi_notificationtargets', [
                'notifications_id' => $notifications_id,
                'type'             => Notification::USER_TYPE,
                'items_id'         => self::CONSULTOR,
            ]);
        }
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
