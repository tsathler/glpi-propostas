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
    public static $itemtype = Proposta::class;
    public static $items_id = 'plugin_propostas_propostas_id';

    public static $rightname = 'plugin_propostas_proposta';

    public $dohistory = true;

    public const TIPO_RECORRENTE = 'recorrente';
    public const TIPO_PONTUAL    = 'pontual';

    public static function getTypeName($nb = 0): string
    {
        return _n('Item', 'Itens', $nb, 'propostas');
    }

    /**
     * @return array<string, string>
     */
    public static function getTipos(): array
    {
        return [
            self::TIPO_RECORRENTE => __('Recorrente (mensal)', 'propostas'),
            self::TIPO_PONTUAL    => __('Pontual (pagamento único)', 'propostas'),
        ];
    }

    /**
     * Quantidade x valor unitário, menos o desconto.
     *
     * @param array<string, mixed> $item
     */
    public static function calcularSubtotal(array $item): float
    {
        $bruto = (float) $item['quantidade'] * (float) $item['valor_unitario'];

        return round($bruto * (1 - ((float) $item['desconto_pct'] / 100)), 2);
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
        $input = $this->validar($input, true);

        return $input === false ? false : parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->validar($input, false);

        return $input === false ? false : parent::prepareInputForUpdate($input);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function validar(array $input, bool $is_add): array|false
    {
        // Aceita "1.234,56" ou "1234.56".
        foreach (['quantidade', 'valor_unitario', 'desconto_pct'] as $campo) {
            if (isset($input[$campo]) && is_string($input[$campo])) {
                $valor = trim($input[$campo]);
                if (str_contains($valor, ',')) {
                    $valor = str_replace(['.', ','], ['', '.'], $valor);
                }
                $input[$campo] = $valor;
            }
        }

        $erros = [];
        if ($is_add && trim((string) ($input['name'] ?? '')) === '') {
            $erros[] = __('Informe a descrição do item.', 'propostas');
        }
        if (isset($input['tipo']) && !array_key_exists($input['tipo'], self::getTipos())) {
            $erros[] = __('Tipo de item inválido.', 'propostas');
        }
        if (isset($input['quantidade']) && (!is_numeric($input['quantidade']) || $input['quantidade'] <= 0)) {
            $erros[] = __('A quantidade deve ser maior que zero.', 'propostas');
        }
        if (isset($input['valor_unitario']) && (!is_numeric($input['valor_unitario']) || $input['valor_unitario'] < 0)) {
            $erros[] = __('O valor unitário não pode ser negativo.', 'propostas');
        }
        if (
            isset($input['desconto_pct'])
            && (!is_numeric($input['desconto_pct']) || $input['desconto_pct'] < 0 || $input['desconto_pct'] > 100)
        ) {
            $erros[] = __('O desconto deve ficar entre 0 e 100%.', 'propostas');
        }

        foreach ($erros as $erro) {
            Session::addMessageAfterRedirect(htmlescape($erro), false, ERROR);
        }

        return $erros === [] ? $input : false;
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
                'subtotal'       => Html::formatNumber(self::calcularSubtotal($row)),
            ];
        }

        TemplateRenderer::getInstance()->display('@propostas/propostaitem.list.html.twig', [
            'proposta'  => $proposta,
            'linhas'    => $linhas,
            'totais'    => $proposta->getTotaisFormatados(),
            'tipos'     => self::getTipos(),
            'canedit'   => $proposta->can($proposta->getID(), UPDATE),
            'form_url'  => self::getFormURL(),
            'items_id'  => self::$items_id,
        ]);
    }
}
