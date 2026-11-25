<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Origem;
use PHPUnit\Framework\TestCase;

final class OrigemTest extends TestCase
{
    public function testIpv4FicaComoEsta(): void
    {
        self::assertSame('203.0.113.5', Origem::deIp('203.0.113.5'));
    }

    public function testIpv4EscritoComoIpv6VoltaASerIpv4(): void
    {
        self::assertSame('203.0.113.5', Origem::deIp('::ffff:203.0.113.5'));
        self::assertNotSame(Origem::deIp('::ffff:203.0.113.5'), Origem::deIp('::ffff:198.51.100.7'), 'cada IPv4 tem o seu limite');
    }

    public function testIpv6AgrupaPelaRede64(): void
    {
        self::assertSame('2001:db8:1:2::/64', Origem::deIp('2001:db8:1:2::1'));
        self::assertSame(Origem::deIp('2001:db8:1:2::1'), Origem::deIp('2001:db8:1:2:aaaa:bbbb:cccc:dddd'));
        self::assertNotSame(Origem::deIp('2001:db8:1:2::1'), Origem::deIp('2001:db8:1:3::1'));
    }

    public function testTextoQueNaoEIpVoltaComoVeio(): void
    {
        self::assertSame('', Origem::deIp(''));
        self::assertSame('nao-e-ip', Origem::deIp('nao-e-ip'));
    }
}
