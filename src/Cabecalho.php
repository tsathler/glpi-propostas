<?php

/**
 * Propostas - textos do topo da página do cliente, sem repetir a razão social.
 *
 * Sem dependência do GLPI, para poder ser testado sozinho (tests/CabecalhoTest.php).
 * Só muda o que é exibido: o título gravado e o código de verificação continuam os mesmos.
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas;

final class Cabecalho
{
    /** A partir desta semelhança (%), o nome fantasia é tratado como repetição da razão social. */
    private const SEMELHANCA_REPETIDO = 85.0;

    /**
     * Linha abaixo da razão social: o título da proposta sem a razão social no fim
     * ("Plano B – Empresa Exemplo Ltda" vira "Plano B"). Título igual à razão social vira vazio.
     */
    public static function subtitulo(string $titulo, string $razao_social): string
    {
        $titulo = trim($titulo);
        $razao  = trim($razao_social);
        if ($razao === '' || $titulo === '') {
            return $titulo;
        }
        if (self::normalizar($titulo) === self::normalizar($razao)) {
            return '';
        }

        $tamanho = mb_strlen($razao);
        if (mb_strlen($titulo) > $tamanho && mb_strtolower(mb_substr($titulo, -$tamanho)) === mb_strtolower($razao)) {
            $resto = rtrim(mb_substr($titulo, 0, -$tamanho));
            $sem_separador = rtrim(preg_replace('/[\-–—·|:]+$/u', '', $resto) ?? $resto);
            // Só corta quando havia um separador: "Plano Empresa X" não vira "Plano".
            if ($sem_separador !== $resto) {
                return $sem_separador;
            }
        }

        return $titulo;
    }

    /**
     * Nome fantasia para exibir: vazio quando é igual ou quase igual à razão social (inclusive
     * cortado, como vem de alguns cadastros), para não repetir o nome da empresa.
     */
    public static function nomeFantasia(string $nome_fantasia, string $razao_social): string
    {
        $fantasia = trim($nome_fantasia);
        $f = self::normalizar($fantasia);
        $r = self::normalizar($razao_social);
        if ($f === '' || $r === '') {
            return $fantasia;
        }
        if (str_starts_with($r, $f) || str_starts_with($f, $r)) {
            return '';
        }
        // Compara com o começo da razão social, do mesmo tamanho: pega o nome cortado ou com uma letra trocada.
        similar_text($f, substr($r, 0, strlen($f)), $semelhanca);

        return $semelhanca >= self::SEMELHANCA_REPETIDO ? '' : $fantasia;
    }

    /** Minúsculas, sem acentos e só com letras e números. */
    private static function normalizar(string $texto): string
    {
        $texto = strtr(mb_strtolower($texto), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $texto) ?? '';
    }
}
