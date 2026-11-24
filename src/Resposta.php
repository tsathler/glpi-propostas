<?php

/**
 * Propostas - respostas do cliente pelo link público (aceite e dúvida).
 *
 * Gravadas direto no banco: o cliente não tem sessão no GLPI, e o registro
 * não deve depender de direitos de usuário.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use GLPIKey;
use Session;

class Resposta extends CommonGLPI
{
    public static $rightname = 'plugin_propostas_proposta';

    /** Qualquer nível de leitura de propostas (as próprias, da equipe ou todas). */
    public static function canView(): bool
    {
        return Proposta::canView();
    }

    public const TABELA = 'glpi_plugin_propostas_respostas';

    public const TIPO_ACEITE = 'aceite';
    public const TIPO_DUVIDA = 'duvida';

    public static function getTypeName($nb = 0): string
    {
        return _n('Resposta do cliente', 'Respostas do cliente', $nb, 'propostas');
    }

    /**
     * @param array<string, mixed> $dados
     */
    public static function registrar(array $dados): int
    {
        global $DB;

        $DB->insert(self::TABELA, $dados + [
            'date_creation' => Session::getCurrentTime() ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $DB->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getDaProposta(int $propostas_id): array
    {
        global $DB;

        return iterator_to_array($DB->request([
            'FROM'  => self::TABELA,
            'WHERE' => ['plugin_propostas_propostas_id' => $propostas_id],
            'ORDER' => ['date_creation DESC', 'id DESC'],
        ]), false);
    }

    /**
     * Aceite da versão informada, se houver.
     *
     * @return array<string, mixed>|null
     */
    public static function getAceite(int $propostas_id, int $versao): ?array
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => self::TABELA,
            'WHERE' => [
                'plugin_propostas_propostas_id' => $propostas_id,
                'tipo'                          => self::TIPO_ACEITE,
                'versao'                        => $versao,
            ],
            'ORDER' => ['id DESC'],
            'LIMIT' => 1,
        ])->current();

        return $row ?: null;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Proposta && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::TABELA, ['plugin_propostas_propostas_id' => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-message-check');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Proposta) {
            return false;
        }

        // CPF completo só para quem pode alterar a proposta; os outros veem a máscara.
        $ver_cpf = $item->can($item->getID(), UPDATE);
        $linhas = [];
        foreach (self::getDaProposta($item->getID()) as $row) {
            $cpf = $row['cpf_mascarado'];
            if ($ver_cpf && !empty($row['cpf_cifrado'])) {
                $cpf = Cpf::formatar((new GLPIKey())->decrypt($row['cpf_cifrado']) ?? '') ?: $cpf;
            }
            $aceite = $row['tipo'] === self::TIPO_ACEITE;
            // Confere no formato em que o aceite foi gravado (1 = sem empresa/blocos/cargo).
            $confere = null;
            if ($aceite && (int) $row['versao'] === (int) $item->fields['versao']) {
                $formato = (int) ($row['hash_formato'] ?? 1);
                $confere = hash_equals(
                    (string) $row['hash_valores'],
                    $item->calcularHashValores($formato, (string) ($row['cargo'] ?? ''))
                );
            }
            $linhas[] = [
                'tipo'     => $aceite ? __('Aceite', 'propostas') : __('Dúvida', 'propostas'),
                'aceite'   => $aceite,
                'confere'  => $confere,
                'versao'   => $row['versao'],
                'data'     => $row['date_creation'],
                'nome'     => $row['nome'],
                'cargo'    => $row['cargo'] ?? '',
                'cpf'      => $cpf,
                'mensagem' => $row['mensagem'],
                'codigo'   => Calculo::codigo($row['hash_valores']),
                'hash'     => $row['hash_valores'],
                'ip'       => $row['ip'],
                'agente'   => $row['user_agent'],
            ];
        }

        TemplateRenderer::getInstance()->display('@propostas/respostas.html.twig', [
            'linhas' => $linhas,
        ]);

        return true;
    }
}
