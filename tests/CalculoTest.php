<?php

/**
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Tests;

use GlpiPlugin\Propostas\Calculo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalculoTest extends TestCase
{
    /**
     * Hash do exemplo abaixo. Conferido contra o algoritmo anterior à criação desta classe
     * (Proposta::calcularHashValores() na versão 0.4.0). Se este teste quebrar, os aceites já
     * gravados deixam de "conferir" com os valores: só mude de propósito.
     */
    private const HASH_EXEMPLO = '63d46bc1d75406eefa9290deeecc80f72049606ff616029042b1aabcd14f1638';

    /**
     * @return array{numero: string, versao: int, titulo: string, emissao: string, validade_dias: int, aviso_previo: int, condicoes: string}
     */
    private static function cabecalho(): array
    {
        return [
            'numero'        => '00042',
            'versao'        => 3,
            'titulo'        => 'Plataforma de atendimento',
            'emissao'       => '2026-11-24',
            'validade_dias' => 15,
            'aviso_previo'  => 60,
            'condicoes'     => '<p>Implantação em até 5 dias úteis.</p>',
        ];
    }

    /**
     * Itens como vêm do banco (DECIMAL chega como string).
     *
     * @return list<array{tipo: string, name: string, quantidade: string, valor_unitario: string, desconto_pct: string}>
     */
    private static function itens(): array
    {
        return [
            ['tipo' => 'recorrente', 'name' => 'Licença', 'quantidade' => '80.00', 'valor_unitario' => '149.9000', 'desconto_pct' => '40.00'],
            ['tipo' => 'pontual', 'name' => 'Onboarding', 'quantidade' => '1.00', 'valor_unitario' => '5000.0000', 'desconto_pct' => '40.00'],
        ];
    }

    /**
     * @return iterable<string, array{mixed, mixed, mixed, float}>
     */
    public static function subtotais(): iterable
    {
        yield 'com desconto' => ['80.00', '149.9000', '40.00', 7195.20];
        yield 'sem desconto' => [2, 10.5, 0, 21.0];
        yield 'desconto total' => ['5', '89.94', '100', 0.0];
        yield 'quantidade fracionada' => ['1.50', '10.0000', '0.00', 15.0];
        yield 'arredonda a 2 casas' => ['3', '0.3333', '0', 1.0];
        yield 'zero itens' => ['0', '100', '0', 0.0];
    }

    #[DataProvider('subtotais')]
    public function testSubtotal(mixed $quantidade, mixed $unitario, mixed $desconto, float $esperado): void
    {
        self::assertSame($esperado, Calculo::subtotal($quantidade, $unitario, $desconto));
    }

    public function testTotaisSeparaPorTipo(): void
    {
        self::assertSame(['recorrente' => 7195.2, 'pontual' => 3000.0], Calculo::totais(self::itens()));
    }

    public function testTotaisSemItens(): void
    {
        self::assertSame(['recorrente' => 0.0, 'pontual' => 0.0], Calculo::totais([]));
    }

    public function testTotaisNaoAcumulaErroDeFloat(): void
    {
        $itens = [
            ['tipo' => 'recorrente', 'quantidade' => '1', 'valor_unitario' => '0.10', 'desconto_pct' => '0'],
            ['tipo' => 'recorrente', 'quantidade' => '1', 'valor_unitario' => '0.20', 'desconto_pct' => '0'],
        ];

        self::assertSame(0.3, Calculo::totais($itens)['recorrente']);
    }

    public function testHashDoExemploNaoMudou(): void
    {
        self::assertSame(self::HASH_EXEMPLO, Calculo::hashValores(self::cabecalho(), self::itens()));
    }

    public function testHashIgualParaOsMesmosValores(): void
    {
        // Mesmo valor escrito de outro jeito (como o banco pode devolver) dá o mesmo hash.
        $itens = self::itens();
        $itens[0]['valor_unitario'] = '149.9';
        $itens[0]['quantidade'] = 80;

        self::assertSame(self::HASH_EXEMPLO, Calculo::hashValores(self::cabecalho(), $itens));
    }

    /**
     * @return iterable<string, array{callable(array, array): array{0: array, 1: array}}>
     */
    public static function alteracoes(): iterable
    {
        yield 'preço' => [static function (array $c, array $i): array { $i[0]['valor_unitario'] = '149.9100'; return [$c, $i]; }];
        yield 'desconto' => [static function (array $c, array $i): array { $i[1]['desconto_pct'] = '39.00'; return [$c, $i]; }];
        yield 'quantidade' => [static function (array $c, array $i): array { $i[0]['quantidade'] = '81.00'; return [$c, $i]; }];
        yield 'nome do item' => [static function (array $c, array $i): array { $i[0]['name'] = 'Licença anual'; return [$c, $i]; }];
        yield 'tipo do item' => [static function (array $c, array $i): array { $i[1]['tipo'] = 'recorrente'; return [$c, $i]; }];
        yield 'ordem dos itens' => [static fn (array $c, array $i): array => [$c, array_reverse($i)]];
        yield 'item a mais' => [static function (array $c, array $i): array { $i[] = $i[1]; return [$c, $i]; }];
        yield 'versão' => [static function (array $c, array $i): array { $c['versao'] = 4; return [$c, $i]; }];
        yield 'condições' => [static function (array $c, array $i): array { $c['condicoes'] .= ' '; return [$c, $i]; }];
        yield 'validade' => [static function (array $c, array $i): array { $c['validade_dias'] = 30; return [$c, $i]; }];
        yield 'emissão' => [static function (array $c, array $i): array { $c['emissao'] = '2026-11-25'; return [$c, $i]; }];
    }

    #[DataProvider('alteracoes')]
    public function testHashMudaQuandoAlgoAceitoMuda(callable $alterar): void
    {
        [$cabecalho, $itens] = $alterar(self::cabecalho(), self::itens());

        self::assertNotSame(self::HASH_EXEMPLO, Calculo::hashValores($cabecalho, $itens));
    }

    /** Hash do exemplo no formato 2. Mesma regra do HASH_EXEMPLO: só mude de propósito. */
    private const HASH_EXEMPLO_FORMATO2 = 'b7218b34cae6cce2632b7c54ddaae3024f6c9eca23c528094c123cd6d4f94f8e';

    /**
     * @return array<string, mixed>
     */
    private static function cabecalhoFormato2(): array
    {
        return self::cabecalho() + ['razao_social' => 'Empresa Exemplo Ltda', 'cnpj' => '12.ABC.345/01DE-35'];
    }

    /**
     * @return list<array{titulo: string, conteudo: string}>
     */
    private static function blocos(): array
    {
        return [['titulo' => 'Sobre o programa', 'conteudo' => '<p>Texto de exemplo.</p>']];
    }

    public function testFormato2DoExemploNaoMudou(): void
    {
        self::assertSame(
            self::HASH_EXEMPLO_FORMATO2,
            Calculo::hashValoresFormato2(self::cabecalhoFormato2(), self::itens(), self::blocos(), 'Diretora')
        );
    }

    public function testFormato2EDiferenteDoFormato1(): void
    {
        self::assertNotSame(self::HASH_EXEMPLO, self::HASH_EXEMPLO_FORMATO2);
        self::assertSame(2, Calculo::HASH_FORMATO_ATUAL);
    }

    public function testFormato2IgnoraPontuacaoDoCnpj(): void
    {
        $cabecalho = self::cabecalhoFormato2();
        $cabecalho['cnpj'] = '12abc34501de35';

        self::assertSame(
            self::HASH_EXEMPLO_FORMATO2,
            Calculo::hashValoresFormato2($cabecalho, self::itens(), self::blocos(), 'Diretora')
        );
    }

    /**
     * @return iterable<string, array{callable(array, array, string): array{0: array, 1: array, 2: string}}>
     */
    public static function alteracoesFormato2(): iterable
    {
        yield 'CNPJ' => [static function (array $c, array $b, string $cargo): array { $c['cnpj'] = '11.222.333/0001-81'; return [$c, $b, $cargo]; }];
        yield 'razão social' => [static function (array $c, array $b, string $cargo): array { $c['razao_social'] .= ' ME'; return [$c, $b, $cargo]; }];
        yield 'cargo' => [static fn (array $c, array $b, string $cargo): array => [$c, $b, 'Gerente']];
        yield 'texto do bloco' => [static function (array $c, array $b, string $cargo): array { $b[0]['conteudo'] = '<p>Outro texto.</p>'; return [$c, $b, $cargo]; }];
        yield 'título do bloco' => [static function (array $c, array $b, string $cargo): array { $b[0]['titulo'] = 'Outro título'; return [$c, $b, $cargo]; }];
        yield 'bloco a mais' => [static function (array $c, array $b, string $cargo): array { $b[] = ['titulo' => 'Fechamento', 'conteudo' => '']; return [$c, $b, $cargo]; }];
        yield 'sem blocos' => [static fn (array $c, array $b, string $cargo): array => [$c, [], $cargo]];
        yield 'preço (como no formato 1)' => [static fn (array $c, array $b, string $cargo): array => [$c + ['_preco' => true], $b, $cargo]];
    }

    #[DataProvider('alteracoesFormato2')]
    public function testFormato2MudaQuandoAlgoAceitoMuda(callable $alterar): void
    {
        [$cabecalho, $blocos, $cargo] = $alterar(self::cabecalhoFormato2(), self::blocos(), 'Diretora');
        $itens = self::itens();
        if (isset($cabecalho['_preco'])) {
            unset($cabecalho['_preco']);
            $itens[0]['valor_unitario'] = '149.9100';
        }

        self::assertNotSame(self::HASH_EXEMPLO_FORMATO2, Calculo::hashValoresFormato2($cabecalho, $itens, $blocos, $cargo));
    }

    public function testCodigo(): void
    {
        self::assertSame('63D4-6BC1-D754-06EE', Calculo::codigo(self::HASH_EXEMPLO));
        self::assertSame('', Calculo::codigo(null));
        self::assertSame('', Calculo::codigo(''));
    }

    public function testPropostaTravadaSoDeixaPassarComentarioEInternos(): void
    {
        $gravado = ['id' => 7, 'name' => 'Proposta', 'empresa_cnpj' => '11222333000181', 'comment' => ''];
        [$input, $ignorados] = Calculo::filtrarTravada([
            'id'           => 7,
            'name'         => 'Outro nome',
            'empresa_cnpj' => '11222333000181',
            'comment'      => 'nota interna',
            '_glpi_csrf_token' => 'x',
            'campo_novo'   => 'y',
        ], $gravado, ['id', 'comment']);

        self::assertSame(['id' => 7, 'comment' => 'nota interna', '_glpi_csrf_token' => 'x'], $input);
        self::assertSame(['name'], $ignorados, 'só avisa do que mudaria; o CNPJ igual e o campo desconhecido não contam');
    }
}
