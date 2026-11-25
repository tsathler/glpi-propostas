<?php

/**
 * Propostas - permissões das linhas de uma proposta (itens e blocos de texto): só mudam enquanto a
 * proposta é rascunho, e quem pode editar a proposta pode tirar uma linha dela.
 *
 * Usado por PropostaItem e PropostaBloco (CommonDBChild de Proposta).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

trait LinhaDoRascunho
{
    /** Qualquer nível de leitura de propostas (as próprias, da equipe ou todas). */
    public static function canView(): bool
    {
        return Proposta::canView();
    }

    private function isPropostaEditavel(int $propostas_id): bool
    {
        $proposta = new Proposta();

        return $proposta->getFromDB($propostas_id) && $proposta->isEditavel();
    }

    public function canCreateItem(): bool
    {
        return $this->isPropostaEditavel((int) ($this->fields[self::$items_id] ?? $this->input[self::$items_id] ?? 0))
            && parent::canCreateItem();
    }

    public function canUpdateItem(): bool
    {
        return $this->isPropostaEditavel((int) $this->fields[self::$items_id]) && parent::canUpdateItem();
    }

    /**
     * Tirar uma linha de um rascunho é editar a proposta, não excluí-la: basta poder editar a
     * proposta (o GLPI pediria o direito de excluir propostas, que o consultor não tem).
     */
    public static function canPurge(): bool
    {
        return static::canChild('canUpdate');
    }

    public function canPurgeItem(): bool
    {
        return $this->isPropostaEditavel((int) $this->fields[self::$items_id]) && parent::canPurgeItem();
    }
}
