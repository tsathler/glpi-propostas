<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Equipe;
use PHPUnit\Framework\TestCase;

final class EquipeTest extends TestCase
{
    private const EQUIPES = [7, 8];

    public function testSemFiltroDeGrupoUsaTodasAsEquipes(): void
    {
        $this->assertSame(
            ['users_id' => 10, 'groups_id' => [7, 8]],
            Equipe::restringirAsEquipes(self::EQUIPES, ['users_id' => 10])
        );
    }

    public function testFiltroDeGrupoNaoESubstituidoPelasEquipes(): void
    {
        // Regressão: ['groups_id' => equipes] + $where descartava o groups_id pedido,
        // e o gestor da equipe 7 via os membros da equipe 8.
        $this->assertSame(
            ['groups_id' => [7], 'is_manager' => 1],
            Equipe::restringirAsEquipes(self::EQUIPES, ['groups_id' => [7], 'is_manager' => 1])
        );
    }

    public function testGrupoQueNaoEEquipeFicaDeFora(): void
    {
        $this->assertSame(['groups_id' => [8]], Equipe::restringirAsEquipes(self::EQUIPES, ['groups_id' => [8, 99]]));
        $this->assertNull(Equipe::restringirAsEquipes(self::EQUIPES, ['groups_id' => [99]]));
    }

    public function testSemEquipesNaoHaConsulta(): void
    {
        $this->assertNull(Equipe::restringirAsEquipes([], ['users_id' => 10]));
        $this->assertNull(Equipe::restringirAsEquipes(self::EQUIPES, ['groups_id' => []]));
    }
}
