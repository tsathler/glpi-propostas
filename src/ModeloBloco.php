<?php

/**
 * Propostas - blocos de texto usados por um modelo, com a ordem.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBChild;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

class ModeloBloco extends CommonDBChild
{
    public static $itemtype = Modelo::class;
    public static $items_id = 'plugin_propostas_modelos_id';

    public static $rightname = 'plugin_propostas_modelo';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_propostas_modelos_blocos';
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Bloco de texto', 'Blocos de texto', $nb, 'propostas');
    }

    /**
     * Blocos do modelo na ordem, com título e texto atuais da biblioteca.
     *
     * @return list<array{id: int, plugin_propostas_blocos_id: int, ordem: int, name: string, conteudo: string, is_active: int}>
     */
    public static function getBlocosDoModelo(int $modelos_id): array
    {
        global $DB;

        $linhas = [];
        foreach (
            $DB->request([
                'SELECT'     => [self::getTable() . '.id', self::getTable() . '.plugin_propostas_blocos_id', self::getTable() . '.ordem',
                    Bloco::getTable() . '.name', Bloco::getTable() . '.conteudo', Bloco::getTable() . '.is_active',
                    Bloco::getTable() . '.depois_dos_valores'],
                'FROM'       => self::getTable(),
                'INNER JOIN' => [Bloco::getTable() => ['ON' => [self::getTable() => 'plugin_propostas_blocos_id', Bloco::getTable() => 'id']]],
                'WHERE'      => [self::getTable() . '.' . self::$items_id => $modelos_id],
                'ORDER'      => [self::getTable() . '.ordem ASC', self::getTable() . '.id ASC'],
            ]) as $row
        ) {
            $linhas[] = $row;
        }

        return $linhas;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function getModelosDoBloco(int $blocos_id): array
    {
        global $DB;

        return iterator_to_array($DB->request([
            'SELECT'     => [Modelo::getTable() . '.id', Modelo::getTable() . '.name'],
            'FROM'       => self::getTable(),
            'INNER JOIN' => [Modelo::getTable() => ['ON' => [self::getTable() => self::$items_id, Modelo::getTable() => 'id']]],
            'WHERE'      => [self::getTable() . '.plugin_propostas_blocos_id' => $blocos_id],
            'ORDER'      => [Modelo::getTable() . '.name ASC'],
        ]), false);
    }

    public function prepareInputForAdd($input)
    {
        if ((int) ($input['plugin_propostas_blocos_id'] ?? 0) <= 0) {
            Session::addMessageAfterRedirect(htmlescape(__('Escolha um bloco de texto.', 'propostas')), false, ERROR);
            return false;
        }
        $bloco  = new Bloco();
        $modelo = new Modelo();
        if (
            !$bloco->getFromDB((int) $input['plugin_propostas_blocos_id'])
            || !$modelo->getFromDB((int) ($input[self::$items_id] ?? 0))
            || !Proposta::visivelNaEntidade($bloco, (int) $modelo->fields['entities_id'])
        ) {
            Session::addMessageAfterRedirect(htmlescape(__('Bloco de texto inválido para a entidade do modelo.', 'propostas')), false, ERROR);
            return false;
        }
        if (countElementsInTable(self::getTable(), [
            self::$items_id              => (int) ($input[self::$items_id] ?? 0),
            'plugin_propostas_blocos_id' => (int) $input['plugin_propostas_blocos_id'],
        ]) > 0) {
            Session::addMessageAfterRedirect(htmlescape(__('Este bloco já está no modelo.', 'propostas')), false, ERROR);
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Modelo && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::getTable(), [self::$items_id => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-text-caption');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Modelo) {
            return false;
        }

        TemplateRenderer::getInstance()->display('@propostas/modelobloco.list.html.twig', [
            'modelo'   => $item,
            'linhas'   => self::getBlocosDoModelo($item->getID()),
            'canedit'  => $item->can($item->getID(), UPDATE),
            'form_url' => self::getFormURL(),
            'items_id' => self::$items_id,
        ]);

        return true;
    }
}
