<?php

/**
 * Propostas - aba "Propostas" em Administração > Perfis, com os direitos do plugin.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonGLPI;
use Html;
use Session;

class Profile extends \Profile
{
    public static $rightname = 'profile';

    /**
     * @return list<array{itemtype: class-string, label: string, field: string}>
     */
    public static function getDireitos(): array
    {
        return [
            ['itemtype' => Proposta::class, 'label' => __('Propostas', 'propostas'), 'field' => PLUGIN_PROPOSTAS_RIGHT],
            ['itemtype' => Modelo::class, 'label' => __('Gerenciar modelos e blocos de texto', 'propostas'), 'field' => PLUGIN_PROPOSTAS_RIGHT_MODELO],
        ];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Profile && $item->fields['interface'] === 'central') {
            return self::createTabEntry(__('Propostas', 'propostas'), 0, $item::class, Proposta::getIcon());
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof \Profile) {
            return false;
        }

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        echo '<div class="firstbloc">';
        if ($canedit) {
            echo '<form method="post" action="' . htmlescape(\Profile::getFormURL()) . '">';
        }
        $item->displayRightsChoiceMatrix(self::getDireitos(), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Propostas', 'propostas'),
        ]);
        if ($canedit) {
            echo '<div class="text-center mt-2">';
            echo Html::hidden('id', ['value' => $item->getID()]);
            echo Html::submit(_x('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo '</div>';
            Html::closeForm(); // inclui o token CSRF
        }
        echo '</div>';

        return true;
    }
}
