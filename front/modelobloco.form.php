<?php

/**
 * Propostas - incluir e remover blocos de texto de um modelo.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Propostas\ModeloBloco;

Session::checkRight(ModeloBloco::$rightname, READ);

$obj = new ModeloBloco();

if (isset($_POST['add'])) {
    $obj->check(-1, CREATE, $_POST);
    $obj->add($_POST);
    Html::back();
} elseif (isset($_POST['update'])) {
    $obj->check($_POST['id'], UPDATE);
    $obj->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $obj->check($_POST['id'], PURGE);
    $obj->delete($_POST, true);
    Html::back();
}

throw new BadRequestHttpException();
