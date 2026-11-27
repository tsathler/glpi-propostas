<?php

/**
 * Propostas - relatório de eficiência dos consultores (Ferramentas > Relatório de propostas).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Propostas\Eficiencia;
use GlpiPlugin\Propostas\Equipe;
use GlpiPlugin\Propostas\Proposta;
use GlpiPlugin\Propostas\Relatorio;
use GlpiPlugin\Propostas\Visibilidade;

Session::checkRightsOr(Proposta::$rightname, Visibilidade::LEITURA);

[$inicio, $fim] = Eficiencia::periodo(
    is_string($_GET['inicio'] ?? null) ? $_GET['inicio'] : null,
    is_string($_GET['fim'] ?? null) ? $_GET['fim'] : null,
    new DateTimeImmutable('today')
);

// Só as equipes que o usuário pode ver; um id de outra equipe na URL é ignorado.
$equipes_visiveis = Relatorio::equipesVisiveis();
$membros = array_intersect_key(Equipe::membrosPorEquipe(), array_flip($equipes_visiveis));
foreach ($equipes_visiveis as $id) {
    $membros[$id] ??= [];
}
$equipe = (int) ($_GET['equipe'] ?? 0);
if (!isset($membros[$equipe])) {
    $equipe = 0;
}

$por_consultor = Eficiencia::porConsultor(Relatorio::propostas($inicio, $fim), $inicio, $fim);
if ($equipe > 0) {
    $por_consultor = array_intersect_key($por_consultor, array_flip($membros[$equipe]));
    $membros = [$equipe => $membros[$equipe]];
}
$por_equipe = Eficiencia::porEquipe($por_consultor, $membros);

$nomear = static function (array $linhas, callable $nome): array {
    $saida = [];
    foreach ($linhas as $id => $linha) {
        $saida[] = ['nome' => (string) $nome($id)] + $linha;
    }
    usort($saida, static fn (array $a, array $b): int => strcasecmp($a['nome'], $b['nome']));

    return $saida;
};
$consultores = $nomear($por_consultor, static fn (int $id): string => getUserName($id));
$equipes = $nomear($por_equipe, static fn (int $id): string => Dropdown::getDropdownName('glpi_groups', $id));

if (!empty($_GET['csv'])) {
    $numero = static fn (float $v): string => number_format($v, 2, ',', '');
    header('Content-Type: text/csv; charset=UTF-8');
    header(sprintf('Content-Disposition: attachment; filename="relatorio-propostas-%s-%s.csv"', $inicio, $fim));
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, ['Tipo', 'Nome', 'Enviadas', 'Aceitas (das enviadas)', 'Em aberto', 'Taxa', 'Aceitas no período', 'R$ mensal', 'R$ pontual', 'Por unidade'], ';', '"', '');
    foreach ([['Consultor', $consultores], ['Equipe', $equipes]] as [$tipo, $linhas]) {
        foreach ($linhas as $l) {
            fputcsv($saida, [
                $tipo,
                Eficiencia::celulaCsv($l['nome']),
                $l['enviadas'],
                $l['aceitas_envio'],
                $l['em_aberto'],
                $l['taxa'] === null ? '' : number_format($l['taxa'] * 100, 1, ',', '') . '%',
                $l['aceitas'],
                $numero($l['recorrente']),
                $numero($l['pontual']),
                $l['por_unidade'],
            ], ';', '"', '');
        }
    }
    fclose($saida);
    exit;
}

Html::header(Relatorio::getTypeName(), '', 'tools', Relatorio::class);
TemplateRenderer::getInstance()->display('@propostas/relatorio.html.twig', [
    'inicio'           => $inicio,
    'fim'              => $fim,
    'equipe'           => $equipe,
    'opcoes_equipe'    => $nomear(
        array_combine($equipes_visiveis, array_map(static fn (int $id): array => ['id' => $id], $equipes_visiveis)),
        static fn (int $id): string => Dropdown::getDropdownName('glpi_groups', $id)
    ),
    'consultores'      => $consultores,
    'equipes'          => $equipes,
    'mostrar_equipes'  => $equipes_visiveis !== [],
    'url'              => Relatorio::getSearchURL(),
]);
Html::footer();
