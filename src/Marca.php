<?php

/**
 * Propostas - identidade visual da página do cliente: logotipo, cores, rodapé e elementos decorativos.
 *
 * Configurado em Configuração > Plug-ins > Propostas. O logotipo fica na pasta de arquivos do
 * plugin (GLPI_PLUGIN_DOC_DIR/propostas) e vai embutido na página (data URI): a página pública
 * não carrega nada de fora dela. Os elementos decorativos (imagens transparentes espalhadas pelo
 * topo, pelo quadro de decisão e pelas margens) ficam na mesma pasta, como decoracao-N.
 * Sem configuração, a página fica com as cores de antes e sem decoração.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Marca
{
    /** Fundo do topo e do quadro de decisão (padrão: o azul-escuro de antes). */
    public const COR_PRINCIPAL_PADRAO = '#13213d';

    /** Botão de aceite, etiquetas e detalhe dos títulos (padrão: o azul de antes). */
    public const COR_DESTAQUE_PADRAO = '#2563eb';

    /** Tamanho máximo do arquivo enviado. */
    public const TAMANHO_MAXIMO = 2 * 1024 * 1024;

    /** Altura máxima do logotipo guardado (px): basta para o topo, mesmo em telas de alta densidade. */
    public const ALTURA_MAXIMA = 160;

    /** Quantos elementos decorativos a página aceita (vagas no topo, na decisão e nas margens). */
    public const DECORACAO_MAXIMO = 6;

    /** Lado máximo de cada elemento decorativo guardado (px): a página mostra até 560 px, em baixa opacidade. */
    public const DECORACAO_LADO = 360;

    /** Tipos aceitos. SVG fica de fora: pode carregar script. */
    private const TIPOS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /**
     * Cor no formato #rrggbb (aceita #rgb e sem o #). Null se inválida ou vazia.
     */
    public static function corValida(mixed $valor): ?string
    {
        $cor = strtolower(ltrim(trim((string) $valor), '#'));
        if (preg_match('/^[0-9a-f]{3}$/', $cor) === 1) {
            $cor = $cor[0] . $cor[0] . $cor[1] . $cor[1] . $cor[2] . $cor[2];
        }

        return preg_match('/^[0-9a-f]{6}$/', $cor) === 1 ? '#' . $cor : null;
    }

    /** Contraste mínimo do texto branco sobre as cores da marca (WCAG AA para texto comum). */
    public const CONTRASTE_MINIMO = 4.5;

    /** Luminância relativa (WCAG 2) de uma cor #rrggbb. */
    public static function luminancia(string $hex): float
    {
        $canal = static function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return 0.2126 * $canal((int) $r) + 0.7152 * $canal((int) $g) + 0.0722 * $canal((int) $b);
    }

    /** Contraste entre duas cores #rrggbb (1 a 21). */
    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * A mesma cor, escurecida aos poucos (mistura com preto) até o texto branco ficar legível sobre
     * ela. Cor que já é escura o bastante volta igual.
     */
    public static function legivelComBranco(string $hex): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $cor = $hex;
        for ($fator = 1.0; $fator > 0 && self::contraste($cor, '#ffffff') < self::CONTRASTE_MINIMO; $fator -= 0.02) {
            $cor = sprintf('#%02x%02x%02x', (int) round($r * $fator), (int) round($g * $fator), (int) round($b * $fator));
        }

        return $cor;
    }

    /** Tamanho máximo do texto do rodapé. */
    public const RODAPE_MAXIMO = 255;

    /**
     * Texto do rodapé: uma linha de texto puro (sem quebras e espaços repetidos), cortado no limite.
     * O template escapa o HTML na exibição.
     */
    public static function textoRodape(mixed $valor): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', (string) $valor));

        return mb_substr($texto, 0, self::RODAPE_MAXIMO);
    }

    /**
     * @return array{logo: ?string, cor_principal: string, cor_destaque: string, cor_destaque_forte: string, rodape: string, decoracao: list<string>}
     */
    public static function dadosPublicos(): array
    {
        $config = \Config::getConfigurationValues('plugin:propostas', ['marca_cor_principal', 'marca_cor_destaque', 'marca_rodape']);
        $principal = self::corValida($config['marca_cor_principal'] ?? null) ?? self::COR_PRINCIPAL_PADRAO;
        $destaque  = self::corValida($config['marca_cor_destaque'] ?? null) ?? self::COR_DESTAQUE_PADRAO;

        return [
            'logo'          => self::logoDataUri(),
            // O topo e o quadro de decisão têm texto branco sobre a cor principal: cor clara é escurecida.
            'cor_principal' => self::legivelComBranco($principal),
            'cor_destaque'  => $destaque,
            // Botões e textos na cor de destaque: a versão legível (a original continua nos detalhes).
            'cor_destaque_forte' => self::legivelComBranco($destaque),
            'rodape'        => self::textoRodape($config['marca_rodape'] ?? ''),
            'decoracao'     => self::decoracaoDataUris(),
        ];
    }

    /**
     * Arquivos de $_FILES enviados num campo múltiplo (nome[]), um por item, sem os campos vazios.
     *
     * @param array<string, mixed> $campo item de $_FILES de um input "multiple"
     * @return list<array<string, mixed>>
     */
    public static function arquivosEnviados(array $campo): array
    {
        if (!is_array($campo['error'] ?? null)) {
            $campo = array_map(static fn ($v): array => [$v], $campo);
        }
        $arquivos = [];
        foreach (array_keys($campo['error'] ?? []) as $i) {
            if ((int) $campo['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $arquivos[] = [
                'name'     => (string) ($campo['name'][$i] ?? ''),
                'type'     => (string) ($campo['type'][$i] ?? ''),
                'tmp_name' => (string) ($campo['tmp_name'][$i] ?? ''),
                'error'    => (int) $campo['error'][$i],
                'size'     => (int) ($campo['size'][$i] ?? 0),
            ];
        }

        return $arquivos;
    }

    /**
     * Primeira vaga livre (1 a DECORACAO_MAXIMO), dadas as vagas ocupadas. Null se todas estão ocupadas.
     *
     * @param list<int> $ocupadas
     */
    public static function vagaLivre(array $ocupadas): ?int
    {
        for ($vaga = 1; $vaga <= self::DECORACAO_MAXIMO; $vaga++) {
            if (!in_array($vaga, $ocupadas, true)) {
                return $vaga;
            }
        }

        return null;
    }

    /**
     * Elementos decorativos guardados, por vaga.
     *
     * @return array<int, string> vaga => caminho
     */
    public static function decoracoes(): array
    {
        $achados = [];
        for ($vaga = 1; $vaga <= self::DECORACAO_MAXIMO; $vaga++) {
            foreach (['webp', 'png'] as $extensao) {
                $caminho = self::pasta() . '/decoracao-' . $vaga . '.' . $extensao;
                if (is_file($caminho)) {
                    $achados[$vaga] = $caminho;
                    break;
                }
            }
        }

        return $achados;
    }

    /**
     * @return array<int, string> vaga => data URI
     */
    public static function decoracaoPorVaga(): array
    {
        $uris = [];
        foreach (self::decoracoes() as $vaga => $caminho) {
            $dados = @file_get_contents($caminho);
            if ($dados !== false) {
                $tipo = str_ends_with($caminho, '.webp') ? 'image/webp' : 'image/png';
                $uris[$vaga] = 'data:' . $tipo . ';base64,' . base64_encode($dados);
            }
        }

        return $uris;
    }

    /** @return list<string> */
    public static function decoracaoDataUris(): array
    {
        return array_values(self::decoracaoPorVaga());
    }

    public static function removerDecoracao(int $vaga): void
    {
        foreach (['webp', 'png'] as $extensao) {
            $caminho = self::pasta() . '/decoracao-' . $vaga . '.' . $extensao;
            if (is_file($caminho)) {
                @unlink($caminho);
            }
        }
    }

    public static function removerDecoracoes(): void
    {
        for ($vaga = 1; $vaga <= self::DECORACAO_MAXIMO; $vaga++) {
            self::removerDecoracao($vaga);
        }
    }

    /**
     * Guarda um elemento decorativo na primeira vaga livre: corta as margens transparentes, limita o
     * lado e grava em WebP (ou PNG, sem suporte a WebP). Exige o GD, para não embutir arquivos enormes.
     *
     * @param array<string, mixed> $arquivo item de $_FILES
     * @return string|null mensagem de erro, ou null se deu certo
     */
    public static function salvarDecoracao(array $arquivo): ?string
    {
        $nome = (string) ($arquivo['name'] ?? '');
        $vaga = self::vagaLivre(array_keys(self::decoracoes()));
        if ($vaga === null) {
            return sprintf(__('%1$s: a página aceita no máximo %2$d elementos decorativos. Remova algum antes.', 'propostas'), $nome, self::DECORACAO_MAXIMO);
        }
        if ((int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($arquivo['tmp_name'] ?? ''))) {
            return sprintf(__('%s: não foi possível receber o arquivo.', 'propostas'), $nome);
        }
        if ((int) ($arquivo['size'] ?? 0) > self::TAMANHO_MAXIMO) {
            return sprintf(__('%s: cada imagem pode ter no máximo 2 MB.', 'propostas'), $nome);
        }
        $origem = (string) $arquivo['tmp_name'];
        $info = @getimagesize($origem);
        $tipo = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!isset(self::TIPOS[$tipo])) {
            return sprintf(__('%s: envie a imagem em PNG ou WebP, com fundo transparente.', 'propostas'), $nome);
        }
        $imagem = self::carregarComGd($origem, $tipo);
        if ($imagem === null) {
            return sprintf(__('%s: o servidor não conseguiu abrir a imagem (extensão GD do PHP).', 'propostas'), $nome);
        }
        if (!is_dir(self::pasta()) && !@mkdir(self::pasta(), 0755, true)) {
            imagedestroy($imagem);
            return __('Não foi possível gravar a imagem na pasta de arquivos do plugin.', 'propostas');
        }

        $imagem = self::ajustar($imagem, self::DECORACAO_LADO, self::DECORACAO_LADO);
        imagealphablending($imagem, false);
        imagesavealpha($imagem, true);
        self::removerDecoracao($vaga);
        $ok = function_exists('imagewebp')
            ? imagewebp($imagem, self::pasta() . '/decoracao-' . $vaga . '.webp', 82)
            : imagepng($imagem, self::pasta() . '/decoracao-' . $vaga . '.png', 9);
        imagedestroy($imagem);

        return $ok ? null : __('Não foi possível gravar a imagem na pasta de arquivos do plugin.', 'propostas');
    }

    public static function pasta(): string
    {
        return GLPI_PLUGIN_DOC_DIR . '/propostas';
    }

    /** Caminho do logotipo guardado, se houver. */
    public static function caminhoLogo(): ?string
    {
        foreach (self::TIPOS as $extensao) {
            $caminho = self::pasta() . '/logo.' . $extensao;
            if (is_file($caminho)) {
                return $caminho;
            }
        }

        return null;
    }

    public static function logoDataUri(): ?string
    {
        $caminho = self::caminhoLogo();
        if ($caminho === null) {
            return null;
        }
        $tipo = array_search(pathinfo($caminho, PATHINFO_EXTENSION), self::TIPOS, true);
        $dados = @file_get_contents($caminho);

        return $tipo !== false && $dados !== false ? 'data:' . $tipo . ';base64,' . base64_encode($dados) : null;
    }

    public static function removerLogo(): void
    {
        foreach (self::TIPOS as $extensao) {
            $caminho = self::pasta() . '/logo.' . $extensao;
            if (is_file($caminho)) {
                @unlink($caminho);
            }
        }
    }

    /**
     * Guarda o logotipo enviado pelo formulário. Com o GD (exigido pelo GLPI), corta as margens
     * transparentes e reduz a altura; o resultado vira PNG. Sem o GD, guarda o arquivo como veio.
     *
     * @param array<string, mixed> $arquivo item de $_FILES
     * @return string|null mensagem de erro, ou null se deu certo
     */
    public static function salvarLogo(array $arquivo): ?string
    {
        if ((int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($arquivo['tmp_name'] ?? ''))) {
            return __('Não foi possível receber o arquivo do logotipo.', 'propostas');
        }
        $origem = (string) $arquivo['tmp_name'];
        if ((int) ($arquivo['size'] ?? 0) > self::TAMANHO_MAXIMO) {
            return __('O logotipo pode ter no máximo 2 MB.', 'propostas');
        }
        $info = @getimagesize($origem);
        $tipo = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!isset(self::TIPOS[$tipo])) {
            return __('Envie o logotipo em PNG, JPEG ou WebP.', 'propostas');
        }

        if (!is_dir(self::pasta()) && !@mkdir(self::pasta(), 0755, true)) {
            return __('Não foi possível gravar o logotipo na pasta de arquivos do plugin.', 'propostas');
        }
        self::removerLogo();

        $imagem = self::carregarComGd($origem, $tipo);
        if ($imagem !== null) {
            $imagem = self::ajustar($imagem);
            imagealphablending($imagem, false);
            imagesavealpha($imagem, true);
            $ok = imagepng($imagem, self::pasta() . '/logo.png', 9);
            imagedestroy($imagem);
        } else {
            $ok = @copy($origem, self::pasta() . '/logo.' . self::TIPOS[$tipo]);
        }

        return $ok ? null : __('Não foi possível gravar o logotipo na pasta de arquivos do plugin.', 'propostas');
    }

    private static function carregarComGd(string $origem, string $tipo): ?\GdImage
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $dados = @file_get_contents($origem);
        $imagem = $dados !== false ? @imagecreatefromstring($dados) : false;
        if (!$imagem instanceof \GdImage) {
            return null;
        }
        if (!imageistruecolor($imagem)) {
            imagepalettetotruecolor($imagem);
        }
        imagealphablending($imagem, false);
        imagesavealpha($imagem, true);

        return $imagem;
    }

    /**
     * Retângulo dos pixels visíveis (alfa abaixo de quase transparente). O imagecropauto do GD só
     * corta quando o canto tem exatamente a cor transparente, o que muitos PNG não têm.
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private static function areaVisivel(\GdImage $imagem): ?array
    {
        $largura = imagesx($imagem);
        $altura  = imagesy($imagem);
        $x0 = $largura;
        $y0 = $altura;
        $x1 = -1;
        $y1 = -1;
        for ($y = 0; $y < $altura; $y++) {
            for ($x = 0; $x < $largura; $x++) {
                // Alfa do GD: 0 = opaco, 127 = transparente.
                if (((imagecolorat($imagem, $x, $y) >> 24) & 0x7F) < 120) {
                    $x0 = min($x0, $x);
                    $x1 = max($x1, $x);
                    $y0 = min($y0, $y);
                    $y1 = max($y1, $y);
                }
            }
        }

        return $x1 < 0 ? null : ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0 + 1, 'height' => $y1 - $y0 + 1];
    }

    /** Corta as margens transparentes e limita a altura (e a largura, quando informada). */
    private static function ajustar(\GdImage $imagem, int $altura_maxima = self::ALTURA_MAXIMA, ?int $largura_maxima = null): \GdImage
    {
        $area = self::areaVisivel($imagem);
        if ($area !== null && ($area['width'] < imagesx($imagem) || $area['height'] < imagesy($imagem))) {
            $cortada = imagecrop($imagem, $area);
            if ($cortada instanceof \GdImage) {
                imagealphablending($cortada, false);
                imagesavealpha($cortada, true);
                imagedestroy($imagem);
                $imagem = $cortada;
            }
        }

        $largura = imagesx($imagem);
        $altura  = imagesy($imagem);
        $escala = min(1, $altura_maxima / $altura, $largura_maxima !== null ? $largura_maxima / $largura : 1);
        if ($escala < 1) {
            $nova_largura = max(1, (int) round($largura * $escala));
            $nova_altura  = max(1, (int) round($altura * $escala));
            $menor = imagecreatetruecolor($nova_largura, $nova_altura);
            imagealphablending($menor, false);
            imagesavealpha($menor, true);
            imagefill($menor, 0, 0, imagecolorallocatealpha($menor, 0, 0, 0, 127));
            imagecopyresampled($menor, $imagem, 0, 0, 0, 0, $nova_largura, $nova_altura, $largura, $altura);
            imagedestroy($imagem);
            $imagem = $menor;
        }

        return $imagem;
    }
}
