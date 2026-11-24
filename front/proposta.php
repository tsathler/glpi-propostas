<?php

/**
 * Propostas - lista (Ferramentas > Propostas).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Proposta;

Session::checkRightsOr(Proposta::$rightname, \GlpiPlugin\Propostas\Visibilidade::LEITURA);

Html::header(Proposta::getTypeName(Session::getPluralNumber()), '', 'tools', Proposta::class);
Search::show(Proposta::class);
Html::footer();
