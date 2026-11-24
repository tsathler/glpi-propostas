<?php

/**
 * Propostas - blocos de texto da proposta (cópia feita na criação; editável enquanto rascunho).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBChild;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

class PropostaBloco extends CommonDBChild
{
    public static $itemtype = Proposta::class;
    public static $items_id = 'plugin_propostas_propostas_id';

    public static $rightname = 'plugin_propostas_proposta';

    public $dohistory = true;

    public static function getTypeName($nb = 0): string
    {
        return _n('Bloco de texto', 'Blocos de texto', $nb, 'propostas');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getBlocosDaProposta(int $propostas_id): array
    {
        global $DB;

        return iterator_to_array($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [self::$items_id => $propostas_id],
            'ORDER' => ['ordem ASC', 'id ASC'],
        ]), false);
    }

    /**
     * Blocos só mudam enquanto a proposta é rascunho.
     */
    private function isPropostaEditavel(int $propostas_id): bool
    {
        $proposta = new Proposta();

        return $proposta->getFromDB($propostas_id) && $proposta->isEditavel();
    }

    public function canCreateItem(): bool
    {
        return $this->isPropostaEditavel((int) ($this->fields[self::$items_id] ?? $this->input[self::$items_id] ?? 0))
            && parent::canCreateItem();
    }

    public function canUpdateItem(): bool
    {
        return $this->isPropostaEditavel((int) $this->fields[self::$items_id]) && parent::canUpdateItem();
    }

    public function canPurgeItem(): bool
    {
        return $this->isPropostaEditavel((int) $this->fields[self::$items_id]) && parent::canPurgeItem();
    }

    public function prepareInputForAdd($input)
    {
        // "Da biblioteca": copia título e texto do bloco escolhido.
        $blocos_id = (int) ($input['_plugin_propostas_blocos_id'] ?? 0);
        if ($blocos_id > 0) {
            $bloco = new Bloco();
            if (!$bloco->getFromDB($blocos_id)) {
                return false;
            }
            $input['name']     = $bloco->fields['name'];
            $input['conteudo'] = $bloco->fields['conteudo'];
        }
        if (trim((string) ($input['name'] ?? '')) === '') {
            Session::addMessageAfterRedirect(htmlescape(__('Informe o título do bloco.', 'propostas')), false, ERROR);
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        $proposta = new Proposta();
        $proposta->getFromDB((int) $this->fields[self::$items_id]);
        TemplateRenderer::getInstance()->display('@propostas/propostabloco.form.html.twig', [
            'item'      => $this,
            'params'    => $options + ['canedit' => $this->can($ID, UPDATE)],
            'proposta'  => $proposta,
        ]);

        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Proposta && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::getTable(), [self::$items_id => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-text-caption');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Proposta) {
            return false;
        }

        TemplateRenderer::getInstance()->display('@propostas/propostabloco.list.html.twig', [
            'proposta' => $item,
            'linhas'   => self::getBlocosDaProposta($item->getID()),
            'canedit'  => $item->isEditavel() && $item->can($item->getID(), UPDATE),
            'travada'  => !$item->isEditavel(),
            'form_url' => self::getFormURL(),
            'items_id' => self::$items_id,
        ]);

        return true;
    }
}
