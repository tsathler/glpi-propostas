<?php

/**
 * Propostas - formulário: modelos de proposta.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Modelo;

Session::checkRight(Modelo::$rightname, READ);

$obj = new Modelo();

if (isset($_POST['add'])) {
    $obj->check(-1, CREATE, $_POST);
    if ($obj->add($_POST) !== false) {
        Html::redirect($obj->getLinkURL());
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $obj->check($_POST['id'], UPDATE);
    $obj->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $obj->check($_POST['id'], PURGE);
    $obj->delete($_POST, true);
    $obj->redirectToList();
} else {
    Html::header(Modelo::getTypeName(Session::getPluralNumber()), '', 'tools', Modelo::class);
    $obj->display(['id' => (int) ($_GET['id'] ?? 0)]);
    Html::footer();
}
