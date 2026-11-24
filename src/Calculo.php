<?php

/**
 * Propostas - cálculos de valores e do código de verificação.
 *
 * Sem dependência do GLPI, para poder ser testado sozinho (tests/CalculoTest.php).
 * Mudar o formato de hashValores() faz os aceites antigos deixarem de "conferir".
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Calculo
{
    public const TIPO_RECORRENTE = 'recorrente';
    public const TIPO_PONTUAL    = 'pontual';

    /**
     * Quantidade x valor unitário, menos o desconto, arredondado a 2 casas.
     */
    public static function subtotal(float|int|string $quantidade, float|int|string $valor_unitario, float|int|string $desconto_pct): float
    {
        $bruto = (float) $quantidade * (float) $valor_unitario;

        return round($bruto * (1 - ((float) $desconto_pct / 100)), 2);
    }

    /**
     * @param iterable<array{tipo: string, quantidade: mixed, valor_unitario: mixed, desconto_pct: mixed}> $itens
     * @return array{recorrente: float, pontual: float}
     */
    public static function totais(iterable $itens): array
    {
        $totais = [self::TIPO_RECORRENTE => 0.0, self::TIPO_PONTUAL => 0.0];
        foreach ($itens as $item) {
            $totais[$item['tipo']] = ($totais[$item['tipo']] ?? 0.0)
                + self::subtotal($item['quantidade'], $item['valor_unitario'], $item['desconto_pct']);
        }

        return array_map(static fn (float $v): float => round($v, 2), $totais);
    }

    /** Formato do hash gravado nos aceites novos. O formato 1 continua valendo para os antigos. */
    public const HASH_FORMATO_ATUAL = 2;

    /**
     * SHA-256 do que o cliente aceita, no formato 1 (aceites da versão 1.0.0).
     *
     * @param array{numero: string, versao: int, titulo: string, emissao: string, validade_dias: int, aviso_previo: int, condicoes: string} $cabecalho
     * @param list<array{tipo: string, name: string, quantidade: mixed, valor_unitario: mixed, desconto_pct: mixed}> $itens na ordem exibida
     */
    public static function hashValores(array $cabecalho, array $itens): string
    {
        return self::sha256(self::conteudoFormato1($cabecalho, $itens));
    }

    /**
     * SHA-256 do que o cliente aceita, no formato 2: o do formato 1 mais a empresa (razão social e
     * CNPJ), os blocos de texto da proposta e o cargo de quem aceitou.
     *
     * @param array{numero: string, versao: int, titulo: string, emissao: string, validade_dias: int, aviso_previo: int, condicoes: string, razao_social: string, cnpj: string} $cabecalho
     * @param list<array{tipo: string, name: string, quantidade: mixed, valor_unitario: mixed, desconto_pct: mixed}> $itens na ordem exibida
     * @param list<array{titulo: string, conteudo: string}> $blocos na ordem exibida
     */
    public static function hashValoresFormato2(array $cabecalho, array $itens, array $blocos, string $cargo): string
    {
        $conteudo = self::conteudoFormato1($cabecalho, $itens) + [
            'formato' => 2,
            'empresa' => [
                'razao_social' => (string) $cabecalho['razao_social'],
                'cnpj'         => Cnpj::normalizar((string) $cabecalho['cnpj']),
            ],
            'blocos'  => array_map(
                static fn (array $b): array => ['titulo' => (string) $b['titulo'], 'conteudo' => (string) $b['conteudo']],
                array_values($blocos)
            ),
            'cargo'   => (string) $cargo,
        ];

        return self::sha256($conteudo);
    }

    /**
     * @param array<string, mixed> $conteudo
     */
    private static function sha256(array $conteudo): string
    {
        return hash('sha256', json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $cabecalho
     * @param list<array<string, mixed>> $itens
     * @return array<string, mixed>
     */
    private static function conteudoFormato1(array $cabecalho, array $itens): array
    {
        $dec = static fn ($v, int $casas): string => number_format((float) $v, $casas, '.', '');

        $linhas = [];
        foreach ($itens as $item) {
            $linhas[] = [
                'tipo'           => (string) $item['tipo'],
                'nome'           => (string) $item['name'],
                'quantidade'     => $dec($item['quantidade'], 2),
                'valor_unitario' => $dec($item['valor_unitario'], 4),
                'desconto_pct'   => $dec($item['desconto_pct'], 2),
                'subtotal'       => $dec(self::subtotal($item['quantidade'], $item['valor_unitario'], $item['desconto_pct']), 2),
            ];
        }
        $totais = self::totais($itens);

        return [
            'numero'        => (string) $cabecalho['numero'],
            'versao'        => (int) $cabecalho['versao'],
            'titulo'        => (string) $cabecalho['titulo'],
            'emissao'       => (string) $cabecalho['emissao'],
            'validade_dias' => (int) $cabecalho['validade_dias'],
            'aviso_previo'  => (int) $cabecalho['aviso_previo'],
            'condicoes'     => (string) $cabecalho['condicoes'],
            'itens'         => $linhas,
            'total_mensal'  => $dec($totais[self::TIPO_RECORRENTE], 2),
            'total_unico'   => $dec($totais[self::TIPO_PONTUAL], 2),
        ];
    }

    /**
     * Código de verificação exibido: os 16 primeiros caracteres do hash, em grupos de 4 (ex.: 77EB-0631-CBC7-0772).
     */
    public static function codigo(?string $hash): string
    {
        if ($hash === null || $hash === '') {
            return '';
        }

        return implode('-', str_split(strtoupper(substr($hash, 0, 16)), 4));
    }
}
