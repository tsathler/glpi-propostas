<?php

/**
 * Propostas - regras de visibilidade e de atribuição por nível de leitura e equipe.
 *
 * Sem dependência do GLPI: recebe os direitos e as equipes já consultados (testável sozinho).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Visibilidade
{
    /** Ler as propostas próprias (o mesmo bit do READ do GLPI). */
    public const MINHAS = 1;

    /** Ler e agir nas propostas dos consultores das equipes que o usuário gere. */
    public const EQUIPE = 2048;

    /** Ler e agir em todas as propostas das entidades do perfil. */
    public const TODAS = 1024;

    /** Qualquer um dos níveis de leitura. */
    public const LEITURA = [self::MINHAS, self::EQUIPE, self::TODAS];

    public static function temLeitura(int $direitos): bool
    {
        return ($direitos & (self::MINHAS | self::EQUIPE | self::TODAS)) !== 0;
    }

    /**
     * O usuário vê a proposta deste consultor?
     *
     * @param list<int> $membros_geridos consultores das equipes em que o usuário é Responsável
     */
    public static function podeVer(int $direitos, int $usuario, int $consultor, array $membros_geridos): bool
    {
        if (!self::temLeitura($direitos)) {
            return false;
        }
        if (($direitos & self::TODAS) !== 0) {
            return true;
        }
        if ($usuario > 0 && $consultor === $usuario) {
            return true;
        }

        return ($direitos & self::EQUIPE) !== 0 && in_array($consultor, $membros_geridos, true);
    }

    /**
     * Consultores cujas propostas o usuário vê, para o filtro da busca.
     * null = sem filtro (nível Todas).
     *
     * @param list<int> $membros_geridos
     * @return list<int>|null
     */
    public static function consultoresVisiveis(int $direitos, int $usuario, array $membros_geridos): ?array
    {
        if (($direitos & self::TODAS) !== 0) {
            return null;
        }
        $ids = [$usuario];
        if (($direitos & self::EQUIPE) !== 0) {
            $ids = array_merge($ids, $membros_geridos);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Pode criar ou duplicar propostas? Com as equipes ligadas, só quem está numa equipe
     * (como consultor ou Responsável) ou tem o nível Todas.
     */
    public static function podeCriar(int $direitos, bool $equipes_ativas, bool $usuario_em_equipe): bool
    {
        return !$equipes_ativas || $usuario_em_equipe || ($direitos & self::TODAS) !== 0;
    }

    /**
     * Pode pôr $alvo como consultor de uma proposta?
     *
     * - Minhas: só a si mesmo.
     * - Da equipe: a si mesmo ou a um membro das equipes que gere.
     * - Todas: a qualquer um que esteja numa equipe (ou a si mesmo).
     * Com as equipes ligadas, o consultor precisa estar numa equipe; a exceção é o próprio
     * usuário com o nível Todas (quem vê todas fazendo uma proposta sua).
     *
     * @param list<int> $membros_geridos
     */
    public static function podeAtribuir(
        int $direitos,
        int $usuario,
        int $alvo,
        bool $equipes_ativas,
        bool $alvo_em_equipe,
        array $membros_geridos
    ): bool {
        if ($alvo <= 0 || !self::temLeitura($direitos)) {
            return false;
        }
        $todas = ($direitos & self::TODAS) !== 0;

        if ($alvo === $usuario) {
            return !$equipes_ativas || $alvo_em_equipe || $todas;
        }
        if ($equipes_ativas && !$alvo_em_equipe) {
            return false;
        }
        if ($todas) {
            return true;
        }

        return ($direitos & self::EQUIPE) !== 0 && in_array($alvo, $membros_geridos, true);
    }
}
