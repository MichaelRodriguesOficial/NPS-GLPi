<?php

/**
 * Token CSRF novo para a avaliação do chamado pelo link do e-mail.
 *
 * Mesmo papel de ajax/public.token.php, mas autorizado pela chave
 * assinada do chamado (TicketLink::resolverPorChave), já que quem
 * responde não está logado. Liberado sem login no setup.php.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\TicketLink;

// v0.65.0: limite de requisições por IP nas rotas sem login.
\GlpiPlugin\Npsphere\RateLimit::proteger(\GlpiPlugin\Npsphere\RateLimit::PAGINA, true);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (TicketLink::resolverPorChave($_GET)['erro'] !== null) {
    http_response_code(403);
    exit;
}

TemplateRenderer::getInstance()->display('@npsphere/csrf_token.html.twig', []);
