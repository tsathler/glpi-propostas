<?php

/**
 * Propostas - de onde vem um envio da página pública, para o limite de tentativas por hora.
 *
 * Sem dependência do GLPI (testável sozinho): tests/OrigemTest.php.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Origem
{
    /**
     * O IPv4 inteiro ou a rede /64 do IPv6 (cada cliente IPv6 costuma ter milhões de endereços).
     * IPv4 escrito como IPv6 ("::ffff:1.2.3.4", em servidor que escuta os dois num socket só) volta a
     * ser o IPv4: senão todos os clientes IPv4 cairiam na mesma rede "::/64".
     */
    public static function deIp(string $ip): string
    {
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return $ip;
        }
        if (str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($bin, 12));
        }

        return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
}
