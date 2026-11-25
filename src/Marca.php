<?php

/**
 * Propostas - identidade visual da página do cliente: logotipo e cores.
 *
 * Configurado em Configuração > Plug-ins > Propostas. O logotipo fica na pasta de arquivos do
 * plugin (GLPI_PLUGIN_DOC_DIR/propostas) e vai embutido na página (data URI): a página pública
 * não carrega nada de fora dela. Sem configuração, a página fica com as cores de antes.
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
     * @return array{logo: ?string, cor_principal: string, cor_destaque: string, rodape: string}
     */
    public static function dadosPublicos(): array
    {
        $config = \Config::getConfigurationValues('plugin:propostas', ['marca_cor_principal', 'marca_cor_destaque', 'marca_rodape']);

        return [
            'logo'          => self::logoDataUri(),
            'cor_principal' => self::corValida($config['marca_cor_principal'] ?? null) ?? self::COR_PRINCIPAL_PADRAO,
            'cor_destaque'  => self::corValida($config['marca_cor_destaque'] ?? null) ?? self::COR_DESTAQUE_PADRAO,
            'rodape'        => self::textoRodape($config['marca_rodape'] ?? ''),
        ];
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

    /** Corta as margens transparentes e limita a altura. */
    private static function ajustar(\GdImage $imagem): \GdImage
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
        if ($altura > self::ALTURA_MAXIMA) {
            $nova_largura = max(1, (int) round($largura * self::ALTURA_MAXIMA / $altura));
            $menor = imagecreatetruecolor($nova_largura, self::ALTURA_MAXIMA);
            imagealphablending($menor, false);
            imagesavealpha($menor, true);
            imagefill($menor, 0, 0, imagecolorallocatealpha($menor, 0, 0, 0, 127));
            imagecopyresampled($menor, $imagem, 0, 0, 0, 0, $nova_largura, self::ALTURA_MAXIMA, $largura, $altura);
            imagedestroy($imagem);
            $imagem = $menor;
        }

        return $imagem;
    }
}
