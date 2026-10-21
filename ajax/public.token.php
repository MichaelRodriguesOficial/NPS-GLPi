<?php

/**
 * Token CSRF novo para a página pública (visitante sem login).
 *
 * Existe separado de ajax/csrf.token.php porque aquele exige
 * autenticação. Este é liberado para acesso anônimo no setup.php, e só
 * responde a quem apresentar um token de pesquisa válido -- não é um
 * gerador de tokens aberto a qualquer um.
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Npsphere\PublicAccess;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$resolved = PublicAccess::resolve($_GET['token'] ?? null);
if ($resolved['survey'] === null) {
    http_response_code(403);
    exit;
}

TemplateRenderer::getInstance()->display('@npsphere/csrf_token.html.twig', []);
