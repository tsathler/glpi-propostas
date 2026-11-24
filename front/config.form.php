<?php

/**
 * Propostas - configuração: endereço externo do link público e busca de CNPJ.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Propostas\Proposta;

Session::checkRight('config', UPDATE);

const PROPOSTAS_CNPJ_URL_PADRAO = 'https://brasilapi.com.br/api/cnpj/v1/{cnpj}';

if (isset($_POST['update'])) {
    $url     = rtrim(trim((string) ($_POST['url_publica'] ?? '')), '/');
    $url_ok  = $url === '' || preg_match('#^https?://[^/\s]+(/[^\s]*)?$#i', $url) === 1;
    $cnpj    = trim((string) ($_POST['cnpj_consulta_url'] ?? '')) ?: PROPOSTAS_CNPJ_URL_PADRAO;
    $cnpj_ok = preg_match('#^https://[^/\s]+/\S*\{cnpj\}\S*$#i', $cnpj) === 1;

    if (!$url_ok) {
        Session::addMessageAfterRedirect(htmlescape(__('Informe um endereço completo, como https://propostas.exemplo.com.br', 'propostas')), false, ERROR);
    } elseif (!$cnpj_ok) {
        Session::addMessageAfterRedirect(htmlescape(__('A URL da busca de CNPJ precisa começar com https:// e ter {cnpj} no lugar do número.', 'propostas')), false, ERROR);
    } else {
        Config::setConfigurationValues('plugin:propostas', [
            'url_publica'         => $url,
            'cnpj_consulta_ativa' => !empty($_POST['cnpj_consulta_ativa']) ? '1' : '0',
            'cnpj_consulta_url'   => $cnpj,
        ]);
        Session::addMessageAfterRedirect(htmlescape(__('Configuração salva.', 'propostas')));
    }
    Html::back();
}

Html::header(__('Propostas', 'propostas'), '', 'config', 'plugin');

$config = Config::getConfigurationValues('plugin:propostas', ['url_publica', 'cnpj_consulta_ativa', 'cnpj_consulta_url']);
TemplateRenderer::getInstance()->display('@propostas/config.html.twig', [
    'url_publica'         => (string) ($config['url_publica'] ?? ''),
    'url_efetiva'         => Proposta::getUrlBasePublica(),
    'cnpj_consulta_ativa' => !empty($config['cnpj_consulta_ativa']),
    'cnpj_consulta_url'   => (string) ($config['cnpj_consulta_url'] ?? PROPOSTAS_CNPJ_URL_PADRAO),
    'cnpj_url_padrao'     => PROPOSTAS_CNPJ_URL_PADRAO,
]);

Html::footer();
