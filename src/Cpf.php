<?php

/**
 * Propostas - CPF: conferência dos dígitos, formatação e máscara.
 *
 * Sem dependência do GLPI, para poder ser testado sozinho (tests/CpfTest.php).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Cpf
{
    public static function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public static function validar(string $cpf): bool
    {
        $cpf = self::somenteDigitos($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ex.: 529.982.247-25. Devolve '' se não tiver 11 dígitos.
     */
    public static function formatar(string $cpf): string
    {
        $cpf = self::somenteDigitos($cpf);

        return strlen($cpf) === 11
            ? sprintf('%s.%s.%s-%s', substr($cpf, 0, 3), substr($cpf, 3, 3), substr($cpf, 6, 3), substr($cpf, 9, 2))
            : '';
    }

    /**
     * Ex.: ***.982.247-**. Devolve '' se não tiver 11 dígitos.
     */
    public static function mascarar(string $cpf): string
    {
        $cpf = self::somenteDigitos($cpf);

        return strlen($cpf) === 11 ? sprintf('***.%s.%s-**', substr($cpf, 3, 3), substr($cpf, 6, 3)) : '';
    }
}
