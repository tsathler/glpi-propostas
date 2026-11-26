<?php

/**
 * Propostas - itens (linhas) de uma proposta.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBChild;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

class PropostaItem extends CommonDBChild
{
    use ValidaItem;

    public static $itemtype = Proposta::class;
    public static $items_id = 'plugin_propostas_propostas_id';

    public static $rightname = 'plugin_propostas_proposta';

    // Só mudam enquanto a proposta é rascunho (permissões compartilhadas com a outra linha da proposta).
    use LinhaDoRascunho;

    public $dohistory = true;

    public const TIPO_RECORRENTE = Calculo::TIPO_RECORRENTE;
    public const TIPO_PONTUAL    = Calculo::TIPO_PONTUAL;

    public static function getTypeName($nb = 0): string
    {
        return _n('Item', 'Itens', $nb, 'propostas');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getItensDaProposta(int $propostas_id): array
    {
        global $DB;

        $itens = [];
        foreach (
            $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [self::$items_id => $propostas_id],
                'ORDER' => ['tipo DESC', 'ordem ASC', 'id ASC'],
            ]) as $row
        ) {
            $itens[] = $row;
        }

        return $itens;
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
        if ($item instanceof Proposta && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::getTable(), [self::$items_id => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-list-details');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Proposta) {
            self::showForProposta($item);
        }

        return true;
    }

    public static function showForProposta(Proposta $proposta): void
    {
        $linhas = [];
        foreach (self::getItensDaProposta($proposta->getID()) as $row) {
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
            'pai'       => $proposta,
            'linhas'    => $linhas,
            'totais'    => $proposta->getTotaisFormatados(),
            'tipos'     => self::getTipos(),
            'canedit'   => $proposta->isEditavel() && $proposta->can($proposta->getID(), UPDATE),
            'travada'   => !$proposta->isEditavel(),
            'form_url'  => self::getFormURL(),
            'items_id'  => self::$items_id,
        ]);
    }
}
