<?php

/**
 * Propostas - registro de cada abertura do link público por quem não está logado no GLPI.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Session;

class Acesso extends CommonGLPI
{
    public static $rightname = 'plugin_propostas_proposta';

    /** Qualquer nível de leitura de propostas (as próprias, da equipe ou todas). */
    public static function canView(): bool
    {
        return Proposta::canView();
    }

    public const TABELA = 'glpi_plugin_propostas_acessos';

    /** Quantos acessos a aba mostra. */
    private const LIMITE_LISTA = 200;

    public static function getTypeName($nb = 0): string
    {
        return _n('Acesso ao link', 'Acessos ao link', $nb, 'propostas');
    }

    /** Reaberturas do mesmo IP dentro deste intervalo não geram outra linha. */
    private const INTERVALO_MINUTOS = 10;

    /**
     * Dias que o IP e o navegador de quem abre o link (e de quem manda dúvida) ficam guardados,
     * configurável. Depois disso a linha fica só com a data. O aceite guarda os dele: são a prova do contrato.
     */
    public const RETENCAO_PADRAO = 180;
    public const RETENCAO_MINIMA = 1;
    public const RETENCAO_MAXIMA = 3650;

    /** Prazo de guarda dentro dos limites? Aceita "45" ou 45; o resto é inválido. */
    public static function retencaoValida(mixed $dias): bool
    {
        $texto = trim((string) $dias);

        return preg_match('/^\d+$/', $texto) === 1
            && (int) $texto >= self::RETENCAO_MINIMA
            && (int) $texto <= self::RETENCAO_MAXIMA;
    }

    /** Prazo configurado; sem configuração (ou inválido), o padrão. */
    public static function retencaoDias(): int
    {
        $dias = \Config::getConfigurationValues('plugin:propostas', ['retencao_dias'])['retencao_dias'] ?? '';

        return self::retencaoValida($dias) ? (int) $dias : self::RETENCAO_PADRAO;
    }

    /**
     * Robô que abre o link sozinho: prévia de mensageiro (WhatsApp, Teams, Slack, Telegram...) ou buscador.
     * shortcut: lista de nomes conhecidos; o filtro de links do Outlook se passa por navegador e não é pego.
     */
    public static function ehRobo(string $user_agent): bool
    {
        return trim($user_agent) === ''
            || preg_match('/whatsapp|facebookexternalhit|facebot|skypeuripreview|microsoftpreview|slackbot|slack-imgproxy|telegrambot|twitterbot|linkedinbot|discordbot|googlebot|bingbot|applebot|duckduckbot|yandexbot|curl\/|wget\//i', $user_agent) === 1;
    }

    public static function cronInfo($name)
    {
        if ($name === 'limpar') {
            return ['description' => __('Apaga IP e navegador de acessos e dúvidas mais antigos que o prazo configurado (LGPD)', 'propostas')];
        }

        return [];
    }

    /**
     * Apaga IP e navegador dos acessos ao link e das dúvidas mais antigos que o prazo. Aceites ficam.
     *
     * @return int 1 se apagou algo, 0 se não havia nada
     */
    public static function cronLimpar(\CronTask $task): int
    {
        global $DB;

        $dias = self::retencaoDias();
        $limite = date('Y-m-d H:i:s', time() - $dias * DAY_TIMESTAMP);
        $total = 0;
        foreach ([
            self::TABELA     => [],
            Resposta::TABELA => ['tipo' => Resposta::TIPO_DUVIDA],
        ] as $tabela => $filtro) {
            $DB->update($tabela, ['ip' => null, 'user_agent' => null], $filtro + [
                'date_creation' => ['<', $limite],
                'OR'            => [['NOT' => ['ip' => null]], ['NOT' => ['user_agent' => null]]],
            ]);
            $total += $DB->affectedRows();
        }
        $task->addVolume($total);
        $task->log(sprintf('IP e navegador apagados em %d registro(s) com mais de %d dias', $total, $dias));

        return $total > 0 ? 1 : 0;
    }

    public static function registrar(int $propostas_id, int $versao, string $ip, string $user_agent): void
    {
        global $DB;

        $ip = mb_substr($ip, 0, 45);
        if (countElementsInTable(self::TABELA, [
            'plugin_propostas_propostas_id' => $propostas_id,
            'versao'                        => $versao,
            'ip'                            => $ip,
            'date_creation'                 => ['>=', date('Y-m-d H:i:s', time() - self::INTERVALO_MINUTOS * MINUTE_TIMESTAMP)],
        ]) > 0) {
            return;
        }

        $DB->insert(self::TABELA, [
            'plugin_propostas_propostas_id' => $propostas_id,
            'versao'                        => $versao,
            'ip'                            => $ip,
            'user_agent'                    => mb_substr(mb_scrub($user_agent, 'UTF-8'), 0, 255),
            'date_creation'                 => Session::getCurrentTime() ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Proposta && !$item->isNewItem()) {
            $nb = $_SESSION['glpishow_count_on_tabs']
                ? countElementsInTable(self::TABELA, ['plugin_propostas_propostas_id' => $item->getID()])
                : 0;

            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb, $item::class, 'ti ti-eye');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        global $DB;

        if (!$item instanceof Proposta) {
            return false;
        }

        $linhas = iterator_to_array($DB->request([
            'FROM'  => self::TABELA,
            'WHERE' => ['plugin_propostas_propostas_id' => $item->getID()],
            'ORDER' => ['date_creation DESC', 'id DESC'],
            'LIMIT' => self::LIMITE_LISTA,
        ]), false);

        TemplateRenderer::getInstance()->display('@propostas/acessos.html.twig', [
            'linhas' => $linhas,
            'limite' => self::LIMITE_LISTA,
        ]);

        return true;
    }
}
