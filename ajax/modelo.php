<?php

/**
 * Propostas - itens e desconto especial de um modelo, para os campos mostrados ao escolher o
 * modelo na criação da proposta.
 *
 * Resposta JSON: {ok: true, desconto_especial, itens: [{id, tipo, nome, valor}]} ou {ok: false, erro}.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Calculo;
use GlpiPlugin\Propostas\Modelo;
use GlpiPlugin\Propostas\ModeloItem;
use GlpiPlugin\Propostas\Proposta;

Session::checkLoginUser();
Session::checkRight(Proposta::$rightname, CREATE);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$responder = static function (array $dados): void {
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

// Mesmas condições da criação da proposta: modelo ativo e visível na entidade em que ela nasce.
$modelo = new Modelo();
if (
    !$modelo->getFromDB((int) ($_GET['id'] ?? 0))
    || !$modelo->fields['is_active']
    || !Proposta::visivelNaEntidade($modelo, Proposta::entidadeDeCriacao())
) {
    $responder(['ok' => false, 'erro' => __('Modelo de proposta inválido ou inativo.', 'propostas')]);
    return;
}

$itens = array_map(
    static fn (array $item): array => [
        'id'    => (int) $item['id'],
        'tipo'  => $item['tipo'] === Calculo::TIPO_PONTUAL ? Calculo::TIPO_PONTUAL : Calculo::TIPO_RECORRENTE,
        'nome'  => (string) $item['name'],
        'valor' => round((float) $item['valor_unitario'], 2),
        'desconto' => round((float) $item['desconto_pct'], 2),
    ],
    ModeloItem::getItensDoModelo($modelo->getID())
);

$responder([
    'ok'                => true,
    'desconto_especial' => $modelo->getDescontoEspecial(),
    'itens'             => $itens,
]);
