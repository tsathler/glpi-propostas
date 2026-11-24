<?php

/**
 * Propostas - biblioteca de blocos de texto (Ferramentas > Blocos de texto).
 *
 * Cada bloco tem título e texto formatado. Os modelos escolhem quais blocos usam e em que ordem;
 * na criação da proposta os blocos são copiados para ela.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use Log;

class Bloco extends CommonDBTM
{
    public static $rightname = 'plugin_propostas_modelo';

    public $dohistory = true;

    public static function getTypeName($nb = 0): string
    {
        return _n('Bloco de texto', 'Blocos de texto', $nb, 'propostas');
    }

    public static function getIcon(): string
    {
        return 'ti ti-text-caption';
    }

    public function defineTabs($options = []): array
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(Log::class, $tabs, $options);

        return $tabs;
    }

    public function post_getEmpty(): void
    {
        $this->fields['is_active']    = 1;
        $this->fields['is_recursive'] = 1;
    }

    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('@propostas/bloco.form.html.twig', [
            'item'    => $this,
            'params'  => $options,
            'modelos' => $this->isNewItem() ? [] : ModeloBloco::getModelosDoBloco($this->getID()),
        ]);

        return true;
    }

    public function cleanDBonPurge(): void
    {
        global $DB;

        // Some do modelo; as propostas já criadas guardam a própria cópia do texto.
        $DB->delete(ModeloBloco::getTable(), ['plugin_propostas_blocos_id' => $this->getID()]);
    }

    public function rawSearchOptions(): array
    {
        return [
            ['id' => 'common', 'name' => __('Characteristics')],
            ['id' => 1, 'table' => self::getTable(), 'field' => 'name', 'name' => __('Título', 'propostas'), 'datatype' => 'itemlink', 'massiveaction' => false],
            ['id' => 2, 'table' => self::getTable(), 'field' => 'id', 'name' => __('ID'), 'datatype' => 'number', 'massiveaction' => false],
            ['id' => 3, 'table' => self::getTable(), 'field' => 'is_active', 'name' => __('Active'), 'datatype' => 'bool'],
            ['id' => 16, 'table' => self::getTable(), 'field' => 'comment', 'name' => __('Comments'), 'datatype' => 'text'],
            ['id' => 19, 'table' => self::getTable(), 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false],
            ['id' => 121, 'table' => self::getTable(), 'field' => 'date_creation', 'name' => __('Creation date'), 'datatype' => 'datetime', 'massiveaction' => false],
            ['id' => 80, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => \Entity::getTypeName(1), 'datatype' => 'dropdown'],
            ['id' => 86, 'table' => self::getTable(), 'field' => 'is_recursive', 'name' => __('Child entities'), 'datatype' => 'bool'],
        ];
    }
}
