<?php

/**
 * Propostas - formulário: blocos de texto.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Bloco;

Session::checkRight(Bloco::$rightname, READ);

$obj = new Bloco();

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
    Html::header(Bloco::getTypeName(Session::getPluralNumber()), '', 'tools', Bloco::class);
    $obj->display(['id' => (int) ($_GET['id'] ?? 0)]);
    Html::footer();
}
