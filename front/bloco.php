<?php

/**
 * Propostas - lista de blocos de texto (Ferramentas > Blocos de texto).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Bloco;

Session::checkRight(Bloco::$rightname, READ);

Html::header(Bloco::getTypeName(Session::getPluralNumber()), '', 'tools', Bloco::class);
Search::show(Bloco::class);
Html::footer();
