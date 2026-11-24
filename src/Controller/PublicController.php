<?php

/**
 * Propostas - página pública da proposta (sem login).
 *
 * Rota: /plugins/propostas/p/{token}
 * Em produção, só esta rota deve ficar exposta para fora (ver docs/desenho.md).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Propostas\Proposta;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicController extends AbstractController
{
    #[Route(
        '/p/{token}',
        name: 'proposta_publica',
        requirements: ['token' => '[A-Za-z0-9_-]{43}'],
        methods: ['GET'],
    )]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function __invoke(string $token): Response
    {
        $proposta = Proposta::buscarPorToken($token);

        if ($proposta === null) {
            return $this->pagina('@propostas/publico/indisponivel.html.twig', [
                'titulo'   => 'Proposta não encontrada',
                'mensagem' => 'Este link não existe ou não é mais válido. Fale com o seu consultor para receber um link atualizado.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($proposta->isLinkExpirado() && $proposta->fields['status'] !== Proposta::STATUS_ACEITA) {
            return $this->pagina('@propostas/publico/indisponivel.html.twig', [
                'titulo'   => 'Proposta expirada',
                'mensagem' => sprintf(
                    'A validade da proposta nº %s terminou. Fale com o seu consultor para receber uma versão atualizada.',
                    $proposta->getNumero()
                ),
            ], Response::HTTP_GONE);
        }

        $proposta->registrarVisualizacao();

        return $this->pagina('@propostas/publico/proposta.html.twig', [
            'p' => $proposta->getDadosPublicos(),
        ]);
    }

    /**
     * @param array<string, mixed> $parametros
     */
    private function pagina(string $template, array $parametros, int $status = Response::HTTP_OK): Response
    {
        $response = new Response(status: $status);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');

        return $this->render($template, $parametros, $response);
    }
}
