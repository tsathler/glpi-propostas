<?php

/**
 * Propostas - busca de razão social e nome fantasia pelo CNPJ.
 *
 * A consulta é feita pelo servidor do GLPI (com o proxy configurado no GLPI, se houver), com
 * limite de 5 segundos. Provedor padrão: BrasilAPI. A URL é configurável em
 * Configuração > Plug-ins > Propostas e usa {cnpj} no lugar do número. Envia só o CNPJ.
 *
 * Resposta JSON: {ok: true, cnpj, razao_social, nome_fantasia, situacao, ativa} ou {ok: false, erro}.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use GlpiPlugin\Propostas\Cnpj;
use GlpiPlugin\Propostas\Proposta;

Session::checkLoginUser();
Session::checkRightsOr(Proposta::$rightname, [CREATE, UPDATE]);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$responder = static function (array $dados): void {
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$config = Config::getConfigurationValues('plugin:propostas', ['cnpj_consulta_ativa', 'cnpj_consulta_url']);
if (empty($config['cnpj_consulta_ativa']) || empty($config['cnpj_consulta_url'])) {
    $responder(['ok' => false, 'erro' => __('A busca de CNPJ está desligada na configuração do plugin.', 'propostas')]);
    return;
}

$cnpj = Cnpj::normalizar((string) ($_GET['cnpj'] ?? ''));
if (!Cnpj::validar($cnpj)) {
    $responder(['ok' => false, 'erro' => __('CNPJ inválido: confira os dígitos.', 'propostas')]);
    return;
}

$url = str_replace('{cnpj}', rawurlencode($cnpj), (string) $config['cnpj_consulta_url']);

try {
    $resposta = Toolbox::getGuzzleClient([
        'timeout'         => 5,
        'connect_timeout' => 5,
        'http_errors'     => false,
        'allow_redirects' => false,
        'headers'         => ['Accept' => 'application/json', 'User-Agent' => 'GLPI-Propostas'],
    ])->get($url);
} catch (Throwable $e) {
    Toolbox::logInFile('propostas', sprintf("Busca de CNPJ falhou: %s\n", $e->getMessage()));
    $responder(['ok' => false, 'erro' => __('Não foi possível consultar agora. Digite os dados à mão.', 'propostas')]);
    return;
}

$status = $resposta->getStatusCode();
if ($status === 404) {
    $responder(['ok' => false, 'erro' => __('CNPJ não encontrado no provedor de consulta.', 'propostas')]);
    return;
}
$dados = json_decode((string) $resposta->getBody(), true);
if ($status !== 200 || !is_array($dados)) {
    Toolbox::logInFile('propostas', sprintf("Busca de CNPJ: resposta HTTP %d inesperada\n", $status));
    $responder(['ok' => false, 'erro' => __('Não foi possível consultar agora. Digite os dados à mão.', 'propostas')]);
    return;
}

// Nomes de campo da BrasilAPI e da Minha Receita (mesmo formato).
$situacao = (string) ($dados['descricao_situacao_cadastral'] ?? $dados['situacao_cadastral'] ?? '');
$responder([
    'ok'            => true,
    'cnpj'          => Cnpj::formatar($cnpj),
    'razao_social'  => (string) ($dados['razao_social'] ?? ''),
    'nome_fantasia' => (string) ($dados['nome_fantasia'] ?? ''),
    'situacao'      => $situacao,
    'ativa'         => strtoupper($situacao) === 'ATIVA',
]);
