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

    /**
     * Proposta enviada ou aceita: só os campos livres (e os internos, com "_") passam. É o que mantém a
     * proposta igual ao que o código de verificação do aceite confere.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $fields valores gravados
     * @param list<string> $livres
     * @return array{array<string, mixed>, list<string>} [input filtrado, campos que mudariam e foram ignorados]
     */
    public static function filtrarTravada(array $input, array $fields, array $livres): array
    {
        $ignorados = [];
        foreach ($input as $campo => $valor) {
            if (str_starts_with((string) $campo, '_') || in_array($campo, $livres, true)) {
                continue;
            }
            if (array_key_exists($campo, $fields) && (string) $fields[$campo] !== (string) $valor) {
                $ignorados[] = (string) $campo;
            }
            unset($input[$campo]);
        }

        return [$input, $ignorados];
    }

    /** Dias depois do envio sem o cliente ver a proposta até o lembrete "não vista". */
    public const LEMBRETE_SEM_VISTA_DIAS = 2;

    /** Dias antes do vencimento para o lembrete "vencendo". */
    public const LEMBRETE_VENCENDO_DIAS = 2;

    /**
     * Lembretes ao consultor que já cabem para uma proposta (ação automática diária "lembrar").
     * - nao_vista: enviada há 2 dias ou mais e o cliente ainda não viu.
     * - vencendo: ainda em aberto, faltam 2 dias ou menos, e o link foi gerado antes dessa janela.
     *
     * @param bool $aguardando_visto status Enviada (o cliente ainda não viu)
     * @param bool $em_aberto status Enviada, Visualizada ou Com dúvida
     * @return list<string>
     */
    public static function lembretesDevidos(
        bool $aguardando_visto,
        bool $em_aberto,
        ?string $gerado_em,
        ?string $expira,
        bool $avisou_nao_vista,
        bool $avisou_vencendo,
        \DateTimeImmutable $agora
    ): array {
        if (!$em_aberto || $expira === null || new \DateTimeImmutable($expira) <= $agora) {
            return [];
        }
        $janela = (new \DateTimeImmutable($expira))->modify(sprintf('-%d days', self::LEMBRETE_VENCENDO_DIAS));
        $gerado = $gerado_em !== null ? new \DateTimeImmutable($gerado_em) : null;

        $devidos = [];
        if ($aguardando_visto && !$avisou_nao_vista && $gerado !== null
            && $gerado->modify(sprintf('+%d days', self::LEMBRETE_SEM_VISTA_DIAS)) <= $agora) {
            $devidos[] = 'nao_vista';
        }
        if (!$avisou_vencendo && $agora >= $janela && ($gerado === null || $gerado < $janela)) {
            $devidos[] = 'vencendo';
        }

        return $devidos;
    }

    /** Validade da proposta em dias: mínimo e máximo aceitos. */
    public const VALIDADE_MINIMA = 1;
    public const VALIDADE_MAXIMA = 365;

    /** Validade dentro dos limites? Aceita "15" ou 15; o resto é inválido. */
    public static function validadeValida(mixed $dias): bool
    {
        $texto = trim((string) $dias);

        return preg_match('/^\d+$/', $texto) === 1
            && (int) $texto >= self::VALIDADE_MINIMA
            && (int) $texto <= self::VALIDADE_MAXIMA;
    }

    /** Exibição ao cliente: itens com quantidade, subtotal e totais somados (o padrão). */
    public const EXIBICAO_TOTAL = 'total';

    /** Exibição ao cliente: preço por unidade ("de R$ X por R$ Y"), sem somar totais. */
    public const EXIBICAO_UNIDADE = 'unidade';

    public static function exibicaoValida(mixed $valor): string
    {
        return $valor === self::EXIBICAO_UNIDADE ? self::EXIBICAO_UNIDADE : self::EXIBICAO_TOTAL;
    }

    /**
     * Percentual entre 0 e 100, com 2 casas. Aceita "5", "5.5" e "5,5"; o resto vira 0.
     */
    public static function percentual(mixed $valor): float
    {
        $numero = self::lerValor($valor);

        return $numero === null ? 0.0 : round(min(100.0, $numero), 2);
    }

    /**
     * Valor digitado no formulário: "123.45", "123,45", "1.234,56" ou "1.500" (milhar). Null se vazio, inválido ou negativo.
     */
    public static function lerValor(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor >= 0 ? (float) $valor : null;
        }
        $texto = self::normalizarNumero((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $texto)) {
            return null;
        }

        return (float) $texto;
    }

    /**
     * Número digitado no formato brasileiro para o formato do PHP ("1.234,56" → "1234.56").
     * Só pontos em grupos de 3 ("1.500", "12.000.000") são milhar; "0.125" e "1.5" continuam decimais.
     * Não valida: o que não for número segue como veio, para a validação recusar.
     */
    public static function normalizarNumero(string $texto): string
    {
        $texto = trim($texto);
        // Ponto depois da última vírgula ("1,234.56", formato americano): não é brasileiro; segue como
        // veio, para a validação recusar em vez de gravar 1,23456.
        if (str_contains($texto, ',') && strrpos($texto, '.') > strrpos($texto, ',')) {
            return $texto;
        }
        if (str_contains($texto, ',')) {
            return str_replace(['.', ','], ['', '.'], $texto);
        }
        if (preg_match('/^[1-9]\d{0,2}(\.\d{3})+$/', $texto) === 1) {
            return str_replace('.', '', $texto);
        }

        return $texto;
    }

    /**
     * Itens do modelo como vão para a proposta: o valor e o desconto (%) digitados para cada item
     * (quando válidos) substituem os do modelo; vazio ou inválido mantém o do modelo.
     *
     * @param list<array<string, mixed>> $itens_modelo linhas de ModeloItem (com id)
     * @param array<int|string, mixed> $valores valor digitado por id do item do modelo
     * @param array<int|string, mixed> $descontos desconto (%) digitado por id do item do modelo
     * @return list<array<string, mixed>>
     */
    public static function itensDaCopia(array $itens_modelo, array $valores, array $descontos = []): array
    {
        // Os campos do quadro são numéricos: o navegador manda ponto decimal ("1.125" é 1,125). Só
        // texto com vírgula passa pela leitura brasileira (ponto de milhar).
        $ler = static fn (mixed $v): ?float => is_numeric($v) ? ((float) $v >= 0 ? (float) $v : null) : self::lerValor($v);
        $copia = [];
        foreach ($itens_modelo as $item) {
            $id = (int) ($item['id'] ?? 0);
            $digitado = $ler($valores[$id] ?? null);
            if ($digitado !== null) {
                $item['valor_unitario'] = $digitado;
            }
            $desconto = $ler($descontos[$id] ?? null);
            if ($desconto !== null) {
                $item['desconto_pct'] = round(min(100.0, $desconto), 2);
            }
            $copia[] = $item;
        }

        return $copia;
    }

    /**
     * Nome da proposta quando não é digitado: "{modelo} – {razão social}", com o que houver;
     * sem nenhum dos dois, "Proposta".
     */
    public static function nomeAutomatico(string $modelo, string $empresa): string
    {
        $partes = array_values(array_filter([trim($modelo), trim($empresa)], static fn (string $p): bool => $p !== ''));

        return $partes === [] ? 'Proposta' : mb_substr(implode(' – ', $partes), 0, 255);
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
