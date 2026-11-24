<?php

/**
 * Propostas - lista de modelos de proposta (Ferramentas > Modelos de proposta).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Modelo;

Session::checkRight(Modelo::$rightname, READ);

Html::header(Modelo::getTypeName(Session::getPluralNumber()), '', 'tools', Modelo::class);
Search::show(Modelo::class);
Html::footer();
