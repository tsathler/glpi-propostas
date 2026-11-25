<?php

/**
 * Propostas - configuração: endereço externo do link público, busca de CNPJ e chamado automático.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Propostas\Equipe;
use GlpiPlugin\Propostas\Marca;
use GlpiPlugin\Propostas\Proposta;

Session::checkRight('config', UPDATE);

const PROPOSTAS_CNPJ_URL_PADRAO = 'https://brasilapi.com.br/api/cnpj/v1/{cnpj}';

if (isset($_POST['sincronizar'])) {
    Equipe::sincronizarTudo();
    Session::addMessageAfterRedirect(htmlescape(__('Observadores dos chamados abertos sincronizados.', 'propostas')));
    Html::back();
}

if (isset($_POST['update'])) {
    $config_antes = Config::getConfigurationValues('plugin:propostas', ['equipes_groups_id']);
    $url     = rtrim(trim((string) ($_POST['url_publica'] ?? '')), '/');
    $url_ok  = $url === '' || preg_match('#^https?://[^/\s]+(/[^\s]*)?$#i', $url) === 1;
    $cnpj    = trim((string) ($_POST['cnpj_consulta_url'] ?? '')) ?: PROPOSTAS_CNPJ_URL_PADRAO;
    $cnpj_ok = preg_match('#^https://[^/\s]+/\S*\{cnpj\}\S*$#i', $cnpj) === 1;
    // Cores da página do cliente: em branco volta ao padrão; inválida não é gravada.
    $cor_principal = trim((string) ($_POST['marca_cor_principal'] ?? ''));
    $cor_destaque  = trim((string) ($_POST['marca_cor_destaque'] ?? ''));
    $cores_ok = ($cor_principal === '' || Marca::corValida($cor_principal) !== null)
        && ($cor_destaque === '' || Marca::corValida($cor_destaque) !== null);

    if (!$cores_ok) {
        Session::addMessageAfterRedirect(htmlescape(__('Informe as cores no formato #rrggbb (ex.: #1e3a8a) ou deixe em branco.', 'propostas')), false, ERROR);
    } elseif (!$url_ok) {
        Session::addMessageAfterRedirect(htmlescape(__('Informe um endereço completo, como https://propostas.exemplo.com.br', 'propostas')), false, ERROR);
    } elseif (!$cnpj_ok) {
        Session::addMessageAfterRedirect(htmlescape(__('A URL da busca de CNPJ precisa começar com https:// e ter {cnpj} no lugar do número.', 'propostas')), false, ERROR);
    } else {
        Config::setConfigurationValues('plugin:propostas', [
            'url_publica'         => $url,
            'cnpj_consulta_ativa' => !empty($_POST['cnpj_consulta_ativa']) ? '1' : '0',
            'cnpj_consulta_url'   => $cnpj,
            'chamado_itilcategories_id' => (string) max(0, (int) ($_POST['chamado_itilcategories_id'] ?? 0)),
            'chamado_groups_id'         => (string) max(0, (int) ($_POST['chamado_groups_id'] ?? 0)),
            'equipes_groups_id'         => (string) max(0, (int) ($_POST['equipes_groups_id'] ?? 0)),
            'propostas_entities_id'     => !empty($_POST['entidade_padrao_ativa'])
                ? (string) max(0, (int) ($_POST['propostas_entities_id'] ?? 0))
                : '-1',
            'marca_cor_principal'       => (string) Marca::corValida($cor_principal),
            'marca_cor_destaque'        => (string) Marca::corValida($cor_destaque),
            'marca_rodape'              => Marca::textoRodape($_POST['marca_rodape'] ?? ''),
        ]);
        if (!empty($_POST['remover_logo'])) {
            Marca::removerLogo();
        } elseif ((int) ($_FILES['logo_publico']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $erro = Marca::salvarLogo($_FILES['logo_publico']);
            if ($erro !== null) {
                Session::addMessageAfterRedirect(htmlescape($erro), false, ERROR);
            }
        }
        // Grupo pai trocado: as equipes mudam, e com elas os gestores observadores.
        if ((int) ($_POST['equipes_groups_id'] ?? 0) !== (int) ($config_antes['equipes_groups_id'] ?? 0)) {
            Equipe::sincronizarTudo();
        }
        Session::addMessageAfterRedirect(htmlescape(__('Configuração salva.', 'propostas')));
    }
    Html::back();
}

Html::header(__('Propostas', 'propostas'), '', 'config', 'plugin');

$config = Config::getConfigurationValues('plugin:propostas', [
    'url_publica', 'cnpj_consulta_ativa', 'cnpj_consulta_url', 'chamado_itilcategories_id', 'chamado_groups_id',
    'equipes_groups_id', 'propostas_entities_id', 'marca_cor_principal', 'marca_cor_destaque', 'marca_rodape',
]);
$entidade_padrao = (int) ($config['propostas_entities_id'] ?? -1);
TemplateRenderer::getInstance()->display('@propostas/config.html.twig', [
    'url_publica'         => (string) ($config['url_publica'] ?? ''),
    'url_efetiva'         => Proposta::getUrlBasePublica(),
    'cnpj_consulta_ativa' => !empty($config['cnpj_consulta_ativa']),
    'cnpj_consulta_url'   => (string) ($config['cnpj_consulta_url'] ?? PROPOSTAS_CNPJ_URL_PADRAO),
    'cnpj_url_padrao'     => PROPOSTAS_CNPJ_URL_PADRAO,
    'chamado_itilcategories_id' => (int) ($config['chamado_itilcategories_id'] ?? 0),
    'chamado_groups_id'         => (int) ($config['chamado_groups_id'] ?? 0),
    'equipes_groups_id'         => (int) ($config['equipes_groups_id'] ?? 0),
    'entidade_padrao_ativa'     => $entidade_padrao >= 0,
    'propostas_entities_id'     => max(0, $entidade_padrao),
    'avisos_equipes'            => Equipe::avisos(),
    'equipes'                   => array_map(
        static fn (int $id): string => Dropdown::getDropdownName('glpi_groups', $id),
        Equipe::equipes()
    ),
    'marca_cor_principal'       => (string) ($config['marca_cor_principal'] ?? ''),
    'marca_cor_destaque'        => (string) ($config['marca_cor_destaque'] ?? ''),
    'marca_padrao'              => ['principal' => Marca::COR_PRINCIPAL_PADRAO, 'destaque' => Marca::COR_DESTAQUE_PADRAO],
    'marca_logo'                => Marca::logoDataUri(),
    'marca_rodape'              => (string) ($config['marca_rodape'] ?? ''),
    'marca_rodape_maximo'       => Marca::RODAPE_MAXIMO,
]);

Html::footer();
