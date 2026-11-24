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

    /**
     * Código de verificação exibido ao cliente: os 16 primeiros caracteres do hash, em grupos de 4.
     */
    public static function formatarCodigo(?string $hash): string
    {
        if ($hash === null || $hash === '') {
            return '';
        }

        return implode('-', str_split(strtoupper(substr($hash, 0, 16)), 4));
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
                $cpf = self::formatarCpf((new GLPIKey())->decrypt($row['cpf_cifrado']) ?? '') ?: $cpf;
            }
            $linhas[] = [
                'tipo'     => $row['tipo'] === self::TIPO_ACEITE ? __('Aceite', 'propostas') : __('Dúvida', 'propostas'),
                'aceite'   => $row['tipo'] === self::TIPO_ACEITE,
                'versao'   => $row['versao'],
                'data'     => $row['date_creation'],
                'nome'     => $row['nome'],
                'cpf'      => $cpf,
                'mensagem' => $row['mensagem'],
                'codigo'   => self::formatarCodigo($row['hash_valores']),
                'hash'     => $row['hash_valores'],
                'ip'       => $row['ip'],
                'agente'   => $row['user_agent'],
            ];
        }

        TemplateRenderer::getInstance()->display('@propostas/respostas.html.twig', [
            'linhas'      => $linhas,
            'hash_atual'  => $item->calcularHashValores(),
            'versao_atual'=> (int) $item->fields['versao'],
        ]);

        return true;
    }

    /* ------------------------------------------------------------------
     * CPF
     * ------------------------------------------------------------------ */

    public static function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public static function validarCpf(string $cpf): bool
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

    public static function formatarCpf(string $cpf): string
    {
        $cpf = self::somenteDigitos($cpf);

        return strlen($cpf) === 11
            ? sprintf('%s.%s.%s-%s', substr($cpf, 0, 3), substr($cpf, 3, 3), substr($cpf, 6, 3), substr($cpf, 9, 2))
            : '';
    }

    /**
     * Ex.: ***.456.789-**
     */
    public static function mascararCpf(string $cpf): string
    {
        $cpf = self::somenteDigitos($cpf);

        return strlen($cpf) === 11 ? sprintf('***.%s.%s-**', substr($cpf, 3, 3), substr($cpf, 6, 3)) : '';
    }
}
