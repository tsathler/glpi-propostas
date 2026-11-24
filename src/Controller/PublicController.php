<?php

/**
 * Propostas - página pública da proposta (sem login).
 *
 * Rotas: GET  /plugins/propostas/p/{token}
 *        POST /plugins/propostas/p/{token}/aceitar
 *        POST /plugins/propostas/p/{token}/duvida
 * Em produção, só estas rotas devem ficar expostas para fora (ver docs/desenho.md).
 * Os POST passam pela checagem de CSRF do próprio GLPI (campo _glpi_csrf_token).
 *
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace GlpiPlugin\Propostas\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Propostas\Proposta;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicController extends AbstractController
{
    private const TOKEN = '[A-Za-z0-9_-]{43}';

    /** Envios (aceite + dúvida) permitidos por IP a cada hora. */
    private const LIMITE_POR_HORA = 10;

    #[Route('/p/{token}', name: 'proposta_publica', requirements: ['token' => self::TOKEN], methods: ['GET'])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function ver(Request $request, string $token): Response
    {
        $proposta = Proposta::buscarPorToken($token);
        $indisponivel = $this->verificarDisponivel($proposta);
        if ($indisponivel !== null) {
            return $indisponivel;
        }

        $proposta->registrarVisualizacao((string) $request->getClientIp(), (string) $request->headers->get('User-Agent', ''));

        return $this->paginaProposta($proposta, $token, [
            'sucesso' => match ($request->query->get('ok')) {
                'aceite' => 'aceite',
                'duvida' => 'duvida',
                default  => null,
            },
        ]);
    }

    // Sem "methods": o roteador de plugins do GLPI 11.0.4 (PluginsRouterListener) não passa o método da
    // requisição ao Symfony e compara tudo como GET, então uma rota só-POST nunca casa. O POST é
    // conferido aqui dentro.
    #[Route('/p/{token}/aceitar', name: 'proposta_aceitar', requirements: ['token' => self::TOKEN])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function aceitar(Request $request, string $token): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return new RedirectResponse($this->caminho($token), Response::HTTP_SEE_OTHER);
        }

        $proposta = Proposta::buscarPorToken($token);
        $indisponivel = $this->verificarDisponivel($proposta) ?? $this->verificarLimite($request);
        if ($indisponivel !== null) {
            return $indisponivel;
        }

        $nome = (string) $request->request->get('nome', '');
        $erros = $proposta->aceitar(
            $nome,
            (string) $request->request->get('cpf', ''),
            $request->request->get('declaracao') === '1',
            (string) $request->getClientIp(),
            (string) $request->headers->get('User-Agent', '')
        );
        if ($erros !== []) {
            return $this->paginaProposta($proposta, $token, [
                'form_aberto' => 'aceite',
                'erros'       => $erros,
                'valores'     => ['nome' => $nome],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->caminho($token) . '?ok=aceite', Response::HTTP_SEE_OTHER);
    }

    // Sem "methods": ver o comentário de aceitar().
    #[Route('/p/{token}/duvida', name: 'proposta_duvida', requirements: ['token' => self::TOKEN])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function duvida(Request $request, string $token): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return new RedirectResponse($this->caminho($token), Response::HTTP_SEE_OTHER);
        }

        $proposta = Proposta::buscarPorToken($token);
        $indisponivel = $this->verificarDisponivel($proposta) ?? $this->verificarLimite($request);
        if ($indisponivel !== null) {
            return $indisponivel;
        }

        $nome     = (string) $request->request->get('nome', '');
        $mensagem = (string) $request->request->get('mensagem', '');
        $erros = $proposta->registrarDuvida(
            $nome,
            $mensagem,
            (string) $request->getClientIp(),
            (string) $request->headers->get('User-Agent', '')
        );
        if ($erros !== []) {
            return $this->paginaProposta($proposta, $token, [
                'form_aberto' => 'duvida',
                'erros'       => $erros,
                'valores'     => ['nome' => $nome, 'mensagem' => $mensagem],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->caminho($token) . '?ok=duvida', Response::HTTP_SEE_OTHER);
    }

    /**
     * Link inexistente/revogado (404) ou expirado (410). Proposta aceita continua visível.
     */
    private function verificarDisponivel(?Proposta $proposta): ?Response
    {
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

        return null;
    }

    /**
     * Conta os envios do IP na última hora numa tabela própria (o IP fica só em hash).
     */
    private function verificarLimite(Request $request): ?Response
    {
        global $DB;

        $tabela  = 'glpi_plugin_propostas_tentativas';
        $ip_hash = hash('sha256', (string) $request->getClientIp());
        $agora   = new \DateTimeImmutable();
        $limite  = $agora->modify('-1 hour')->format('Y-m-d H:i:s');

        $DB->delete($tabela, ['date_creation' => ['<', $limite]]);
        $envios = countElementsInTable($tabela, [
            'ip_hash'       => $ip_hash,
            'date_creation' => ['>=', $limite],
        ]);
        if ($envios >= self::LIMITE_POR_HORA) {
            return $this->pagina('@propostas/publico/indisponivel.html.twig', [
                'titulo'   => 'Muitas tentativas',
                'mensagem' => 'Recebemos muitos envios a partir desta conexão. Aguarde alguns minutos e tente de novo, ou fale com o seu consultor.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
        $DB->insert($tabela, ['ip_hash' => $ip_hash, 'date_creation' => $agora->format('Y-m-d H:i:s')]);

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function paginaProposta(Proposta $proposta, string $token, array $extra = [], int $status = Response::HTTP_OK): Response
    {
        return $this->pagina('@propostas/publico/proposta.html.twig', $extra + [
            'p'           => $proposta->getDadosPublicos(),
            'acao_aceite' => $this->caminho($token) . '/aceitar',
            'acao_duvida' => $this->caminho($token) . '/duvida',
            'form_aberto' => null,
            'erros'       => [],
            'valores'     => [],
            'sucesso'     => null,
        ], $status);
    }

    /**
     * Caminho relativo ao host: funciona tanto pelo endereço interno quanto pelo externo.
     */
    private function caminho(string $token): string
    {
        return '/plugins/propostas/p/' . $token;
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
