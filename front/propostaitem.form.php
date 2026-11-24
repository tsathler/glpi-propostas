<?php

/**
 * Propostas - incluir, alterar e remover itens de uma proposta.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Propostas\PropostaItem;

Session::checkRightsOr(PropostaItem::$rightname, \GlpiPlugin\Propostas\Visibilidade::LEITURA);

$item = new PropostaItem();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $item->add($_POST);
    Html::back();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, true);
    Html::back();
}

throw new BadRequestHttpException();
