<?php

/**
 * Propostas - formulário da proposta.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Proposta;

Session::checkRight(Proposta::$rightname, READ);

$proposta = new Proposta();

if (isset($_POST['add'])) {
    $proposta->check(-1, CREATE, $_POST);
    // Sempre abre a proposta criada: o próximo passo é incluir os itens.
    if ($proposta->add($_POST) !== false) {
        Html::redirect($proposta->getLinkURL());
    }
    Html::back();
} elseif (isset($_POST['duplicar'])) {
    $proposta->check($_POST['id'], READ);
    Session::checkRight(Proposta::$rightname, CREATE);
    $id = $proposta->duplicar();
    if ($id !== false) {
        Html::redirect(Proposta::getFormURLWithID($id));
    }
    Html::back();
} elseif (isset($_POST['gerar_link']) || isset($_POST['reabrir']) || isset($_POST['cancelar'])) {
    $proposta->check($_POST['id'], UPDATE);
    match (true) {
        isset($_POST['gerar_link']) => $proposta->gerarLink(),
        isset($_POST['reabrir'])    => $proposta->reabrir(),
        isset($_POST['cancelar'])   => $proposta->cancelar(),
    };
    Html::back();
} elseif (isset($_POST['update'])) {
    $proposta->check($_POST['id'], UPDATE);
    $proposta->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    if ($proposta->getFromDB((int) $_POST['id']) && $proposta->temAceite()) {
        Session::addMessageAfterRedirect(htmlescape(__('Proposta com aceite registrado não pode ser excluída. Use "Cancelar" se ela não vale mais.', 'propostas')), false, ERROR);
        Html::back();
    }
    $proposta->check($_POST['id'], PURGE);
    $proposta->delete($_POST, true);
    $proposta->redirectToList();
} else {
    Html::header(Proposta::getTypeName(Session::getPluralNumber()), '', 'tools', Proposta::class);
    $proposta->display(['id' => (int) ($_GET['id'] ?? 0)]);
    Html::footer();
}
