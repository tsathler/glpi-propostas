<?php

/**
 * Propostas - itens de um modelo de proposta (valores de exemplo, copiados para a proposta).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBChild;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

class ModeloItem extends CommonDBChild
{
    use ValidaItem;

    public static $itemtype = Modelo::class;
    public static $items_id = 'plugin_propostas_modelos_id';

    public static $rightname = 'plugin_propostas_modelo';

    public $dohistory = true;

    public static function getTypeName($nb = 0): string
    {
        return _n('Item', 'Itens', $nb, 'propostas');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getItensDoModelo(int $modelos_id): array
    {
        global $DB;

        return iterator_to_array($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [self::$items_id => $modelos_id],
            'ORDER' => ['tipo DESC', 'ordem ASC', 'id ASC'],
        ]), false);
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->validarItem($input, true);

        return $input === false ? false : parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->validarItem($input, false);

        return $input === false ? false : parent::prepareInputForUpdate($input);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Modelo && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::getTable(), [self::$items_id => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-list-details');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Modelo) {
            return false;
        }

        $linhas = [];
        $totais = Calculo::totais(self::getItensDoModelo($item->getID()));
        foreach (self::getItensDoModelo($item->getID()) as $row) {
            $linhas[] = [
                'id'             => $row['id'],
                'tipo'           => self::getTipos()[$row['tipo']] ?? $row['tipo'],
                'name'           => $row['name'],
                'quantidade'     => Html::formatNumber($row['quantidade']),
                'valor_unitario' => Html::formatNumber($row['valor_unitario']),
                'desconto_pct'   => Html::formatNumber($row['desconto_pct']),
                'subtotal'       => Html::formatNumber(Calculo::subtotal($row['quantidade'], $row['valor_unitario'], $row['desconto_pct'])),
            ];
        }

        TemplateRenderer::getInstance()->display('@propostas/propostaitem.list.html.twig', [
            'pai'      => $item,
            'linhas'   => $linhas,
            'totais'   => array_map(static fn (float $v): string => Html::formatNumber($v), $totais),
            'tipos'    => self::getTipos(),
            'canedit'  => $item->can($item->getID(), UPDATE),
            'travada'  => false,
            'aviso'    => __('Valores de exemplo: na proposta, o consultor ajusta quantidade e preço.', 'propostas'),
            'form_url' => self::getFormURL(),
            'items_id' => self::$items_id,
        ]);

        return true;
    }
}
