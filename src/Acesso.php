<?php

/**
 * Propostas - registro de cada abertura do link público por quem não está logado no GLPI.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

class Acesso extends CommonGLPI
{
    public static $rightname = 'plugin_propostas_proposta';

    public const TABELA = 'glpi_plugin_propostas_acessos';

    /** Quantos acessos a aba mostra. */
    private const LIMITE_LISTA = 200;

    public static function getTypeName($nb = 0): string
    {
        return _n('Acesso ao link', 'Acessos ao link', $nb, 'propostas');
    }

    public static function registrar(int $propostas_id, int $versao, string $ip, string $user_agent): void
    {
        global $DB;

        $DB->insert(self::TABELA, [
            'plugin_propostas_propostas_id' => $propostas_id,
            'versao'                        => $versao,
            'ip'                            => mb_substr($ip, 0, 45),
            'user_agent'                    => mb_substr(mb_scrub($user_agent, 'UTF-8'), 0, 255),
            'date_creation'                 => Session::getCurrentTime() ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Proposta && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::TABELA, ['plugin_propostas_propostas_id' => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-eye');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;

        if (!$item instanceof Proposta) {
            return false;
        }

        $linhas = iterator_to_array($DB->request([
            'FROM'  => self::TABELA,
            'WHERE' => ['plugin_propostas_propostas_id' => $item->getID()],
            'ORDER' => ['date_creation DESC', 'id DESC'],
            'LIMIT' => self::LIMITE_LISTA,
        ]), false);

        TemplateRenderer::getInstance()->display('@propostas/acessos.html.twig', [
            'linhas' => $linhas,
            'limite' => self::LIMITE_LISTA,
        ]);

        return true;
    }
}
