<?php

/**
 * Propostas - configuração (endereço externo do link público).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Propostas\Proposta;

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    $url = rtrim(trim((string) ($_POST['url_publica'] ?? '')), '/');
    if ($url !== '' && !preg_match('#^https?://[^/\s]+(/[^\s]*)?$#i', $url)) {
        Session::addMessageAfterRedirect(htmlescape(__('Informe um endereço completo, como https://propostas.exemplo.com.br', 'propostas')), false, ERROR);
    } else {
        Config::setConfigurationValues('plugin:propostas', ['url_publica' => $url]);
        Session::addMessageAfterRedirect(htmlescape(__('Configuração salva.', 'propostas')));
    }
    Html::back();
}

Html::header(__('Propostas', 'propostas'), '', 'config', 'plugin');

TemplateRenderer::getInstance()->display('@propostas/config.html.twig', [
    'url_publica' => (string) (Config::getConfigurationValue('plugin:propostas', 'url_publica') ?? ''),
    'url_efetiva' => Proposta::getUrlBasePublica(),
]);

Html::footer();
