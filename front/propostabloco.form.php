<?php

/**
 * Propostas - blocos de texto da proposta: incluir, editar e remover (só em rascunho).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Proposta;
use GlpiPlugin\Propostas\PropostaBloco;

Session::checkRightsOr(PropostaBloco::$rightname, \GlpiPlugin\Propostas\Visibilidade::LEITURA);

$bloco = new PropostaBloco();

if (isset($_POST['add'])) {
    $bloco->check(-1, CREATE, $_POST);
    $bloco->add($_POST);
    Html::back();
} elseif (isset($_POST['update'])) {
    $bloco->check($_POST['id'], UPDATE);
    $bloco->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $bloco->check($_POST['id'], PURGE);
    $propostas_id = (int) $bloco->fields[PropostaBloco::$items_id];
    $bloco->delete($_POST, true);
    Html::redirect(Proposta::getFormURLWithID($propostas_id));
} else {
    $id = (int) ($_GET['id'] ?? 0);
    $bloco->check($id, READ);
    Html::header(Proposta::getTypeName(Session::getPluralNumber()), '', 'tools', Proposta::class);
    $bloco->display(['id' => $id]);
    Html::footer();
}
