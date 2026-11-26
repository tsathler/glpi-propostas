<?php

/**
 * Propostas - validação comum aos itens de proposta e aos itens de modelo.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use Session;

trait ValidaItem
{
    /**
     * @return array<string, string>
     */
    public static function getTipos(): array
    {
        return [
            Calculo::TIPO_RECORRENTE => __('Recorrente (mensal)', 'propostas'),
            Calculo::TIPO_PONTUAL    => __('Pontual (pagamento único)', 'propostas'),
        ];
    }

    /**
     * Normaliza números ("1.234,56" ou "1234.56") e confere descrição, tipo, quantidade, preço e desconto.
     * Erros vão para a mensagem da tela.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    private function validarItem(array $input, bool $is_add): array|false
    {
        foreach (['quantidade', 'valor_unitario', 'desconto_pct'] as $campo) {
            if (isset($input[$campo]) && is_string($input[$campo])) {
                $input[$campo] = Calculo::normalizarNumero($input[$campo]);
            }
        }

        $erros = [];
        if ($is_add && trim((string) ($input['name'] ?? '')) === '') {
            $erros[] = __('Informe a descrição do item.', 'propostas');
        }
        if (isset($input['tipo']) && !array_key_exists($input['tipo'], self::getTipos())) {
            $erros[] = __('Tipo de item inválido.', 'propostas');
        }
        if (isset($input['quantidade']) && (!is_numeric($input['quantidade']) || round((float) $input['quantidade'], 2) <= 0)) {
            $erros[] = __('A quantidade deve ser maior que zero.', 'propostas');
        }
        if (isset($input['valor_unitario']) && (!is_numeric($input['valor_unitario']) || $input['valor_unitario'] < 0)) {
            $erros[] = __('O valor unitário não pode ser negativo.', 'propostas');
        }
        if (
            isset($input['desconto_pct'])
            && (!is_numeric($input['desconto_pct']) || $input['desconto_pct'] < 0 || $input['desconto_pct'] > 100)
        ) {
            $erros[] = __('O desconto deve ficar entre 0 e 100%.', 'propostas');
        }

        foreach ($erros as $erro) {
            Session::addMessageAfterRedirect(htmlescape($erro), false, ERROR);
        }

        return $erros === [] ? $input : false;
    }
}
