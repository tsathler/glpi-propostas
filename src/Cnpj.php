<?php

/**
 * Propostas - CNPJ: conferência dos dígitos verificadores e formatação.
 *
 * Aceita o formato numérico e o alfanumérico (12 posições com letras e números + 2 dígitos
 * verificadores). O cálculo é o mesmo para os dois: cada posição vale ord(caractere) - 48 e os
 * dígitos saem do módulo 11.
 *
 * Sem dependência do GLPI, para poder ser testado sozinho (tests/CnpjTest.php).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Cnpj
{
    private const PESOS_DV1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    private const PESOS_DV2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Só letras e números, em maiúsculas (ex.: "12.abc.345/01de-35" -> "12ABC34501DE35").
     */
    public static function normalizar(string $cnpj): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $cnpj) ?? '');
    }

    public static function validar(string $cnpj): bool
    {
        $cnpj = self::normalizar($cnpj);
        if (preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) !== 1 || preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        return substr($cnpj, 12, 2) === self::digitos(substr($cnpj, 0, 12));
    }

    /**
     * Ex.: 11.222.333/0001-81 ou 12.ABC.345/01DE-35. Devolve '' se não tiver 14 posições.
     */
    public static function formatar(string $cnpj): string
    {
        $cnpj = self::normalizar($cnpj);

        return strlen($cnpj) === 14
            ? sprintf('%s.%s.%s/%s-%s', substr($cnpj, 0, 2), substr($cnpj, 2, 3), substr($cnpj, 5, 3), substr($cnpj, 8, 4), substr($cnpj, 12, 2))
            : '';
    }

    /**
     * Os 2 dígitos verificadores das 12 primeiras posições.
     */
    public static function digitos(string $base): string
    {
        foreach ([self::PESOS_DV1, self::PESOS_DV2] as $pesos) {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (ord($base[$i]) - 48) * $peso;
            }
            $resto = $soma % 11;
            $base .= (string) ($resto < 2 ? 0 : 11 - $resto);
        }

        return substr($base, 12, 2);
    }
}
