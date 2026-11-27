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

// Exportação: os quatro formatos partem das mesmas linhas (Eficiencia::linhasExportacao).
$formato = is_string($_GET['formato'] ?? null) ? $_GET['formato'] : '';
if (in_array($formato, ['csv', 'xlsx', 'ods', 'pdf'], true)) {
    $secoes = [['Consultores', 'Consultor', Eficiencia::linhasExportacao($consultores)]];
    if ($equipes_visiveis !== []) {
        $secoes[] = ['Equipes', 'Equipe', Eficiencia::linhasExportacao($equipes)];
    }
    $cabecalho = Eficiencia::CABECALHO_EXPORTACAO;
    $arquivo = sprintf('relatorio-propostas-%s-%s.%s', $inicio, $fim, $formato);
    // Texto para CSV e PDF: taxa em %, valores com vírgula decimal.
    $em_texto = static function (array $linha, string $milhar): array {
        $linha[Eficiencia::COLUNA_TAXA] = $linha[Eficiencia::COLUNA_TAXA] === null
            ? ''
            : number_format($linha[Eficiencia::COLUNA_TAXA] * 100, 1, ',', '') . '%';
        foreach (Eficiencia::COLUNAS_VALOR as $c) {
            $linha[$c] = number_format($linha[$c], 2, ',', $milhar);
        }

        return $linha;
    };

    if ($formato === 'pdf') {
        $data = static fn (string $d): string => implode('/', array_reverse(explode('-', $d)));
        $html = '<h2>' . htmlescape(Relatorio::getTypeName()) . '</h2>'
            . '<p>' . htmlescape(sprintf('Período: %s a %s', $data($inicio), $data($fim))) . '</p>';
        foreach ($secoes as [$titulo, , $linhas]) {
            $html .= '<h3>' . htmlescape($titulo) . '</h3><table border="1" cellpadding="4"><tr style="background-color:#e9ecf1;font-weight:bold;">';
            foreach ($cabecalho as $i => $coluna) {
                $html .= '<th align="' . ($i === 0 ? 'left' : 'right') . '">' . htmlescape($coluna) . '</th>';
            }
            $html .= '</tr>';
            foreach ($linhas as $linha) {
                $html .= '<tr>';
                foreach ($em_texto($linha, '.') as $i => $celula) {
                    $html .= '<td align="' . ($i === 0 ? 'left' : 'right') . '">' . htmlescape((string) $celula) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }
        $pdf = new GLPIPDF(['orientation' => 'L'], null, Relatorio::getTypeName());
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($arquivo, 'D');

        return;
    }

    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF");
        fputcsv($saida, ['Tipo', ...$cabecalho], ';', '"', '');
        foreach ($secoes as [, $tipo, $linhas]) {
            foreach ($linhas as $linha) {
                $linha[0] = Eficiencia::celulaCsv($linha[0]);
                fputcsv($saida, [$tipo, ...$em_texto($linha, '')], ';', '"', '');
            }
        }
        fclose($saida);

        return;
    }

    // XLSX e ODS: uma aba por seção, números de verdade, nome sempre como texto (nunca fórmula).
    $planilha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $planilha->removeSheetByIndex(0);
    foreach ($secoes as [$titulo, , $linhas]) {
        $aba = $planilha->createSheet();
        $aba->setTitle($titulo);
        $aba->fromArray($cabecalho, null, 'A1');
        $aba->getStyle('A1:I1')->getFont()->setBold(true);
        foreach ($linhas as $n => $linha) {
            $l = $n + 2;
            foreach ($linha as $c => $celula) {
                if ($c === 0) {
                    $aba->setCellValueExplicit([1, $l], (string) $celula, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } elseif ($celula !== null) {
                    $aba->setCellValue([$c + 1, $l], $celula);
                }
            }
        }
        $ultima = count($linhas) + 1;
        $aba->getStyle('E2:E' . $ultima)->getNumberFormat()->setFormatCode('0.0%');
        $aba->getStyle('G2:H' . $ultima)->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', 'I') as $coluna) {
            $aba->getColumnDimension($coluna)->setAutoSize(true);
        }
    }
    $planilha->setActiveSheetIndex(0);
    header('Content-Type: ' . ($formato === 'xlsx'
        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        : 'application/vnd.oasis.opendocument.spreadsheet'));
    header('Content-Disposition: attachment; filename="' . $arquivo . '"');
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($planilha, ucfirst($formato))->save('php://output');

    return;
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
